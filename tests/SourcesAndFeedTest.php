<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars — event sources, the feed answer, and the vocabularies
 *
 * The existing suite covers the two things that were found to be broken: a
 * hostile query string, and a `</script>` in the emitted configuration. What it
 * does not cover is the ordinary path — several sources, each with its own
 * colour and endpoint, and the JSON that answers them. Two classes with no
 * assertions between them.
 *
 * Sources are the feature that separates this from "a calendar": deadlines in
 * one colour, appointments in another, each fetched from its own URL and each
 * separately editable. Get the description wrong and the calendar draws
 * everything in one colour from one endpoint, which looks like a styling bug.
 *
 * Run: php src/Libs/Italix/Calendars/tests/SourcesAndFeedTest.php
 */

declare(strict_types=1);

(static function (): void {
    foreach ([
        __DIR__ . '/../vendor/autoload.php',               // checked out on its own
        __DIR__ . '/../../../../../vendor/autoload.php',   // vendored in a project
        __DIR__ . '/../../../../vendor/autoload.php',      // installed as a package
        __DIR__ . '/../../../autoload.php',                // sibling autoloader
    ] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;

            return;
        }
    }

    fwrite(STDERR, "Could not find an autoloader. Run composer install.\n");
    exit(2);
})();

use Italix\Calendars\CalendarFeedResponse;
use Italix\Calendars\CalendarSet;
use Italix\Calendars\CalendarSource;
use Italix\Calendars\Drivers\FullCalendar\FullCalendarDriver;

use function Italix\Testing\{suite, section, test, summary};

suite('Italix Calendars — sources and the feed');

/** @return array{0: bool, 1: string} */
$throws = static function (callable $fn): array {
    try {
        $fn();

        return [false, ''];
    } catch (\Throwable $e) {
        return [true, $e->getMessage()];
    }
};

$driver = new FullCalendarDriver();

// -----------------------------------------------------------------------------
section('sources are declared, and each keeps its own settings');

$calendar = new CalendarSet();
$calendar->id('cal')->ajax_url('/events.json');

test('a calendar with nothing declared has no sources', !$calendar->has_sources());

$deadlines = $calendar->source('deadlines');

test('source() returns something configurable', $deadlines instanceof CalendarSource);
test('…and the calendar now has one', $calendar->has_sources());
test('…and asking again gives the same object, not a second source',
    $calendar->source('deadlines') === $deadlines);

$deadlines->label('Scadenze')->color('#c0392b')->ajax_url('/deadlines.json')
          ->ajax_params(['kind' => 'deadline'])->editable(false)
          ->extra(['className' => 'deadline-event']);

$appointments = $calendar->source('appointments');
$appointments->label('Appuntamenti')->color('#2980b9')->ajax_url('/appointments.json')->editable();

$sources = iterator_to_array($calendar->each_source());

test('both sources are there', count($sources) === 2, (string) count($sources));

test('THE SECOND SOURCE DID NOT OVERWRITE THE FIRST',
    $sources['deadlines']->get_color() === '#c0392b'
    && $sources['appointments']->get_color() === '#2980b9',
    'every event would be drawn in one colour, which reads as a styling bug');

test('each keeps its own endpoint',
    $sources['deadlines']->get_ajax_url() === '/deadlines.json'
    && $sources['appointments']->get_ajax_url() === '/appointments.json');

test('each keeps its own parameters',
    $sources['deadlines']->get_ajax_params() === ['kind' => 'deadline']
    && $sources['appointments']->get_ajax_params() === []);

test('EDITABILITY IS PER SOURCE, and false is not confused with unset',
    $sources['deadlines']->get_editable() === false
    && $sources['appointments']->get_editable() === true,
    'a read-only source that reports null is one the calendar lets people drag');

test('a source nobody configured reports null rather than a guess',
    (new CalendarSource('plain'))->get_editable() === null);

test('the name is kept', $deadlines->get_name() === 'deadlines');
test('the label defaults to a readable form of the name',
    (new CalendarSource('public_holidays'))->get_label() === 'Public Holidays',
    (new CalendarSource('public_holidays'))->get_label());

test('extras are carried through', $sources['deadlines']->get_extra() === ['className' => 'deadline-event']);

// -----------------------------------------------------------------------------
section('…and they reach the emitted script');

$script = $driver->render_script($calendar, '#cal', 'cal');

foreach (['#c0392b', '/deadlines.json', '#2980b9', '/appointments.json', 'deadlines', 'appointments'] as $needle) {
    test("\"{$needle}\" is in the configuration", strpos($script, $needle) !== false);
}

// The label is *not* there, and that is correct: FullCalendar's `eventSources`
// have an id, a url, a colour and an editable flag, and no concept of a label.
// It is metadata for the application — a legend it draws itself. Pinned so the
// next reader does not spend an afternoon looking for it in the config.
test('THE LABEL IS DELIBERATELY NOT EMITTED — it is for the application, not the driver',
    strpos($script, 'Scadenze') === false && strpos($script, 'Appuntamenti') === false,
    'the driver started emitting it, which means the assertion below about what it *is* for is stale');

test('…and each source is emitted under its own id',
    substr_count($script, '"id"') >= 2, $script);

// -----------------------------------------------------------------------------
section('the vocabularies');

// `initial_view()` whitelists, and that is the stronger of the two defences
// against a payload in a view name: it cannot carry one at all. Pinned so the
// whitelist is not relaxed into a pass-through for "flexibility".
test('every view this library names is accepted', (static function (): bool {
    foreach (CalendarSet::VIEWS as $view_c) {
        (new CalendarSet())->initial_view($view_c);
    }

    return true;
})());

[$threw, $message] = $throws(static function (): void {
    (new CalendarSet())->initial_view('timeGridWeek');
});

test('A VIEW OUTSIDE THE VOCABULARY IS REFUSED', $threw,
    'a driver-specific name was accepted, so the library no longer knows what its own views are');
test('…and the message lists what is allowed',
    strpos($message, implode(', ', CalendarSet::VIEWS)) !== false, $message);

[$threw] = $throws(static function (): void {
    (new CalendarSet())->views(['month', 'agendaWeek']);
});

test('views() checks every entry, not only the first', $threw);

[$threw] = $throws(static function (): void {
    (new CalendarSet())->views(['month', 'week']);
});

test('…and accepts a list that is entirely legal', !$threw);

// `first_day` is documented as 0–6 and validated nowhere. Recorded rather than
// changed: unlike an unknown view, an out-of-range day cannot carry a payload —
// it is an int — and the client's own handling of it is not this library's to
// define. The point of the assertion is that the next reader learns it here
// instead of from a calendar that starts its week on a Tuesday.
test('FIRST_DAY IS NOT RANGE-CHECKED, and that is recorded rather than assumed',
    (new CalendarSet())->first_day(9)->get_first_day() === 9,
    'it validates now — which may be an improvement, but this assertion is the '
    . 'place that said it did not');

// -----------------------------------------------------------------------------
section('the feed answer');

$events = [
    ['id' => 'event_1', 'title' => 'Team meeting', 'start' => '2026-09-01 10:00', 'allDay' => false],
    ['id' => 'event_2', 'title' => 'Project deadline', 'start' => '2026-09-04',   'allDay' => true],
];

$response = new CalendarFeedResponse($events);

test('the events come back as given', $response->to_array() === $events);

$json = $response->to_json();

test('it is valid JSON', json_decode($json, true) === $events, substr($json, 0, 120));

test('AN EMPTY FEED IS AN EMPTY ARRAY, not an object',
    (new CalendarFeedResponse([]))->to_json() === '[]',
    'FullCalendar reads `{}` as a malformed feed and draws nothing, with no error',
);

// A calendar showing "Riunione — città" is the ordinary case here, not the
// exotic one, and a wrong encoding shows up as an empty title.
$accented = new CalendarFeedResponse([['title' => 'Riunione a Reggio nell\'Emilia — città']]);

test('accents and apostrophes survive the round trip',
    json_decode($accented->to_json(), true)[0]['title'] === 'Riunione a Reggio nell\'Emilia — città');

test('flags reach json_encode',
    strpos((new CalendarFeedResponse([['url' => '/a/b']]))->to_json(JSON_UNESCAPED_SLASHES), '/a/b') !== false);

// The event titles are the one part of a feed that is written by a user: a meeting
// name, an appointment subject. They are answered as JSON with a JSON content
// type, so `</script>` in one is inert — but only as long as nobody decides to
// inline the feed into a page, so the shape is pinned here.
$hostile = new CalendarFeedResponse([['title' => '</script><img src=x onerror=alert(1)>']]);

test('a title is carried verbatim and escaped by JSON, not stripped',
    json_decode($hostile->to_json(), true)[0]['title'] === '</script><img src=x onerror=alert(1)>',
    'the feed rewrote what the user typed');

test('…and the JSON itself stays parseable', json_decode($hostile->to_json(), true) !== null);

// -----------------------------------------------------------------------------
section('event callbacks');

$calendar = new CalendarSet();
$calendar->id('cal')->ajax_url('/e.json')
         ->on('event_click', 'on_event_click')
         ->on('date_click', 'on_date_click');

test('callbacks are registered', $calendar->get_events()
    === ['event_click' => 'on_event_click', 'date_click' => 'on_date_click'],
    json_encode($calendar->get_events()));

$script = $driver->render_script($calendar, '#cal', 'cal');

test('AND ACTUALLY REACH THE SCRIPT', strpos($script, 'on_event_click') !== false
    && strpos($script, 'on_date_click') !== false,
    'the callback was stored and the driver never emitted it — a calendar whose clicks do nothing');

test('…wired to the driver\'s own option names',
    strpos($script, 'config.eventClick') !== false && strpos($script, 'config.dateClick') !== false,
    $script);

// The vocabulary is this library's, and the driver's names are the camelCase
// ones — so `on('eventClick', …)` is the mistake somebody makes after reading
// FullCalendar's documentation. It used to be stored and silently dropped.
[$threw, $message] = $throws(static function (): void {
    (new CalendarSet())->on('eventClick', 'handler');
});

test('A DRIVER-SPELLED EVENT NAME IS REFUSED', $threw,
    'stored and dropped: the handler never fires and nothing says why');
test('…and the message explains which vocabulary this is',
    strpos($message, 'event_click') !== false && strpos($message, 'camelCase') !== false, $message);

foreach (CalendarSet::EVENTS as $event_c) {
    [$threw] = $throws(static function () use ($event_c): void {
        (new CalendarSet())->on($event_c, 'handler');
    });

    test("\"{$event_c}\" is accepted", !$threw);
}

exit(summary());
