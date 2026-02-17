<?php

declare(strict_types=1);

class MediaPlayerType
{
    public static function GetSuggestions(): array
    {
        // keine automatische Suche; Benutzerzuweisung erforderlich
        return [];
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'on':
            case 'off':
            case 'toggle':
                if (isset($entityConfig['power_id']) && IPS_VariableExists($entityConfig['power_id'])) {
                    $value = $cmdId === 'on' ? true : ($cmdId === 'off' ? false : !GetValue($entityConfig['power_id']));
                    RequestAction($entityConfig['power_id'], $value);
                    return true;
                }
                break;

            case 'play_pause':
            case 'stop':
            case 'next':
            case 'previous':
                if (isset($entityConfig[$cmdId . '_id']) && IPS_ScriptExists($entityConfig[$cmdId . '_id'])) {
                    IPS_RunScript($entityConfig[$cmdId . '_id']);
                    return true;
                }
                break;

            case 'volume':
                if (isset($entityConfig['volume_id']) && IPS_VariableExists($entityConfig['volume_id']) && isset($params['volume'])) {
                    RequestAction($entityConfig['volume_id'], (int)$params['volume']);
                    return true;
                }
                break;

            case 'mute':
            case 'unmute':
            case 'mute_toggle':
                if (isset($entityConfig['mute_id']) && IPS_VariableExists($entityConfig['mute_id'])) {
                    if ($cmdId === 'mute_toggle') {
                        $value = !GetValue($entityConfig['mute_id']);
                    } else {
                        $value = ($cmdId === 'mute');
                    }
                    RequestAction($entityConfig['mute_id'], $value);
                    return true;
                }
                break;

            case 'repeat':
                if (isset($entityConfig['repeat_id']) && IPS_VariableExists($entityConfig['repeat_id']) && isset($params['repeat'])) {
                    RequestAction($entityConfig['repeat_id'], $params['repeat']);
                    return true;
                }
                break;

            case 'shuffle':
                if (isset($entityConfig['shuffle_id']) && IPS_VariableExists($entityConfig['shuffle_id']) && isset($params['shuffle'])) {
                    RequestAction($entityConfig['shuffle_id'], $params['shuffle']);
                    return true;
                }
                break;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        $attributes = [];

        if (isset($entityConfig['power_id']) && IPS_VariableExists($entityConfig['power_id'])) {
            $attributes['state'] = GetValue($entityConfig['power_id']) ? 'ON' : 'OFF';
        }

        if (isset($entityConfig['volume_id']) && IPS_VariableExists($entityConfig['volume_id'])) {
            $attributes['volume'] = (int)GetValue($entityConfig['volume_id']);
        }

        if (isset($entityConfig['mute_id']) && IPS_VariableExists($entityConfig['mute_id'])) {
            $attributes['muted'] = (bool)GetValue($entityConfig['mute_id']);
        }

        if (isset($entityConfig['repeat_id']) && IPS_VariableExists($entityConfig['repeat_id'])) {
            $attributes['repeat'] = GetValue($entityConfig['repeat_id']);
        }

        if (isset($entityConfig['shuffle_id']) && IPS_VariableExists($entityConfig['shuffle_id'])) {
            $attributes['shuffle'] = GetValue($entityConfig['shuffle_id']);
        }

        return $attributes;
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class,
            Capability_Volume::class,
            Capability_Mute::class,
            Capability_Playback::class,
            Capability_RepeatShuffle::class
        ];
    }
}


