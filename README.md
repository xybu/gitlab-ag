# gitlab-ag

AutoGrader (http://github.com/xybu/autograder) hook for GitLab (http://gitlab.com). In this system AutoGrader resides on the back, listening to events emitted by GitLab and responding according to predefined rules.

Besides, AutoGrader also provides interface for super user operations not available in GitLab.

## Installation

### Setup the gitlab-ag

gitlab-ag runs as a standalone website, not necessarily residing in the same machine as GitLab since GitLab and its hooks communicate via HTTP. 

What gitlab-ag requires is a web server (e.g., Nginx) and a PHP engine (e.g., Zend PHP). The internal database uses sqlite for simplicity.

First download the source code to a dir which will be the parent dir of gitlab-ag web root.

```
git clone https://github.com/xybu/gitlab-ag.git
```
or
```
wget https://codeload.github.com/xybu/gitlab-ag/zip/master
unzip master.zip
```

Enter `gitlab-ag` directory and on your webserver, create a new website whose root points here. If you use Nginx, there is a template configuration file bundled. You need to edit it and put it in `/etc/nginx/sites-enabled`:

```
mv nginx.conf.def nginx.conf
vim nginx.conf
# then finish the TODOs inside the file
sudo ln -s PATH_TO_/nginx.conf /etc/nginx/sites-enabled/gitlab-ag
sudo nginx -s reload
```

TBA.

### Setup GitLab

Here we assume GitLab has been installed. Here is what's next.
