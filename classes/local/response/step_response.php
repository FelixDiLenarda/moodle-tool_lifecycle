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
 * Possible Responses of a Subplugin
 *
 * @package tool_lifecycle
 * @copyright  2017 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lifecycle\local\response;

/**
 * Possible Responses of a Subplugin
 *
 * @package tool_lifecycle
 * @copyright  2017 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_response {

    /** @var string Proceed the workflow to the next step. */
    const PROCEED = 'proceed';
    /** @var string The step is still processing the course and probably waiting for some interaction. */
    const WAITING = 'waiting';
    /** @var string The process should be rolled back. */
    const ROLLBACK = 'rollback';

    /** @var string Value of the response. */
    private $value;

    /**
     * Creates an instance of a SubpluginResponse
     * @param string $responsetype code of the response
     */
    private function __construct($responsetype) {
        $this->value = $responsetype;
    }

    /**
     * Creates a step_response telling that the subplugin finished processing the course.
     */
    public static function proceed() {
        return new step_response(self::PROCEED);
    }

    /**
     * Creates a step_response telling that the subplugin is still processing the course.
     */
    public static function waiting() {
        return new step_response(self::WAITING);
    }

    /**
     * Creates a step_response telling that a rollback for the process of this course is necessary.
     */
    public static function rollback() {
        return new step_response(self::ROLLBACK);
    }



}
