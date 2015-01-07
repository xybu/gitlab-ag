# Installation

The installation guide assumes GitLab has been set up on a Ubuntu-based server.
For other systems, the shell commands may need to change.

## Prerequisites

gitlab-ag requires `git` and `curl` packages installed on the machine, and needs
`python-daemonize` package for Python3k. Besides, since it needs to run as a 
standalone website, `PHP`(>5.4) with PDO and SQLite support and a web server 
like `Nginx` (recommended) are needed. PHP extensions like `APCu` and `opcache`
are recommended. Ubuntu has built-in support for Python.
Here is a guide for [setting up a Ubuntu server from scratch](http://xybu.me/setting-up-a-ubuntu-server/).

```bash
sudo apt-get install git curl pip3
sudo pip3 install daemonize
```

[Docker](http://docker.com/) is recommended as a container to securely run 
grading programs. Refer to `docker.md` for installation.

## Install gitlab-ag

(1) Grab the source code

First download the source code to a dir which will be the parent dir of gitlab-ag 
web root.

```bash
git clone https://github.com/xybu/gitlab-ag.git
```
or
```bash
wget https://codeload.github.com/xybu/gitlab-ag/zip/master
unzip master.zip
```

Rename the generated directory if necessary and enter it. We assume the 
default name `gitlab-ag`.

(2) Change credentials

This step can be skipped with little harm to security.

Open `ga-include/ga-session.php` and change the constant `SESSION_SALT` to 
a more complex string.

(3) Change file permissions

The following directories need `RWX` permission on web server worker user:

 * ga-data
 * ga-hook/logs
 * ga-hook/pushes
 * ga-hook/queue
 * ga-hook/fails

The following files need `RX` permission on web server worker user:

 * ga-hook/delegates/ga-get_repo.py
 * ga-hook/delegates/ga-grader_queue.py

Other files should have `RX` (for `.php` code) or `R` permission for web server 
worker user.

(4) Set-up web server

gitlab-ag requires two virtual sites to be added to your web server.

An example config file for Nginx, named `nginx.conf.example`, is given for reference.

First create a site whose root dir points to `gitlab-ag`. The port can be picked 
up arbitrarily as long as not used. This site is the admin panel of gitlab-ag and 
should be visible to public (or at least yourself). For security, be sure to have 
this virtual website deny accesses to anywhere except for `ga-assets`, `index.php`,
and `admincp.php`.

Second create a site whose root dir points to `gitlab-ag/ga-hook`. The port should 
be secret, and the site should be accessible only from GitLab host and gitlab-ag
machine (if they are on the same machine, deny all accesses from other than 
`127.0.0.1`). Set up URL rewrite rule so that `webhook/123` can be redirected to `ga-webhook.php?key=123`. For Nginx, put the following line in a `server` block:

```
rewrite ^/(webhook|syshook|callback)/(.*)$ /ga-$1.php?key=$2? last;
```

For extra security, make 100% sure that the hook site cannot be accessed by 
public users. And make sure no user except for web server worker on the machine can access `ga-data`. (For example, for Nginx, set the owner and group of `ga-data` to `www-data` and 
permission bits to `0700`).

(5) Run the installer

In your browser, visit `http://url_to_gitlab_ag/`. For example, if the site with root 
`gitlab-ag` listens to port `8080` and the machine domain is `example.com`, then open 
`http://example.com:8080/`. An installer webpage will show up. Fill in the form carefully
and hit `Hook with GitLab` button, upon success gitlab-ag will add its system hook url 
to GitLab. And then click `Install` button to its right and the config file 
`ga-data/ga-config.php` will be generated. To rerun the installer, you need to delete this 
file and visit the gitlab-ag url.


## Setup GitLab

There is no particular action to do on GitLab side. However, if there are already projects
in GitLab and you want gitlab-ag to monitor them also, you may need to add webhooks manually.
New projects will be added gitlab-ag hook automatically.
