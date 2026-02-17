<?php

declare(strict_types=1);

class SwitchType
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

            if ($v['VariableType'] !== 0 || stripos($profile, 'switch') === false) {
                continue;
            }

            $parent = IPS_GetParent($id);
            $name = IPS_GetName($id);

            $result[] = [
                'name' => "$parent → $name",
                'var_id' => $id,
                'use' => false
            ];
        }

        return $result;
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        if (!isset($entityConfig['var_id']) || !IPS_VariableExists($entityConfig['var_id'])) {
            return false;
        }

        switch ($cmdId) {
            case 'on':
                RequestAction($entityConfig['var_id'], true);
                return true;

            case 'off':
                RequestAction($entityConfig['var_id'], false);
                return true;

            case 'toggle':
                $current = GetValue($entityConfig['var_id']);
                RequestAction($entityConfig['var_id'], !$current);
                return true;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        $state = 'OFF';

        if (isset($entityConfig['var_id']) && IPS_VariableExists($entityConfig['var_id'])) {
            $state = GetValue($entityConfig['var_id']) ? 'ON' : 'OFF';
        }

        return ['state' => $state];
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class,
            Capability_SwitchToggle::class
        ];
    }
}


