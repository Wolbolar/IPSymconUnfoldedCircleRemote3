<?php

declare(strict_types=1);

class Capability_Color implements Remote3Capability
{
    use Helper;

    public static function GetAttributes(array $entity): array
    {
        if (!isset($entity['color_var'])) {
            return [];
        }

        $value = GetValue($entity['color_var']);
        return [
            'color' => self::HexToRgb($value)
        ];
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        if (!isset($entity['color_var'])) {
            return;
        }

        switch ($cmdId) {
            case 'set_color':
                if (isset($params['color'])) {
                    $hex = self::RgbToHex($params['color']);
                    RequestAction($entity['color_var'], $hex);
                }
                break;
        }
    }

    private static function RgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    private static function HexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
}
