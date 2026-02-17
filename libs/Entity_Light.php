<?php

declare(strict_types=1);

class Entity_Light
{
    // --- States ---
    public const STATE_ON = 'ON'; // The light is turned on.
    public const STATE_OFF = 'OFF'; // The light is turned off.

    // --- Features ---
    public const FEATURE_ON_OFF = 'on_off'; // The light can be turned on and off.
    public const FEATURE_DIM = 'dim'; // The brightness level can be controlled.
    public const FEATURE_COLOR_TEMP = 'color_temperature'; // The color temperature can be adjusted.
    public const FEATURE_COLOR = 'color'; // The color can be changed using hue/saturation values.

    // --- Attributes ---
    public const ATTR_STATE = 'state'; // The current state of the light (ON or OFF).
    public const ATTR_BRIGHTNESS = 'brightness'; // Brightness level (0–255).
    public const ATTR_COLOR_TEMPERATURE = 'color_temperature'; // Color temperature.
    public const ATTR_HUE = 'hue'; // Hue value (0–360).
    public const ATTR_SATURATION = 'saturation'; // Saturation level (0–255).

    // --- Commands ---
    public const CMD_ON = 'on'; // Turn the light on (optionally with brightness, color, etc.).
    public const CMD_OFF = 'off'; // Turn the light off.
    public const CMD_TOGGLE = 'toggle'; // Toggle between on and off.
}
