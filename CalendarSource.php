<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - CalendarSource
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars;

/**
 * A named, independently-fetched event feed within a CalendarSet.
 *
 * Used to combine several event feeds into a single calendar view without
 * merging them server-side — e.g. one source per team, each
 * colored differently, aggregated into an internal "all teams" view.
 *
 * Example:
 *
 *     $cal->source('team_12')
 *         ->label('Team A')
 *         ->color('#2f6fed')
 *         ->ajax_url('/it/admin/calendar/events.json')
 *         ->ajax_params(['team_id' => 12]);
 */
class CalendarSource
{
    /** @var string */
    private $name;

    /** @var string|null */
    private $label = null;

    /** @var string|null CSS color (hex or named) applied to events from this source */
    private $color = null;

    /** @var string|null Overrides the parent CalendarSet's ajax_url for this source */
    private $ajax_url = null;

    /** @var array Extra parameters sent with this source's feed requests */
    private $ajax_params = [];

    /** @var bool|null Editable override; null = inherit from the parent CalendarSet */
    private $editable = null;

    /** @var array Extra driver-specific options */
    private $extra = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // =========================================================================
    // Fluent Setters
    // =========================================================================

    /**
     * Set the display label (e.g. shown in a legend).
     *
     * @param string $label
     * @return self
     */
    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the color applied to events from this source.
     *
     * @param string $color CSS color, e.g. '#2f6fed'
     * @return self
     */
    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Set the AJAX URL this source fetches events from.
     *
     * If not set, falls back to the parent CalendarSet's ajax_url().
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
     * Set extra parameters to send with this source's feed requests.
     *
     * Merged with (and taking precedence over) the parent CalendarSet's
     * ajax_params().
     *
     * @param array $params
     * @return self
     */
    public function ajax_params(array $params): self
    {
        $this->ajax_params = array_merge($this->ajax_params, $params);
        return $this;
    }

    /**
     * Override editability for this source only.
     *
     * @param bool $editable
     * @return self
     */
    public function editable(bool $editable = true): self
    {
        $this->editable = $editable;
        return $this;
    }

    /**
     * Set extra driver-specific options.
     *
     * @param array $options
     * @return self
     */
    public function extra(array $options): self
    {
        $this->extra = array_merge($this->extra, $options);
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /** @return string */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get the display label (auto-generated from the name if not set).
     *
     * @return string
     */
    public function get_label(): string
    {
        return $this->label ?? ucwords(str_replace('_', ' ', $this->name));
    }

    /** @return string|null */
    public function get_color(): ?string
    {
        return $this->color;
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

    /** @return bool|null */
    public function get_editable(): ?bool
    {
        return $this->editable;
    }

    /** @return array */
    public function get_extra(): array
    {
        return $this->extra;
    }
}
