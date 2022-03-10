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
 * Step subplugin to check that a course still meets the prerequisites of a specific trigger.
 *
 * @package    lifecyclestep_checktrigger
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\step;

use coursecat;
use tool_lifecycle\local\manager\lib_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\local\response\trigger_response;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../../trigger/matchingcategories/lib.php');
require_once(__DIR__ . '/../../trigger/categoriesolderxyears/lib.php');


/**
 * Class which implements the basic methods necessary for a cleanyp courses trigger subplugin
 *
 * @package    lifecyclestep_checktrigger
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checktrigger extends libbase {

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

        $process = process_manager::get_process_by_id($processid);
        $workflowid = $process->workflowid;
        $triggers = trigger_manager::get_triggers_for_workflow($workflowid);
        $triggertocheck = settings_manager::get_settings($instanceid, settings_type::STEP)['triggertocheck'];

        foreach ($triggers as $trigger) {
            if ($trigger->subpluginname === $triggertocheck) {
                mtrace('Triggerplugin: ' . $trigger->subpluginname . " GEFUNDEN");
                $lib = lib_manager::get_automatic_trigger_lib($trigger->subpluginname);
                $params = ['courseid' => $course->id ];
                $sql = 'SELECT {course}.* from {course} '.
                    'WHERE {course}.id = :courseid';

                list($wheresql, $whereparams) = $lib->get_course_recordset_where($trigger->id);
                if (!empty($wheresql)) {
                    $sql .= ' AND ' . $wheresql;
                    $params = array_merge($whereparams, $params);
                }
                # TODO bedenken was passiert wenn die kurse im trigger ausgeschlossen und nicht inkludiert werden -> scheint zu funktonieren (getestet für Kategorietrigger)

                if ( $DB->record_exists_sql($sql, $params)) {
                    $response = $lib->check_course($course, $trigger->id);
                    if ($response == trigger_response::next()) {
                        mtrace($course->id . ' NEXT -> ROLLBACK');
                        return step_response::rollback();
                    }
                    if ($response == trigger_response::exclude()) {
                        mtrace($course->id . ' EXCLUDE -> ROLLBACK');
                        return step_response::rollback();
                    }
                    if ($response == trigger_response::trigger()) {
                        mtrace($course->id . ' TRIGGER -> PROCEED');
                        return step_response::proceed();
                    }
                }
                else {
                    mtrace('ROLLBACK');
                    return step_response::rollback();
                }
            }

        }
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'checktrigger';
    }
    /**
     * Defines which settings each instance of the subplugin offers for the user to define.
     * @return instance_setting[] containing settings keys and PARAM_TYPES
     */

    public function instance_settings() {
        return array(
            new instance_setting('triggertocheck', PARAM_COMPONENT),
        );
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition($mform) {
        $alltriggers = trigger_manager::get_chooseable_trigger_types();
        $mform->addElement('autocomplete', 'triggertocheck',
            get_string('availabletriggers', 'lifecyclestep_checktrigger'),
            $alltriggers);
        $mform->setType('triggertocheck', PARAM_COMPONENT);
        $mform->addRule('triggertocheck', 'Required', 'required');
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        $mform->addElement('html', get_string('info', 'lifecyclestep_checktrigger'));
    }

}
