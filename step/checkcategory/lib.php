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
 * Step subplugin to check that a course still resides in a category.
 *
 * @package    lifecyclestep_checkcategory
 * @copyright  2019 Martin Gauk, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\step;

use coursecat;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../../trigger/matchingcategories/lib.php');

/**
 * Class which implements the basic methods necessary for a cleanyp courses trigger subplugin
 *
 * @package    lifecycletrigger_matchingcategories
 * @copyright  2019 Martin Gauk, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkcategory extends libbase {

    /**
     * Get the category names from the matching categories trigger plugin.
     *
     * @param int $processid
     * @return string[]
     */
    static private function get_category_names($processid) {
        static $cached = [];

        $process = process_manager::get_process_by_id($processid);
        $workflowid = $process->workflowid;

        if (isset($cached[$workflowid])) {
            return $cached[$workflowid];
        }

        $triggers = trigger_manager::get_triggers_for_workflow($workflowid);
        foreach ($triggers as $trigger) {
            if ($trigger->subpluginname === 'matchingcategories') {
                $cats = \tool_lifecycle\trigger\matchingcategories::get_category_names($trigger->id);
                $cached[$workflowid] = $cats;
                return $cats;
            }
        }

        mtrace('Could not find a matchingcategories trigger in the workflow.');
        $cached[$workflowid] = [];
        return [];
    }

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
        $catnames = self::get_category_names($processid);
        $cat = \core_course_category::get($course->category);
        if (in_array($cat->name, $catnames)) {
            mtrace("Checkcategory: Proceed course {$course->id}.");
            return step_response::proceed();
        }

        // The category name doesn't match one of the defined categories.
        mtrace("Checkcategory: Rollback course {$course->id} because it doesn't reside in one of the defined categories anymore.");
        return step_response::rollback();
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'checkcategory';
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        $mform->addElement('html', get_string('info', 'lifecyclestep_checkcategory'));
    }

}
