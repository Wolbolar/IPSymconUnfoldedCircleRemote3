<?php

declare(strict_types=1);

class Capability_SwitchToggle
{
    public static function GetAttributes(int $variableID): array
    {
        return [
            'state' => GetValue($variableID) ? 'ON' : 'OFF'
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        $current = GetValue($variableID);
        RequestAction($variableID, !$current);
    }

    public static function GetCapabilities(): array
    {
        return ['toggle'];
    }
}
