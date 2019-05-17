<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'evalcode');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/evalcode:view', $context);

$evalcode = new evalcode($context, $cm, $course);
$urlparams = array('id' => $id,
    'action' => optional_param('action', '', PARAM_TEXT),
    'rownum' => optional_param('rownum', 0, PARAM_INT),
    'useridlistid' => optional_param('useridlistid', $evalcode->get_useridlist_key_id(), PARAM_ALPHANUM));

$url = new moodle_url('/mod/evalcode/view.php', $urlparams);
$PAGE->set_url($url);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $evalcode->view(optional_param('action', '', PARAM_TEXT));
