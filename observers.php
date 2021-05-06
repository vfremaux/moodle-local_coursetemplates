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

if (!function_exists('debug_trace')) {
    function debug_trace($message, $label = '') {
        assert(1);
    }
}

/**
 * Event observer for format_page.
 */
class local_coursetemplates_observer {

    /**
     * This will add the teacher as standard editingteacher
     * @param object $event
     */
    public static function on_course_created(\core\event\course_created $event) {
        global $DB, $USER, $SESSION;

        $config = get_config('local_coursetemplates');

        if (function_exists('debug_trace')) {
            debug_trace("coursetemplates observer : try to enrol creator");
        }

        if (!empty($SESSION->nocoursetemplateautoenrol)) {
            // Exclude sessions having this mark on.
            return false;
        }

        if (empty($config->autoenrolcreator)) {
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : abort not enabled");
            }
            return false;
        }

        // Exclude administrators from this setup when performing from CLI_SCRIPTS.
        // Administrators usually create courses for other people.
        $systemcontext = context_system::instance();
        if (has_capability('moodle/site:config', $systemcontext, $event->userid) && defined('CLI_SCRIPT')) {
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : abort as admin capabilities");
            }
            return;
        }

        // User has course import capability. He may NOT be enrolled in all imported courses !
        if (has_capability('tool/sync:configure', $systemcontext, $event->userid) && defined('CLI_SCRIPT')) {
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : abort as bulk course create capabilitites ");
            }
            return;
        }

        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : abort as testing ");
            }
            return;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : abort as running cli ");
            }
            return;
        }

        if (function_exists('debug_trace')) {
            debug_trace("coursetemplates observer : enrolling $event->userid in course $event->objectid");
        }
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $enrolplugin = enrol_get_plugin('manual');
        $params = array('enrol' => 'manual', 'courseid' => $event->objectid, 'status' => ENROL_INSTANCE_ENABLED);
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
        } else {
            // If not yet exists, add manual enrol instance to course.
            $course = $DB->get_record('course', array('id' => $event->objectid));
            $enrolid = $enrolplugin->add_instance($course);
            $enrol = $DB->get_record('enrol', array('id' => $enrolid));
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
            if (function_exists('debug_trace')) {
                debug_trace("coursetemplates observer : no enrol instances in course $event->objectid");
            }
        }
    }
}
