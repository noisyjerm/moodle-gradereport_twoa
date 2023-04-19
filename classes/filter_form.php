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
 * Form to filter the Grade Exports
 *
 * @package   gradereport_twoa
 * @copyright Te Wānanga o Aotearoa 2023 and 2017 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_twoa;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/formslib.php');

/**
 * Class filter_form form to filter the results by date
 * @package gradereport_twoa
 */
class filter_form extends \moodleform {
    /**
     * Form definition
     * @throws \HTML_QuickForm_Error
     * @throws \coding_exception
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'filterheader', get_string('filter'));

        $selectoptions = [
            100 => get_string('status100', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_MISSING => get_string('status-2', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_ERROR => get_string('status-1', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_NOTREADY => get_string('status0', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_READY => get_string('status1', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_SENT => get_string('status2', 'gradereport_twoa'),
            \gradereport_twoa\transfergrade::STATUS_MODIFIED => get_string('status3', 'gradereport_twoa'),
        ];

        $mform->addElement('select', 'status', get_string('status'), $selectoptions);
        $mform->setType('status', PARAM_INT);

        $opts = ['optional' => true];
        $mform->addElement('date_selector', 'filterstartdate', get_string('from'), $opts);
        $mform->addElement('date_selector', 'filterenddate', get_string('to'), $opts);

        // Add the filter/cancel buttons (without 'closeHeaderBefore', so they collapse with the filter).
        $buttonarray = [
            $mform->createElement('submit', 'submitbutton', get_string('filter')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Expand the form contents if the filter is in use.
     * @throws \HTML_QuickForm_Error
     */
    public function definition_after_data() {
        $mform = $this->_form;
        $filterstartdate = $mform->getElement('filterstartdate')->getValue();
        $filterenddate = $mform->getElement('filterenddate')->getValue();
        if (!empty($filterstartdate['enabled']) || !empty($filterenddate['enabled'])) {
            $mform->setExpanded('filterheader', true);
        }
    }
}
