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

use \grade_item;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/grade/querylib.php');

/**
 * Contains data model and logic for transferring grades to SMS
 */
class transfergrade {

    /** @var int The status cannot be determined. */
    const STATUS_ERROR = -1;
    /** @var int This grade is not considered complete. */
    const STATUS_NOTREADY = 0;
    /** @var int This grade is considered complete. */
    const STATUS_READY = 1;
    /** @var int This grade is considered complete and has been retrieved by API. */
    const STATUS_SENT = 2;

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
            if (!$this->is_passed($item) && !$this->is_allattemptsused($item)) {
                $data->status = self::STATUS_NOTREADY;
                break;
            }
        }
        // Update the table.
        $record = $DB->get_record('gradereport_twoa', ['gradeid' => $this->gradeid]);
        if ($record === false) {
            $DB->insert_record('gradereport_twoa', $data);
        } else {
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
            // Todo: test this more. Maybe event does not fire on every category?
            $items = grade_item::fetch_all(['courseid' => $this->gradeitem->courseid, 'itemtype' => 'mod', 'gradetype' => 1]);
            foreach ($items as $key => $item) {
                $cat = \grade_item::fetch([
                    'courseid' => $this->gradeitem->courseid,
                    'itemtype' => 'category',
                    'iteminstance' => $item->categoryid
                    ]);
                if ($cat->gradetype == 0) {
                    unset($items[$key]);
                }
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
