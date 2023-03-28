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
 * Web services for TWOA grade report.
 *
 * @package    gradereport_twoa
 * @copyright  2023 Te Wānanga o Aotearoa
 * @author     Jeremy FitzPatrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'gradereport_twoa_getcompletegrades' => array(
        'classname' => 'gradereport_twoa\external\gradereport_twoa_getcompletegrades',
        'methodname' => 'get_completegrades',
        'classpath' => 'grade/report/twoa/classes/external/gradereport_twoa_getcompletegrades.php',
        'description' => 'Returns a list of grades that are ready to be entered into the SMS',
        'type' => 'read',
        'capabilities' => 'gradereport/user:view',
        'ajax' => false,
    ),
);
