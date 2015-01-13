#!/usr/bin/python3

'''
Perform a HTTP POST request after a delay.

stdin:

{
	'http_url':
	'data':
	'delay':
}

'''

import os
import sys
import json
import time
import datetime
import urllib
import http.client

def Now():
	return datetime.datetime.now(datetime.timezone.utc)

script_path = os.path.dirname(os.path.realpath(__file__))
log_path = script_path + '/../../ga-data/logs'

if not os.path.isdir(log_path):
	os.mkdir(log_path)

input_json = sys.stdin.read()
input_data = json.loads(input_json)
url = urllib.parse.urlparse(input_data['http_url'])

time.sleep(input_data['delay'])

cli = http.client.HTTPConnection(url.netloc)
cli.request('POST', url.path + '?' + url.query, urllib.parse.urlencode(input_data['data']))
response = cli.getresponse()
if response.status < 200 or response.status > 300:
	with open(log_path + '/post_' + Now().strftime('%Y%m%d-%H%M%S.%f') + '.log', 'w') as f:
		f.write(input_json + '\n')
		f.write(str(response.status) + ' ' + response.reason +  '\n')
		f.write(response.read().decode('UTF-8'))
