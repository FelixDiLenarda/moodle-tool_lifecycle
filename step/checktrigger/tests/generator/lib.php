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
 * lifecyclestep_checktrigger generator tests
 *
 * @package    lifecyclestep_checktrigger
 * @category   test
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
# TODO: wahrs auch step_manager und process * process_manager!?
use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\entity\step_subplugin;
use tool_lifecycle\local\entity\workflow;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\step_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\settings_type;

/**
 * lifecyclestep_checktrigger generator tests
 *
 * @package    lifecyclestep_checktrigger
 * @category   test
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lifecycle_step_checktrigger_generator extends testing_module_generator {

    /**
     * Creates a chosen trigger for an artificial workflow and the checktrigger step.
     * @param array $data Data which is used to fill the triggers with certain settings.
     * @return trigger_subplugin The created startdatedelay trigger.
     * @throws moodle_exception
     */
    public static function create_trigger_with_workflow_and_checktriggerstep($data) {
        // Create Workflow.
        $record = new stdClass();
        $record->id = null;
        $record->title = 'myworkflow';
        $workflow = workflow::from_record($record);
        workflow_manager::insert_or_update($workflow);
        // Create trigger.
        $record = new stdClass();
        $record->subpluginname = $data['triggersubpluginname'];
        $record->instancename = $data['triggerinstancename'];
        $record->workflowid = $workflow->id;
        $trigger = trigger_subplugin::from_record($record);
        trigger_manager::insert_or_update($trigger);
        // Create 'checktrigger'-Step
        $record = new stdClass();
        $record->subpluginname = 'checktrigger';
        $record->instancename = 'checktriggerTESTstep';
        $record->workflowid = $workflow->id;
        $step = step_subplugin::from_record($record);
        step_manager::insert_or_update($step);
        // Set 'checktrigger'-Step settings
        $settings = new stdClass();
        $settings->triggertocheck = 'categoriesolderxyears';
        settings_manager::save_settings($step->id, settings_type::STEP, $step->subpluginname, $settings);

        return array($trigger, $step);
    }
}
