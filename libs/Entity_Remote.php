<?php

declare(strict_types=1);

class Entity_Remote
{
    // --- States ---
    public const STATE_ON = 'ON'; // The remote device is currently online and responsive.
    public const STATE_OFF = 'OFF'; // The remote device is currently offline or unreachable.

    // --- Features ---
    public const FEATURE_SEND_COMMAND = 'send_cmd'; // Can send IR/Bluetooth/other remote commands.
    public const FEATURE_ON_OFF = 'on_off'; // Remote has on and off commands.
    public const FEATURE_TOGGLE = 'toggle'; // Power toggle support. If there's no native support, the remote will use the current state of the remote to send the corresponding on or off command.

    // --- Attributes ---
    public const ATTR_STATE = 'state'; // Current state of the remote.

    // --- Commands ---
    public const CMD_ON = 'on'; // Send the on-command to the controlled device.
    public const CMD_OFF = 'off'; // Send the off-command to the controlled device.
    public const CMD_TOGGLE = 'toggle'; // Send the toggle-command to the controlled device.
    public const CMD_SEND_COMMAND = 'send_cmd'; // A single command.
    public const CMD_SEND_COMMAND_SEQUENCE = 'send_cmd_sequence'; // Command list. Same defaults are used as for the send_cmd command.
}
