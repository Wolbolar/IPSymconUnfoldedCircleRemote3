<?php

declare(strict_types=1);

class CoverType
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

            if ($v['VariableType'] !== 1 || stripos($profile, 'position') === false) {
                continue;
            }

            $parent = IPS_GetParent($id);
            $name = IPS_GetName($id);

            $result[] = [
                'name' => "$parent → $name",
                'position_id' => $id,
                'tilt_id' => 0,
                'switch_id_open' => 0,
                'switch_id_close' => 0,
                'switch_id_stop' => 0,
                'use' => false
            ];
        }

        return $result;
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'open':
                return self::triggerSwitch($entityConfig['switch_id_open'] ?? 0);
            case 'close':
                return self::triggerSwitch($entityConfig['switch_id_close'] ?? 0);
            case 'stop':
                return self::triggerSwitch($entityConfig['switch_id_stop'] ?? 0);
            case 'position':
                if (isset($entityConfig['position_id']) && IPS_VariableExists($entityConfig['position_id'])) {
                    $position = $params['position'] ?? null;
                    if ($position !== null) {
                        RequestAction($entityConfig['position_id'], (int)$position);
                        return true;
                    }
                }
                break;
            case 'tilt':
                if (isset($entityConfig['tilt_id']) && IPS_VariableExists($entityConfig['tilt_id'])) {
                    $tilt = $params['tilt_position'] ?? null;
                    if ($tilt !== null) {
                        RequestAction($entityConfig['tilt_id'], (int)$tilt);
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

        if (isset($entityConfig['position_id']) && IPS_VariableExists($entityConfig['position_id'])) {
            $attributes['position'] = (int)GetValue($entityConfig['position_id']);
        }

        if (isset($entityConfig['tilt_id']) && IPS_VariableExists($entityConfig['tilt_id'])) {
            $attributes['tilt_position'] = (int)GetValue($entityConfig['tilt_id']);
        }

        // Optionaler Status könnte mit externer Logik ergänzt werden: OPEN/CLOSED
        return $attributes;
    }

    private static function triggerSwitch(int $id): bool
    {
        if ($id > 0 && IPS_VariableExists($id)) {
            RequestAction($id, true);
            return true;
        }
        return false;
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_CoverPosition::class,
            Capability_CoverTilt::class
        ];
    }
}


