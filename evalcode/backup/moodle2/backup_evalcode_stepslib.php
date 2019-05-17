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
 * Define all the backup steps that will be used by the backup_evalcode_activity_task
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete choice structure for backup, with file and id annotations
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_evalcode_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the evalcode activity
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $evalcode = new backup_nested_element('evalcode', array('id'),
                                            array('name',
                                                  'intro',
                                                  'introformat',
                                                  'alwaysshowdescription',
                                                  'submissiondrafts',
                                                  'sendnotifications',
                                                  'sendlatenotifications',
                                                  'sendstudentnotifications',
                                                  'duedate',
                                                  'cutoffdate',
                                                  'allowsubmissionsfromdate',
                                                  'grade',
                                                  'timemodified',
                                                  'completionsubmit',
                                                  'requiresubmissionstatement',
                                                  'teamsubmission',
                                                  'requireallteammemberssubmit',
                                                  'teamsubmissiongroupingid',
                                                  'blindmarking',
                                                  'revealidentities',
                                                  'attemptreopenmethod',
                                                  'maxattempts',
                                                  'markingworkflow',
                                                  'markingallocation',
                                                  'preventsubmissionnotingroup'));

        $userflags = new backup_nested_element('userflags');

        $userflag = new backup_nested_element('userflag', array('id'),
                                                array('userid',
                                                      'evalcodeframework',
                                                      'mailed',
                                                      'locked',
                                                      'extensionduedate',
                                                      'workflowstate',
                                                      'allocatedmarker'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'),
                                                array('userid',
                                                      'timecreated',
                                                      'timemodified',
                                                      'status',
                                                      'groupid',
                                                      'attemptnumber',
                                                      'latest'));

        $grades = new backup_nested_element('grades');

        $grade = new backup_nested_element('grade', array('id'),
                                           array('userid',
                                                 'timecreated',
                                                 'timemodified',
                                                 'grader',
                                                 'grade',
                                                 'attemptnumber'));

        $pluginconfigs = new backup_nested_element('plugin_configs');

        $pluginconfig = new backup_nested_element('plugin_config', array('id'),
                                                   array('plugin',
                                                         'subtype',
                                                         'name',
                                                         'value'));

        // Build the tree.
        $evalcode->add_child($userflags);
        $userflags->add_child($userflag);
        $evalcode->add_child($submissions);
        $submissions->add_child($submission);
        $evalcode->add_child($grades);
        $grades->add_child($grade);
        $evalcode->add_child($pluginconfigs);
        $pluginconfigs->add_child($pluginconfig);

        // Define sources.
        $evalcode->set_source_table('evalcode', array('id' => backup::VAR_ACTIVITYID));
        $pluginconfig->set_source_table('evalcode_plugin_config',
                                        array('evalcodeframework' => backup::VAR_PARENTID));

        if ($userinfo) {
            $userflag->set_source_table('evalcode_user_flags',
                                     array('evalcodeframework' => backup::VAR_PARENTID));

            $submission->set_source_table('eval_submission',
                                     array('evalcodeframework' => backup::VAR_PARENTID));

            $grade->set_source_table('evalcode_grades',
                                     array('evalcodeframework' => backup::VAR_PARENTID));

            // Support 2 types of subplugins.
            $this->add_subplugin_structure('evalsubmission', $submission, true);
            $this->add_subplugin_structure('evalfeedback', $grade, true);
        }

        // Define id annotations.
        $userflag->annotate_ids('user', 'userid');
        $userflag->annotate_ids('user', 'allocatedmarker');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'grader');
        $evalcode->annotate_ids('grouping', 'teamsubmissiongroupingid');

        // Define file annotations.
        // These file areas don't have an itemid.
        $evalcode->annotate_files('mod_evalcode', 'intro', null);
        $evalcode->annotate_files('mod_evalcode', 'introattachment', null);

        // Return the root element (choice), wrapped into standard activity structure.

        return $this->prepare_activity_structure($evalcode);
    }
}
