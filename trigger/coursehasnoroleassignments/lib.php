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
 * Trigger subplugin to trigger courses that have no roleassignments of given roles
 * @package    lifecycletrigger_coursehasnousers
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lifecycle\trigger;

use tool_lifecycle\local\response\trigger_response;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');


/**
 * Class which implements the basic methods necessary for a life cycle trigger subplugin
 *
 * @package    lifecycletrigger_coursehasnoroleassignments
 * @copyright  2021 Felix Di Lenarda, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursehasnoroleassignments extends base_automatic {

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
     * Return sql sniplet for including all the courses that are not reachable anymore
     *
     * @param int $triggerid Id of the trigger.
     * @return array A list containing the constructed sql fragment and an array of parameters.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_course_recordset_where($triggerid) {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($this->get_roles($triggerid), SQL_PARAMS_NAMED);

        $where = "{course}.id IN (
            SELECT DISTINCT co.id FROM {course} co 
                JOIN {context} cxt ON co.id = cxt.instanceid AND cxt.contextlevel = 50
                LEFT JOIN {role_assignments} ra ON ra.contextid = cxt.id AND ra.roleid {$insql}
            WHERE ra.id is null )";
        return array($where, $params);
    }

    /**
     * Return the roles that were set in the config.
     * @param $triggerid int id of the trigger instance
     * @return array
     * @throws \coding_exception
     */
    private function get_roles($triggerid) {
        $roles = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['roles'];
        if ($roles === "") {
            throw new \coding_exception('No Roles defined');
        } else {
            $roles = explode(",", $roles);
        }
        return $roles;
    }
    /**
     * The return value should be equivalent with the name of the subplugin folder.
     * @return string technical name of the subplugin
     */
    public function get_subpluginname() {
        return 'coursehasnoroleassignments';
    }

    public function instance_settings() {
        return array(
            new instance_setting('roles', PARAM_SEQUENCE),
        );
    }

    public function extend_add_instance_form_definition($mform)
    {
        global $DB;
        $allroles = $DB->get_records('role', null, 'sortorder DESC');

        $rolenames = array();
        foreach ($allroles as $role) {
            $rolenames[$role->id] = empty($role->name) ? $role->shortname : $role->name;
        }
        $options = array(
            'multiple' => true,
        );
        $mform->addElement('autocomplete', 'roles',
            get_string('responsibleroles', 'lifecycletrigger_coursehasnoroleassignments'),
            $rolenames, $options);
        $mform->addHelpButton('roles', 'responsibleroles', 'lifecycletrigger_coursehasnoroleassignments');
        $mform->setType('roles', PARAM_SEQUENCE);
        $mform->addRule('roles', 'Test', 'required');
    }
}
