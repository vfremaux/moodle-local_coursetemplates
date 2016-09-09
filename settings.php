<?php
// This file is NOT part of Moodle - http://moodle.org/
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

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_coursetemplates
 * @category   local
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// settings default init
if (is_dir($CFG->dirroot.'/local/adminsettings')) {
    // Integration driven code 
    require_once($CFG->dirroot.'/local/adminsettings/lib.php');
    list($hasconfig, $hassiteconfig, $capability) = local_adminsettings_access();
} else {
    // Standard Moodle code
    $capability = 'moodle/site:config';
    $hasconfig = $hassiteconfig = has_capability($capability, context_system::instance());
}

if ($hassiteconfig) { // needs this condition or there is error on login page
    $settings = new admin_settingpage('local_coursetemplates', get_string('pluginname', 'local_coursetemplates'));
    $ADMIN->add('localplugins', $settings);

    $yesnooptions[0] = get_string('no');
    $yesnooptions[1] = get_string('yes');

    // post 2.5
    include_once($CFG->dirroot.'/lib/coursecatlib.php');
    $catlist = coursecat::make_categories_list();

    $settings->add(new admin_setting_configcheckbox('local_coursetemplates/enabled', get_string('configenabled', 'local_coursetemplates'),
                   get_string('configenableddesc', 'local_coursetemplates'), 1));

    $settings->add(new admin_setting_configselect('local_coursetemplates/templatecategory', get_string('configtemplatecategory','local_coursetemplates'),
        get_string('configtemplatecategorydesc','local_coursetemplates'),'',$catlist));

    $config = get_config('local_coursetemplates');
    if (!empty($config->enabled)) {
        $templateurl = new moodle_url('/local/coursetemplates/index.php');
        $menuaccess = new admin_externalpage('local_coursetemplates_access', get_string('createfromtemplate', 'local_coursetemplates'), $templateurl);
        $ADMIN->add('courses', $menuaccess);
    }

    $settings->add(new admin_setting_configcheckbox('local_coursetemplates/autoenrolcreator', get_string('configautoenrolcreator', 'local_coursetemplates'),
                   get_string('configautoenrolcreatordesc', 'local_coursetemplates'), 1));
}

