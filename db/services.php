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

$functions = [
    'gradereport_twoa_getcompletegrades' => [
        'classname' => 'gradereport_twoa\external\gradereport_twoa_getcompletegrades',
        'methodname' => 'get_completegrades',
        'classpath' => 'grade/report/twoa/classes/external/gradereport_twoa_getcompletegrades.php',
        'description' => 'Returns a list of grades that are ready to be entered into the SMS',
        'type' => 'read',
        'capabilities' => 'gradereport/user:view',
        'ajax' => false,
    ],
    'gradereport_twoa_manualstatus' => [
        'classname' => 'gradereport_twoa\external\gradereport_twoa_manualstatus',
        'methodname' => 'update_transferstatus',
        'classpath' => 'grade/report/twoa/classes/external/gradereport_twoa_manualstatus.php',
        'description' => 'Changes the ready status of the grade',
        'type' => 'write',
        'capabilities' => 'gradereport/twoa:view',
        'ajax' => true,
    ],
];

// We define the services to install as pre-built services. A pre-build service is not editable by administrator.
$services = [
    'TWOA Grades Export API' => [
        'functions'         => [
            'gradereport_twoa_getcompletegrades',
        ],
        'restrictedusers'   => 1,
        'enabled'           => 1,
    ],
];
