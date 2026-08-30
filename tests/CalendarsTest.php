<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars — the feed request, and the JavaScript the driver writes
 *
 * This library has the same two surfaces as `Italix\DataSets` and, as it turns
 * out, had the same two defects: a driver that emits JSON into a `<script>`
 * element without neutralising `</script>`, and a request parser that casts
 * whatever the query string held into a string, including an array.
 *
 * That is worth stating plainly, because it is the argument for testing every
 * library rather than the ones that look risky. The two drivers were written
 * from one another; the flaw was copied along with the shape, and it sat in
 * both for as long as neither had a suite.
 *
 * Run: php src/Libs/Italix/Calendars/tests/CalendarsTest.php
 */

declare(strict_types=1);

(static function (): void {
    foreach ([
        __DIR__ . '/../../../../../vendor/autoload.php',
        __DIR__ . '/../../../../vendor/autoload.php',
        __DIR__ . '/../../../autoload.php',
        __DIR__ . '/../vendor/autoload.php',
    ] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;

            return;
        }
    }

    fwrite(STDERR, "Could not find an autoloader. Run composer install.\n");
    exit(2);
})();

use Italix\Calendars\CalendarFeedRequest;
use Italix\Calendars\CalendarSet;
use Italix\Calendars\Drivers\FullCalendar\FullCalendarDriver;

use function Italix\Calendars\calendar_set;
use function Italix\Testing\{suite, section, test, summary};

suite('Italix Calendars — feed requests and emitted JavaScript');

$from = static fn (array $params): CalendarFeedRequest => CalendarFeedRequest::from_array($params);

// -----------------------------------------------------------------------------
section('the range FullCalendar sends');

$request = $from(['start' => '2026-08-01', 'end' => '2026-09-01', 'timeZone' => 'Europe/Rome']);

test('the start parses', $request->start() !== null
    && $request->start()->format('Y-m-d') === '2026-08-01');
test('the end parses', $request->end() !== null
    && $request->end()->format('Y-m-d') === '2026-09-01');
test('the timezone comes back', $request->timezone() === 'Europe/Rome');
test('an absent range is null rather than today',
    $from([])->start() === null && $from([])->end() === null,
    'defaulting to now would silently query a different window than the one asked for');
test('an empty string is absent, not epoch', $from(['start' => ''])->start() === null);
test('the ISO 8601 form FullCalendar actually sends parses too',
    $from(['start' => '2026-08-01T00:00:00+02:00'])->start() !== null);

// -----------------------------------------------------------------------------
section('nothing in the query string can crash or mislead the parser');

// Every one of these is a URL somebody can type. None may throw, and none may
// come back as a value that looks usable and is not.
$hostile = [
    'garbage'        => ['start' => 'not a date'],
    'sql-ish'        => ['start' => "2026-01-01'; DROP TABLE cal_event --"],
    'array start'    => ['start' => ['2026-01-01']],
    'array end'      => ['end' => ['2026-01-01']],
    'array timezone' => ['timeZone' => ['Europe/Rome']],
    'nested array'   => ['start' => [['x']]],
    'bool'           => ['start' => true],
    'null byte'      => ['start' => "2026-01-01\0"],
];

$crashes = [];

foreach ($hostile as $label_c => $params) {
    try {
        $request = $from($params);
        $request->start();
        $request->end();
        $request->timezone();
    } catch (\Throwable $e) {
        $crashes[] = $label_c . ': ' . get_class($e);
    }
}

test('NOTHING IN THE QUERY STRING THROWS', $crashes === [], implode('; ', $crashes));

// The sharper half. An array used to be cast, and `(string) ['x']` is the
// literal word "Array" — which `timezone()` returned as though it were a zone
// name. The caller then handed it to DateTimeZone and got the exception one
// layer away from the cause.
test('AN ARRAY TIMEZONE IS NULL, NOT THE WORD "Array"',
    $from(['timeZone' => ['Europe/Rome']])->timezone() === null,
    'a value that looks usable and is not costs more than a null');
test('…and an array date is null too',
    $from(['start' => ['2026-01-01']])->start() === null);
test('…without emitting a PHP warning',
    (static function () use ($from): bool {
        $warned_flag = false;

        set_error_handler(static function () use (&$warned_flag): bool {
            $warned_flag = true;

            return true;
        });

        $from(['start' => ['x'], 'timeZone' => ['y']])->start();
        $from(['start' => ['x'], 'timeZone' => ['y']])->timezone();

        restore_error_handler();

        return !$warned_flag;
    })(),
    'Array to string conversion, twice per request, into whatever reads the log');

// Pinned because it is surprising rather than wrong: PHP's date parser reads
// the first four digits of an over-long year and discards the rest.
test('an over-long year is truncated by PHP, not rejected',
    $from(['start' => '9999999-01-01'])->start()->format('Y') === '0999',
    'worth knowing before a caller treats a parsed date as a validated one');

// -----------------------------------------------------------------------------
section('the calendar configuration');

$calendar = calendar_set()
    ->ajax_url('/calendar/events.json')
    ->initial_view('month')
    ->locale('it')
    ->first_day(1)
    ->editable();

test('calendar_set() builds one', $calendar instanceof CalendarSet);
test('the endpoint is kept', $calendar->get_ajax_url() === '/calendar/events.json');
test('the initial view is kept', $calendar->get_initial_view() === 'month');
test('AN UNKNOWN VIEW IS REFUSED RATHER THAN PASSED THROUGH',
    (static function (): bool {
        try {
            calendar_set()->initial_view('dayGridMonth');

            return false;
        } catch (\InvalidArgumentException $e) {
            return true;
        }
    })(),
    'the library has its own view vocabulary and whitelists it — so this name cannot carry a payload');
test('the locale is kept', $calendar->get_locale() === 'it');
test('the first day is kept', $calendar->get_first_day() === 1);
test('editable is a flag, not a string', $calendar->is_editable() === true);
test('a source can be declared and read back',
    calendar_set()->source('deadlines') !== null);

// -----------------------------------------------------------------------------
section('the emitted script: nothing may close the element it lives in');

$driver = new FullCalendarDriver();

$breakout_c = '</script><img src=x onerror=alert(1)>';

// `initial_view()` and `views()` are deliberately absent from this list: they
// are whitelisted at the setter, so a payload never reaches the driver. That is
// the stronger defence of the two, and the assertion for it is above.
$entry_points = [
    'calendar id'    => static fn (): CalendarSet => calendar_set()->id($breakout_c),
    'css class'      => static fn (): CalendarSet => calendar_set()->css_class($breakout_c),
    'ajax url'       => static fn (): CalendarSet => calendar_set()->ajax_url('/x?' . $breakout_c),
    'ajax params'    => static fn (): CalendarSet => calendar_set()->ajax_params(['note' => $breakout_c]),
    'locale'         => static fn (): CalendarSet => calendar_set()->locale($breakout_c),
    'height'         => static fn (): CalendarSet => calendar_set()->height($breakout_c),
    'extra options'  => static fn (): CalendarSet => calendar_set()->extra(['note' => $breakout_c]),
    'source name'    => static function () use ($breakout_c): CalendarSet {
        $set = calendar_set();
        $set->source($breakout_c);

        return $set;
    },
];

$leaks = [];

foreach ($entry_points as $where_c => $build) {
    $script = $driver->render_script($build()->ajax_url('/events.json'), '#calendar');

    if (strpos($script, '</script>') !== false) {
        $leaks[] = $where_c;
    }
}

test('NO ENTRY POINT CAN CLOSE THE SCRIPT TAG',
    $leaks === [],
    $leaks === [] ? '' : 'closes the element via: ' . implode(', ', $leaks));

// And the value still has to arrive intact, or the escaping traded one outage
// for another.
$script = $driver->render_script(
    calendar_set()->ajax_url('/events.json')->locale('it < IT & "quoted"'),
    '#calendar'
);

preg_match('/"locale":\s*("(?:[^"\\\\]|\\\\.)*")/', $script, $found);

test('the value survives the escaping intact',
    isset($found[1]) && json_decode($found[1]) === 'it < IT & "quoted"',
    'decoded: ' . var_export(isset($found[1]) ? json_decode($found[1]) : null, true));

// -----------------------------------------------------------------------------
section('and the script is still JavaScript afterwards');

$node_c = trim((string) @shell_exec('command -v node 2>/dev/null'));

if ($node_c === '') {
    echo "  SKIPPED — node is not installed, so the emitted script cannot be parsed.\n";
} else {
    $set = calendar_set()->ajax_url('/events.json')->id($breakout_c)->locale('Ünïcodé — ' . $breakout_c);

    $script_c = tempnam(sys_get_temp_dir(), 'ix_cal_') . '.js';
    file_put_contents($script_c, $driver->render_script($set, '#calendar'));

    $status_n = 0;
    @exec(escapeshellcmd($node_c) . ' --check ' . escapeshellarg($script_c) . ' 2>&1', $output, $status_n);

    test('node parses the emitted script', $status_n === 0, implode(' ', $output));

    unlink($script_c);
}

exit(summary());
