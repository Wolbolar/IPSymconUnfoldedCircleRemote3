<?php

declare(strict_types=1);

class SensorType
{
    public static function GetSuggestions(): array
    {
        $result = [];

        foreach (IPS_GetObjectList() as $id) {
            if (!IPS_VariableExists($id)) {
                continue;
            }

            $v = IPS_GetVariable($id);
            $profile = $v['VariableCustomProfile'] ?: $v['VariableProfile'];

            // Akzeptiere alles mit Zahl oder Text (Zustandswert), keine boolschen Sensoren
            if (!in_array($v['VariableType'], [1, 2, 3])) {
                continue;
            }

            $parent = IPS_GetParent($id);
            $name = IPS_GetName($id);

            $result[] = [
                'name' => "$parent → $name",
                'var_id' => $id,
                'unit' => '',   // Optional definierbar durch Konfiguration
                'use' => false
            ];
        }

        return $result;
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        // Sensoren empfangen keine Kommandos
        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        $attributes = [];

        if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
            $value = GetValue($entityConfig['var_id']);
            $attributes['value'] = $value;

            if (!empty($entityConfig['unit'])) {
                $attributes['unit'] = $entityConfig['unit'];
            }

            // Optionaler Zustand
            $attributes['state'] = 'ON';
        }

        return $attributes;
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Temperature::class
        ];
    }
}


