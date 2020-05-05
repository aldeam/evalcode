<?php 
/**
 * This file contains the main logic for the evaluation process
 */
//### JUNIT ###

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

    $testClassFileName = shell_exec('grep -n "Test" '.$path.'sources_list.txt');
    /*
    $tmp = explode(":", $testClassFileName);
    $testClassFileName = end($tmp);
    $testClassFileName = str_replace(".java", "", $testClassFileName);
    $testClassFileName = str_replace("/", ".", $testClassFileName);
    */

    $contents = file_get_contents('sources_list.txt');

    //En la compilacion se añaden al classpath todas las librerias externas que haya en /var/www/
    shell_exec('mkdir out');
    $comand2 = 'javac -d '.$path.'out -Xlint:unchecked -encoding UTF-8 -cp .:/var/www/* @sources_list.txt 2>&1';
    $salida = shell_exec($comand2);
    error_log("Junit: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    if(strpos($salida,'error') || strpos($salida,'errors')){
        $returndata->grade = 0;
        $returndata->feedbackcomment = "Error during compilation (javac): <br>".str_replace("\n", "<br>", $salida);
        return $returndata;
    }

    $junitResult = shell_exec('java -jar /var/www/junit-platform-console-standalone-1.6.0.jar --class-path out --scan-class-path 2>&1');

    $output = $junitResult;
    $fh = fopen('feedback.log','w');
    fwrite($fh,$output);
    fclose($fh);

    $feedback= shell_exec('ansi2html --white < feedback.log');
    
    error_log("Junit: ".$feedback."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    $result = [];

    
    $countTotalTest="";
    $countSuccesfullTest="";
    $countFailureTest="";
    
    preg_match_all("/([0-9]+) tests found/",$junitResult,$countTotalTest);
    $countTotalTest = strtok($countTotalTest[0][0], " ");

    preg_match_all("/([0-9]+) tests successful/",$junitResult,$countSuccesfullTest);
    $countSuccesfullTest = strtok($countSuccesfullTest[0][0], " ");

    preg_match_all("/([0-9]+) tests failed/",$junitResult,$countFailureTest);
    $countFailureTest = strtok($countFailureTest[0][0], " ");


    if (intval($countFailureTest)>0) {

        if (intval($countFailureTest) > (intval($countTotalTest)-intval($additionalParams))) {
            $grade = 10 *((intval($countTotalTest)-intval($countFailureTest)) * 5 / intval($additionalParams));
        } else {
            $grade = 100-((50/(intval($countTotalTest)-intval($additionalParams)))*((intval($countTotalTest))-(intval($countTotalTest)-intval($countFailureTest))));
        }
    }else{

        $grade = 100;
    }
     
    $comment = "<font size='3'>";    
    $comment .= "\tTotal test(s): " . $countTotalTest;
    $comment .= "<br>\tMin correct test(s) required: " . $additionalParams;
    if($countFailureTest>0){
        $comment .= "<br>\tFailure test(s): " . $countFailureTest;
    }
    $comment .= "<br><br><strong>\tJUnit test grade: " . $grade."</strong></font>";
    $comment .= "<br><br>".$feedback;

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
}
?>