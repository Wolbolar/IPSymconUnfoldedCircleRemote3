<?php

declare(strict_types=1);

class TemplateType
{
    public static function GetSuggestions(): array
    {
        return []; // Template wird nicht automatisch vorgeschlagen
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'X':
                IPS_LogMessage('TemplateType', 'Command X received');
                return true;

            case 'Y':
                IPS_LogMessage('TemplateType', 'Command Y received with parameter foo=' . ($params['foo'] ?? ''));
                return true;

            case 'Z':
                IPS_LogMessage('TemplateType', 'Command Z received with parameter bar=' . ($params['bar'] ?? ''));
                return true;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        return [
            'state' => 'OFF',
            'X' => 'value of X',
            'Y' => 'value of Y'
        ];
    }
}
