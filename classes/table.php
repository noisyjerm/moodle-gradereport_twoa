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
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table extends \table_sql {

    /** @var grade_info $gradeinfo          An object containing the grades for users in the course of the grade item. */
    private $gradeinfo;

    /** @var array $excludedexportformats   An array of formats to exclude from the list of available formats to download as. */
    private $excludedexportformats = array();

    /**
     * table constructor.
     *
     * Constructs the TWOA report table with the given parameters.
     *
     * By default this is constructed as downloadable, not collapsible and download buttons shown at the bottom. The export formats
     * to exclude from this report are hardcoded in at this stage.
     *
     * The implementation of this class is slightly different as the sql set in here is used to only get the users that are enrolled
     * in the given course id. The grade_info class is constructed with the courseid and itemid which populates the grades for the
     * given course and grade item. A function in the grade_info class accepts a userid as a parameter which looks in it's own array
     * of user grades indexed via the userid and returns the associated information data for that user.
     *
     * @param string $uniqueid          Some sort of unique id for this table object.
     * @param int $courseid             The id of the course where the grade item is to be pulled from.
     * @param int $itemid               The id of the grade item of type category in the provided course id.
     * @param string $sqlfields         The table fields to select the users enrolled in the provided course from.
     * @param string $sqlfrom           The tables to select the data from.
     * @param string $sqlwhere          Any conditions that the query needs to meet.
     * @param string $sqlparams         Any parameters that need to be passed to the sql query.
     * @param array $columns            The columns to display on this report.
     * @param array $headers            The titles of the columns for this report.
     * @param array $fieldstonotsort    The columns in $columns to exclude from the fields that can be sorted.
     * @param \moodle_url $baseurl      The url including any query strings. This will help generate urls for paging what is output.
     * @param bool $isdownloadable      Should this report be downloadable? The default is set to yes.
     * @param bool $iscollapsible       Should this report be collapsible? The default is yes.
     * @param array $showbuttonsat      Where should the download buttons be shown? The default is set to display at the bottom.
     */
    public function __construct($uniqueid, $courseid, $itemid,
                                $sqlfields = '', $sqlfrom = '', $sqlwhere = '', $sqlparams = '',
                                $columns = array(), $headers = array(), $fieldstonotsort = array(),
                                \moodle_url $baseurl, $isdownloadable = true, $iscollapsible = false,
                                $showbuttonsat = array(TABLE_P_BOTTOM)) {
        global $CFG;

        // Set the id of this table.
        parent::__construct($uniqueid);

        // Set the columns as per the constructor.
        $this->define_columns($columns);

        // Set the column headers as per the constructor.
        $this->define_headers($headers);

        // Get the users grade information for this course and grade item.
        $gradeinfo = new \gradereport_twoa\grade_info($courseid, $itemid);

        // Now pass the grade info object that has all the user grade data to this objects propertys.
        $this->gradeinfo = $gradeinfo;

        // What fields are not to be sorted on as per constructor.
        foreach ($fieldstonotsort as $fieldtonotsort) {
            $this->no_sorting($fieldtonotsort);
        }

        // Set the base url as per the constructor.
        $this->define_baseurl($baseurl);

        // Is this table downloadable?
        $this->is_downloadable($isdownloadable);

        // Is this table collapsible?
        $this->collapsible($iscollapsible);

        // Set the sql for this table. This should be passed in the constructor otherwise we are in trouble!
        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);

        // Lets make the table download button show up where it is defined in the constructor.
        $this->show_download_buttons_at($showbuttonsat);

        // Get the formats that this report can not be exported as from the plugin settings.
        if ($excludedformatexports = get_config('gradereport_twoa', 'excluded_dataformats')) {
            $this->excludedexportformats = explode(',', $excludedformatexports);
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
     * col_email function.
     *
     * From the grade_info object return the users email address for the given userid.
     *
     * @param object $value
     * @return mixed
     */
    public function col_email($value) {
        return $this->gradeinfo->get_user_grade_info($value->userid, 'email');
    }

    /**
     * col_course function.
     *
     * From the grade_info object return the users fullname for the given userid.
     *
     * @param object $value
     * @return mixed
     */
    public function col_course($value) {
        return $this->gradeinfo->get_user_grade_info($value->userid, 'gradeitemname');
    }

    /**
     * col_grade function.
     *
     * From the grade_info object return the final grade value for the given userid.
     *
     * @param object $value
     * @return mixed
     */
    public function col_grade($value) {
        return $this->gradeinfo->get_user_grade_info($value->userid, 'str_grade');
    }

    /**
     * col_dategraded function.
     *
     * From the grade_info object return the time graded value for the given userid.
     *
     * @param object $value
     * @return mixed
     */
    public function col_dategraded($value) {
        return $this->gradeinfo->get_user_grade_info($value->userid, 'twoatimegraded');
    }

    /**
     * Get the HTML for the Actions column.
     * @param object $value
     * @return string
     */
    public function col_action($value) {
        $idnumber = $this->gradeinfo->get_item_idnumber();
        $status = $this->gradeinfo->get_user_grade_info($value->userid, 'transferstatus');
        $gradeid = $this->gradeinfo->get_user_grade_info($value->userid, 'gradeid');
        $pattern = \gradereport_twoa\transfergrade::GRADECAT_PATTERN;

        $attributes = [
            'type' => 'checkbox',
            'data-gradeid' => $gradeid,
            'class' => 'gradetransfer'
        ];
        if (!preg_match($pattern, $idnumber) || $status == 2) {
            $attributes['disabled'] = 'disabled';
        }
        if ($status >= 1) {
            $attributes['checked'] = true;
        }

        $out = html_writer::tag('label', 'transferred',
                array('for' => 'status_' . $value->userid, 'class' => 'accesshide'));
        $out .= html_writer::empty_tag('input', $attributes);
        return $out;
    }
}
