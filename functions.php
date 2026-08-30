<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - Helper Functions
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars;

/**
 * Create a new CalendarSet.
 *
 * @return CalendarSet
 */
function calendar_set(): CalendarSet
{
    return new CalendarSet();
}
