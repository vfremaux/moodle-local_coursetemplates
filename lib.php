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
 * General library.
 *
 * @package    local_coursetemplates
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  Valery Fremaux (www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * phpcs:disable moodle.Commenting.ValidTags.Invalid
 */

define('LOCAL_CT_TRACE_DEBUG', 3);

/**
 * this function is not implemented in thos plugin, but is needed to mark
 * the vf documentation custom volume availability.
 */
function local_coursetemplates_supports_feature() {
    assert(1);
}

/**
 * Make a silent restore of the template into the target category and enrol user as teacher inside
 * if requested.
 * @param string $archivefile
 * @param object $data options from form.
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function coursetemplates_restore_template($archivefile, $data) {
    global $CFG, $DB, $USER, $PAGE, $OUTPUT;

    $uniqid = uniqid();
    $contextid = context_system::instance()->id;
    $tempdir = $CFG->tempdir."/backup/$uniqid";

    if (!is_dir($tempdir)) {
        mkdir($tempdir, 0777, true);
    }

    $url = new moodle_url('/local/coursetemplates/index.php');

    if (!$archivefile->extract_to_pathname(new mbz_packer(), $tempdir)) {
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('error');
        echo $OUTPUT->notification(get_string('restoreerror', 'local_coursetemplates'));
        echo $OUTPUT->box_end();
        echo $OUTPUT->continue_button($url);
        echo $OUTPUT->footer();
        return false;
    }

    // Transaction.
    $transaction = $DB->start_delegated_transaction();

    // Create new course.
    $categoryid = $data->category; // A categoryid.
    $userdoingtherestore = $USER->id; // E.g. 2 == admin.
    $newcourseid = restore_dbops::create_new_course('', '', $categoryid);

    // Restore backup into course.
    $controller = new restore_controller($uniqid, $newcourseid,
        backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userdoingtherestore,
        backup::TARGET_NEW_COURSE);
    if ($controller->execute_precheck()) {
        $controller->execute_plan();
    } else {
        $precheckerrors = $controller->get_precheck_results();
        $info = $controller->get_info();
        echo $OUTPUT->header();
        $renderer = $PAGE->get_renderer('core', 'backup');
        echo $renderer->precheck_notices($precheckerrors);

        if (!empty($info->role_mappings->mappings)) {
            $context = context_course::instance($controller->get_courseid());
            $assignableroles = get_assignable_roles($context, ROLENAME_ALIAS, false);

            // Get current role mappings.
            $currentroles = role_fix_names(get_all_roles(), $context);
            // Get backup role mappings.
            $rolemappings = $info->role_mappings->mappings;

            array_map(function($rolemapping) use ($currentroles) {
                foreach ($currentroles as $role) {
                    // Find matching archetype to determine the backup's shortname for label display.
                    if ($rolemapping->archetype == $role->archetype) {
                        $rolemapping->name = $rolemapping->shortname;
                        break;
                    }
                }
                if ($rolemapping->name == null) {
                    $rolemapping->name = get_string('undefinedrolemapping', 'backup', $rolemapping->archetype);
                }
            }, $rolemappings);

            echo $renderer->role_mappings($rolemappings, $assignableroles);
        }

        echo $OUTPUT->continue_button($url);
        echo $OUTPUT->footer();
        return false;
    }

    // Commit.
    $transaction->allow_commit();

    // Update names.
    if ($newcourse = $DB->get_record('course', ['id' => $newcourseid])) {
        $newcourse->fullname = $data->fullname;
        $newcourse->shortname = $data->shortname;
        $newcourse->idnumber = $data->idnumber;
        if (!empty($data->summary)) {
            $newcourse->summary = $data->summary;
        }
        $DB->update_record('course', $newcourse);
    }

    // Cleanup temp file area.
    $fs = get_file_storage();
    $fs->delete_area_files($contextid, 'local_coursetemplates', 'temp');

    return $newcourseid;
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
    if ($DB->get_record('block', ['name' => 'publishflow'])) {
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
 * @param int $courseid
 * @param array $options
 * @param object $log
 */
function local_coursetemplates_backup_for_template($courseid, $options = [], &$log = '') {
    global $CFG;

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

        $settings = [
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
            'filename' => backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised),
        ];

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

/**
 * Get template courses.
 */
function local_coursetemplates_get_courses() {
    global $DB;

    $config = get_config('local_coursetemplates');

    if (empty($config->templatecategory)) {
        return;
    }

    $categories = [];
    local_coursetemplates_get_all_categories($categories, $config->templatecategory);

    list($insql, $params) = $DB->get_in_or_equal($categories);

    $select = " category $insql ";

    $allcourses = $DB->get_records_select('course', $select, $params, 'id,shortname,fullname');

    return $allcourses;
}

/**
 * Recusively fetch all template categories subtree.
 * @param array $catarray
 * @param int $parentid
 */
function local_coursetemplates_get_all_categories(& $catarray, $parentid) {
    global $DB;

    $catarray[] = $parentid;

    $children = $DB->get_records('course_categories', ['parent' => $parentid], 'id,name');
    if (empty($children)) {
        return;
    }

    foreach (array_keys($children) as $ch) {
        local_coursetemplates_get_all_categories($catarray, $ch);
    }
}

/**
 * Do enable templates.
 */
function local_coursetemplates_enable() {
    set_config('enabled', 1, 'local_coursetemplates');
}

/**
 * Do disable templates.
 */
function local_coursetemplates_disable() {
    set_config('enabled', 0, 'local_coursetemplates');
}

/**
 * Are tempaltes enabled ?
 */
function local_coursetemplates_enabled() {
    return get_config('local_coursetemplates', 'enabled');
}

/**
 * get courses i am authoring in (or by capability).
 * @param string $fields return fields of the course record
 * @param string $capability the capability to check for authoring
 * @param string $excludecats an array of catids we do not want courses in
 */
function local_coursetemplates_get_my_authoring_courses($fields = '*', $capability = 'local/my:isauthor', $excludecats = []) {
    global $USER, $DB;

    if (empty($fields)) {
        throw new moodle_exception('Empty field list');
    }

    if ($fields != '*' && !preg_match('/\bcategory\b/', $fields)) {
        $fields .= ',category';
    }

    if ($authored = local_get_user_capability_course($capability, $USER->id, false, '', 'sortorder')) {
        foreach ($authored as $a) {
            $course = $DB->get_record('course', ['id' => $a->id], $fields);
            if (!in_array($course->category, $excludecats)) {
                $authoredcourses[$a->id] = $course;
            }
        }
        return $authoredcourses;
    }
    return [];
}

/**
 * Wrapper to APL debugging.
 * @param string $msg
 * @param int $level
 * @param string $label
 * @param int $backtracelevel
 */
function local_coursetemplates_debug_trace($msg, $level = LOCAL_CT_TRACE_DEBUG, $label = '', $backtracelevel = 1) {
    if (function_exists('debug_trace')) {
        debug_trace($msg, $level, $label, $backtracelevel + 1);
    }
}
