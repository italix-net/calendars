# Italix Calendars

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MPL%202.0-blue.svg)](LICENSE)

A calendar **described in PHP** — views, toolbar, locale, event sources — with a driver that renders
the client configuration and a small contract for range-based feeds.

```php
use Italix\Calendars\CalendarSet;
use Italix\Calendars\Drivers\FullCalendar\FullCalendarDriver;

$cal = new CalendarSet();

$cal->id('events-calendar')->height('750px')
    ->views(['month', 'week', 'day', 'list'])->initial_view('month')
    ->locale($lang)->first_day(1)
    ->toolbar(true)
    ->ajax_url('/it/admin/calendar/events.json');

$script = (new FullCalendarDriver())->render_script($cal, '#events-calendar', 'events_calendar');
```

The feed endpoint is the other half. The widget asks for a window; you answer with the events inside
it:

```php
use Italix\Calendars\CalendarFeedRequest;
use Italix\Calendars\CalendarFeedResponse;

$freq = CalendarFeedRequest::from($_GET);

$events = [];
foreach ($rows as $row) {
    if (!in_range($row['when_dt'], $freq->start(), $freq->end())) {
        continue;
    }
    $events[] = [
        'id'     => 'appointment_' . $row['id'],
        'title'  => $t->get('calendar.appointment_created', ['code' => $row['code']]),
        'start'  => $row['when_dt'],
        'allDay' => false,
        'color'  => '#3b82f6',
        'url'    => "/{$lang}/admin/appointments/{$row['id']}/edit.html",
    ];
}

return (new CalendarFeedResponse($events))->to_json();
```

## Sources are declared, not discovered

`CalendarSource` says where events come from. Nothing scans, nothing registers itself at boot: a
calendar that shows the wrong events is a bug you find by reading one file, not by working out which
listener fired.

## The `</script>` breakout

The driver emits its configuration as JSON into a `<script>` element — the driver's own docblock
shows exactly that placement. `json_encode()` makes a value safe as a JSON *string*: quotes and
backslashes are escaped, so nothing can break out of the literal. Nothing had to. A calendar id, a
CSS class, a locale or an AJAX parameter containing `</script>` closed the **element**, and the
remainder was handed to the HTML parser. Three call sites also passed `JSON_UNESCAPED_SLASHES`, which
removed the accidental protection `<\/script>` would have given.

Fixed with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` at **one** door rather than
three. Values decode identically in the browser; the suite runs `node --check` on the emitted script.

Worth stating for anyone writing a driver: this is a *pair* of bugs with `italix/datasets`. The two
drivers were written from one another, so the defect was copied along with the shape — and it
survived in both for exactly as long as neither had a test suite.

## Hostile input returns absence, not nonsense

`?timeZone[]=Europe/Rome` used to return the literal string `Array` — a query string can hold an
array, casting one produces that word plus a warning, and `timezone()` handed it back as though it
were a zone name. The caller then met a `DateTimeZone` exception one layer away from its cause.
Non-scalars now return `null`, which every caller already handles. The same guard covers `start()`
and `end()`.

`initial_view()` is stronger still: it whitelists against the library's own vocabulary (`month`,
`week`, `day`, `list`), so that name cannot carry a payload at all.

## Drivers

FullCalendar ships in the box. A driver is the only piece that knows the widget's option names, which
is what keeps the widget replaceable.

## Requirements

`php >= 7.4`, `italix/contracts`.
