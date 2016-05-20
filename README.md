# Gitorious to Gitlab.com Mass-Migration Script

Run this PHP script on the command line to migrate all your Gitorious repositories to GitLab.

We used this to move over 100 repositories with no loss of data.

The GitLab API is full-featured and well-documented but takes a while to get the hang of.

* [GitLab Documentation](http://docs.gitlab.com/)
* [Enterprise Edition/GitLab.com API Docs](http://docs.gitlab.com/ee/api/README.html)
* [Community Edition API Docs](http://docs.gitlab.com/ce/api/README.html)

The private token for your account is at https://gitlab.com/profile/account.

##Usage:

From the command line

**$ php migrate.php {number of projects to migrate} [specific project]**


###Example Usage

Migrates top 10 projects (sorted by row id):

**$ php migrate.php 10**

Only migrate the one project and all the repositories it contains:

**$ php migrate.php 1 this_specific_project**
