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
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  Valery Fremaux (www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/coursetemplates/lib.php');

/**
 * Event observer for format_page.
 * phpcs:disable moodle.Commenting.ValidTags.Invalid
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
class local_coursetemplates_observer {

    /**
     * This will add the teacher as standard editingteacher
     * @param object $event
     */
    public static function on_course_created(\core\event\course_created $event) {
        global $DB, $SESSION, $CFG;

        $config = get_config('local_coursetemplates');

        local_coursetemplates_debug_trace("coursetemplates observer / created : try to enrol creator", LOCAL_CT_TRACE_DEBUG);

        /*
         * Used by some non script code to disable this feature "on demand".
         * This is to be used as a one shot trigger switch. Toggles back to disabled state after use.
         * @see local/moodlescript/classes/handle_add_course.class.php
         */
        if (!empty($SESSION->nocoursetemplateautoenrol)) {
            // Exclude sessions having this marked on.
            $SESSION->nocoursetemplateautoenrol = false;
            return false;
        }

        if (empty($config->autoenrolcreator)) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort not enabled", LOCAL_CT_TRACE_DEBUG);
            return false;
        }

        // Exclude administrators from this setup when performing from CLI_SCRIPTS.
        // Administrators usually create courses for other people.
        $systemcontext = context_system::instance();
        if (has_capability('moodle/site:config', $systemcontext, $event->userid) && (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as admin capabilities", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        // User has course import capability. He may NOT be enrolled in all imported courses !
        if (is_dir($CFG->dirroot.'/admin/tool/sync')) {
            // For those who have tool_sync installed.
            if (has_capability('tool/sync:configure', $systemcontext, $event->userid) && (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
                $msg = "coursetemplates observer : abort as bulk course create capabilitites ";
                local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
                return;
            }
        }

        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as testing ", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as running cli ", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        $msg = "coursetemplates observer : enrolling $event->userid in course $event->objectid";
        local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $enrolplugin = enrol_get_plugin('manual');
        $params = ['enrol' => 'manual', 'courseid' => $event->objectid, 'status' => ENROL_INSTANCE_ENABLED];
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
        } else {
            // If not yet exists, add manual enrol instance to course.
            $course = $DB->get_record('course', ['id' => $event->objectid]);
            $enrolid = $enrolplugin->add_default_instance($course);
            $enrol = $DB->get_record('enrol', ['id' => $enrolid]);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
            $msg = "coursetemplates observer : no enrol instances in course $event->objectid but added one to complete.";
            local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
        }
    }

    /**
     * This will add the teacher as standard editingteacher
     * @param object $event
     */
    public static function on_course_restored(\core\event\course_restored $event) {
        global $DB, $SESSION, $CFG;

        $config = get_config('local_coursetemplates');

        local_coursetemplates_debug_trace("coursetemplates observer / restored : try to enrol creator", LOCAL_CT_TRACE_DEBUG);

        /*
         * Used by some non script code to disable this feature "on demand".
         * This is to be used as a one shot trigger switch. Toggles back to disabled state after use.
         * @see local/moodlescript/classes/handle_add_course.class.php
         */
        if (!empty($SESSION->nocoursetemplateautoenrol)) {
            // Exclude sessions having this marked on.
            $SESSION->nocoursetemplateautoenrol = false;
            return false;
        }

        if (empty($config->autoenrolcreator)) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort not enabled", LOCAL_CT_TRACE_DEBUG);
            return false;
        }

        // Exclude administrators from this setup when performing from CLI_SCRIPTS.
        // Administrators usually create courses for other people.
        $systemcontext = context_system::instance();
        if (has_capability('moodle/site:config', $systemcontext, $event->userid) && (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as admin capabilities", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        // User has course import capability. He may NOT be enrolled in all imported courses !
        if (is_dir($CFG->dirroot.'/admin/tool/sync')) {
            // For those who have tool_sync installed.
            if (has_capability('tool/sync:configure', $systemcontext, $event->userid) && (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
                $msg = "coursetemplates observer : abort as bulk course create capabilitites ";
                local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
                return;
            }
        }

        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as testing ", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            local_coursetemplates_debug_trace("coursetemplates observer : abort as running cli ", LOCAL_CT_TRACE_DEBUG);
            return;
        }

        $msg = "coursetemplates observer : enrolling $event->userid in course $event->objectid";
        local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $enrolplugin = enrol_get_plugin('manual');
        $params = ['enrol' => 'manual', 'courseid' => $event->objectid, 'status' => ENROL_INSTANCE_ENABLED];
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
        } else {
            // If not yet exists, add manual enrol instance to course.
            $course = $DB->get_record('course', ['id' => $event->objectid]);
            $enrolid = $enrolplugin->add_default_instance($course);
            $enrol = $DB->get_record('enrol', ['id' => $enrolid]);
            $enrolplugin->enrol_user($enrol, $event->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
            $msg = "coursetemplates observer : no enrol instances in course $event->objectid but added one to complete.";
            local_coursetemplates_debug_trace($msg, LOCAL_CT_TRACE_DEBUG);
        }
    }
}
