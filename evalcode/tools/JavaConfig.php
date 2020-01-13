<?php
class JavaConfig {
    private $notices,$mform,$locallib;

    function __construct(&$notices1,&$mform1,$locallib1) {
        $notices = $notices1;
        $mform = $mform1;
        $locallib = $locallib1;

        global $USER, $CFG;
        $time1 = round(microtime(true) * 1000);
        
        require_once($CFG->dirroot . '/mod/evalcode/externallib.php');

        // Include submission form.
        require_once($CFG->dirroot . '/mod/evalcode/submission_form.php');

        $userid = optional_param('userid', $USER->id, PARAM_INT);
        // Need submit permission to submit an evalcodeframework.
        require_sesskey();
        if (!$locallib->submissions_open($userid)) {
            $notices[] = get_string('duedatereached', 'evalcode');
            return false;
        }

        $data = new stdClass();
        $data->userid = $userid;
        $mform = new mod_eval_submission_form(null, array($locallib, $data));
        if ($mform->is_cancelled()) {
            return true;
        }
        if ($data = $mform->get_data()) {
            if ($locallib->save_submission($data, $notices)) {
		error_log("########## NEW SUBMISSION of ".$userid." ##########\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
                $time2 = round(microtime(true) * 1000);
                $packageData = $this->prepareFiles($userid,$notices);
                $time3 = round(microtime(true) * 1000);
                error_log("Nombre del paquete:".$packageData["name"] ."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
                if ($packageData["name"] != "") {
                    $this->executeJunitTest($userid, $packageData);
                    $time4 = round(microtime(true) * 1000);
                    error_log("Tiempo en guardar entrega:".($time2-$time1).
                        "\nTiempo en prepareFiles:".($time3-$time2).
                        "\nTiempo en executeJunitTest:".($time4-$time3)."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
		error_log("########## END SUBMISSION of ".$userid." ##########\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
                    return true;
                }
            }
        }
        return false;
    }

    public function prepareFiles($userid,&$notices)
    {

        $packageName = "";
        $packageData = [];
        $packageData["files"] = [];

        $fs = get_file_storage();
        $context = $locallib->get_context();
        //Create a directory with the userid and the timestamp (Unix format)
        $fecha = new DateTime();
        $path = '/var/www/moodledata/temp/filestorage/' . $userid . '_' . $fecha->getTimestamp() . '/';
        $this->tempPath = $path;
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        //Set the current path to this one created
        chdir($path);

        $listJavaFiles = [];
        //Download from de db the user submission files
        $submission = $this->get_user_submission($userid, false);
        $fileNames = array();
        //If there are not any files at all, return false TODO mensaje de error
        if (empty($submission))
        {
            return false;
        }

        $files = $fs->get_area_files($context->id, 'evalsubmission_file', EVALSUBMISSION_FILE_FILEAREA, $submission->id);
        //If there are not any files at all, return false TODO mensaje de error
        if (count($files) == 0) {
            return false;
        }

        //Take the second value of the submission (the zip archive)
        //  get_area_files returns all the submission files and a "." in [0]
        $f = array_values($files)[1];
        $fileName = $f->get_filename();
        $fileNames[] = $fileName;
        $contents = $f->get_content();
        file_put_contents($fileName, $contents);
        error_log("nombre del zip subido:".$fileName."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
        //Check if the submission is a .zip file
        if (substr($fileName, -4) == '.zip') {
            //Unzip
            $listJavaFiles = $this->unZipFile($fileName, $path);
            //Delete the .zip
            unlink($fileName);
            if(empty($listJavaFiles)){
                //If the zip is empty
                $notices[] = "El zip no contiene ningÃºn elemento. Recarga la pÃ¡gina para volver a hacer una entrega."; //TODO cambiar esto por una referencia mediante get_string
                return false;
            }
            foreach ($listJavaFiles as $archivo){
                error_log("nombre de archivo subido:".$archivo."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
                //If the file is a test, delete it (the student could have changed the test file)
                if(substr($archivo, -9) == 'Test.java'){
                    unlink($archivo);
                }
                /*if(substr($archivo, -5) != '.java' &&substr($archivo, -1)!='/') {
                    //The zip contains files that are not java files or directories
                    $notices[] = "El zip contiene uno o mÃ¡s elementos que no son .java. Recarga la pÃ¡gina para volver a hacer una entrega."; //TODO cambiar esto por una referencia mediante get_string
                    return false;
		}*/
            }
        }else{
            //Notification to the student telling that the file must be in zip format
            $notices[] = "El archivo debe estar en formato .zip. Recarga la pÃ¡gina para volver a hacer una entrega."; //TODO cambiar esto por una referencia mediante get_string
            return false;
        }


        //Download from the db the tests provided by the professor
        $files = $fs->get_area_files($context->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_JUNIT);
        //Get the zip
        $f = array_values($files)[1];
        $fileName = $f->get_filename();
        $contents = $f->get_content();
        file_put_contents($fileName, $contents);

        //Check if the submission is a .zip file
        if (substr($fileName, -4) == '.zip') {
            //Unzip
		$packageName = $this->unZipFile($fileName, $path);
		//$testClassFileName = $packageName[1];
		$packageName = $packageName[0];
	    error_log("nombre del paquete con los tests: ".$packageName."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
            //Delete zip file
	    unlink($fileName);
            shell_exec('find * -name "*.java" > sources_list.txt');

	    $testClassFileName = shell_exec('grep -n "Test" '.$path.'sources_list.txt');
	    error_log("nombre de la clase test:".$testClassFileName."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
	    //shell_exec('rm '.$path.$testClassFileName2);
	    //$packageName = "";
            /*
            if (!$fp = fopen($testClassFileName, "r")) {
                echo "Error open file " . $testClassFileName;
            } else {
                while (!feof($fp)) {
                    $packageName = fgets($fp);
                     if (strpos($packageName, 'package') !== false) {
                        $packageName = explode(";", $packageName);
                        $packageName = explode(" ", $packageName[0]);
                        $packageName = $packageName[1];
                        break;
                    }
                }
                fclose($fp);
	    }*/
            //$packageName = explode("/",$packageName)[0];
            //$testClassFileName = explode("/", $testClassFileName)[count($testClassFileName) - 1];//TODO, poner esto para que busque donde esta el TEST en vez de indicar si es 1 o 2
            //$testClassFileName = $testClassFileName[count($testClassFileName) - 1];
            $tmp = explode(":", $testClassFileName);
            $testClassFileName = end($tmp);
            $tmp = explode("/", $testClassFileName);
            $testClassFileName = end($tmp);
            $testClassFileName = str_replace(".java", "", $testClassFileName);
            //$testRunner = file_get_contents("/var/www/html/moodle/mod/evalcode/testRunner.java");

            //$testRunner = str_replace("@@PACKAGE_NAME@@", $packageName, $testRunner);
            //$testRunner = str_replace("@@CLASS_TEST_NAME@@", $testClassFileName, $testRunner);
            //$testRunner = str_replace("@@CLASS_TEST_NAME@@", $testClassFileName, $testRunner);
            //file_put_contents($path."/TestRunner.java", $testRunner);//TODO cambiar esta guarrada

	        //shell_exec('mv '.$path.'TestRunner.java '.$path.' /test/');
        }

        $packageData["name"] = $testClassFileName;
        return $packageData;
    }

    public function prepareEvalFeedbackComment($junitData, $note, $qualityData)
    {
        $instance = $this->get_instance();
        $comment = "";
        $comment .= "JUNIT RESULT: <br>";
        $comment .= "<br>\tTotal Test: " . $junitData["TOTALTEST"];
        if(array_key_exists('FAILURETEST', $junitData)){
            $comment .= "<br>\tFailure Test: " . $junitData["FAILURETEST"];
        }
        $comment .= "<br>\tJunit note(" . $instance->percentagetest . "%): " . $note["junit"];
        $comment .= "<br><br>QUALITY CHECK RESULT: <br>";
        $comment .= "<br>\tErrors: " . $qualityData["COUNTERRORS"];
        $comment .= "<br>\tWarnings: " . $qualityData["COUNTWARN"];
        $comment .= "<br>\tQuality check note(" . $instance->percentagequality . "%): " . $note["quality"];
        $comment .= "<br><br>See attached log file for more information";

        return $comment;
    }

    public function saveEvalCodeGrade($userId, $result, $fileParsed, $qualiyResult, $isTeacher)
    {
        $data = new stdClass();
        error_log("nota junit:".$result["note"]["junit"]."\n"
		."nota quality:".$result["note"]["quality"]."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
        $data->grade = (intval($result["note"]["junit"]) + intval($result["note"]["quality"]));
        error_log("suma de la nota:".$data->grade."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
        $data->evalfeedbackcomments_editor = [
                "text" => $result["description"],
                "format" => 1
            ];

        $data->rownum = 0;
        $data->useridlistid = "";
        $data->attemptnumber = -1;
        $data->ajax = 0;
        $data->userid = $userId;
        $data->sendstudentnotifications = 1;
        $data->action = "submitgrade";

        $elementname = "files_" . $userId . "_filemanager";
        $data->$elementname = file_get_unused_draft_itemid();

        $grade = $this->get_user_grade($userId, true);

        $this->saveEvalCodeGradeFile($grade, $data, $fileParsed, $userId, $isTeacher, $fileName = "junitResult.txt");
        $this->saveEvalCodeGradeFile($grade, $data, $qualiyResult, $userId, $isTeacher, $fileName = "qualityResult.txt");
        // $this->save_grade($userId, $data);

        $this->apply_grade_to_user($data, $userId, $data->attemptnumber);

        $this->process_outcomes($userId, $data);

        return true;
    }

    public function unZipFile($fileName, $path)
    {
        $listFiles = [];
        $zip = new ZipArchive;
        if ($zip->open($fileName) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $listFiles[] = $zip->getNameIndex($i);
            }
            $zip->extractTo($path);
            $zip->close();
            return $listFiles;
        } else {
            echo 'failed unzip file ' . $fileName; //TODO cambiar esto por un mensaje de error
            return [];
        }
    }

    /*public function findFileInDir($dir)
    {
        if (is_dir($dir)) {
            $d = opendir($dir);
            while ($file = readdir($d)) {
                if ($file != "." AND $file != "..") {
                    if (is_file($dir . '/' . $file)) {
                        if (substr($file, -9) == 'Test.java') {
                            return ($dir . '/' . $file);
                        }
                    }

                    if (is_dir($dir . '/' . $file)) {
                        $r = $this->findFileInDir($dir . '/' . $file);
                        if (substr(basename($r), -9) == 'Test.java') {
                            return $r;
                        }
                    }
                }
            }
        }
        return FALSE;
    }*/

    public function executeJunitTest($userId, $packageData, $isTeacher = false)
    {

        
        shell_exec('find . -type f -exec mv {} . \;');
        //Generar el fichero sources_list.txt que contiene todos los .java de la entrega
        $auxComand = 'find * -name "*.java" > sources_list.txt';
        shell_exec($auxComand);
        
        //Iterar por todos los archivos de sources_list.txt y borrar su paquete
        $contents = file_get_contents('sources_list.txt');
        $lines = explode("\n", $contents);

        foreach($lines as $javaFile) {
            if(!empty($javaFile) ){
                $fileContents = file_get_contents($javaFile);
                $newContents = preg_replace('/^.*(?:package).*$(?:\r\n|\n)?/m', '', $fileContents);
                //$newContents = preg_replace('/^.*(?:import).*$(?:\r\n|\n)?/m', '', $fileContents);
                preg_match_all('/^.*(?:import ).*$(?:\r\n|\n)?/m',$fileContents,$matches);
                //print_r($matches);
                foreach($matches[0] as $m){
                        if(!empty($m)){
                        //error_log("\n".'Matches: '.count($matches[0]).' Nuevo match:'.$m, 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
                        foreach($lines as $javaFile2){
                            
                            //echo '\n\n'.'probando '.$m.' con '.explode('.',$javaFile2)[0].' en '.$javaFile.PHP_EOL;
                            //error_log("\n".' probando '.$m.' con '.explode('.',$javaFile2)[0].' en '.$javaFile, 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
                            if(!empty($javaFile2)){
                                if(strpos($m, explode('.',$javaFile2)[0]) !== false){
                                    //error_log("\n".'Borrado.', 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
                                    $newContents = str_replace($m,'',$newContents);
                                }
                            }
                        
                        }
                    }
                }
                file_put_contents($javaFile,$newContents);
            }
        }
        //En la compilacion se añaden al classpath todas las librerias externas que haya en /var/www/
        $comand2 = 'javac -Xlint:unchecked -encoding UTF-8 -cp .:/var/www/* @sources_list.txt 2>&1';
        $salida = shell_exec($comand2);
        //$auxComand = 'cp *.class '.$packageData["name"]."/";
        //shell_exec($auxComand);

        error_log("Sell exec: ".$salida."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
        $junitResult = shell_exec('java -cp .:/var/www/* org.junit.runner.JUnitCore ' . $packageData["name"] . ' 2>&1');
        error_log("Junit: ".$junitResult."\n", 3, "/var/www/moodledata/temp/filestorage/evalcode.log");
        $result = [];
        $qualiyResult = $this->checkCodeQuality($packageData);

        $fileParsed = $this->parseFile($junitResult);

        $result['note'] = $this->prepareNote($fileParsed, $qualiyResult);
        $result['description'] = $this->prepareEvalFeedbackComment($fileParsed, $result['note'], $qualiyResult);

        $this->saveEvalCodeGrade($userId, $result, $fileParsed, $qualiyResult, $isTeacher);

        //delete temp directory
        if ($packageData["name"] != "") {
            //array_map('unlink', glob($packageData["name"] . "/*.*"));
            //rmdir($packageData["name"]);
        }
        if ($this->tempPath != "") {
            //array_map('unlink', glob($this->tempPath . "/*.*"));
            //rmdir($this->tempPath);
        }
    }

    public function checkCodeQuality($packageData)
    {
        $javaFileToCheck = "";
        foreach ($packageData["files"] as $file) {
            if($file != "TestRunner.java"){
                $javaFileToCheck .= $this->tempPath . $file . " ";
            }
        }

        shell_exec("sed -i '/TestRunner.java/d' ".$this->tempPath."sources_list.txt");
        shell_exec("sed -i '/GuiEmpresaLimpieza.java/d' ".$this->tempPath."sources_list.txt");
        error_log("Checkstyle: ".$javaFileToCheck."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
        error_log("TempPath: ".shell_exec("cat ".$this->tempPath."sources_list.txt")."\n",3,"/var/www/moodledata/temp/filestorage/evalcode.log");
        $qualityData = [];
        $qualityData["COUNTERRORS"] = 0;
        $qualityData["COUNTWARN"] = 0;
        $qualityData["DATA"] = "";
        //$comand1 = '/usr/java/jdk1.8.0_131/bin/java -jar /usr/java/jdk1.8.0_131/lib/checkstyle-7.7-all.jar -c /usr/java/jdk1.8.0_131/lib/sun_checks.xml ' . $javaFileToCheck . ' 2>&1';
        $comand2 = '/usr/java/jdk1.8.0_131/bin/java -jar /var/www/checkstyle-8.15-all.jar -c /var/www/checkstyle_ed_checks.xml @'.$this->tempPath.'sources_list.txt 2>&1';
        //$qualityData["DATA"] .= shell_exec($comand1);
        $qualityData["DATA"] .= shell_exec($comand2);

        $qualityData["COUNTERRORS"] = substr_count($qualityData["DATA"], '[ERROR]');
        $qualityData["COUNTWARN"] = substr_count($qualityData["DATA"], '[WARN]');
        return $qualityData;
    }

    private function parseFile($junitResult)
    {
        preg_match('/^.*(?:OK).*$(?:\r\n|\n)?/m',$junitResult,$totalTest);
        $data = [];
        $data['TEXT'] = $junitResult;
        if(!empty($totalTest)){
            $data['TOTALTEST'] = 'Pasan todos los tests.';
        }else{
            preg_match('/^.*(?:Tests run:).*$(?:\r\n|\n)?/m',$junitResult,$totalTest);
            //echo '###'.$totalTest[0];
            
            $data['TOTALTEST'] = str_replace('Tests run: ','',explode(',',$totalTest[0])[0]);
            $data['FAILURETEST'] =str_replace('  Failures: ','',explode(',',$totalTest[0])[1]);
        }
        return $data;
    }

    public function prepareNote($data, $qualiyResult)
    {
        $instance = $this->get_instance();
        $note = [];
        $note["junit"] = 0;
        $note["quality"] = 0;

        if (array_key_exists('FAILURETEST', $data)) {

            if (intval($data['FAILURETEST']) > intval($instance->maxtestnumber)) {
                $note["junit"] = 10 *((intval($data['TOTALTEST'])-intval($data['FAILURETEST'])) * 5.0 / intval($instance->maxtestnumber));
            } else {
                $note["junit"] = 10 *(intval($data['TOTALTEST'])-intval($data['FAILURETEST'])) * 10.0 / intval($data['TOTALTEST']);
            }
            $note["junit"] = $note["junit"] * intval($instance->percentagetest) / 100;
            $note["junit"] * 10;
        }else{
            //echo '##'.intval($instance->percentagetest);
            $note["junit"] = 100 * intval($instance->percentagetest) / 100;
        }
        if (array_key_exists('COUNTERRORS', $qualiyResult)) {
            //print_r(intval($qualiyResult['COUNTWARN'])/3);
            if ((intval($qualiyResult['COUNTERRORS']) + intval($qualiyResult['COUNTWARN'])/3) <= intval($instance->maxerrornumber)) {
                $note["quality"] = 10 - (intval($qualiyResult['COUNTERRORS']) * 5 / intval($instance->maxtestnumber));
                $note["quality"] = 10 * $note["quality"] * intval($instance->percentagequality) / 100;
                $note["quality"] * 10;
            }
        }

        return $note;
    }

    public function saveEvalCodeGradeFile(stdClass $grade, stdClass $data, $fileData, $userId, $isTeacher, $fileName)
    {
        global $USER;
        $elemName = "files_" . $userId . "_filemanager";
        $fs = get_file_storage();
        if ($isTeacher) {
            $teacher = optional_param('userid', $USER->id, PARAM_INT);
            $context = context_user::instance($teacher);

            $userId = $context->instanceid;

        } else {
            $context = context_user::instance($userId);
        }

        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'user',     // usually = table name
            'filearea' => 'draft',     // usually = table name
            'itemid' => $data->$elemName,               // usually = ID of row in table
            'filepath' => '/',           // any path beginning and ending in /
            'filename' => $fileName,
            'source' => 'O:8:"stdClass":1:{s:6:"source";s:' . strlen($fileName) . ':"' . $fileName . '";}',
            'userid' => $userId);

        $fs->create_file_from_string($fileinfo, print_r($fileData, 1));
        $plugin = $this->get_feedback_plugin_by_type('file');
        $plugin->is_feedback_modified($grade, $data);

        $plugin->save($grade, $data);
        // print_r($plugin->get_file_feedback($grade->id));

    }
}
?>