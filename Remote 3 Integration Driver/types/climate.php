<?php

declare(strict_types=1);

class ClimateType
{
    public static function GetSuggestions(): array
    {
        $result = [];

        $allObjects = IPS_GetObjectList();

        foreach ($allObjects as $id) {
            if (!IPS_VariableExists($id)) {
                continue;
            }

            $v = IPS_GetVariable($id);
            $profile = $v['VariableCustomProfile'] ?: $v['VariableProfile'];

            if ($v['VariableType'] !== 1 || stripos($profile, 'Temperature') === false) {
                continue;
            }

            $parent = IPS_GetParent($id);
            $name = IPS_GetName($id);

            $result[] = [
                'name' => "$parent → $name",
                'temperature_id' => $id,
                'mode_id' => 0, // to be filled manually or by matching profile
                'switch_id' => 0,
                'use' => false
            ];
        }

        return $result;
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'on':
                if (isset($entityConfig['switch_id']) && IPS_VariableExists($entityConfig['switch_id'])) {
                    RequestAction($entityConfig['switch_id'], true);
                    return true;
                }
                break;

            case 'off':
                if (isset($entityConfig['switch_id']) && IPS_VariableExists($entityConfig['switch_id'])) {
                    RequestAction($entityConfig['switch_id'], false);
                    return true;
                }
                break;

            case 'hvac_mode':
                if (isset($entityConfig['mode_id']) && IPS_VariableExists($entityConfig['mode_id'])) {
                    $mode = $params['hvac_mode'] ?? '';
                    RequestAction($entityConfig['mode_id'], $mode);
                    return true;
                }
                break;

            case 'target_temperature':
                if (isset($entityConfig['temperature_id']) && IPS_VariableExists($entityConfig['temperature_id'])) {
                    $temp = $params['temperature'] ?? null;
                    if ($temp !== null) {
                        RequestAction($entityConfig['temperature_id'], (float)$temp);
                        return true;
                    }
                }
                break;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        $attributes = [];

        if (isset($entityConfig['mode_id']) && IPS_VariableExists($entityConfig['mode_id'])) {
            $attributes['hvac_mode'] = GetValue($entityConfig['mode_id']);
        }

        if (isset($entityConfig['temperature_id']) && IPS_VariableExists($entityConfig['temperature_id'])) {
            $attributes['target_temperature'] = (float)GetValue($entityConfig['temperature_id']);
        }

        if (isset($entityConfig['current_temp_id']) && IPS_VariableExists($entityConfig['current_temp_id'])) {
            $attributes['current_temperature'] = (float)GetValue($entityConfig['current_temp_id']);
        }

        return $attributes;
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class,
            Capability_Temperature::class,
            Capability_Mode::class
        ];
    }
}


