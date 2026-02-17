<?php

declare(strict_types=1);

class Capability_CoverTilt
{
    public static function GetAttributes(array $entity): array
    {
        $attributes = [];

        if (isset($entity['tilt_id']) && IPS_VariableExists($entity['tilt_id'])) {
            $attributes['tilt_position'] = (int)GetValue($entity['tilt_id']);
        }

        return $attributes;
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        if ($cmdId === 'tilt' && isset($entity['tilt_id'], $params['tilt_position']) && IPS_VariableExists($entity['tilt_id'])) {
            RequestAction($entity['tilt_id'], (int)$params['tilt_position']);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['tilt_position'];
    }
}
