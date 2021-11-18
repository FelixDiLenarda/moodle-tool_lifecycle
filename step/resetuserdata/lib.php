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
 * Step subplugin to reset userdata in a course (assesments and homework submissions).
 *
 * @package    lifecyclestep resetuserdata
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\step;

use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

/**
 * Class which implements the basic methods necessary for a life cycle step subplugin
 * @package    lifecyclestep_resetuserdata
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resetuserdata extends libbase {

    /** @var int $numberofresets Resets done so far in this php call. */
    private static $numberofresets = 0;

    /**
     * Processes the course and returns a repsonse.
     * The response tells either
     *  - that the subplugin is finished processing.
     *  - that the subplugin is not yet finished processing.
     *  - that a rollback for this course is necessary.
     * @param int $processid of the respective process.
     * @param int $instanceid of the step instance.
     * @param mixed $course to be processed.
     * @return step_response
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function process_course($processid, $instanceid, $course) {
        global $DB;

        if (self::$numberofresets >= settings_manager::get_settings(
                $instanceid, settings_type::STEP)['maximumresetspercron']) {
            return step_response::waiting(); // Wait with further resets til the next cron run.
        }
        mtrace("Userdata reset for course: " . $course->fullname . "\n");

        // purge quizdata
        $dataquiz = new \stdClass();
        $dataquiz->courseid = $course->id;
        $dataquiz->reset_quiz_attempts = true;
        $dataquiz->reset_quiz_user_overrides = true;
        $dataquiz->reset_quiz_group_overrides = true;
        $dataquiz->timeshift = 0;
        quiz_reset_userdata($dataquiz);  //mod/quiz/lib.php

        // purge assignment data
        $dataassign = new \stdClass();
        $dataassign->courseid = $course->id;
        $dataassign->reset_assign_submissions = true;
        $dataassign->reset_assign_user_overrides = true;
        $dataassign->reset_assign_group_overrides = true;
        $dataassign->timeshift = 0;
        assign_reset_userdata($dataassign); //mod/assign/lib.php

        //purge coursegrade data
        grade_course_reset($course->id); //lib/gradelib.php

        //purge grade_grades_history
        $where = "id in (
            SELECT ggh.id FROM {grade_grades_history} ggh
            INNER JOIN {grade_items} gi ON gi.id = ggh.itemid
            WHERE gi.courseid = :courseid )";
        $DB->delete_records_select('grade_grades_history', "$where", [ 'courseid' => $course->id ]);

        self::$numberofresets++;
        return step_response::proceed();
    }

    /**
     * Processes the course in status waiting and returns a repsonse.
     * The response tells either
     *  - that the subplugin is finished processing.
     *  - that the subplugin is not yet finished processing.
     *  - that a rollback for this course is necessary.
     * @param int $processid of the respective process.
     * @param int $instanceid of the step instance.
     * @param mixed $course to be processed.
     * @return step_response
     */
    public function process_waiting_course($processid, $instanceid, $course) {
        return $this->process_course($processid, $instanceid, $course);
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'resetuserdata';
    }

    /**
     * Defines which settings each instance of the subplugin offers for the user to define.
     * @return instance_setting[] containing settings keys and PARAM_TYPES
     */
    public function instance_settings() {
        return array(
            new instance_setting('maximumresetspercron', PARAM_INT),
        );
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition($mform) {
        $elementname = 'maximumresetspercron';
        $mform->addElement('text', $elementname, get_string('resetuserdata_maximumresetspercron', 'lifecyclestep_resetuserdata'));
        $mform->setType($elementname, PARAM_INT);
        $mform->setDefault($elementname, 10);
    }

}
