<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
/**
 * Italix Calendars - DriverRegistry
 *
 * @package Italix\Calendars
 * @license MPL-2.0
 */

declare(strict_types=1);

namespace Italix\Calendars\Drivers;

use InvalidArgumentException;

/**
 * Registry that maps driver names to driver instances.
 *
 * Allows registering multiple calendar drivers and retrieving them by
 * name. Useful when the driver choice is dynamic (e.g. per-view config,
 * or while trialling a second library before fully switching over).
 *
 * Example:
 *
 *     $registry = new DriverRegistry();
 *     $registry->register(new FullCalendarDriver());
 *
 *     $driver = $registry->get('full_calendar');
 *     $config = $driver->render($calendar);
 */
class DriverRegistry
{
    /** @var array<string, DriverInterface> */
    private $drivers = [];

    /**
     * Register a driver.
     *
     * @param DriverInterface $driver
     * @return self
     */
    public function register(DriverInterface $driver): self
    {
        $this->drivers[$driver->get_name()] = $driver;
        return $this;
    }

    /**
     * Get a driver by name.
     *
     * @param string $name
     * @return DriverInterface
     * @throws InvalidArgumentException If the driver is not registered
     */
    public function get(string $name): DriverInterface
    {
        if (!isset($this->drivers[$name])) {
            $available = implode(', ', array_keys($this->drivers));
            throw new InvalidArgumentException(
                "Driver '{$name}' is not registered. Available: {$available}"
            );
        }

        return $this->drivers[$name];
    }

    /**
     * Check if a driver is registered.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, DriverInterface>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
