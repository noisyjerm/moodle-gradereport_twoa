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

/**
 * Contains data model and logic for transferring grades to SMS
 */
class transfergrade {

    const STATUS_ERROR = -1;
    const STATUS_NOTREADY = 0;
    const STATUS_READY = 1;
    const STATUS_SENT = 2;

    private $gradeitem;
    private $userid;
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
            'status' => self::STATUS_NOTREADY,
            'timemodified' => time(),
        ];
        if ($this->is_allpassed($items) || $this->is_allattemptsused($items)) {
            $data->status = self::STATUS_READY;
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
     * @param array $items
     * @return bool
     */
    protected function is_allpassed($items) {
        $iscomplete = true;
        foreach ($items as $item) {
            $grade = \grade_grade::fetch_users_grades($item['object'], [$this->userid], false);

            if ($item['object']->gradepass > $grade[$this->userid]->finalgrade) {
                $iscomplete = false;
                break;
            }

        }
        return $iscomplete;
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
            // Todo: test this.
            $items = grade_get_gradable_activities($this->gradeitem->courseid);
        }
        return $items;
    }

    /**
     * Check if all the attempts are used.
     * @return false
     */
    protected function is_allattemptsused() {
        // Todo: implement this.
        return false;
    }

}
