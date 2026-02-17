<?php

declare(strict_types=1);

class Capability_Mute
{
    public static function GetAttributes(int $variableID): array
    {
        return [
            'muted' => (bool)GetValue($variableID)
        ];
    }

    public static function HandleCommand(int $variableID, array $params): void
    {
        if (!isset($params['command'])) {
            return;
        }

        $command = $params['command'];

        switch ($command) {
            case 'mute':
                RequestAction($variableID, true);
                break;
            case 'unmute':
                RequestAction($variableID, false);
                break;
            case 'mute_toggle':
                $current = GetValue($variableID);
                RequestAction($variableID, !$current);
                break;
        }
    }

    public static function GetCapabilities(): array
    {
        return ['muted'];
    }
}
