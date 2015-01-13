#!/usr/bin/python3

'''
get_repo.py

Python delegate that pulls all files in a repository to a local folder.
A delegate is introduced to keep the PHP session as short as possible.
Delegate key id is generated randomly when the task is assigned.

Data will be put to a file (name specified in argv[1]) in ../repos/. It will be JSON like

{
	"delegate_callback": "http://127.0.0.1:8099",
	"delegate_key": "123",
	"archive_root_path": "/path/to/archive/root",
	"gitlab_url": "http://127.0.0.1",
	"gitlab_admin_user": "root",
	"gitlab_admin_pass": "5iveL!fe",
	"push_event": {
		"before": "95790bf891e76fee5e1747ab589903a6a1f80f22",
		"after": "da1560886d4f094c3e6c9ef40349f7d38b5d27d7",
		"ref": "refs/heads/master",
		"user_id": 4,
		"user_name": "John Smith",
		"project_id": 15,
		"repository": {
			"name": "Diaspora",
			"url": "git@example.com:diaspora.git",
			"description": "",
			"homepage": "http://example.com/diaspora"
		},
		"commits": [{
			"id": "b6568db1bc1dcd7f8b4d5a946b0b91f9dacd7327",
			"message": "Update Catalan translation to e38cb41.",
			"timestamp": "2011-12-12T14:27:31+02:00",
			"url": "http://example.com/diaspora/commits/b6568db1bc1dcd7f8b4d5a946b0b91f9dacd7327",
			"author": {
				"name": "Jordi Mallach",
				"email": "jordi@softcatala.org"
			}
		},
		{
			"id": "da1560886d4f094c3e6c9ef40349f7d38b5d27d7",
			"message": "fixed readme",
			"timestamp": "2012-01-03T23:36:29+02:00",
			"url": "http://example.com/diaspora/commits/da1560886d4f094c3e6c9ef40349f7d38b5d27d7",
			"author": {
				"name": "GitLab dev user",
				"email": "gitlabdev@dv6700.(none)"
			}
		}],
		"total_commits_count": 4
	}
}

'''

import os
import sys
import json
# import signal
import subprocess
import http.client
import datetime

script_path = os.path.dirname(os.path.realpath(__file__))
log_path = script_path + '/../../ga-data/logs'
push_path = script_path + '/../../ga-data/pushes'
task_json = None
task_json_raw = ''
archive_raw = ''

# Prevent zombie process
# signal.signal(signal.SIGCHLD, signal.SIG_IGN)

def Now():
	return datetime.datetime.now(datetime.timezone.utc)

def LogException(src, ex, info = None):
	if not os.path.isdir(log_path):
		if os.path.exists(log_path):
			try:
				os.remove(log_path)
			except:
				pass
		try:
			os.mkdir(log_path)
		except:
			pass
	with open(log_path + '/get_repo_' + Now().strftime('%Y%m%d-%H%M%S.%f') + '_' + src + '.log', 'w') as f:
		f.write(str(ex) + '\n\n')
		if info != None: f.write('Reference Info:\n' + str(info))

f = open(push_path + '/' + sys.argv[1], 'r')
task_json_raw = f.read()
f.close()

try:
	task_json = json.loads(task_json_raw)
except Exception as e:
	LogException('get_repo', e, task_json_raw)
	sys.exit(1)

# Remove the '.git' tail
target_repo_path = task_json['archive_root_path'] + '/' + task_json['push_event']['repository']['url'].split(':', 1)[1][:-4]

if os.path.exists(target_repo_path) and os.path.isdir(target_repo_path):
	# if the repository is saved before, run a git pull to update it
	print('Running "git pull" on ' + target_repo_path)
	subprocess.call(['git', 'pull'], cwd = target_repo_path)
else:
	try:
		user_namespace = os.path.dirname(target_repo_path)
		git_repo_url = task_json['push_event']['repository']['homepage'] + '.git'
		git_repo_url = git_repo_url.replace('://', '://' + task_json['gitlab_admin_user'] + ':' + task_json['gitlab_admin_pass'] + '@')
		
		if os.path.isfile(target_repo_path): os.remove(target_repo_path)
		if not os.path.exists(user_namespace): os.makedirs(user_namespace)
		
		subprocess.call(['git', 'clone', git_repo_url], cwd = user_namespace)
	except Exception as e:
		LogException('get_repo', e)
		sys.exit(1)

post_data = {
	'project_id': task_json['push_event']['project_id'],
	'result': 'needs_grading'
}

cli = http.client.HTTPConnection(task_json['delegate_callback'].split('://', 1)[1])
cli.request('POST', '/callback/' + task_json['delegate_key'], json.dumps(post_data))
response = cli.getresponse()
if response.status < 200 or response.status > 300:
	LogException('get_repo', response.read())
else:
	os.remove(push_path + '/' + sys.argv[1])

