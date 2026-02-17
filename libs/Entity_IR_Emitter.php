<?php

declare(strict_types=1);

class Entity_IR_Emitter
{
    // Features
    public const FEATURE_LEARN_IR  = 'learn_ir';  // The device supports learning an IR command.
    public const FEATURE_SEND_IR   = 'send_ir';   // The device supports sending an IR command.

    // Attributes
    public const ATTR_STATE                = 'state';             // Current state of the emitter (e.g. ON/OFF/IDLE)

    // States
    public const STATE_ON                  = 'ON';                // The IR emitter is active

    // Command IDs
    public const CMD_SEND_IR       = 'send_ir';   // Send a previously learned IR command

    // Command Parameters
    public const PARAM_CODE          = 'code';        // IR code to send.
    public const PARAM_FORMAT                = 'format';              // Optional IR format of code if the emitter supports multiple IR formats. Defaults to PRONTO if not specified.
    public const PARAM_PORT                = 'port';              // Optional: output port identifier. Only required if the emitter supports multiple outputs.
    public const PARAM_REPEAT              = 'repeat';            // Optional: how many times the command shall be repeated. Defaults to 1 if not specified (single command without repeat).
}
