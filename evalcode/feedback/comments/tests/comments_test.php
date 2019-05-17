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
 * Unit tests for evalfeedback_comments
 *
 * @package    evalfeedback_comments
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/evalcode/tests/base_test.php');

/**
 * Unit tests for evalfeedback_comments
 *
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evalfeedback_comments_testcase extends mod_evalcode_base_testcase {

    /**
     * Create an evalcode object and submit an online text submission.
     */
    protected function create_evalcode_and_submit_text() {
        $evalcode = $this->create_instance(array('evalsubmission_onlinetext_enabled' => 1,
                                               'evalfeedback_comments_enabled' => 1));

        $user = $this->students[0];
        $this->setUser($user);

        // Create an online text submission.
        $submission = $evalcode->get_user_submission($user->id, true);

        $data = new stdClass();
        $data->onlinetext_editor = array(
                'text' => '<p>This is some text.</p>',
                'format' => 1,
                'itemid' => file_get_unused_draft_itemid());
        $plugin = $evalcode->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        return $evalcode;
    }

    /**
     * Test the is_feedback_modified() method for the comments feedback.
     */
    public function test_is_feedback_modified() {
        $evalcode = $this->create_evalcode_and_submit_text();

        $this->setUser($this->teachers[0]);

        // Create formdata.
        $data = new stdClass();
        $data->evalfeedbackcomments_editor = array(
                'text' => '<p>first comment for this test</p>',
                'format' => 1
            );
        $grade = $evalcode->get_user_grade($this->students[0]->id, true);

        // This is the first time that we are submitting feedback, so it is modified.
        $plugin = $evalcode->get_feedback_plugin_by_type('comments');
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        // Save the feedback.
        $plugin->save($grade, $data);
        // Try again with the same data.
        $this->assertFalse($plugin->is_feedback_modified($grade, $data));
        // Change the data.
        $data->evalfeedbackcomments_editor = array(
                'text' => '<p>Altered comment for this test</p>',
                'format' => 1
            );
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
    }
}
