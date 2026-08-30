<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - CalendarFeedRequest
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars;

use DateTimeImmutable;
use Exception;
use Italix\Contracts\DataContainer;

/**
 * Parses an incoming calendar event-feed request.
 *
 * JS calendar libraries fetch events for the currently visible date range,
 * sending 'start'/'end' (ISO 8601) plus any extra params configured via
 * CalendarSet::ajax_params() / CalendarSource::ajax_params().
 *
 * Example:
 *
 *     $freq = CalendarFeedRequest::from_globals();
 *     $rows = $dm->query(
 *         'SELECT ... FROM calendar_event WHERE start_dt < ? AND end_dt > ?',
 *         [$freq->end()?->format('Y-m-d H:i:s'), $freq->start()?->format('Y-m-d H:i:s')]
 *     );
 */
class CalendarFeedRequest
{
    /** @var array */
    private $params;

    /**
     * @param array $params The request parameters
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * Create from PHP superglobals ($_GET for GET, $_POST for POST).
     *
     * @return self
     */
    public static function from_globals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $params = strtoupper($method) === 'POST' ? $_POST : $_GET;

        return new self($params);
    }

    /**
     * Create from a custom array (useful for testing or framework integration).
     *
     * @param array $params
     * @return self
     */
    public static function from_array(array $params): self
    {
        return new self($params);
    }

    /**
     * Create from a DataContainer (e.g. from RequestInput::get()).
     *
     * @param DataContainer $data
     * @return self
     */
    public static function from(DataContainer $data): self
    {
        return new self($data->to_array());
    }

    /**
     * Get the start of the visible date range.
     *
     * @return DateTimeImmutable|null
     */
    public function start(): ?DateTimeImmutable
    {
        return $this->parse_date($this->params['start'] ?? null);
    }

    /**
     * Get the end of the visible date range (exclusive).
     *
     * @return DateTimeImmutable|null
     */
    public function end(): ?DateTimeImmutable
    {
        return $this->parse_date($this->params['end'] ?? null);
    }

    /**
     * Get the browser timezone name sent alongside the range, if any.
     *
     * @return string|null
     */
    public function timezone(): ?string
    {
        $tz = $this->params['timeZone'] ?? $this->params['timezone'] ?? null;

        // `?timeZone[]=x` arrives as an array. Casting one yields the literal
        // word "Array", which is not a timezone but looks like a value — the
        // caller passes it to DateTimeZone and gets the exception one layer
        // further away from the cause.
        if (!is_scalar($tz)) {
            return null;
        }

        return $tz !== '' ? (string) $tz : null;
    }

    // =========================================================================
    // Raw Access
    // =========================================================================

    /**
     * Get any raw parameter by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Get all raw parameters.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->params;
    }

    /**
     * @param mixed $value
     * @return DateTimeImmutable|null
     */
    private function parse_date($value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable((string)$value);
        } catch (Exception $e) {
            return null;
        }
    }
}
