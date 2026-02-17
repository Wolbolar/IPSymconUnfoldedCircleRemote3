<?php

declare(strict_types=1);

class ButtonType
{
    public static function GetSuggestions(): array
    {
        $result = [];

        foreach (IPS_GetScriptList() as $id) {
            $name = IPS_GetName($id);
            $parent = IPS_GetName(IPS_GetParent($id));

            $result[] = [
                'name' => "$parent → $name",
                'script_id' => $id,
                'use' => false
            ];
        }

        return $result;
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        if ($cmdId !== 'push') {
            return false;
        }

        if (!isset($entityConfig['script_id']) || !IPS_ScriptExists($entityConfig['script_id'])) {
            return false;
        }

        IPS_RunScript($entityConfig['script_id']);
        return true;
    }

    public static function BuildState(array $entityConfig): array
    {
        return [
            'state' => 'AVAILABLE'
        ];
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_Power::class // für simplen ON-Zustand (stateless AVAILABLE)
        ];
    }
}
