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
 * Trigger subplugin to include courses of certain categories which imply the course is older than X years
 * and a child of the category "Archivbereich"
 *
 * @package    lifecycletrigger_categoriesolderxyears
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lifecycle\trigger;

use coursecat;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\trigger_response;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../../lib.php');

/**
 * Class which implements the basic methods necessary for a life cycle trigger subplugin
 *
 * @package    lifecycletrigger_categoriesolderxyears
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categoriesolderxyears extends base_automatic {

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
     * Get the category names which are older than X years (categories older than 5 years are deleted anyways).
     *
     * @param int $triggerid
     * @return string[]
     * @throws \coding_exception
     * @throws \dml_exception
     */
    static public function get_category_names($triggerid) {
        // get setting for how many years in the past the trigger should start to trigger and convert in year
        $catsolderthanyears = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['years'];
        // mtrace("\n Setting: only courses older " . $catsolderthanyears . " years \n");
        $xyearago = date("y")-$catsolderthanyears;

        // look max five years back in time because the courses are deleted after 5 yers anyway
        $fiveyearago = date("y") - 5;
        $categorynames = [];
        // check if Workflow runs in  SS or WS
        if (date("m")>3 && date("m")<10) {
            // SS
            for ($x = $xyearago; $x >= $fiveyearago; $x--) {
                $categorynames[] = "WS" . ($x - 1) . "/" . $x;
                $categorynames[] = "SS" . ($x - 1);
            }
        } elseif(date("m")<4) {
            // WS beginning year
            for ($x = ($xyearago-1); $x >= $fiveyearago; $x--) {
                $categorynames[] = "SS" . $x;
                $categorynames[] = "WS" . ($x - 1) . "/" . $x;
            }
        } else {
            // WS ending year
            for ($x = $xyearago; $x >= $fiveyearago; $x--) {
                $categorynames[] = "SS" . $x;
                $categorynames[] = "WS" . ($x - 1) . "/" . $x;
            }
        }

        return $categorynames;
    }

    /**
     * Return sql sniplet for including the courses belonging to specific categories
     *
     * @param int $triggerid Id of the trigger.
     * @return array A list containing the constructed sql fragment and an array of parameters.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_course_recordset_where($triggerid) {
        global $DB;

        $categorynames = self::get_category_names($triggerid);
        $wherecats = [];
        $allcategories = \core_course_category::get_all();
        foreach ($allcategories as $cat) {
            $parentcat = $cat->get_parent_coursecat();

            //Überprüfe ob die Kategory älter als der eingestellte Wert und ein Kind des "Archivbereich" ist
            if (in_array($cat->name, $categorynames) && $parentcat->name == "Archivbereich") {
                $wherecats[] = $cat->id;
            }
        }
        // TODO: get_in_or_equal nimmt keine leeren arrays -> falls keine cats gefunden wurden bleibt $insql leer!?
        list($insql, $inparams) = $DB->get_in_or_equal($wherecats, SQL_PARAMS_NAMED);
        $where = "{course}.category {$insql}";
        return array($where, $inparams);
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'categoriesolderxyears';
    }
    /**
     * Defines which settings each instance of the subplugin offers for the user to define.
     * @return instance_setting[] containing settings keys and PARAM_TYPES
     */
    public function instance_settings() {
        return array(new instance_setting('years', PARAM_INT));
    }
    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function extend_add_instance_form_definition($mform) {
        $yearvaluearray = array_slice(range(0,4), 1, 4, true);
        $mform->addElement('select', 'years',
            get_string('years', 'lifecycletrigger_categoriesolderxyears'), $yearvaluearray);
        $mform->setType('years', PARAM_INT);

    }
}
