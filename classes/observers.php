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
 * Listen for events
 *
 * @package    gradereport_twoa
 * @copyright  2023 Te Wānanga o Aotearoa
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_twoa;

/**
 * Listen for events
 */
class observers {
    /**
     * @param \core\event\user_graded $event
     * @return void
     * @throws \dml_exception
     */
    public static function handle_user_graded(\core\event\user_graded $event) {
        $itemid = $event->other['itemid'];

        // First find if this is a category with matching ID number.
        $item = \grade_item::fetch(['id' => $itemid]);
        $pattern = '/[A-Z]{5}\d{3}.*/';
        if ($item->is_category_item() && preg_match($pattern, $item->get_idnumber())) {
            $transferitem = new \gradereport_twoa\transfergrade($item, $event->relateduserid, $event->objectid);
            $transferitem->set_gradeready_status();
        }
    }
}
