<?php

declare(strict_types=1);

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

        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyString('hostname', '');
        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyString('dock_id', '');
        $this->RegisterPropertyString('model', '');
        $this->RegisterPropertyString('version', '');
        $this->RegisterPropertyString('rev', '');
        $this->RegisterPropertyString('ws_path', '');

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

        // Variables (visible state)
        $this->MaintainVariable('Name', 'Name', VARIABLETYPE_STRING, '', 10, true);
        $this->MaintainVariable('Hostname', 'Hostname', VARIABLETYPE_STRING, '', 20, true);
        $this->MaintainVariable('Model', 'Model', VARIABLETYPE_STRING, '', 30, true);
        $this->MaintainVariable('Firmware', 'Firmware', VARIABLETYPE_STRING, '', 40, true);
        $this->MaintainVariable('Serial', 'Serial', VARIABLETYPE_STRING, '', 50, true);
        $this->MaintainVariable('HardwareRevision', 'Hardware revision', VARIABLETYPE_STRING, '', 60, true);
        $this->MaintainVariable('Uptime', 'Uptime', VARIABLETYPE_STRING, '', 70, true);
        $this->MaintainVariable('Ethernet', 'Ethernet', VARIABLETYPE_BOOLEAN, '~Switch', 80, true);
        $this->MaintainVariable('WiFi', 'WiFi', VARIABLETYPE_BOOLEAN, '~Switch', 90, true);
        $this->MaintainVariable('SSID', 'SSID', VARIABLETYPE_STRING, '', 100, true);
        $this->MaintainVariable('LEDBrightness', 'LED brightness', VARIABLETYPE_INTEGER, '', 110, true);
        $this->MaintainVariable('EthernetLEDBrightness', 'Ethernet LED brightness', VARIABLETYPE_INTEGER, '', 120, true);
        $this->MaintainVariable('Volume', 'Volume', VARIABLETYPE_INTEGER, '', 130, true);
        $this->MaintainVariable('FreeHeap', 'Free heap', VARIABLETYPE_INTEGER, '', 140, true);
        $this->MaintainVariable('ResetReason', 'Reset reason', VARIABLETYPE_STRING, '', 150, true);
        $this->MaintainVariable('Features', 'Features', VARIABLETYPE_INTEGER, '', 160, true);
        $this->MaintainVariable('Ports', 'Ports (JSON)', VARIABLETYPE_STRING, '', 170, true);
        $this->MaintainVariable('LastResultCode', 'Last result code', VARIABLETYPE_INTEGER, '', 180, true);
        $this->MaintainVariable('LastResultMessage', 'Last result message', VARIABLETYPE_STRING, '', 190, true);

        // Populate variables with last known values (e.g. after restart / form reload)
        $this->UpdateVariablesFromAttributes(false);
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
            'LastResultMessage' => $this->ReadAttributeString('sys_last_msg')
        ];

        foreach ($map as $ident => $value) {
            if ($debug) {
                $this->SendDebug(__FUNCTION__, "Updating {$ident}", 0);
            }
            $this->SetVarIfExists($ident, $value);
        }
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