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

namespace gradereport_twoa;

/**
 * Class grade_info.
 * @package     gradereport_twoa
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_info {
    /** @var bool|\grade_item           The result of a \grade_item::fetch() call. */
    private $gradeitem;

    /** @var int $gradeitemid           The id of the category grade item to get user grades for. */
    private $gradeitemid;

    /** @var int $courseid              The id of the course that the category grade item is in. */
    private $courseid;

    /** @var  \stdClass $usergrades     Grade item information and associated user grades. */
    private $usergrades;

    /** @var array $userids             The userids to get grades for. */
    private $userids = [];

    /**
     * grade_info constructor.
     *
     * @param int $courseid         The id of the course to get user grades from.
     * @param int $gradeitemid      The id of the category grade item to get user grades from.
     */
    public function __construct($courseid = 0, $gradeitemid = 0) {
        global $DB;

        // What library files do we need to include for this to work. These need to be loaded first.
        $requiredlibraries = [
            'grade/querylib.php'    => 'dirroot',
            'gradelib.php'          => 'libdir',
            // Need the grade_item class.
            'grade/grade_item.php'  => 'libdir',
            // Need the grade_grade class.
            'grade/grade_grade.php' => 'libdir',
            // Need some grade constants also.
            'grade/constants.php'   => 'libdir',
            // Lets load our plugins lib too :).
            'grade/report/twoa/locallib.php'    => 'dirroot',
        ];

        // Load the library files that we said we needed for this to work.
        $this->load_libraries($requiredlibraries);

        // Get the grade item.
        $gradeitem = $DB->get_record('grade_items', ['id' => $gradeitemid]);

        // Set the params to construct the grade_item object with.
        $gradeitemparams = [
            'id'            => $gradeitem->id,
            'courseid'      => $courseid,
            'itemtype'      => $gradeitem->itemtype,
            'categoryid'    => $gradeitem->categoryid,
        ];

        // Set the grade item.
        $this->gradeitem = \grade_item::fetch($gradeitemparams);

        // Set the course id.
        $this->courseid = $courseid;

        // Set the grade item id.
        $this->gradeitemid = $gradeitemid;

        // What users are we getting grades for?
        $this->set_userids();

        // Set the user grades for this grade item.
        $this->set_user_grades();
    }

    /**
     * set_userids function.
     *
     * Find the users that are enrolled in this course via any enrolment method in the user enrolments table.
     *
     * @return void
     */
    private function set_userids() {
        global $DB;

        // Get the enrolled users from the locallib function.
        $this->userids = gradereport_twoa_get_enrolled_users($this->courseid);
    }

    /**
     * load_libraries function.
     *
     * Load any librarires for functions that we use in this class.
     *
     * @param array $requiredlibraries
     * @return void
     */
    private function load_libraries($requiredlibraries = []) {
        global $CFG;

        // Load the files.
        foreach ($requiredlibraries as $requiredlibraryfilepath => $location) {
            // Todo: Check that key and value exists.
            // Check if the file exists first before trying to require it.
            // Todo: Remove any leading foward slashes.
            if (file_exists($file = "{$CFG->$location}/{$requiredlibraryfilepath}")) {
                require_once($file);
            }
        }
    }

    /**
     * set_user_grades function.
     *
     * Adapted from another lib, this function sets up our user grades for the given grade item in the given course. This also
     * prepares any other data that the report needs.
     *
     * @return void
     */
    private function set_user_grades() {
        global $DB;

        // Regrade grades if need be.
        if ($this->gradeitem->needsupdate) {
            grade_regrade_final_grades($this->gradeitem->courseid);
        }

        // The grade item and associated grades.
        $item               = new \stdClass();
        $item->scaleid      = $this->gradeitem->scaleid;
        $item->name         = $this->gradeitem->get_name();
        $item->grademin     = $this->gradeitem->grademin;
        $item->grademax     = $this->gradeitem->grademax;
        $item->gradepass    = $this->gradeitem->gradepass;
        $item->locked       = $this->gradeitem->is_locked();
        $item->hidden       = $this->gradeitem->is_hidden();
        $item->grades       = [];

        switch ($this->gradeitem->gradetype) {
            case GRADE_TYPE_NONE:
                break;

            case GRADE_TYPE_VALUE:
                $item->scaleid = 0;
                break;

            case GRADE_TYPE_TEXT:
                $item->scaleid   = 0;
                $item->grademin   = 0;
                $item->grademax   = 0;
                $item->gradepass  = 0;
                break;
        }

        // Get the users grades for this grade item.
        $gradegrades = \grade_grade::fetch_users_grades($this->gradeitem, $this->userids, true);
        $sql = "SELECT gg.userid, gt.status FROM
                {grade_items} gi
                JOIN {grade_grades} gg ON gg.itemid = gi.id
                LEFT JOIN {gradereport_twoa} gt ON gt.gradeid = gg.id
                WHERE gi.id = ?";
        $transferedgrades = $DB->get_records_sql($sql, [$this->gradeitemid]);

        foreach ($this->userids as $userid) {
            $gradegrades[$userid]->gradeitem =& $this->gradeitem;

            $grade                  = new \stdClass();
            // This field needs to be named final.
            $grade->finalgrade      = $gradegrades[$userid]->finalgrade;

            // Added these properties to the object for this class.
            $grade->timemodified    = $gradegrades[$userid]->get_dategraded();
            $grade->timecreated     = $gradegrades[$userid]->timecreated;
            $grade->passed          = $grade->finalgrade >= $this->gradeitem->gradepass;

            // This is what was the default.
            $grade->locked          = $gradegrades[$userid]->is_locked();
            $grade->hidden          = $gradegrades[$userid]->is_hidden();
            $grade->overridden      = $gradegrades[$userid]->overridden;
            $grade->feedback        = $gradegrades[$userid]->feedback;
            $grade->feedbackformat  = $gradegrades[$userid]->feedbackformat;
            $grade->usermodified    = $gradegrades[$userid]->usermodified;
            $grade->dategraded      = $gradegrades[$userid]->get_dategraded();
            $grade->datesubmitted   = $gradegrades[$userid]->get_datesubmitted();
            $grade->transferstatus  = isset($transferedgrades[$userid])
                                        ? $transferedgrades[$userid]->status
                                        : \gradereport_twoa\transfergrade::STATUS_NOTREADY;
            $grade->gradeid         = $gradegrades[$userid]->id;

            // Create a text representation of the grade.
            if ($this->gradeitem->needsupdate) {
                $grade->grade          = false;
                $grade->str_grade      = get_string('error');
                $grade->str_long_grade = $grade->str_grade;
            } else if (is_null($grade->finalgrade)) {
                $grade->str_grade      = '-';
                $grade->str_long_grade = $grade->str_grade;
            } else {
                $grade->str_grade = grade_format_gradevalue($grade->finalgrade, $this->gradeitem);

                // Some pre conditions.
                $gradetypeisscale = $this->gradeitem->gradetype == GRADE_TYPE_SCALE;
                $gradeitemdisplaytypeisnotreal = $this->gradeitem->get_displaytype() != GRADE_DISPLAY_TYPE_REAL;

                // Now we have a tidier condition that is sort of readable.
                if ($gradetypeisscale || $gradeitemdisplaytypeisnotreal) {
                    $grade->str_long_grade  = $grade->str_grade;
                } else {
                    $a                      = new \stdClass();
                    $a->grade               = $grade->str_grade;
                    $a->max                 = grade_format_gradevalue($this->gradeitem->grademax, $this->gradeitem);
                    $grade->str_long_grade  = get_string('gradelong', 'grades', $a);
                }
            }

            // Create an html representation of the feedback.
            if (is_null($grade->feedback)) {
                $grade->str_feedback = '';
            } else {
                $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
            }

            // Put the user information in the grade object too.
            $userdetails = $DB->get_record(
                'user',
                ['id' => $userid],
                // We only want the users name and their email. We will also put in their id in case we need it.
                "id, firstname, lastname, email"
            );

            // Put some user details in the object.
            $grade->userid          = $userdetails->id;
            $grade->firstname       = $userdetails->firstname;
            $grade->lastname        = $userdetails->lastname;
            $grade->email           = $userdetails->email;
            $grade->userfullname    = "{$userdetails->firstname} {$userdetails->lastname}";

            // Set the time graded.
            $currenttime = time();
            $twoatimegraded = $currenttime;

            // Set the time graded to time created if time created isn't set.
            if (empty($grade->timecreated)) {
                $twoatimegraded = $grade->timemodified;
            }

            // Set the time back to the current time if time modified is also not set.
            if (empty($grade->timemodified)) {
                $twoatimegraded = $currenttime;
            }

            // Now format the time for the twoa_table class.
            $grade->twoatimegraded = date('Y/m/d', $twoatimegraded);

            // What is the name of the grade item.
            $grade->gradeitemname = $this->gradeitem->itemname;

            // Now assign the object to this users grade.
            $item->grades[$userid] = $grade;
        }

        $this->usergrades = $item;
    }

    /**
     * get_user_grade_info function.
     *
     * An accessor function used by the custom table class to get the data associated to a user.
     *
     * @param int $userid           The id of the user to retrieve information from.
     * @param string $property      The value of the property that we actually want to retrieve.
     * @return \stdClass|string     If a userid is given the
     */
    public function get_user_grade_info($userid = 0, $property = '') {
        // Todo: Should also check if property isn't empty but this still works.
        if (!empty($userid)) {
            // Todo: Check if the index for that userid actually exists.
            return $this->usergrades->grades[$userid]->$property;
        }
        return $this->usergrades;
    }

    /**
     * Gets the grade category ID Number
     * @return string
     */
    public function get_item_idnumber() {
        return $this->gradeitem->idnumber;
    }
}
