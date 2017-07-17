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

    $file = array_pop($backupfiles);
    $newcourseid = restore_automation::run_automated_restore($file->get_id(), null, $category);

    // Confirm/force idnumber and new course params in new course.
    $DB->set_field('course', 'fullname', $sourcecourse->fullname, array('id' => "{$newcourseid}"));
    $DB->set_field('course', 'shortname', $sourcecourse->shortname, array('id' => "{$newcourseid}"));
    $DB->set_field('course', 'idnumber', $sourcecourse->idnumber, array('id' => "{$newcourseid}"));

    if ($enrolme) {
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $enrol = enrol_get_plugin('manual');
        $params = array('enrol' => $c->enrol, 'courseid' => $newcourseid, 'status' => ENROL_INSTANCE_ENABLED);
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin = enrol_get_plugin($c->enrol);
            $enrolplugin->enrol_user($enrol, $USER->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
        }
    }

    return ($newcourseid);
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

/**
 * Make a course backup without user data and stores it in the course
 * backup area.
 */
function local_coursetemplates_backup_for_template($courseid, $options = array(), &$log = '') {
    global $CFG, $USER;

    $user = get_admin();

    include_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');

    $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
                                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id);

    try {

        $coursecontext = context_course::instance($courseid);

        // Build default settings for quick backup.
        // Quick backup is intended for publishflow purpose.

        // Get default filename info from controller.
        $format = $bc->get_format();
        $type = $bc->get_type();
        $id = $bc->get_id();
        $users = $bc->get_plan()->get_setting('users')->get_value();
        $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();

        $settings = array(
            'users' => 0,
            'role_assignments' => 0,
            'user_files' => 0,
            'activities' => 1,
            'blocks' => 1,
            'filters' => 1,
            'comments' => 0,
            'completion_information' => 0,
            'logs' => 0,
            'histories' => 0,
            'filename' => backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised)
        );

        foreach ($settings as $setting => $configsetting) {
            if ($bc->get_plan()->setting_exists($setting)) {
                $bc->get_plan()->get_setting($setting)->set_value($configsetting);
            }
        }

        $bc->set_status(backup::STATUS_AWAITING);

        $bc->execute_plan();
        $results = $bc->get_results();
        // Convert user file in course file.
        $file = $results['backup_destination'];

        $fs = get_file_storage();

        $filerec = new StdClass();
        $filerec->contextid = $coursecontext->id;
        $filerec->component = 'backup';
        $filerec->filearea = 'course';
        $filerec->itemid = 0;
        $filerec->filepath = $file->get_filepath();
        $filerec->filename = $file->get_filename();

        if (!empty($options['clean'])) {
            if (!empty($options['verbose'])) {
                $log .= "Cleaning course backup area\n";
            }
            $fs->delete_area_files($coursecontext->id, 'backup', 'course');
        }

        if (!empty($options['verbose'])) {
            $log .= "Moving backup to course backup area\n";
        }
        $archivefile = $fs->create_file_from_storedfile($filerec, $file);

        // Remove user scope original file.
        $file->delete();

        return $archivefile;

    } catch (backup_exception $e) {
        return null;
    }
}

function local_coursetemplates_get_courses() {
    global $DB;

    $config = get_config('local_coursetemplates');

    if (empty($config->templatecategory)) {
        return;
    }

    $categories = array();
    local_coursetemplates_get_all_categories($categories, $config->templatecategory);

    list($insql, $params) = $DB->get_in_or_equal($categories);

    $select = " category $insql ";

    $allcourses = $DB->get_records_select('course', $select, $params, 'id,shortname,fullname');

    return $allcourses;
}

/**
 * Recusively fetch all template categories subtree.
 *
 */
function local_coursetemplates_get_all_categories(&$catarray, $parent) {
    global $DB;

    $catarray[] = $parent;

    $children = $DB->get_records('course_categories', array('parent' => $parent), 'id,name');
    if (empty($children)) {
        return;
    }

    foreach (array_keys($children) as $ch) {
        local_coursetemplates_get_all_categories($catarray, $ch);
    }
}