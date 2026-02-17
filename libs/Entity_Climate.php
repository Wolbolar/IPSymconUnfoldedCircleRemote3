<?php

declare(strict_types=1);

class Entity_Climate
{
    // --- States / HVAC Modes ---
    public const STATE_OFF = 'OFF'; // The climate device is switched off.
    public const STATE_HEAT = 'HEAT'; // The device is set to heating, optionally to a set target temperature.
    public const STATE_COOL = 'COOL'; // The device is set to cooling, optionally to a set target temperature.
    public const STATE_HEAT_COOL = 'HEAT_COOL'; // The device is set to heat or cool to a target temperature range.
    public const STATE_FAN = 'FAN'; // Fan-only mode without heating or cooling.
    public const STATE_AUTO = 'AUTO'; // The device is set to automatic mode (e.g. schedule, presence detection).

    // --- Commands (cmd_id) ---
    public const CMD_ON = 'on'; // Switch on the climate device.
    public const CMD_OFF = 'off'; // Switch off the climate device.
    public const CMD_SET_HVAC_MODE = 'hvac_mode'; // Set the device to heating, cooling, etc. See state.
    public const CMD_SET_TARGET_TEMPERATURE = 'target_temperature'; // Change the target temperature.

    // --- Attributes ---
    public const ATTR_HVAC_MODE = 'hvac_mode'; // Current HVAC mode
    public const ATTR_TARGET_TEMPERATURE = 'target_temperature'; // Setpoint temperature
    public const ATTR_CURRENT_TEMPERATURE = 'current_temperature'; // Measured current temperature

    // --- Supported features ---
    public const FEATURE_ON_OFF = 'on_off'; // The device can be turned on and off. The active HVAC mode after power on is device specific and must be reflected in the state attribute.
    public const FEATURE_HEAT = 'heat'; // The device supports heating.
    public const FEATURE_COOL = 'cool'; // The device supports cooling.
    public const FEATURE_CURRENT_TEMPERATURE = 'current_temperature'; // The device can measure the current temperature.
    public const FEATURE_TARGET_TEMPERATURE = 'target_temperature'; // The device supports a target temperature for heating or cooling.

    // --- Presets (if supported in future) ---
    // public const PRESET_ECO = 'eco';
    // public const PRESET_COMFORT = 'comfort';
    // public const PRESET_HOME = 'home';
    // public const PRESET_AWAY = 'away';
}
