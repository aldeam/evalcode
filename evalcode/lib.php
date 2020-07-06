<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the moodle hooks for the evalcode module.
 *
 * It delegates most functions to the evalcodeframework class.
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Adds an evalcodeframework instance
 *
 * This is done by calling the add_instance() method of the evalcodeframework type class
 * @param stdClass $data
 * @param mod_evalcode_mod_form $form
 * @return int The instance id of the new evalcodeframework
 */
function evalcode_add_instance(stdClass $data, mod_evalcode_mod_form $form = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $evalcodeframework = new evalcode(context_module::instance($data->coursemodule), null, null);

    return $evalcodeframework->add_instance($data, true);
}

/**
 * delete an evalcodeframework instance
 * @param int $id
 * @return bool
 */
function evalcode_delete_instance($id) {
    /* CODIGO INICIAL:
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $cm = get_coursemodule_from_instance('evalcode', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $evalcodeframework = new evalcode($context, null, null);
    return $evalcodeframework->delete_instance();
    */
    error_log("Delete lib.php \n", 3, "/var/tmp/evalcode.log");
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $cm = get_coursemodule_from_instance('evalcode', $id, 0, false, MUST_EXIST);
    error_log("evalcode course module id=".$cm->id."\n", 3, "/var/tmp/evalcode.log");
    error_log("evalcode course module name=".$cm->name."\n", 3, "/var/tmp/evalcode.log");
    $context = context_block::instance($cm->id);

    $evalcodeframework = new evalcode($context, null, null);
    return $evalcodeframework->delete_instance();
    /**
     *    global $CFG, $DB;

    if (! $assignment = $DB->get_record('assignment', array('id'=>$id))) {
    return false;
    }

    $result = true;
    // Now get rid of all files
    $fs = get_file_storage();
    if ($cm = get_coursemodule_from_instance('assignment', $assignment->id)) {
    $context = context_module::instance($cm->id);
    $fs->delete_area_files($context->id);
    }

    if (! $DB->delete_records('assignment_submissions', array('assignment'=>$assignment->id))) {
    $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'assignment', 'instance'=>$assignment->id))) {
    $result = false;
    }

    if (! $DB->delete_records('assignment', array('id'=>$assignment->id))) {
    $result = false;
    }

    grade_update('mod/assignment', $assignment->course, 'mod', 'assignment', $assignment->id, 0, NULL, array('deleted'=>1));

    return $result;
     */
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all evalcodeframework submissions and feedbacks in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array
 */
function evalcode_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $status = array();
    $params = array('courseid'=>$data->courseid);
    $sql = "SELECT a.id FROM {evalcode} a WHERE a.course=:courseid";
    $course = $DB->get_record('course', array('id'=>$data->courseid), '*', MUST_EXIST);
    if ($evalcodes = $DB->get_records_sql($sql, $params)) {
        foreach ($evalcodes as $evalcode) {
            $cm = get_coursemodule_from_instance('evalcode',
                                                 $evalcode->id,
                                                 $data->courseid,
                                                 false,
                                                 MUST_EXIST);
            $context = context_module::instance($cm->id);
            $evalcodeframework = new evalcode($context, $cm, $course);
            $status = array_merge($status, $evalcodeframework->reset_userdata($data));
        }
    }
    return $status;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every evalcodeframework event in the site is checked, else
 * only evalcodeframework events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @return bool
 */
function evalcode_refresh_events($courseid = 0) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$evalcodes = $DB->get_records('evalcode', array('course' => $courseid))) {
            return false;
        }
        // Get course from courseid parameter.
        if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
            return false;
        }
    } else {
        if (!$evalcodes = $DB->get_records('evalcode')) {
            return false;
        }
    }
    foreach ($evalcodes as $evalcode) {
        // Use evalcodeframework's course column if courseid parameter is not given.
        if (!$courseid) {
            $courseid = $evalcode->course;
            if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
                continue;
            }
        }
        if (!$cm = get_coursemodule_from_instance('evalcode', $evalcode->id, $courseid, false)) {
            continue;
        }
        $context = context_module::instance($cm->id);
        $evalcodeframework = new evalcode($context, $cm, $course);
        $evalcodeframework->update_calendar($cm->id);
    }

    return true;
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of evalcodeframework to limit the reset to a particular evalcodeframework type
 */
function evalcode_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $params = array('moduletype'=>'evalcode', 'courseid'=>$courseid);
    $sql = 'SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {evalcode} a, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid';

    if ($evalcodeframeworks = $DB->get_records_sql($sql, $params)) {
        foreach ($evalcodeframeworks as $evalcodeframework) {
            evalcode_grade_item_update($evalcodeframework, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the evalcodeframework.
 * @param moodleform $mform form passed by reference
 */
function evalcode_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'evalcodeheader', get_string('modulenameplural', 'evalcode'));
    $name = get_string('deleteallsubmissions', 'evalcode');
    $mform->addElement('advcheckbox', 'reset_eval_submissions', $name);
}

/**
 * Course reset form defaults.
 * @param  object $course
 * @return array
 */
function evalcode_reset_course_form_defaults($course) {
    return array('reset_eval_submissions'=>1);
}

/**
 * Update an evalcodeframework instance
 *
 * This is done by calling the update_instance() method of the evalcodeframework type class
 * @param stdClass $data
 * @param stdClass $form - unused
 * @return object
 */
function evalcode_update_instance(stdClass $data, $form) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $context = context_module::instance($data->coursemodule);
    $evalcodeframework = new evalcode($context, null, null);
    return $evalcodeframework->update_instance($data);
}

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function evalcode_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_PLAGIARISM:
            return true;

        default:
            return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function evalcode_grading_areas_list() {
    return array('submissions'=>get_string('submissions', 'evalcode'));
}


/**
 * extend an assigment navigation settings
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 */
function evalcode_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    global $PAGE, $DB;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = $cm->context;
    $course = $PAGE->course;

    if (!$course) {
        return;
    }

    // Link to gradebook.
    if (has_capability('gradereport/grader:view', $cm->context) &&
            has_capability('moodle/grade:viewall', $cm->context)) {
        $link = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
        $linkname = get_string('viewgradebook', 'evalcode');
        $node = $navref->add($linkname, $link, navigation_node::TYPE_SETTING);
    }

    // Link to download all submissions.
    if (has_any_capability(array('mod/evalcode:grade', 'mod/evalcode:viewgrades'), $context)) {
        $link = new moodle_url('/mod/evalcode/view.php', array('id' => $cm->id, 'action'=>'grading'));
        $node = $navref->add(get_string('viewgrading', 'evalcode'), $link, navigation_node::TYPE_SETTING);

        $link = new moodle_url('/mod/evalcode/view.php', array('id' => $cm->id, 'action'=>'downloadall'));
        $node = $navref->add(get_string('downloadall', 'evalcode'), $link, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/evalcode:revealidentities', $context)) {
        $dbparams = array('id'=>$cm->instance);
        $evalcodeframework = $DB->get_record('evalcode', $dbparams, 'blindmarking, revealidentities');

        if ($evalcodeframework && $evalcodeframework->blindmarking && !$evalcodeframework->revealidentities) {
            $urlparams = array('id' => $cm->id, 'action'=>'revealidentities');
            $url = new moodle_url('/mod/evalcode/view.php', $urlparams);
            $linkname = get_string('revealidentities', 'evalcode');
            $node = $navref->add($linkname, $url, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Add a get_coursemodule_info function in case any evalcodeframework type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function evalcode_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = array('id'=>$coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, allowsubmissionsfromdate, intro, introformat';
    if (! $evalcodeframework = $DB->get_record('evalcode', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $evalcodeframework->name;
    if ($coursemodule->showdescription) {
        if ($evalcodeframework->alwaysshowdescription || time() > $evalcodeframework->allowsubmissionsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('evalcode', $evalcodeframework, $coursemodule->id, false);
        }
    }
    return $result;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function evalcode_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-evalcode-*' => get_string('page-mod-evalcode-x', 'evalcode'),
        'mod-evalcode-view' => get_string('page-mod-evalcode-view', 'evalcode'),
    );
    return $modulepagetype;
}

/**
 * Print an overview of all evalcodeframeworks
 * for the courses.
 *
 * @param mixed $courses The list of courses to print the overview for
 * @param array $htmlarray The array of html to return
 *
 * @return true
 */
function evalcode_print_overview($courses, &$htmlarray) {
    global $CFG, $DB;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return true;
    }

    if (!$evalcodeframeworks = get_all_instances_in_courses('evalcode', $courses)) {
        return true;
    }

    $evalcodeframeworkids = array();

    // Do evalcodeframework_base::isopen() here without loading the whole thing for speed.
    foreach ($evalcodeframeworks as $key => $evalcodeframework) {
        $time = time();
        $isopen = false;
        if ($evalcodeframework->duedate) {
            $duedate = false;
            if ($evalcodeframework->cutoffdate) {
                $duedate = $evalcodeframework->cutoffdate;
            }
            if ($duedate) {
                $isopen = ($evalcodeframework->allowsubmissionsfromdate <= $time && $time <= $duedate);
            } else {
                $isopen = ($evalcodeframework->allowsubmissionsfromdate <= $time);
            }
        }
        if ($isopen) {
            $evalcodeframeworkids[] = $evalcodeframework->id;
        }
    }

    if (empty($evalcodeframeworkids)) {
        // No evalcodeframeworks to look at - we're done.
        return true;
    }

    // Definitely something to print, now include the constants we need.
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $strduedate = get_string('duedate', 'evalcode');
    $strcutoffdate = get_string('nosubmissionsacceptedafter', 'evalcode');
    $strnolatesubmissions = get_string('nolatesubmissions', 'evalcode');
    $strduedateno = get_string('duedateno', 'evalcode');
    $strevalcodeframework = get_string('modulename', 'evalcode');

    // We do all possible database work here *outside* of the loop to ensure this scales.
    list($sqlevalcodeframeworkids, $evalcodeframeworkidparams) = $DB->get_in_or_equal($evalcodeframeworkids);

    $mysubmissions = null;
    $unmarkedsubmissions = null;

    foreach ($evalcodeframeworks as $evalcodeframework) {

        // Do not show evalcodeframeworks that are not open.
        if (!in_array($evalcodeframework->id, $evalcodeframeworkids)) {
            continue;
        }

        $context = context_module::instance($evalcodeframework->coursemodule);

        // Does the submission status of the evalcodeframework require notification?
        if (has_capability('mod/evalcode:submit', $context)) {
            // Does the submission status of the evalcodeframework require notification?
            $submitdetails = evalcode_get_mysubmission_details_for_print_overview($mysubmissions, $sqlevalcodeframeworkids,
                    $evalcodeframeworkidparams, $evalcodeframework);
        } else {
            $submitdetails = false;
        }

        if (has_capability('mod/evalcode:grade', $context)) {
            // Does the grading status of the evalcodeframework require notification ?
            $gradedetails = evalcode_get_grade_details_for_print_overview($unmarkedsubmissions, $sqlevalcodeframeworkids,
                    $evalcodeframeworkidparams, $evalcodeframework, $context);
        } else {
            $gradedetails = false;
        }

        if (empty($submitdetails) && empty($gradedetails)) {
            // There is no need to display this evalcodeframework as there is nothing to notify.
            continue;
        }

        $dimmedclass = '';
        if (!$evalcodeframework->visible) {
            $dimmedclass = ' class="dimmed"';
        }
        $href = $CFG->wwwroot . '/mod/evalcode/view.php?id=' . $evalcodeframework->coursemodule;
        $basestr = '<div class="evalcode overview">' .
               '<div class="name">' .
               $strevalcodeframework . ': '.
               '<a ' . $dimmedclass .
                   'title="' . $strevalcodeframework . '" ' .
                   'href="' . $href . '">' .
               format_string($evalcodeframework->name) .
               '</a></div>';
        if ($evalcodeframework->duedate) {
            $userdate = userdate($evalcodeframework->duedate);
            $basestr .= '<div class="info">' . $strduedate . ': ' . $userdate . '</div>';
        } else {
            $basestr .= '<div class="info">' . $strduedateno . '</div>';
        }
        if ($evalcodeframework->cutoffdate) {
            if ($evalcodeframework->cutoffdate == $evalcodeframework->duedate) {
                $basestr .= '<div class="info">' . $strnolatesubmissions . '</div>';
            } else {
                $userdate = userdate($evalcodeframework->cutoffdate);
                $basestr .= '<div class="info">' . $strcutoffdate . ': ' . $userdate . '</div>';
            }
        }

        // Show only relevant information.
        if (!empty($submitdetails)) {
            $basestr .= $submitdetails;
        }

        if (!empty($gradedetails)) {
            $basestr .= $gradedetails;
        }
        $basestr .= '</div>';

        if (empty($htmlarray[$evalcodeframework->course]['evalcode'])) {
            $htmlarray[$evalcodeframework->course]['evalcode'] = $basestr;
        } else {
            $htmlarray[$evalcodeframework->course]['evalcode'] .= $basestr;
        }
    }
    return true;
}

/**
 * This api generates html to be displayed to students in print overview section, related to their submission status of the given
 * evalcodeframework.
 *
 * @param array $mysubmissions list of submissions of current user indexed by evalcodeframework id.
 * @param string $sqlevalcodeframeworkids sql clause used to filter open evalcodeframeworks.
 * @param array $evalcodeframeworkidparams sql params used to filter open evalcodeframeworks.
 * @param stdClass $evalcodeframework current evalcodeframework
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function evalcode_get_mysubmission_details_for_print_overview(&$mysubmissions, $sqlevalcodeframeworkids, $evalcodeframeworkidparams,
                                                            $evalcodeframework) {
    global $USER, $DB;

    if ($evalcodeframework->nosubmissions) {
        // Offline evalcodeframework. No need to display alerts for offline evalcodeframeworks.
        return false;
    }

    $strnotsubmittedyet = get_string('notsubmittedyet', 'evalcode');

    if (!isset($mysubmissions)) {

        // Get all user submissions, indexed by evalcodeframework id.
        $dbparams = array_merge(array($USER->id), $evalcodeframeworkidparams, array($USER->id));
        $mysubmissions = $DB->get_records_sql('SELECT a.id AS evalcodeframework,
                                                      a.nosubmissions AS nosubmissions,
                                                      g.timemodified AS timemarked,
                                                      g.grader AS grader,
                                                      g.grade AS grade,
                                                      s.status AS status
                                                 FROM {evalcode} a, {eval_submission} s
                                            LEFT JOIN {evalcode_grades} g ON
                                                      g.evalcodeframework = s.evalcodeframework AND
                                                      g.userid = ? AND
                                                      g.attemptnumber = s.attemptnumber
                                                WHERE a.id ' . $sqlevalcodeframeworkids . ' AND
                                                      s.latest = 1 AND
                                                      s.evalcodeframework = a.id AND
                                                      s.userid = ?', $dbparams);
    }

    $submitdetails = '';
    $submitdetails .= '<div class="details">';
    $submitdetails .= get_string('mysubmission', 'evalcode');
    $submission = false;

    if (isset($mysubmissions[$evalcodeframework->id])) {
        $submission = $mysubmissions[$evalcodeframework->id];
    }

    if ($submission && $submission->status == EVAL_SUBMISSION_STATUS_SUBMITTED) {
        // A valid submission already exists, no need to notify students about this.
        return false;
    }

    // We need to show details only if a valid submission doesn't exist.
    if (!$submission ||
        !$submission->status ||
        $submission->status == EVAL_SUBMISSION_STATUS_DRAFT ||
        $submission->status == EVAL_SUBMISSION_STATUS_NEW
    ) {
        $submitdetails .= $strnotsubmittedyet;
    } else {
        $submitdetails .= get_string('submissionstatus_' . $submission->status, 'evalcode');
    }
    if ($evalcodeframework->markingworkflow) {
        $workflowstate = $DB->get_field('evalcode_user_flags', 'workflowstate', array('evalcodeframework' =>
                $evalcodeframework->id, 'userid' => $USER->id));
        if ($workflowstate) {
            $gradingstatus = 'markingworkflowstate' . $workflowstate;
        } else {
            $gradingstatus = 'markingworkflowstate' . EVALCODE_MARKING_WORKFLOW_STATE_NOTMARKED;
        }
    } else if (!empty($submission->grade) && $submission->grade !== null && $submission->grade >= 0) {
        $gradingstatus = EVALCODE_GRADING_STATUS_GRADED;
    } else {
        $gradingstatus = EVALCODE_GRADING_STATUS_NOT_GRADED;
    }
    $submitdetails .= ', ' . get_string($gradingstatus, 'evalcode');
    $submitdetails .= '</div>';
    return $submitdetails;
}

/**
 * This api generates html to be displayed to teachers in print overview section, related to the grading status of the given
 * evalcodeframework's submissions.
 *
 * @param array $unmarkedsubmissions list of submissions of that are currently unmarked indexed by evalcodeframework id.
 * @param string $sqlevalcodeframeworkids sql clause used to filter open evalcodeframeworks.
 * @param array $evalcodeframeworkidparams sql params used to filter open evalcodeframeworks.
 * @param stdClass $evalcodeframework current evalcodeframework
 * @param context $context context of the evalcodeframework.
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function evalcode_get_grade_details_for_print_overview(&$unmarkedsubmissions, $sqlevalcodeframeworkids, $evalcodeframeworkidparams,
                                                     $evalcodeframework, $context) {
    global $DB;
    if (!isset($unmarkedsubmissions)) {
        // Build up and array of unmarked submissions indexed by evalcodeframework id/ userid
        // for use where the user has grading rights on evalcodeframework.
        $dbparams = array_merge(array(EVAL_SUBMISSION_STATUS_SUBMITTED), $evalcodeframeworkidparams);
        $rs = $DB->get_recordset_sql('SELECT s.evalcodeframework as evalcodeframework,
                                             s.userid as userid,
                                             s.id as id,
                                             s.status as status,
                                             g.timemodified as timegraded
                                        FROM {eval_submission} s
                                   LEFT JOIN {evalcode_grades} g ON
                                             s.userid = g.userid AND
                                             s.evalcodeframework = g.evalcodeframework AND
                                             g.attemptnumber = s.attemptnumber
                                       WHERE
                                             ( g.timemodified is NULL OR
                                             s.timemodified > g.timemodified OR
                                             g.grade IS NULL ) AND
                                             s.timemodified IS NOT NULL AND
                                             s.status = ? AND
                                             s.latest = 1 AND
                                             s.evalcodeframework ' . $sqlevalcodeframeworkids, $dbparams);

        $unmarkedsubmissions = array();
        foreach ($rs as $rd) {
            $unmarkedsubmissions[$rd->evalcodeframework][$rd->userid] = $rd->id;
        }
        $rs->close();
    }

    // Count how many people can submit.
    $submissions = 0;
    if ($students = get_enrolled_users($context, 'mod/evalcode:view', 0, 'u.id')) {
        foreach ($students as $student) {
            if (isset($unmarkedsubmissions[$evalcodeframework->id][$student->id])) {
                $submissions++;
            }
        }
    }

    if ($submissions) {
        $urlparams = array('id' => $evalcodeframework->coursemodule, 'action' => 'grading');
        $url = new moodle_url('/mod/evalcode/view.php', $urlparams);
        $gradedetails = '<div class="details">' .
                '<a href="' . $url . '">' .
                get_string('submissionsnotgraded', 'evalcode', $submissions) .
                '</a></div>';
        return $gradedetails;
    } else {
        return false;
    }

}

/**
 * Print recent activity from all evalcodeframeworks in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function evalcode_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    // Do not use log table if possible, it may be huge.

    $dbparams = array($timestart, $course->id, 'evalcode', EVAL_SUBMISSION_STATUS_SUBMITTED);
    $namefields = user_picture::fields('u', null, 'userid');
    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, um.id as recordid,
                                                     $namefields
                                                FROM {eval_submission} asb
                                                     JOIN {evalcode} a      ON a.id = asb.evalcodeframework
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                                LEFT JOIN {evalcode_user_mapping} um ON um.userid = u.id AND um.evalcodeframework = a.id
                                               WHERE asb.timemodified > ? AND
                                                     asb.latest = 1 AND
                                                     a.course = ? AND
                                                     md.name = ? AND
                                                     asb.status = ?
                                            ORDER BY asb.timemodified ASC", $dbparams)) {
         return false;
    }

    $modinfo = get_fast_modinfo($course);
    $show    = array();
    $grader  = array();

    $showrecentsubmissions = get_config('evalcode', 'showrecentsubmissions');

    foreach ($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($submission->cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        $context = context_module::instance($submission->cmid);
        // The act of submitting of evalcodeframework may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'evalcode').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $evalcode = new evalcode($context, $cm, $cm->course);
        $link = $CFG->wwwroot.'/mod/evalcode/view.php?id='.$cm->id;
        // Obscure first and last name if blind marking enabled.
        if ($evalcode->is_blind_marking()) {
            $submission->firstname = get_string('participant', 'mod_evalcode');
            if (empty($submission->recordid)) {
                $submission->recordid = $evalcode->get_uniqueid_for_user($submission->userid);
            }
            $submission->lastname = $submission->recordid;
        }
        print_recent_activity_note($submission->timemodified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }

    return true;
}

/**
 * Returns all evalcodeframeworks since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function evalcode_get_recent_mod_activity(&$activities,
                                        &$index,
                                        $timestart,
                                        $courseid,
                                        $cmid,
                                        $userid=0,
                                        $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->get_cm($cmid);
    $params = array();
    if ($userid) {
        $userselect = 'AND u.id = :userid';
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;
    $params['submitted'] = EVAL_SUBMISSION_STATUS_SUBMITTED;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql('SELECT asb.id, asb.timemodified, ' .
                                                     $userfields .
                                             '  FROM {eval_submission} asb
                                                JOIN {evalcode} a ON a.id = asb.evalcodeframework
                                                JOIN {user} u ON u.id = asb.userid ' .
                                          $groupjoin .
                                            '  WHERE asb.timemodified > :timestart AND
                                                     asb.status = :submitted AND
                                                     a.id = :cminstance
                                                     ' . $userselect . ' ' . $groupselect .
                                            ' ORDER BY asb.timemodified ASC', $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext      = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cmcontext);


    $showrecentsubmissions = get_config('evalcode', 'showrecentsubmissions');
    $show = array();
    foreach ($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // The act of submitting of evalcodeframework may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $userids = array();
        foreach ($show as $id => $submission) {
            $userids[] = $submission->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'evalcode', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($show as $submission) {
        $activity = new stdClass();

        $activity->type         = 'evalcode';
        $activity->cmid         = $cm->id;
        $activity->name         = $aname;
        $activity->sectionnum   = $cm->sectionnum;
        $activity->timestamp    = $submission->timemodified;
        $activity->user         = new stdClass();
        if ($grader) {
            $activity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                // Aliased in SQL above.
                $activity->user->{$userfield} = $submission->userid;
            } else {
                $activity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $activity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $activity;
    }

    return;
}

/**
 * Print recent activity from all evalcodeframeworks in a given course
 *
 * This is used by course/recent.php
 * @param stdClass $activity
 * @param int $courseid
 * @param bool $detail
 * @param array $modnames
 */
function evalcode_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="evalcodeframework-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo '<img src="' . $OUTPUT->pix_url('icon', 'evalcode') . '" '.
             'class="icon" alt="' . $modname . '">';
        echo '<a href="' . $CFG->wwwroot . '/mod/evalcode/view.php?id=' . $activity->cmid . '">';
        echo $activity->name;
        echo '</a>';
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">";
    echo "{$activity->user->fullname}</a>  - " . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Checks if a scale is being used by an evalcodeframework.
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param int $evalcodeframeworkid
 * @param int $scaleid
 * @return boolean True if the scale is used by the evalcodeframework
 */
function evalcode_scale_used($evalcodeframeworkid, $scaleid) {
    global $DB;

    $return = false;
    $rec = $DB->get_record('evalcode', array('id'=>$evalcodeframeworkid, 'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of evalcodeframework
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any evalcodeframework
 */
function evalcode_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('evalcode', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function evalcode_get_view_actions() {
    return array('view submission', 'view feedback');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function evalcode_get_post_actions() {
    return array('upload', 'submit', 'submit for grading');
}

/**
 * Call cron on the evalcode module.
 */
function evalcode_cron() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    evalcode::cron();

    $plugins = core_component::get_plugin_list('evalsubmission');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('evalsubmission_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'eval_submission_' . $name;
            require_once($CFG->dirroot . '/mod/evalcode/submission/' . $name . '/locallib.php');
            $class::cron();
        }
    }
    $plugins = core_component::get_plugin_list('evalfeedback');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('evalfeedback_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'eval_feedback_' . $name;
            require_once($CFG->dirroot . '/mod/evalcode/feedback/' . $name . '/locallib.php');
            $class::cron();
        }
    }

    return true;
}

/**
 * Returns all other capabilities used by this module.
 * @return array Array of capability strings
 */
function evalcode_get_extra_capabilities() {
    return array('gradereport/grader:view',
                 'moodle/grade:viewall',
                 'moodle/site:viewfullnames',
                 'moodle/site:config');
}

/**
 * Create grade item for given evalcodeframework.
 *
 * @param stdClass $evalcode record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function evalcode_grade_item_update($evalcode, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($evalcode->courseid)) {
        $evalcode->courseid = $evalcode->course;
    }

    $params = array('itemname'=>$evalcode->name, 'idnumber'=>$evalcode->cmidnumber);

    // Check if feedback plugin for gradebook is enabled, if yes then
    // gradetype = GRADE_TYPE_TEXT else GRADE_TYPE_NONE.
    $gradefeedbackenabled = false;

    if (isset($evalcode->gradefeedbackenabled)) {
        $gradefeedbackenabled = $evalcode->gradefeedbackenabled;
    } else if ($evalcode->grade == 0) { // Grade feedback is needed only when grade == 0.
        require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
        $mod = get_coursemodule_from_instance('evalcode', $evalcode->id, $evalcode->courseid);
        $cm = context_module::instance($mod->id);
        $evalcodeframework = new evalcode($cm, null, null);
        $gradefeedbackenabled = $evalcodeframework->is_gradebook_feedback_enabled();
    }

    if ($evalcode->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $evalcode->grade;
        $params['grademin']  = 0;

    } else if ($evalcode->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$evalcode->grade;

    } else if ($gradefeedbackenabled) {
        // $evalcode->grade == 0 and feedback enabled.
        $params['gradetype'] = GRADE_TYPE_TEXT;
    } else {
        // $evalcode->grade == 0 and no feedback enabled.
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/evalcode',
                        $evalcode->courseid,
                        'mod',
                        'evalcode',
                        $evalcode->id,
                        0,
                        $grades,
                        $params);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $evalcode record of evalcode with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function evalcode_get_user_grades($evalcode, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $cm = get_coursemodule_from_instance('evalcode', $evalcode->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $evalcodeframework = new evalcode($context, null, null);
    $evalcodeframework->set_instance($evalcode);
    return $evalcodeframework->get_user_grades_for_gradebook($userid);
}

/**
 * Update activity grades.
 *
 * @param stdClass $evalcode database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function evalcode_update_grades($evalcode, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if ($evalcode->grade == 0) {
        evalcode_grade_item_update($evalcode);

    } else if ($grades = evalcode_get_user_grades($evalcode, $userid)) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        evalcode_grade_item_update($evalcode, $grades);

    } else {
        evalcode_grade_item_update($evalcode);
    }
}

/**
 * List the file areas that can be browsed.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array
 */
function evalcode_get_file_areas($course, $cm, $context) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $areas = array(
        EVALCODE_INTROATTACHMENT_FILEAREA => get_string('introattachments', 'mod_evalcode'),
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        EVALCODE_INTROATTACHMENT_JUNIT => get_string('introattachmentsjunit', 'mod_evalcode'),
        
        EVALCODE_PLAGIARISM_FILEAREA => get_string('plagiarismteacherfiles', 'mod_evalcode')
    );

    $evalcodeframework = new evalcode($context, $cm, $course);
    foreach ($evalcodeframework->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }
    foreach ($evalcodeframework->get_feedback_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }

    return $areas;
}

/**
 * File browsing support for evalcode module.
 *
 * @param file_browser $browser
 * @param object $areas
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return object file_info instance or null if not found
 */
function evalcode_get_file_info($browser,
                              $areas,
                              $course,
                              $cm,
                              $context,
                              $filearea,
                              $itemid,
                              $filepath,
                              $filename) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;

    // Need to find where this belongs to.
    $evalcodeframework = new evalcode($context, $cm, $course);
    if ($filearea === EVALCODE_INTROATTACHMENT_FILEAREA) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($evalcodeframework->get_context()->id,
                                          'mod_evalcode', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $evalcodeframework->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }


    /////////////////////////////////////////////////////////////////////////////////////////////////
    if ($filearea === EVALCODE_INTROATTACHMENT_JUNIT) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($evalcodeframework->get_context()->id,
                                          'mod_evalcode', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $evalcodeframework->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }
    
    /////////////////////////////////////////////////////////////////////////////////////////////////
    if ($filearea === EVALCODE_PLAGIARISM_FILEAREA) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($evalcodeframework->get_context()->id,
                                          'mod_evalcode', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $evalcodeframework->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }

    $pluginowner = null;
    foreach ($evalcodeframework->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if (array_key_exists($filearea, $pluginareas)) {
                $pluginowner = $plugin;
                break;
            }
        }
    }
    if (!$pluginowner) {
        foreach ($evalcodeframework->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginareas = $plugin->get_file_areas();

                if (array_key_exists($filearea, $pluginareas)) {
                    $pluginowner = $plugin;
                    break;
                }
            }
        }
    }

    if (!$pluginowner) {
        return null;
    }

    $result = $pluginowner->get_file_info($browser, $filearea, $itemid, $filepath, $filename);
    return $result;
}

/**
 * Prints the complete info about a user's interaction with an evalcodeframework.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $evalcode the database evalcode record
 *
 * This prints the submission summary and feedback summary for this student.
 */
function evalcode_user_complete($course, $user, $coursemodule, $evalcode) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $context = context_module::instance($coursemodule->id);

    $evalcodeframework = new evalcode($context, $coursemodule, $course);

    echo $evalcodeframework->view_student_summary($user, false);
}

/**
 * Rescale all grades for this activity and push the new grades to the gradebook.
 *
 * @param stdClass $course Course db record
 * @param stdClass $cm Course module db record
 * @param float $oldmin
 * @param float $oldmax
 * @param float $newmin
 * @param float $newmax
 */
function evalcode_rescale_activity_grades($course, $cm, $oldmin, $oldmax, $newmin, $newmax) {
    global $DB;

    if ($oldmax <= $oldmin) {
        // Grades cannot be scaled.
        return false;
    }
    $scale = ($newmax - $newmin) / ($oldmax - $oldmin);
    if (($newmax - $newmin) <= 1) {
        // We would lose too much precision, lets bail.
        return false;
    }

    $params = array(
        'p1' => $oldmin,
        'p2' => $scale,
        'p3' => $newmin,
        'a' => $cm->instance
    );

    $sql = 'UPDATE {evalcode_grades} set grade = (((grade - :p1) * :p2) + :p3) where evalcodeframework = :a';
    $dbupdate = $DB->execute($sql, $params);
    if (!$dbupdate) {
        return false;
    }

    // Now re-push all grades to the gradebook.
    $dbparams = array('id' => $cm->instance);
    $evalcode = $DB->get_record('evalcode', $dbparams);
    $evalcode->cmidnumber = $cm->idnumber;

    evalcode_update_grades($evalcode);

    return true;
}

/**
 * Print the grade information for the evalcodeframework for this user.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $evalcodeframework
 */
function evalcode_user_outline($course, $user, $coursemodule, $evalcodeframework) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/grading/lib.php');

    $gradinginfo = grade_get_grades($course->id,
                                        'mod',
                                        'evalcode',
                                        $evalcodeframework->id,
                                        $user->id);

    $gradingitem = $gradinginfo->items[0];
    $gradebookgrade = $gradingitem->grades[$user->id];

    if (empty($gradebookgrade->str_long_grade)) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('outlinegrade', 'evalcode', $gradebookgrade->str_long_grade);
    $result->time = $gradebookgrade->dategraded;

    return $result;
}

/**
 * Obtains the automatic completion state for this module based on any conditions
 * in evalcode settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function evalcode_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

    $evalcode = new evalcode(null, $cm, $course);

    // If completion option is enabled, evaluate it and return true/false.
    if ($evalcode->get_instance()->completionsubmit) {
        if ($evalcode->get_instance()->teamsubmission) {
            $submission = $evalcode->get_group_submission($userid, 0, false);
        } else {
            $submission = $evalcode->get_user_submission($userid, false);
        }
        return $submission && $submission->status == EVAL_SUBMISSION_STATUS_SUBMITTED;
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * Serves intro attachment files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function evalcode_pluginfile($course,
                $cm,
                context $context,
                $filearea,
                $args,
                $forcedownload,
                array $options=array()) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    if (!has_capability('mod/evalcode:view', $context)) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $evalcode = new evalcode($context, $cm, $course);
//////////////////////////////////////////////////////////////////////////////////
   // if (($filearea !== EVALCODE_INTROATTACHMENT_FILEAREA) || ($filearea !== EVALCODE_INTROATTACHMENT_JUNIT)) {
    if (($filearea !== EVALCODE_INTROATTACHMENT_FILEAREA) ) {
        return false;
    }
    if (!$evalcode->show_intro()) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if ($itemid != 0) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/mod_evalcode/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve the grading panel as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_evalcode_output_fragment_gradingpanel($args) {
    global $CFG;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
    $evalcode = new evalcode($context, null, null);

    $userid = clean_param($args['userid'], PARAM_INT);
    $attemptnumber = clean_param($args['attemptnumber'], PARAM_INT);
    $formdata = array();
    if (!empty($args['jsonformdata'])) {
        $serialiseddata = json_decode($args['jsonformdata']);
        parse_str($serialiseddata, $formdata);
    }
    $viewargs = array(
        'userid' => $userid,
        'attemptnumber' => $attemptnumber,
        'formdata' => $formdata
    );

    return $evalcode->view('gradingpanel', $viewargs);
}




