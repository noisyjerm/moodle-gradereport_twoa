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
 * @copyright   2023 Te Wānanga o Aotearoa
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// Include the locallib for this plugin.
require_login();

$status    = optional_param('status', 100, PARAM_INT);
$download  = optional_param('download', '', PARAM_ALPHA);
$perpage   = optional_param('perpage', 100, PARAM_INT);
$startdate = optional_param('startdate', get_config('gradereport_twoa', 'report_fromdate'), PARAM_INT);
$enddate   = optional_param('enddate', time(), PARAM_INT);
$setstatuses = optional_param('setstatuses', null, PARAM_INT);
$setstatus = optional_param_array('setstatus', null, PARAM_INT);

// Set the current page url.
$currentpageurl = new moodle_url('/grade/report/twoa/report.php');

// Set where the user gets redirected to on error and when downloading.
$redirectto = new \moodle_url('/grade/report/twoa/report.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($currentpageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'gradereport_twoa'));
$PAGE->set_heading(get_string('pluginname', 'gradereport_twoa'));
$PAGE->requires->js_call_amd('gradereport_twoa/bulk', 'init');

// This is the normal requirements.
require_capability('gradereport/singleview:view', $context);
require_capability('moodle/grade:viewall', $context);
require_capability('moodle/grade:edit', $context);

// Update the statuses.
if (isset($setstatuses)) {
    foreach ($setstatus as $gradeid) {
        $graderecord = $DB->get_record('gradereport_twoa', ['gradeid' => $gradeid]);
        if ($graderecord) {
            $graderecord->status = $setstatuses;
            $graderecord->timemodified = time();
            $success = $DB->update_record('gradereport_twoa', $graderecord);
        } else {
            $graderecord = (object)[
                'gradeid' => $gradeid,
                'status' => $setstatuses,
                'timemodified' => time(),
            ];
            $success = $DB->insert_record('gradereport_twoa', $graderecord, false);
        }
    }

    // Log this change.
    if ($setstatus !== null) {
        $event = \gradereport_twoa\event\admin_report_statuschanged::create(
            array(
                'context' => $context,
                'courseid' => 0,
                'other' => [
                    'items' => implode(', ', array_values($setstatus)),
                    'status' => get_string('status' . $setstatuses, 'gradereport_twoa'),
                ],
            )
        );
        $event->trigger();
    }

}

// Set the name of the report. This will be the name used for the exported file and the id of the table element.
$reportname = get_string('pluginname', 'gradereport_twoa') . ' - ' .
    get_string('adminreport', 'gradereport_twoa') . ' - ' .
    get_string('status' . $status, 'gradereport_twoa');

// Provide some options to filter the returned data set.
$filters = new \gradereport_twoa\filter_form();
$filters->set_data(['status' => $status, 'filterstartdate' => $startdate, 'filterenddate' => 0]);
if ($filters->is_cancelled()) {
    $redir = $PAGE->url;
    $redir->remove_params(['startdate', 'enddate']);
    redirect($redir);
}
if ($dates = $filters->get_data()) {
    if ($dates->filterstartdate) {
        $startdate = $dates->filterstartdate;
    }

    if ($dates->filterenddate) {
        $enddate = $dates->filterenddate;
    }
}

// Set the base url.
$reportbaseurl = new \moodle_url(
    "{$CFG->wwwroot}/grade/report/twoa/report.php",
    array(
        'status' => $status,
        'startdate' => $startdate,
        'enddate' => $enddate
    )
);

// Now let's get the report.
$report = new \gradereport_twoa\report_table('gradeadminreport', $reportbaseurl, [
    'status' => $status,
    'startdate' => $startdate,
    'enddate' => $enddate,
]);

if ($download) {
    // Tell the report object that we are downloading something.
    $report->is_downloading($download, $reportname, $reportname);

    // Now download the report.
    $report->out(25, false);

    // And then redirect to the main report page.
    redirect($currentpageurl);
}

// And send things to the screen.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adminreport', 'gradereport_twoa'));
$filters->display();
// Show the report here.
$report->out($perpage, false);

$event = \gradereport_twoa\event\admin_report_viewed::create(
    array(
        'context'       => $context,
        'courseid'      => 0,
    )
);
$event->trigger();

echo $OUTPUT->footer();
