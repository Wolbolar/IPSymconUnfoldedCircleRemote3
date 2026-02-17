<?php

declare(strict_types=1);

class Capability_SendCommand
{
    public static function GetAttributes(array $entity): array
    {
        return [
            'state' => 'ON'
        ];
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        if ($cmdId === 'send_cmd' && isset($entity['command_script']) && IPS_ScriptExists($entity['command_script'])) {
            IPS_RunScriptEx($entity['command_script'], [
                'command' => $params['command'] ?? '',
                'repeat'  => $params['repeat'] ?? 1,
                'delay'   => $params['delay'] ?? 0,
                'hold'    => $params['hold'] ?? 0
            ]);
        }

        if ($cmdId === 'send_cmd_sequence' && isset($entity['sequence_script']) && IPS_ScriptExists($entity['sequence_script'])) {
            IPS_RunScriptEx($entity['sequence_script'], [
                'sequence' => $params['sequence'] ?? [],
                'repeat'   => $params['repeat'] ?? 1,
                'delay'    => $params['delay'] ?? 0,
                'hold'     => $params['hold'] ?? 0
            ]);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['send_cmd', 'send_cmd_sequence'];
    }
}
