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
                'rangeval' => new \external_value(PARAM_INT, 'Paramter', VALUE_OPTIONAL, '86400'),
                'limit'      => new \external_value(PARAM_INT, 'Maximum number of records per request', VALUE_OPTIONAL),
                'page'       => new \external_value(PARAM_INT, 'The page number of a paginated request', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Retrieve a set of grades considered ready for adding to SMS
     * @param string $range keyword to describe the subset (last | since | new)
     * @param integer $rangeval parameter associated with range (numseconds, unixtime, null)
     * @param integer $limit maximum number of records (not implemented. Todo: implement)
     * @param integer $page page number of record subset (not implemented. Todo: implement)
     * @return array
     */
    public static function get_completegrades($range = 'last', $rangeval = 86400, $limit=0, $page=1) {
        global $DB;
        // Range options.
        $params = [
            0 => time() - $rangeval,
            1 => \gradereport_twoa\transfergrade::STATUS_READY,
            2 => \gradereport_twoa\transfergrade::STATUS_SENT,
        ];
        $eqorin = 'IN (?,?)';
        if ($range === 'new') {
            $params[0] = 0;
            $eqorin = '= ?';
        } else if ($range === 'since') {
            $params[0] = $rangeval;
        }

        $query = "SELECT gt.*, u.email TauiraID, cc.idnumber ProgCode, c.idnumber ClassID, gi.idnumber CourseCode,
                         gg.timemodified EventDate, gg.finalgrade Grade, gi.scaleid, s.scale, gg.usermodified
                  FROM {gradereport_twoa} gt
                  JOIN {grade_grades} gg ON gg.id = gt.gradeid
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  LEFT JOIN {scale} s ON s.id = gi.scaleid
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {user} u ON u.id = gg.userid
                  WHERE gt.timemodified >= ?
                  AND gt.status $eqorin";
        $results = $DB->get_records_sql($query, $params);
        $errors = [];

        foreach ($results as $key => $result) {
            // Strip email.
            $result->tauiraid = preg_replace('/@.+/', '', $result->tauiraid);
            $error = self::validate_result($result);
            if ($error !== true) {
                $errors[] = "Grade id $key $error is not valid, ";
                $result->status = \gradereport_twoa\transfergrade::STATUS_ERROR;
                $DB->update_record('gradereport_twoa', $result);
                unset($results[$key]);
                continue;
            }
            $result->status = \gradereport_twoa\transfergrade::STATUS_SENT;
            $DB->update_record('gradereport_twoa', $result);

            // Format date.
            $result->eventdate  = date("Y-m-d G:i:s", $result->eventdate);
            // Get scale value.
            if (isset($result->scaleid)) {
                $scale = explode(',', $result->scale);
                $result->grade = trim($scale[$result->grade - 1]);
            }
        }
        if (empty($errors)) {
            $errors[] = 'None';
        }

        $event = \gradereport_twoa\event\grade_data_retrieved::create(
            array(
                'context'       => \context_system::instance(),
                'courseid'      => 0,
                'relateduserid' => 0,
                'other'         => ['message' =>
                                        count($results) . ' results successful, ' . count($errors) . ' results skipped.'
                ],
            )
        );

        $event->trigger();

        $results = array_values($results);
        return ['grades' => $results, 'errors' => implode(', ', $errors)];
    }

    /**
     * Describe the returned data structure.
     * @return \external_single_structure
     */
    public static function get_completegrades_returns() {

        $grade = new \external_single_structure(
            array(
                'tauiraid' => new \external_value(PARAM_INT, 'Portion of email address before @ matching student ID from SMS'),
                'progcode' => new \external_value(PARAM_ALPHANUMEXT, 'ID number of category matching Class code from SMS'),
                'classid' => new \external_value(PARAM_INT, 'ID number of course matching Class id from SMS'),
                'coursecode' => new \external_value(PARAM_RAW, 'ID number of category grade item matching Grade code in SMS'),
                'grade' => new \external_value(PARAM_RAW, 'Grade awarded to the student'),
                'eventdate' => new \external_value(PARAM_RAW, 'Unix timestamp when grade was last updated'),
            )
        );

        return new \external_single_structure([
            'grades' => new \external_multiple_structure($grade), 'List of grades',
            'errors' => new \external_value(PARAM_RAW, 'Notes of errors', VALUE_OPTIONAL, 'None'),
        ]);
    }

    /**
     * Check data is populated correctly
     * @param object $result
     * @return bool | string
     */
    private static function validate_result($result) {
        if (!is_numeric($result->tauiraid)) {
            return 'student id ' . $result->tauiraid;
        }

        if (!preg_match(\gradereport_twoa\transfergrade::COURSECAT_PATTERN, $result->progcode)) {
            return 'course category ' . $result->progcode;
        }

        if (!is_numeric($result->classid)) {
            return 'course id ' . $result->classid;
        }

        if (!preg_match(\gradereport_twoa\transfergrade::GRADECAT_PATTERN, $result->coursecode)) {
            // Shouldn't be able to happen as match is a pre-condition.
            return 'grade category id ' . $result->coursecode;
        }

        // Todo: check it.
        if (empty($result->grade)) {
            return 'grade ' . $result->grade;
        }
        // Todo: check it.
        if (empty($result->eventdate)) {
            return 'date ' . $result->eventdate;
        }
        return true;
    }

}
