## Automated Grading

**STILL A DRAFT DOC**

gitlab-ag uses GitLab Web Hook API to receive events that may trigger automated 
grading. At installation gitlab-ag should successfully add a system hook to 
GitLab. Projects created after that point of time will be added a gitlab-ag 
webhook automatically. 

When someone pushes to a repository, gitlab-ag will receive a `push` event from
its webhook. First gitlab-ag will `git pull` the repository to its archive 
directory. Second, if there is a project whose name is the name of the pushed 
one appended by *-test* under the specified GitLab root user, and the pushed 
project does not belong to the root user, gitlab-ag will initiate the grader 
daemon, if not started yet, and queue a grading task. For instance, if a user 
whose username is `xb` pushes a repository called `lab1-src`, then the directory 
`/path_to_specified_archive_dir/xb/lab1-src` will be in sync with GitLab's 
`xb/lab1-src` project. And if there exists a project called `root/lab1-src-test`,
where `root` is the registered root user in gitlab-ag, then the followinig will 
happen:

 * A file with event data (in JSON format) will be created in `ga-hook/queue/`.
 * `ga-hook/delegates/ga-grader_queue.py` will run if not running yet.
 * The daemon will monitor `queue` dir and process the event files.
 * If there is an event file, then 
	 * create a temp dir under the designated temp dir of gitlab-ag;
	 * recursively copy all the subdirs and files in `xb/lab1-src` to this temp dir;
	 * recursively copy all the subdirs and files in `root/lab1-src-test` to this temp dir; existing files will be overwritten;
	 * try running the script file called `test_all` under this dir;
	 * grab the output of the `test_all` process and send it back to `gitlab-ag`.

The file called `test_all` can be written in any language as long as it is _executable_.

Its `STDOUT` must print something like

```javascript
{
	'grade_total': 98,
	'grade_detail': {
		'test_case_1': {
			'grade': 10,
			'reason': 'good!'
		},
		'test_case_2': {
			'grade': 0,
			'reason': 'you forgot something.'
		}
	}
	...
}
```

and its `STDERR` can print debug info for this grading session, which will also be sent back to gitlab-ag.

The script `test_all` will run under the the same user as grader_queue daemon, which is likely to be the worker of your web server. Thus, this script MUST do whatever it needs to secure the system. For example, change `euid` to an underprivileged user, run the testee program in a container / jail / VM, and use file system sandboxing to protect the physical file system from unwanted changes.
