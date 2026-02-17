<?php

declare(strict_types=1);

class Capability_Power
{
    public static function GetAttributes(int $variableID): array
    {
        $value = GetValue($variableID);
        return [
            'state' => $value ? 'ON' : 'OFF'
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (!isset($params['state'])) {
            return;
        }

        $state = strtoupper((string) $params['state']);
        $value = $state === 'ON' ? true : false;

        RequestAction($variableID, $value);
    }

    public static function GetCapabilities(): array
    {
        return ['power'];
    }
}
