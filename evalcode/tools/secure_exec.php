<?php
/**
 * This file contains the function secure_exec
 */

 /**
 * This is the function that is called when is going to be operations with files submitted by
 * the students and makes the execution secure within a sandbox called firejail
 * @param $command is the original command that is to be executed in the sandbox
 * 
 * @return $ the output of the command without the firejail output
 */
function secure_exec(String $command) {
    return shell_exec('firejail --writable-var --profile=/var/www/html/moodle/mod/evalcode/tools/evalcode.profile --quiet '.$command);
  }
?>
