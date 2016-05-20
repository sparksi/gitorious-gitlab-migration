# Gitorious to Gitlab Mass-migration Script

Run this PHP script on the command line to migrate all your Gitorious repositories to GitLab.

We used this to move over 100 repositories with no loss of data.

##Usage

From the command line

**$ php migrate.php {number of projects to migrate} [specific project]**

Migrates top 10 projects (sorted by row id)

**$ php migrate.php 10**

Only migrate the one project and all the repositories it contains

**$ php migrate.php 1 this_specific_project**
