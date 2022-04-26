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
 * Step test for checktrigger-Step.
 *
 * @package    lifecyclestep_checktrigger
 * TODO: @group      lifecycletrigger
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\step;

use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\manager\lib_manager;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\processor;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;
use stdClass;


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/generator/lib.php');

/**
 * Trigger test for categories trigger.
 *
 * @package    lifecycletrigger_categories
 * TODO: @group      lifecycletrigger
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lifecycle_step_checktrigger_testcase extends \advanced_testcase
{

    /** @var trigger_subplugin $excludetrigger Trigger instance that excludes a category. */
    private $excludetrigger;
    /** @var trigger_subplugin $includetrigger Trigger instance that includes a category. */
    private $includetrigger;

    /** @var \core_course_category $category A category. */
    private $category;
    /** @var \core_course_category $category A child category. */
    private $childcategory;

    /** @var processor $processor Instance of the lifecycle processor */
    private $processor;

    /**
     * Setup the testcase.
     * @throws \moodle_exception
     */
    public function setUp(): void
    {
        $this->resetAfterTest(true);
        $this->setAdminUser();


        $this->processor = new processor();
    }

    /**
     * Tests if a course, which is first triggered by categories-trigger is rolledback after the categorie changed.
     */
    public function test_for_categories_trigger()
    {
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $othercategory = $generator->create_category();
        $childcategory = $generator->create_category(array('parent' => $category->id));
        $categoryneverinclude = $generator->create_category();

        $course = $this->getDataGenerator()->create_course(array('category' => $category->id, 'fullname' => 'course'));
        $othercourse = $this->getDataGenerator()->create_course(array('category' => $othercategory->id, 'fullname' => 'othercourse'));
        $coursechildcategory = $this->getDataGenerator()->create_course(array('category' => $childcategory->id, 'fullname' => 'coursechildcategory'));
        $courseneverinclude = $this->getDataGenerator()->create_course(array('category' => $categoryneverinclude->id, 'fullname' => 'courseneverinclude'));

        // Which trigger do we test and what settings does it have
        $data = array(
            'triggersubpluginname' => 'categories',
            'triggerinstancename' => 'categoriesTrigger',
            'categories' => $othercategory->id . ',' . $category->id,
        );
        list($trigger, $step) = \tool_lifecycle_step_checktrigger_generator::create_trigger_with_workflow_and_checktriggerstep($data);
        $steplib = lib_manager::get_step_lib($step->subpluginname);

        // Set trigger settings so the courses are excluded. (individual for each trigger Subplugin
        $settings = new stdClass();
        $settings->categories = $data['categories'];
        // both categories are ECLUDED
        $settings->exclude = true;
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);
        //set Step settings
        $settings = new stdClass();
        $settings->triggertocheck = 'categories';
        settings_manager::save_settings($step->id, settings_type::STEP, $step->subpluginname, $settings);

        // Run the trigger and make sure none of the courses is triggered this time -> Recordset must be empty -> no step is processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);
        $foundfalse = false;
        $foundchildcatcourse = true;
        foreach ($recordset as $courseelement) {
            if ( in_array($courseelement->id, array($course->id, $othercourse) ) ) {
                $foundfalse = true;
            }
            elseif ( $courseelement->id == $coursechildcategory) {
                $foundchildcatcourse = false;
            }
        }
        $this->assertFalse($foundfalse, 'Found and triggered false course');
        $this->assertTrue($foundchildcatcourse, 'Did not find and trigger $coursechildcategory');
        $recordset->close();

        // Set trigger   settings so the courses are included.
        // both categories are INCLUDED
        $settings->exclude = false;
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);

        // Run the trigger and make sure all the courses except $courseneverinclude are triggered this time -> step will be processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);

        foreach ($recordset as $courseelement) {
            $found = false;
            $correctstepresult = false;
            mtrace(' ');
            mtrace('CourseelementID'.$courseelement->id);
            mtrace($courseelement->fullname);
            mtrace('Course'.$course->id);

            if ( $courseelement->id == SITEID) {
                mtrace('1kurs SITEID');
                // continue;
                //$found = true;
            }
            if ($courseelement->id == $course->id || $courseelement->id == $coursechildcategory->id ) {
                $found = true;
                $process = process_manager::create_process($courseelement->id, $step->workflowid);
                try {
                    $courseo = get_course($process->courseid);
                } catch (\dml_missing_record_exception $e) {
                    // Course no longer exists!
                    break;
                }
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                var_dump($result);
                mtrace('Fullanme'.$courseelement->fullname);

                if ($result == step_response::proceed()) {
                    $correctstepresult = true;
                    mtrace('course oder coursecildcategory TRUE');
                }
            } elseif ($courseelement->id == $othercourse->id) {
                $found = true;
                // TODO Cankge the Category of the $othercourse and process both triggered courses trough the checktrigger step
                $othercourse->category = $categoryneverinclude->id;
                update_course($othercourse);
                $process = process_manager::create_process($othercourse->id, $step->workflowid);
                try {
                    $coursea = get_course($process->courseid);
                } catch (\dml_missing_record_exception $e) {
                    // Course no longer exists!
                    break;
                }
                $result = $steplib->process_course($process->id, $step->id, $coursea);
                #var_dump($result);

                if ($result == step_response::rollback()) {
                    $correctstepresult = true;
                    mtrace('othercourseTRUE');
                }

            }
            $this->assertTrue($found, 'A wrong course was triggered');
            $this->assertTrue($correctstepresult, 'The checktrigger_step was wrong for a course');
        }
        $recordset->close();

        // TODO include testing delayed courses in this test? (What happens if checktrigger_step is run for delayed courses? is it possible

    }

    public function test_for_categoriesolderxyears_trigger()
    {
        $generator = $this->getDataGenerator();
        $categoryneverinclude = $generator->create_category();
        $archivcategory = $generator->create_category(array('name' => 'Archivbereich'));
        $childcategory = $generator->create_category(array('parent' => $archivcategory->id));
        // TODO function to dynamically create name so test will succeed in the future?
        $ss21category = $generator->create_category(array('name' => 'SS21', 'parent' => $archivcategory->id));
        $ss20category = $generator->create_category(array('name' => 'SS20', 'parent' => $archivcategory->id));
        $ss19category = $generator->create_category(array('name' => 'SS19', 'parent' => $archivcategory->id));

        $ss21course = $this->getDataGenerator()->create_course(array('category' => $ss21category->id, 'fullname' => 'ss21course'));
        $ss20course = $this->getDataGenerator()->create_course(array('category' => $ss20category->id, 'fullname' => 'ss20course'));
        $ss19course = $this->getDataGenerator()->create_course(array('category' => $ss19category->id, 'fullname' => 'ss19course'));

        $coursechildcategory = $this->getDataGenerator()->create_course(array('category' => $childcategory->id, 'fullname' => 'coursechildcategory'));
        $courseneverinclude = $this->getDataGenerator()->create_course(array('category' => $categoryneverinclude->id, 'fullname' => 'courseneverinclude'));

        // Which trigger do we test and what settings does it have
        $data = array(
            'triggersubpluginname' => 'categoriesolderxyears',
            'triggerinstancename' => 'categoriesolderxyearsTrigger',
            'years' => 1,
        );
        list($trigger, $step) = \tool_lifecycle_step_checktrigger_generator::create_trigger_with_workflow_and_checktriggerstep($data);
        $steplib = lib_manager::get_step_lib($step->subpluginname);

        // Set trigger settings so the courses are excluded. (individual for each trigger Subplugin
        $settings = new stdClass();
        $settings->years = $data['years'];
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);

        //TODO: diesen part auslagern in checktrigger_generator?
        //set Step settings
        $settings = new stdClass();
        $settings->triggertocheck = 'categoriesolderxyears';
        settings_manager::save_settings($step->id, settings_type::STEP, $step->subpluginname, $settings);

        // Run the trigger and make sure none of the courses is triggered this time -> Recordset must be empty -> no step is processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);
        foreach ($recordset as $courseelement) {
            $foundfalse = false;
            mtrace('COURSEELEMENT: ' . $courseelement->fullname);
            if ($courseelement->id != $ss20course->id && $courseelement->id != $ss19course->id) {
                mtrace('faundfalse = TRUE: ' . $courseelement->fullname);
                $foundfalse = true;
            }
            $correctresult = false;
            $process = process_manager::create_process($courseelement->id, $step->workflowid);
            if ($courseelement->id == $ss20course->id) {
                $ss20course->category = $categoryneverinclude->id;
                update_course($ss20course);
                $courseo = get_course($process->courseid);
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                if ($result == step_response::rollback()) {
                    $correctresult = true;
                }
            } elseif ($courseelement->id == $ss19course->id){
                $courseo = get_course($process->courseid);
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                if ($result == step_response::proceed()) {
                    $correctresult = true;
                }
            }
            $this->assertFalse($foundfalse, 'Found and triggered false course');
            $this->assertTrue($correctresult, 'The checktrigger_step was wrong for a course');
        }
        $recordset->close();


        /*if ( in_array($courseelement->id, array($course->id, $othercourse) ) ) {
            $foundfalse = true;
        }
        elseif ( $courseelement->id == $coursechildcategory) {
            $foundchildcatcourse = false;
        }*/



        /*// Set trigger   settings so the courses are included.
        // both categories are INCLUDED
        $settings->exclude = false;
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);

        // Run the trigger and make sure all the courses except $courseneverinclude are triggered this time -> step will be processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);

        foreach ($recordset as $courseelement) {
            $found = false;
            $correctstepresult = false;
            mtrace(' ');
            mtrace('CourseelementID'.$courseelement->id);
            mtrace($courseelement->fullname);
            mtrace('Course'.$course->id);

            if ( $courseelement->id == SITEID) {
                mtrace('1kurs SITEID');
                // continue;
                //$found = true;
            }
            if ($courseelement->id == $course->id || $courseelement->id == $coursechildcategory->id ) {
                $found = true;
                $process = process_manager::create_process($courseelement->id, $step->workflowid);
                try {
                    $courseo = get_course($process->courseid);
                } catch (\dml_missing_record_exception $e) {
                    // Course no longer exists!
                    break;
                }
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                var_dump($result);
                mtrace('Fullanme'.$courseelement->fullname);

                if ($result == step_response::proceed()) {
                    $correctstepresult = true;
                    mtrace('course oder coursecildcategory TRUE');
                }
            } elseif ($courseelement->id == $othercourse->id) {
                $found = true;
                // TODO Cankge the Category of the $othercourse and process both triggered courses trough the checktrigger step
                $othercourse->category = $categoryneverinclude->id;
                update_course($othercourse);
                $process = process_manager::create_process($othercourse->id, $step->workflowid);
                try {
                    $coursea = get_course($process->courseid);
                } catch (\dml_missing_record_exception $e) {
                    // Course no longer exists!
                    break;
                }
                $result = $steplib->process_course($process->id, $step->id, $coursea);
                #var_dump($result);

                if ($result == step_response::rollback()) {
                    $correctstepresult = true;
                    mtrace('othercourseTRUE');
                }

            }
            $this->assertTrue($found, 'A wrong course was triggered');
            $this->assertTrue($correctstepresult, 'The checktrigger_step was wrong for a course');
        }*/
    }
// TODO write a test with more than one triggers
}