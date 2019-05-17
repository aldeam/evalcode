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
    $tmp = explode(":", $testClassFileName);
    $testClassFileName = end($tmp);
    $testClassFileName = str_replace(".java", "", $testClassFileName);
    $testClassFileName = str_replace("/", ".", $testClassFileName);

    $contents = file_get_contents('sources_list.txt');
    
    //En la compilacion se añaden al classpath todas las librerias externas que haya en /var/www/
    $comand2 = 'javac -Xlint:unchecked -encoding UTF-8 -cp .:/var/www/* @sources_list.txt 2>&1';
    $salida = shell_exec($comand2);
    if(strpos($salida,'error') || strpos($salida,'errors')){
        $returndata->grade = 0;
        $returndata->feedbackcomment = "Error during compilation (javac): <br>".str_replace("\n", "<br>", $salida);
        return $returndata;
    }

    error_log("Sell exec: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    $junitResult = shell_exec('java -cp .:/var/www/hamcrest-core-1.3.jar:/var/www/junit4.jar org.junit.runner.JUnitCore ' . $testClassFileName . ' 2>&1');
    error_log("Test class filename: ".$testClassFileName."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    error_log("Junit: ".$junitResult."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    $result = [];

    preg_match('/^.*(?:OK).*$(?:\r\n|\n)?/m',$junitResult,$totalTest);
    $countFailureTest=0;
    if(!empty($totalTest)){
        $countTotalTest = 'Pasan todos los tests.';
    }else{
        preg_match('/^.*(?:Tests run:).*$(?:\r\n|\n)?/m',$junitResult,$totalTest);
        //echo '###'.$totalTest[0];
        
        $countTotalTest = str_replace('Tests run: ','',explode(',',$totalTest[0])[0]);
        $countFailureTest = str_replace('  Failures: ','',explode(',',$totalTest[0])[1]);
    }
    
    if (intval($countFailureTest)>0) {

        if (intval($countFailureTest) > intval($additionalParams)) {
            $grade = 10 *((intval($countTotalTest)-intval($countFailureTest)) * 5 / intval($additionalParams));
        } else {
            $grade = 10 *(intval($countTotalTest)-intval($countFailureTest)) * 10 / intval($countTotalTest);
        }
    }else{

        $grade = 100;
    }
    //echo($grade);
    $comment = "";
    $comment .= "JUNIT RESULT: <br>";
    $comment .= "<br>\tTotal test(s): " . $countTotalTest;
    $comment .= "<br>\tMin correct test(s) required: " . $additionalParams;
    if($countFailureTest>0){
        $comment .= "<br>\tFailure test(s): " . $countFailureTest;
        $comment .= "<br><br>Failed Test(s): <br>".str_replace("\n", "<br>", $junitResult);
    }
    $comment .= "<br>\tJUnit test grade: " . $grade;

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
};

?>