<?php

declare(strict_types=1);

class IrEmitterType
{
    public static function GetSuggestions(): array
    {
        // IR-Emitter haben keinen automatischen Status – manuelle Konfiguration nötig
        return [];
    }

    public static function HandleCommand(array $entityConfig, string $cmdId, array $params): bool
    {
        switch ($cmdId) {
            case 'send_ir':
                $code = $params['code'] ?? '';
                $repeat = $params['repeat'] ?? 1;
                $port = $params['port'] ?? null;
                $format = $params['format'] ?? 'PRONTO';

                if ($code === '') {
                    return false;
                }

                // Sende IR-Code über IR-Adapter deiner Wahl (z.B. Broadlink, Global Caché, eigene Logik)
                // Beispiel: logge nur den Aufruf
                IPS_LogMessage('IR Emitter', "Send IR: format=$format, repeat=$repeat, port=$port, code=$code");

                return true;

            case 'stop_ir':
                $port = $params['port'] ?? null;
                IPS_LogMessage('IR Emitter', "Stop IR on port $port");
                return true;
        }

        return false;
    }

    public static function BuildState(array $entityConfig): array
    {
        // Immer als verfügbar markieren
        return ['state' => 'ON'];
    }

    public static function GetCapabilitiesFor(array $entity): array
    {
        return [
            Capability_IrEmitter::class
        ];
    }
}
