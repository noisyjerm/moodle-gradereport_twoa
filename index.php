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
 * Displays the TWOA report for grade items.
 *
 * @package     gradereport_twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/lib/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');

// Include the locallib for this plugin.
require_once(dirname(__FILE__).'/locallib.php');

$courseid = required_param('id', PARAM_INT);

$itemid = optional_param('itemid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 100, PARAM_INT);

$download = optional_param('download', '', PARAM_ALPHA);

// Set the components used to construct the report table.

// Set the sql components for set_sql in table_sql class.
$sqlfields  = 'gg.id, u.id userid, u.firstname, u.lastname, u.email';
$sqlfrom    = '{grade_grades} gg JOIN {user} u ON gg.userid = u.id';
$sqlwhere   = 'gg.itemid = :itemid AND gg.finalgrade IS NOT NULL';
$sqlparams  = array('itemid' => $itemid);

// Define the list of columns to show.
$columns = array('email', 'course', 'grade', 'dategraded');

// Define the titles of columns to show in header.
$headers = array('Email', 'Course', 'Grade', 'Date Graded');

// Define the fields that will not be sorted.
$nosorting = array('email');

$courseparams = array('id' => $courseid);

// The base url for the report object.
$reportbaseurl = new \moodle_url(
    "{$CFG->wwwroot}/grade/report/twoa/index.php",
    array('id' => $courseid, 'itemid' => $itemid)
);

// Setup the current page url.
$pageparams = array(
    'id'                => $courseid,
    'page'              => $page,
    'perpage'           => $perpage,
    'itemid'            => $itemid
);

// Set the current page url.
$currentpageurl = new moodle_url('/grade/report/twoa/index.php', $pageparams);

// Set where the user gets redirected to on error and when downloading.
$redirectto = new \moodle_url('/grade/report/twoa/index.php', array('id' => $courseid));

$PAGE->set_url($currentpageurl);
$PAGE->set_pagelayout('incourse');

// Ensure that the course exists.
if (!$course = $DB->get_record('course', $courseparams)) {
    // Where did the user come from?
    if (!empty($lastcourse = $USER->grade_last_report)) {
        // Set the array index to the end.
        end($lastcourse);

        // Now get the key of the array at this index and use that to redirect.
        $lastcourseid = key($lastcourse);

        // Generate the url to redirect to.
        $redirectto = new \moodle_url('/grade/report/twoa/index.php', array('id' => $lastcourseid));

        // We have found where they came from so lets send them back.
        throw new moodle_exception('nocourseid', 'gradereport_twoa', $redirectto);
    }

    // We can't find where they came from so just send them where ever this goes.
    throw new moodle_exception('nocourseid');
}

if ($course->id === get_site()->id) {
    throw new moodle_exception('sitecourse');
}

// Check that the grade item exists. Throw an error if it doesn't.
if (!empty($itemid)) {
    if (!$gradeitem = $DB->get_record('grade_items', array('id' => $itemid, 'courseid' => $courseid, 'itemtype' => 'category'))) {
        throw new moodle_exception('nogradeitem', 'gradereport_twoa', $redirectto);
    }
}

require_login($course);

$context = context_course::instance($course->id);

// This is the normal requirements.
require_capability('gradereport/singleview:view', $context);
require_capability('moodle/grade:viewall', $context);
require_capability('moodle/grade:edit', $context);

$gpr = new grade_plugin_return(
    array('type' => 'report', 'plugin' => 'twoa', 'courseid' => $courseid)
);

// Set the name of the grade item if it isn't set aleady.
$gradeitemname = empty($gradeitem->itemname) ? 'namenotset' : $gradeitem->itemname;

// Set the name of the report. This will be the name used for the exported file and the id of the table element.
$reportname = "TWOA_GradeExport_{$course->shortname}_{$gradeitemname}";

if ($download) {
    // If the grade item category name isn't set then lets get out of here!
    if (empty($gradeitem->itemname)) {
        gradereport_twoa_print_error($course->id, 'gradecategorytotalname:error/missing', $redirectto);
    }

    // Make our report object.
    $report = new \gradereport_twoa\table(
        $reportname, $courseid, $itemid, $sqlfields, $sqlfrom, $sqlwhere, $sqlparams, $columns, $headers, $nosorting, $reportbaseurl
    );

    // Tell the report object that we are downloading something.
    $report->is_downloading($download, $reportname, $reportname);

    // Now download the report.
    $report->out(25, true);

    // And then redirect to the main report page.
    redirect($currentpageurl);
}

// Last selected report session tracking.
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'twoa';

// First make sure we have proper final grades.
grade_regrade_final_grades_if_required($course);

if (!empty($itemid)) {
    // If the grade items name isn't set then print an error.
    if (empty($gradeitem->itemname)) {
        gradereport_twoa_print_error($course->id, 'gradecategorytotalname:error/missing', $redirectto);
    }

    $report = new \gradereport_twoa\table(
        $reportname, $courseid, $itemid, $sqlfields, $sqlfrom, $sqlwhere, $sqlparams, $columns, $headers, $nosorting, $reportbaseurl
    );
}

$currentpage = new moodle_url('/grade/report/twoa/index.php', $pageparams);

// Make sure things display correctly with the right names.
$PAGE->set_pagelayout('report');

// Initally set the page head title to assume that the user hasn't selected a category to get a report for.
$reportpagehead = get_string('gradereportindex:defaultreporthead', 'gradereport_twoa');

// Todo: Confirm with TWOA about this i.e. error message etc.
// If there are no enrolled users then show an error.
if (empty(gradereport_twoa_get_enrolled_users($course->id))) {
    // Generate a link to redirect to the course they came from.
    gradereport_twoa_print_error($course->id, 'courseconfigurationerror:noenrolledusers', $redirectto);
}

// Get the options that should be put into the drop down menu sorted by their sort order.
$coursegradeitems = $DB->get_records('grade_items', array('itemtype' => 'category', 'courseid' => $courseid), 'sortorder');

// If there are no grade items for this course then throw a nice message.
if (empty($coursegradeitems)) {
    gradereport_twoa_print_error($course->id, 'gradecategoryitems:error/missing', $redirectto);
}

// Prepare the options for the drop down. Also set the grade report head string if the itemid isn't empty.
foreach ($coursegradeitems as $id => $coursegradeitem) {
    // Get the grade category name using the iteminstance in grade_items to map to an id in the grade_categories table. This is
    // ok to assume that a grade category has a name because it is a required field.
    $gradecategory = $DB->get_record('grade_categories', array('id' => $coursegradeitem->iteminstance));

    // Set the grade report name.
    if (!empty($itemid)) {
        if ($itemid == $coursegradeitem->id) {
            // Use the fullname from the grade category associated to the itemid for this report.
            $reportpagehead = "Grade export: {$gradecategory->fullname} ({$course->shortname} {$gradeitemname})";
        }
    }

    // Does this grade item have a name? If not we need a default or some sort of notification for the teacher.
    if (empty($coursegradeitem->itemname)) {
        $coursegradeitem->itemname = get_string('gradeitem:missingcode', 'gradereport_twoa');
    }

    $selectoptions[$id] = "{$gradecategory->fullname} ({$coursegradeitem->itemname})";
}

// Set the text for the default select option from the lang string.
$defaultselectoption = array('' => get_string('selectdefaultoptiontext:categorygradeitem', 'gradereport_twoa'));

// Lets make another url for the select to use with just course id as a query string.
$singleselecturl = new \moodle_url("{$CFG->wwwroot}/grade/report/twoa/index.php", array('id' => $courseid));

// Make a cool select that reloads automatically.
$select = new \single_select($singleselecturl, 'itemid', $selectoptions, '', $defaultselectoption);

print_grade_page_head($course->id, 'report', 'twoa', $reportpagehead);

// Setup the parameters required to use the single select class.
$selectoptions = array();

// Output the cool auto reloading select!
echo \html_writer::div(
    \html_writer::div($OUTPUT->render($select), 'selectitems'), 'reporttable'
);

// Only output the report if there is an itemid.
if (!empty($itemid)) {
    // Do the report here.
    $report->out(25, true);
}

$event = \gradereport_twoa\event\grade_report_viewed::create(
    array(
        'context'       => $context,
        'courseid'      => $courseid,
        'relateduserid' => $USER->id,
    )
);

$event->trigger();

echo $OUTPUT->footer();
