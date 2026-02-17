<?php

declare(strict_types=1);

class LightType
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

            // Haupt-Schaltvariable (bool, vermutlich ein Schalter)
            if ($v['VariableType'] !== 0 || stripos($profile, 'switch') === false) {
                continue;
            }

            $parent = IPS_GetParent($id);
            $name = IPS_GetName($id);

            // Suche nach möglichen Zusatzfunktionen (Brightness, Farbe, Farbtemperatur)
            $children = IPS_GetChildrenIDs($parent);
            $brightnessId = 0;
            $hueId = 0;
            $saturationId = 0;
            $colorTempId = 0;

            foreach ($children as $childId) {
                if (!IPS_VariableExists($childId) || $childId === $id) {
                    continue;
                }

                $cv = IPS_GetVariable($childId);
                $cprofile = $cv['VariableCustomProfile'] ?: $cv['VariableProfile'];

                if ($cv['VariableType'] === 1 && stripos($cprofile, 'intensity') !== false) {
                    $brightnessId = $childId;
                } elseif ($cv['VariableType'] === 1 && stripos($cprofile, 'hue') !== false) {
                    $hueId = $childId;
                } elseif ($cv['VariableType'] === 1 && stripos($cprofile, 'saturation') !== false) {
                    $saturationId = $childId;
                } elseif ($cv['VariableType'] === 1 && stripos($cprofile, 'temperature') !== false) {
                    $colorTempId = $childId;
                }
            }

            $result[] = [
                'name' => $name,
                'parent' => IPS_GetName($parent),
                'var_id' => $id,
                'brightness' => $brightnessId,
                'hue' => $hueId,
                'saturation' => $saturationId,
                'color_temperature' => $colorTempId,
            ];
        }

        return $result;
    }
    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'on':
                if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
                    RequestAction($entityConfig['var_id'], true);
                }
                if (isset($params['brightness'], $entityConfig['brightness']) && IPS_VariableExists($entityConfig['brightness'])) {
                    RequestAction($entityConfig['brightness'], (int)$params['brightness']);
                }
                if (isset($params['hue'], $entityConfig['hue']) && IPS_VariableExists($entityConfig['hue'])) {
                    RequestAction($entityConfig['hue'], (int)$params['hue']);
                }
                if (isset($params['saturation'], $entityConfig['saturation']) && IPS_VariableExists($entityConfig['saturation'])) {
                    RequestAction($entityConfig['saturation'], (int)$params['saturation']);
                }
                if (isset($params['color_temperature'], $entityConfig['color_temperature']) && IPS_VariableExists($entityConfig['color_temperature'])) {
                    RequestAction($entityConfig['color_temperature'], (int)$params['color_temperature']);
                }
                return true;

            case 'off':
                if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
                    RequestAction($entityConfig['var_id'], false);
                    return true;
                }
                break;

            case 'toggle':
                if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
                    $current = GetValue($entityConfig['var_id']);
                    RequestAction($entityConfig['var_id'], !$current);
                    return true;
                }
                break;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        $attributes = [];

        if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
            $attributes['state'] = GetValue($entityConfig['var_id']) ? 'ON' : 'OFF';
        }

        if (isset($entityConfig['brightness']) && IPS_VariableExists($entityConfig['brightness'])) {
            $attributes['brightness'] = (int)GetValue($entityConfig['brightness']);
        }

        if (isset($entityConfig['hue']) && IPS_VariableExists($entityConfig['hue'])) {
            $attributes['hue'] = (int)GetValue($entityConfig['hue']);
        }

        if (isset($entityConfig['saturation']) && IPS_VariableExists($entityConfig['saturation'])) {
            $attributes['saturation'] = (int)GetValue($entityConfig['saturation']);
        }

        if (isset($entityConfig['color_temperature']) && IPS_VariableExists($entityConfig['color_temperature'])) {
            $attributes['color_temperature'] = (int)GetValue($entityConfig['color_temperature']);
        }

        return $attributes;
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class,
            Capability_Brightness::class,
            Capability_Color::class,
            Capability_ColorTemperature::class,
            Capability_SwitchToggle::class
        ];
    }
}
