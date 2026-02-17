<?php

declare(strict_types=1);

class Capability_Playback
{
    public static function GetAttributes(int $stateVariableID): array
    {
        if (!IPS_VariableExists($stateVariableID)) {
            return [];
        }

        return [
            'playback_state' => GetValue($stateVariableID)
        ];
    }

    public static function HandleCommand(array $entityConfig, string $cmdId): void
    {
        if (!isset($entityConfig[$cmdId . '_script'])) {
            return;
        }

        $scriptId = $entityConfig[$cmdId . '_script'];
        if (IPS_ScriptExists($scriptId)) {
            IPS_RunScript($scriptId);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['play_pause', 'stop', 'next', 'previous'];
    }
}
