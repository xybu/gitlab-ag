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

Grader will run `test_all` under this temp_path. The `test_all` must be runnable,
and its stdout must be a JSON string of format

{
	'grade_total': 100,
	'grade_detail': {
		'example_case_1': {
			'grade': 10,
			'reason': 'good!'
		},
		'example_case_2': {
			'grade': 0,
			'reason': 'you forgot something.'
		}
	}
	...
}

Its stderr is for debugging and will be sent back to hook.

Items in result_queue are dictionaries with keys 

{
	'project_id': 123,
	'project_name': 'root/lab1-src',
	'delegate_key': "dsfdsf",
	'grade': 0,
	'grade_data': None,
	'grade_log': ''
}

'''

import os
import sys
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

docker_enabled = True
docker_image_name = 'xybu/c_dev:jan_15'

num_of_graders = 2
main_sleep_time = 10 # in seconds
grader_timeout = 1800 # in seconds

script_path = os.path.dirname(os.path.realpath(__file__))
cfg_file = script_path + '/../../ga-data/ga-grader_queue.cfg'
pid_file = script_path + '/../../ga-data/ga-grader_queue.pid'
log_file = script_path + '/../../ga-data/ga-grader_queue.log'

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

def VirtualizedCmd(cmd, stdin = None, mount = [], memory = '256m', net = 'none', runas = 'slave', cwd = None):
	docker_args = ['docker', 'run', '-t', '-i', '--attach', 'STDIN,STDOUT,STDERR', '--cpu-shares', '25', '--memory', memory, '--user', runas, '--net', net]
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
				'grade': 0,
				'grade_data': None,
				'grade_log': ''
			}
			try:
				if not os.path.isfile(task['temp_path'] + '/test_all'):
					raise Exception('Executable "test_all" was not found.')
				cmd = [task['temp_path'] + '/test_all']
				if docker_enabled:
					cmd = VirtualizedCmd(cmd, mount=[task['temp_path'] + ':/home'], cwd='/home')
				subp = subprocess.Popen(cmd, cwd=task['temp_path'], stdin=subprocess.Pipe, stdout=subprocess.Pipe, stderr=subprocess.Pipe)
				sout, serr = subp.communicate(None, timeout = grader_timeout)
				grade_json = json.loads(sout)
				grade_result['grade'] = grade_json['grade_total']
				grade_result['grade_data'] = grade_json['grade_detail']
				grade_result['grade_log'] = serr
			except Exception as e:
				logger.warning(str(e))
				grade_result['grade_log'] = str(e)
			
			result_queue.put(grade_result)
			result_semaphore.release()
			
			try:
				shutil.rmtree(task['temp_path'], ignore_errors=True)
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
					raise Exception('HTTP' + str(response.status) + ' ' + response.reason)
			except:
				result_queue.put(item)
				result_semaphore.release()
	
def main():
	
	global config
	global worker_semaphore
	global result_semaphore
	global task_queue
	global result_queue
	
	logger.info('grader queue started.')
	
	event_root_dir = script_path + '/../queue'
	if os.path.isfile(event_root_dir): os.remove(event_root_dir)
	if not os.path.exists(event_root_dir): os.makedirs(event_root_dir)
	if not os.path.exists(script_path + '/../fails'): os.makedirs(script_path + '/../fails')
	
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

	worker_semaphore = threading.Semaphore(0)
	result_semaphore = threading.Semaphore(0)
	task_queue = queue.Queue()
	result_queue = queue.Queue()
	
	ReporterThread().start()
	for i in range(num_of_graders):
		GraderThread('grader-' + str(i)).start()
	
	os.chdir(event_root_dir)
	
	while True:
		task_files = os.listdir()
		for filename in task_files:
			logger.info('processing file "' + filename + '"')
			try:
				with open(filename, 'r') as f:
					t = json.loads(f.read())
					t_dir = config['temp_path'] + '/' + t['project_name'].replace('/', '_') + Now()
					os.makedirs(t_dir)
					for d in t['merge_dir']:
						ret = subprocess.call('cp -R ' + d + ' ' + t_dir + '/', shell=True)
						if ret != 0: raise Exception('Failed on command "cp -R ' + d + ' ' + t_dir + '/"')
					subprocess.call('chmod -R +rwx ' + t_dir + '/*', shell=True)
					t['temp_path'] = t_dir
					task_queue.put(t)
					worker_semaphore.release()
				os.remove(filename)
			except Exception as e:
				logger.warning(str(e))
				try:
					os.rename(filename, '../fails/' + filename)
				except:
					pass
			
					
		time.sleep(main_sleep_time)

Daemonize(app="ga-command_queue", pid=pid_file, action=main, keep_fds = daemon_keep_fds).start()

logger.info('grader queue stopped.')

