<?php
/**
 * This file contains the configuration for a specific tool for EvalCode.
 */

//### JUNIT ###
$languageTool = new \stdClass();
//Name for the tool, must be the same as the folder and without blank spaces
$languageTool->name = 'JUnit';
//Specify the current version of the tool
$languageTool->version = '4.2';
//Code language that the tool evaluates, must be the same as the language folder name and only one
$languageTool->language = 'Java';
//Description for the tool
$languageTool->description = 'Execute JUnit tests and put a score. Requires an additional parameter with the 
                                minimum number of correct tests required to pass.';
return $languageTool;
?>