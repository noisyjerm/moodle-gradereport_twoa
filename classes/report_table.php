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

namespace gradereport_twoa;

defined('MOODLE_INTERNAL') || die();

// Load tablelib because this is not autoloaded.
require_once("{$CFG->libdir}/tablelib.php");
use \html_writer;

/**
 * Class gradereport_twoa_table.
 *
 * @package     gradereport_twoa
 * @copyright   2023 Te Wānanga o Aotearoa
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \table_sql {

    /**
     * table constructor.
     *
     * Constructs the TWOA report table with the given parameters.
     *
     * @param string $uniqueid         Some sort of unique id for this table object.
     * @param \moodle_url $baseurl     The url including any query strings. This will help generate urls for paging what is output.
     * @param array $params            Any parameters that need to be passed to the sql query.
     */
    public function __construct($uniqueid, \moodle_url $baseurl, $params = []) {
        global $CFG;

        // Set the id of this table.
        parent::__construct($uniqueid);

        $this->define_baseurl($baseurl);

        // Set the columns as per the constructor.
        $columns = array('tauiraid', 'progcode', 'coursecode', 'classid', 'eventdate', 'grade', 'status');
        $this->define_columns($columns);

        // Set the column headers as per the constructor.
        foreach ($columns as $column) {
            $headers[] = get_string('colheader_' . $column, 'gradereport_twoa');
        }
        $this->define_headers($headers);

        // Sorting and not sorting.
        $this->sortable(true, 'eventdate', SORT_DESC);
        $this->no_sorting('grade');

        // Is this table downloadable?
        $this->is_downloadable(true);

        // Is this table collapsible?
        $this->collapsible(true);

        // Lets make the table download button show up where it is defined in the constructor.
        $this->show_download_buttons_at(array(TABLE_P_BOTTOM));

        // Get the formats that this report can not be exported as from the plugin settings.
        if ($excludedformatexports = get_config('gradereport_twoa', 'excluded_dataformats')) {
            $this->excludedexportformats = explode(',', $excludedformatexports);
        }

        // Set the sql for this table.
        $sqlfields  = 'gg.id, gg.timemodified EventDate, gg.finalgrade Grade, cc.idnumber ProgCode,
                       c.id courseid, c.idnumber ClassID, gi.id itemid, gi.idnumber CourseCode, gi.grademax, gi.scaleid,
                       s.scale, gt.status, gt.timemodified, u.email TauiraID';
        $sqlfrom    = '{grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  LEFT JOIN {gradereport_twoa} gt ON gt.gradeid = gg.id
                  LEFT JOIN {scale} s ON s.id = gi.scaleid
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {user} u ON u.id = gg.userid';
        // Build the WHERE.
        $sqlwhere = "gi.itemtype = 'category'
                     AND gg.timemodified >= ?
                     AND gg.timemodified <= ?";

        if ($params['status'] == \gradereport_twoa\transfergrade::STATUS_MISSING) {
            $sqlwhere .= ' AND gt.status IS NULL';
        } else if ($params['status'] != 100) {
            $sqlwhere .= ' AND gt.status = ?';
        }

        $sqlparams[] = !isset($params['startdate']) ? get_config('gradereport_twoa', 'report_fromdate') : $params['startdate'];
        $sqlparams[] = !isset($params['enddate']) ? time() : $params['enddate'];
        $sqlparams[] = $params['status'];

        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
    }

    /** @var array $excludedexportformats   An array of formats to exclude from the list of available formats to download as. */
    private $excludedexportformats = array();

    /**
     * Put the table in a form so we can update items.
     * @return void
     */
    public function wrap_html_start() {
        echo html_writer::start_tag('form', [
            'name' => 'updateselected',
            'class' => 'updateselected',
            'action' => $this->baseurl,
            'method' => 'post'
        ]);
    }

    /**
     * Add the select and close the form.
     * @return void
     * @throws \coding_exception
     */
    public function wrap_html_finish() {
        // Get options for status changer.
        echo html_writer::span(get_string('transfergradeitemschangestatus', 'gradereport_twoa'));
        for ($i = \gradereport_twoa\transfergrade::STATUS_ERROR; $i <= \gradereport_twoa\transfergrade::STATUS_MODIFIED; $i++) {
            $statuses[$i] = get_string('status' . $i, 'gradereport_twoa');
        }
        echo html_writer::select($statuses, 'setstatuses');
        echo html_writer::end_tag('form');
    }

    /**
     * Overrides parent function to remove unwanted rows.
     * Take the data returned from the db_query and go through all the rows
     * processing each col using either col_{columnname} method or other_cols
     * method or if other_cols returns NULL then put the data straight into the
     * table.
     * @return void
     */
    public function build_table() {

        if ($this->rawdata instanceof \Traversable && !$this->rawdata->valid()) {
            return;
        }
        if (!$this->rawdata) {
            return;
        }

        foreach ($this->rawdata as $key => $row) {
            $rowclass = $this->get_row_class($row);
            // Tag results that don't match.
            if (!isset($row->status) &&
                !preg_match(\gradereport_twoa\transfergrade::GRADECAT_PATTERN, $row->coursecode)) {
                $rowclass .= ' invalid';
            }
            $formattedrow = $this->format_row($row);
            $this->add_data_keyed($formattedrow, $rowclass);
        }
    }

    /**
     * out function.
     *
     * Overidden from the parent to help control what download options are displayed. Before outputting the table we will
     * use the set_config() function to disable the formats we want to exclude as an option to download. Once the table
     * has been output then we re enable these. This is done so that we don't interfere with any other plugin that needs
     * those export options.
     *
     * @param integer $pagesize
     * @param bool $useinitialsbar
     * @param string $downloadhelpbutton
     * @return void
     */
    public function out($pagesize, $useinitialsbar = true, $downloadhelpbutton='') {
        // First we need to disable some dataformats so they don't appear.
        $this->set_export_formats(1);

        // Do whatever our base class definition of this function does.
        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);

        // Now re enable the dataformats them.
        $this->set_export_formats(0);
    }

    /**
     * set_export_formats function.
     *
     * This function should only be called by $this->out(). It should be called before calling parent::out() passing in 1,
     * and after passing in 0. This ensures that the table download options are only restricted to the formats that we want.
     *
     * @param int $disabled     Are we going to enable or disable the dataformat_exportformat?
     * @return void
     */
    private function set_export_formats($disabled) {
        // Set the format extensions defined in this class to enabled or disabled.
        foreach ($this->excludedexportformats as $exportformat) {
            set_config('disabled', $disabled, "dataformat_{$exportformat}");
        }

        // Purge plugin caches.
        \core_plugin_manager::reset_caches();
    }

    /**
     * Show the user email before the @.
     *
     * @param object $value
     * @return false|string
     */
    public function col_tauiraid($value) {
        $studentid = html_writer::checkbox(
            'setstatus[]',
            $value->id,
            false,
            preg_replace('/@.+/', '', $value->tauiraid),
            ['class' => 'setstatus']
        );
        return $studentid;
    }

    /**
     * Show the grade category idnumber linking to the grade item TWOA Grade Export report.
     *
     * @param object $value
     * @return false|string
     */
    public function col_coursecode($value) {
        $url = new \moodle_url('index.php', ['id' => $value->courseid, 'itemid' => $value->itemid]);
        return html_writer::link($url, $value->coursecode);
    }

    /**
     * Show the course idnumber linking to the gradebook setup.
     *
     * @param object $value
     * @return false|string
     */
    public function col_classid($value) {
        $url = new \moodle_url('/grade/edit/tree/index.php', ['id' => $value->courseid]);
        return html_writer::link($url, $value->classid);
    }

    /**
     * Show a readable date in the date column,
     *
     * @param object $value
     * @return mixed
     */
    public function col_eventdate($value) {
        return date("d/m/Y", $value->eventdate);
    }

    /**
     * show the grade or scale value in the grade column.
     *
     * @param object $value
     * @return mixed
     */
    public function col_grade($value) {
        if (isset($value->scaleid) && $value->grade > 0) {
            $scale = explode(',', $value->scale);
            $grade = trim($scale[$value->grade - 1]);
        } else if ($value->grademax > 0) {
            $grade = 100 * $value->grade / $value->grademax;
        } else {
            $grade = -1;
        }
        return $grade;
    }

    /**
     * Give the data in the status column a useful name.
     *
     * @param object $value
     * @return mixed
     */
    public function col_status($value) {
        if (!isset($value->status)) {
            $value->status = \gradereport_twoa\transfergrade::STATUS_MISSING;
        }
        $status = get_string('status' . $value->status, 'gradereport_twoa');
        if ($value->status >= \gradereport_twoa\transfergrade::STATUS_SENT) {
            $status .= ' (' . date("d/m/Y", $value->timemodified) . ')';
        }

        return $status;
    }

}
