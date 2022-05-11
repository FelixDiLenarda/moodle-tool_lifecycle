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
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\step;

use tool_lifecycle\local\manager\lib_manager;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\processor;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;
use stdClass;


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/generator/lib.php');

/**
 * Step test for checktrigger-Step.
 *
 * @package    lifecyclestep_checktrigger
 * @copyright  2022 Felix Di Lnearda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lifecycle_step_checktrigger_testcase extends \advanced_testcase
{
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
    public function test_for_categories_trigger() {
        $triggers = trigger_manager::get_trigger_types();
        if (!array_key_exists("categories", $triggers)) {
            mtrace('test_for_categories_trigger ABORTED because categories-trigger is not installed');
            return;
        }
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $othercategory = $generator->create_category();
        $childcategory = $generator->create_category(array('parent' => $category->id));
        $categoryneverinclude = $generator->create_category();
        $course = $this->getDataGenerator()->create_course(array('category' => $category->id, 'fullname' => 'course'));
        $othercourse = $this->getDataGenerator()->create_course(array('category' => $othercategory->id, 'fullname' => 'othercourse'));
        $coursechildcategory = $this->getDataGenerator()->create_course(array('category' => $childcategory->id, 'fullname' => 'coursechildcategory'));

        // Which trigger do we test and what settings does it have
        $data = array(
            'triggersubpluginname' => 'categories',
            'triggerinstancename' => 'categoriesTrigger',
            'settings' => array(
                'categories' => $othercategory->id . ',' . $category->id,
                'exclude' => true, ),
        );
        list($trigger, $step) = \tool_lifecycle_step_checktrigger_generator::create_trigger_with_workflow_and_checktriggerstep($data);
        $steplib = lib_manager::get_step_lib($step->subpluginname);

        // Run the trigger and get the courserecordset of the triggered courses
        $recordset = $this->processor->get_course_recordset([$trigger], []);
        $foundfalse = false;
        $foundchildcatcourse = false; // childcategories of the set categories are also excluded here
        foreach ($recordset as $courseelement) {
            if ( in_array($courseelement->id, array($course->id, $othercourse->id) ) ) {
                $foundfalse = true;
            }
            elseif ( $courseelement->id == $coursechildcategory) {
                $foundchildcatcourse = true;
            }
        }
        $this->assertFalse($foundfalse, 'Found and triggered false course');
        $this->assertFalse($foundchildcatcourse, 'Found and triggered $coursechildcategory');
        $recordset->close();

        // Set trigger   settings so the courses are included.
        $settings = new stdClass();
        $settings->exclude = false;         // both categories and childcategories are INCLUDED
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);

        // Run the trigger and make sure all the courses are triggered this time -> step will be processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);
        $found = 0;
        foreach ($recordset as $courseelement) {
            if ($courseelement->id == $course->id || $courseelement->id == $coursechildcategory->id ) {
                $correctstepresult = false;
                $found++;
                $process = process_manager::create_process($courseelement->id, $step->workflowid);
                try {
                    $courseo = get_course($process->courseid);
                } catch (\dml_missing_record_exception $e) {
                    // Course no longer exists!
                    break;
                }
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                if ($result == step_response::proceed()) {
                    $correctstepresult = true;
                }
                $this->assertTrue($correctstepresult, 'The checktrigger_step was wrong for a course');
            } elseif ($courseelement->id == $othercourse->id) {
                $correctstepresult = false;
                $found++;
                // Change the Category of the $othercourse and make sure the checktrigger step rolls back the course
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
                if ($result == step_response::rollback()) {
                    $correctstepresult = true;
                    mtrace('othercourseTRUE');
                }
                $this->assertTrue($correctstepresult, 'The checktrigger_step was wrong for a course');
            }
        }
        $this->assertEquals(3, $found, 'A wrong number of courses was triggered');
        $recordset->close();
    }

    /**
     * Trigger test for categories trigger.
     */
    public function test_for_categoriesolderxyears_trigger() {
        $triggers = trigger_manager::get_trigger_types();
        if (!array_key_exists("categoriesolderxyearss", $triggers)) {
            mtrace('test_for_categoriesolderxyears_trigger ABORTED because categoriesolderxyears-trigger is not installed');
            return;
        }
        $generator = $this->getDataGenerator();
        $categoryneverinclude = $generator->create_category();
        $archivcategory = $generator->create_category(array('name' => 'Archivbereich'));
        $ss20category = $generator->create_category(array('name' => 'SS20', 'parent' => $archivcategory->id));
        $ss19category = $generator->create_category(array('name' => 'SS19', 'parent' => $archivcategory->id));
        $ss20course = $this->getDataGenerator()->create_course(array('category' => $ss20category->id, 'fullname' => 'ss20course'));
        $ss19course = $this->getDataGenerator()->create_course(array('category' => $ss19category->id, 'fullname' => 'ss19course'));

        // Which trigger do we test and what settings does it have
        $data = array(
            'triggersubpluginname' => 'categoriesolderxyears',
            'triggerinstancename' => 'categoriesolderxyearsTrigger',
            'settings' => array( 'years' => 1 ),
        );
        list($trigger, $step) = \tool_lifecycle_step_checktrigger_generator::create_trigger_with_workflow_and_checktriggerstep($data);
        $steplib = lib_manager::get_step_lib($step->subpluginname);

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
    }

    /**
     * Trigger test for timedelay trigger.
     */
    public function test_for_timedelay_trigger() {
        $triggers = trigger_manager::get_trigger_types();
        if (!array_key_exists("timedelay", $triggers)) {
            mtrace('test_for_timedelay_trigger ABORTED because timedelay-trigger is not installed');
            return;
        }
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $othercategory = $generator->create_category();
        $course = $this->getDataGenerator()->create_course(array(
            'category' => $category->id,
            'fullname' => 'course',
            'enddate' => strtotime('now + 31622400 seconds'),  //  equals 366 days
        ));
        $othercourse = $this->getDataGenerator()->create_course(array(
            'category' => $othercategory->id,
            'fullname' => 'othercourse',
            'startdate' => strtotime('now - 41622400 seconds'), //  startdate has to be before enddate
            'enddate' => strtotime('now - 31622400 seconds'),  //  equals 366 days
        ));
        $thirdcourse = $this->getDataGenerator()->create_course(array(
            'category' => $othercategory->id,
            'fullname' => 'thirdcourse',
            'startdate' => strtotime('now - 41622400 seconds'), //  startdate has to be before enddate
            'enddate' => strtotime('now - 31622400 seconds'),  //  equals 366 days
        ));
        mtrace('Othercourseid: ' . $othercourse->id . '|   Thirdcourseid: ' . $thirdcourse->id);

        // Which trigger do we test and what settings does it have
        $data = array(
            'triggersubpluginname' => 'timedelay',
            'triggerinstancename' => 'timedelayModifiedTrigger',
            'settings' => array(
                'dbtimefield' => 'enddate',
                'delay' => 31536000,  // equals 365 days
            )
        );
        list($trigger, $step) = \tool_lifecycle_step_checktrigger_generator::create_trigger_with_workflow_and_checktriggerstep($data);
        $steplib = lib_manager::get_step_lib($step->subpluginname);
        // Run the trigger and make sure none of the courses is triggered this time -> Recordset must be empty -> no step is processed
        $recordset = $this->processor->get_course_recordset([$trigger], []);
        $notcourse = true;
        $othercoursetriggered = false;
        foreach ($recordset as $courseelement) {
            if ($courseelement->id == $course->id) {
                $notcourse = false;
            }
            elseif ($courseelement->id == $othercourse->id) {
                $othercoursetriggered = true;
                $process = process_manager::create_process($courseelement->id, $step->workflowid);
                $courseo = get_course($process->courseid);
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                $correctresultone = false;
                if ($result == step_response::proceed()) {
                    $correctresultone = true;
                }
            } elseif (($courseelement->id == $thirdcourse->id)) {
                // change enddate for $othercourse so it will be rolled back
                $thirdcourse->enddate = strtotime('now');
                update_course($thirdcourse);
                $process = process_manager::create_process($courseelement->id, $step->workflowid);
                $courseo = get_course($process->courseid);
                $result = $steplib->process_course($process->id, $step->id, $courseo);
                $correctresulttwo = false;
                if ($result == step_response::rollback()) {
                    $correctresulttwo = true;
                }
            }
        }
        $this->assertTrue($notcourse, '$course was triggered');
        $this->assertTrue($othercoursetriggered, '$othercourse was not triggered');
        $this->assertTrue($correctresultone, 'wrong step_response one');
        $this->assertTrue($correctresulttwo, 'wrong step_response two');

    }

// TODO write a test with more than one triggers
}