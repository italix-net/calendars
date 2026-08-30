<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - CalendarFeedResponse
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars;

use RuntimeException;

/**
 * Response wrapper for calendar event-feed AJAX endpoints.
 *
 * Wraps a plain list of event arrays. Each event should follow FullCalendar's
 * event object shape (id, title, start, end, allDay, color, url,
 * extendedProps, ...); other drivers translate from this same shape so
 * controllers don't need to change when the driver changes.
 *
 * Example:
 *
 *     $events = $query->all();
 *     (new CalendarFeedResponse($events))->send();
 */
class CalendarFeedResponse
{
    /** @var array */
    private $events;

    /**
     * @param array $events List of event arrays
     */
    public function __construct(array $events)
    {
        $this->events = $events;
    }

    /**
     * Export as a plain array of events.
     *
     * @return array
     */
    public function to_array(): array
    {
        return $this->events;
    }

    /**
     * Export as JSON string.
     *
     * @param int $flags JSON encoding flags
     * @return string
     */
    public function to_json(int $flags = 0): string
    {
        $json = json_encode($this->events, $flags);
        if ($json === false) {
            throw new RuntimeException(
                'Failed to encode calendar feed to JSON: ' . json_last_error_msg()
            );
        }
        return $json;
    }

    /**
     * Send as an HTTP JSON response.
     *
     * @param int $flags JSON encoding flags
     * @return void
     */
    public function send(int $flags = 0): void
    {
        header('Content-Type: application/json');
        echo $this->to_json($flags);
    }
}
