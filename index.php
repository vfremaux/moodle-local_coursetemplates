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
 * Template index page.
 *
 * @package    local_coursetemplates
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  Valery Fremaux (www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/local/coursetemplates/deploy_form.php');
require_once($CFG->dirroot.'/local/coursetemplates/lib.php');
require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');
require_once($CFG->libdir."/filestorage/mbz_packer.php");

$url = new moodle_url('/local/coursetemplates/index.php');

$PAGE->set_url($url);

$context = context_system::instance();
$PAGE->set_context($context);

// Security.
require_login();

$renderer = $PAGE->get_renderer('local_coursetemplates');

$PAGE->set_title(get_string('pluginname', 'local_coursetemplates'));
$PAGE->set_heading(get_string('pluginname', 'local_coursetemplates'));
$PAGE->navbar->add(get_string('pluginname', 'local_coursetemplates'));

$options = [];

if (optional_param('profileme', 0, PARAM_BOOL)) {
    $customdata['profileme'] = 1;
}

$config = get_config('local_coursetemplates');
$customdata['templatecourses'] = $DB->get_records('course', ['category' => $config->templatecategory]);
$mycatlist = core_course_category::make_categories_list('moodle/course:create');
$mycatlist = ['' => get_string('choose').'...'] + $mycatlist;
$customdata['mycatlist'] = $mycatlist;

$mform = new TemplateDeployForm($url, $customdata);

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

    // Check we have permissions to do it.
    $categorycontext = context_coursecat::instance($data->category);
    if (!has_capability('moodle/course:create', $categorycontext)) {
        throw new moodle_exception('errornocategoryaccess', 'local_coursetemplates', '', $url);
    }

    if (empty($templatecourseid)) {
        throw new moodle_exception('errorbadtemplateid', 'local_coursetemplates', '', $url);
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

    $newcourseid = coursetemplates_restore_template($archivefile, $data);
    if ($newcourseid === false) {
        die;
    }

    if (!empty($data->enrolme)) {
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $enrolplugin = enrol_get_plugin('manual');
        $params = ['enrol' => 'manual', 'courseid' => $newcourseid, 'status' => ENROL_INSTANCE_ENABLED];
        if ($enrols = $DB->get_records('enrol', $params, 'sortorder ASC')) {
            $enrol = reset($enrols);
            $enrolplugin->enrol_user($enrol, $USER->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('success', 'local_coursetemplates'), 'notifysuccess');
    $label = get_string('gotonew', 'local_coursetemplates');
    echo $OUTPUT->single_button(new moodle_url('/course/view.php', ['id' => $newcourseid]), $label);
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
