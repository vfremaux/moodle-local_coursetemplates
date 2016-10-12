<?php
// This file is NOT part of Moodle - http://moodle.org/
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
 * @package    local_coursetemplates
 * @category   local
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * prints a flat template list from available templates
 * TODO extend to template subcategories
 */
function coursetemplates_course_list() {
    global $OUTPUT, $PAGE, $DB;

    $renderer = $PAGE->get_renderer('local_coursetemplates');

    $config = get_config('local_coursetemplates');

    $courses = $DB->get_records('course', array('category' => $config->templatecategory));

    if ($courses) {
        foreach ($courses as $course) {
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourse')) {
                continue;
            }
            echo $renderer->templatecoursebox($course);
        }
    } else {
        $OUTPUT->box(get_string('notemplates', 'local_courdsetemplates'));
    }
}

/** 
 * Make a silent restore of the template into the target category and enrol user as teacher inside 
 * if reqested.
 * NOT USED AT THE MOMENT
 */
function coursetemplates_restore_template($category, $sourcecourse, $enrolme) {
    global $CFG, $USER, $DB;

    include_once($CFG->dirroot.'/backup/restorelib.php');
    include_once($CFG->dirroot.'/backup/lib.php');

    $deploycat = $DB->get_record('course_categories', array('id' => $category));

    /*
     * If publishflow is installed, prefer published backups,
     * else where take standard available backup
     */
    $fs = get_file_storage();
    $coursecontextid = context_course::instance($sourcecourse->id)->id;
    if ($DB->get_record('blocks', array('name' => 'publishflow'))) {
        // Lets get the publishflow published file.
        $backupfiles = $fs->get_area_files($coursecontextid, 'backup', 'publishflow', 0, 'timecreated', false);
    }

    if (!$backupfiles) {
        assert(true);
        // TODO : Get last standard backup.
    }

    if (!$backupfiles) {
        print_error('errornotpublished', 'block_publishflow');
    }

    $origtime = ini_get('max_execution_time');
    $origmem = ini_get('memory_limit');

    $maxtime = '240';
    $maxmem = '512M';

    ini_set('max_execution_time', $maxtime);
    ini_set('memory_limit', $maxmem);

    // Confirm/force guest closure.

    $file = array_pop ($backupfiles);
    $newcourse_id =  restore_automation::run_automated_restore($file->get_id(), null, $category);

    // Confirm/force idnumber and new course params in new course.
    $DB->set_field('course', 'fullname', $sourcecourse->fullname, array('id' => "{$newcourse_id}"));
    $DB->set_field('course', 'shortname', $sourcecourse->shortname, array('id' => "{$newcourse_id}"));
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$newcourse_id}"));

    if ($enrolme) {
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $enrol = enrol_get_plugin('manual');
        $params = array('enrol' => $c->enrol, 'courseid' => $newcourse_id, 'status' => ENROL_INSTANCE_ENABLED);
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin = enrol_get_plugin($c->enrol);
            $enrolplugin->enrol_user($enrol, $USER->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
        }
    }

    return ($newcourse_id);
}

/**
 * checks locally if a deployable/publishable backup is available
 * @param $courseid
 * @param $filearea
 * @return boolean
 */
function local_coursetemplates_locate_backup_file($courseid, $filearea) {
    global $DB;

    $fs = get_file_storage();
    $templatecontext = context_course::instance($courseid);

    // If ever the publishflow block is installed, get first the last published backup.
    if ($DB->get_record('block', array('name' => 'publishflow'))) {
        // Lets get the publishflow published file.
        $backupfiles = $fs->get_area_files($templatecontext->id, 'backup', 'publishflow', 0, 'timecreated', false);
    }

    if (empty($backupfiles)) {
        // Alternatively and as last try standard backup.
        $backupfiles = $fs->get_area_files($templatecontext->id, 'backup', $filearea, 0, 'timecreated', false);
    }

    if (count($backupfiles) > 0) {
        return array_pop($backupfiles);
    }

    return false;
}
