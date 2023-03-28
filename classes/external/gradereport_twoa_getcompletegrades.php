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

/**
 * Class gradereport_twoa_getcompletegrades
 */
class gradereport_twoa_getcompletegrades extends \external_api {

    /**
     * Validate incoming parameters
     * @return \external_function_parameters
     */
    public static function get_completegrades_parameters() {
        return new \external_function_parameters (
            array(
                'range'      => new \external_value(
                    PARAM_ALPHA,
                    'Keyword to describe the subset of results',
                    VALUE_OPTIONAL,
                    'last'
                ),
                'rangeparam' => new \external_value(PARAM_INT, 'Paramter', VALUE_OPTIONAL, '86400'),
                'limit'      => new \external_value(PARAM_INT, 'Maximum number of records per request', VALUE_OPTIONAL),
                'page'       => new \external_value(PARAM_INT, 'The page number of a paginated request', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Retrieve a set of grades considered ready for adding to SMS
     * @param string $range keyword to describe the subset (last | since | new)
     * @param integer $rangeparam parameter associated with range (numseconds, unixtime, null)
     * @param integer $limit maximum number of records (not implemented. Todo: implement)
     * @param integer $page page number of record subset (not implemented. Todo: implement)
     * @return array
     */
    public static function get_completegrades($range = 'last', $rangeparam = 86400, $limit=0, $page=1) {
        global $DB;
        // Range options.
        $params = [
            0 => time() - $rangeparam,
            1 => \gradereport_twoa\transfergrade::STATUS_READY,
            2 => \gradereport_twoa\transfergrade::STATUS_SENT,
        ];
        $eqorin = 'IN (?,?)';
        if ($range === 'new') {
            $params[0] = 0;
            $eqorin = '= ?';
        } else if ($range === 'since') {
            $params[0] = $rangeparam;
        }

        $query = "SELECT gt.*, u.email TauiraID, cat.name ProgCode, c.idnumber ClassID, gi.idnumber CourseCode,
                         gg.timemodified EventDate, gg.finalgrade Grade, gg.usermodified
                  FROM {gradereport_twoa} gt
                  JOIN {grade_grades} gg ON gg.id = gt.gradeid
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN {course_categories} cat ON c.category
                  JOIN {user} u ON u.id = gg.userid
                  WHERE gt.timemodified >= ?
                  AND gt.status " . $eqorin;
        $results = $DB->get_records_sql($query, $params);

        foreach ($results as $result) {
            $result->status = \gradereport_twoa\transfergrade::STATUS_SENT;
            $DB->update_record('gradereport_twoa', $result);
            // Strip email.
            $result->tauiraid = preg_replace('/@.+/', '', $result->tauiraid);
            // Format date.
            $result->eventdate  = date("Y-m-d G:i:s", $result->eventdate);
        }

        $results = array_values($results);
        return ['grades' => $results];
    }

    /**
     * Describe the returned data structure.
     * @return \external_single_structure
     */
    public static function get_completegrades_returns() {

        $grade = new \external_single_structure(
            array(
                'tauiraid' => new \external_value(PARAM_ALPHANUMEXT, 'Email address of student from SMS'),
                'progcode' => new \external_value(PARAM_ALPHANUMEXT, 'Class code from SMS'),
                'classid' => new \external_value(PARAM_INT, 'Class id from SMS'),
                'coursecode' => new \external_value(PARAM_ALPHANUM, 'Grade code in SMS'),
                'grade' => new \external_value(PARAM_RAW, 'Grade awarded to the student'),
                'eventdate' => new \external_value(PARAM_RAW, 'Unix timestamp when grade was last updated'),
            )
        );

        return new \external_single_structure(['grades' => new \external_multiple_structure($grade), 'List of grades']);
    }

}
