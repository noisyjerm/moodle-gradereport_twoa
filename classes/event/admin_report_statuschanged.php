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
 * TWOA report gradetransferitem status changed event.
 *
 * @package     gradereport_twoa
 * @copyright   2023 Te Wānanga o Aotearoa.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_twoa\event;

use \core\event\base;

/**
 * Admin report gradetransferitem status changed event class.
 *
 * @package     gradereport_twoa
 * @copyright   2023 Te Wānanga o Aotearoa.
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_report_statuschanged extends base {

    /** @var string $reporttype The report type being viewed. */
    protected $reporttype;

    /**
     * Initialise the event data.
     */
    protected function init() {
        $reporttype = explode('\\', $this->eventname);
        $shorttype = explode('_', $reporttype[1]);
        $this->reporttype = $shorttype[1];

        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventadminreportitemchanged', 'gradereport_twoa');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return 'Status was changed to \'' . $this->other['status'] . '\' for grade transfer items: ' . $this->other['items'];
    }

    /**
     * Returns relevant URL.
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/grade/report/twoa/report.php');
    }

}
