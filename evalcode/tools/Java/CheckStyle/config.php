<?php
/**
 * This file contains the configuration for a specific tool for EvalCode.
 */

//### CheckStyle ###
$languageTool = new \stdClass();
//Name for the tool, must be the same as the folder and without blank spaces
$languageTool->name = 'CheckStyle';
//Specify the current version of the tool
$languageTool->version = '8.15';
//Code language that the tool evaluates, must be the same as the language folder name and only one
$languageTool->language = 'Java';
//Description for the tool
$languageTool->description = 'Checks if the coding style follows a specific set of rules. Requires an additional param
                                to specify the max number of errors required to pass.';

return $languageTool;
?>