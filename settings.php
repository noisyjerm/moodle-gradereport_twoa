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
 * Defines site settings for the TWOA Grade Export Report plugin.
 *
 * @package     gradereport_twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Put all the settings we want to display on this page in an array and output them at the end in a foreach statement.
    $twoasettings = array();

    // Use this as a heading. Set the message of the day lang string to something if you want to display nice welcome message.
    $twoasettings[] = new \admin_setting_heading(
        'gradereport_twoa/heading', '', get_string('settings:heading/messageoftheday', 'gradereport_twoa')
    );

    // Settings for controlling what data formats can be exported.
    $twoasettings[] = new \admin_setting_heading(
        'gradereport_twoa/excluded_dataformats_heading',
        get_string('settings:excluded_dataformats/heading', 'gradereport_twoa'),
        get_string('settings:excluded_dataformats/heading_description', 'gradereport_twoa')
    );

    // Get the dataformats that can be used to export as.
    $dataformats = \core_plugin_manager::instance()->get_plugins_of_type('dataformat');

    // Keep our own record of the dataformats to be fed into our configmulticheckbox setting object.
    $dataformats = array_keys($dataformats);

    // Make the array of data formats key and value be the name of the dataformat i.e. $dataformat['csv'] = 'csv'.
    foreach ($dataformats as $index => $dataformat) {
        $dataformats[$dataformat] = $dataformat;

        // Unset the numerically indexed one of this.
        unset($dataformats[$index]);
    }

    // Put out a bunch of options for data formats that are available.
    $twoasettings[] = new \admin_setting_configmulticheckbox(
        'gradereport_twoa/excluded_dataformats',
        get_string('settings:excluded_dataformats/checkbox_heading', 'gradereport_twoa'),
        get_string('settings:excluded_dataformats/checkbox_description', 'gradereport_twoa'),
        array(),
        $dataformats
    );

    // Columns that can be optionally included.
    $optionalcols = [
        'fullname' => get_string('fullname')
    ];

    // Put out a bunch of options for columns that can be added.
    $twoasettings[] = new \admin_setting_configmulticheckbox(
        'gradereport_twoa/optional_columns',
        get_string('settings:optional_columns/checkbox_heading', 'gradereport_twoa'),
        get_string('settings:optional_columns/checkbox_description', 'gradereport_twoa'),
        array(),
        $optionalcols
    );

    // Now add the settings for this plugin to the settings object.
    foreach ($twoasettings as $twoasetting) {
        $settings->add($twoasetting);
    }

}
$ADMIN->add("grades", new admin_externalpage(
    'gradereport_twoa',
    get_string('pluginname', 'gradereport_twoa'),
    new moodle_url("/grade/report/twoa/report.php")
));

