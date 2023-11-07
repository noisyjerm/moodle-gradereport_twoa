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
 * Javascript module for fixing the position of sticky headers with multiple colspans
 *
 * @module      gradereport_twoa/actions
 * @copyright   2023 Te Wānanga o Aotearoa
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


import * as Ajax from 'core/ajax';
import Notification from 'core/notification';
import * as ModalFactory from "core/modal_factory";
import * as ModalEvents from "core/modal_events";
import * as Str from "core/str";

/**
 * Initialize module
 */
export const init = () => {
    document.querySelector('table.twoa_gradeexport').addEventListener('change', updateStatus);
};

const updateStatus = (evt) => {
    let el = evt.target;
    let gradeid = el.dataset.gradeid;
    let ispassed = el.dataset.passed;
    // Disable the element.
    el.disabled = 'disabled';
    let titlewords = Str.get_string("dialog:title", "gradereport_twoa");
    let bodywords = Str.get_string("dialog:body", "gradereport_twoa");

    // Warn a YTA cannot be set to ready.
    if (ispassed === "0" && el.checked) {
        ModalFactory.create({
            type: ModalFactory.types.ALERT,
            body: bodywords,
            title: titlewords,
            removeOnClose: true
        }).then(function(modal) {
            modal.getRoot().on(ModalEvents.hidden, function() {
                el.disabled = false;
                el.checked = false;
                modal.destroy();
            });
            modal.show();
            return true;
        }).catch(Notification.exception);
    } else {
        Ajax.call([{
            methodname: 'gradereport_twoa_manualstatus',
            args: {'id': gradeid},
            done: function() {
                // Enable the element
                el.disabled = false;
            },
            fail: Notification.exception
        }]);
    }
};