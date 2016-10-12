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

class local_coursetemplates_renderer extends plugin_renderer_base {

    public function templatecoursebox($course) {

        $class = (!$course->visible) ? ' coursetemplate-shadow' : '';

        $str = '';

        $str .= '<div class="courselist coursebox '.$class.'">';
        $str .= '<table class="course" width="100%">';
        $button = '<input name="deploy"
                          type="submit"
                          value="'.get_string('deploy', 'local_coursetemplates').'"
                          onclick="coursetemplate_submit();" />';
        $submitbutton = '<div class="coursetemplate-submit">'.$button.'</div>';
        $title = '<h3>'.format_string($course->fullname).$submitbutton.'</h3>';
        $str .= '<tr><td colspan="2" class="coursetemplates-coursename">'.$title.'</td></tr>';
        $str .= '<tr>';
        $str .= '<td width="20%" class="coursetemplates-coursepicture"></td>';
        $str .= '<td width="80%" class="coursetemplates-coursedesc">'.$course->summary.'</td>';
        $str .= '</tr>';
        $str .= '</table>';
        $str .= '</div>';

        return $str;
    }

    /**
     * provides form elements for deployment.
     * will pick all the target categories the user can create courses in
     */
    public function deployform() {
        global $CFG;

        // Post 2.5.
        include_once($CFG->dirroot.'/lib/coursecatlib.php');
        $mycatlist = coursecat::make_categories_list('moodle/course:create');

        $str = '';
        $str .= '<table width="100%">';

        $str .= '<tr>';
        $str .= '<td class="column1" width="40%">';
        $str .= get_string('fullname');
        $str .= '</td>';
        $str .= '<td class="column2" width="60%">';
        $str .= '<input type="text" name="fullname" size="80" />';
        $str .= '</td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '<td class="column1" width="40%">';
        $str .= get_string('shortname');
        $str .= '</td>';
        $str .= '<td class="column2">';
        $str .= '<input type="text" name="shortname" size="16" />';
        $str .= '</td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '<td class="column1" width="40%">';
        $str .= get_string('idnumber');
        $str .= '</td>';
        $str .= '<td class="column2">';
        $str .= '<input type="text" name="idnumber" size="16" />';
        $str .= '</td>';
        $str .= '</tr>';

        $str .= '<tr>';
        $str .= '<td class="column1" width="40%">';
        $str .= get_string('targetcategory', 'local_coursetemplates');
        $str .= '</td>';
        $str .= '<td class="column1">';
        $str .= html_writer::select($mycatlist, 'targetcategory');
        $str .= '</td>';
        $str .= '</tr>';

        $str .= '</table>';

        return $str;
    }

    public function postdeploychoice($newcourseid, $templatecourseid) {

        $str = '';

        $newcourseurl = new moodle_url('/course/view.php', array('id' => $newvcouseid));
        $templatecourseurl = new moodle_url('/course/view.php', array('id' => $templatevcouseid));
        $templatelisturl = new moodle_url('/local/coursetemplates/index.php');

        $str .= '<a href="'.$newcourseurl.'" >'.get_string('gotonew', 'local_coursetemplates').'</a>';
        $str .= '- <a href="'.$templatecourseurl.'" >'.get_string('gototemplate', 'local_coursetemplates').'</a>';
        $str .= '- <a href="'.$templatelisturl.'" >'.get_string('gototemplatelist', 'local_coursetemplates').'</a>';

        return $str;
    }
}