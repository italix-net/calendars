<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - CalendarSet
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars;

use Italix\Calendars\Drivers\DriverInterface;
use Generator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Core calendar view definition.
 *
 * CalendarSet configures a calendar (which generic views are available,
 * locale, editability, event feed) independently of which JS calendar
 * library ultimately renders it. A driver translates the configuration
 * into that library's own init options — swap the driver, keep every
 * controller and view untouched.
 *
 * Example:
 *
 *     $cal = new CalendarSet();
 *     $cal->views(['month', 'week', 'day', 'list'])->initial_view('month');
 *     $cal->ajax_url('/it/admin/calendar/events.json');
 *     $cal->editable(true)->selectable(true);
 *
 *     $cal->on('event_click', 'on_event_click');
 *     $cal->on('select', 'on_range_select');
 *     $cal->on('event_drop', 'on_event_moved');
 *
 *     // Aggregate several independent feeds in one calendar (e.g. one
 *     // one per source), each colored differently:
 *     $cal->source('team_12')->label('Team A')->color('#2f6fed')
 *         ->ajax_url('/it/admin/calendar/events.json')->ajax_params(['team_id' => 12]);
 *     $cal->source('team_7')->label('Team B')->color('#e0a327')
 *         ->ajax_url('/it/admin/calendar/events.json')->ajax_params(['team_id' => 7]);
 *
 *     $driver = new FullCalendarDriver();
 *     $js = $driver->render_script($cal, '#my-calendar');
 */
class CalendarSet
{
    /** Generic view keys understood by every driver. */
    public const VIEWS = ['month', 'week', 'day', 'list'];

    /**
     * The events a calendar can report, in this library's own vocabulary.
     *
     * Whitelisted for the same reason `VIEWS` is, and with more cause: a driver
     * translates these into its own names, and FullCalendar's happen to be the
     * camelCase versions. Somebody reading that library's documentation types
     * `on('eventClick', …)`, this stored it, the driver did not recognise it,
     * and the callback was dropped — no error, and a calendar whose clicks do
     * nothing. Refusing the name says so at the line that wrote it.
     */
    public const EVENTS = ['event_click', 'date_click', 'select', 'event_drop', 'event_resize'];

    /** @var string[] Generic view keys the user can switch between */
    private $views = self::VIEWS;

    /** @var string Generic view key shown on first load */
    private $initial_view = 'month';

    /** @var bool Whether to show the built-in prev/next/today/view-switch chrome */
    private $toolbar = true;

    /** @var string|null Locale code (e.g. 'it', 'en'); null = driver default */
    private $locale = null;

    /** @var int First day of the week: 0 = Sunday, 1 = Monday, ... 6 = Saturday */
    private $first_day = 1;

    /** @var bool Whether events can be dragged/resized by the user */
    private $editable = false;

    /** @var bool Whether dragging/clicking an empty slot fires the 'select' event */
    private $selectable = false;

    /** @var string|null AJAX URL for the default (single) event feed */
    private $ajax_url = null;

    /** @var array Extra parameters sent with every event feed request */
    private $ajax_params = [];

    /** @var array<string, CalendarSource> Named additional event sources */
    private $sources = [];

    /** @var string|null Unique identifier (used for the HTML container id) */
    private $id = null;

    /** @var string|null CSS class for the calendar container */
    private $css_class = null;

    /** @var string|null Calendar height (CSS value, e.g. '700px', '80vh') */
    private $height = null;

    /** @var array<string, string> Event name => JS callback function name */
    private $events = [];

    /** @var array Extra driver-specific options merged at the top level */
    private $extra = [];

    // =========================================================================
    // Views
    // =========================================================================

    /**
     * Set which generic views the user can switch between.
     *
     * Generic keys: 'month', 'week', 'day', 'list'. Each driver maps these
     * to its own view names (e.g. FullCalendar: dayGridMonth / timeGridWeek /
     * timeGridDay / listWeek).
     *
     * @param string[] $views
     * @return self
     * @throws InvalidArgumentException If an unknown view key is given
     */
    public function views(array $views): self
    {
        foreach ($views as $view) {
            if (!in_array($view, self::VIEWS, true)) {
                throw new InvalidArgumentException(
                    "Unknown calendar view '{$view}'. Allowed: " . implode(', ', self::VIEWS)
                );
            }
        }

        $this->views = $views;
        return $this;
    }

    /**
     * Set the view shown when the calendar first loads.
     *
     * @param string $view One of the generic view keys
     * @return self
     * @throws InvalidArgumentException If the view key is unknown
     */
    public function initial_view(string $view): self
    {
        if (!in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException(
                "Unknown calendar view '{$view}'. Allowed: " . implode(', ', self::VIEWS)
            );
        }

        $this->initial_view = $view;
        return $this;
    }

    // =========================================================================
    // Display Configuration
    // =========================================================================

    /**
     * Show or hide the built-in navigation/view-switch chrome.
     *
     * @param bool $enabled
     * @return self
     */
    public function toolbar(bool $enabled = true): self
    {
        $this->toolbar = $enabled;
        return $this;
    }

    /**
     * Set the calendar locale.
     *
     * @param string $locale E.g. 'it', 'en'
     * @return self
     */
    public function locale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Set the first day of the week.
     *
     * @param int $day 0 = Sunday, 1 = Monday, ... 6 = Saturday
     * @return self
     */
    public function first_day(int $day): self
    {
        $this->first_day = $day;
        return $this;
    }

    /**
     * Set a unique identifier for this calendar.
     *
     * @param string $id
     * @return self
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set a CSS class for the calendar container.
     *
     * @param string $css_class
     * @return self
     */
    public function css_class(string $css_class): self
    {
        $this->css_class = $css_class;
        return $this;
    }

    /**
     * Set the calendar height.
     *
     * @param string $height CSS value, e.g. '700px', '80vh'
     * @return self
     */
    public function height(string $height): self
    {
        $this->height = $height;
        return $this;
    }

    // =========================================================================
    // Interaction
    // =========================================================================

    /**
     * Allow events to be dragged and resized by the user.
     *
     * Persisting the change is left to the 'event_drop' / 'event_resize'
     * callbacks registered via on() — this only enables the gesture.
     *
     * @param bool $enabled
     * @return self
     */
    public function editable(bool $enabled = true): self
    {
        $this->editable = $enabled;
        return $this;
    }

    /**
     * Allow clicking/dragging on empty date slots to select a range.
     *
     * Fires the 'select' event registered via on(), typically used to
     * open a "new event" form pre-filled with the selected range.
     *
     * @param bool $enabled
     * @return self
     */
    public function selectable(bool $enabled = true): self
    {
        $this->selectable = $enabled;
        return $this;
    }

    // =========================================================================
    // Event Feed
    // =========================================================================

    /**
     * Set the AJAX URL for the default (single) event feed.
     *
     * The driver fetches events for the visible date range by calling this
     * URL with 'start'/'end' query parameters (parse them with
     * CalendarFeedRequest). Ignored once one or more source() feeds are
     * defined instead.
     *
     * @param string $url
     * @return self
     */
    public function ajax_url(string $url): self
    {
        $this->ajax_url = $url;
        return $this;
    }

    /**
     * Set extra parameters to send with every event feed request.
     *
     * @param array $params
     * @return self
     */
    public function ajax_params(array $params): self
    {
        $this->ajax_params = array_merge($this->ajax_params, $params);
        return $this;
    }

    // =========================================================================
    // Multiple Sources
    // =========================================================================

    /**
     * Get or create a named event source.
     *
     * Use this to aggregate several independent event feeds in a single
     * calendar view — e.g. one source per team, each with its own color —
     * so an internal "all teams" view can be built without merging data
     * server-side.
     *
     * @param string $name
     * @return CalendarSource
     */
    public function source(string $name): CalendarSource
    {
        if (!isset($this->sources[$name])) {
            $this->sources[$name] = new CalendarSource($name);
        }

        return $this->sources[$name];
    }

    /**
     * Check if any additional sources are configured.
     *
     * @return bool
     */
    public function has_sources(): bool
    {
        return !empty($this->sources);
    }

    /**
     * Iterate over the configured sources, in definition order.
     *
     * @return Generator<string, CalendarSource>
     */
    public function each_source(): Generator
    {
        foreach ($this->sources as $name => $source) {
            yield $name => $source;
        }
    }

    // =========================================================================
    // Events
    // =========================================================================

    /**
     * Register a JS callback for a calendar interaction event.
     *
     * The callback function name refers to a function in the global scope
     * (window.functionName) that the JS bootstrap calls when the event
     * fires. Persisting any resulting change (moving/resizing an event,
     * creating one from a selection, ...) is the callback's responsibility,
     * same convention as DataSet::on().
     *
     * Built-in events:
     * - 'event_click'   — callback(info) — an existing event was clicked
     * - 'date_click'    — callback(info) — an empty date/slot was clicked
     * - 'select'        — callback(info) — a date range was selected
     * - 'event_drop'    — callback(info) — an event was dragged to a new date
     * - 'event_resize'  — callback(info) — an event's duration was resized
     *
     * @param string $event
     * @param string $callback JS function name (must exist in window scope)
     * @return self
     */
    public function on(string $event, string $callback): self
    {
        if (!in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException(
                "Unknown calendar event '{$event}'. Allowed: " . implode(', ', self::EVENTS)
                . '. These are this library\'s names, not a driver\'s — FullCalendar spells them '
                . 'in camelCase and the driver translates.'
            );
        }

        $this->events[$event] = $callback;
        return $this;
    }

    /**
     * Get all registered event callbacks.
     *
     * @return array<string, string>
     */
    public function get_events(): array
    {
        return $this->events;
    }

    // =========================================================================
    // Extra
    // =========================================================================

    /**
     * Set extra driver-specific options merged at the top level.
     *
     * @param array $options
     * @return self
     */
    public function extra(array $options): self
    {
        $this->extra = array_merge($this->extra, $options);
        return $this;
    }

    /** @return array */
    public function get_extra(): array
    {
        return $this->extra;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /** @return string[] */
    public function get_views(): array
    {
        return $this->views;
    }

    /** @return string */
    public function get_initial_view(): string
    {
        return $this->initial_view;
    }

    /** @return bool */
    public function has_toolbar(): bool
    {
        return $this->toolbar;
    }

    /** @return string|null */
    public function get_locale(): ?string
    {
        return $this->locale;
    }

    /** @return int */
    public function get_first_day(): int
    {
        return $this->first_day;
    }

    /** @return string|null */
    public function get_id(): ?string
    {
        return $this->id;
    }

    /** @return string|null */
    public function get_css_class(): ?string
    {
        return $this->css_class;
    }

    /** @return string|null */
    public function get_height(): ?string
    {
        return $this->height;
    }

    /** @return bool */
    public function is_editable(): bool
    {
        return $this->editable;
    }

    /** @return bool */
    public function is_selectable(): bool
    {
        return $this->selectable;
    }

    /** @return string|null */
    public function get_ajax_url(): ?string
    {
        return $this->ajax_url;
    }

    /** @return array */
    public function get_ajax_params(): array
    {
        return $this->ajax_params;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Render the calendar configuration using a driver.
     *
     * Convenience method: shorthand for $driver->render($this).
     *
     * @param DriverInterface $driver
     * @return array The driver-specific configuration array
     */
    public function render(DriverInterface $driver): array
    {
        return $driver->render($this);
    }

    /**
     * Render the calendar configuration as JSON using a driver.
     *
     * @param DriverInterface $driver
     * @param int $flags JSON encoding flags
     * @return string
     */
    public function to_json(DriverInterface $driver, int $flags = 0): string
    {
        $json = json_encode($this->render($driver), $flags);
        if ($json === false) {
            throw new RuntimeException(
                'Failed to encode calendar config to JSON: ' . json_last_error_msg()
            );
        }
        return $json;
    }
}
