<?php

declare(strict_types=1);

class Capability_CoverPosition
{
    public static function GetAttributes(array $entity): array
    {
        $attributes = [];

        if (isset($entity['position_id']) && IPS_VariableExists($entity['position_id'])) {
            $position = (int)GetValue($entity['position_id']);
            $attributes['position'] = $position;

            // Optional: einfachen Status aus Position ableiten
            if ($position === 0) {
                $attributes['state'] = 'CLOSED';
            } elseif ($position === 100) {
                $attributes['state'] = 'OPEN';
            } else {
                $attributes['state'] = 'PARTIALLY_OPEN';
            }
        }

        return $attributes;
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        if ($cmdId === 'position' && isset($entity['position_id'], $params['position']) && IPS_VariableExists($entity['position_id'])) {
            $position = (int)$params['position'];
            RequestAction($entity['position_id'], $position);
        }
    }

    public static function GetCapabilities(): array
    {
        return ['position'];
    }
}
