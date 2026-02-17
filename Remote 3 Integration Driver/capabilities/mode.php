<?php

declare(strict_types=1);

class Capability_Mode
{
    public static function GetAttributes(int $variableID): array
    {
        return [
            'hvac_mode' => GetValue($variableID)
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (!isset($params['hvac_mode'])) {
            return;
        }

        RequestAction($variableID, $params['hvac_mode']);
    }

    public static function GetCapabilities(): array
    {
        return ['hvac_mode'];
    }
}
