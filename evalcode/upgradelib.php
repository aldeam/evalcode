<?php

defined('MOODLE_INTERNAL') || die();

class evalcode_upgrade_manager {

    /**
     * This function converts all of the base settings for an instance of
     * the old evalcodeframework to the new format. Then it calls each of the plugins
     * to see if they can help upgrade this evalcodeframework.
     * @param int $oldevalcodeframeworkid (don't rely on the old evalcodeframework type even being installed)
     * @param string $log This string gets appended to during the conversion process
     * @return bool true or false
     */
    public function upgrade_evalcodeframework($oldevalcodeframeworkid, & $log) {
        // Steps to upgrade an evalcodeframework.
        return true;
    }
}
