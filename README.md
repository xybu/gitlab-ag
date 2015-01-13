# gitlab-ag

Extend [GitLab](http://gitlab.com) for education use:

 * **Import / delete users in batch**: import students from CSV when a new 
 semester starts, and delete all users after semester ends.
 * **Create / clone new projects in batch**: create new projects of same 
 parameters for (any set of) users, optionally cloning from an existing 
 git repository.
 * **Backup at push**: so a designated directory will always have the latest 
 copy of monitored projects in GitLab.
 * **System event logging and notification (planned)**: notify instructors 
 if a GitLab system event is emitted.
 * **Automated grading**: grading the submission after student pushes code 
 to GitLab.

gitlab-ag is built on top of PHP with no framework involved. Some auto-grading 
delegates are written in Python3k.

**Table of Contents**

 * [Installation](ga-docs/installation.md)
 * [Docker integration](ga-docs/docker.md)
 * [Usage](ga-docs/usage.md)
 * [Auto-grading](ga-docs/autograding.md)
 * [Security concerns](ga-docs/security.md)
 * [Instructor notes](ga-docs/instructor.md)

**License**

gitlab-ag is licensed under GNU v2.
