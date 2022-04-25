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
 * Lang strings for specific date trigger
 *
 * @package lifecycletrigger_specificdate
 * @copyright  2018 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Specific date trigger';
$string['privacy:metadata'] = 'This subplugin does not store any personal data.';

$string['dates'] = 'Dates at which the workflow should run.';
$string['dates_desc'] = 'Write one date per line with the format Day.Month';
$string['dates_desc_help'] = 'One date per line for example: 04.08 , for 4th of august. If you are putting todays date it will be triggered';
$string['timelastrun'] = 'Date when the trigger last run.';
$string['dates_not_parseable'] = 'Dates must be of the format Day.Month';
