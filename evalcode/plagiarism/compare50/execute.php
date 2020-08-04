<?php

//File that contains the secure_exec function to operate in a sandbox with the submmited files
require_once('/var/www/html/moodle/mod/evalcode/tools/secure_exec.php'); 

/**
 * This file contains the function executeCompare
 */

 /**
 * This is the function that is called to execute the plagiarism tool compare50 and return it 
 * in a forced download
 * @param $path path to the students files folder
 * 
 */

function executeCompare($path) {
   chdir($path);
   chdir('..');

   //If the teacher has given any files we execute the command with the -d flag
   if (file_exists('files/')){
      $command="compare50 ./submissions/* -d files/ -n 15"; 
   }else{
      $command="compare50 ./submissions/* -n 15"; 
   }

   $result = secure_exec($command);
   error_log("SALIDA COMPARE50: ".$result."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
   
   //We compress the results folder
   shell_exec('zip -r results.zip results');

   //We force a download to return the result

   $file = "results.zip"; //Name of the file to look for
   $filename = "Compare50Results.zip";	//Name of the file in the download
   $filepath = $path .'/../'. $file;

   // Process download

   if(file_exists($filepath)) {
      ob_start();

      header("Content-type: application/force-download");
      header('Content-Disposition: attachment; filename="'.$filename.'"');	
      header('Content-Length: ' . filesize($filepath));	
      
      while (ob_get_level()) {
         ob_end_clean();
      }

      readfile($filepath);	 
   }
}

?>
