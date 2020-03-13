<?php
/**
 * This file contains the main logic for the evaluation process
 */
//### CheckStyle ###

/**
 * This is the function called when a student clicks the 'Submit' button
 * @param $path Is the temporal path in wich your function will operate, it contains 
 *      the student submission and your evaluation files.
 * @param $returndata Is a generic class to specify the grade and the feedback comment.
 *      It's the only thing that this function should return.
 * @param $additionalParams This is a string containing all the additional information
 *      you've provided during the creation of your EvalCode activity.
 * 
 * @return $returndata Data class containing the grade and the feedback comment.
 * 
 * @throws NothingHere This is the example error to show how you sould use it. All
 *      the errors this function generates are treated and shown to the student in the
 *      sumbission comment box.
 * NOTE: This is an Anonymous function, that's the reason why it has to be inside $evaluatefunc.
 */
$evaluatefunc = function ($path,$returndata,$additionalParams){
    shell_exec('find * -name "*.java" > sources_list.txt');
    $javaFileToCheck = "";
    $contents = file_get_contents('sources_list.txt');
    $lines = explode("\n", $contents);

    foreach($lines as $javaFile) {
        if(!empty($javaFile) ){
            $javaFileToCheck .= $javaFile." ";
        }
    }

    $comand2 = '/usr/lib/jvm/java-1.8.0-openjdk-amd64/bin/java -jar /var/www/checkstyle-8.15-all.jar -c /var/www/checkstyle_ed_checks.xml @'.$path.'sources_list.txt 2>&1';
    $qualityData = shell_exec($comand2);

    error_log("CHECKSTYLE RESULT: ".$qualityData."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    
    $countErrors = substr_count($qualityData, '[ERROR]');
    $countWarns = substr_count($qualityData, '[WARN]');
    /*$grade = 100 - ((intval($countErrors)+ intval($countWarns)/3) * 50 / intval($additionalParams));
    if($grade<0){
        $grade = 0;
    }*/
    if(((intval($countErrors)+ intval($countWarns))>intval($additionalParams))){
        $grade = 0;
    }else{
        $grade = 100-(100*(intval($countErrors)+ intval($countWarns))/intval($additionalParams));
    }

    $comment = "";
    $comment .= "<br>QUALITY CHECK RESULT: <br>";
    $comment .= "<br>\tErrors: " . $countErrors;
    $comment .= "<br>\tWarnings: " . $countWarns;
    if(($countErrors+$countWarns)>0){
        $comment .= "<br><br>Errors/Warns detected: <br>".str_replace("\n", "<br>", $qualityData);
    }
    $comment .= "<br>\tMax errors permited: ".$additionalParams;
    $comment .= "<br><br><strong>\tQuality check grade: " . $grade."</strong>";
    
    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
};
?>