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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/evalcode/locallib.php');

/**
 * EvalCode grading options form
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_evalcode_grading_options_form extends moodleform {
    /**
     * Define this form - called from the parent constructor.
     */
    public function definition() {
        /*$mform = $this->_form;
        $instance = $this->_customdata;
        $dirtyclass = array('class' => 'ignoredirty');

        $mform->addElement('header', 'general', get_string('gradingoptions', 'evalcode'));
        // Visible elements.
        $options = array(-1 => get_string('all'), 10 => '10', 20 => '20', 50 => '50', 100 => '100');
        $maxperpage = get_config('evalcode', 'maxperpage');
        if (isset($maxperpage) && $maxperpage != -1) {
            unset($options[-1]);
            foreach ($options as $val) {
                if ($val > $maxperpage) {
                    unset($options[$val]);
                }
            }
        }
        $mform->addElement('select', 'perpage', get_string('evalcodeframeworksperpage', 'evalcode'), $options, $dirtyclass);
        $options = array('' => get_string('filternone', 'evalcode'),
                         EVALCODE_FILTER_NOT_SUBMITTED => get_string('filternotsubmitted', 'evalcode'),
                         EVALCODE_FILTER_SUBMITTED => get_string('filtersubmitted', 'evalcode'),
                         EVALCODE_FILTER_REQUIRE_GRADING => get_string('filterrequiregrading', 'evalcode'));
        if ($instance['submissionsenabled']) {
            $mform->addElement('select', 'filter', get_string('filter', 'evalcode'), $options, $dirtyclass);
        }
        if (!empty($instance['markingallocationopt'])) {
            $markingfilter = get_string('markerfilter', 'evalcode');
            $mform->addElement('select', 'markerfilter', $markingfilter, $instance['markingallocationopt'], $dirtyclass);
        }
        if (!empty($instance['markingworkflowopt'])) {
            $workflowfilter = get_string('workflowfilter', 'evalcode');
            $mform->addElement('select', 'workflowfilter', $workflowfilter, $instance['markingworkflowopt'], $dirtyclass);
        }
        // Hidden params.
        $mform->addElement('hidden', 'contextid', $instance['contextid']);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'id', $instance['cm']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'saveoptions');
        $mform->setType('action', PARAM_ALPHA);

        // Buttons.
        $this->add_action_buttons(false, get_string('updatetable', 'evalcode'));*/
    }
}

