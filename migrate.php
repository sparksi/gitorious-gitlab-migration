<?php

/*
* @company       Sparks Interactive
* @author        Andy Foster
* @lastUpdated   13 Nov 2015
*
* GitLab Migration Script
* This command-line script mass-migrates our git repositories from
* our locally-hosted version of Gitorious to GitLab.
*
* @usage:
* On the command line
*
* $ php migrate.php {number of projects to migrate} [specific project]
*
* $ php migrate.php 10 	// migrates top 10 projects (sorted by row id)
* $ php migrate.php 1 this_specific_project   // only migrates the one project
*
*
* Gitorious has the notion of reposititories nested inside projects
* but GitLab only has a repository.
* On GitLab we use {project_name}.{repository_name}
*
*
* WARNING: This script generates a lot of folders.
* I recommend moving this into an empty temp directory before running.
*
* TODO 
* * Have the script create and run everything in a /tempFiles/ directory
*
*/

// Define the important settings up here.
define("SERVERNAME", "localhost");
define("USERNAME", "your_database_username_here");
define("PASSWORD", "your_database_password_here");
define("DBNAME", "gitorious_production");
define("GITLAB_TOKEN", "your_API_token_here");

// Ensure that either 1 or 2 arguments get passed in
if ($argc < 2 || $argc > 3) {
  echo  <<<'EOT'
  ERROR
  Usage: $ php script.php {LIMIT} [ {PROJECT_SLUG} ]
  Example:
     $ php script.php 10
     $ php script.php 1 example_git_project

EOT;
  exit;
}

// Defaults for input parameters
$project_limit = isset($argv[1]) ? $argv[1] : 1;
$which_project = isset($argv[2]) ?
  "WHERE projects.slug = '{$argv[2]}'" :
  "WHERE 1=1";

// Create database connection
$conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Keep track of the number of rows migrated
$count = 0;

// Query to pull the projects with the latest repositories
$project_query = "SELECT id, slug, description " .
	"FROM projects " .
	"WHERE owner_id = 1 " .
	"AND owner_type = 'Group' " .
	"AND slug NOT LIKE '%-migrated'";

output("Query: $project_query");

$result = $conn->query($project_query);

if ($result->num_rows > 0) {
    while($project = $result->fetch_assoc()) {
      $project_id = $project['id'];

      // Append -migrated tag in Gitorious
      output("Appending '-migrated' to project "
        . $project['slug'] . "...");
      mark_as_migrated($conn, $project['slug'], $project['id']);

      $repo_query = "SELECT * FROM repositories " .
        "WHERE project_id = '$project_id' " .
        "AND name NOT LIKE '%-wiki'";
      output($repo_query);

      $repo_result = $conn->query($repo_query);
      while($repo = $repo_result->fetch_assoc()) {
        output("Writing new repo to GitLab");
        create_bare_repo_on_gitlab(
          $project['slug'],
          $project['description'],
          $repo['name'],
          $repo['description']);

        clone_and_push_repo($project['slug'], $repo['name']);

        $count++;
      }
    }
    output("End of project. " . $project['name']);
    disable_project($conn, $project['id']);

    echo <<<EOT
    ==================================
    Script finished. Migrated $count repos.
    ==================================

EOT;
} else {
    echo <<<EOT
    ========
    No results found.
    ==========
EOT;
}
$conn->close();


/**
*
* Create a bare repo on GitLab with title and description
* Sets admin as the owner. Only admin users can delete or transfer
*
*/
function create_bare_repo_on_gitlab(
  $projectSlug,
  $projectDescription,
  $repoName,
  $repoDescription) {

  $proj_repo_description = trim(
    urlencode($projectDescription . " -- " . $repoDescription));

  $curl = 'curl --header "PRIVATE-TOKEN: ' .
    GITLAB_TOKEN . '" -X POST ' .
    '"https://gitlab.com/api/v3/projects?' .
    'name=' . $projectSlug . '.' . $repoName .
    '&path=' . $projectSlug . '.' . $repoName .
    '&namespace_id=325277' .
    '&builds_enabled=false' .
    '&description=' . $proj_repo_description . '"';

  shell_exec($curl);
}


/**
*
* Use the git commands to upload to the GitLab repo
*
*/
function clone_and_push_repo($projectSlug, $repoName) {
  $new_folder = $projectSlug . "-" . $repoName;

  $mkdir = "mkdir {$new_folder}";
  output($mkdir);
  shell_exec($mkdir);

  $git_clone = "cd {$new_folder}; " .
    "git clone --mirror https://git.sparksinteractive.co.nz/" .
    "{$projectSlug}-migrated/{$repoName}.git .git";
  output($git_clone);
  shell_exec($git_clone);

  shell_exec("cd {$new_folder}; git config --bool core.bare false");

  $remote_add = "cd {$new_folder}; " .
    "git remote add gitlab git@gitlab.com:sparksi/" .
    "{$projectSlug}.{$repoName}.git";
  output($remote_add);
  shell_exec($remote_add);

  $git_push = "cd {$new_folder}; git push --mirror gitlab";
  output($git_push);
  shell_exec($git_push);
}


/**
*
* Append "-migrated" to  project (NOT individual repos).
* This prevents users accessing the repository during the process
*
*/
function mark_as_migrated($dbConn, $projectSlug, $projectID){
  $project_slug_migrated = $projectSlug . "-migrated";

  $append_migrated =
    "UPDATE projects " .
    "SET slug = '$project_slug_migrated' " .
    "WHERE id = '$projectID' LIMIT 1";
  $dbConn->query($append_migrated);
}


/**
*
* Disable git access to project (cannot disable individual repos).
*
*/
function disable_project($dbConn, $projectID){
  $suspend_project =
    "UPDATE projects " .
    "SET suspended_at = NOW() " .
    "WHERE id = '$projectID' LIMIT 1";
  $dbConn->query($suspend_project);
}


/**
*
* Print out what is going on at every point.
* We could add logging to a file in the future
*
*/
function output($content){
  echo $content . "\n";
}

?>