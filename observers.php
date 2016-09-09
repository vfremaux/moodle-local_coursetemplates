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
 * Event observers used coursetemplates.
 *
 * @package    local_coursetemplates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for format_page.
 */
class local_coursetemplates_observer {

    /**
     * This will add the teacher as standard editingteacher
     * @param object $event
     */
    static function on_course_created(\core\event\course_created $event) {
        global $DB;

        /*
        if (defined('CLI_SCRIPT')) {
            return;
        }
        */

        // Exclude administrators from this setup.
        // Administrators usually create courses for other people.
        $systemcontext = context_system::instance();
        if (has_capability('moodle/site:config', $systemcontext, $event->userid)) {
            return;
        }
        if (has_capability('tool/sync:configure', $systemcontext, $event->userid)) {
            return;
        }

        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $enrolplugin = enrol_get_plugin('manual');
        if ($enrols = $DB->get_records('enrol', array('enrol' => 'manual', 'courseid' => $event->objectid, 'status' => ENROL_INSTANCE_ENABLED), 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
        }

    }
}
