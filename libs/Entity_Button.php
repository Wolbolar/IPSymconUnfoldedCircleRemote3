<?php

declare(strict_types=1);

class Entity_Button
{
    // --- States ---
    public const STATE_AVAILABLE = 'AVAILABLE'; // The button entity is online and available to receive commands.

    // --- Supported features ---
    public const FEATURE_PRESS = 'press'; // The button can be "pushed", e.g. via touchscreen, physical press, or remote.

    // --- Commands ---
    public const CMD_PUSH = 'push'; // Triggers the button’s push action (momentary interaction).
}
