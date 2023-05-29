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


/**
 * Initialize module
 */
export const init = () => {
    document.querySelector('select#menusetstatuses').addEventListener('change', bulkUpdateStatus);
    document.querySelector('form.updateselected').addEventListener('change', highlightrow);
};

const highlightrow = (evt) => {
    let el = evt.target;
    let row = el.closest('tr');
    let cssClass = row.getAttribute('class');
    if (el.checked) {
        cssClass += ' selected';
        row.setAttribute('class', cssClass);
    } else {
        cssClass = cssClass.replace(' selected', '');
        row.setAttribute('class', cssClass);
    }
};

const bulkUpdateStatus = (evt) => {
    let el = evt.target;
    let form = el.closest('form');
    form.submit();
};
