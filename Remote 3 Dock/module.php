<?php


declare(strict_types=1);
// ---- Symcon variable presentation constants (guarded) ----
// Some IDE stubs / environments may not provide these constants at parse time.
// IPS_SetVariableCustomPresentation expects GUID strings for PRESENTATION.
if (!defined('VARIABLE_PRESENTATION_SLIDER')) {
    define('VARIABLE_PRESENTATION_SLIDER', '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}');
}
if (!defined('VARIABLE_PRESENTATION_SWITCH')) {
    define('VARIABLE_PRESENTATION_SWITCH', '{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}');
}
if (!defined('VARIABLE_PRESENTATION_ENUMERATION')) {
    define('VARIABLE_PRESENTATION_ENUMERATION', '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}');
}

// ---------------------------------------------------------

class Remote3Dock extends IPSModuleStrict
{
    public function GetCompatibleParents(): string
    {
        // Require the WebSocket Client as parent
        return json_encode([
            'type' => 'require',
            'moduleIDs' => [
                '{9CD1AA03-841E-FB97-8E32-6536A1D4561B}'
            ]
        ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Last received port modes (get_port_modes)
        $this->RegisterAttributeString('port_modes_raw', '');
        $this->RegisterAttributeString('port_modes_ports_json', '');
        $this->RegisterAttributeInteger('port_modes_last_code', 0);
        $this->RegisterAttributeString('port_modes_last_msg', '');

        // Per-port cached fields (for easy variable mapping)
        $this->RegisterAttributeString('port1_mode', '');
        $this->RegisterAttributeString('port1_active_mode', '');
        $this->RegisterAttributeString('port1_supported_modes', '');
        $this->RegisterAttributeString('port2_mode', '');
        $this->RegisterAttributeString('port2_active_mode', '');
        $this->RegisterAttributeString('port2_supported_modes', '');

        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyString('hostname', '');
        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyString('dock_id', '');
        $this->RegisterPropertyString('model', '');
        $this->RegisterPropertyString('version', '');
        $this->RegisterPropertyString('rev', '');
        $this->RegisterPropertyString('ws_path', '');

        // Variable selection (stored as JSON list of rows: [{ident,caption,enabled}, ...])
        $this->RegisterAttributeString('variable_selection', '');

        // Last received sysinfo (stored as attributes for UI display)
        $this->RegisterAttributeString('sysinfo_raw', '');
        $this->RegisterAttributeString('sys_name', '');
        $this->RegisterAttributeString('sys_hostname', '');
        $this->RegisterAttributeString('sys_model', '');
        $this->RegisterAttributeString('sys_version', '');
        $this->RegisterAttributeString('sys_serial', '');
        $this->RegisterAttributeString('sys_revision', '');
        $this->RegisterAttributeString('sys_uptime', '');
        $this->RegisterAttributeString('sys_reset_reason', '');
        $this->RegisterAttributeInteger('sys_features', 0);
        $this->RegisterAttributeInteger('sys_led_brightness', 0);
        $this->RegisterAttributeInteger('sys_eth_led_brightness', 0);
        $this->RegisterAttributeInteger('sys_volume', 0);
        $this->RegisterAttributeInteger('sys_free_heap', 0);
        $this->RegisterAttributeBoolean('sys_ethernet', false);
        $this->RegisterAttributeBoolean('sys_wifi', false);
        $this->RegisterAttributeString('sys_ssid', '');
        $this->RegisterAttributeString('sys_ports_json', '');
        $this->RegisterAttributeInteger('sys_last_code', 0);
        $this->RegisterAttributeString('sys_last_msg', '');

        // Profiles are created in ApplyChanges (context depends on instance lifecycle)
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->EnsurePortControlProfile();
        $this->EnsureVariableSelectionInitialized();

        $enabled = array_flip($this->GetEnabledVariableIdents());
        $avail = $this->GetAvailableVariables();

        foreach ($avail as $ident => $meta) {
            [$caption, $type, $profile, $pos] = $meta;
            $keep = isset($enabled[$ident]);
            $this->MaintainVariable($ident, (string)$caption, (int)$type, (string)$profile, (int)$pos, $keep);
        }

        // Enable actions for writable variables
        $this->EnsureWritableActions($enabled);
        $this->ApplyPresentationsForEnabled($enabled);

        // Remove legacy variables from older versions (no longer used)
        foreach (['Port1Mode', 'Port1ActiveMode', 'Port2Mode', 'Port2ActiveMode'] as $legacyIdent) {
            $this->MaintainVariable($legacyIdent, $legacyIdent, VARIABLETYPE_STRING, '', 0, false);
        }

        // Populate variables with last known values (e.g. after restart / form reload)
        $this->UpdateVariablesFromAttributes(false);

        // Update instance status (prevents "Instanz wurde nicht erstellt" / IS_CREATING lingering)
        $parentId = IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0;
        if ($parentId <= 0) {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $parent = IPS_GetInstance((int)$parentId);
            $this->SetStatus(((int)($parent['InstanceStatus'] ?? IS_INACTIVE) === IS_ACTIVE) ? IS_ACTIVE : IS_INACTIVE);
        }
    }

    /**
     * Ensure actions are enabled for writable variables that currently exist.
     * This is needed because variables can be created/removed dynamically via the selection list
     * without triggering ApplyChanges.
     *
     * @param array $enabledIdents Flip map: ident => true
     */
    private function EnsureWritableActions(array $enabledIdents): void
    {
        foreach (['LEDBrightness', 'EthernetLEDBrightness', 'Volume', 'Port1Control', 'Port2Control'] as $writableIdent) {
            if (isset($enabledIdents[$writableIdent])) {
                $this->EnableAction($writableIdent);
            }
        }
    }

    /**
     * Request the Dock Manager (parent) to fetch port modes from the dock.
     * The response will be forwarded back via ReceiveData.
     */
    public function RequestPortModes(): void
    {
        $payload = [
            'action' => 'get_port_modes'
        ];

        $data = [
            'DataID' => '{F975667E-3B5A-0148-4A47-CB4CD513EAD8}',
            'Buffer' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ];

        $this->SendDebug(__FUNCTION__, '➡️ Requesting port modes via parent: ' . $data['Buffer'], 0);
        $this->SendDataToParent(json_encode($data));
    }


    /**
     * Request the Dock Manager (parent) to fetch system information from the dock.
     * The response will be forwarded back via ReceiveData.
     */
    public function RequestSysInfo(): void
    {
        $payload = [
            'action' => 'get_sysinfo'
        ];

        $data = [
            // Child -> Parent DataID: must match Dock Manager `implemented` and Dock Child `parentRequirements`
            'DataID' => '{F975667E-3B5A-0148-4A47-CB4CD513EAD8}',
            'Buffer' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ];

        $this->SendDebug(__FUNCTION__, '➡️ Requesting sysinfo via parent: ' . $data['Buffer'], 0);
        $this->SendDataToParent(json_encode($data));
    }

    private function SetVarIfExists(string $ident, $value): void
    {
        try {
            $varId = $this->GetIDForIdent($ident);
        } catch (Throwable $e) {
            $this->SendDebug(__FUNCTION__, "Variable ident not found: {$ident}", 0);
            return;
        }

        if ($varId <= 0) {
            $this->SendDebug(__FUNCTION__, "Invalid variable id for ident: {$ident}", 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, "Set {$ident} (VarID {$varId}) => " . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value), 0);

        // IPSModuleStrict: use module SetValue() to update instance variables
        $this->SetValue($ident, $value);
        $this->SendDebug(__FUNCTION__, "Updated variable {$ident}", 0);
    }

    private function UpdateVariablesFromAttributes(bool $debug = false): void
    {
        // Map of all possible values we can write to instance variables
        $map = [
            'Name' => $this->ReadAttributeString('sys_name'),
            'Hostname' => $this->ReadAttributeString('sys_hostname'),
            'Model' => $this->ReadAttributeString('sys_model'),
            'Firmware' => $this->ReadAttributeString('sys_version'),
            'Serial' => $this->ReadAttributeString('sys_serial'),
            'HardwareRevision' => $this->ReadAttributeString('sys_revision'),
            'Uptime' => $this->ReadAttributeString('sys_uptime'),
            'Ethernet' => $this->ReadAttributeBoolean('sys_ethernet'),
            'WiFi' => $this->ReadAttributeBoolean('sys_wifi'),
            'SSID' => $this->ReadAttributeString('sys_ssid'),
            'LEDBrightness' => $this->ReadAttributeInteger('sys_led_brightness'),
            'EthernetLEDBrightness' => $this->ReadAttributeInteger('sys_eth_led_brightness'),
            'Volume' => $this->ReadAttributeInteger('sys_volume'),
            'FreeHeap' => $this->ReadAttributeInteger('sys_free_heap'),
            'ResetReason' => $this->ReadAttributeString('sys_reset_reason'),
            'Features' => $this->ReadAttributeInteger('sys_features'),
            'Ports' => $this->ReadAttributeString('sys_ports_json'),
            'LastResultCode' => $this->ReadAttributeInteger('sys_last_code'),
            'LastResultMessage' => $this->ReadAttributeString('sys_last_msg'),

            // Port modes
            'PortModesRaw' => $this->ReadAttributeString('port_modes_raw'),
            'Port1Control' => ($this->ReadAttributeString('port1_mode') === 'AUTO') ? 0 : 1,
            'Port1SupportedModes' => $this->ReadAttributeString('port1_supported_modes'),
            'Port2Control' => ($this->ReadAttributeString('port2_mode') === 'AUTO') ? 0 : 1,
            'Port2SupportedModes' => $this->ReadAttributeString('port2_supported_modes')
        ];

        // Only write values for variables the user has enabled (and which therefore exist).
        // This avoids runtime warnings when a variable was removed via the selection list.
        $enabled = array_flip($this->GetEnabledVariableIdents());

        foreach ($map as $ident => $value) {
            if (!isset($enabled[$ident])) {
                if ($debug) {
                    $this->SendDebug(__FUNCTION__, "Skipping disabled variable {$ident}", 0);
                }
                continue;
            }

            if ($debug) {
                $this->SendDebug(__FUNCTION__, "Updating {$ident}", 0);
            }

            $this->SetVarIfExists($ident, $value);
        }
    }

    /**
     * All variables this instance can expose.
     * ident => [caption, type, profile, position]
     */
    private function GetAvailableVariables(): array
    {
        return [
            'Name' => ['Name', VARIABLETYPE_STRING, '', 10],
            'Hostname' => ['Hostname', VARIABLETYPE_STRING, '', 20],
            'Model' => ['Model', VARIABLETYPE_STRING, '', 30],
            'Firmware' => ['Firmware', VARIABLETYPE_STRING, '', 40],
            'Serial' => ['Serial', VARIABLETYPE_STRING, '', 50],
            'HardwareRevision' => ['Hardware revision', VARIABLETYPE_STRING, '', 60],
            'Uptime' => ['Uptime', VARIABLETYPE_STRING, '', 70],
            'Ethernet' => ['Ethernet', VARIABLETYPE_BOOLEAN, '~Switch', 80],
            'WiFi' => ['WiFi', VARIABLETYPE_BOOLEAN, '~Switch', 90],
            'SSID' => ['SSID', VARIABLETYPE_STRING, '', 100],
            'LEDBrightness' => ['LED brightness', VARIABLETYPE_INTEGER, '~Intensity.100', 110],
            'EthernetLEDBrightness' => ['Ethernet LED brightness', VARIABLETYPE_INTEGER, '~Intensity.100', 120],
            'Volume' => ['Volume', VARIABLETYPE_INTEGER, '~Volume', 130],
            'FreeHeap' => ['Free heap', VARIABLETYPE_INTEGER, '', 140],
            'ResetReason' => ['Reset reason', VARIABLETYPE_STRING, '', 150],
            'Features' => ['Features', VARIABLETYPE_INTEGER, '', 160],
            'Ports' => ['Ports (JSON)', VARIABLETYPE_STRING, '', 170],
            'LastResultCode' => ['Last result code', VARIABLETYPE_INTEGER, '', 180],
            'LastResultMessage' => ['Last result message', VARIABLETYPE_STRING, '', 190],

            // Port modes
            'PortModesRaw' => ['Port modes (raw JSON)', VARIABLETYPE_STRING, '', 200],
            'Port1Control' => ['Port 1 mode', VARIABLETYPE_INTEGER, 'UCD.DockPortControl', 210],
            'Port1SupportedModes' => ['Port 1 supported modes', VARIABLETYPE_STRING, '', 220],
            'Port2Control' => ['Port 2 mode', VARIABLETYPE_INTEGER, 'UCD.DockPortControl', 230],
            'Port2SupportedModes' => ['Port 2 supported modes', VARIABLETYPE_STRING, '', 240],
        ];
    }

    /**
     * Load selection rows from attribute. If empty, initialize defaults (all enabled).
     */
    private function LoadVariableSelectionRows(): array
    {
        $raw = trim($this->ReadAttributeString('variable_selection'));
        $rows = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        $avail = $this->GetAvailableVariables();

        // If nothing stored yet, default to all enabled
        if (!is_array($rows) || count($rows) === 0) {
            $defaults = [];
            foreach ($avail as $ident => $meta) {
                $defaults[] = [
                    'ident' => $ident,
                    'caption' => (string)$meta[0],
                    'enabled' => true
                ];
            }
            return $defaults;
        }

        // Normalize: ensure all known idents exist; keep user state when present
        $byIdent = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $ident = (string)($r['ident'] ?? '');
            if ($ident === '') {
                continue;
            }
            $byIdent[$ident] = [
                'ident' => $ident,
                'caption' => (string)($r['caption'] ?? ($avail[$ident][0] ?? $ident)),
                'enabled' => (bool)($r['enabled'] ?? false)
            ];
        }

        foreach ($avail as $ident => $meta) {
            if (!isset($byIdent[$ident])) {
                $byIdent[$ident] = [
                    'ident' => $ident,
                    'caption' => (string)$meta[0],
                    'enabled' => true
                ];
            } else {
                // Always refresh caption to current definition
                $byIdent[$ident]['caption'] = (string)$meta[0];
            }
        }

        // Return stable order
        $out = [];
        foreach (array_keys($avail) as $ident) {
            $out[] = $byIdent[$ident];
        }
        return $out;
    }

    /**
     * Resolve enabled idents from selection rows.
     */
    private function GetEnabledVariableIdents(): array
    {
        $rows = $this->LoadVariableSelectionRows();
        $enabled = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            if (!empty($r['enabled'])) {
                $ident = (string)($r['ident'] ?? '');
                if ($ident !== '') {
                    $enabled[$ident] = true;
                }
            }
        }
        return array_keys($enabled);
    }

    /**
     * Ensure the variable selection attribute is initialized.
     */
    private function EnsureVariableSelectionInitialized(): void
    {
        $raw = trim($this->ReadAttributeString('variable_selection'));
        if ($raw !== '') {
            return;
        }

        $defaults = $this->LoadVariableSelectionRows();
        $json = json_encode($defaults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->SendDebug(__FUNCTION__, 'Initializing variable selection defaults (all enabled).', 0);

        // Persist defaults into attribute (no ApplyChanges recursion)
        $this->WriteAttributeString('variable_selection', (string)$json);
    }

    /**
     * Create/update the enum profile for port control.
     */
    private function EnsurePortControlProfile(): void
    {
        $profile = 'UCD.DockPortControl';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Automatisch', '', 0);
        IPS_SetVariableProfileAssociation($profile, 1, 'Manuell', '', 0);
        IPS_SetVariableProfileValues($profile, 0, 1, 1);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || !isset($data['Buffer']) || !is_string($data['Buffer'])) {
            $this->SendDebug('ReceiveData', 'Invalid payload: ' . $JSONString, 0);
            return '';
        }

        // Envelope for debugging
        $this->SendDebug('ReceiveData', 'Envelope: ' . $JSONString, 0);

        $rawBuffer = $data['Buffer'];
        $decoded = $this->DecodeBuffer($rawBuffer);

        if ($decoded === null) {
            // Show a shortened preview to avoid flooding the debug window
            $preview = (strlen($rawBuffer) > 500) ? (substr($rawBuffer, 0, 500) . '…') : $rawBuffer;
            $this->SendDebug('ReceiveData', 'Buffer could not be decoded. Preview: ' . $preview, 0);
            return '';
        }

        // If decoded data is JSON, pretty print; otherwise show as string
        if (is_array($decoded) || is_object($decoded)) {
            $this->SendDebug('ReceiveData', 'Payload: ' . json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0);
        } else {
            $this->SendDebug('ReceiveData', 'Payload: ' . (string)$decoded, 0);
        }

        // Handle Dock sysinfo payload forwarded by the Dock Manager
        if (is_array($decoded)
            && (($decoded['type'] ?? '') === 'dock')
            && (($decoded['msg'] ?? '') === 'get_sysinfo')
        ) {
            // Store raw payload for diagnostics
            $this->WriteAttributeString('sysinfo_raw', json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $this->WriteAttributeInteger('sys_last_code', (int)($decoded['code'] ?? 0));
            $this->WriteAttributeString('sys_last_msg', (string)($decoded['msg'] ?? ''));

            $this->WriteAttributeString('sys_name', (string)($decoded['name'] ?? ''));
            $this->WriteAttributeString('sys_hostname', (string)($decoded['hostname'] ?? ''));
            $this->WriteAttributeString('sys_model', (string)($decoded['model'] ?? ''));
            $this->WriteAttributeString('sys_version', (string)($decoded['version'] ?? ''));
            $this->WriteAttributeString('sys_serial', (string)($decoded['serial'] ?? ''));
            $this->WriteAttributeString('sys_revision', (string)($decoded['revision'] ?? ''));
            $this->WriteAttributeString('sys_uptime', (string)($decoded['uptime'] ?? ''));
            $this->WriteAttributeString('sys_reset_reason', (string)($decoded['reset_reason'] ?? ''));

            $this->WriteAttributeInteger('sys_features', (int)($decoded['features'] ?? 0));
            $this->WriteAttributeInteger('sys_led_brightness', (int)($decoded['led_brightness'] ?? 0));
            $this->WriteAttributeInteger('sys_eth_led_brightness', (int)($decoded['eth_led_brightness'] ?? 0));
            $this->WriteAttributeInteger('sys_volume', (int)($decoded['volume'] ?? 0));

            // free_heap comes as string in your example
            $freeHeap = $decoded['free_heap'] ?? 0;
            $this->WriteAttributeInteger('sys_free_heap', (int)$freeHeap);

            $this->WriteAttributeBoolean('sys_ethernet', (bool)($decoded['ethernet'] ?? false));
            $this->WriteAttributeBoolean('sys_wifi', (bool)($decoded['wifi'] ?? false));
            $this->WriteAttributeString('sys_ssid', (string)($decoded['ssid'] ?? ''));

            $portsJson = '';
            if (isset($decoded['ports'])) {
                $portsJson = json_encode($decoded['ports'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $this->WriteAttributeString('sys_ports_json', $portsJson);

            // Update variables
            $this->SendDebug(__FUNCTION__, 'Handling dock sysinfo payload…', 0);
            $this->UpdateVariablesFromAttributes(true);
            $this->SendDebug(__FUNCTION__, 'Sysinfo stored and variables updated.', 0);

            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
        }

        // Handle Dock port modes payload forwarded by the Dock Manager
        if (is_array($decoded)
            && (($decoded['type'] ?? '') === 'dock')
            && (($decoded['msg'] ?? '') === 'get_port_modes')
        ) {
            $this->WriteAttributeString('port_modes_raw', json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->WriteAttributeInteger('port_modes_last_code', (int)($decoded['code'] ?? 0));
            $this->WriteAttributeString('port_modes_last_msg', (string)($decoded['msg'] ?? ''));

            // Store ports JSON for diagnostics
            $portsJson = '';
            if (isset($decoded['ports'])) {
                $portsJson = json_encode($decoded['ports'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $this->WriteAttributeString('port_modes_ports_json', $portsJson);

            // Extract per-port fields (we currently support port 1 and 2)
            $ports = $decoded['ports'] ?? [];
            if (is_array($ports)) {
                foreach ($ports as $p) {
                    if (!is_array($p)) {
                        continue;
                    }
                    $port = (int)($p['port'] ?? 0);
                    $mode = (string)($p['mode'] ?? '');
                    $active = (string)($p['active_mode'] ?? '');
                    $supported = $p['supported_modes'] ?? [];
                    if (!is_array($supported)) {
                        $supported = [];
                    }
                    $supportedStr = implode(', ', array_map('strval', $supported));

                    if ($port === 1) {
                        $this->WriteAttributeString('port1_mode', $mode);
                        $this->WriteAttributeString('port1_active_mode', $active);
                        $this->WriteAttributeString('port1_supported_modes', $supportedStr);
                    } elseif ($port === 2) {
                        $this->WriteAttributeString('port2_mode', $mode);
                        $this->WriteAttributeString('port2_active_mode', $active);
                        $this->WriteAttributeString('port2_supported_modes', $supportedStr);
                    }
                }
            }

            // Update variables
            $this->SendDebug(__FUNCTION__, 'Handling dock port modes payload…', 0);
            $this->UpdateVariablesFromAttributes(true);
            $this->SendDebug(__FUNCTION__, 'Port modes stored and variables updated.', 0);

            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
        }

        return '';
    }

    /**
     * Decode Symcon WebSocket payload buffers.
     * The WebSocket Client often delivers Buffer content as HEX string.
     * Returns decoded JSON array if possible, otherwise a decoded string.
     */
    private function DecodeBuffer(string $buffer)
    {
        $payload = $buffer;

        // Try: HEX encoded payload (common)
        $isHex = (strlen($payload) % 2 === 0) && (strlen($payload) > 0) && ctype_xdigit($payload);
        if ($isHex) {
            $bin = @hex2bin($payload);
            if ($bin !== false) {
                $payload = $bin;
            }
        }

        // Try: JSON
        $json = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        // Try: UTF-8 string (do not use utf8_decode; it can corrupt already valid UTF-8)
        if (is_string($payload)) {
            // Ensure it's valid UTF-8; if not, best-effort convert
            if (!mb_check_encoding($payload, 'UTF-8')) {
                $payload = @mb_convert_encoding($payload, 'UTF-8');
            }
            return $payload;
        }

        return null;
    }

    /**
     * IPS_SetVariableCustomPresentation expects a JSON string as 2nd parameter.
     * Passing an array triggers "Cannot auto-convert value for parameter Presentation".
     */
    private function SetCustomPresentation(int $variableId, array $presentation): void
    {
        IPS_SetVariableCustomPresentation(
            $variableId,
            json_encode($presentation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function ApplyPresentationsForEnabled(array $enabledIdents): void
    {
        // nur für Variablen, die existieren (enabled) und wo UI relevant ist
        foreach (array_keys($enabledIdents) as $ident) {
            $this->ApplyPresentationForIdent($ident);
        }
    }

    private function ApplyPresentationForIdent(string $ident): void
    {
        // Variable muss existieren
        try {
            $varId = $this->GetIDForIdent($ident);
        } catch (Throwable $e) {
            return;
        }
        if ($varId <= 0) {
            return;
        }

        // Für viele Read-Only Strings brauchst du nichts setzen.
        // Wir verbessern hier gezielt die "unschönen" Controls aus deinem WebFront Screenshot.
        switch ($ident) {
            case 'LEDBrightness':
            case 'EthernetLEDBrightness':
                // Slider Parameter: siehe Symcon Objekt-Darstellung "Schieberegler"  [oai_citation:3‡symcon.de](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen)
                $this->SetCustomPresentation($varId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'MIN' => 0,
                    'MAX' => 100,
                    'STEP' => 1
                ]);
                break;

            case 'Volume':
                $this->SetCustomPresentation($varId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'MIN' => 0,
                    'MAX' => 100,
                    'STEP' => 1
                ]);
                break;

            case 'Ethernet':
            case 'WiFi':
                $this->SetCustomPresentation($varId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                ]);
                break;

            case 'Port1Control':
            case 'Port2Control':
                // Aufzählung (= Enumeration) mit Optionen, erfordert EnableAction  [oai_citation:4‡symcon.de](https://www.symcon.de/de/service/dokumentation/komponenten/objekt-darstellung/aufzaehlung)
                $this->SetCustomPresentation($varId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS' => [
                        [
                            'VALUE' => 0,
                            'CAPTION' => 'Automatisch'
                        ],
                        [
                            'VALUE' => 1,
                            'CAPTION' => 'Manuell'
                        ]
                    ],
                    // optional: wie dargestellt wird (Row/Column/Grid) – je nach Symcon-Konstante
                    // 'ARRANGEMENT' => VARIABLE_PRESENTATION_ENUMERATION_ARRANGEMENT_ROW,
                ]);
                break;

            default:
                // nichts tun
                break;
        }
    }

    /**
     * Persist a single row edit from the configuration form list.
     * Symcon Lists do not support onChange, only onEdit.
     * The onEdit handler passes scalar values only (ident + enabled).
     */
    public function UpdateVariableSelection(string $ident, bool $enabled): void
    {
        $ident = trim($ident);
        if ($ident === '') {
            $this->SendDebug(__FUNCTION__, 'Empty ident received, ignoring.', 0);
            return;
        }

        $avail = $this->GetAvailableVariables();
        if (!isset($avail[$ident])) {
            $this->SendDebug(__FUNCTION__, 'Unknown ident received: ' . $ident, 0);
            return;
        }

        // Load current selection rows from attribute (or defaults)
        $rows = $this->LoadVariableSelectionRows();

        // Update only the edited row
        $updated = false;
        foreach ($rows as &$r) {
            if (!is_array($r)) {
                continue;
            }
            if ((string)($r['ident'] ?? '') === $ident) {
                $r['enabled'] = $enabled;
                // refresh caption from current definition
                $r['caption'] = (string)$avail[$ident][0];
                $updated = true;
                break;
            }
        }
        unset($r);

        // If row not found (shouldn't happen), append it
        if (!$updated) {
            $rows[] = [
                'ident' => $ident,
                'caption' => (string)$avail[$ident][0],
                'enabled' => $enabled
            ];
        }

        // Normalize: ensure all known idents exist (default enabled)
        $byIdent = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $rid = (string)($r['ident'] ?? '');
            if ($rid === '' || !isset($avail[$rid])) {
                continue;
            }
            $byIdent[$rid] = [
                'ident' => $rid,
                'caption' => (string)$avail[$rid][0],
                'enabled' => (bool)($r['enabled'] ?? false)
            ];
        }

        $out = [];
        foreach (array_keys($avail) as $aid) {
            if (isset($byIdent[$aid])) {
                $out[] = $byIdent[$aid];
            } else {
                $out[] = [
                    'ident' => $aid,
                    'caption' => (string)$avail[$aid][0],
                    'enabled' => true
                ];
            }
        }

        $json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->SendDebug(__FUNCTION__, 'Storing variable selection (attribute): ' . $json, 0);
        $this->WriteAttributeString('variable_selection', (string)$json);

        // Apply selection immediately: create/remove variables now
        $enabledIdents = array_flip($this->GetEnabledVariableIdents());
        foreach ($avail as $vident => $meta) {
            [$caption, $type, $profile, $pos] = $meta;
            $keep = isset($enabledIdents[$vident]);
            $this->MaintainVariable($vident, (string)$caption, (int)$type, (string)$profile, (int)$pos, $keep);
        }

        // Ensure actions are enabled for writable variables that were just created
        $this->EnsureWritableActions($enabledIdents);
        $this->ApplyPresentationsForEnabled($enabledIdents);

        // Remove legacy variables from older versions (no longer used)
        foreach (['Port1Mode', 'Port1ActiveMode', 'Port2Mode', 'Port2ActiveMode'] as $legacyIdent) {
            $this->MaintainVariable($legacyIdent, $legacyIdent, VARIABLETYPE_STRING, '', 0, false);
        }

        // Populate variables with last known values
        $this->UpdateVariablesFromAttributes(false);
        // Do not call ReloadForm() here to preserve panel expansion state.
    }

    /**
     * Handle actions via IPS_RequestAction.
     */
    public function RequestAction(string $Ident, $Value): void
    {
        switch ($Ident) {

            case 'LEDBrightness':
                $this->SetDockLedBrightness((int)$Value);
                break;

            case 'EthernetLEDBrightness':
                $this->SetDockEthernetLedBrightness((int)$Value);
                break;

            case 'Volume':
                $this->SetDockVolume((int)$Value);
                break;

            case 'Port1Control':
                $this->SetDockPortMode(1, (int)$Value === 0 ? 'AUTO' : 'NONE');
                break;

            case 'Port2Control':
                $this->SetDockPortMode(2, (int)$Value === 0 ? 'AUTO' : 'NONE');
                break;

            default:
                parent::RequestAction($Ident, $Value);
        }
    }

    private function SetDockLedBrightness(int $value): void
    {
        $payload = [
            'action' => 'set_led_brightness',
            'value' => $value
        ];
        $this->SendDockCommand($payload);
    }

    private function SetDockEthernetLedBrightness(int $value): void
    {
        $payload = [
            'action' => 'set_eth_led_brightness',
            'value' => $value
        ];
        $this->SendDockCommand($payload);
    }

    private function SetDockVolume(int $value): void
    {
        $payload = [
            'action' => 'set_volume',
            'value' => $value
        ];
        $this->SendDockCommand($payload);
    }

    private function SetDockPortMode(int $port, string $mode): void
    {
        $payload = [
            'action' => 'set_port_mode',
            'port' => $port,
            'mode' => $mode
        ];
        $this->SendDockCommand($payload);
    }

    private function SendDockCommand(array $payload): void
    {
        $data = [
            'DataID' => '{F975667E-3B5A-0148-4A47-CB4CD513EAD8}',
            'Buffer' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ];

        $this->SendDebug(__FUNCTION__, '➡️ Sending dock command: ' . $data['Buffer'], 0);
        $this->SendDataToParent(json_encode($data));
    }


    /**
     * build configuration form
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        // return current form
        return json_encode(
            [
                'elements' => $this->FormHead(),
                'actions' => $this->FormActions(),
                'status' => $this->FormStatus()]
        );
    }

    /**
     * return form configurations on configuration step
     *
     * @return array
     */
    protected function FormHead(): array
    {
        return [
            [
                'type' => 'ExpansionPanel',
                'caption' => 'Instance variables',
                'expanded' => true,
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'Select which variables should exist in this instance. Unchecked items will be removed from the instance.'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'variable_selection',
                        'caption' => 'Variables',
                        'rowCount' => 12,
                        'add' => false,
                        'delete' => false,
                        'onEdit' => 'UCD_UpdateVariableSelection($id, $variable_selection["ident"], $variable_selection["enabled"]);',
                        'sort' => [
                            'column' => 'caption',
                            'direction' => 'ascending'
                        ],
                        'columns' => [
                            ['caption' => 'Enabled', 'name' => 'enabled', 'width' => '80px', 'edit' => ['type' => 'CheckBox']],
                            ['caption' => 'Ident', 'name' => 'ident', 'width' => '160px'],
                            ['caption' => 'Caption', 'name' => 'caption', 'width' => 'auto']
                        ],
                        'values' => $this->LoadVariableSelectionRows()
                    ]
                ]
            ],
            [
                'type' => 'Label',
                'caption' => 'System information (read-only)'
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_name',
                'caption' => 'Name',
                'value' => $this->ReadAttributeString('sys_name'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_hostname',
                'caption' => 'Hostname',
                'value' => $this->ReadAttributeString('sys_hostname'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_model',
                'caption' => 'Model',
                'value' => $this->ReadAttributeString('sys_model'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_version',
                'caption' => 'Firmware',
                'value' => $this->ReadAttributeString('sys_version'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_serial',
                'caption' => 'Serial',
                'value' => $this->ReadAttributeString('sys_serial'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_revision',
                'caption' => 'Hardware revision',
                'value' => $this->ReadAttributeString('sys_revision'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_uptime',
                'caption' => 'Uptime',
                'value' => $this->ReadAttributeString('sys_uptime'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_reset_reason',
                'caption' => 'Reset reason',
                'value' => $this->ReadAttributeString('sys_reset_reason'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_features',
                'caption' => 'Features',
                'value' => (string)$this->ReadAttributeInteger('sys_features'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_led_brightness',
                'caption' => 'LED brightness',
                'value' => (string)$this->ReadAttributeInteger('sys_led_brightness'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_eth_led_brightness',
                'caption' => 'Ethernet LED brightness',
                'value' => (string)$this->ReadAttributeInteger('sys_eth_led_brightness'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_volume',
                'caption' => 'Volume',
                'value' => (string)$this->ReadAttributeInteger('sys_volume'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_free_heap',
                'caption' => 'Free heap',
                'value' => (string)$this->ReadAttributeInteger('sys_free_heap'),
                'enabled' => false
            ],
            [
                'type' => 'CheckBox',
                'name' => 'sys_ethernet',
                'caption' => 'Ethernet',
                'value' => $this->ReadAttributeBoolean('sys_ethernet'),
                'enabled' => false
            ],
            [
                'type' => 'CheckBox',
                'name' => 'sys_wifi',
                'caption' => 'WiFi',
                'value' => $this->ReadAttributeBoolean('sys_wifi'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_ssid',
                'caption' => 'SSID',
                'value' => $this->ReadAttributeString('sys_ssid'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sys_ports_json',
                'caption' => 'Ports (JSON)',
                'value' => $this->ReadAttributeString('sys_ports_json'),
                'enabled' => false
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'sysinfo_raw',
                'caption' => 'Last sysinfo (raw JSON)',
                'value' => $this->ReadAttributeString('sysinfo_raw'),
                'enabled' => false
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => 'Dock Ports (get_port_modes)',
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'Port modes require authentication on the dock. Use the button below to refresh.'
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port1_control_display',
                        'caption' => 'Port 1 mode',
                        'value' => ($this->ReadAttributeString('port1_mode') === 'AUTO') ? 'Automatisch' : 'Manuell',
                        'enabled' => false
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port1_supported_modes',
                        'caption' => 'Port 1 supported modes',
                        'value' => $this->ReadAttributeString('port1_supported_modes'),
                        'enabled' => false
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port2_control_display',
                        'caption' => 'Port 2 mode',
                        'value' => ($this->ReadAttributeString('port2_mode') === 'AUTO') ? 'Automatisch' : 'Manuell',
                        'enabled' => false
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port2_supported_modes',
                        'caption' => 'Port 2 supported modes',
                        'value' => $this->ReadAttributeString('port2_supported_modes'),
                        'enabled' => false
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port_modes_raw',
                        'caption' => 'Last get_port_modes (raw JSON)',
                        'value' => $this->ReadAttributeString('port_modes_raw'),
                        'enabled' => false,
                        'multiline' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * return form actions by token
     *
     * @return array
     */
    protected function FormActions(): array
    {
        return [
            [
                'type' => 'Button',
                'caption' => 'Request dock sysinfo',
                'onClick' => 'UCD_RequestSysInfo($id);'
            ],
            [
                'type' => 'Button',
                'caption' => 'Request port modes',
                'onClick' => 'UCD_RequestPortModes($id);'
            ]
        ];
    }

    /**
     * return from status
     *
     * @return array
     */
    protected function FormStatus(): array
    {
        $form = [
            [
                'code' => IS_CREATING,
                'icon' => 'inactive',
                'caption' => 'Creating instance.'],
            [
                'code' => IS_ACTIVE,
                'icon' => 'active',
                'caption' => 'Remote 3 Dock created.'],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => 'Interface closed.']];

        return $form;
    }

}
