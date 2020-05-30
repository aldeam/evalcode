<?php
/**
 * This file contains the main logic for the evaluation process
 */
//### Astyle ###

//File that contains the secure_exec function to operate in a sandbox with the submmited files
require_once('/var/www/html/moodle/mod/evalcode/tools/secure_exec.php');

/**
 * This is the function called when a student clicks the 'Submit' button
 * @param $path Is the temporal path in wich your function will operate, it contains 
 *      the student submission and your evaluation files.
 * @param $returndata Is a generic class to specify the grade and the feedback comment.
 *      It's the only thing that this function should return.
 * @param $additionalParams This is a string containing all the additional information
 *      you've provided during the creation of your EvalCode activity.
 * @return $returndata Data class containing the grade and the feedback comment.
 * @throws NothingHere This is the example error to show how you sould use it. All
 *      the errors this function generates are treated and shown to the student in the
 *      sumbission comment box.
 * NOTE: This is an Anonymous function, that's the reason why it has to be inside $evaluatefunc.
 */
$evaluatefunc = function ($path,$returndata,$additionalParams){
    global $USER;

	shell_exec('find * -name "*.c" > sources_list.txt');
    $contents = file_get_contents('sources_list.txt');
    error_log("ARCHIVO: ".$contents."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    //It only takes the first file. Only working for 1 file
    $salida = shell_exec('style50 -o score '.$contents);
    
    if(strpos($salida,'error') || strpos($salida,'errors')){
        $returndata->grade = 0;
        $returndata->feedbackcomment = "Error: <br>".str_replace("\n", "<br>", $salida);
        return $returndata;
    }
    
    error_log("Grade: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    error_log("Path: ".$path."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");

    $grade = floatval($salida)*100;

    $output = secure_exec('style50 '.$contents);
    $fh = fopen('feedback.log','w');
    fwrite($fh,$output);
    fclose($fh);

    $feedback= shell_exec('ansi2html --white --title "'.fullname($USER).'" < feedback.log');
    
    $fh = fopen('feedback.html','w');
    fwrite($fh,$feedback);

    $comment = "".$feedback;

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
};
?>


