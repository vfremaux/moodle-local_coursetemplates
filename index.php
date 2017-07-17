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
require('../../config.php');
require_once($CFG->dirroot.'/local/coursetemplates/deploy_form.php');
require_once($CFG->dirroot.'/local/coursetemplates/lib.php');
require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');
require_once("$CFG->libdir/filestorage/tgz_packer.php");

$url = new moodle_url('/local/coursetemplates/index.php');

$sourcecourse = new StdClass();
$sourcecourse->id = optional_param('restore', false, PARAM_INT);
$sourcecourse->shortname = optional_param('shortname', false, PARAM_TEXT);
$sourcecourse->fullname = optional_param('fullname', false, PARAM_CLEANHTML);
$sourcecourse->idnumber = optional_param('idnumber', false, PARAM_TEXT);
$targetcategory = optional_param('category', false, PARAM_INT);
$forceediting = optional_param('forceediting', false, PARAM_INT);

$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

// Security.
require_login();

$renderer = $PAGE->get_renderer('local_coursetemplates');

$PAGE->set_title(get_string('pluginname', 'local_coursetemplates'));
$PAGE->set_heading(get_string('pluginname', 'local_coursetemplates'));
$PAGE->navbar->add(get_string('pluginname', 'local_coursetemplates'));

// Control input and run deployment.
if ($sourcecourse->id) {
    if (empty($sourcecourse->fullname)) {
        echo $OUTPUT->notification(get_string('erroremptyname', 'local_coursetemplates'), 'error');
    } else if (empty($sourcecourse->shortname)) {
        echo $OUTPUT->notification(get_string('erroremptyshortname', 'local_coursetemplates'), 'error');
    } else if (empty($targetcategory)) {
        print_error('errorbadcategory', 'local_coursetemplates');
    } else if (!$targetcontext = context_coursecat::instance($targetcategory)) {
        print_error('errorbadcategorycontext', 'local_coursetemplates');
    } else if (!has_capability('moodle/course:create', $targetcontext)) {
        echo $OUTPUT->notification(get_string('errornocategoryaccess', 'local_coursetemplates'), 'error');
    } else {
        $enrolme = optional_param('enrolme', false, PARAM_INT);
        coursetemplate_restore_template($targetcategory, $sourcecourse, $enrolme);

        $OUTPUT->header();
        echo $renderer->postdeploychoice();
        $OUTPUT->footer();
    }
}

$options = array();
if ($targetcategory) {
    $options = array('categoryid' => $targetcategory, 'forceediting' => $forceediting);
}

if (optional_param('profileme', 0, PARAM_BOOL)) {
    $options['profileme'] = 1;
}

$mform = new TemplateDeployForm($url, $options);

// Process intermediary forms.
if ($data = $mform->get_data()) {
    // Deploy a new templated course.

    $dataarr = (array)$data;

    // Find submit key.
    foreach (array_keys($dataarr) as $datakey) {
        if (preg_match('/^submit_(\d+)$/', $datakey, $matches)) {
            if (!empty($data->$datakey)) {
                $templatecourseid = $matches[1];
                break;
            }
        }
    }

    if (empty($templatecourseid)) {
        print_error('errorbadtemplateid', 'local_coursetemplates', '', $url);
    }

    if (!$archivefile = local_coursetemplates_locate_backup_file($templatecourseid, 'course')) {
        // Try backup the template automatically.
        $archivefile = local_coursetemplates_backup_for_template($templatecourseid);
    }

    if (!$archivefile) {
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('error');
        echo $OUTPUT->notification(get_string('errornobackup', 'local_coursetemplates'));
        echo $OUTPUT->box_end();
        echo $OUTPUT->continue_button($url);
        echo $OUTPUT->footer();
        die;
    }

    $contextid = context_system::instance()->id;
    $component = 'local_coursetemplates';
    $filearea = 'temp';
    $itemid = $uniq = 9999999 + rand(0, 100000);
    $tempdir = $CFG->tempdir."/backup/$uniq";

    if (!is_dir($tempdir)) {
        mkdir($tempdir, 0777, true);
    }

    if (!$archivefile->extract_to_pathname(new tgz_packer(), $tempdir)) {
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('error');
        echo $OUTPUT->notification(get_string('restoreerror', 'local_coursetemplates'));
        echo $OUTPUT->box_end();
        echo $OUTPUT->continue_button($url);
        echo $OUTPUT->footer();
        die;
    }

    // Transaction.
    $transaction = $DB->start_delegated_transaction();

    // Create new course.
    $categoryid = $data->category; // A categoryid.
    $userdoingtherestore = $USER->id; // E.g. 2 == admin.
    $newcourseid = restore_dbops::create_new_course('', '', $categoryid);

    // Restore backup into course.
    $controller = new restore_controller($uniq, $newcourseid,
        backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userdoingtherestore,
        backup::TARGET_NEW_COURSE );
    $controller->execute_precheck();
    $controller->execute_plan();

    // Commit.
    $transaction->allow_commit();

    // Update names.
    if ($newcourse = $DB->get_record('course', array('id' => $newcourseid))) {
        $newcourse->fullname = $data->fullname;
        $newcourse->shortname = $data->shortname;
        $newcourse->idnumber = $data->idnumber;
        $DB->update_record('course', $newcourse);
    }

    if (!empty($data->enrolme)) {
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $enrolplugin = enrol_get_plugin('manual');
        $params = array('enrol' => 'manual', 'courseid' => $newcourseid, 'status' => ENROL_INSTANCE_ENABLED);
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $USER->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
        }
    }

    // Cleanup temp file area.
    $fs = get_file_storage();
    $fs->delete_area_files($contextid, 'local_coursetemplates', 'temp');

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('success', 'local_coursetemplates'), 'notifysuccess');
    $label = get_string('gotonew', 'local_coursetemplates');
    echo $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $newcourseid)), $label);
    echo '<hr>';
    $mform->display();
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('usetemplate', 'local_coursetemplates'));

$formdata = new StdClass();
$formdata->category = $targetcategory;

$mform->set_data($formdata);
$mform->display();
echo $OUTPUT->footer();