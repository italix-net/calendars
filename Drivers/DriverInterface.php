<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - DriverInterface
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars\Drivers;

use Italix\Calendars\CalendarSet;

/**
 * Interface for calendar rendering drivers.
 *
 * Each driver translates a CalendarSet configuration into the JSON config
 * format expected by a specific JS calendar library.
 *
 * Drivers produce a plain PHP array (not HTML, not JSON strings). The
 * consumer is responsible for json_encode() and embedding it in templates
 * — or can lean on the driver's own render_script() helper, where offered,
 * the same way Italix\DataSets drivers do.
 *
 * Example:
 *
 *     class FullCalendarDriver implements DriverInterface
 *     {
 *         public function get_name(): string { return 'full_calendar'; }
 *
 *         public function render(CalendarSet $calendar): array
 *         {
 *             // Build FullCalendar-specific config array
 *             return ['initialView' => 'dayGridMonth', 'events' => [...], ...];
 *         }
 *     }
 */
interface DriverInterface
{
    /**
     * Get the driver name.
     *
     * Used for registration and identification.
     *
     * @return string E.g. 'full_calendar', 'toast_ui'
     */
    public function get_name(): string;

    /**
     * Render a CalendarSet into the driver-specific configuration array.
     *
     * @param CalendarSet $calendar
     * @return array The JS library configuration
     */
    public function render(CalendarSet $calendar): array;
}
