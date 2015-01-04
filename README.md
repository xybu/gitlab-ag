# gitlab-ag

AutoGrader (http://github.com/xybu/autograder) hook for GitLab (http://gitlab.com).
In this system AutoGrader resides on the back, listening to events emitted by GitLab 
and responding according to predefined rules.

Besides, AutoGrader also provides interface for super user operations not available 
in GitLab.

## Features

gitlab-ag extends GitLab in the following ways:

 * You can create (import) / delete users in batch.
 * You can create new repositories for target users in batch.
 * `gitlab-ag` provides system hooks to monitor GitLab user activity, and alerts you
   if a user's activity matches any preset rule.
 * `gitlab-ag` provides web hooks which enables further "submission collection" and 
   automated grading.

All those features better GitLab for education use.

Besides, gitlab-ag is built on top of PHP with no framework involved. This makes the system
fast and less resource-intensive.

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

Open `ga-include/ga-session.php` and change the constant `SESSION_SALT` to a complex string.

Enter `gitlab-ag` directory and on your web server, create a new website whose root dir points 
here. Fore security, be sure to have this virtual website deny accesses to anywhere except 
for `ga-assets` and `index.php`.

And then create another virtual site whose root dir points to `ga-hook`, making sure this site is only accessible by your GitLab system (NOT the users of your GitLab instance) using internal IP address and port.

Make sure your web worker user (for example, `www-data` is the default username for Nginx 
workers) has `RWX` permission on `gitlab-ag` directory and `ga-data` subdirectory, and make sure
no other user access `ga-data` (probably set the owner and group of `ga-data` to `www-data` and 
permission bits to `0700`).

Make sure the access log of your web server is not readable by low-privilege users. If someone can 
get alive session data from disk and find the user-agent string associated with that session in server
access log, security may be compromised.

Now open the previously created virtual website in your browser, and follow the installation 
guides. Be sure to take note of "App root password" and "App API access token" fields before 
proceeding. Once set, you cannot modify them unless you delete `ga-data/ga-config.php` file to re-enable
the installation guide. WARNING: modifying the file by hand voids its internal encryptions 
immediately.

### Setup GitLab

Here we assume GitLab has been installed. Here is what's next.

## Security Concerns

Read the comments at the beginning of the following files:

 * `ga-include/ga-installer.php`
 * `ga-include/ga-session.php`

## Usage

The layout of gitlab-control panel consists of four parts: navbar (the bar at the top showing menus and sign out link), breadcrumb (the bar below navbar that shows your current position), content (the part under breadcrumb and above footer), and footer (the bottom bar shows copyright info and performance counter).

### First Use



### Import Users

To import users from a CSV file, first sign in to gitlab-ag control panel, then in the navbar menu, choose "Users" - "Import Users". In the content panel will show the control for pasting CSV data.

The first line of the CSV data must be the header of columns. Three columns are required: `User ID` (matching `username` field on GitLab), `NAME` (matching `name`), and `EMAIL` (matching `email`). Besides, `ID` column, if exists, will be matched to `extern_uid` field on GitLab.

The password for each user will be generated randomly. You will need to make an announcement to the people to ask them reset their passwords on GitLab using "forget password" link on GitLab sign-in page.

Besides, you can set the maximum number of projects a user can have to prevent the users from creating projects that are unrelated to your GitLab purpose. Non-positive number means no limit.

There are two additional options, one for allowing users to create their own groups, and one for importing the users as GitLab administrators. Use with caution.

When you hit "Start" button, a progress bar will show up to indicate progress. If there are any errors in the middle, an error message will show up below the progress bar.

