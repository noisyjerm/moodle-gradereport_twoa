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
 * A file to test things i.e. logic for the TWOA grade report plugin.
 *
 * @package     gradereport
 * @subpackage  twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(CLI_SCRIPT, true);

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');

// The id of the course to get stuff for.
$courseid = 6;

// The grade item id.
$gradeitemid = 13;

$x = new \gradereport_twoa\grade_info($courseid, $gradeitemid);

$gradedetails = $x->get_user_grades();

foreach ($gradedetails->grades as $grade) {
    print_object($grade);die;
}
