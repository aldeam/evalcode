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
 * The mod_evalcode all submissions downloaded event.
 *
 * @package    mod_evalcode
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_evalcode\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_evalcode all submissions downloaded event class.
 *
 * @package    mod_evalcode
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_submissions_downloaded extends base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \evalcode $evalcode
     * @return all_submissions_downloaded
     */
    public static function create_from_evalcode(\evalcode $evalcode) {
        $data = array(
            'context' => $evalcode->get_context(),
            'objectid' => $evalcode->get_instance()->id
        );
        self::$preventcreatecall = false;
        /** @var submission_graded $event */
        $event = self::create($data);
        self::$preventcreatecall = true;
        $event->set_evalcode($evalcode);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has downloaded all the submissions for the evalcodeframework " .
            "with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventallsubmissionsdownloaded', 'mod_evalcode');
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'evalcode';
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $this->set_legacy_logdata('download all submissions', get_string('downloadall', 'evalcode'));
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call all_submissions_downloaded::create() directly, use all_submissions_downloaded::create_from_evalcode() instead.');
        }

        parent::validate_data();
    }

    public static function get_objectid_mapping() {
        return array('db' => 'evalcode', 'restore' => 'evalcode');
    }
}
