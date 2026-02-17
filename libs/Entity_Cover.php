<?php

declare(strict_types=1);

class Entity_Cover
{
    // --- States ---
    public const STATE_OPENING = 'OPENING'; // The cover is in the process of opening. Either fully opened or to a set position.
    public const STATE_OPEN = 'OPEN'; // The cover is in the open state.
    public const STATE_CLOSING = 'CLOSING'; // The cover is in the process of closing. Either fully closed or to a set position.
    public const STATE_CLOSED = 'CLOSED'; // The cover is in the closed state.

    // --- Device Classes ---
    public const DEVICE_CLASS_BLIND = 'blind'; // Window blinds or shutters which can be opened, closed or tilted.
    public const DEVICE_CLASS_CURTAIN = 'curtain'; // Window curtain or drapes which can be opened or closed.
    public const DEVICE_CLASS_GARAGE = 'garage'; // Controllable garage door.
    public const DEVICE_CLASS_SHADE = 'shade'; // Sun shades which can be opened to protect an area from the sun.

    // --- Commands ---
    public const CMD_OPEN = 'open'; // Open the cover fully.
    public const CMD_CLOSE = 'close'; // Close the cover fully.
    public const CMD_STOP = 'stop'; // Stop the cover while moving.
    public const CMD_POSITION = 'position'; // Move the cover to a specified position.

    // --- Attributes ---
    public const ATTR_STATE = 'state'; // Current state of the cover.
    public const ATTR_POSITION = 'position'; // Current or target position of the cover (0 = closed, 100 = open).

    // --- Supported Features ---
    public const FEATURE_OPEN = 'open'; // Supports opening.
    public const FEATURE_CLOSE = 'close'; // Supports closing.
    public const FEATURE_STOP = 'stop'; // Supports stopping.
    public const FEATURE_POSITION = 'position'; // Supports setting or reporting cover position.
}
