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
 * TWOA report plugin library file for other non moodle functions.
 *
 * @package     gradereport_twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * gradereport_twoa_print_error_category_total_name_not_set function.
 *
 * Function call to print an error when the category total name isn't set or a course doens't have any grade categories/items.
 *
 * @param integer $courseid
 * @param string $langstringidentifier  The TWOA lang string identifier to get the message for the specific error.
 * @param moodle_url $redirectto        The url that the user will be redirected to when they click the continue button.
 */
function gradereport_twoa_print_error($courseid, $langstringidentifier = '', \moodle_url $redirectto) {
    // Just in case we need the grade lib. This is just to use the print_grade_page_head() function.
    require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/grade/lib.php');

    // Setup the place holders for the lang string.
    $a                      = new \stdClass();
    $a->missingcomponent    = get_string($langstringidentifier, 'gradereport_twoa');
    $a->contactwho          = get_string('courseconfigurationerror:errormessage/contactwho', 'gradereport_twoa');
    $a->contactoptions      = get_string('courseconfigurationerror:errormessage/contactoptions', 'gradereport_twoa');

    print_grade_page_head($courseid, 'report', 'twoa', '');

    // Output an error and return the user to the TWOA report.
    throw new moodle_exception('courseconfigurationerror', 'gradereport_twoa', $redirectto, $a);
}

/**
 * gradereport_twoa_get_enrolled_users function.
 *
 * Function to get all enrolled users for a course.
 *
 * @param int $courseid     The id of the course to get enrolled users for.
 * @return array            An array of userids of the users that are enrolled in the course.
 */
function gradereport_twoa_get_enrolled_users($courseid = 0) {
    global $DB;

    // An array to hold the user ids in.
    $userids = array();

    // Get the enrolment methods for this course.
    $courseenrolmentmethods = $DB->get_records('enrol', array('courseid' => $courseid));

    // Grab all the users from all enrolment methods in this course and just put their ids in the array.
    foreach ($courseenrolmentmethods as $courseenrolmentmethod) {
        // If there are enrolments for this enrolment method then add the user ids to the array of userids.
        if ($userenrolments = $DB->get_records('user_enrolments', array('enrolid' => $courseenrolmentmethod->id))) {
            // There are some enrolments so just add the user id.
            foreach ($userenrolments as $userenrolment) {
                $userids[] = $userenrolment->userid;
            }
        }
    }

    // Finished getting all the users associated to this course. Now return them.
    return $userids;
}
