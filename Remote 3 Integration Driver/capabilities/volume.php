<?php

declare(strict_types=1);

class Capability_Volume
{
    public static function GetAttributes(int $variableID): array
    {
        $value = GetValue($variableID);
        return [
            'volume' => (int)$value
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (isset($params['volume'])) {
            RequestAction($variableID, (int)$params['volume']);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['volume'];
    }
}
