<?php
/**
 * This file contains the configuration for a specific tool for EvalCode.
 */

//### AStyle ###
$languageTool = new \stdClass();
//Name for the tool, must be the same as the folder and without blank spaces
$languageTool->name = 'Astyle';
//Specify the current version of the tool
$languageTool->version = '3.1';
//Code language that the tool evaluates, must be the same as the language folder name and only one
$languageTool->language = 'C';
//Description for the tool
$languageTool->description = 'Artistic Style is a source code indenter, formatter, and beautifier for 
                                the C, C++, C++/CLI, Objective‑C and C# programming languages.';

return $languageTool;
?>



