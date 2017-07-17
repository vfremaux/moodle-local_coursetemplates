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

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions.

// Ensure options are blanck.
unset($options);

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'             => false,
        'logroot'          => true,
        'verbose'          => true,
        'fullstop'         => false,
    ),
    array(
        'h' => 'help',
        'l' => 'logroot',
        'v' => 'verbose',
        'x' => 'fullstop',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
File driven course operations

    Options:
    -h, --help              Print out this help
    -l, --logroot           Root for the log output
    -x, --fullstop          Stops the processing on first error
    -v, --verbose           More output

"; // TODO: localize - to be translated later when everything is finished.

    echo $help;
    die;
}

$logroot = '';
if (!empty($options['logroot'])) {
    $logroot = '--logroot='.$options['logroot'];
}

$verbose = '';
if (!empty($options['verbose'])) {
    $verbose = '--verbose='.$options['verbose'];
}

if (!empty($options['file'])) {
    die ("A file must be provides \n");
}
$file = '--file='.$options['file'];

$allhosts = $DB->get_records('local_vmoodle', array('enabled' => 1));

// Start updating.
// Linux only implementation.

echo "Starting bulk backuping for templates....";

$i = 1;
foreach ($allhosts as $h) {
    $workercmd = "php {$CFG->dirroot}/local/coursetemplates/cli/backup_templates.php --host=\"{$h->vhostname}\" ";
    $workercmd .= " {$verbose} {$logroot} ";

    mtrace("Executing $workercmd\n######################################################\n");
    $output = array();
    exec($workercmd, $output, $return);

    if (!empty($output)) {
        echo implode("\n", $output);
    }
    echo "\n";

    if ($return) {
        if (!empty($option['fullstop'])) {
            die("Worker ended with error\n");
        } else {
            mtrace("Worker ended with error\n");
        }
    }
}

echo "All done.";
