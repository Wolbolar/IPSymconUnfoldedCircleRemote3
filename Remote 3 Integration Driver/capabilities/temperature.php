<?php

declare(strict_types=1);

class Capability_Temperature
{
    public static function GetAttributes(int $variableID, string $type = 'current'): array
    {
        $value = GetValue($variableID);
        return [
            $type === 'target' ? 'target_temperature' : 'current_temperature' => (float)$value
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (isset($params['temperature'])) {
            RequestAction($variableID, (float)$params['temperature']);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['temperature'];
    }
}
