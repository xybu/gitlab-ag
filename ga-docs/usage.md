Usage
=====

The layout of gitlab-control panel consists of four parts: navbar (the bar at the top showing menus and sign out link), breadcrumb (the bar below navbar that shows your current position), content (the part under breadcrumb and above footer), and footer (the bottom bar shows copyright info and performance counter).

## Import Users

To import users from a CSV file, first sign in to gitlab-ag control panel, then in the navbar menu, choose "Users" - "Import Users". In the content panel will show the control for pasting CSV data.

The first line of the CSV data must be the header of columns. Three columns are required: `User ID` (matching `username` field on GitLab), `NAME` (matching `name`), and `EMAIL` (matching `email`). Besides, `ID` column, if exists, will be matched to `extern_uid` field on GitLab.

The password for each user will be generated randomly. You will need to make an announcement to the people to ask them reset their passwords on GitLab using "forget password" link on GitLab sign-in page.

Besides, you can set the maximum number of projects a user can have to prevent the users from creating projects that are unrelated to your GitLab purpose. Non-positive number means no limit.

There are two additional options, one for allowing users to create their own groups, and one for importing the users as GitLab administrators. Use with caution.

When you hit "Start" button, a progress bar will show up to indicate progress. If there are any errors in the middle, an error message will show up below the progress bar.

## Create New Projects

In the navbar menu, choose "Repositories" - "Create Repositories". Type the username pattern and click "List Matched Users" and you will see a table of users under whose namespace the desired project will be created.

Then fill in the basic information of the project and click "Create Projects".

`Import URL` (optional) is a URL to an existing Git repository. If it is given, the created project will be clone from there (i.e., having the same files and commit history). This is good for creating a base project and distributing it to all students.
Make sure it can be cloned with the URL. If username and password are needed, include them in the URL. For example, `http://user:pass@host:post/user/lab1-src.git`.

After clicking "Create Projects`, a progress bar will show up indicating the progress. If there is any error occurred during the process, an alert will show below the progress bar. If a project is created successfully for the user, his / her row in the table of matched users will be deleted. (So if everything goes fine, the table will become empty and the progress bar will go to 100%.)
