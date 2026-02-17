<?php

declare(strict_types=1);

class Capability_RepeatShuffle
{
    public static function GetAttributes(array $entity): array
    {
        $attributes = [];

        if (isset($entity['repeat_id']) && IPS_VariableExists($entity['repeat_id'])) {
            $attributes['repeat'] = GetValue($entity['repeat_id']);
        }

        if (isset($entity['shuffle_id']) && IPS_VariableExists($entity['shuffle_id'])) {
            $attributes['shuffle'] = GetValue($entity['shuffle_id']);
        }

        return $attributes;
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        if ($cmdId === 'repeat' && isset($entity['repeat_id'], $params['repeat']) && IPS_VariableExists($entity['repeat_id'])) {
            RequestAction($entity['repeat_id'], $params['repeat']);
        }

        if ($cmdId === 'shuffle' && isset($entity['shuffle_id'], $params['shuffle']) && IPS_VariableExists($entity['shuffle_id'])) {
            RequestAction($entity['shuffle_id'], $params['shuffle']);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['repeat', 'shuffle'];
    }
}
