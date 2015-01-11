## Automated Grading

gitlab-ag uses GitLab Web Hook API to receive events that may trigger automated 
grading. At installation time gitlab-ag should successfully add a system hook to 
GitLab. Projects created after that will be added a gitlab-ag webhook automatically. 

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

 * A file with event data (in JSON format) will be created in `ga-data/queue/`.
 * `ga-hook/delegates/ga-grader_queue.py` will run if not running yet.
 * The daemon will monitor `queue` dir and process the event files.
 * If there is an event file, then 
	 * create a temp dir under the designated temp dir of gitlab-ag;
	 * recursively copy all the subdirs and files in `xb/lab1-src` to this temp dir;
	 * recursively copy all the subdirs and files in `root/lab1-src-test` to this temp
		dir; existing files will be overwritten;
	 * try running the script file called `test_all` under this dir (virtualization 
		support by Docker);
	 * grab `STDOUT` and `STDERR` of `test_all` process and send it back to 
		gitlab-ag callback end;
	 * `<summary>` node that contains `grade_total` will be added to gradebook;
	 * the project owner will receive an email containing the `STDOUT` of `test_all` process.

The file called `test_all` can be written in any language as long as it is _executable_.

The `STDOUT` must print something like

```html
whatever_you_want_to_tell_students

<summary>
grade_total = THE_GRADE_YOU_WANT_TO_RECORD
</summary>

whatever_you_want_to_tell_students
```

Whatever is outside `<summary>` tag, for example, the two 
`whatever_you_want_to_tell_students`, is ignored by gitlab-ag. Inside of 
it is the metadata for the grading result in INI format. The key 
`grade_total` equals the total score for the grading and thus must exist.
The whole `STDOUT` text will be sent to the student.

Its `STDERR` can contain debug text for this grading session, which will 
also be sent back to gitlab-ag to archive.

The script `test_all` will run under the the same user as grader_queue daemon, 
which is likely to be the worker of your web server, UNLESS you correctly 
install and enable Docker. Thus, if Docker is not enabled, then this script 
MUST do whatever it needs to secure the system. For example, change `euid` to
an underprivileged user, run the testee program in a container / jail / VM, and
use file system sandboxing to protect the physical file system from unwanted 
changes.


## Parameters

There are several parameters that you may want to change in 
`ga-hook/delegates/ga-grader_queue.py`:

 * `docker_enabled`: set `True` to enable Docker, or `False` to disable it.
 * `docker_image_name`: the name of Docker image to use for virtualized environment.
 * `num_of_graders`: the max _number_ of grading sessions running concurrently.
 * `main_sleep_time`: main thread will be put to sleep for this specified _seconds_ if 
 	no task in the queue.
 * `grader_timeout`: the max number of _seconds_ a grading session can last
 * `max_reporter_retry`: the max _number_ of attempts to report grading result to gitlab-ag
 	if HTTP error is encountered.

Besides, in function `VirtualizedCmd()`, you can change the max amount of memory
(default: `256m` for `256 MiB`), networking method (default: `none` for no network), 
slave user (default: `slave`, a underprivileged user in default Docker image `xybu/cdev:v1`),
etc., of Docker environment. Refer to [`docker run`](https://docs.docker.com/reference/run/)
for more details.
