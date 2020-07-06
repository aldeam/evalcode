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
 * External evalcode API
 *
 * @package    mod_evalcode
 * @since      Moodle 2.4
 * @copyright  2012 Paul Charsley
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/externallib.php");
require_once("$CFG->dirroot/mod/evalcode/locallib.php");

/**
 * EvalCode functions
 * @copyright 2012 Paul Charsley
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_evalcode_external extends external_api {

    /**
     * Generate a warning in a standard structure for a known failure.
     *
     * @param int $evalcodeframeworkid - The evalcodeframework
     * @param string $warningcode - The key for the warning message
     * @param string $detail - A description of the error
     * @return array - Warning structure containing item, itemid, warningcode, message
     */
    private static function generate_warning($evalcodeframeworkid, $warningcode, $detail) {
        $warningmessages = array(
            'couldnotlock'=>'Could not lock the submission for this user.',
            'couldnotunlock'=>'Could not unlock the submission for this user.',
            'couldnotsubmitforgrading'=>'Could not submit evalcodeframework for grading.',
            'couldnotrevealidentities'=>'Could not reveal identities.',
            'couldnotgrantextensions'=>'Could not grant submission date extensions.',
            'couldnotrevert'=>'Could not revert submission to draft.',
            'invalidparameters'=>'Invalid parameters.',
            'couldnotsavesubmission'=>'Could not save submission.',
            'couldnotsavegrade'=>'Could not save grade.'
        );

        $message = $warningmessages[$warningcode];
        if (empty($message)) {
            $message = 'Unknown warning type.';
        }

        return array('item'=>$detail,
                     'itemid'=>$evalcodeframeworkid,
                     'warningcode'=>$warningcode,
                     'message'=>$message);
    }

    /**
     * Describes the parameters for get_grades
     * @return external_external_function_parameters
     * @since  Moodle 2.4
     */
    public static function get_grades_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'evalcodeframework id'),
                    '1 or more evalcodeframework ids',
                    VALUE_REQUIRED),
                'since' => new external_value(PARAM_INT,
                          'timestamp, only return records where timemodified >= since',
                          VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns grade information from evalcode_grades for the requested evalcodeframework ids
     * @param int[] $evalcodeframeworkids
     * @param int $since only return records with timemodified >= since
     * @return array of grade records for each requested evalcodeframework
     * @since  Moodle 2.4
     */
    public static function get_grades($evalcodeframeworkids, $since = 0) {
        global $DB;
        $params = self::validate_parameters(self::get_grades_parameters(),
                        array('evalcodeframeworkids' => $evalcodeframeworkids,
                              'since' => $since));

        $evalcodeframeworks = array();
        $warnings = array();
        $requestedevalcodeframeworkids = $params['evalcodeframeworkids'];

        // Check the user is allowed to get the grades for the evalcodeframeworks requested.
        $placeholders = array();
        list($sqlevalcodeframeworkids, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module ".
               "WHERE md.name = :modname AND cm.instance ".$sqlevalcodeframeworkids;
        $placeholders['modname'] = 'evalcode';
        $cms = $DB->get_records_sql($sql, $placeholders);
        foreach ($cms as $cm) {
            try {
                $context = context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/evalcode:grade', $context);
            } catch (Exception $e) {
                $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($cm->instance));
                $warning = array();
                $warning['item'] = 'evalcodeframework';
                $warning['itemid'] = $cm->instance;
                $warning['warningcode'] = '1';
                $warning['message'] = 'No access rights in module context';
                $warnings[] = $warning;
            }
        }

        // Create the query and populate an array of grade records from the recordset results.
        if (count ($requestedevalcodeframeworkids) > 0) {
            $placeholders = array();
            list($inorequalsql, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);

            $sql = "SELECT ag.id,
                           ag.evalcodeframework,
                           ag.userid,
                           ag.timecreated,
                           ag.timemodified,
                           ag.grader,
                           ag.grade,
                           ag.attemptnumber
                      FROM {evalcode_grades} ag, {eval_submission} s
                     WHERE s.evalcodeframework $inorequalsql
                       AND s.userid = ag.userid
                       AND s.latest = 1
                       AND s.attemptnumber = ag.attemptnumber
                       AND ag.timemodified  >= :since
                       AND ag.evalcodeframework = s.evalcodeframework
                  ORDER BY ag.evalcodeframework, ag.id";

            $placeholders['since'] = $params['since'];
            $rs = $DB->get_recordset_sql($sql, $placeholders);
            $currentevalcodeframeworkid = null;
            $evalcodeframework = null;
            foreach ($rs as $rd) {
                $grade = array();
                $grade['id'] = $rd->id;
                $grade['userid'] = $rd->userid;
                $grade['timecreated'] = $rd->timecreated;
                $grade['timemodified'] = $rd->timemodified;
                $grade['grader'] = $rd->grader;
                $grade['attemptnumber'] = $rd->attemptnumber;
                $grade['grade'] = (string)$rd->grade;

                if (is_null($currentevalcodeframeworkid) || ($rd->evalcodeframework != $currentevalcodeframeworkid )) {
                    if (!is_null($evalcodeframework)) {
                        $evalcodeframeworks[] = $evalcodeframework;
                    }
                    $evalcodeframework = array();
                    $evalcodeframework['evalcodeframeworkid'] = $rd->evalcodeframework;
                    $evalcodeframework['grades'] = array();
                    $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($rd->evalcodeframework));
                }
                $evalcodeframework['grades'][] = $grade;

                $currentevalcodeframeworkid = $rd->evalcodeframework;
            }
            if (!is_null($evalcodeframework)) {
                $evalcodeframeworks[] = $evalcodeframework;
            }
            $rs->close();
        }
        foreach ($requestedevalcodeframeworkids as $evalcodeframeworkid) {
            $warning = array();
            $warning['item'] = 'evalcodeframework';
            $warning['itemid'] = $evalcodeframeworkid;
            $warning['warningcode'] = '3';
            $warning['message'] = 'No grades found';
            $warnings[] = $warning;
        }

        $result = array();
        $result['evalcodeframeworks'] = $evalcodeframeworks;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates a grade single structure.
     *
     * @return external_single_structure a grade single structure.
     * @since  Moodle 3.1
     */
    private static function get_grade_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'id'                => new external_value(PARAM_INT, 'grade id'),
                'evalcodeframework'        => new external_value(PARAM_INT, 'evalcodeframework id', VALUE_OPTIONAL),
                'userid'            => new external_value(PARAM_INT, 'student id'),
                'attemptnumber'     => new external_value(PARAM_INT, 'attempt number'),
                'timecreated'       => new external_value(PARAM_INT, 'grade creation time'),
                'timemodified'      => new external_value(PARAM_INT, 'grade last modified time'),
                'grader'            => new external_value(PARAM_INT, 'grader'),
                'grade'             => new external_value(PARAM_TEXT, 'grade'),
                'gradefordisplay'   => new external_value(PARAM_RAW, 'grade rendered into a format suitable for display',
                                                            VALUE_OPTIONAL),
            ), 'grade information', $required
        );
    }

    /**
     * Creates an evalcode_grades external_single_structure
     * @return external_single_structure
     * @since  Moodle 2.4
     */
    private static function evalcode_grades() {
        return new external_single_structure(
            array (
                'evalcodeframeworkid'  => new external_value(PARAM_INT, 'evalcodeframework id'),
                'grades'        => new external_multiple_structure(self::get_grade_structure())
            )
        );
    }

    /**
     * Describes the get_grades return value
     * @return external_single_structure
     * @since  Moodle 2.4
     */
    public static function get_grades_returns() {
        return new external_single_structure(
            array(
                'evalcodeframeworks' => new external_multiple_structure(self::evalcode_grades(), 'list of evalcodeframework grade information'),
                'warnings'      => new external_warnings('item is always \'evalcodeframework\'',
                    'when errorcode is 3 then itemid is an evalcodeframework id. When errorcode is 1, itemid is a course module id',
                    'errorcode can be 3 (no grades found) or 1 (no permission to get grades)')
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since  Moodle 2.4
     */
    public static function get_evalcodeframeworks_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id, empty for retrieving all the courses where the user is enroled in'),
                    '0 or more course ids',
                    VALUE_DEFAULT, array()
                ),
                'capabilities'  => new external_multiple_structure(
                    new external_value(PARAM_CAPABILITY, 'capability'),
                    'list of capabilities used to filter courses',
                    VALUE_DEFAULT, array()
                ),
                'includenotenrolledcourses' => new external_value(PARAM_BOOL, 'whether to return courses that the user can see
                                                                    even if is not enroled in. This requires the parameter courseids
                                                                    to not be empty.', VALUE_DEFAULT, false)
            )
        );
    }

    /**
     * Returns an array of courses the user is enrolled, and for each course all of the evalcodeframeworks that the user can
     * view within that course.
     *
     * @param array $courseids An optional array of course ids. If provided only evalcodeframeworks within the given course
     * will be returned. If the user is not enrolled in or can't view a given course a warning will be generated and returned.
     * @param array $capabilities An array of additional capability checks you wish to be made on the course context.
     * @param bool $includenotenrolledcourses Wheter to return courses that the user can see even if is not enroled in.
     * This requires the parameter $courseids to not be empty.
     * @return An array of courses and warnings.
     * @since  Moodle 2.4
     */
    public static function get_evalcodeframeworks($courseids = array(), $capabilities = array(), $includenotenrolledcourses = false) {
        global $USER, $DB, $CFG;

        $params = self::validate_parameters(
            self::get_evalcodeframeworks_parameters(),
            array(
                'courseids' => $courseids,
                'capabilities' => $capabilities,
                'includenotenrolledcourses' => $includenotenrolledcourses
            )
        );

        $warnings = array();
        $courses = array();
        $fields = 'sortorder,shortname,fullname,timemodified';

        // If the courseids list is empty, we return only the courses where the user is enrolled in.
        if (empty($params['courseids'])) {
            $courses = enrol_get_users_courses($USER->id, true, $fields);
            $courseids = array_keys($courses);
        } else if ($includenotenrolledcourses) {
            // In this case, we don't have to check here for enrolmnents. Maybe the user can see the course even if is not enrolled.
            $courseids = $params['courseids'];
        } else {
            // We need to check for enrolments.
            $mycourses = enrol_get_users_courses($USER->id, true, $fields);
            $mycourseids = array_keys($mycourses);

            foreach ($params['courseids'] as $courseid) {
                if (!in_array($courseid, $mycourseids)) {
                    unset($courses[$courseid]);
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $courseid,
                        'warningcode' => '2',
                        'message' => 'User is not enrolled or does not have requested capability'
                    );
                } else {
                    $courses[$courseid] = $mycourses[$courseid];
                }
            }
            $courseids = array_keys($courses);
        }

        foreach ($courseids as $cid) {

            try {
                $context = context_course::instance($cid);
                self::validate_context($context);

                // Check if this course was already loaded (by enrol_get_users_courses).
                if (!isset($courses[$cid])) {
                    $courses[$cid] = get_course($cid);
                }
                $courses[$cid]->contextid = $context->id;
            } catch (Exception $e) {
                unset($courses[$cid]);
                $warnings[] = array(
                    'item' => 'course',
                    'itemid' => $cid,
                    'warningcode' => '1',
                    'message' => 'No access rights in course context '.$e->getMessage()
                );
                continue;
            }
            if (count($params['capabilities']) > 0 && !has_all_capabilities($params['capabilities'], $context)) {
                unset($courses[$cid]);
            }
        }
        $extrafields='m.id as evalcodeframeworkid, ' .
                     'm.course, ' .
                     'm.nosubmissions, ' .
                     'm.submissiondrafts, ' .
                     'm.sendnotifications, '.
                     'm.sendlatenotifications, ' .
                     'm.sendstudentnotifications, ' .
                     'm.duedate, ' .
                     'm.allowsubmissionsfromdate, '.
                     'm.grade, ' .
                     'm.timemodified, '.
                     'm.completionsubmit, ' .
                     'm.cutoffdate, ' .
                     'm.teamsubmission, ' .
                     'm.requireallteammemberssubmit, '.
                     'm.teamsubmissiongroupingid, ' .
                     'm.blindmarking, ' .
                     'm.revealidentities, ' .
                     'm.attemptreopenmethod, '.
                     'm.maxattempts, ' .
                     'm.markingworkflow, ' .
                     'm.markingallocation, ' .
                     'm.requiresubmissionstatement, '.
                     'm.intro, '.
                     'm.introformat';
        $coursearray = array();
        foreach ($courses as $id => $course) {
            $evalcodeframeworkarray = array();
            // Get a list of evalcodeframeworks for the course.
            if ($modules = get_coursemodules_in_course('evalcode', $courses[$id]->id, $extrafields)) {
                foreach ($modules as $module) {
                    $context = context_module::instance($module->id);
                    try {
                        self::validate_context($context);
                        require_capability('mod/evalcode:view', $context);
                    } catch (Exception $e) {
                        $warnings[] = array(
                            'item' => 'module',
                            'itemid' => $module->id,
                            'warningcode' => '1',
                            'message' => 'No access rights in module context'
                        );
                        continue;
                    }
                    $configrecords = $DB->get_recordset('evalcode_plugin_config', array('evalcodeframework' => $module->evalcodeframeworkid));
                    $configarray = array();
                    foreach ($configrecords as $configrecord) {
                        $configarray[] = array(
                            'id' => $configrecord->id,
                            'evalcodeframework' => $configrecord->evalcodeframework,
                            'plugin' => $configrecord->plugin,
                            'subtype' => $configrecord->subtype,
                            'name' => $configrecord->name,
                            'value' => $configrecord->value
                        );
                    }
                    $configrecords->close();

                    $evalcodeframework = array(
                        'id' => $module->evalcodeframeworkid,
                        'cmid' => $module->id,
                        'course' => $module->course,
                        'name' => $module->name,
                        'nosubmissions' => $module->nosubmissions,
                        'submissiondrafts' => $module->submissiondrafts,
                        'sendnotifications' => $module->sendnotifications,
                        'sendlatenotifications' => $module->sendlatenotifications,
                        'sendstudentnotifications' => $module->sendstudentnotifications,
                        'duedate' => $module->duedate,
                        'allowsubmissionsfromdate' => $module->allowsubmissionsfromdate,
                        'grade' => $module->grade,
                        'timemodified' => $module->timemodified,
                        'completionsubmit' => $module->completionsubmit,
                        'cutoffdate' => $module->cutoffdate,
                        'teamsubmission' => $module->teamsubmission,
                        'requireallteammemberssubmit' => $module->requireallteammemberssubmit,
                        'teamsubmissiongroupingid' => $module->teamsubmissiongroupingid,
                        'blindmarking' => $module->blindmarking,
                        'revealidentities' => $module->revealidentities,
                        'attemptreopenmethod' => $module->attemptreopenmethod,
                        'maxattempts' => $module->maxattempts,
                        'markingworkflow' => $module->markingworkflow,
                        'markingallocation' => $module->markingallocation,
                        'requiresubmissionstatement' => $module->requiresubmissionstatement,
                        'configs' => $configarray
                    );

                    // Return or not intro and file attachments depending on the plugin settings.
                    $evalcode = new evalcode($context, null, null);

                    if ($evalcode->show_intro()) {

                        list($evalcodeframework['intro'], $evalcodeframework['introformat']) = external_format_text($module->intro,
                            $module->introformat, $context->id, 'mod_evalcode', 'intro', null);

                        $fs = get_file_storage();
                        if ($files = $fs->get_area_files($context->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_FILEAREA,
                                                            0, 'timemodified', false)) {

                            $evalcodeframework['introattachments'] = array();
                            foreach ($files as $file) {
                                $filename = $file->get_filename();
                                $evalcodeframework['introattachments'][] = array(
                                    'filename' => $filename,
                                    'mimetype' => $file->get_mimetype(),
                                    'fileurl'  => moodle_url::make_webservice_pluginfile_url(
                                        $context->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_FILEAREA, 0, '/', $filename)->out(false)
                                );
                            }
                        }
                        /////////////////////////////////////////////////////////////////////
                        $evalcodeframeworkarray[] = $evalcodeframework;
                       if ($files = $fs->get_area_files($context->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_JUNIT,
                                                            0, 'timemodified', false)) {

                            $evalcodeframework['introattachmentsjunit'] = array();
                            foreach ($files as $file) {
                                $filename = $file->get_filename();
                                $evalcodeframework['introattachmentsjunit'][] = array(
                                    'filename' => $filename,
                                    'mimetype' => $file->get_mimetype(),
                                    'fileurl'  => moodle_url::make_webservice_pluginfile_url(
                                        $context->id, 'mod_evalcode', EVALCODE_INTROATTACHMENT_JUNIT, 0, '/', $filename)->out(false)
                                );
                            }
                        }
                        $evalcodeframeworkarray[] = $evalcodeframework;
						/////////////////////////////////////////////////////////////////////
                       if ($files = $fs->get_area_files($context->id, 'mod_evalcode', EVALCODE_PLAGIARISM_FILEAREA,
                                                            0, 'timemodified', false)) {

                            $evalcodeframework['plagiarismteacherfiles'] = array();
                            foreach ($files as $file) {
                                $filename = $file->get_filename();
                                $evalcodeframework['plagiarismteacherfiles'][] = array(
                                    'filename' => $filename,
                                    'mimetype' => $file->get_mimetype(),
                                    'fileurl'  => moodle_url::make_webservice_pluginfile_url(
                                        $context->id, 'mod_evalcode', EVALCODE_PLAGIARISM_FILEAREA, 0, '/', $filename)->out(false)
                                );
                            }
                        }
                        $evalcodeframeworkarray[] = $evalcodeframework;
                    }


                }
            }
            $coursearray[]= array(
                'id' => $courses[$id]->id,
                'fullname' => external_format_string($courses[$id]->fullname, $course->contextid),
                'shortname' => external_format_string($courses[$id]->shortname, $course->contextid),
                'timemodified' => $courses[$id]->timemodified,
                'evalcodeframeworks' => $evalcodeframeworkarray
            );
        }

        $result = array(
            'courses' => $coursearray,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Creates an evalcodeframework external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function get_evalcodeframeworks_evalcodeframework_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'evalcodeframework id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'course' => new external_value(PARAM_INT, 'course id'),
                'name' => new external_value(PARAM_TEXT, 'evalcodeframework name'),
                'nosubmissions' => new external_value(PARAM_INT, 'no submissions'),
                'submissiondrafts' => new external_value(PARAM_INT, 'submissions drafts'),
                'sendnotifications' => new external_value(PARAM_INT, 'send notifications'),
                'sendlatenotifications' => new external_value(PARAM_INT, 'send notifications'),
                'sendstudentnotifications' => new external_value(PARAM_INT, 'send student notifications (default)'),
                'duedate' => new external_value(PARAM_INT, 'evalcodeframework due date'),
                'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'allow submissions from date'),
                'grade' => new external_value(PARAM_INT, 'grade type'),
                'timemodified' => new external_value(PARAM_INT, 'last time evalcodeframework was modified'),
                'completionsubmit' => new external_value(PARAM_INT, 'if enabled, set activity as complete following submission'),
                'cutoffdate' => new external_value(PARAM_INT, 'date after which submission is not accepted without an extension'),
                'teamsubmission' => new external_value(PARAM_INT, 'if enabled, students submit as a team'),
                'requireallteammemberssubmit' => new external_value(PARAM_INT, 'if enabled, all team members must submit'),
                'teamsubmissiongroupingid' => new external_value(PARAM_INT, 'the grouping id for the team submission groups'),
                'blindmarking' => new external_value(PARAM_INT, 'if enabled, hide identities until reveal identities actioned'),
                'revealidentities' => new external_value(PARAM_INT, 'show identities for a blind marking evalcodeframework'),
                'attemptreopenmethod' => new external_value(PARAM_TEXT, 'method used to control opening new attempts'),
                'maxattempts' => new external_value(PARAM_INT, 'maximum number of attempts allowed'),
                'markingworkflow' => new external_value(PARAM_INT, 'enable marking workflow'),
                'markingallocation' => new external_value(PARAM_INT, 'enable marking allocation'),
                'requiresubmissionstatement' => new external_value(PARAM_INT, 'student must accept submission statement'),
                'configs' => new external_multiple_structure(self::get_evalcodeframeworks_config_structure(), 'configuration settings'),
                'intro' => new external_value(PARAM_RAW,
                    'evalcodeframework intro, not allways returned because it deppends on the activity configuration', VALUE_OPTIONAL),
                'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                'introattachments' => new external_multiple_structure(
                    new external_single_structure(
                        array (
                            'filename' => new external_value(PARAM_FILE, 'file name'),
                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                        )
                    ), 'intro attachments files', VALUE_OPTIONAL
                )
            ), 'evalcodeframework information object');
    }

    /**
     * Creates an evalcode_plugin_config external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function get_evalcodeframeworks_config_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'evalcode_plugin_config id'),
                'evalcodeframework' => new external_value(PARAM_INT, 'evalcodeframework id'),
                'plugin' => new external_value(PARAM_TEXT, 'plugin'),
                'subtype' => new external_value(PARAM_TEXT, 'subtype'),
                'name' => new external_value(PARAM_TEXT, 'name'),
                'value' => new external_value(PARAM_TEXT, 'value')
            ), 'evalcodeframework configuration object'
        );
    }

    /**
     * Creates a course external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function get_evalcodeframeworks_course_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'course id'),
                'fullname' => new external_value(PARAM_TEXT, 'course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'course short name'),
                'timemodified' => new external_value(PARAM_INT, 'last time modified'),
                'evalcodeframeworks' => new external_multiple_structure(self::get_evalcodeframeworks_evalcodeframework_structure(), 'evalcodeframework info')
              ), 'course information object'
        );
    }

    /**
     * Describes the return value for get_evalcodeframeworks
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    public static function get_evalcodeframeworks_returns() {
        return new external_single_structure(
            array(
                'courses' => new external_multiple_structure(self::get_evalcodeframeworks_course_structure(), 'list of courses'),
                'warnings'  => new external_warnings('item can be \'course\' (errorcode 1 or 2) or \'module\' (errorcode 1)',
                    'When item is a course then itemid is a course id. When the item is a module then itemid is a module id',
                    'errorcode can be 1 (no access rights) or 2 (not enrolled or no permissions)')
            )
        );
    }

    /**
     * Return information (files and text fields) for the given plugins in the evalcodeframework.
     *
     * @param  evalcode $evalcode the evalcodeframework object
     * @param  array $evalcodeplugins array of evalcodeframework plugins (submission or feedback)
     * @param  stdClass $item the item object (submission or grade)
     * @return array an array containing the plugins returned information
     */
    private static function get_plugins_data($evalcode, $evalcodeplugins, $item) {
        global $CFG;

        $plugins = array();
        $fs = get_file_storage();

        foreach ($evalcodeplugins as $evalcodeplugin) {

            if (!$evalcodeplugin->is_enabled() or !$evalcodeplugin->is_visible()) {
                continue;
            }

            $plugin = array(
                'name' => $evalcodeplugin->get_name(),
                'type' => $evalcodeplugin->get_type()
            );
            // Subtype is 'evalsubmission', type is currently 'file' or 'onlinetext'.
            $component = $evalcodeplugin->get_subtype().'_'.$evalcodeplugin->get_type();

            $fileareas = $evalcodeplugin->get_file_areas();
            foreach ($fileareas as $filearea => $name) {
                $fileareainfo = array('area' => $filearea);
                $files = $fs->get_area_files(
                    $evalcode->get_context()->id,
                    $component,
                    $filearea,
                    $item->id,
                    "timemodified",
                    false
                );
                foreach ($files as $file) {
                    $filepath = $file->get_filepath().$file->get_filename();
                    $fileurl = file_encode_url($CFG->wwwroot . '/webservice/pluginfile.php', '/' . $evalcode->get_context()->id .
                        '/' . $component. '/'. $filearea . '/' . $item->id . $filepath);
                    $fileinfo = array(
                        'filepath' => $filepath,
                        'fileurl' => $fileurl
                        );
                    $fileareainfo['files'][] = $fileinfo;
                }
                $plugin['fileareas'][] = $fileareainfo;
            }

            $editorfields = $evalcodeplugin->get_editor_fields();
            foreach ($editorfields as $name => $description) {
                $editorfieldinfo = array(
                    'name' => $name,
                    'description' => $description,
                    'text' => $evalcodeplugin->get_editor_text($name, $item->id),
                    'format' => $evalcodeplugin->get_editor_format($name, $item->id)
                );

                // Now format the text.
                foreach ($fileareas as $filearea => $name) {
                    list($editorfieldinfo['text'], $editorfieldinfo['format']) = external_format_text(
                        $editorfieldinfo['text'], $editorfieldinfo['format'], $evalcode->get_context()->id,
                        $component, $filearea, $item->id);
                }

                $plugin['editorfields'][] = $editorfieldinfo;
            }
            $plugins[] = $plugin;
        }
        return $plugins;
    }

    /**
     * Describes the parameters for get_submissions
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_submissions_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'evalcodeframework id'),
                    '1 or more evalcodeframework ids',
                    VALUE_REQUIRED),
                'status' => new external_value(PARAM_ALPHA, 'status', VALUE_DEFAULT, ''),
                'since' => new external_value(PARAM_INT, 'submitted since', VALUE_DEFAULT, 0),
                'before' => new external_value(PARAM_INT, 'submitted before', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns submissions for the requested evalcodeframework ids
     *
     * @param int[] $evalcodeframeworkids
     * @param string $status only return submissions with this status
     * @param int $since only return submissions with timemodified >= since
     * @param int $before only return submissions with timemodified <= before
     * @return array of submissions for each requested evalcodeframework
     * @since Moodle 2.5
     */
    public static function get_submissions($evalcodeframeworkids, $status = '', $since = 0, $before = 0) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::get_submissions_parameters(),
                        array('evalcodeframeworkids' => $evalcodeframeworkids,
                              'status' => $status,
                              'since' => $since,
                              'before' => $before));

        $warnings = array();
        $evalcodeframeworks = array();

        // Check the user is allowed to get the submissions for the evalcodeframeworks requested.
        $placeholders = array();
        list($inorequalsql, $placeholders) = $DB->get_in_or_equal($params['evalcodeframeworkids'], SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module ".
               "WHERE md.name = :modname AND cm.instance ".$inorequalsql;
        $placeholders['modname'] = 'evalcode';
        $cms = $DB->get_records_sql($sql, $placeholders);
        $evalcodes = array();
        foreach ($cms as $cm) {
            try {
                $context = context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/evalcode:grade', $context);
                $evalcode = new evalcode($context, null, null);
                $evalcodes[] = $evalcode;
            } catch (Exception $e) {
                $warnings[] = array(
                    'item' => 'evalcodeframework',
                    'itemid' => $cm->instance,
                    'warningcode' => '1',
                    'message' => 'No access rights in module context'
                );
            }
        }

        foreach ($evalcodes as $evalcode) {
            $submissions = array();
            $placeholders = array('evalcodeid1' => $evalcode->get_instance()->id,
                                  'evalcodeid2' => $evalcode->get_instance()->id);

            $submissionmaxattempt = 'SELECT mxs.userid, MAX(mxs.attemptnumber) AS maxattempt
                                     FROM {eval_submission} mxs
                                     WHERE mxs.evalcodeframework = :evalcodeid1 GROUP BY mxs.userid';

            $sql = "SELECT mas.id, mas.evalcodeframework,mas.userid,".
                   "mas.timecreated,mas.timemodified,mas.status,mas.groupid,mas.attemptnumber ".
                   "FROM {eval_submission} mas ".
                   "JOIN ( " . $submissionmaxattempt . " ) smx ON mas.userid = smx.userid ".
                   "WHERE mas.evalcodeframework = :evalcodeid2 AND mas.attemptnumber = smx.maxattempt";

            if (!empty($params['status'])) {
                $placeholders['status'] = $params['status'];
                $sql = $sql." AND mas.status = :status";
            }
            if (!empty($params['before'])) {
                $placeholders['since'] = $params['since'];
                $placeholders['before'] = $params['before'];
                $sql = $sql." AND mas.timemodified BETWEEN :since AND :before";
            } else {
                $placeholders['since'] = $params['since'];
                $sql = $sql." AND mas.timemodified >= :since";
            }

            $submissionrecords = $DB->get_records_sql($sql, $placeholders);

            if (!empty($submissionrecords)) {
                $submissionplugins = $evalcode->get_submission_plugins();
                foreach ($submissionrecords as $submissionrecord) {
                    $submission = array(
                        'id' => $submissionrecord->id,
                        'userid' => $submissionrecord->userid,
                        'timecreated' => $submissionrecord->timecreated,
                        'timemodified' => $submissionrecord->timemodified,
                        'status' => $submissionrecord->status,
                        'attemptnumber' => $submissionrecord->attemptnumber,
                        'groupid' => $submissionrecord->groupid,
                        'plugins' => self::get_plugins_data($evalcode, $submissionplugins, $submissionrecord)
                    );
                    $submissions[] = $submission;
                }
            } else {
                $warnings[] = array(
                    'item' => 'module',
                    'itemid' => $evalcode->get_instance()->id,
                    'warningcode' => '3',
                    'message' => 'No submissions found'
                );
            }

            $evalcodeframeworks[] = array(
                'evalcodeframeworkid' => $evalcode->get_instance()->id,
                'submissions' => $submissions
            );

        }

        $result = array(
            'evalcodeframeworks' => $evalcodeframeworks,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Creates an evalcodeframework plugin structure.
     *
     * @return external_single_structure the plugin structure
     */
    private static function get_plugin_structure() {
        return new external_single_structure(
            array(
                'type' => new external_value(PARAM_TEXT, 'submission plugin type'),
                'name' => new external_value(PARAM_TEXT, 'submission plugin name'),
                'fileareas' => new external_multiple_structure(
                    new external_single_structure(
                        array (
                            'area' => new external_value (PARAM_TEXT, 'file area'),
                            'files' => new external_multiple_structure(
                                new external_single_structure(
                                    array (
                                        'filepath' => new external_value (PARAM_TEXT, 'file path'),
                                        'fileurl' => new external_value (PARAM_URL, 'file download url',
                                            VALUE_OPTIONAL)
                                    )
                                ), 'files', VALUE_OPTIONAL
                            )
                        )
                    ), 'fileareas', VALUE_OPTIONAL
                ),
                'editorfields' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_TEXT, 'field name'),
                            'description' => new external_value(PARAM_TEXT, 'field description'),
                            'text' => new external_value (PARAM_RAW, 'field value'),
                            'format' => new external_format_value ('text')
                        )
                    )
                    , 'editorfields', VALUE_OPTIONAL
                )
            )
        );
    }

    /**
     * Creates a submission structure.
     *
     * @return external_single_structure the submission structure
     */
    private static function get_submission_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'submission id'),
                'userid' => new external_value(PARAM_INT, 'student id'),
                'attemptnumber' => new external_value(PARAM_INT, 'attempt number'),
                'timecreated' => new external_value(PARAM_INT, 'submission creation time'),
                'timemodified' => new external_value(PARAM_INT, 'submission last modified time'),
                'status' => new external_value(PARAM_TEXT, 'submission status'),
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'evalcodeframework' => new external_value(PARAM_INT, 'evalcodeframework id', VALUE_OPTIONAL),
                'latest' => new external_value(PARAM_INT, 'latest attempt', VALUE_OPTIONAL),
                'plugins' => new external_multiple_structure(self::get_plugin_structure(), 'plugins', VALUE_OPTIONAL)
            ), 'submission info', $required
        );
    }

    /**
     * Creates an eval_submissions external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    private static function get_submissions_structure() {
        return new external_single_structure(
            array (
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'evalcodeframework id'),
                'submissions' => new external_multiple_structure(self::get_submission_structure())
            )
        );
    }

    /**
     * Describes the get_submissions return value
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function get_submissions_returns() {
        return new external_single_structure(
            array(
                'evalcodeframeworks' => new external_multiple_structure(self::get_submissions_structure(), 'evalcodeframework submissions'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for set_user_flags
     * @return external_function_parameters
     * @since  Moodle 2.6
     */
    public static function set_user_flags_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid'    => new external_value(PARAM_INT, 'evalcodeframework id'),
                'userflags' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'userid'           => new external_value(PARAM_INT, 'student id'),
                            'locked'           => new external_value(PARAM_INT, 'locked', VALUE_OPTIONAL),
                            'mailed'           => new external_value(PARAM_INT, 'mailed', VALUE_OPTIONAL),
                            'extensionduedate' => new external_value(PARAM_INT, 'extension due date', VALUE_OPTIONAL),
                            'workflowstate'    => new external_value(PARAM_TEXT, 'marking workflow state', VALUE_OPTIONAL),
                            'allocatedmarker'  => new external_value(PARAM_INT, 'allocated marker', VALUE_OPTIONAL)
                        )
                    )
                )
            )
        );
    }

    /**
     * Create or update user_flags records
     *
     * @param int $evalcodeframeworkid the evalcodeframework for which the userflags are created or updated
     * @param array $userflags  An array of userflags to create or update
     * @return array containing success or failure information for each record
     * @since Moodle 2.6
     */
    public static function set_user_flags($evalcodeframeworkid, $userflags = array()) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::set_user_flags_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid,
                                                  'userflags' => $userflags));

        // Load evalcodeframework if it exists and if the user has the capability.
        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/evalcode:grade', $context);
        $evalcode = new evalcode($context, null, null);

        $results = array();
        foreach ($params['userflags'] as $userflag) {
            $success = true;
            $result = array();

            $record = $evalcode->get_user_flags($userflag['userid'], false);
            if ($record) {
                if (isset($userflag['locked'])) {
                    $record->locked = $userflag['locked'];
                }
                if (isset($userflag['mailed'])) {
                    $record->mailed = $userflag['mailed'];
                }
                if (isset($userflag['extensionduedate'])) {
                    $record->extensionduedate = $userflag['extensionduedate'];
                }
                if (isset($userflag['workflowstate'])) {
                    $record->workflowstate = $userflag['workflowstate'];
                }
                if (isset($userflag['allocatedmarker'])) {
                    $record->allocatedmarker = $userflag['allocatedmarker'];
                }
                if ($evalcode->update_user_flags($record)) {
                    $result['id'] = $record->id;
                    $result['userid'] = $userflag['userid'];
                } else {
                    $result['id'] = $record->id;
                    $result['userid'] = $userflag['userid'];
                    $result['errormessage'] = 'Record created but values could not be set';
                }
            } else {
                $record = $evalcode->get_user_flags($userflag['userid'], true);
                $setfields = isset($userflag['locked'])
                             || isset($userflag['mailed'])
                             || isset($userflag['extensionduedate'])
                             || isset($userflag['workflowstate'])
                             || isset($userflag['allocatedmarker']);
                if ($record) {
                    if ($setfields) {
                        if (isset($userflag['locked'])) {
                            $record->locked = $userflag['locked'];
                        }
                        if (isset($userflag['mailed'])) {
                            $record->mailed = $userflag['mailed'];
                        }
                        if (isset($userflag['extensionduedate'])) {
                            $record->extensionduedate = $userflag['extensionduedate'];
                        }
                        if (isset($userflag['workflowstate'])) {
                            $record->workflowstate = $userflag['workflowstate'];
                        }
                        if (isset($userflag['allocatedmarker'])) {
                            $record->allocatedmarker = $userflag['allocatedmarker'];
                        }
                        if ($evalcode->update_user_flags($record)) {
                            $result['id'] = $record->id;
                            $result['userid'] = $userflag['userid'];
                        } else {
                            $result['id'] = $record->id;
                            $result['userid'] = $userflag['userid'];
                            $result['errormessage'] = 'Record created but values could not be set';
                        }
                    } else {
                        $result['id'] = $record->id;
                        $result['userid'] = $userflag['userid'];
                    }
                } else {
                    $result['id'] = -1;
                    $result['userid'] = $userflag['userid'];
                    $result['errormessage'] = 'Record could not be created';
                }
            }

            $results[] = $result;
        }
        return $results;
    }

    /**
     * Describes the set_user_flags return value
     * @return external_multiple_structure
     * @since  Moodle 2.6
     */
    public static function set_user_flags_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of record if successful, -1 for failure'),
                    'userid' => new external_value(PARAM_INT, 'userid of record'),
                    'errormessage' => new external_value(PARAM_TEXT, 'Failure error message', VALUE_OPTIONAL)
                )
            )
        );
    }

    /**
     * Describes the parameters for get_user_flags
     * @return external_function_parameters
     * @since  Moodle 2.6
     */
    public static function get_user_flags_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'evalcodeframework id'),
                    '1 or more evalcodeframework ids',
                    VALUE_REQUIRED)
            )
        );
    }

    /**
     * Returns user flag information from evalcode_user_flags for the requested evalcodeframework ids
     * @param int[] $evalcodeframeworkids
     * @return array of user flag records for each requested evalcodeframework
     * @since  Moodle 2.6
     */
    public static function get_user_flags($evalcodeframeworkids) {
        global $DB;
        $params = self::validate_parameters(self::get_user_flags_parameters(),
                        array('evalcodeframeworkids' => $evalcodeframeworkids));

        $evalcodeframeworks = array();
        $warnings = array();
        $requestedevalcodeframeworkids = $params['evalcodeframeworkids'];

        // Check the user is allowed to get the user flags for the evalcodeframeworks requested.
        $placeholders = array();
        list($sqlevalcodeframeworkids, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module ".
               "WHERE md.name = :modname AND cm.instance ".$sqlevalcodeframeworkids;
        $placeholders['modname'] = 'evalcode';
        $cms = $DB->get_records_sql($sql, $placeholders);
        foreach ($cms as $cm) {
            try {
                $context = context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/evalcode:grade', $context);
            } catch (Exception $e) {
                $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($cm->instance));
                $warning = array();
                $warning['item'] = 'evalcodeframework';
                $warning['itemid'] = $cm->instance;
                $warning['warningcode'] = '1';
                $warning['message'] = 'No access rights in module context';
                $warnings[] = $warning;
            }
        }

        // Create the query and populate an array of evalcode_user_flags records from the recordset results.
        if (count ($requestedevalcodeframeworkids) > 0) {
            $placeholders = array();
            list($inorequalsql, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);

            $sql = "SELECT auf.id,auf.evalcodeframework,auf.userid,auf.locked,auf.mailed,".
                   "auf.extensionduedate,auf.workflowstate,auf.allocatedmarker ".
                   "FROM {evalcode_user_flags} auf ".
                   "WHERE auf.evalcodeframework ".$inorequalsql.
                   " ORDER BY auf.evalcodeframework, auf.id";

            $rs = $DB->get_recordset_sql($sql, $placeholders);
            $currentevalcodeframeworkid = null;
            $evalcodeframework = null;
            foreach ($rs as $rd) {
                $userflag = array();
                $userflag['id'] = $rd->id;
                $userflag['userid'] = $rd->userid;
                $userflag['locked'] = $rd->locked;
                $userflag['mailed'] = $rd->mailed;
                $userflag['extensionduedate'] = $rd->extensionduedate;
                $userflag['workflowstate'] = $rd->workflowstate;
                $userflag['allocatedmarker'] = $rd->allocatedmarker;

                if (is_null($currentevalcodeframeworkid) || ($rd->evalcodeframework != $currentevalcodeframeworkid )) {
                    if (!is_null($evalcodeframework)) {
                        $evalcodeframeworks[] = $evalcodeframework;
                    }
                    $evalcodeframework = array();
                    $evalcodeframework['evalcodeframeworkid'] = $rd->evalcodeframework;
                    $evalcodeframework['userflags'] = array();
                    $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($rd->evalcodeframework));
                }
                $evalcodeframework['userflags'][] = $userflag;

                $currentevalcodeframeworkid = $rd->evalcodeframework;
            }
            if (!is_null($evalcodeframework)) {
                $evalcodeframeworks[] = $evalcodeframework;
            }
            $rs->close();

        }

        foreach ($requestedevalcodeframeworkids as $evalcodeframeworkid) {
            $warning = array();
            $warning['item'] = 'evalcodeframework';
            $warning['itemid'] = $evalcodeframeworkid;
            $warning['warningcode'] = '3';
            $warning['message'] = 'No user flags found';
            $warnings[] = $warning;
        }

        $result = array();
        $result['evalcodeframeworks'] = $evalcodeframeworks;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates an evalcode_user_flags external_single_structure
     * @return external_single_structure
     * @since  Moodle 2.6
     */
    private static function evalcode_user_flags() {
        return new external_single_structure(
            array (
                'evalcodeframeworkid'    => new external_value(PARAM_INT, 'evalcodeframework id'),
                'userflags'   => new external_multiple_structure(new external_single_structure(
                        array(
                            'id'               => new external_value(PARAM_INT, 'user flag id'),
                            'userid'           => new external_value(PARAM_INT, 'student id'),
                            'locked'           => new external_value(PARAM_INT, 'locked'),
                            'mailed'           => new external_value(PARAM_INT, 'mailed'),
                            'extensionduedate' => new external_value(PARAM_INT, 'extension due date'),
                            'workflowstate'    => new external_value(PARAM_TEXT, 'marking workflow state', VALUE_OPTIONAL),
                            'allocatedmarker'  => new external_value(PARAM_INT, 'allocated marker')
                        )
                    )
                )
            )
        );
    }

    /**
     * Describes the get_user_flags return value
     * @return external_single_structure
     * @since  Moodle 2.6
     */
    public static function get_user_flags_returns() {
        return new external_single_structure(
            array(
                'evalcodeframeworks' => new external_multiple_structure(self::evalcode_user_flags(), 'list of evalcode user flag information'),
                'warnings'      => new external_warnings('item is always \'evalcodeframework\'',
                    'when errorcode is 3 then itemid is an evalcodeframework id. When errorcode is 1, itemid is a course module id',
                    'errorcode can be 3 (no user flags found) or 1 (no permission to get user flags)')
            )
        );
    }

    /**
     * Describes the parameters for get_user_mappings
     * @return external_function_parameters
     * @since  Moodle 2.6
     */
    public static function get_user_mappings_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'evalcodeframework id'),
                    '1 or more evalcodeframework ids',
                    VALUE_REQUIRED)
            )
        );
    }

    /**
     * Returns user mapping information from evalcode_user_mapping for the requested evalcodeframework ids
     * @param int[] $evalcodeframeworkids
     * @return array of user mapping records for each requested evalcodeframework
     * @since  Moodle 2.6
     */
    public static function get_user_mappings($evalcodeframeworkids) {
        global $DB;
        $params = self::validate_parameters(self::get_user_mappings_parameters(),
                        array('evalcodeframeworkids' => $evalcodeframeworkids));

        $evalcodeframeworks = array();
        $warnings = array();
        $requestedevalcodeframeworkids = $params['evalcodeframeworkids'];

        // Check the user is allowed to get the mappings for the evalcodeframeworks requested.
        $placeholders = array();
        list($sqlevalcodeframeworkids, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module ".
               "WHERE md.name = :modname AND cm.instance ".$sqlevalcodeframeworkids;
        $placeholders['modname'] = 'evalcode';
        $cms = $DB->get_records_sql($sql, $placeholders);
        foreach ($cms as $cm) {
            try {
                $context = context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/evalcode:revealidentities', $context);
            } catch (Exception $e) {
                $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($cm->instance));
                $warning = array();
                $warning['item'] = 'evalcodeframework';
                $warning['itemid'] = $cm->instance;
                $warning['warningcode'] = '1';
                $warning['message'] = 'No access rights in module context';
                $warnings[] = $warning;
            }
        }

        // Create the query and populate an array of evalcode_user_mapping records from the recordset results.
        if (count ($requestedevalcodeframeworkids) > 0) {
            $placeholders = array();
            list($inorequalsql, $placeholders) = $DB->get_in_or_equal($requestedevalcodeframeworkids, SQL_PARAMS_NAMED);

            $sql = "SELECT aum.id,aum.evalcodeframework,aum.userid ".
                   "FROM {evalcode_user_mapping} aum ".
                   "WHERE aum.evalcodeframework ".$inorequalsql.
                   " ORDER BY aum.evalcodeframework, aum.id";

            $rs = $DB->get_recordset_sql($sql, $placeholders);
            $currentevalcodeframeworkid = null;
            $evalcodeframework = null;
            foreach ($rs as $rd) {
                $mapping = array();
                $mapping['id'] = $rd->id;
                $mapping['userid'] = $rd->userid;

                if (is_null($currentevalcodeframeworkid) || ($rd->evalcodeframework != $currentevalcodeframeworkid )) {
                    if (!is_null($evalcodeframework)) {
                        $evalcodeframeworks[] = $evalcodeframework;
                    }
                    $evalcodeframework = array();
                    $evalcodeframework['evalcodeframeworkid'] = $rd->evalcodeframework;
                    $evalcodeframework['mappings'] = array();
                    $requestedevalcodeframeworkids = array_diff($requestedevalcodeframeworkids, array($rd->evalcodeframework));
                }
                $evalcodeframework['mappings'][] = $mapping;

                $currentevalcodeframeworkid = $rd->evalcodeframework;
            }
            if (!is_null($evalcodeframework)) {
                $evalcodeframeworks[] = $evalcodeframework;
            }
            $rs->close();

        }

        foreach ($requestedevalcodeframeworkids as $evalcodeframeworkid) {
            $warning = array();
            $warning['item'] = 'evalcodeframework';
            $warning['itemid'] = $evalcodeframeworkid;
            $warning['warningcode'] = '3';
            $warning['message'] = 'No mappings found';
            $warnings[] = $warning;
        }

        $result = array();
        $result['evalcodeframeworks'] = $evalcodeframeworks;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates an evalcode_user_mappings external_single_structure
     * @return external_single_structure
     * @since  Moodle 2.6
     */
    private static function evalcode_user_mappings() {
        return new external_single_structure(
            array (
                'evalcodeframeworkid'    => new external_value(PARAM_INT, 'evalcodeframework id'),
                'mappings'   => new external_multiple_structure(new external_single_structure(
                        array(
                            'id'     => new external_value(PARAM_INT, 'user mapping id'),
                            'userid' => new external_value(PARAM_INT, 'student id')
                        )
                    )
                )
            )
        );
    }

    /**
     * Describes the get_user_mappings return value
     * @return external_single_structure
     * @since  Moodle 2.6
     */
    public static function get_user_mappings_returns() {
        return new external_single_structure(
            array(
                'evalcodeframeworks' => new external_multiple_structure(self::evalcode_user_mappings(), 'list of evalcode user mapping data'),
                'warnings'      => new external_warnings('item is always \'evalcodeframework\'',
                    'when errorcode is 3 then itemid is an evalcodeframework id. When errorcode is 1, itemid is a course module id',
                    'errorcode can be 3 (no user mappings found) or 1 (no permission to get user mappings)')
            )
        );
    }

    /**
     * Describes the parameters for lock_submissions
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function lock_submissions_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user id'),
                    '1 or more user ids',
                    VALUE_REQUIRED),
            )
        );
    }

    /**
     * Locks (prevent updates to) submissions in this evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param array $userids Array of user ids to lock
     * @return array of warnings for each submission that could not be locked.
     * @since Moodle 2.6
     */
    public static function lock_submissions($evalcodeframeworkid, $userids) {
        global $CFG;

        $params = self::validate_parameters(self::lock_submissions_parameters(),
                        array('evalcodeframeworkid' => $evalcodeframeworkid,
                              'userids' => $userids));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        foreach ($params['userids'] as $userid) {
            if (!$evalcodeframework->lock_submission($userid)) {
                $detail = 'User id: ' . $userid . ', EvalCode id: ' . $params['evalcodeframeworkid'];
                $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                     'couldnotlock',
                                                     $detail);
            }
        }

        return $warnings;
    }

    /**
     * Describes the return value for lock_submissions
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function lock_submissions_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for revert_submissions_to_draft
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function revert_submissions_to_draft_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user id'),
                    '1 or more user ids',
                    VALUE_REQUIRED),
            )
        );
    }

    /**
     * Reverts a list of user submissions to draft for a single evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param array $userids Array of user ids to revert
     * @return array of warnings for each submission that could not be reverted.
     * @since Moodle 2.6
     */
    public static function revert_submissions_to_draft($evalcodeframeworkid, $userids) {
        global $CFG;

        $params = self::validate_parameters(self::revert_submissions_to_draft_parameters(),
                        array('evalcodeframeworkid' => $evalcodeframeworkid,
                              'userids' => $userids));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        foreach ($params['userids'] as $userid) {
            if (!$evalcodeframework->revert_to_draft($userid)) {
                $detail = 'User id: ' . $userid . ', EvalCode id: ' . $params['evalcodeframeworkid'];
                $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                     'couldnotrevert',
                                                     $detail);
            }
        }

        return $warnings;
    }

    /**
     * Describes the return value for revert_submissions_to_draft
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function revert_submissions_to_draft_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for unlock_submissions
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function unlock_submissions_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user id'),
                    '1 or more user ids',
                    VALUE_REQUIRED),
            )
        );
    }

    /**
     * Locks (prevent updates to) submissions in this evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param array $userids Array of user ids to lock
     * @return array of warnings for each submission that could not be locked.
     * @since Moodle 2.6
     */
    public static function unlock_submissions($evalcodeframeworkid, $userids) {
        global $CFG;

        $params = self::validate_parameters(self::unlock_submissions_parameters(),
                        array('evalcodeframeworkid' => $evalcodeframeworkid,
                              'userids' => $userids));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        foreach ($params['userids'] as $userid) {
            if (!$evalcodeframework->unlock_submission($userid)) {
                $detail = 'User id: ' . $userid . ', EvalCode id: ' . $params['evalcodeframeworkid'];
                $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                     'couldnotunlock',
                                                     $detail);
            }
        }

        return $warnings;
    }

    /**
     * Describes the return value for unlock_submissions
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function unlock_submissions_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for submit_grading_form webservice.
     * @return external_external_function_parameters
     * @since  Moodle 3.1
     */
    public static function submit_grading_form_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userid' => new external_value(PARAM_INT, 'The user id the submission belongs to'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the grading form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the logged in users evalcodeframework for grading.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param int $userid The id of the user the submission belongs to.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return array of warnings to indicate any errors.
     * @since Moodle 2.6
     */
    public static function submit_grading_form($evalcodeframeworkid, $userid, $jsonformdata) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/evalcode/locallib.php');
        require_once($CFG->dirroot . '/mod/evalcode/gradeform.php');

        $params = self::validate_parameters(self::submit_grading_form_parameters(),
                                            array(
                                                'evalcodeframeworkid' => $evalcodeframeworkid,
                                                'userid' => $userid,
                                                'jsonformdata' => $jsonformdata
                                            ));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();

        $options = array(
            'userid' => $params['userid'],
            'attemptnumber' => $data['attemptnumber'],
            'rownum' => 0,
            'gradingpanel' => true
        );

        $customdata = (object) $data;
        $formparams = array($evalcodeframework, $customdata, $options);

        // Data is injected into the form by the last param for the constructor.
        $mform = new mod_evalcode_grade_form(null, $formparams, 'post', '', null, true, $data);
        $validateddata = $mform->get_data();

        if ($validateddata) {
            $evalcodeframework->save_grade($params['userid'], $validateddata);
        } else {
            $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                 'couldnotsavegrade',
                                                 'Form validation failed.');
        }


        return $warnings;
    }

    /**
     * Describes the return for submit_grading_form
     * @return external_external_function_parameters
     * @since  Moodle 3.1
     */
    public static function submit_grading_form_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for submit_for_grading
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function submit_for_grading_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'acceptsubmissionstatement' => new external_value(PARAM_BOOL, 'Accept the evalcodeframework submission statement')
            )
        );
    }

    /**
     * Submit the logged in users evalcodeframework for grading.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @return array of warnings to indicate any errors.
     * @since Moodle 2.6
     */
    public static function submit_for_grading($evalcodeframeworkid, $acceptsubmissionstatement) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::submit_for_grading_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid,
                                                  'acceptsubmissionstatement' => $acceptsubmissionstatement));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        $data = new stdClass();
        $data->submissionstatement = $params['acceptsubmissionstatement'];
        $notices = array();

        if (!$evalcodeframework->submit_for_grading($data, $notices)) {
            $detail = 'User id: ' . $USER->id . ', EvalCode id: ' . $params['evalcodeframeworkid'] . ' Notices:' . implode(', ', $notices);
            $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                 'couldnotsubmitforgrading',
                                                 $detail);
        }

        return $warnings;
    }

    /**
     * Describes the return value for submit_for_grading
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function submit_for_grading_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for save_user_extensions
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function save_user_extensions_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user id'),
                    '1 or more user ids',
                    VALUE_REQUIRED),
                'dates' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'dates'),
                    '1 or more extension dates (timestamp)',
                    VALUE_REQUIRED),
            )
        );
    }

    /**
     * Grant extension dates to students for an evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param array $userids Array of user ids to grant extensions to
     * @param array $dates Array of extension dates
     * @return array of warnings for each extension date that could not be granted
     * @since Moodle 2.6
     */
    public static function save_user_extensions($evalcodeframeworkid, $userids, $dates) {
        global $CFG;

        $params = self::validate_parameters(self::save_user_extensions_parameters(),
                        array('evalcodeframeworkid' => $evalcodeframeworkid,
                              'userids' => $userids,
                              'dates' => $dates));

        if (count($params['userids']) != count($params['dates'])) {
            $detail = 'Length of userids and dates parameters differ.';
            $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                 'invalidparameters',
                                                 $detail);

            return $warnings;
        }

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        foreach ($params['userids'] as $idx => $userid) {
            $duedate = $params['dates'][$idx];
            if (!$evalcodeframework->save_user_extension($userid, $duedate)) {
                $detail = 'User id: ' . $userid . ', EvalCode id: ' . $params['evalcodeframeworkid'] . ', Extension date: ' . $duedate;
                $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                     'couldnotgrantextensions',
                                                     $detail);
            }
        }

        return $warnings;
    }

    /**
     * Describes the return value for save_user_extensions
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function save_user_extensions_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for reveal_identities
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function reveal_identities_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on')
            )
        );
    }

    /**
     * Reveal the identities of anonymous students to markers for a single evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @return array of warnings to indicate any errors.
     * @since Moodle 2.6
     */
    public static function reveal_identities($evalcodeframeworkid) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::reveal_identities_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $warnings = array();
        if (!$evalcodeframework->reveal_identities()) {
            $detail = 'User id: ' . $USER->id . ', EvalCode id: ' . $params['evalcodeframeworkid'];
            $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                 'couldnotrevealidentities',
                                                 $detail);
        }

        return $warnings;
    }

    /**
     * Describes the return value for reveal_identities
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function reveal_identities_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for save_submission
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function save_submission_parameters() {
        global $CFG;
        $instance = new evalcode(null, null, null);
        $pluginsubmissionparams = array();

        foreach ($instance->get_submission_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginparams = $plugin->get_external_parameters();
                if (!empty($pluginparams)) {
                    $pluginsubmissionparams = array_merge($pluginsubmissionparams, $pluginparams);
                }
            }
        }

        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'plugindata' => new external_single_structure(
                    $pluginsubmissionparams
                )
            )
        );
    }

    /**
     * Save a student submission for a single evalcodeframework
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param array $plugindata - The submitted data for plugins
     * @return array of warnings to indicate any errors
     * @since Moodle 2.6
     */
    public static function save_submission($evalcodeframeworkid, $plugindata) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::save_submission_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid,
                                                  'plugindata' => $plugindata));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $notices = array();

        if (!$evalcodeframework->submissions_open($USER->id)) {
            $notices[] = get_string('duedatereached', 'evalcode');
        } else {
            $submissiondata = (object)$params['plugindata'];
            $evalcodeframework->save_submission($submissiondata, $notices);
        }

        $warnings = array();
        foreach ($notices as $notice) {
            $warnings[] = self::generate_warning($params['evalcodeframeworkid'],
                                                 'couldnotsavesubmission',
                                                 $notice);
        }

        return $warnings;
    }

    /**
     * Describes the return value for save_submission
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function save_submission_returns() {
        return new external_warnings();
    }

    /**
     * Describes the parameters for save_grade
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function save_grade_parameters() {
        global $CFG;
        require_once("$CFG->dirroot/grade/grading/lib.php");
        $instance = new evalcode(null, null, null);
        $pluginfeedbackparams = array();

        foreach ($instance->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginparams = $plugin->get_external_parameters();
                if (!empty($pluginparams)) {
                    $pluginfeedbackparams = array_merge($pluginfeedbackparams, $pluginparams);
                }
            }
        }

        $advancedgradingdata = array();
        $methods = array_keys(grading_manager::available_methods(false));
        foreach ($methods as $method) {
            require_once($CFG->dirroot.'/grade/grading/form/'.$method.'/lib.php');
            $details  = call_user_func('gradingform_'.$method.'_controller::get_external_instance_filling_details');
            if (!empty($details)) {
                $items = array();
                foreach ($details as $key => $value) {
                    $value->required = VALUE_OPTIONAL;
                    unset($value->content->keys['id']);
                    $items[$key] = new external_multiple_structure (new external_single_structure(
                        array(
                            'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                            'fillings' => $value
                        )
                    ));
                }
                $advancedgradingdata[$method] = new external_single_structure($items, 'items', VALUE_OPTIONAL);
            }
        }

        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'userid' => new external_value(PARAM_INT, 'The student id to operate on'),
                'grade' => new external_value(PARAM_FLOAT, 'The new grade for this user. Ignored if advanced grading used'),
                'attemptnumber' => new external_value(PARAM_INT, 'The attempt number (-1 means latest attempt)'),
                'addattempt' => new external_value(PARAM_BOOL, 'Allow another attempt if the attempt reopen method is manual'),
                'workflowstate' => new external_value(PARAM_ALPHA, 'The next marking workflow state'),
                'applytoall' => new external_value(PARAM_BOOL, 'If true, this grade will be applied ' .
                                                               'to all members ' .
                                                               'of the group (for group evalcodeframeworks).'),
                'plugindata' => new external_single_structure($pluginfeedbackparams, 'plugin data', VALUE_DEFAULT, array()),
                'advancedgradingdata' => new external_single_structure($advancedgradingdata, 'advanced grading data',
                                                                       VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Save a student grade for a single evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param int $userid The id of the user
     * @param float $grade The grade (ignored if the evalcodeframework uses advanced grading)
     * @param int $attemptnumber The attempt number
     * @param bool $addattempt Allow another attempt
     * @param string $workflowstate New workflow state
     * @param bool $applytoall Apply the grade to all members of the group
     * @param array $plugindata Custom data used by plugins
     * @param array $advancedgradingdata Advanced grading data
     * @return null
     * @since Moodle 2.6
     */
    public static function save_grade($evalcodeframeworkid,
                                      $userid,
                                      $grade,
                                      $attemptnumber,
                                      $addattempt,
                                      $workflowstate,
                                      $applytoall,
                                      $plugindata = array(),
                                      $advancedgradingdata = array()) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::save_grade_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid,
                                                  'userid' => $userid,
                                                  'grade' => $grade,
                                                  'attemptnumber' => $attemptnumber,
                                                  'workflowstate' => $workflowstate,
                                                  'addattempt' => $addattempt,
                                                  'applytoall' => $applytoall,
                                                  'plugindata' => $plugindata,
                                                  'advancedgradingdata' => $advancedgradingdata));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $gradedata = (object)$params['plugindata'];

        $gradedata->addattempt = $params['addattempt'];
        $gradedata->attemptnumber = $params['attemptnumber'];
        $gradedata->workflowstate = $params['workflowstate'];
        $gradedata->applytoall = $params['applytoall'];
        $gradedata->grade = $params['grade'];

        if (!empty($params['advancedgradingdata'])) {
            $advancedgrading = array();
            $criteria = reset($params['advancedgradingdata']);
            foreach ($criteria as $key => $criterion) {
                $details = array();
                foreach ($criterion as $value) {
                    foreach ($value['fillings'] as $filling) {
                        $details[$value['criterionid']] = $filling;
                    }
                }
                $advancedgrading[$key] = $details;
            }
            $gradedata->advancedgrading = $advancedgrading;
        }

        $evalcodeframework->save_grade($params['userid'], $gradedata);

        return null;
    }

    /**
     * Describes the return value for save_grade
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function save_grade_returns() {
        return null;
    }

    /**
     * Describes the parameters for save_grades
     * @return external_external_function_parameters
     * @since  Moodle 2.7
     */
    public static function save_grades_parameters() {
        global $CFG;
        require_once("$CFG->dirroot/grade/grading/lib.php");
        $instance = new evalcode(null, null, null);
        $pluginfeedbackparams = array();

        foreach ($instance->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginparams = $plugin->get_external_parameters();
                if (!empty($pluginparams)) {
                    $pluginfeedbackparams = array_merge($pluginfeedbackparams, $pluginparams);
                }
            }
        }

        $advancedgradingdata = array();
        $methods = array_keys(grading_manager::available_methods(false));
        foreach ($methods as $method) {
            require_once($CFG->dirroot.'/grade/grading/form/'.$method.'/lib.php');
            $details  = call_user_func('gradingform_'.$method.'_controller::get_external_instance_filling_details');
            if (!empty($details)) {
                $items = array();
                foreach ($details as $key => $value) {
                    $value->required = VALUE_OPTIONAL;
                    unset($value->content->keys['id']);
                    $items[$key] = new external_multiple_structure (new external_single_structure(
                        array(
                            'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                            'fillings' => $value
                        )
                    ));
                }
                $advancedgradingdata[$method] = new external_single_structure($items, 'items', VALUE_OPTIONAL);
            }
        }

        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
                'applytoall' => new external_value(PARAM_BOOL, 'If true, this grade will be applied ' .
                                                               'to all members ' .
                                                               'of the group (for group evalcodeframeworks).'),
                'grades' => new external_multiple_structure(
                    new external_single_structure(
                        array (
                            'userid' => new external_value(PARAM_INT, 'The student id to operate on'),
                            'grade' => new external_value(PARAM_FLOAT, 'The new grade for this user. '.
                                                                       'Ignored if advanced grading used'),
                            'attemptnumber' => new external_value(PARAM_INT, 'The attempt number (-1 means latest attempt)'),
                            'addattempt' => new external_value(PARAM_BOOL, 'Allow another attempt if manual attempt reopen method'),
                            'workflowstate' => new external_value(PARAM_ALPHA, 'The next marking workflow state'),
                            'plugindata' => new external_single_structure($pluginfeedbackparams, 'plugin data',
                                                                          VALUE_DEFAULT, array()),
                            'advancedgradingdata' => new external_single_structure($advancedgradingdata, 'advanced grading data',
                                                                                   VALUE_DEFAULT, array())
                        )
                    )
                )
            )
        );
    }

    /**
     * Save multiple student grades for a single evalcodeframework.
     *
     * @param int $evalcodeframeworkid The id of the evalcodeframework
     * @param boolean $applytoall If set to true and this is a team evalcodeframework,
     * apply the grade to all members of the group
     * @param array $grades grade data for one or more students that includes
     *                  userid - The id of the student being graded
     *                  grade - The grade (ignored if the evalcodeframework uses advanced grading)
     *                  attemptnumber - The attempt number
     *                  addattempt - Allow another attempt
     *                  workflowstate - New workflow state
     *                  plugindata - Custom data used by plugins
     *                  advancedgradingdata - Optional Advanced grading data
     * @throws invalid_parameter_exception if multiple grades are supplied for
     * a team evalcodeframework that has $applytoall set to true
     * @return null
     * @since Moodle 2.7
     */
    public static function save_grades($evalcodeframeworkid, $applytoall = false, $grades) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::save_grades_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid,
                                                  'applytoall' => $applytoall,
                                                  'grades' => $grades));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $evalcodeframework = new evalcode($context, $cm, null);

        if ($evalcodeframework->get_instance()->teamsubmission && $params['applytoall']) {
            // Check that only 1 user per submission group is provided.
            $groupids = array();
            foreach ($params['grades'] as $gradeinfo) {
                $group = $evalcodeframework->get_submission_group($gradeinfo['userid']);
                if (in_array($group->id, $groupids)) {
                    throw new invalid_parameter_exception('Multiple grades for the same team have been supplied '
                                                          .' this is not permitted when the applytoall flag is set');
                } else {
                    $groupids[] = $group->id;
                }
            }
        }

        foreach ($params['grades'] as $gradeinfo) {
            $gradedata = (object)$gradeinfo['plugindata'];
            $gradedata->addattempt = $gradeinfo['addattempt'];
            $gradedata->attemptnumber = $gradeinfo['attemptnumber'];
            $gradedata->workflowstate = $gradeinfo['workflowstate'];
            $gradedata->applytoall = $params['applytoall'];
            $gradedata->grade = $gradeinfo['grade'];

            if (!empty($gradeinfo['advancedgradingdata'])) {
                $advancedgrading = array();
                $criteria = reset($gradeinfo['advancedgradingdata']);
                foreach ($criteria as $key => $criterion) {
                    $details = array();
                    foreach ($criterion as $value) {
                        foreach ($value['fillings'] as $filling) {
                            $details[$value['criterionid']] = $filling;
                        }
                    }
                    $advancedgrading[$key] = $details;
                }
                $gradedata->advancedgrading = $advancedgrading;
            }
            $evalcodeframework->save_grade($gradeinfo['userid'], $gradedata);
        }

        return null;
    }

    /**
     * Describes the return value for save_grades
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function save_grades_returns() {
        return null;
    }

    /**
     * Describes the parameters for copy_previous_attempt
     * @return external_function_parameters
     * @since  Moodle 2.6
     */
    public static function copy_previous_attempt_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeframeworkid' => new external_value(PARAM_INT, 'The evalcodeframework id to operate on'),
            )
        );
    }

    /**
     * Copy a students previous attempt to a new attempt.
     *
     * @param int $evalcodeframeworkid
     * @return array of warnings to indicate any errors.
     * @since Moodle 2.6
     */
    public static function copy_previous_attempt($evalcodeframeworkid) {

        $params = self::validate_parameters(self::copy_previous_attempt_parameters(),
                                            array('evalcodeframeworkid' => $evalcodeframeworkid));

        $cm = get_coursemodule_from_instance('evalcode', $params['evalcodeframeworkid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcodeframework = new evalcode($context, $cm, null);

        $notices = array();

        $evalcodeframework->copy_previous_attempt($notices);

        $warnings = array();
        foreach ($notices as $notice) {
            $warnings[] = self::generate_warning($evalcodeframeworkid,
                                                 'couldnotcopyprevioussubmission',
                                                 $notice);
        }

        return $warnings;
    }

    /**
     * Describes the return value for save_submission
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function copy_previous_attempt_returns() {
        return new external_warnings();
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_grading_table_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeid' => new external_value(PARAM_INT, 'evalcode instance id')
            )
        );
    }

    /**
     * Trigger the grading_table_viewed event.
     *
     * @param int $evalcodeid the evalcode instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_grading_table($evalcodeid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::view_grading_table_parameters(),
                                            array(
                                                'evalcodeid' => $evalcodeid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $evalcode = $DB->get_record('evalcode', array('id' => $params['evalcodeid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($evalcode, 'evalcode');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/evalcode:view', $context);

        $evalcode = new evalcode($context, null, null);
        $evalcode->require_view_grades();
        \mod_evalcode\event\grading_table_viewed::create_from_evalcode($evalcode)->trigger();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_grading_table_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for view_submission_status.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_submission_status_parameters() {
        return new external_function_parameters (
            array(
                'evalcodeid' => new external_value(PARAM_INT, 'evalcode instance id'),
            )
        );
    }

    /**
     * Trigger the submission status viewed event.
     *
     * @param int $evalcodeid evalcode instance id
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function view_submission_status($evalcodeid) {
        global $DB, $CFG;

        $warnings = array();
        $params = array(
            'evalcodeid' => $evalcodeid,
        );
        $params = self::validate_parameters(self::view_submission_status_parameters(), $params);

        // Request and permission validation.
        $evalcode = $DB->get_record('evalcode', array('id' => $params['evalcodeid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($evalcode, 'evalcode');

        $context = context_module::instance($cm->id);
        // Please, note that is not required to check mod/evalcode:view because is done by validate_context->require_login.
        self::validate_context($context);

        $evalcode = new evalcode($context, $cm, $course);
        \mod_evalcode\event\submission_status_viewed::create_from_evalcode($evalcode)->trigger();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_submission_status return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_submission_status_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_submission_status.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_submission_status_parameters() {
        return new external_function_parameters (
            array(
                'evalcodeid' => new external_value(PARAM_INT, 'evalcodeframework instance id'),
                'userid' => new external_value(PARAM_INT, 'user id (empty for current user)', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns information about an evalcodeframework submission status for a given user.
     *
     * @param int $evalcodeid evalcodeframework instance id
     * @param int $userid user id (empty for current user)
     * @return array of warnings and grading, status, feedback and previous attempts information
     * @since Moodle 3.1
     * @throws required_capability_exception
     */
    public static function get_submission_status($evalcodeid, $userid = 0) {
        global $USER, $DB;

        $warnings = array();

        $params = array(
            'evalcodeid' => $evalcodeid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_submission_status_parameters(), $params);

        // Request and permission validation.
        $evalcode = $DB->get_record('evalcode', array('id' => $params['evalcodeid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($evalcode, 'evalcode');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcode = new evalcode($context, $cm, $course);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }
        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        if (!$evalcode->can_view_submission($user->id)) {
            throw new required_capability_exception($context, 'mod/evalcode:viewgrades', 'nopermission', '');
        }

        $gradingsummary = $lastattempt = $feedback = $previousattempts = null;

        // Get the renderable since it contais all the info we need.
        if ($evalcode->can_view_grades()) {
            $gradingsummary = $evalcode->get_evalcode_grading_summary_renderable();
        }

        // Retrieve the rest of the renderable objects.
        if (has_capability('mod/evalcode:submit', $evalcode->get_context(), $user)) {
            $lastattempt = $evalcode->get_eval_submission_status_renderable($user, true);
        }

        $feedback = $evalcode->get_eval_feedback_status_renderable($user);

        $previousattempts = $evalcode->get_evalcode_attempt_history_renderable($user);

        // Now, build the result.
        $result = array();

        // First of all, grading summary, this is suitable for teachers/managers.
        if ($gradingsummary) {
            $result['gradingsummary'] = $gradingsummary;
        }

        // Did we submit anything?
        if ($lastattempt) {
            $submissionplugins = $evalcode->get_submission_plugins();

            if (empty($lastattempt->submission)) {
                unset($lastattempt->submission);
            } else {
                $lastattempt->submission->plugins = self::get_plugins_data($evalcode, $submissionplugins, $lastattempt->submission);
            }

            if (empty($lastattempt->teamsubmission)) {
                unset($lastattempt->teamsubmission);
            } else {
                $lastattempt->teamsubmission->plugins = self::get_plugins_data($evalcode, $submissionplugins,
                                                                                $lastattempt->teamsubmission);
            }

            // We need to change the type of some of the structures retrieved from the renderable.
            if (!empty($lastattempt->submissiongroup)) {
                $lastattempt->submissiongroup = $lastattempt->submissiongroup->id;
            } else {
                unset($lastattempt->submissiongroup);
            }

            if (!empty($lastattempt->usergroups)) {
                $lastattempt->usergroups = array_keys($lastattempt->usergroups);
            }
            // We cannot use array_keys here.
            if (!empty($lastattempt->submissiongroupmemberswhoneedtosubmit)) {
                $lastattempt->submissiongroupmemberswhoneedtosubmit = array_map(
                                                                            function($e){
                                                                                return $e->id;
                                                                            },
                                                                            $lastattempt->submissiongroupmemberswhoneedtosubmit);
            }

            $result['lastattempt'] = $lastattempt;
        }

        // The feedback for our latest submission.
        if ($feedback) {
            if ($feedback->grade) {
                $feedbackplugins = $evalcode->get_feedback_plugins();
                $feedback->plugins = self::get_plugins_data($evalcode, $feedbackplugins, $feedback->grade);
            } else {
                unset($feedback->plugins);
                unset($feedback->grade);
            }

            $result['feedback'] = $feedback;
        }

        // Retrieve only previous attempts.
        if ($previousattempts and count($previousattempts->submissions) > 1) {
            // Don't show the last one because it is the current submission.
            array_pop($previousattempts->submissions);

            // Show newest to oldest.
            $previousattempts->submissions = array_reverse($previousattempts->submissions);

            foreach ($previousattempts->submissions as $i => $submission) {
                $attempt = array();

                $grade = null;
                foreach ($previousattempts->grades as $onegrade) {
                    if ($onegrade->attemptnumber == $submission->attemptnumber) {
                        $grade = $onegrade;
                        break;
                    }
                }

                $attempt['attemptnumber'] = $submission->attemptnumber;

                if ($submission) {
                    $submission->plugins = self::get_plugins_data($evalcode, $previousattempts->submissionplugins, $submission);
                    $attempt['submission'] = $submission;
                }

                if ($grade) {
                    // From object to id.
                    $grade->grader = $grade->grader->id;
                    $feedbackplugins = self::get_plugins_data($evalcode, $previousattempts->feedbackplugins, $grade);

                    $attempt['grade'] = $grade;
                    $attempt['feedbackplugins'] = $feedbackplugins;
                }
                $result['previousattempts'][] = $attempt;
            }
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_submission_status return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_submission_status_returns() {
        return new external_single_structure(
            array(
                'gradingsummary' => new external_single_structure(
                    array(
                        'participantcount' => new external_value(PARAM_INT, 'Number of users who can submit.'),
                        'submissiondraftscount' => new external_value(PARAM_INT, 'Number of submissions in draft status.'),
                        'submissiondraftscount' => new external_value(PARAM_INT, 'Number of submissions in draft status.'),
                        'submissionsenabled' => new external_value(PARAM_BOOL, 'Whether submissions are enabled or not.'),
                        'submissionssubmittedcount' => new external_value(PARAM_INT, 'Number of submissions in submitted status.'),
                        'submissionsneedgradingcount' => new external_value(PARAM_INT, 'Number of submissions that need grading.'),
                        'warnofungroupedusers' => new external_value(PARAM_BOOL, 'Whether we need to warn people that there
                                                                        are users without groups.'),
                    ), 'Grading information.', VALUE_OPTIONAL
                ),
                'lastattempt' => new external_single_structure(
                    array(
                        'submission' => self::get_submission_structure(VALUE_OPTIONAL),
                        'teamsubmission' => self::get_submission_structure(VALUE_OPTIONAL),
                        'submissiongroup' => new external_value(PARAM_INT, 'The submission group id (for group submissions only).',
                                                                VALUE_OPTIONAL),
                        'submissiongroupmemberswhoneedtosubmit' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'USER id.'),
                            'List of users who still need to submit (for group submissions only).',
                            VALUE_OPTIONAL
                        ),
                        'submissionsenabled' => new external_value(PARAM_BOOL, 'Whether submissions are enabled or not.'),
                        'locked' => new external_value(PARAM_BOOL, 'Whether new submissions are locked.'),
                        'graded' => new external_value(PARAM_BOOL, 'Whether the submission is graded.'),
                        'canedit' => new external_value(PARAM_BOOL, 'Whether the user can edit the current submission.'),
                        'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit.'),
                        'extensionduedate' => new external_value(PARAM_INT, 'Extension due date.'),
                        'blindmarking' => new external_value(PARAM_BOOL, 'Whether blind marking is enabled.'),
                        'gradingstatus' => new external_value(PARAM_ALPHANUMEXT, 'Grading status.'),
                        'usergroups' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'Group id.'), 'User groups in the course.'
                        ),
                    ), 'Last attempt information.', VALUE_OPTIONAL
                ),
                'feedback' => new external_single_structure(
                    array(
                        'grade' => self::get_grade_structure(VALUE_OPTIONAL),
                        'gradefordisplay' => new external_value(PARAM_RAW, 'Grade rendered into a format suitable for display.'),
                        'gradeddate' => new external_value(PARAM_INT, 'The date the user was graded.'),
                        'plugins' => new external_multiple_structure(self::get_plugin_structure(), 'Plugins info.', VALUE_OPTIONAL),
                    ), 'Feedback for the last attempt.', VALUE_OPTIONAL
                ),
                'previousattempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'attemptnumber' => new external_value(PARAM_INT, 'Attempt number.'),
                            'submission' => self::get_submission_structure(VALUE_OPTIONAL),
                            'grade' => self::get_grade_structure(VALUE_OPTIONAL),
                            'feedbackplugins' => new external_multiple_structure(self::get_plugin_structure(), 'Feedback info.',
                                                                                    VALUE_OPTIONAL),
                        )
                    ), 'List all the previous attempts did by the user.', VALUE_OPTIONAL
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function list_participants_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeid' => new external_value(PARAM_INT, 'evalcode instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'filter' => new external_value(PARAM_RAW, 'search string to filter the results'),
                'skip' => new external_value(PARAM_INT, 'number of records to skip', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'maximum number of records to return', VALUE_DEFAULT, 0),
                'onlyids' => new external_value(PARAM_BOOL, 'Do not return all user fields', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Retrieves the list of students to be graded for the evalcodeframework.
     *
     * @param int $evalcodeid the evalcode instance id
     * @param int $groupid the current group id
     * @param string $filter search string to filter the results.
     * @param int $skip Number of records to skip
     * @param int $limit Maximum number of records to return
     * @param bool $onlyids Only return user ids.
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function list_participants($evalcodeid, $groupid, $filter, $skip, $limit, $onlyids) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/evalcode/locallib.php");
        require_once($CFG->dirroot . "/user/lib.php");

        $params = self::validate_parameters(self::list_participants_parameters(),
                                            array(
                                                'evalcodeid' => $evalcodeid,
                                                'groupid' => $groupid,
                                                'filter' => $filter,
                                                'skip' => $skip,
                                                'limit' => $limit,
                                                'onlyids' => $onlyids
                                            ));
        $warnings = array();

        // Request and permission validation.
        $evalcode = $DB->get_record('evalcode', array('id' => $params['evalcodeid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($evalcode, 'evalcode');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/evalcode:view', $context);

        $evalcode = new evalcode($context, null, null);
        $evalcode->require_view_grades();

        $participants = $evalcode->list_participants_with_filter_status_and_group($params['groupid']);

        $result = array();
        $index = 0;
        foreach ($participants as $record) {
            // Preserve the fullname set by the evalcodeframework.
            $fullname = $record->fullname;
            $searchable = $fullname;
            $match = false;
            if (empty($filter)) {
                $match = true;
            } else {
                $filter = core_text::strtolower($filter);
                $value = core_text::strtolower($searchable);
                if (is_string($value) && (core_text::strpos($value, $filter) !== false)) {
                    $match = true;
                }
            }
            if ($match) {
                $index++;
                if ($index <= $params['skip']) {
                    continue;
                }
                if (($params['limit'] > 0) && (($index - $params['skip']) > $params['limit'])) {
                    break;
                }
                // Now we do the expensive lookup of user details because we completed the filtering.
                if (!$evalcode->is_blind_marking() && !$params['onlyids']) {
                    $userdetails = user_get_user_details($record, $course);
                } else {
                    $userdetails = array('id' => $record->id);
                }
                $userdetails['fullname'] = $fullname;
                $userdetails['submitted'] = $record->submitted;
                $userdetails['requiregrading'] = $record->requiregrading;
                if (!empty($record->groupid)) {
                    $userdetails['groupid'] = $record->groupid;
                }
                if (!empty($record->groupname)) {
                    $userdetails['groupname'] = $record->groupname;
                }

                $result[] = $userdetails;
            }
        }
        return $result;
    }

    /**
     * Returns the description of the results of the mod_evalcode_external::list_participants() method.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function list_participants_returns() {
        // Get user description.
        $userdesc = core_user_external::user_description();
        // List unneeded properties.
        $unneededproperties = [
            'auth', 'confirmed', 'lang', 'calendartype', 'theme', 'timezone', 'mailformat'
        ];
        // Remove unneeded properties for consistency with the previous version.
        foreach ($unneededproperties as $prop) {
            unset($userdesc->keys[$prop]);
        }

        // Override property attributes for consistency with the previous version.
        $userdesc->keys['fullname']->type = PARAM_NOTAGS;
        $userdesc->keys['profileimageurlsmall']->required = VALUE_OPTIONAL;
        $userdesc->keys['profileimageurl']->required = VALUE_OPTIONAL;
        $userdesc->keys['email']->desc = 'Email address';
        $userdesc->keys['email']->desc = 'Email address';
        $userdesc->keys['idnumber']->desc = 'The idnumber of the user';

        // Define other keys.
        $otherkeys = [
            'groups' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'group id'),
                        'name' => new external_value(PARAM_RAW, 'group name'),
                        'description' => new external_value(PARAM_RAW, 'group description'),
                    ]
                ), 'user groups', VALUE_OPTIONAL
            ),
            'roles' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'roleid' => new external_value(PARAM_INT, 'role id'),
                        'name' => new external_value(PARAM_RAW, 'role name'),
                        'shortname' => new external_value(PARAM_ALPHANUMEXT, 'role shortname'),
                        'sortorder' => new external_value(PARAM_INT, 'role sortorder')
                    ]
                ), 'user roles', VALUE_OPTIONAL
            ),
            'enrolledcourses' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Id of the course'),
                        'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                        'shortname' => new external_value(PARAM_RAW, 'Shortname of the course')
                    ]
                ), 'Courses where the user is enrolled - limited by which courses the user is able to see', VALUE_OPTIONAL
            ),
            'submitted' => new external_value(PARAM_BOOL, 'have they submitted their evalcodeframework'),
            'requiregrading' => new external_value(PARAM_BOOL, 'is their submission waiting for grading'),
            'groupid' => new external_value(PARAM_INT, 'for group evalcodeframeworks this is the group id', VALUE_OPTIONAL),
            'groupname' => new external_value(PARAM_NOTAGS, 'for group evalcodeframeworks this is the group name', VALUE_OPTIONAL),
        ];

        // Merge keys.
        $userdesc->keys = array_merge($userdesc->keys, $otherkeys);
        return new external_multiple_structure($userdesc);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_participant_parameters() {
        return new external_function_parameters(
            array(
                'evalcodeid' => new external_value(PARAM_INT, 'evalcode instance id'),
                'userid' => new external_value(PARAM_INT, 'user id'),
                'embeduser' => new external_value(PARAM_BOOL, 'user id', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Get the user participating in the given evalcodeframework. An error with code 'usernotincourse'
     * is thrown is the user isn't a participant of the given evalcodeframework.
     *
     * @param int $evalcodeid the evalcode instance id
     * @param int $userid the user id
     * @param bool $embeduser return user details (only applicable if not blind marking)
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function get_participant($evalcodeid, $userid, $embeduser) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/evalcode/locallib.php");
        require_once($CFG->dirroot . "/user/lib.php");

        $params = self::validate_parameters(self::get_participant_parameters(), array(
            'evalcodeid' => $evalcodeid,
            'userid' => $userid,
            'embeduser' => $embeduser
        ));

        // Request and permission validation.
        $evalcode = $DB->get_record('evalcode', array('id' => $params['evalcodeid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($evalcode, 'evalcode');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $evalcode = new evalcode($context, null, null);
        $evalcode->require_view_grades();

        $participant = $evalcode->get_participant($params['userid']);
        if (!$participant) {
            // No participant found so we can return early.
            throw new moodle_exception('usernotincourse');
        }

        $return = array(
            'id' => $participant->id,
            'fullname' => $participant->fullname,
            'submitted' => $participant->submitted,
            'requiregrading' => $participant->requiregrading,
            'blindmarking' => $evalcode->is_blind_marking(),
        );

        if (!empty($participant->groupid)) {
            $return['groupid'] = $participant->groupid;
        }
        if (!empty($participant->groupname)) {
            $return['groupname'] = $participant->groupname;
        }

        // Skip the expensive lookup of user detail if we're blind marking or the caller
        // hasn't asked for user details to be embedded.
        if (!$evalcode->is_blind_marking() && $embeduser) {
            $return['user'] = user_get_user_details($participant, $course);
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function get_participant_returns() {
        $userdescription = core_user_external::user_description();
        $userdescription->default = [];
        $userdescription->required = VALUE_OPTIONAL;

        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'ID of the user'),
            'fullname' => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
            'submitted' => new external_value(PARAM_BOOL, 'have they submitted their evalcodeframework'),
            'requiregrading' => new external_value(PARAM_BOOL, 'is their submission waiting for grading'),
            'blindmarking' => new external_value(PARAM_BOOL, 'is blind marking enabled for this evalcodeframework'),
            'groupid' => new external_value(PARAM_INT, 'for group evalcodeframeworks this is the group id', VALUE_OPTIONAL),
            'groupname' => new external_value(PARAM_NOTAGS, 'for group evalcodeframeworks this is the group name', VALUE_OPTIONAL),
            'user' => $userdescription,
        ));
    }
}
