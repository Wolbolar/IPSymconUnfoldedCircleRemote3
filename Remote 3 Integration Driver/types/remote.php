<?php

declare(strict_types=1);

class RemoteType
{
    public static function GetSuggestions(): array
    {
        // Kein automatischer Vorschlag, Nutzer muss selbst konfigurieren
        return [];
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'on':
                return self::triggerSwitch($entityConfig['switch_id_on'] ?? 0, true);
            case 'off':
                return self::triggerSwitch($entityConfig['switch_id_off'] ?? 0, true);
            case 'toggle':
                if (isset($entityConfig['switch_id_toggle']) && IPS_VariableExists($entityConfig['switch_id_toggle'])) {
                    $current = GetValue($entityConfig['switch_id_toggle']);
                    RequestAction($entityConfig['switch_id_toggle'], !$current);
                    return true;
                }
                break;

            case 'send_cmd':
                if (isset($entityConfig['command_script']) && IPS_ScriptExists($entityConfig['command_script'])) {
                    IPS_RunScriptEx($entityConfig['command_script'], [
                        'command' => $params['command'] ?? '',
                        'repeat' => $params['repeat'] ?? 1,
                        'delay' => $params['delay'] ?? 0,
                        'hold' => $params['hold'] ?? 0
                    ]);
                    return true;
                }
                break;

            case 'send_cmd_sequence':
                if (isset($entityConfig['sequence_script']) && IPS_ScriptExists($entityConfig['sequence_script'])) {
                    IPS_RunScriptEx($entityConfig['sequence_script'], [
                        'sequence' => $params['sequence'] ?? [],
                        'repeat' => $params['repeat'] ?? 1,
                        'delay' => $params['delay'] ?? 0,
                        'hold' => $params['hold'] ?? 0
                    ]);
                    return true;
                }
                break;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        if (isset($entityConfig['state_id']) && IPS_VariableExists($entityConfig['state_id'])) {
            return [
                'state' => GetValue($entityConfig['state_id']) ? 'ON' : 'OFF'
            ];
        }

        return ['state' => 'OFF'];
    }

    private static function triggerSwitch(int $id, bool $value): bool
    {
        if ($id > 0 && IPS_VariableExists($id)) {
            RequestAction($id, $value);
            return true;
        }
        return false;
    }
    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class,
            Capability_SwitchToggle::class,
            Capability_SendCommand::class
        ];
    }
}
