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
use core_external\external_multiple_structure;

/**
 * Class gradereport_twoa_getcompletegrades
 */
class gradereport_twoa_getcompletegrades extends external_api {

    /**
     * Validate incoming parameters
     * @return \external_function_parameters
     */
    public static function get_completegrades_parameters() {
        return new external_function_parameters (
            [
                'range'    => new external_value(PARAM_ALPHA, 'Keyword to describe the subset of results',
                    VALUE_DEFAULT, 'new'),
                'rangeval' => new external_value(PARAM_INT, 'Paramter', VALUE_DEFAULT, '86400'),
                'stealth'  => new external_value(PARAM_BOOL, 'To mark as sent or not', VALUE_DEFAULT, 0),
                'limit'    => new external_value(PARAM_INT, 'Maximum number of records per request', VALUE_DEFAULT, 1000),
                'lastid'     => new external_value(PARAM_INT, 'The page number of a paginated request', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Retrieve a set of grades considered ready for adding to SMS
     * @param string $range keyword to describe the subset (last | since | new)
     * @param integer $rangeval parameter associated with range (numseconds, unixtime, null)
     * @param int $stealth use when testing to not mark the record as sent
     * @param integer $limit maximum number of records (not implemented. Todo: implement)
     * @param integer $lastid page number of record subset (not implemented. Todo: implement)
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_completegrades($range = 'new', $rangeval = 86400, $stealth=0, $limit=1000, $lastid=0) {
        global $DB;
        $errors = [];

        // Range options.
        $params = [
            0 => $rangeval,
            1 => \gradereport_twoa\transfergrade::STATUS_READY,
            2 => \gradereport_twoa\transfergrade::STATUS_MODIFIED,
            3 => \gradereport_twoa\transfergrade::STATUS_SENT,
        ];
        $instatuses = 'IN (?,?,?)';
        if ($range === 'new') {
            $params[0] = $lastid === 0 ? 0 : $rangeval;
            $instatuses = 'IN (?,?)';
        } else if ($range === 'last' && $lastid === 0) {
            $params[0] = time() - $rangeval;
        }

        $classes = get_config('gradereport_twoa', 'api_onlytheseclasses');
        $andidnumberin = '';
        if ($classes != '') {
            // A ? or single quote in the list will mess with the query. Todo: validate the input somehow.
            if (preg_match('/[\?\']/', $classes) == 1) {
                $classes = str_replace('?', '%%Q%%', $classes);
                $classes = str_replace('\'', '%%S%%', $classes);
                $errors[] = 'Question marks? and single quotes don\'t play nicely here. Please remove them from the classes list.';
            }
            // Convert listed items to a string for strict databases like Postgres.
            $classeslist = preg_split('/\n/', $classes);
            array_walk($classeslist, function(&$item) {
                $item = '\'' . trim($item) . '\'';
            });
            $andidnumberin = "AND c.idnumber IN (" . implode(',', $classeslist) . ")";
        }

        $query = "SELECT gt.*, u.email TauiraID, cc.idnumber ProgCode, c.idnumber ClassID, gi.idnumber CourseCode,
                         gg.timemodified EventDate, gg.finalgrade Grade, gi.grademax, gi.scaleid, s.scale, gg.usermodified
                  FROM {gradereport_twoa} gt
                  JOIN {grade_grades} gg ON gg.id = gt.gradeid
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  LEFT JOIN {scale} s ON s.id = gi.scaleid
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {user} u ON u.id = gg.userid
                  WHERE gg.timemodified >= ?
                  AND gt.status $instatuses
                  $andidnumberin
                  ORDER BY gt.timemodified, gt.id ASC";
        $results = $DB->get_records_sql($query, $params);

        // Pagination
        // Use SQL to reduce the data set to gte to the timemodified of the last result of the previous 'page',
        // then use PHP to throw away results with that same timemodified that were already sent in last 'page' / request,
        // to get a complete set accounting for possible changes between calls.

        // Skip results we have already sent.
        foreach ($results as $result) {
            if ($result->timemodified == $rangeval && $result->id <= $lastid) {
                array_shift($results);
            } else {
                break;
            }
        }

        $pages = ceil(count($results) / $limit);

        if (count($results) > $limit) {
            $i = 0;
            if ($lastid !== 0) {
                $i = array_search($lastid, array_keys($results)) + 1;
            }
            $results = array_slice($results, $i, $limit, true);
        }
        $lastid = array_key_last($results);
        $lasttime = $results[$lastid]->timemodified;
        $nextquery = $pages > 1 ? "&range=$range&rangeval=$lasttime&stealth=$stealth&limit=$limit&lastid=$lastid" : '';
        $paginationinfo = [
            'size' => $limit,
            'pages' => $pages,
            'lastid' => $lastid,
            'nextquery' => $nextquery,
        ];

        $updates = $DB->start_delegated_transaction();
        foreach ($results as $key => $result) {
            // Strip email.
            $result->tauiraid = preg_replace('/@.+/', '', $result->tauiraid);
            $result->timemodified = time();
            $error = self::validate_result($result);
            if ($error !== true) {
                $errors[] = "Grade id $key $error is not valid, ";
                $result->status = \gradereport_twoa\transfergrade::STATUS_ERROR;
                $DB->update_record('gradereport_twoa', $result);
                unset($results[$key]);
                continue;
            }

            if (!$stealth) {
                $result->status = \gradereport_twoa\transfergrade::STATUS_SENT;
                $DB->update_record('gradereport_twoa', $result);
            }

            // Format date.
            $result->eventdate  = date("Y-m-d G:i:s", $result->eventdate);
            // Get scale value.
            if (isset($result->scaleid)) {
                $scale = explode(',', $result->scale);
                $result->grade = trim($scale[$result->grade - 1]);
            } else {
                $result->grade = 100 * $result->grade / $result->grademax;
            }
        }
        $updates->allow_commit();

        if (empty($errors)) {
            $errors[] = 'None';
        }

        $event = \gradereport_twoa\event\grade_data_retrieved::create(
            [
                'context'       => \context_system::instance(),
                'courseid'      => 0,
                'relateduserid' => 0,
                'other'         => ['message' =>
                                        count($results) . ' results successful, ' . count($errors) . ' results skipped.',
                ],
            ]
        );

        $event->trigger();

        $results = array_values($results);
        return [
            'grades' => $results,
            'errors' => implode(', ', $errors),
            'pagination' => $paginationinfo,
        ];
    }

    /**
     * Describe the returned data structure.
     * @return external_single_structure
     */
    public static function get_completegrades_returns() {

        $grade = new external_single_structure(
            [
                'tauiraid' => new external_value(PARAM_INT, 'Portion of email address before @ matching student ID from SMS'),
                'progcode' => new external_value(PARAM_ALPHANUMEXT, 'ID number of category matching Class code from SMS'),
                'classid' => new external_value(PARAM_INT, 'ID number of course matching Class id from SMS'),
                'coursecode' => new external_value(PARAM_RAW, 'ID number of category grade item matching Grade code in SMS'),
                'grade' => new external_value(PARAM_RAW, 'Grade awarded to the student'),
                'eventdate' => new external_value(PARAM_RAW, 'Unix timestamp when grade was last updated'),
            ]
        );

        $pagination = [
                'size' => new external_value(PARAM_INT, 'The number of records'),
                'pages' => new external_value(PARAM_INT, 'The number pages of results for this query'),
                'lastid' => new external_value(PARAM_INT, 'The id number of the last record in this set'),
                'nextquery' => new external_value(PARAM_RAW, 'Query string with params and values for the next \'page\''),
        ];

        return new external_single_structure([
            'grades' => new external_multiple_structure($grade, 'List of grades'),
            'errors' => new external_value(PARAM_RAW, 'Notes of errors', VALUE_OPTIONAL, 'None'),
            'pagination' => new external_single_structure($pagination, 'Details of pagination', VALUE_OPTIONAL, 'None'),
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
