Instructor Notes
================

Xiangyu Bu

For educational use, here is a guideline of what the instructor should 
make clear to students about using GitLab.

Project Creation
================

In GitLab, forking a project does not create a new project, and thus gitlab-ag
cannot keep track of the forked project. You may want to use the "Create Repositories"
feature of gitlab-ag to create a bunch of projects with same files (by specifying 
"Import URL") for the students to use. Please refer to [Usage](usage.md) document for 
more detail.

Action Monitoring
=================

GitLab itself has detailed log of what happened at what time in admin area. All 
actions are logged. gitlab-ag may implement a log management system to supported 
automated event triggering and filtering. In other words, admins can see everything.

Project Visibility
==================

1. Project must be **private** and under the namespace of one **user** if the 
assignment is supposed to be finished without inter-student collaboration. Adding 
collaborators or setting higher visibility level are considered cheating.

2. Project can be put in the namespace of a group if the assignment requires teamwork, but 
most NOT be visible to people other than team members and instructors.

3. Public projects can only contain code that is unrelated to assignments.

Turn-in Instructions
====================

1. Do students need to submit the code to somewhere other than the designated GitLab
repository?

2. If GitLab is used to collect assignment submissions, which commit / push will be used 
for grading?

Automated Grading
=================

gitlab-ag requires all projects that need automated grading to be hooked to gitlab-ag web hook
end. 

Students need to know the consequences of invalidating (deleting, changing, etc.) the hook.

Students need to know which branch in their repo will be used for grading (by default, the branch marked "default" in project settings; by default, the branch named `master`). Changing this option on gitlab-ag requires modifying `ga-hook/delegates/ga-get_repo.py`.

Extra Features
==============

gitlab-ag supports creating projects in batch. However, there is no API in GitLab that supports
_forking_ a project to the namespace of a target user; so gitlab-ag does not support forking projects for users in batch. To cope with the problem, instructors may 
 * create a public, read-only project that contains the skeleton code and helper materials
 * give students the URL to this project, so that they can fork the project themselves.

GitLab provides full-featured Git experience, including Wiki, Issue Tracker, Tagging, etc.
An instructor may design rules to better make use of those features. For example, one may 
allow students to collaboratively write a wiki for an assignment (to make use of the wiki 
for the public project).

