<?php

declare(strict_types=1);

class Capability_Brightness
{
    public static function GetAttributes(int $variableID): array
    {
        $value = GetValue($variableID);
        return [
            'brightness' => intval($value)  // Remote 3 erwartet Integer zwischen 0–100
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (!isset($params['brightness'])) {
            return;
        }

        $value = intval($params['brightness']);

        // Wertebereich validieren
        $value = max(0, min(100, $value));

        // Wenn z. B. das Variablenprofil 0–255 nutzt, hier ggf. umrechnen
        $profile = IPS_GetVariable($variableID)['VariableProfile'];
        if ($profile === '~Intensity.255') {
            $value = intval($value * 2.55);
        }

        // Setze Wert in Symcon
        RequestAction($variableID, $value);
    }

    public static function GetCapabilities(): array
    {
        return ['brightness'];
    }
}