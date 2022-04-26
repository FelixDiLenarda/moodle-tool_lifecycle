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
 * Trigger-Subplugin for the time delay.
 *
 * @package lifecycletrigger_timedelay
 * @copyright  2022 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\trigger;

use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\trigger_response;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../../lib.php');

/**
 * Class which implements the basic methods necessary for a cleanyp courses trigger subplugin
 * @package lifecycletrigger_timedelay
 * @copyright  2022 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timedelay extends base_automatic {

    /**
     * Checks the course and returns a repsonse, which tells if the course should be further processed.
     * @param object $course Course to be processed.
     * @param int $triggerid Id of the trigger instance.
     * @return trigger_response
     */
    public function check_course($course, $triggerid) {
        // Everything is already in the sql statement.
        return trigger_response::trigger();
    }

    /**
     * Return sql sniplet for comparing the current date to the chosen timefield of a course in combination with the specified delay.
     * @param int $triggerid Id of the trigger.
     * @return array A list containing the constructed sql fragment and an array of parameters.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_course_recordset_where($triggerid) {
        $delay = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['delay'];
        $dbtimefield = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['dbtimefield'];

        $where = "{course}.$dbtimefield < :timedelay";
        $params = array(
            "timedelay" => time() - $delay,
        );
        return array($where, $params);
    }

    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'timedelay';
    }

    /**
     * Defines which settings each instance of the subplugin offers for the user to define.
     * @return instance_setting[] containing settings keys and PARAM_TYPES
     */
    public function instance_settings() {
        return array(
            new instance_setting('delay', PARAM_INT),
            new instance_setting('dbtimefield', PARAM_ALPHA),
        );
    }

    /**
     * At the delay since the start date of a course.
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    public function extend_add_instance_form_definition($mform) {
        $mform->addElement('duration', 'delay', get_string('delay', 'lifecycletrigger_timedelay'));
        $mform->addHelpButton('delay', 'delay', 'lifecycletrigger_timedelay');

        $rolenames = array(
            "startdate" => "startdate",
            "enddate" => "enddate",
            "timecreated" => "timecreated",
            "timemodified" => "timemodified"
        );
        $options = array(
            'multiple' => false,
            'noselectionstring' => get_string('dbtimefield_noselection', 'lifecycletrigger_timedelay'),
        );
        $mform->addElement('autocomplete', 'dbtimefield',
            get_string('dbtimefield', 'lifecycletrigger_timedelay'),
            $rolenames, $options);
        $mform->setType('dbtimefield', PARAM_ALPHA);
        $mform->addRule('dbtimefield', 'Erforderlich', 'required');
    }

    /**
     * Reset the delay at the add instance form initializiation.
     * @param \MoodleQuickForm $mform
     * @param array $settings array containing the settings from the db.
     */
    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        if (is_array($settings) && array_key_exists('delay', $settings)) {
            $default = $settings['delay'];
        } else {
            $default = 16416000;
        }
        $mform->setDefault('delay', $default);
    }
}
