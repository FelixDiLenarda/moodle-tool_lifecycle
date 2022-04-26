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
 * Trigger subplugin to check if courses contain gradings.
 * It is used to exclude courses for the resetuserdata-step which are already resetted
 *
 * @package    lifecycletrigger_gradesincourseexist
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lifecycle\trigger;

use tool_lifecycle\local\response\trigger_response;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

/**
 * Class which implements the basic methods necessary for a life cycle trigger subplugin
 *
 * @package    lifecycletrigger_gradesincourseexist
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradesincourseexist extends base_automatic {

    /**
     * Checks the course and returns a repsonse, which tells if the course should be further processed.
     *
     * @param object $course Course to be processed.
     * @param int $triggerid Id of the trigger instance.
     * @return trigger_response
     */
    public function check_course($course, $triggerid) {
        // Every decision is already in the where statement.
        return trigger_response::trigger();
    }

    /**
     * Return sql sniplet for including all the courses still containing gradingdata
     *
     * @param int $triggerid Id of the trigger.
     * @return array A list containing the constructed sql fragment and an array of parameters.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_course_recordset_where($triggerid) {
        $where = "{course}.id IN (SELECT DISTINCT c.id FROM {course} c
            INNER JOIN {grade_items} gi ON c.id = gi.courseid
            INNER JOIN {grade_grades} gg ON gi.id = gg.itemid
            WHERE gg.finalgrade IS NOT NULL AND (gi.itemmodule = 'assign' OR gi.itemmodule = 'quiz'))";
        $params = array();
        return array($where, $params);
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'gradesincourseexist';
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        //adding Triggerinfo
        $mform->addElement('html', get_string('info', 'lifecycletrigger_gradesincourseexist'));
    }

}
