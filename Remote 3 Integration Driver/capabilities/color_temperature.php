<?php

declare(strict_types=1);

class Capability_ColorTemperature
{
    public static function GetAttributes(int $variableID): array
    {
        return [
            'color_temperature' => (int)GetValue($variableID)
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (isset($params['color_temperature'])) {
            $value = (int)$params['color_temperature'];
            RequestAction($variableID, $value);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['color_temperature'];
    }
}
