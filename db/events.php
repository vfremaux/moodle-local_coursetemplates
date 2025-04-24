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
 * Event observers definition.
 *
 * @package     local_coursetemplates
 * @author      Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright   2017 Valery Fremaux (activeprolearn.com)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\course_created',
        'callback'    => 'local_coursetemplates_observer::on_course_created',
        'includefile' => '/local/coursetemplates/observers.php',
        'internal'    => true,
        'priority'    => 9999,
    ],
    [
        'eventname'   => '\core\event\course_restored',
        'callback'    => 'local_coursetemplates_observer::on_course_restored',
        'includefile' => '/local/coursetemplates/observers.php',
        'internal'    => true,
        'priority'    => 9999,
    ],

];
