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
 * This script launches the course operations from command line CLI script calls.
 *
 * @package     local_coursetemplates
 * @author      Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright   2017 Valery Fremaux (activeprolearn.com)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * Not compatible with VMoodle meta handling.
 * phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
 */

define('CLI_SCRIPT', true);
global $clivmoodleprecheck;

$clivmoodleprecheck = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions.

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    [
        'verbose'           => false,
        'help'              => false,
        'clean'             => false,
        'host'              => false,
    ],
    [
        'h' => 'help',
        'v' => 'verbose',
        'c' => 'clean',
        'H' => 'host',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(cli_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Template category mass backup for templating.

Backups all courses present in template dedicated category and subcategories to ensure
a course scope backup without user data is available for deployment.

Options:
    -v, --verbose       Provides more output
    -h, --help          Print out this help
    -c, --clean         Clean the area before backuping (delete old instances)
    -H, --host          Set the host (physical or virtual) to operate on.

\n"; // TODO: localize - to be translated later when everything is finished.

    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // Mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
echo('Config check : playing for '.$CFG->wwwroot."\n");

// Here can real processing start.
require_once($CFG->dirroot.'/local/coursetemplates/lib.php');

// Fakes admin login for operations.
global $USER;
$USER = get_admin();

$log = '';

if ($options['verbose']) {
    $log .= "Getting template course entries\n";
}
$courses = local_coursetemplates_get_courses();
if ($options['verbose']) {
    $log .= "Found ".count($courses)." to process\n";
}

if ($courses) {
    foreach ($courses as $cid => $course) {
        if (!empty($options['verbose'])) {
            $log .= "Backuping course $cid : $course->fullname\n";
        }
        local_coursetemplates_backup_for_template($cid, $options, $log);
    }
}

if (!empty($options['logroot'])) {
    if ($log = fopen($logroot.'/'.$options['host'].'_backup_templates.log', 'w')) {
        fputs($log, $log);
        fclose($log);
    }
}
if (!empty($options['verbose'])) {
    echo $log;
}

echo "Done.\n";
