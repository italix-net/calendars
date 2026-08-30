# Changelog — italix/calendars

## [1.2.1] — 2026-08-28

### Changed

No change to this library's own code. `require-dev`'s `italix/testing` widened to `^2.0` (was
`^1.0`) and `require`'s `italix/contracts` widened to `^2.0` (was `^1.0`), both MAJOR-bumped
elsewhere in this same round for a function-naming convention change (`_c` retired on method
names — see `src/Libs/Italix/CONVENTIONS.md`) that touches neither of the interfaces this library
actually uses.

## [1.2.0] — 2026-08-17

### Fixed

- **`on('eventClick', …)` was stored and silently dropped.** The event vocabulary is this library's
  own — `event_click`, `date_click`, `select`, `event_drop`, `event_resize` — and the driver
  translates it into FullCalendar's camelCase. Somebody reading *FullCalendar's* documentation writes
  the camelCase name, `on()` accepted it because it validated nothing, the driver did not recognise
  it, and the callback never reached the page. A calendar whose clicks do nothing, and no error
  anywhere.

  `CalendarSet::EVENTS` is now a whitelist, next to `VIEWS` and for the same reason — with more
  cause, because here the wrong spelling is the one the driver's own documentation teaches. The
  message says which vocabulary it is.

  Found by writing the first assertion that asked whether a registered callback reaches the script.

### Added

- **`CalendarSource` and `CalendarFeedResponse` had no assertions between them.** 44 now.

  Sources are what separates this from "a calendar" — deadlines in one colour, appointments in
  another, each with its own endpoint and each separately editable — and the failure of getting it
  wrong is that everything is drawn in one colour from one feed, which reads as a styling bug rather
  than a data one. Pinned: a second source does not overwrite the first, `false` for editable is not
  confused with unset, and each source keeps its own parameters.

  Also pinned, because it costs an afternoon to discover: **the label is deliberately not emitted.**
  FullCalendar's `eventSources` have an id, a url, a colour and an editable flag and no concept of a
  label, so `label()` is metadata for the application's own legend.

  On the feed side: an empty feed is `[]` and not `{}` — FullCalendar reads the latter as malformed
  and draws nothing without an error — and an event title is carried verbatim rather than stripped,
  because the title is the one part of a feed a user writes.

- **`first_day` is documented as 0–6 and validated nowhere**, and that is now recorded rather than
  assumed. Left as it is on purpose: unlike a view name, an out-of-range integer cannot carry a
  payload, and what a client does with it is not this library's to define.

## [1.1.1] — 2026-08-17

### Added

- **A README.** The package had none.

## [1.1.0] — 2026-08-14

### Security

- **The same `</script>` breakout `Italix\DataSets` had, in `FullCalendarDriver`.** The two drivers
  were written from one another and the defect was copied along with the shape: JSON emitted into a
  `<script>` element — the driver's own docblock shows exactly that placement — with
  `JSON_UNESCAPED_SLASHES` removing the accidental protection `<\/script>` would have given.
  A calendar id, a css class, a locale or an ajax parameter containing `</script>` closed the element
  and handed the remainder to the HTML parser.

  Fixed with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` at **one** door rather
  than three call sites. Values decode identically in the browser; `node --check` confirms the
  emitted script still parses.

  Worth recording as a pair rather than as two bugs: neither library had a suite, and the flaw
  survived in both for exactly as long as that was true.

### Fixed

- **`?timeZone[]=Europe/Rome` returned the literal string `Array`.** A query string can hold an
  array; casting one produces the word `Array` plus a PHP warning, and `timezone()` handed that back
  as though it were a zone name. The caller then passed it to `DateTimeZone` and met the exception
  one layer away from its cause. Non-scalars now return `null` — an absent value, which callers
  already handle — and the same guard covers `start()` and `end()`.

### Added

- **The first tests this library has had**, 22 assertions. Eight hostile query strings asserted never
  to throw and never to return a value that looks usable and is not; nine entry points into the
  emitted script asserted unable to close the element; the value asserted to survive escaping intact;
  and `node --check` on the result, skipped with a reason when node is absent.

- Pinned rather than changed: `initial_view()` whitelists against the library's own view vocabulary
  (`month`, `week`, `day`, `list`), so that name cannot carry a payload at all — the stronger of the
  two defences, and the reason it is absent from the breakout corpus.

Format: [Keep a Changelog](https://keepachangelog.com/). Versioning policy: `VERSIONING.md` at the
project root.


### Legal

- **Licensed under MPL-2.0**, applied 2026-08-13: the `license` field in `composer.json`, a `LICENSE`
  file, and the Exhibit A notice in every source file — MPL §1.4 defines "Covered Software" per file,
  so the per-file header is what makes the licence apply rather than decoration.

  This is a **first declaration, not a relicensing.** The package carried no licence at all before,
  which in most jurisdictions means all rights reserved: nothing had been granted, so nothing is
  taken away and no consumer's position gets worse. That is why it is recorded here rather than
  treated as a breaking change — unlike `italix/orm`, which went Apache-2.0 → MPL-2.0 and took a
  MAJOR because that direction does narrow what a consumer already had.

## [1.0.0] — baseline

Versioning starts here. This entry records the state of the library at the time the policy was
adopted, not a release. Added to this project in 2026-07.

### Contents

- **`CalendarSet`** — the calendar described in PHP.
- **`CalendarSource`** — event sources, declared rather than discovered.
- **`CalendarFeedRequest`** / **`CalendarFeedResponse`** — the feed contract for range-based loading.
- **`Drivers/`** — render the client configuration.
- `functions.php` — the factory function (house rule 9).

### Known compatibility notes

Like `italix/datasets`, the driver returns markup typed as a `string`. Narrowing it to
`Italix\Encode\Html` is a planned MAJOR; see `VERSIONING.md`, house rule 15.
