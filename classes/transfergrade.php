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
 * Store grade results to transfer to SMS
 *
 * @package    gradereport_twoa
 * @copyright  2023 Te Wānanga o Aotearoa
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_twoa;

use grade_item;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/grade/querylib.php');

/**
 * Contains data model and logic for transferring grades to SMS
 */
class transfergrade {

    /** @var string Regular expression to match the grade category id on */
    const GRADECAT_PATTERN = '/(W[A-Z]{4}\d{3}|Q\d{5})(\.{1}\d{1,2})?/';
    /** @var string Regular expression to match the course category on */
    const COURSECAT_PATTERN = '/[A-Z]{4}\d{3}/';

    /** @var int A date to ignore results before. */
    const FROMDATE = 1678014000; // Midnight 6 March 2023 NZDST.

    /** @var int The status cannot be determined. */
    const STATUS_MISSING = -2;
    /** @var int The date does not meet the requirements for transferring. */
    const STATUS_ERROR = -1;
    /** @var int This grade is not considered complete. */
    const STATUS_NOTREADY = 0;
    /** @var int This grade is considered complete. */
    const STATUS_READY = 1;
    /** @var int This grade is considered complete and has been retrieved by API. */
    const STATUS_SENT = 2;
    /** @var int This grade is has been nodified after being retrieved by API. */
    const STATUS_MODIFIED = 3;

    /** @var grade_item The grade item that has been updated. */
    private $gradeitem;
    /** @var int The student / user whose grade has been updated. */
    private $userid;
    /** @var int The id for the grade_grade. */
    private $gradeid;

    /**
     * Constructor
     * @param \grade_item $item The grade item that has been updated
     * @param int $userid The student / user whose grade has been updated
     * @param int $gradeid The id for the grade_grade
     */
    public function __construct(\grade_item $item, $userid, $gradeid) {
        $this->gradeitem = $item;
        $this->gradeid = $gradeid;
        $this->userid = $userid;
    }

    /**
     * Record status of individual grade transfer
     * @return bool
     * @throws \dml_exception
     */
    public function set_gradeready_status() {
        global $DB;
        $items = $this->get_includeditems();
        $data = (object) [
            'gradeid' => $this->gradeid,
            'status' => self::STATUS_READY,
            'timemodified' => time(),
        ];

        foreach ($items as $item) {
            if (!$this->is_passed($item)) {
                $data->status = self::STATUS_NOTREADY;
                break;
            }
        }
        // Update the table.
        $record = $DB->get_record('gradereport_twoa', ['gradeid' => $this->gradeid]);
        if ($record === false) {
            $DB->insert_record('gradereport_twoa', $data);
        } else {
            if ($record->status == self::STATUS_SENT) {
                $data->status = self::STATUS_MODIFIED;
            }
            $data->id = $record->id;
            $DB->update_record('gradereport_twoa', $data);
        }
        return true;
    }

    /**
     * Check if all items are passed.
     * @param \grade_item $item
     * @return bool
     */
    protected function is_passed($item) {
        if (gettype($item) == 'array') {
            $item = $item['object'];
        }
        $grade = \grade_grade::fetch_users_grades($item, [$this->userid]);
        if ($grade[$this->userid]->finalgrade >= $item->gradepass) {
            return true;
        }
        return false;
    }

    /**
     * Get items for this grade
     * If the category has no items, we retrive all activity grade items
     * @return array|void
     */
    protected function get_includeditems() {
        $cat = \grade_category::fetch(['id' => $this->gradeitem->iteminstance]);
        $items = $cat->get_children();
        if (count($items) === 0) {
            // Extract items from calculation.
            $calc = $this->gradeitem->calculation;
            $gipattern = '/(?!##gi)\d+(##)/';
            preg_match_all($gipattern, $calc, $itemids);
            foreach ($itemids[0] as $itemid) {
                $itemid = str_replace('##', '', $itemid);
                $items[$itemid] = \grade_item::fetch(['id' => $itemid]);
            }
        }

        return $items;
    }

    /**
     * Check if all the attempts are used for a grade grade
     * @param \grade_item $item
     * @return bool
     * @throws \dml_exception
     */
    protected function is_allattemptsused($item) {
        global $DB;
        // Todo: implement quiz and others.
        // Sometimes we get an array so extract the object.
        if (is_array($item) && isset($item['object'])) {
            $item = $item['object'];
        }
        if ($item->itemmodule === 'assign') {
            $assign = $DB->get_record('assign', ['id' => $item->iteminstance]);

            $attempts = $DB->count_records('assign_grades', ['assignment' => $item->iteminstance, 'userid' => $this->userid]);
            if ($assign->maxattempts == $attempts) {
                return true;
            }
        }
        return false;
    }

}
