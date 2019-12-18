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
 * Trigger subplugin to include courses of certain categories.
 *
 * @package    lifecycletrigger_matchingcategories
 * @copyright  2019 Martin Gauk, innoCampus, TU Berlin
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
 * Class which implements the basic methods necessary for a cleanyp courses trigger subplugin
 *
 * @package    lifecycletrigger_matchingcategories
 * @copyright  2019 Martin Gauk, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matchingcategories extends base_automatic {

    /**
     * Checks the course and returns a repsonse, which tells if the course should be further processed.
     * @param object $course Course to be processed.
     * @param int $triggerid Id of the trigger instance.
     * @return trigger_response
     */
    public function check_course($course, $triggerid) {
        // Every decision is already in the where statement.
        return trigger_response::trigger();
    }

    /**
     * Get the category names defined in the settings for the trigger.
     *
     * @param int $triggerid
     * @return string[]
     * @throws \coding_exception
     * @throws \dml_exception
     */
    static public function get_category_names($triggerid) {
        $setting = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['categories'];
        $categorynames = explode("\n", $setting);
        return array_map('trim', $categorynames);
    }

    /**
     * Return sql sniplet for including (or excluding) the courses belonging to specific categories
     * and all their children.
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
            if (in_array($cat->name, $categorynames)) {
                $wherecats[] = $cat->id;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($wherecats, SQL_PARAMS_NAMED);
        $where = "{course}.category {$insql}";
        return array($where, $inparams);
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'matchingcategories';
    }

    /**
     * Defines which settings each instance of the subplugin offers for the user to define.
     * @return instance_setting[] containing settings keys and PARAM_TYPES
     */
    public function instance_settings() {
        return array(
            new instance_setting('categories', PARAM_TEXT),
        );
    }

    /**
     * This method can be overriden, to add form elements to the form_step_instance.
     * It is called in definition().
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function extend_add_instance_form_definition($mform) {
        $mform->addElement('textarea', 'categories',
                get_string('categories_setting', 'lifecycletrigger_matchingcategories'),
                ['rows' => 10, 'cols' => 50]);
        $mform->setType('categories', PARAM_TEXT);
    }

}
