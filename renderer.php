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
 * Main renderer.
 *
 * @package    local_coursetemplates
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  Valery Fremaux (www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Renderer class.
 */
class local_coursetemplates_renderer extends plugin_renderer_base {

    /**
     * prints links to choose where to go after deployment is complete.
     * @param int $newcourseid
     * @param int $templatecourseid
     */
    public function postdeploychoice($newcourseid, $templatecourseid) {

        $template = new StdClass();

        $template->newcourseurl = new moodle_url('/course/view.php', ['id' => $newcourseid]);
        $template->templatecourseurl = new moodle_url('/course/view.php', ['id' => $templatecourseid]);
        $template->templatelisturl = new moodle_url('/local/coursetemplates/index.php');

        return $this->output->render_from_template('local_coursetemplates', $template);
    }
}
