#!/usr/bin/python3

'''
A command queue daemon. This prevents system outages.

When the daemon starts, it must accept the following JSON text from 
file "ga-data/ga-grader_queue.cfg":
{
	"delegate_callback": "http://127.0.0.1:8099",
	"temp_path": "/tmp/ag"
}

Main thread monitors a file-based event queue.
An event file should be a JSON consists of the following keys:
{
	"project_id": 123,
	"project_name": "/john/lab1-src",
	"delegate_key": "dsfdsf",
	"merge_dir": [
		"/path/to/student_repo",
		"/path/to/tester_repo_1",
		"/path/to/other_dir_if_necessary"
	]
}

An item in task_queue is event plus a key 'temp_path'.

Grader will run `test_all` under this temp_path. The `test_all` must be executable,
and its stdout should be like the following:

Test case 1: basic case (10 pts / 10 pts)
Output number too large.

Test case 2: name (n pts / N pts)
Description.

......

<summary>
grade_total = 30
grade_datetime = YYYY-mm-dd HH:MM:SS +Z
</summary>

stdout ends here. The <summary> node contains machine-readable info, in INI format. 
The parameter grade_total, setting the total score for this submission, is required. 
The text outside of <summary> node is ignored by gitlab-ag, but will be sent to the 
student to read.

Its stderr is for debugging and will be sent back to hook. However students will not see its content.

Items in result_queue are dictionaries with keys 

{
	'project_id': 123,
	'project_name': 'root/lab1-src',
	'delegate_key': "dsfdsf",
	'grade_data': None,
	'grade_log': ''
}

'''

import os
import sys
import stat
import time
import json
import shutil
import datetime
import logging
import threading
import subprocess
import queue
import http.client
from daemonize import Daemonize

# Set to False to disable Docker integration
docker_enabled = True

# The Docker image name for virtualization
docker_image_name = 'xybu/cdev:v1'

# The number of grader threads
num_of_graders = 4

# Time of sleep between main thread scans file queue
main_sleep_time = 600  # in seconds

# Force the grader to proceed if test_all does not finish after this time
grader_timeout = 900 # in seconds

# Reporter will try to report result this max number of times facing HTTP error
max_reporter_retry = 10

script_path = os.path.dirname(os.path.realpath(__file__))
queue_path = script_path + '/../../ga-data/queue'
queue_failed_path = script_path + '/../../ga-data/queue_failed'
cfg_file = script_path + '/../../ga-data/ga-grader_queue.cfg'
pid_file = script_path + '/../../ga-data/ga-grader_queue.pid'
log_file = script_path + '/../../ga-data/ga-grader_queue.log'

logging.basicConfig(format='[%(asctime)-15s] %(threadName)s: %(message)s')
logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG)
logger.propagate = False
logger_fh = logging.FileHandler(log_file, 'w')
logger_fh.setLevel(logging.DEBUG)
logger.addHandler(logger_fh)
daemon_keep_fds = [logger_fh.stream.fileno()]

config = None
task_queue = None
result_queue = None
worker_semaphore = None
result_semaphore = None

def VirtualizedCmd(cmd, mount = [], memory = '256m', net = 'none', runas = 'slave', cwd = None):
	'''
	runas should be a username inside the docker image.
	'''
	docker_args = ['docker', 'run', '-t', '--cpu-shares', '25', '--memory', memory, '--user', runas, '--net', net]
	for x in mount: docker_args += ['--volume', x]
	if cwd != None: docker_args += ['-w', cwd]
	docker_args += [docker_image_name] + cmd
	return docker_args

def Now():
	return datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S.%f')
	
class GraderThread(threading.Thread):
	
	def __init__(self, name):
		super().__init__()
		self.daemon = True
		self.name = name
	
	def run(self):
		logger.debug(self.name + ' started.')
		
		while True:
			worker_semaphore.acquire()
			task = task_queue.get()
			task_queue.task_done()
			grade_result = {
				'project_id': task['project_id'],
				'project_name': task['project_name'],
				'delegate_key': task['delegate_key'],
				'grade_data': None,
				'grade_log': None
			}
			logger.debug(task)
			try:
				if not os.path.isfile(task['temp_path'] + '/test_all'):
					logger.critical('grader file "' + task['temp_path'] + '/test_all" not found.')
					raise Exception('Executable "test_all" was not found.')
				
				if docker_enabled:
					cmd = VirtualizedCmd(['/ag/test_all'], mount=[task['temp_path'] + ':/ag'], cwd='/ag')
				else:
					cmd = [task['temp_path'] + '/test_all']
				
				logger.debug(cmd)
				subp = subprocess.Popen(cmd, cwd=task['temp_path'], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
				sout, serr = subp.communicate(None, timeout = grader_timeout)
				sout = sout.decode('UTF-8')
				serr = serr.decode('UTF-8')
				if '<summary>' not in sout:
					sout = sout + "\n<summary>grade_total=0\n</summary>\n"
				grade_result['grade_data'] = sout
				grade_result['grade_log'] = serr
			except Exception as e:
				logger.warning(str(e))
				grade_result['grade_data'] = "An internal error occurred. \nPlease contact instructors for more detail, or retry later.\n<summary>grade_total=0\n</summary>\n"
				grade_result['grade_log'] = str(e)
			
			result_queue.put(grade_result)
			result_semaphore.release()
			
			try:
				shutil.rmtree(task['temp_path'], ignore_errors=True)
				os.rmdir(task['temp_path'])
				pass
			except:
				pass

class ReporterThread(threading.Thread):
	
	def __init__(self):
		super().__init__()
		self.daemon = True
		self.name = 'ga-reporter'
	
	def run(self):
		logger.debug(self.name + ' started.')
		while True:
			result_semaphore.acquire()
			item = result_queue.get()
			result_queue.task_done()
			try:
				cli = http.client.HTTPConnection(config['delegate_callback'].split('://', 1)[1])
				cli.request('POST', '/callback/' + item['delegate_key'], json.dumps(item))
				response = cli.getresponse()
				if response.status < 200 or response.status > 300:
					# if there is network problem, try again later
					if 'attempt_count' not in item:
						item['attempt_count'] = 1
					else:
						item['attempt_count'] += 1
					if item['attempt_count'] < max_reporter_retry:
						result_queue.put(item)
						result_semaphore.release()
					raise Exception('HTTP' + str(response.status) + ' ' + response.reason + '\n' + json.dumps(item))
			except Exception as e:
				logger.critical(str(e))
	
def main():
	
	global config
	global worker_semaphore
	global result_semaphore
	global task_queue
	global result_queue
	
	logger.info('grader queue started.')
	
	if os.path.isfile(queue_path): os.remove(queue_path)
	if not os.path.exists(queue_path):
		os.makedirs(queue_path)
		os.chmod(queue_path, stat.S_IRWXU | stat.S_IRWXG)
	if not os.path.exists(queue_failed_path): 
		os.makedirs(queue_failed_path)
		os.chmod(queue_failed_path, stat.S_IRWXU | stat.S_IRWXG)
	
	logger.debug('path checking completed.')

	try:
		logger.debug('loading config from "' + cfg_file + '".')
		f = open(cfg_file, 'r')
		config = json.loads(f.read())
		f.close()
		logger.info(config['delegate_callback'])
		logger.info(config['temp_path'])
	except Exception as e:
		logger.critical('cannot load config from "' + cfg_file + '": ' + str(e))
		sys.exit(1)
	
	logger.info('input is correct.')
	
	if not os.path.exists(config['temp_path']):
		os.makedirs(config['temp_path'])
		os.chmod(config['temp_path'], stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)
	
	worker_semaphore = threading.Semaphore(0)
	result_semaphore = threading.Semaphore(0)
	task_queue = queue.Queue()
	result_queue = queue.Queue()
	# sys.stdout = open(script_path + '/../../ga-data/ga-grader_queue.out', 'w')
	# sys.stderr = open(script_path + '/../../ga-data/ga-grader_queue.err', 'w')
	os.chdir(queue_path)
	
	ReporterThread().start()
	for i in range(num_of_graders):
		GraderThread('grader-' + str(i)).start()
	
	while True:
		# logger.debug('start scanning dir.')
		task_files = os.listdir()
		for filename in task_files:
			logger.info('processing file "' + filename + '"')
			try:
				with open(filename, 'r') as f:
					t = json.loads(f.read())
					t_dir = config['temp_path'] + '/' + t['project_name'].replace('/', '_') + '_' + Now()
					os.mkdir(t_dir)
					os.chmod(t_dir, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)
					for d in t['merge_dir']:
						ret = subprocess.call('cp -R ' + d + '/* ' + t_dir + '/', shell=True)
						if ret != 0: raise Exception('Failed on command "cp -R ' + d + ' ' + t_dir + '/"')
					subprocess.call('chmod -R 777 ' + t_dir + '/*', shell=True)
					t['temp_path'] = t_dir
					task_queue.put(t)
					worker_semaphore.release()
				os.remove(queue_path + '/' + filename)
			except Exception as e:
				logger.warning(str(e))
				try:
					os.rename(filename,  queue_failed_path + '/' + filename)
				except Exception as e:
					logger.warning(str(e))
		
		time.sleep(main_sleep_time)

Daemonize(app="ga-command_queue", pid=pid_file, action=main, keep_fds = daemon_keep_fds).start()
# main()

logger.info('grader queue stopped.')

