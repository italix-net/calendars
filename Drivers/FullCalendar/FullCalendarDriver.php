<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - FullCalendarDriver
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars\Drivers\FullCalendar;

use Italix\Calendars\CalendarSet;
use Italix\Calendars\Drivers\DriverInterface;

/**
 * Driver for the FullCalendar JS library (https://fullcalendar.io).
 *
 * Translates CalendarSet configuration into FullCalendar's Calendar()
 * constructor options, plus a JS bootstrap that wires up event feeds,
 * interaction callbacks, and instantiates the calendar — the same
 * render()/render_script() split used by Italix\DataSets\Drivers\Tabulator\TabulatorDriver.
 *
 * Requires FullCalendar's core + daygrid/timegrid/list bundles (or the
 * "all" bundle) to be loaded on the page before render_script() runs, and,
 * for non-English locales, the matching locale script.
 *
 * Usage:
 *
 *     $driver = new FullCalendarDriver();
 *     $config = $driver->render($calendar);
 *     $js     = $driver->render_script($calendar, '#my-calendar');
 *
 *     // In your template:
 *     // <div id="my-calendar"></div>
 *     // <script><?= $js ?></script>
 */
class FullCalendarDriver implements DriverInterface
{

    /**
     * Flags that make a JSON literal safe **inside a `<script>` block**.
     *
     * `JSON_HEX_TAG` is the one that matters. `json_encode()` already makes a
     * value safe as a JSON *string* — it cannot break out of the literal — but
     * it never had to: a value containing `</script>` breaks out of the
     * **element**, and the browser parses the remainder as HTML. This driver's
     * own docblock shows the output being placed inside a script tag, and
     * `JSON_UNESCAPED_SLASHES` at the main call site removed the accidental
     * protection that `<\/script>` would have given.
     *
     * Same defect, same fix and same date as `Italix\DataSets`: the two
     * drivers were written from one another and inherited it.
     */
    private const JS_SAFE_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /**
     * The one door every value takes on its way into the emitted JavaScript.
     *
     * @param mixed $value
     */
    private function js($value, int $flags = 0): string
    {
        return (string) json_encode($value, $flags | self::JS_SAFE_FLAGS);
    }
    /** Maps CalendarSet's generic view keys to FullCalendar view names. */
    private const VIEW_MAP = [
        'month' => 'dayGridMonth',
        'week'  => 'timeGridWeek',
        'day'   => 'timeGridDay',
        'list'  => 'listWeek',
    ];

    /** Maps CalendarSet's generic interaction events to FullCalendar callback options. */
    private const EVENT_MAP = [
        'event_click'  => 'eventClick',
        'date_click'   => 'dateClick',
        'select'       => 'select',
        'event_drop'   => 'eventDrop',
        'event_resize' => 'eventResize',
    ];

    /**
     * {@inheritdoc}
     */
    public function get_name(): string
    {
        return 'full_calendar';
    }

    /**
     * {@inheritdoc}
     */
    public function render(CalendarSet $calendar): array
    {
        $config = [];

        $config['initialView'] = self::VIEW_MAP[$calendar->get_initial_view()];

        $config['headerToolbar'] = $calendar->has_toolbar()
            ? [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => $this->build_view_button_list($calendar),
            ]
            : false;

        if ($calendar->get_locale() !== null) {
            $config['locale'] = $calendar->get_locale();
        }

        $config['firstDay']   = $calendar->get_first_day();
        $config['editable']   = $calendar->is_editable();
        $config['selectable'] = $calendar->is_selectable();

        if ($calendar->get_height() !== null) {
            $config['height'] = $calendar->get_height();
        }

        if ($calendar->has_sources()) {
            $config['eventSources'] = $this->build_event_sources($calendar);
        } elseif ($calendar->get_ajax_url() !== null) {
            $config['events'] = $this->build_single_source($calendar);
        }

        // Interaction callbacks metadata (consumed by render_script(), not valid JSON as-is)
        if (!empty($calendar->get_events())) {
            $config['_events'] = $calendar->get_events();
        }

        // Driver-specific passthrough, applied last so callers can override anything above
        $config = array_merge($config, $calendar->get_extra());

        return $config;
    }

    /**
     * Render the calendar configuration plus a ready-to-embed JS bootstrap.
     *
     * @param CalendarSet $calendar
     * @param string $selector CSS selector for the container element
     * @param string|null $var_name JS variable name to assign the Calendar instance to (default: '_calendar')
     * @return string
     */
    public function render_script(CalendarSet $calendar, string $selector, ?string $var_name = null): string
    {
        $config = $this->render($calendar);
        $events = $config['_events'] ?? [];
        unset($config['_events']);

        $config_json = $this->js($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $selector_escaped = $this->js($selector);
        $calendar_var = $var_name !== null ? $var_name : '_calendar';

        $js = "(function() {\n";
        $js .= "    var config = {$config_json};\n\n";
        $js .= $this->build_callback_wiring_js($events);
        $js .= "    var el = document.querySelector({$selector_escaped});\n";
        $js .= "    var {$calendar_var} = new FullCalendar.Calendar(el, config);\n";
        $js .= "    {$calendar_var}.render();\n";
        $js .= "})();\n";

        return $js;
    }

    /**
     * Build the ordered list of view-switch buttons for the header toolbar.
     *
     * @param CalendarSet $calendar
     * @return string
     */
    private function build_view_button_list(CalendarSet $calendar): string
    {
        $buttons = array_map(
            fn (string $view) => self::VIEW_MAP[$view],
            $calendar->get_views()
        );

        return implode(',', $buttons);
    }

    /**
     * Build FullCalendar's eventSources array from the calendar's named sources.
     *
     * Each source falls back to the parent CalendarSet's ajax_url/ajax_params
     * when it does not define its own, so a single feed endpoint can be
     * reused with a per-source filter (e.g. ?team_id=12).
     *
     * @param CalendarSet $calendar
     * @return array
     */
    private function build_event_sources(CalendarSet $calendar): array
    {
        $sources = [];

        foreach ($calendar->each_source() as $source) {
            $url = $source->get_ajax_url() ?? $calendar->get_ajax_url();
            if ($url === null) {
                // No feed configured anywhere for this source; nothing to fetch.
                continue;
            }

            $entry = [
                'id'  => $source->get_name(),
                'url' => $url,
            ];

            $params = array_merge($calendar->get_ajax_params(), $source->get_ajax_params());
            if (!empty($params)) {
                $entry['extraParams'] = $params;
            }
            if ($source->get_color() !== null) {
                $entry['color'] = $source->get_color();
            }
            if ($source->get_editable() !== null) {
                $entry['editable'] = $source->get_editable();
            }

            $sources[] = array_merge($entry, $source->get_extra());
        }

        return $sources;
    }

    /**
     * Build FullCalendar's single "events" feed config.
     *
     * @param CalendarSet $calendar
     * @return array
     */
    private function build_single_source(CalendarSet $calendar): array
    {
        $entry = ['url' => $calendar->get_ajax_url()];

        if (!empty($calendar->get_ajax_params())) {
            $entry['extraParams'] = $calendar->get_ajax_params();
        }

        return $entry;
    }

    /**
     * Build the JS lines that wire registered callbacks onto FullCalendar's
     * own option names, guarding each call the same way TabulatorDriver does
     * (typeof check before invoking window[name]).
     *
     * @param array<string, string> $events Generic event name => JS function name
     * @return string
     */
    private function build_callback_wiring_js(array $events): string
    {
        if (empty($events)) {
            return '';
        }

        $js = '';
        foreach (self::EVENT_MAP as $generic => $fc_option) {
            if (!isset($events[$generic])) {
                continue;
            }
            $callback_js = $this->js($events[$generic]);
            $js .= "    config.{$fc_option} = function(info) {\n";
            $js .= "        if (typeof window[{$callback_js}] === \"function\") {\n";
            $js .= "            window[{$callback_js}](info);\n";
            $js .= "        }\n";
            $js .= "    };\n";
        }
        $js .= "\n";

        return $js;
    }
}
