# gitlab-ag

AutoGrader (http://github.com/xybu/autograder) hook for GitLab (http://gitlab.com).
In this system AutoGrader resides on the back, listening to events emitted by GitLab 
and responding according to predefined rules.

Besides, AutoGrader also provides interface for super user operations not available 
in GitLab.

## Features

gitlab-ag extends GitLab in the following ways:

 * You can create new users in batch.
 * You can create new repositories for target users in batch.
 * `gitlab-ag` provides system hooks to monitor GitLab user activity, and alerts you
   if a user's activity matches any preset rule.
 * `gitlab-ag` provides web hooks which enables further "submission collection" and 
   automated grading.

All those features better GitLab for education use.

## Installation

### Setup the gitlab-ag

gitlab-ag runs as a standalone website, not necessarily residing in the same machine 
as GitLab since GitLab and its hooks communicate via HTTP. 

What gitlab-ag requires is a web server (e.g., Nginx) and a PHP engine (e.g., Zend PHP). 
The internal database uses sqlite for simplicity.

First download the source code to a dir which will be the parent dir of gitlab-ag web root.

```
git clone https://github.com/xybu/gitlab-ag.git
```
or
```
wget https://codeload.github.com/xybu/gitlab-ag/zip/master
unzip master.zip
```

Enter `gitlab-ag` directory and on your web server, create a new website whose root points 
here. Fore security, besure to have this virtual website deny accesses to anywhere except 
for `ga-assets` and `index.php`.

Make sure your web worker user (for example, `www-data` is the default username for Nginx 
workers) has `RWX` permission on `gitlab-ag` directory and `ga-data` subdirectory.

Now open the previously created virtual website in your browser, and follow the installation 
guides. Be sure to take note of "App root password" and "App API access token" fields before 
proceeding. Once set, you cannot modify them unless you delete `ga-data/ga-config.php` file to re-enable
the installation guide. WARNING: modifying the file by hand voids its internal encryptions 
immediately.

### Setup GitLab

Here we assume GitLab has been installed. Here is what's next.
