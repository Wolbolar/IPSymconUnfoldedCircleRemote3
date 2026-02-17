<?php

declare(strict_types=1);

class Entity_Switch
{
    // --- States ---
    public const STATE_ON = 'ON'; // The switch is turned on.
    public const STATE_OFF = 'OFF'; // The switch is turned off.

    // --- Commands ---
    public const CMD_ON = 'on'; // Switch on the device.
    public const CMD_OFF = 'off'; // Switch off the device.
    public const CMD_TOGGLE = 'toggle'; // Toggle the switch.

    // --- Attributes ---
    public const ATTR_STATE = 'state'; // Current state of the switch (ON or OFF).

    // --- Features ---
    public const FEATURE_ON_OFF = 'on_off'; // The switch can be turned on and off.
    public const FEATURE_TOGGLE = 'toggle'; // The switch supports toggling.
}
