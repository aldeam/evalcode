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
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/evalcode/adminlib.php');

$ADMIN->add('modsettings', new admin_category('modevalcodefolder', new lang_string('pluginname', 'mod_evalcode'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('settings', 'mod_evalcode'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) {
    $menu = array();
    foreach (core_component::get_plugin_list('evalfeedback') as $type => $notused) {
        $visible = !get_config('evalfeedback_' . $type, 'disabled');
        if ($visible) {
            $menu['evalfeedback_' . $type] = new lang_string('pluginname', 'evalfeedback_' . $type);
        }
    }

    // The default here is feedback_comments (if it exists).
    $name = new lang_string('feedbackplugin', 'mod_evalcode');
    $description = new lang_string('feedbackpluginforgradebook', 'mod_evalcode');
    $settings->add(new admin_setting_configselect('evalcode/feedback_plugin_for_gradebook',
                                                  $name,
                                                  $description,
                                                  'evalfeedback_comments',
                                                  $menu));

    $name = new lang_string('showrecentsubmissions', 'mod_evalcode');
    $description = new lang_string('configshowrecentsubmissions', 'mod_evalcode');
    $settings->add(new admin_setting_configcheckbox('evalcode/showrecentsubmissions',
                                                    $name,
                                                    $description,
                                                    0));

    $name = new lang_string('sendsubmissionreceipts', 'mod_evalcode');
    $description = new lang_string('sendsubmissionreceipts_help', 'mod_evalcode');
    $settings->add(new admin_setting_configcheckbox('evalcode/submissionreceipts',
                                                    $name,
                                                    $description,
                                                    1));

    $name = new lang_string('submissionstatement', 'mod_evalcode');
    $description = new lang_string('submissionstatement_help', 'mod_evalcode');
    $default = get_string('submissionstatementdefault', 'mod_evalcode');
    $settings->add(new admin_setting_configtextarea('evalcode/submissionstatement',
                                                    $name,
                                                    $description,
                                                    $default));

    $name = new lang_string('maxperpage', 'mod_evalcode');
    $options = array(
        -1 => get_string('unlimitedpages', 'mod_evalcode'),
        10 => 10,
        20 => 20,
        50 => 50,
        100 => 100,
    );
    $description = new lang_string('maxperpage_help', 'mod_evalcode');
    $settings->add(new admin_setting_configselect('evalcode/maxperpage',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options));

    $name = new lang_string('defaultsettings', 'mod_evalcode');
    $description = new lang_string('defaultsettings_help', 'mod_evalcode');
    $settings->add(new admin_setting_heading('defaultsettings', $name, $description));

    $name = new lang_string('alwaysshowdescription', 'mod_evalcode');
    $description = new lang_string('alwaysshowdescription_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/alwaysshowdescription',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('allowsubmissionsfromdate', 'mod_evalcode');
    $description = new lang_string('allowsubmissionsfromdate_help', 'mod_evalcode');
    $setting = new admin_setting_configduration('evalcode/allowsubmissionsfromdate',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('duedate', 'mod_evalcode');
    $description = new lang_string('duedate_help', 'mod_evalcode');
    $setting = new admin_setting_configduration('evalcode/duedate',
                                                    $name,
                                                    $description,
                                                    604800);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('cutoffdate', 'mod_evalcode');
    $description = new lang_string('cutoffdate_help', 'mod_evalcode');
    $setting = new admin_setting_configduration('evalcode/cutoffdate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('submissiondrafts', 'mod_evalcode');
    $description = new lang_string('submissiondrafts_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/submissiondrafts',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requiresubmissionstatement', 'mod_evalcode');
    $description = new lang_string('requiresubmissionstatement_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/requiresubmissionstatement',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(
        'none' => get_string('attemptreopenmethod_none', 'mod_evalcode'),
        'manual' => get_string('attemptreopenmethod_manual', 'mod_evalcode'),
        'untilpass' => get_string('attemptreopenmethod_untilpass', 'mod_evalcode')
    );
    $name = new lang_string('attemptreopenmethod', 'mod_evalcode');
    $description = new lang_string('attemptreopenmethod_help', 'mod_evalcode');
    $setting = new admin_setting_configselect('evalcode/attemptreopenmethod',
                                                    $name,
                                                    $description,
                                                    'none',
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(-1 => get_string('unlimitedattempts', 'mod_evalcode'));
    $options += array_combine(range(1, 30), range(1, 30));
    $name = new lang_string('maxattempts', 'mod_evalcode');
    $description = new lang_string('maxattempts_help', 'mod_evalcode');
    $setting = new admin_setting_configselect('evalcode/maxattempts',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmission', 'mod_evalcode');
    $description = new lang_string('teamsubmission_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/teamsubmission',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('preventsubmissionnotingroup', 'mod_evalcode');
    $description = new lang_string('preventsubmissionnotingroup_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/preventsubmissionnotingroup',
        $name,
        $description,
        0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requireallteammemberssubmit', 'mod_evalcode');
    $description = new lang_string('requireallteammemberssubmit_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/requireallteammemberssubmit',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmissiongroupingid', 'mod_evalcode');
    $description = new lang_string('teamsubmissiongroupingid_help', 'mod_evalcode');
    $setting = new admin_setting_configempty('evalcode/teamsubmissiongroupingid',
                                                    $name,
                                                    $description);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendnotifications', 'mod_evalcode');
    $description = new lang_string('sendnotifications_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/sendnotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendlatenotifications', 'mod_evalcode');
    $description = new lang_string('sendlatenotifications_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/sendlatenotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendstudentnotificationsdefault', 'mod_evalcode');
    $description = new lang_string('sendstudentnotificationsdefault_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/sendstudentnotifications',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('blindmarking', 'mod_evalcode');
    $description = new lang_string('blindmarking_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/blindmarking',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingworkflow', 'mod_evalcode');
    $description = new lang_string('markingworkflow_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/markingworkflow',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingallocation', 'mod_evalcode');
    $description = new lang_string('markingallocation_help', 'mod_evalcode');
    $setting = new admin_setting_configcheckbox('evalcode/markingallocation',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

$ADMIN->add('modevalcodefolder', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('modevalcodefolder', new admin_category('evalsubmissionplugins',
    new lang_string('submissionplugins', 'evalcode'), !$module->is_enabled()));
$ADMIN->add('evalsubmissionplugins', new evalcode_admin_page_manage_evalcode_plugins('evalsubmission'));
$ADMIN->add('modevalcodefolder', new admin_category('evalfeedbackplugins',
    new lang_string('feedbackplugins', 'evalcode'), !$module->is_enabled()));
$ADMIN->add('evalfeedbackplugins', new evalcode_admin_page_manage_evalcode_plugins('evalfeedback'));

foreach (core_plugin_manager::instance()->get_plugins_of_type('evalsubmission') as $plugin) {
    /** @var \mod_evalcode\plugininfo\evalsubmission $plugin */
    $plugin->load_settings($ADMIN, 'evalsubmissionplugins', $hassiteconfig);
}

foreach (core_plugin_manager::instance()->get_plugins_of_type('evalfeedback') as $plugin) {
    /** @var \mod_evalcode\plugininfo\evalfeedback $plugin */
    $plugin->load_settings($ADMIN, 'evalfeedbackplugins', $hassiteconfig);
}
