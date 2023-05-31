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
 * Strings for component 'gradereport_twoa', language 'en'.
 *
 * @package     gradereport_twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Strings for the plugin name.
$string['pluginname'] = 'TWOA Grade Export Report';

// Strings to give to things that trigger events.
$string['eventgradereportviewed']   = 'Grade TWOA report viewed.';
$string['eventgradedataretrieved']  = 'Grade TWOA report data retrieved.';
$string['eventadminreportviewed']   = 'Grade TWOA admin report viewed.';
$string['eventadminreportitemchanged']   = 'Grade TWOA admin report item status changed.';

// String that joins the error messages for no grade items for a course and missing grade category total name.
$string['courseconfigurationerror'] = '{$a->missingcomponent}. Please contact {$a->contactwho} {$a->contactoptions}'.
                                       ' to have this resolved.';

// String to tell the user that the report for the selected grade item is missing it's name i.e. grade category total name.
$string['gradecategorytotalname:error/missing'] =
    'The grade category total name is missing or empty and is required for the export';

// String to tell the user that the course they are trying to get a TWOA grade export for doesn't have any grade items setup.
$string['gradecategoryitems:error/missing'] = 'There are no grade categories configured for this course';

// String to tell the user that there are no users enrolled in the course.
$string['courseconfigurationerror:noenrolledusers'] = 'There are no users enrolled in this course';

// String for the default option in the drop down form for choosing a grade item.
$string['selectdefaultoptiontext:categorygradeitem'] = 'Select category total name...';

// String for the default report head title. This appears on the main index page (with no itemid query string).
$string['gradereportindex:defaultreporthead'] = 'Select a category to get grades for';

// The string to be displayed when grade items don't have a category total name.
$string['gradeitem:missingcode'] = 'missing code';

// The strings to be displayed for courses that have grade items that don't have a category total name.
$string['courseconfigurationerror:errormessage/contactwho']       = 'the Hangarau Service Desk';
$string['courseconfigurationerror:errormessage/contactoptions']   = '<a href="mailto:hangarau@twoa.ac.nz">by email</a>'.
                                                                   ' or phone 0800 808 789';
$string['categorysetupok'] = 'This grade category / konae is able to be automatically transfered to 101.';
$string['categorysetupnotok'] = 'This grade category / konae is not able to be automatically transfered to 101.';

// Strings for the settings page.

// String to display a message at the top of the page.
$string['settings:heading/messageoftheday']                 = '';

// Strings for the main 'course' report.
$string['settings:course_report/heading']             = 'Course report settings';
$string['settings:course_report/heading_description'] =
    'Configure how the grade report will display and export.';

// Strings for the allowed report export formats (config check box).
$string['settings:excluded_dataformats/checkbox_heading']       = 'Excluded data formats';
$string['settings:excluded_dataformats/checkbox_description']   =
    'Select all of the formats that the reports can NOT be exported as.';

// Strings for the optional columns to show (config check box).
$string['settings:optional_columns/checkbox_heading']       = 'Additional columns';
$string['settings:optional_columns/checkbox_description']   =
    'Select any extra columns you would like included.';

// Strings for Admin report settings.
$string['settings:adminreport/adminreport_description']     = 'Options for the Admin report';
$string['settings:adminreport/fromdate_heading']            = 'Initial from date';
$string['settings:adminreport/fromdate_description']        =
    'Set the date that will be initially selected for the \'From\' filter.<br>
     Items graded before this date will not appear on the report.
     This saves seeing a heap of redundant \'missing\' grade results.';

// Strings for the API output.
$string['settings:api_output/heading']             = 'API settings';
$string['settings:api_output/heading_description'] = 'Configure what will be included in the API output.';
$string['settings:api_output/classes']             = 'Classes to include';
$string['settings:api_output/classes_description'] = 'Enter in a comma separated list of class ids (course idnumbers) for classes that
                                                      are to be included in the API output, all other classes (courses) will be ignored. <br>
                                                      Only numbers, commas and spaces allowed. No results will be returned if format is incorrect.<br>
                                                      If blank, all classes will be included.';

$string['colheader_email'] = "Email";
$string['colheader_course'] = "Course";
$string['colheader_grade'] = "Grade";
$string['colheader_dategraded'] = "Date Graded";
$string['colheader_fullname'] = "Tauira's Fullname";
$string['colheader_action'] = "Ready for transfer";
$string['warning_modified'] = 'This grade has been modified since being transferred to 101.';

$string['twoa:view'] = "View TWOA grade export report";

$string['status-2'] = 'Missing';
$string['status-1'] = 'Error';
$string['status0'] = 'Not ready';
$string['status1'] = 'Ready';
$string['status2'] = 'Retrieved';
$string['status3'] = 'Modified';
$string['status100'] = 'All';

// Strings for admin report.
$string['colheader_tauiraid'] = "Tauira ID";
$string['colheader_progcode'] = "Programme code";
$string['colheader_coursecode'] = "Konae code";
$string['colheader_classid'] = "Class ID";
$string['colheader_eventdate'] = "Date Graded";
$string['colheader_status'] = "Status";
$string['adminreport'] = 'Admin report';
$string['selectdefaultoptiontext:transferstatus'] = 'Select transfer status...';
$string['transfergradeitemschangestatus'] = 'Change the status of selected grades to: ';
