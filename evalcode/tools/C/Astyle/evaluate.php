<?php
/**
 * This file contains the main logic for the evaluation process
 */
//### Astyle ###

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
    /*shell_exec('find * -name "*.c" > sources_list.txt');
    $contents = file_get_contents('sources_list.txt');
    $salida = shell_exec('style50 -o score @sources_list.txt 2>&1');
    if(strpos($salida,'error') || strpos($salida,'errors')){
        $returndata->grade = 0;
        $returndata->feedbackcomment = "Error: <br>".str_replace("\n", "<br>", $salida);
        return $returndata;
    }

    error_log("Sell exec: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
    shell_exec('style50 -o split @sources_list.txt > feedback.log');
    $feedback =	shell_exec('ansi2html < feedback.log');
    $grade = intval($salida);
    */
    $grade = 50;
    $comment = "";
    $comment .= "ASTYLE RESULT: <br>";
    $comment .= '
        <table>
            <tr>
                <td>#include &#60;stdio.h&#62; </td>
                <td>#include &#60;stdio.h&#62;</td>
            </tr>
            <tr>
                <td>int main(void)</td>
                <td>int main(void)</td>
            </tr>
            <tr>
                <td>&nbsp&nbsp&nbsp&nbsp<font color="red">{</font></td>
                <td><font color="green">{</font></td>
            </tr>
            <tr>
                <td>printf("hello, world\n");&nbsp&nbsp&nbsp</td>
                <td> <font color="green">....</font>printf("hello, world\n");</td>
            </tr>
            <tr>
                <td>&nbsp&nbsp&nbsp&nbsp<font color="red">}</font></td>
                <td><font color="green">}</font></td>
            </tr>
        </table>';
    $comment .= "<br>\tAstyle grade: " . $grade;

    $returndata->grade = $grade;
    $returndata->feedbackcomment = $comment;
    return $returndata;
};
?>


