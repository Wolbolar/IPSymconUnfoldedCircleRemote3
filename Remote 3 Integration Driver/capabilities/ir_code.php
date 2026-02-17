<?php

declare(strict_types=1);

class Capability_IrEmitter
{
    public static function GetAttributes(array $entity): array
    {
        return [
            'state' => 'ON'
        ];
    }

    public static function HandleCommand(array $entity, string $cmdId, array $params): void
    {
        switch ($cmdId) {
            case 'send_ir':
                $code = $params['code'] ?? '';
                $repeat = $params['repeat'] ?? 1;
                $port = $params['port'] ?? null;
                $format = $params['format'] ?? 'PRONTO';

                // Beispiel-Logging oder tatsächliche Anbindung an IR-Sendeinstanz
                IPS_LogMessage('IR Emitter', "Send IR: format=$format, repeat=$repeat, port=$port, code=$code");
                break;

            case 'stop_ir':
                $port = $params['port'] ?? null;
                IPS_LogMessage('IR Emitter', "Stop IR on port $port");
                break;
        }
    }

    public static function GetCapabilities(): array
    {
        return ['send_ir', 'stop_ir'];
    }
}
