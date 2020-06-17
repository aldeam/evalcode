<?php

//File that contains the secure_exec function to operate in a sandbox with the submmited files
require_once('/var/www/html/moodle/mod/evalcode/tools/secure_exec.php'); 
ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);
error_reporting(E_ALL);

/**
 * This file contains the function executeCompare
 */

 /**
 * This is the function that is called when is going to be operations with files submitted by
 * the students and makes the execution secure within a sandbox called firejail
 * @param $command is the original command that is to be executed in the sandbox
 * 
 * @return $ the output of the command without the firejail output
 */

function executeCompare($path) {
   chdir($path);
   $command="compare50 * -x '*' -i'*.c' -n 15 2>&1";
   $result = secure_exec($command);
   error_log("SALIDA COMPARE50: ".$result."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
}


?>
