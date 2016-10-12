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

require($CFG->libdir.'/formslib.php');
require_once('__other/elementgrid.php');

class TemplateDeployForm extends moodleform {

    public function definition() {
        global $DB, $CFG;

        $mform = $this->_form;

        include_once($CFG->dirroot.'/lib/coursecatlib.php');
        $mycatlist = coursecat::make_categories_list('moodle/course:create');
        $mycatlist = array('' => get_string('choose').'...') + $mycatlist;

        if (!empty($this->_customdata['profileme'])) {
            $mform->addElement('hidden', 'PROFILEME', 1);
            $mform->setType('PROFILEME', PARAM_BOOL);
        }

        $mform->addElement('text', 'fullname', get_string('fullname'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', get_string('erroremptyname', 'local_coursetemplates'), 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname'));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', get_string('erroremptyshortname', 'local_coursetemplates'), 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber'));
        $mform->setType('idnumber', PARAM_TEXT);

        if (empty($this->_customdata['categoryid'])) {
            $mform->addElement('select', 'category', get_string('category'), $mycatlist);
            $mform->setType('category', PARAM_INT);
            $label = get_string('errormissingcategory', 'local_coursetemplates');
            $mform->addRule('category', $label, 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'category', $this->_customdata['categoryid']);
            $mform->setType('category', PARAM_INT);
        }

        if (empty($this->_customdata['categoryid'])) {
            $mform->addElement('checkbox', 'enrolme', get_string('enrolmein', 'local_coursetemplates'));
            $mform->setDefault('enrolme', 1);
        } else {
            $mform->addElement('hidden', 'enrolme', true);
            $mform->setType('enrolme', PARAM_BOOL);
        }

        $grid = &$mform->addElement('elementgrid', 'grid', get_string('templateselection', 'local_coursetemplates'));
        $grid->setWidths(array('70%', '30%'));

        $config = get_config('local_coursetemplates');
        $templatecourses = $DB->get_records('course', array('category' => $config->templatecategory));

        foreach ($templatecourses as $course) {
            if (!$course->visible) {
                continue;
            }
            $row = array();
            $row[] = $mform->createElement('html', '<h3>'.format_string($course->fullname).'</h3>');
            $row[] = $mform->createElement('submit', 'submit_'.$course->id, get_string('deploy', 'local_coursetemplates'));
            $grid->addRow($row);
        }
    }

    public function validation($data, $files = array()) {
        global $DB;

        $errors = parent::validation($data, $files);

        if ($DB->get_record('course', array('shortname' => $data['shortname']))) {
            $errors['shortname'] = get_string('errorshortnameused', 'local_coursetemplates');
        }

        return $errors;
    }
}