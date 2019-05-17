<?php
/**
 * This file contains the forms to create and edit an instance of this module
 * @package   mod_evalcode
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

/**
 * EvalCode settings form.
 *
 * @package   mod_evalcode
 */
class mod_evalcode_mod_form extends moodleform_mod
{
    public $tools = array();
    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition()
    {
        global $CFG, $COURSE, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('evalcodeframeworkname', 'evalcode'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description', 'evalcode'));
        $optionsSelect = array(0 => 0, 10 => 10, 20 => 20, 30 => 30, 40 => 40, 50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90, 100 => 100);

        //NUEVO ############################
        $mform->addElement('header', 'tools', 'Evaluation Tools');
        $mform->setExpanded('tools', true);

        $mform->addElement('static','tituloseccion','Select tools to evaluate:');
        //get list of language dirs
        $dirs = array_filter(glob($CFG->dirroot . '/mod/evalcode/languageConfig/*'), 'is_dir'); 
        //create aux arrays
        $languages = array();
        
        foreach($dirs as $dir){ 
            //FIRST
            //extract name of language for each dir
            $lan = explode("/",$dir);
            //add language name to the array
            array_push($languages, end($lan));
            //add language to the form
            $mform->addElement('static', 'language', end($lan),'');

            //SECOND
            //get list of tools dirs
            $tooldirs = array_filter(glob($dir.'/*'), 'is_dir');
            foreach($tooldirs as $td){
                //get config.php from each tool
                if((include $td.'/config.php') == TRUE){
                    //add tool name to the array
                    array_push($this->tools, $languageTool->name);
                    $tool = $languageTool->name;
                    $mform->setType($tool, PARAM_TEXT);
                    //add tool to the checkbox
                    $grupo = array();
                    $grupo[] =& $mform->addElement('advcheckbox', 'tool_'.$tool, '',$tool, array('group' => 1), array(0, 1));
                    $grupo[] =& $mform->addElement('select', 'percentage_'.$tool, '', $optionsSelect);
                    $grupo[] =& $mform->addElement('text', 'params_'.$tool, 'Extra params for the tool:');
                    //$mform->addGroup($grupo, 'grupo', '', array(' '), true);
                    $mform->setType('tool_'.$tool, PARAM_INT);
                    $mform->setType('params_'.$tool, PARAM_RAW);
                    //echo 'Created tool_'.$tool.' ';
                    $mform->addElement('static', 'static','', 
                        'Name: '.$languageTool->name."\n"
                        .'Version: '.$languageTool->version."\n");
                    $mform->addElement('static', 'static','',
                        'Description: '.$languageTool->description);
                }
            }
            
        }
        // FIN NUEVO ############################
        $mform->addElement('filemanager', 'introattachments',
            'Provided Files',
            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes));

        $mform->addElement('filemanager', 'introattachmentsjunit',
            'Evaluation Files',
            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes));

        $mform->addHelpButton('introattachments', 'introattachments', 'evalcode');
        $mform->addHelpButton('introattachmentsjunit', 'introattachmentsjunit', 'evalcode');

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('evalcode', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $evalcodeframework = new evalcode($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id' => $this->current->course), '*', MUST_EXIST);
            $evalcodeframework->set_course($course);
        }
        
        

        // new option to create evalcode
        /*
        $mform->addElement('text', 'maxTestNumber', 'Maximum number of failed Test to pass');
        $mform->setType('maxTestNumber', PARAM_INT);
        $mform->setDefault('maxTestNumber', '3');

        $mform->addElement('text', 'maxErrorNumber', 'Maximum number of Quality errors to pass');
        $mform->setType('maxErrorNumber', PARAM_INT);
        $mform->setDefault('maxErrorNumber', '3');

        
        $mform->addElement('select', 'percentageTest', 'Percentage Test', $optionsSelect);
        $mform->setDefault('percentageTest', '70');
        $mform->addElement('select', 'percentageQuality', 'Percentage Quality', $optionsSelect);
        $mform->setDefault('percentageQuality', '30');
        */

        $mform->addElement('header', 'availability', get_string('availability', 'evalcode'));
        $mform->setExpanded('availability', true);

        $name = get_string('allowsubmissionsfromdate', 'evalcode');
        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'evalcode');

        $name = get_string('duedate', 'evalcode');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional' => true));
        $mform->addHelpButton('duedate', 'duedate', 'evalcode');

        $name = get_string('cutoffdate', 'evalcode');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional' => true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'evalcode');

        $name = get_string('alwaysshowdescription', 'evalcode');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'evalcode');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        $evalcodeframework->add_all_plugin_settings($mform);

        $mform->addElement('header', 'notifications', get_string('notifications', 'evalcode'));

        $name = get_string('sendnotifications', 'evalcode');
        $mform->addElement('selectyesno', 'sendnotifications', $name);
        $mform->addHelpButton('sendnotifications', 'sendnotifications', 'evalcode');

        $name = get_string('sendlatenotifications', 'evalcode');
        $mform->addElement('selectyesno', 'sendlatenotifications', $name);
        $mform->addHelpButton('sendlatenotifications', 'sendlatenotifications', 'evalcode');
        $mform->disabledIf('sendlatenotifications', 'sendnotifications', 'eq', 1);

        $name = get_string('sendstudentnotificationsdefault', 'evalcode');
        $mform->addElement('selectyesno', 'sendstudentnotifications', $name);
        $mform->addHelpButton('sendstudentnotifications', 'sendstudentnotificationsdefault', 'evalcode');

        $this->standard_coursemodule_elements();
        $this->apply_admin_defaults();

        $this->add_action_buttons();
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);
        if ($data['allowsubmissionsfromdate'] && $data['duedate']) {
            if ($data['allowsubmissionsfromdate'] > $data['duedate']) {
                $errors['duedate'] = get_string('duedatevalidation', 'evalcode');
            }
        }
        if ($data['duedate'] && $data['cutoffdate']) {
            if ($data['duedate'] > $data['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'evalcode');
            }
        }
        if ($data['allowsubmissionsfromdate'] && $data['cutoffdate']) {
            if ($data['allowsubmissionsfromdate'] > $data['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'evalcode');
            }
        }

        $aux = 0;
        $selected = false;
        foreach($this->tools as $t){
            $aux += intval($data['percentage_'.$t]);
            if($data['tool_'.$t] == 1){
                $selected = true;
            }
        }
        if($aux != 100){
            $errors['tituloseccion'] = 'Sum of tool percentages must equal 100. Please review values.';
        }
        if(!$selected){
            $errors['tituloseccion'] = 'Al least one tool must me selected.';
        }
        /*
        if ((intval($data['percentageTest']) + intval($data['percentageQuality'])) != 100) {
            $errors['percentageTest'] = 'Sum of percentages must equal 100. Please review values.';
            $errors['percentageQuality'] = 'Sum of percentages must equal 100. Please review values.';
        }
        if (!is_int($data['maxTestNumber'])) {
            $errors['maxTestNumber'] = 'Invalid max test number value.';
        }
        if (!is_int($data['maxErrorNumber'])) {
            $errors['maxErrorNumber'] = 'Invalid max errors number value.';
        }
        */

        return $errors;
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues)
    {
        global $DB;
        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('evalcode', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
            $record = $DB->get_record('evalcode',
                array('id' => $this->current->id),
                $fields='*',
                MUST_EXIST);
            //$defaultvalues['maxTestNumber'] = $record->maxtestnumber;
            //$defaultvalues['maxErrorNumber'] = $record->maxerrornumber;
            //$defaultvalues['percentageTest'] = intval($record->percentagetest);
            //$defaultvalues['percentageQuality'] = intval($record->percentagequality);
            //selectedtools:
            $xml=simplexml_load_string($record->selectedtools);//convertir el string xml a un objeto para parsear
            $toolslist = $xml->tool;
            foreach ($toolslist as $tool){
                $defaultvalues['tool_'.$tool->name] = 1;
                $defaultvalues['percentage_'.$tool->name] = $tool->percentage;
                $defaultvalues['params_'.$tool->name] = $tool->additional_params;
            }
        }
        $evalcodeframework = new evalcode($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id' => $this->current->course), '*', MUST_EXIST);
            $evalcodeframework->set_course($course);
        }

        $draftitemid = file_get_submitted_draft_itemid('introattachments');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_FILEAREA,
            0, array('subdirs' => 0));
        $defaultvalues['introattachments'] = $draftitemid;

        $draftitemid = file_get_submitted_draft_itemid('introattachmentsjunit');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_JUNIT,
            0, array('subdirs' => 0));
        $defaultvalues['introattachmentsjunit'] = $draftitemid;

        $evalcodeframework->plugin_data_preprocessing($defaultvalues);
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules()
    {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'evalcode'));
        return array('completionsubmit');
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data)
    {
        return !empty($data['completionsubmit']);
    }

}
