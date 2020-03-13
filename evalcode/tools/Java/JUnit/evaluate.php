<?php 
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

    /*
    mkdir out
    javac -d out Student.java StudentSortSearch.java
    javac -d out -cp out:junit-platform-console-standalone-1.3.1.jar TestClass.java
    java -jar junit-platform-console-standalone-1.3.1.jar --class-path out --scan-class-path
    */
    $junitResult = shell_exec('java -jar /var/www/junit-platform-console-standalone-1.6.0.jar --class-path out --scan-class-path 2>&1');


    //error_log("Sell exec: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    //$junitResult = shell_exec('java -cp .:/var/www/hamcrest-core-1.3.jar:/var/www/junit-4.12.jar org.junit.runner.JUnitCore ' . $testClassFileName . ' 2>&1');
    //$junitResult = shell_exec('java -cp .:/var/www/hamcrest-core-1.3.jar:/var/www/junit-jupiter-5.6.0.jar org.junit.runner.JUnitCore ' . $testClassFileName . ' 2>&1');
    //error_log("Test class filename: ".$testClassFileName."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    
    
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
    
    preg_match("/(?:\d) tests found/m",$junitResult,$countTotalTest);
    preg_match("/(?:\d) tests successful/m",$junitResult,$countSuccesfullTest);
    preg_match("/(?:\d) tests failed/m",$junitResult,$countFailureTest);

    $countTotalTest= $countTotalTest[0][0];
    $countSuccesfullTest=$countSuccesfullTest[0][0];
    $countFailureTest=$countFailureTest[0][0];
  
    error_log("PREG_MATCXH: ".$countTotalTest."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    error_log("PREG_MATCXH: ".$countSuccesfullTest."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    error_log("PREG_MATCXH: ".$countFailureTest."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");


    if (intval($countFailureTest)>0) {

        if (intval($countFailureTest) > (intval($countTotalTest)-intval($additionalParams))) {
            $grade = 10 *((intval($countTotalTest)-intval($countFailureTest)) * 5 / intval($additionalParams));
        } else {
            $grade = 100-((50/(intval($countTotalTest)-intval($additionalParams)))*((intval($countTotalTest))-(intval($countTotalTest)-intval($countFailureTest))));
        }
    }else{

        $grade = 100;
    }
     
    $comment = "";
    $comment .= "<br>JUNIT RESULT: <br>";
    
    $comment .= "<br>\tTotal test(s): " . $countTotalTest;
    $comment .= "<br>\tMin correct test(s) required: " . $additionalParams;
    if($countFailureTest>0){
        $comment .= "<br>\tFailure test(s): " . $countFailureTest;
        $comment .= "<br><br><strong>\tJUnit test grade: " . $grade."</strong>";
    }
    //$comment .="".str_replace("\n", "<br>", $feedback);
    $comment .= "<br><br>".$feedback;

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
  
    
    /*
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

        if (intval($countFailureTest) > (intval($countTotalTest)-intval($additionalParams))) {
            $grade = 10 *((intval($countTotalTest)-intval($countFailureTest)) * 5 / intval($additionalParams));
        } else {
            $grade = 100-((50/(intval($countTotalTest)-intval($additionalParams)))*((intval($countTotalTest))-(intval($countTotalTest)-intval($countFailureTest))));
        }
    }else{

        $grade = 100;
    }
     
    $grade = 100;
    //echo($grade);
    $comment = "";
    $comment .= "<br>JUNIT RESULT: <br>";
    /*
    $comment .= "<br>\tTotal test(s): " . $countTotalTest;
    $comment .= "<br>\tMin correct test(s) required: " . $additionalParams;
    if($countFailureTest>0){
        $comment .= "<br>\tFailure test(s): " . $countFailureTest;
        $comment .= "<br><br><b>\tJUnit test grade: " . $grade."</b>";
        $comment .= "<br><br>Failed Test(s): <br>".str_replace("\n", "<br>", $junitResult);
    }
    
    $comment .="".str_replace("\n", "<br>", $feedback);
    //$comment .= "<br><br>." .str_replace("\n", "<br>", $junitResult);
    $comment .= "<br><br><b>\tJUnit test grade: " . $grade."</b>";

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
    */
    
}
?>