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
 * External service to retrieve grade category user grades considered complete.
 *
 * @package    gradereport_twoa
 * @copyright  2023 Te Wānanga o Aotearoa
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_twoa\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Class gradereport_twoa_manualstatus
 */
class gradereport_twoa_manualstatus extends external_api {

    /**
     * Validate incoming parameters
     * @return \external_function_parameters
     */
    public static function update_transferstatus_parameters() {
        return new external_function_parameters (
            [
                'id'      => new external_value(
                    PARAM_INT,
                    'The id of the grade to update',
                    VALUE_REQUIRED,
                    0
                ),
            ]
        );
    }

    /**
     * Change the ready status of this grade
     * @param string $id
     * @return array
     */
    public static function update_transferstatus($id) {
        global $DB;
        $success = false;
        $graderecord = $DB->get_record('gradereport_twoa', ['gradeid' => $id]);

        if ($graderecord) {
            $status = $graderecord->status;
            $graderecord->status = $status < \gradereport_twoa\transfergrade::STATUS_READY
                       ? \gradereport_twoa\transfergrade::STATUS_READY
                       : \gradereport_twoa\transfergrade::STATUS_NOTREADY;
            $graderecord->timemodified = time();
            $success = $DB->update_record('gradereport_twoa', $graderecord);
        } else {
            $graderecord = (object)[
                'gradeid' => $id,
                'status' => \gradereport_twoa\transfergrade::STATUS_READY,
                'timemodified' => time(),
            ];
            $success = $DB->insert_record('gradereport_twoa', $graderecord, false);
        }

        return ['success' => $success];
    }

    /**
     * Describe the returned data structure.
     * @return \external_single_structure
     */
    public static function update_transferstatus_returns() {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Did this work ok')]);
    }

}
