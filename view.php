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
 * Display the list of courses relevant for a specific user in a specific step instance.
 *
 * @package tool_cleanupcourses
 * @copyright  2017 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../../../config.php');

use tool_cleanupcourses\manager\step_manager;
use tool_cleanupcourses\manager\interaction_manager;
use tool_cleanupcourses\table\interaction_table;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new \moodle_url('/admin/tool/cleanupcourses/view.php'));

$action = optional_param('action', null, PARAM_ALPHA);
$processid = optional_param('processid', null, PARAM_INT);

$PAGE->set_title("Title");
$PAGE->set_heading("Heading");

$renderer = $PAGE->get_renderer('tool_cleanupcourses');

echo $renderer->header();

$controller = new \tool_cleanupcourses\view_controller();
$controller->handle_view();


echo $renderer->footer();
