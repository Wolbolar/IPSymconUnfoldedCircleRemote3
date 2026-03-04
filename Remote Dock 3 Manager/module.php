<?php

declare(strict_types=1);

class Remote3DockManager extends IPSModuleStrict
{
    // Dock WebSocket API (UCD2/UCD3) defaults
    const DEFAULT_WS_PROTOCOL = 'ws://';
    const DEFAULT_WS_PORT = 80; // Dock 3
    const DOCK2_WS_PORT = 946; // 946
    const MODEL_DOCK3 = 'UCD3';
    const MODEL_DOCK2 = 'UCD2';

    // WebSocket path differs by dock model:
    // - Dock 3 (UCD3): /ws
    // - Dock 2 (UCD2): root path (empty string)
    const DEFAULT_WS_PATH = '/ws'; // Dock 3
    const DOCK2_WS_PATH = '';

    // DataID for forwarding Dock data to child instances.
    // Must match Dock Manager `childRequirements` and Dock Child `implemented`.
    const DOCK_CHILD_DATAID = '{B65C3047-2C25-5859-A9D6-7408B791CDCD}';

    public function GetCompatibleParents(): string
    {
        // Require the WebSocket Client as parent
        return json_encode([
            'type' => 'require',
            'moduleIDs' => [
                '{D68FD31F-0E90-7019-F16C-1949BD3079EF}'
            ]
        ]);
    }

    private function EnsureApiKey(): bool
    {
        // Dock-API does not expose the same REST API as remote-core for generating API keys.
        // The Dock WebSocket API authenticates with an access token sent via an `auth` message.
        // We store that token in `api_key` attribute for reuse.

        // 1) Manual override from property (user editable in form) has priority.
        //    Keep attribute in sync so all send/auth logic uses the latest value

        $manualPin = trim($this->ReadPropertyString('pin'));
        $legacyManual = trim($this->ReadPropertyString('api_key_display')); // backward compatibility
        $manualApiKey = $manualPin !== '' ? $manualPin : $legacyManual;

        $apiKey = $this->ReadAttributeString('api_key');

        if ($manualApiKey !== '') {
            if ($manualApiKey !== $apiKey) {
                $this->WriteAttributeString('api_key', $manualApiKey);
                $this->SendDebug(__FUNCTION__, '🔁 Sync PIN key: property -> attribute (EnsureApiKey).', 0);
            }
            return true;
        }

        // 2) If we already have a stored attribute token, use it
        if ($apiKey !== '') {
            return true;
        }

        // 3) Try pulling from selected Remote 3 Core instance
        $coreInstanceId = (int)$this->ReadPropertyInteger('core_instance_id');
        if ($coreInstanceId > 0) {
            $this->SendDebug(__FUNCTION__, '🔑 Trying to fetch API key from selected Remote 3 Core instance #' . $coreInstanceId . '…', 0);
            $this->UpdateApiKeyFromCoreById($coreInstanceId);
            $apiKey = $this->ReadAttributeString('api_key');
            if ($apiKey !== '') {
                return true;
            }
            $this->SendDebug(__FUNCTION__, '⚠️ Could not fetch API key from Remote 3 Core.', 0);
        }

        $this->SendDebug(__FUNCTION__, '⏸️ API key not available yet (manual empty and core not selected/returned empty).', 0);
        return false;
    }

    /**
     * Fetch API key from the selected Remote 3 Core instance (property `core_instance_id`)
     * and store it in this instance.
     * The selected instance must provide the public function UCR_GetApiKey(int $id): string.
     */
    public function UpdateApiKeyFromCore(): void
    {
        $coreInstanceId = (int)$this->ReadPropertyInteger('core_instance_id');
        $this->SendDebug(__FUNCTION__, '🔑 Fetch API key requested. Selected core_instance_id=' . $coreInstanceId, 0);
        $this->UpdateApiKeyFromCoreById($coreInstanceId);
    }

    /**
     * Internal helper to fetch and store the API key from a given Remote 3 Core instance id.
     */
    public function UpdateApiKeyFromCoreById(int $coreInstanceId): void
    {
        if ($coreInstanceId <= 0 || !@IPS_InstanceExists($coreInstanceId)) {
            $this->SendDebug(__FUNCTION__, '⏸️ No valid Remote 3 Core instance selected.', 0);
            return;
        }

        // Verify function exists in the system (provided by the Remote 3 Core module)
        if (!function_exists('UCR_GetApiKey')) {
            $this->SendDebug(__FUNCTION__, '❌ Function UCR_GetApiKey() not found. Please ensure the Remote 3 Core module is installed/loaded.', 0);
            return;
        }

        try {
            $apiKey = (string)@UCR_GetApiKey($coreInstanceId);
        } catch (Throwable $e) {
            $this->SendDebug(__FUNCTION__, '❌ Error calling UCR_GetApiKey: ' . $e->getMessage(), 0);
            return;
        }

        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            $this->SendDebug(__FUNCTION__, '⚠️ Remote 3 Core returned an empty API key.', 0);
            return;
        }

        $this->WriteAttributeString('api_key', $apiKey);
        $this->SendDebug(__FUNCTION__, '✅ API key updated from Remote 3 Core instance #' . $coreInstanceId, 0);

        if (method_exists($this, 'ReloadForm')) {
            $this->ReloadForm();
        }

        // Optionally trigger parent WS client reconfiguration
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0;
        if (is_int($parentID) && $parentID > 0) {
            $this->SendDebug(__FUNCTION__, '🔄 Triggering parent ApplyChanges (after API key update)…', 0);
            @IPS_ApplyChanges($parentID);
        }
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterAttributeString('api_key', '');
        $this->RegisterAttributeString('auth_mode', '');
        $this->RegisterPropertyString('hostname', '');
        $this->RegisterPropertyInteger('core_instance_id', 0);
        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyString('model', 'UCD3');
        $this->RegisterPropertyString('port', '');
        $this->RegisterPropertyString('https_port', '');
        $this->RegisterPropertyString('ws_path', self::DEFAULT_WS_PATH);
        $this->RegisterPropertyString('ws_port', (string)self::DEFAULT_WS_PORT);
        $this->RegisterPropertyString('ws_host', '');
        $this->RegisterPropertyString('ws_https_port', '');
        $this->RegisterPropertyString('ws_https_host', '');
        $this->RegisterAttributeString('ws_auth_mode', '');
        $this->RegisterAttributeString('ws_api_key', '');
        $this->RegisterAttributeString('sysinfo_raw', '');
        $this->RegisterAttributeString('sysinfo_last_req_id', '');
        $this->RegisterAttributeString('port_modes_raw', '');
        $this->RegisterAttributeInteger('port_modes_last_req_id', 0);
        $this->RegisterAttributeInteger('dock_msg_id', 0);
        $this->RegisterAttributeString('DownloadSecret', '');
        $this->RegisterPropertyString('api_key_display', '');
        $this->RegisterPropertyString('pin', '');

        //We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        // Ensure a per-instance secret exists for export downloads
        if ($this->ReadAttributeString('DownloadSecret') === '') {
            try {
                $this->WriteAttributeString('DownloadSecret', bin2hex(random_bytes(16)));
            } catch (Throwable $e) {
                // Fallback if random_bytes is not available
                $this->WriteAttributeString('DownloadSecret', md5((string)microtime(true) . ':' . (string)$this->InstanceID));
            }
        }
    }

    public function Destroy(): void
    {
        // Debug-Information zur Überprüfung, dass Destroy aufgerufen wird
        $this->SendDebug('Destroy', 'Destroy-Methode wird aufgerufen', 0);

        // Webhook löschen, falls dieser existiert
        $this->UnregisterHook('unfoldedcircle_dock3/' . $this->InstanceID . '/download');

        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $host = $this->ReadPropertyString('host');

        // Normalize WS settings depending on Dock model (keeps UI consistent and avoids confusion).
        $model = trim($this->ReadPropertyString('model'));
        if ($model === '') {
            $model = self::MODEL_DOCK3;
        }

        // For Dock 2: ws://IP:946 (no path)
        // For Dock 3: ws://IP/ws (default port 80 can be omitted)
        $expectedWsPort = ($model === self::MODEL_DOCK2) ? (string)self::DOCK2_WS_PORT : (string)self::DEFAULT_WS_PORT;
        $expectedWsPath = ($model === self::MODEL_DOCK2) ? self::DOCK2_WS_PATH : self::DEFAULT_WS_PATH;

        $currentWsPort = (string)$this->ReadPropertyString('ws_port');
        $currentWsPath = (string)$this->ReadPropertyString('ws_path');

        //Only call this in READY state. On startup the WebHook instance might not be available yet
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterHook('unfoldedcircle_dock3/' . $this->InstanceID . '/download');
        }

        // Only write properties when they differ to avoid ApplyChanges loops.
        $needSync = false;
        if (trim($currentWsPort) !== $expectedWsPort) {
            $needSync = true;
        }
        if ((string)$currentWsPath !== (string)$expectedWsPath) {
            $needSync = true;
        }

        if ($needSync) {
            $this->SendDebug(__FUNCTION__, '🔧 Sync WS defaults for model=' . $model . ' (ws_port=' . $expectedWsPort . ', ws_path=' . json_encode($expectedWsPath) . ')', 0);
            @IPS_SetProperty($this->InstanceID, 'ws_port', $expectedWsPort);
            @IPS_SetProperty($this->InstanceID, 'ws_path', $expectedWsPath);

            // Re-run ApplyChanges once with the corrected properties.
            @IPS_ApplyChanges($this->InstanceID);
            return;
        }

        // If setup is incomplete, keep module inactive and do not touch parent configuration.
        if ($host === '') {
            $this->SendDebug(__FUNCTION__, '⏸️ Setup incomplete (Host missing) – waiting for user input.', 0);
            $this->SetStatus(IS_INACTIVE);
            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
            return;
        }

        // Debug: log effective WS URL that will be used for the parent (model-aware)
        $cfg = json_decode($this->GetConfigurationForParent(), true);
        if (is_array($cfg) && isset($cfg['URL'])) {
            $this->SendDebug(__FUNCTION__, '🧭 Effective WS URL (model=' . $model . '): ' . (string)$cfg['URL'], 0);
        }

        // Sync: if user changed the API key property, mirror it into the attribute used for sending/auth.
        $propApiKey = trim($this->ReadPropertyString('api_key_display'));
        $attrApiKey = $this->ReadAttributeString('api_key');

        if ($propApiKey !== '' && $propApiKey !== $attrApiKey) {
            $this->SendDebug(__FUNCTION__, '🔁 Sync API key: property -> attribute', 0);
            $this->WriteAttributeString('api_key', $propApiKey);
        }

        // Setup seems complete – ensure Dock access token exists.
        $this->SendDebug(__FUNCTION__, '🔐 Setup complete – ensuring Dock access token…', 0);
        $ok = $this->EnsureApiKey();

        // Update the API key display in the form
        if (method_exists($this, 'ReloadForm')) {
            $this->ReloadForm();
        }

        if (!$ok) {
            $this->SendDebug(__FUNCTION__, '⏸️ Token not available yet – parent WS config will remain dummy.', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        // We have an API key; mark active and trigger parent to fetch fresh configuration.
        $this->SetStatus(IS_ACTIVE);

        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0;
        if (is_int($parentID) && $parentID > 0) {
            $this->SendDebug(__FUNCTION__, '🔄 Triggering parent ApplyChanges to update WS configuration (API-Key/Headers)…', 0);
            @IPS_ApplyChanges($parentID);
        } else {
            $this->SendDebug(__FUNCTION__, '⚠️ No parent connected yet – cannot trigger WS client reconfiguration.', 0);
        }
    }

    public function GetConfigurationForParent(): string
    {
        $host = trim($this->ReadPropertyString('host'));
        $token = trim($this->ReadAttributeString('api_key'));

        // Determine Dock model (set by discovery). Fallback to Dock 3.
        $model = trim($this->ReadPropertyString('model'));
        if ($model === '') {
            $model = self::MODEL_DOCK3;
        }

        // Build WS URL depending on dock model.
        // Forum confirmed:
        // - Dock 2: ws://IP:946
        // - Dock 3: ws://IP/ws
        $urlHost = ($host !== '') ? $host : '127.0.0.1';

        $protocol = self::DEFAULT_WS_PROTOCOL;
        $url = '';

        if ($model === self::MODEL_DOCK2) {
            // Dock 2: explicit port, no /ws path
            $url = $protocol . $urlHost . ':' . self::DOCK2_WS_PORT . self::DOCK2_WS_PATH;
        } else {
            // Dock 3: /ws path, default port (80) can be omitted in URL
            $path = self::DEFAULT_WS_PATH;
            if ($path === '') {
                $path = '/ws';
            }

            // If a custom ws_port is configured and not default, include it.
            $customPort = (int)trim($this->ReadPropertyString('ws_port'));
            if ($customPort > 0 && $customPort !== self::DEFAULT_WS_PORT) {
                $url = $protocol . $urlHost . ':' . $customPort . $path;
            } else {
                $url = $protocol . $urlHost . $path;
            }
        }

        // If setup is incomplete (host and/or token missing), still configure the WS client URL
        // with the best-known host so the parent does not show a misleading 127.0.0.1.
        // Authentication may happen later once the token becomes available.
        if ($host === '' || $token === '') {
            $this->SendDebug(__FUNCTION__, '⏸️ WS configuration incomplete (Host/Token missing) – using model-based URL without headers.', 0);

            $config = [
                'URL' => $url,
                'VerifyCertificate' => false,
                'Type' => 0,
                'Headers' => json_encode([])
            ];

            return json_encode($config);
        }

        // Ensure we have a valid API key once host+token are present.
        $apiKey = $this->ReadAttributeString('api_key');
        if ($apiKey === '') {
            $this->SendDebug(__FUNCTION__, '🔐 No API key yet – trying to create/validate API key…', 0);
            if (!$this->EnsureApiKey()) {
                $this->SendDebug(__FUNCTION__, '⏸️ WS configuration postponed (API key not available).', 0);

                $config = [
                    'URL' => $url,
                    'VerifyCertificate' => false,
                    'Type' => 0,
                    'Headers' => json_encode([])
                ];

                return json_encode($config);
            }
            $apiKey = $this->ReadAttributeString('api_key');
        }

        // Dock authenticates with an `auth` message, not via HTTP headers.
        $config = [
            'URL' => $url,
            'VerifyCertificate' => false,
            'Type' => 0,
            'Headers' => json_encode([])
        ];

        $this->SendDebug(__FUNCTION__, '🧩 WS Configuration (model=' . $model . '): ' . json_encode($config), 0);
        return json_encode($config);
    }

    public function UpdateWSClient(): void
    {
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0;
        if (!is_int($parentID) || $parentID <= 0) {
            $this->SendDebug(__FUNCTION__, '❌ No parent WebSocket Client connected (ConnectionID is empty).', 0);
            return;
        }

        // Build the config exactly like the parent would request it.
        $configJson = $this->GetConfigurationForParent();
        $cfg = json_decode($configJson, true);
        if (!is_array($cfg)) {
            $this->SendDebug(__FUNCTION__, '❌ Invalid parent configuration JSON: ' . $configJson, 0);
            return;
        }

        // WebSocket Client (Symcon) uses these configuration keys:
        // {"Active":false,"Headers":"[]","Type":0,"URL":"ws://<ip>:<port>/<path>","VerifyCertificate":false}
        $this->SendDebug(__FUNCTION__, '🔧 Applying WS client configuration to parent…', 0);
        $this->SendDebug(__FUNCTION__, 'ParentID: ' . $parentID, 0);
        $this->SendDebug(__FUNCTION__, 'Config: ' . json_encode($cfg), 0);

        // Apply required properties
        if (array_key_exists('URL', $cfg)) {
            $this->SendDebug(__FUNCTION__, '➡️ IPS_SetProperty(URL): ' . (string)$cfg['URL'], 0);
            @IPS_SetProperty($parentID, 'URL', (string)$cfg['URL']);
        }

        if (array_key_exists('VerifyCertificate', $cfg)) {
            $this->SendDebug(__FUNCTION__, '➡️ IPS_SetProperty(VerifyCertificate): ' . json_encode((bool)$cfg['VerifyCertificate']), 0);
            @IPS_SetProperty($parentID, 'VerifyCertificate', (bool)$cfg['VerifyCertificate']);
        }

        if (array_key_exists('Type', $cfg)) {
            $this->SendDebug(__FUNCTION__, '➡️ IPS_SetProperty(Type): ' . json_encode((int)$cfg['Type']), 0);
            @IPS_SetProperty($parentID, 'Type', (int)$cfg['Type']);
        }

        if (array_key_exists('Headers', $cfg)) {
            // Headers must be a JSON string (e.g. "[]")
            $headers = $cfg['Headers'];
            if (is_array($headers)) {
                $headers = json_encode($headers);
            }
            $headers = (string)$headers;
            $this->SendDebug(__FUNCTION__, '➡️ IPS_SetProperty(Headers): ' . $headers, 0);
            @IPS_SetProperty($parentID, 'Headers', $headers);
        }

        // Enable the WS client (Symcon uses property name "Active")
        $this->SendDebug(__FUNCTION__, '➡️ IPS_SetProperty(Active): true', 0);
        @IPS_SetProperty($parentID, 'Active', true);

        $this->SendDebug(__FUNCTION__, '🔄 Calling IPS_ApplyChanges on parent…', 0);
        @IPS_ApplyChanges($parentID);

        // Log the resulting parent configuration for troubleshooting
        $finalCfg = @IPS_GetConfiguration($parentID);
        if (is_string($finalCfg) && $finalCfg !== '') {
            $this->SendDebug(__FUNCTION__, '✅ Parent configuration after ApplyChanges: ' . $finalCfg, 0);
        }
    }

    // --- Dock WebSocket API helpers -------------------------------------------------

    private function NextDockMsgId(): int
    {
        $id = (int)$this->ReadAttributeInteger('dock_msg_id');
        $id++;
        // Keep it in a sane range
        if ($id < 0 || $id > 2147483000) {
            $id = 1;
        }
        $this->WriteAttributeInteger('dock_msg_id', $id);
        return $id;
    }

    /**
     * Send a Dock API message that uses the `command` field (most requests).
     *
     * Payload format per AsyncAPI examples:
     *   {"type":"dock","id":<int>,"command":"...", ...}
     */
    private function SendDockCommand(string $command, array $fields = []): void
    {
        $payload = array_merge(
            [
                'type' => 'dock',
                'id' => $this->NextDockMsgId(),
                'command' => $command
            ],
            $fields
        );

        $this->SendDebug(__FUNCTION__, '➡️ Sending dock command: ' . json_encode($payload, JSON_UNESCAPED_SLASHES), 0);
        $this->SendToWebSocket($payload);
    }

    /**
     * Send a Dock API message that uses the `msg` field (e.g. ping).
     *
     * Payload format per AsyncAPI examples:
     *   {"type":"dock","msg":"ping"}
     */
    private function SendDockMsg(string $msg, array $fields = []): void
    {
        $payload = array_merge(
            [
                'type' => 'dock',
                'msg' => $msg
            ],
            $fields
        );

        $this->SendDebug(__FUNCTION__, '➡️ Sending dock msg: ' . json_encode($payload, JSON_UNESCAPED_SLASHES), 0);
        $this->SendToWebSocket($payload);
    }

    /**
     * Dock authentication message (per Dock AsyncAPI spec):
     *   {"type":"auth","token":"..."}
     */
    private function SendAuth(string $token): void
    {
        $token = trim($token);
        $len = strlen($token);

        // Per Dock AsyncAPI spec: token length 4..40 characters
        if ($len === 0) {
            $this->SendDebug(__FUNCTION__, '❌ Empty token – cannot authenticate.', 0);
            return;
        }
        if ($len < 4 || $len > 40) {
            $this->SendDebug(
                __FUNCTION__,
                '❌ Token length invalid (' . $len . '). The Dock API expects an API access token with 4..40 characters. ' .
                'This is NOT the same as the Remote 3 REST API key. Please enter the Dock API access token.',
                0
            );
            return;
        }

        // Do not log the token in plain text
        $this->SendDebug(__FUNCTION__, '🔐 Sending auth message: {"type":"auth","token":"***"} (len=' . $len . ')', 0);
        $this->SendToWebSocket([
            'type' => 'auth',
            'token' => $token
        ]);
    }

    // --- Dock WebSocket API: documented requests -----------------------------------

    /** Ping the dock (no authentication required). */
    public function Ping(): void
    {
        $this->SendDockMsg('ping');
    }

    /**
     * Perform a system command.
     * Allowed values (per docs):
     * ir_receive_on, ir_receive_off, remote_charged, remote_lowbattery, remote_normal, identify, reboot, reset
     */
    public function SystemCommand(string $command): void
    {
        $command = trim($command);
        if ($command === '') {
            $this->SendDebug(__FUNCTION__, '❌ Empty command.', 0);
            return;
        }
        $this->SendDockCommand($command);
    }

    /** Identify the dock: blink status LED green, amber, blue and red. */
    public function Identify(): void
    {
        $this->SystemCommand('identify');
    }

    /** Get system information (no authentication required). */
    public function GetSysInfo(): void
    {
        // Use the documented command message format.
        $this->SendDockCommand('get_sysinfo');
    }

    /** Stop a currently repeating IR transmission. */
    public function IRStop(): void
    {
        $this->SendDockCommand('ir_stop');
    }

    /**
     * Send an IR code.
     *
     * @param string $code IR code (Unfolded Circle hex or Pronto)
     * @param string $format 'hex' or 'pronto'
     * @param int $repeat Optional repeat value (0..20)
     * @param array $outputs Optional outputs, e.g. ['int_side'=>true,'int_top'=>true,'ext1'=>true,'ext2'=>true]
     * @param int $featureFlags Optional feature flags (field `f`)
     * @param int $holdMs Optional hold duration in ms (if supported by dock feature flags)
     */
    public function IRSend(string $code, string $format = 'hex', int $repeat = 0, array $outputs = [], int $featureFlags = 0, int $holdMs = 0): void
    {
        $code = trim($code);
        if ($code === '') {
            $this->SendDebug(__FUNCTION__, '❌ Empty IR code.', 0);
            return;
        }

        $format = strtolower(trim($format));
        if ($format !== 'hex' && $format !== 'pronto') {
            $format = 'hex';
        }

        $fields = [
            'code' => $code,
            'format' => $format,
            'repeat' => $repeat,
            'f' => $featureFlags
        ];

        // Optional hold (only if caller provided > 0)
        if ($holdMs > 0) {
            $fields['hold'] = $holdMs;
        }

        // Optional outputs (bool flags)
        foreach (['int_side', 'int_top', 'ext1', 'ext2'] as $k) {
            if (array_key_exists($k, $outputs)) {
                $fields[$k] = (bool)$outputs[$k];
            }
        }

        $this->SendDockCommand('ir_send', $fields);
    }

    /**
     * Set LED brightness.
     *
     * @param int $ledBrightness Main status LED brightness
     * @param int $ethernetLedBrightness Optional ethernet LED brightness (0 = off)
     */
    public function SetBrightness(int $ledBrightness, int $ethernetLedBrightness = -1): void
    {
        // Symcon side uses percent (0..100). Dock API expects raw brightness values 0..255.
        $pct = max(0, min(100, $ledBrightness));
        $status = (int)round($pct * 255 / 100);

        $fields = [
            // Dock API schema: setBrightnessMsg
            // fields: status_led (0..255) and optional eth_led (0..255)
            'status_led' => $status
        ];

        if ($ethernetLedBrightness >= 0) {
            $pctEth = max(0, min(100, $ethernetLedBrightness));
            $eth = (int)round($pctEth * 255 / 100);
            $fields['eth_led'] = $eth;
        }

        $this->SendDebug(__FUNCTION__, '➡️ set_brightness (Dock API) fields: ' . json_encode($fields, JSON_UNESCAPED_SLASHES), 0);
        $this->SendDockCommand('set_brightness', $fields);
    }

    /** Set speaker volume (0..100 depending on firmware). */
    public function SetVolume(int $volume): void
    {
        $this->SendDockCommand('set_volume', ['volume' => $volume]);
    }

    /**
     * Configure dock logging.
     * This is a thin wrapper; exact fields may differ by firmware version.
     */
    public function SetLogging(string $level, bool $enabled = true): void
    {
        $level = trim($level);
        if ($level === '') {
            $level = 'info';
        }
        $this->SendDockCommand('set_logging', ['level' => $level, 'enabled' => $enabled]);
    }

    /** Get active/supported configuration for a single external port. */
    public function GetPortMode(int $port): void
    {
        $this->SendDockCommand('get_port_mode', ['port' => $port]);
    }

    /** Get active/supported configuration for all external ports. */
    public function GetPortModes(): void
    {
        $this->SendDockCommand('get_port_modes');
    }

    /**
     * Get cached port modes from attribute.
     *
     * @return array|null
     */
    private function GetCachedPortModes(): ?array
    {
        $raw = trim($this->ReadAttributeString('port_modes_raw'));
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Build rows for the form List control.
     *
     * @return array
     */
    private function BuildPortModesListValues(): array
    {
        $data = $this->GetCachedPortModes();
        if (!is_array($data)) {
            return [];
        }

        $ports = $data['ports'] ?? null;
        if (!is_array($ports)) {
            return [];
        }

        $rows = [];
        foreach ($ports as $p) {
            if (!is_array($p)) {
                continue;
            }
            $supported = $p['supported_modes'] ?? [];
            if (!is_array($supported)) {
                $supported = [];
            }

            $rows[] = [
                'port' => (int)($p['port'] ?? 0),
                'mode' => (string)($p['mode'] ?? ''),
                'active_mode' => (string)($p['active_mode'] ?? ''),
                'supported_modes' => implode(', ', array_map('strval', $supported))
            ];
        }

        return $rows;
    }

    /**
     * Request port modes and wait (best-effort) for a response.
     * This enables calling from scripts where the response arrives asynchronously via ReceiveData.
     *
     * @param int $timeoutMs
     * @return string JSON (same structure as Dock response) or empty string on timeout.
     */
    public function GetPortModesAndWait(int $timeoutMs = 2000): string
    {
        $timeoutMs = max(0, $timeoutMs);

        // Snapshot current req id so we can detect a newer response.
        $beforeReqId = (int)$this->ReadAttributeInteger('port_modes_last_req_id');

        // Trigger request
        $this->GetPortModes();

        if ($timeoutMs === 0) {
            return '';
        }

        $deadline = microtime(true) + ($timeoutMs / 1000.0);
        while (microtime(true) < $deadline) {
            $afterReqId = (int)$this->ReadAttributeInteger('port_modes_last_req_id');
            if ($afterReqId !== 0 && $afterReqId !== $beforeReqId) {
                // We got a newer response
                return (string)$this->ReadAttributeString('port_modes_raw');
            }
            IPS_Sleep(50);
        }

        $this->SendDebug(__FUNCTION__, '⏱️ Timeout waiting for get_port_modes response (' . $timeoutMs . 'ms).', 0);
        return '';
    }

    /**
     * Set external port mode.
     *
     * @param int $port 1-based port index
     * @param string $mode Mode string (e.g. AUTO, NONE, IR_BLASTER, TRIGGER_5V, RS232, ...)
     * @param array $uart Optional UART config for RS232, e.g. ['baud_rate'=>9600,'data_bits'=>8,'stop_bits'=>'1','parity'=>'none']
     */
    public function SetPortMode(int $port, string $mode, array $uart = []): void
    {
        $fields = [
            'port' => $port,
            'mode' => $mode
        ];
        if (!empty($uart)) {
            $fields['uart'] = $uart;
        }
        $this->SendDockCommand('set_port_mode', $fields);
    }

    /**
     * Configure 5V trigger output for a port.
     *
     * @param int $port 1-based port index
     * @param bool $enabled Enable/disable trigger
     * @param int $pulseMs Optional pulse duration in ms (0 = continuous, depending on firmware)
     */
    public function SetPortTrigger(int $port, bool $enabled, int $pulseMs = 0): void
    {
        $fields = [
            'port' => $port,
            'enabled' => $enabled
        ];
        if ($pulseMs > 0) {
            $fields['pulse'] = $pulseMs;
        }
        $this->SendDockCommand('set_port_trigger', $fields);
    }

    /** Get current trigger configuration for a port. */
    public function GetPortTrigger(int $port): void
    {
        $this->SendDockCommand('get_port_trigger', ['port' => $port]);
    }

    /**
     * Set (partial) dock configuration.
     * This is a generic wrapper that forwards the given config object as-is.
     */
    public function SetConfig(array $config): void
    {
        $this->SendDockCommand('set_config', $config);
    }

    /**
     * Convenience: send a raw Dock API payload (advanced).
     * The payload must already follow the Dock schema.
     */
    public function SendRawDockPayload(array $payload): void
    {
        if (!isset($payload['type'])) {
            $payload['type'] = 'dock';
        }
        $this->SendDebug(__FUNCTION__, '➡️ Sending raw dock payload: ' . json_encode($payload, JSON_UNESCAPED_SLASHES), 0);
        $this->SendToWebSocket($payload);
    }

    /**
     * Triggers Dock WebSocket authentication using a provided access token.
     * Useful for manual testing from scripts.
     */
    public function Authenticate(): void
    {
        // Prefer the editable property if present; keep attribute synced.
        $prop = trim($this->ReadPropertyString('pin'));
        if ($prop === '') {
            $prop = trim($this->ReadPropertyString('api_key_display')); // backward compatibility
        }
        if ($prop !== '') {
            $this->WriteAttributeString('api_key', $prop);
        }

        $token = trim($this->ReadAttributeString('api_key'));
        if ($token === '') {
            $this->SendDebug(__FUNCTION__, '❌ Empty token – cannot authenticate.', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, '🔐 Authenticate(): sending Dock auth message using the currently stored Dock API access token.', 0);
        $this->SendAuth($token);
    }

    public function AuthenticateWithToken(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            $this->SendDebug(__FUNCTION__, '❌ Empty token – cannot authenticate.', 0);
            return;
        }

        // Store token for reuse and keep form property in sync where possible.
        $this->WriteAttributeString('api_key', $token);
        $this->SendDebug(__FUNCTION__, '🔐 AuthenticateWithToken(): token stored to attribute api_key.', 0);

        $this->SendAuth($token);

        if (method_exists($this, 'ReloadForm')) {
            $this->ReloadForm();
        }
    }

    /**
     * Low-level brightness setter that maps legacy percent fields or Dock API raw fields to the Dock API schema.
     */
    public function SetBrightnessFields(array $fields): void
    {
        $out = [];

        // Accept percent (0..100) via legacy keys, OR raw values (0..255) via Dock API keys.

        // Main status LED
        if (array_key_exists('status_led', $fields)) {
            $v = (int)$fields['status_led'];
            if ($v >= 0) {
                $out['status_led'] = max(0, min(255, $v));
            }
        } elseif (array_key_exists('led_brightness', $fields)) {
            $pct = (int)$fields['led_brightness'];
            if ($pct >= 0) {
                $pct = max(0, min(100, $pct));
                $out['status_led'] = (int)round($pct * 255 / 100);
            }
        }

        // Ethernet LED
        if (array_key_exists('eth_led', $fields)) {
            $v = (int)$fields['eth_led'];
            if ($v >= 0) {
                $out['eth_led'] = max(0, min(255, $v));
            }
        } elseif (array_key_exists('eth_led_brightness', $fields)) {
            $pct = (int)$fields['eth_led_brightness'];
            if ($pct >= 0) {
                $pct = max(0, min(100, $pct));
                $out['eth_led'] = (int)round($pct * 255 / 100);
            }
        }

        if (empty($out)) {
            $this->SendDebug(__FUNCTION__, '⏸️ No valid brightness fields provided.', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, '➡️ set_brightness (Dock API) fields: ' . json_encode($out, JSON_UNESCAPED_SLASHES), 0);
        $this->SendDockCommand('set_brightness', $out);
    }

    /** Convenience: set only the main LED brightness. */
    public function SetLedBrightness(int $ledBrightness): void
    {
        // Convenience: percent 0..100
        $this->SetBrightnessFields(['led_brightness' => $ledBrightness]);
    }

    /** Convenience: set only the ethernet LED brightness. */
    public function SetEthernetLedBrightness(int $ethernetLedBrightness): void
    {
        // Convenience: percent 0..100
        $this->SetBrightnessFields(['eth_led_brightness' => $ethernetLedBrightness]);
    }

    private function SendToWebSocket(array $payload): void
    {
        // The WebSocket Client transports payloads as HEX strings (see incoming Buffer).
        // Therefore we also send HEX-encoded JSON to avoid corrupted frames (NUL bytes).
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->SendDebug(__FUNCTION__, '❌ WS send failed: json_encode returned false.', 0);
            return;
        }

        $hex = strtoupper(bin2hex($json));

        $data = [
            'DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}',
            'Buffer' => $hex
        ];

        $this->SendDebug(__FUNCTION__, '➡️ WS send (json): ' . $json, 0);
        $this->SendDebug(__FUNCTION__, '➡️ WS send (hex,len=' . strlen($hex) . '): ' . substr($hex, 0, 128) . (strlen($hex) > 128 ? '…' : ''), 0);
        $this->SendDataToParent(json_encode($data));
    }

    private function ForwardToChildren(array $payload): void
    {
        $data = [
            'DataID' => self::DOCK_CHILD_DATAID,
            // Children receive readable JSON; no HEX here
            'Buffer' => json_encode($payload, JSON_UNESCAPED_SLASHES)
        ];
        $this->SendDebug(__FUNCTION__, '➡️ Forward to children: ' . $data['Buffer'], 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ForwardData(string $JSONString): string
    {
        $this->SendDebug(__FUNCTION__, '📥 Incoming child data: ' . $JSONString, 0);

        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            $this->SendDebug(__FUNCTION__, '❌ Invalid JSON from child.', 0);
            return json_encode(['error' => 'Invalid JSON']);
        }

        if (!isset($data['Buffer'])) {
            $this->SendDebug(__FUNCTION__, '❌ Missing Buffer in child envelope.', 0);
            return json_encode(['error' => 'Missing Buffer']);
        }

        // Child sends Buffer as JSON string
        $bufferRaw = $data['Buffer'];
        $buffer = null;
        if (is_string($bufferRaw)) {
            $buffer = json_decode($bufferRaw, true);
            if (!is_array($buffer)) {
                // Sometimes Symcon already passes an array-like string; fallback to treating it as plain text
                $this->SendDebug(__FUNCTION__, '⚠️ Buffer is not JSON – raw: ' . $bufferRaw, 0);
            }
        } elseif (is_array($bufferRaw)) {
            $buffer = $bufferRaw;
        }

        if (!is_array($buffer)) {
            return json_encode(['error' => 'Invalid Buffer']);
        }

        // IR device payload from child (Remote3IRDockDevice)
        // Example: {"type":"ir","codeFormat":"UC_HEX","repeat":1,"code":"..."}
        $childType = (string)($buffer['type'] ?? '');
        if ($childType === 'ir') {
            $code = trim((string)($buffer['code'] ?? ''));
            if ($code === '') {
                return json_encode(['error' => 'Missing code']);
            }

            // Map child codeFormat to Dock API format
            $cf = strtoupper(trim((string)($buffer['codeFormat'] ?? '')));
            $format = 'hex';
            if ($cf === 'PRONTO') {
                $format = 'pronto';
            } elseif ($cf === 'UC_HEX' || $cf === 'HEX') {
                $format = 'hex';
            }

            $repeat = (int)($buffer['repeat'] ?? 0);
            if ($repeat < 0) {
                $repeat = 0;
            }

            // Optional Dock fields
            $outputs = $buffer['outputs'] ?? [];
            if (!is_array($outputs)) {
                $outputs = [];
            }
            // Default: use internal emitters if caller didn't specify
            if (empty($outputs)) {
                $outputs = ['int_side' => true, 'int_top' => true];
            }

            $f = (int)($buffer['f'] ?? 0);
            $hold = (int)($buffer['hold'] ?? 0);

            $this->SendDebug(__FUNCTION__, '➡️ Child IR payload received. codeFormat=' . $cf . ' mappedFormat=' . $format . ' repeat=' . $repeat . ' outputs=' . json_encode($outputs), 0);

            // Send via Dock WebSocket API
            $this->IRSend($code, $format, $repeat, $outputs, $f, $hold);
            // ACK to child: Dock will respond asynchronously over WS (forwarded via ReceiveData)
            return json_encode([
                'ok' => true,
                'queued' => true,
                'action' => 'ir_send',
                'format' => $format,
                'repeat' => $repeat
            ], JSON_UNESCAPED_SLASHES);
        } elseif ($childType === 'getDownloadUrl') {
            return $this->HandleGetDownloadUrl($buffer);
        }

        // New request style from Dock child: {"action":"get_sysinfo"}
        $action = (string)($buffer['action'] ?? '');
        if ($action !== '') {
            $this->SendDebug(__FUNCTION__, '➡️ Handling action: ' . $action, 0);
            switch ($action) {
                case 'get_sysinfo':
                    // Trigger the actual WS request; response will arrive via ReceiveData and be forwarded to children.
                    $this->GetSysInfo();
                    return '';
                case 'get_port_modes':
                    // Trigger the actual WS request; response will arrive via ReceiveData and be forwarded to children.
                    $this->GetPortModes();
                    return '';
                case 'authenticate':
                    // Trigger Dock auth (token is stored in attribute/property)
                    $this->Authenticate();
                    return '';

                case 'set_led_brightness':
                    $value = (int)($buffer['value'] ?? $buffer['led_brightness'] ?? -1);
                    if ($value < 0) {
                        return json_encode(['error' => 'Missing value']);
                    }
                    $this->SetLedBrightness($value);
                    return '';

                case 'set_eth_led_brightness':
                    $value = (int)($buffer['value'] ?? $buffer['eth_led_brightness'] ?? -1);
                    if ($value < 0) {
                        return json_encode(['error' => 'Missing value']);
                    }
                    $this->SetEthernetLedBrightness($value);
                    return '';

                case 'set_brightness':
                    // Support both legacy percent keys and Dock API raw keys
                    $payload = [];
                    if (array_key_exists('led_brightness', $buffer) || array_key_exists('value', $buffer)) {
                        $payload['led_brightness'] = (int)($buffer['led_brightness'] ?? $buffer['value'] ?? -1);
                    }
                    if (array_key_exists('eth_led_brightness', $buffer)) {
                        $payload['eth_led_brightness'] = (int)$buffer['eth_led_brightness'];
                    }
                    // Allow passing raw Dock API fields directly
                    if (array_key_exists('status_led', $buffer)) {
                        $payload['status_led'] = (int)$buffer['status_led'];
                    }
                    if (array_key_exists('eth_led', $buffer)) {
                        $payload['eth_led'] = (int)$buffer['eth_led'];
                    }

                    $this->SetBrightnessFields($payload);
                    return '';

                case 'set_volume':
                    $value = (int)($buffer['value'] ?? $buffer['volume'] ?? -1);
                    if ($value < 0) {
                        return json_encode(['error' => 'Missing value']);
                    }
                    $this->SetVolume($value);
                    return '';

                case 'set_port_mode':
                    $port = (int)($buffer['port'] ?? 0);
                    $mode = (string)($buffer['mode'] ?? '');
                    // Allow a compact numeric value mapping from child (0=AUTO, 1=NONE)
                    if ($mode === '' && isset($buffer['value'])) {
                        $mode = ((int)$buffer['value'] === 0) ? 'AUTO' : 'NONE';
                    }
                    if ($port <= 0 || $mode === '') {
                        return json_encode(['error' => 'Missing port/mode']);
                    }
                    $uart = $buffer['uart'] ?? [];
                    if (!is_array($uart)) {
                        $uart = [];
                    }
                    $this->SetPortMode($port, $mode, $uart);
                    return '';

                case 'set_port_trigger':
                    $port = (int)($buffer['port'] ?? 0);
                    $enabled = (bool)($buffer['enabled'] ?? false);
                    $pulse = (int)($buffer['pulse'] ?? 0);
                    if ($port <= 0) {
                        return json_encode(['error' => 'Missing port']);
                    }
                    $this->SetPortTrigger($port, $enabled, $pulse);
                    return '';

                case 'system_command':
                    $cmd = (string)($buffer['command'] ?? $buffer['value'] ?? '');
                    if (trim($cmd) === '') {
                        return json_encode(['error' => 'Missing command']);
                    }
                    $this->SystemCommand($cmd);
                    return '';

                case 'ir_stop':
                    $this->IRStop();
                    return '';

                case 'ir_send':
                    $code = (string)($buffer['code'] ?? '');
                    if (trim($code) === '') {
                        return json_encode(['error' => 'Missing code']);
                    }
                    $format = (string)($buffer['format'] ?? 'hex');
                    $repeat = (int)($buffer['repeat'] ?? 0);
                    $outputs = $buffer['outputs'] ?? [];
                    if (!is_array($outputs)) {
                        $outputs = [];
                    }
                    $f = (int)($buffer['f'] ?? 0);
                    $hold = (int)($buffer['hold'] ?? 0);
                    $this->IRSend($code, $format, $repeat, $outputs, $f, $hold);
                    // ACK to child: Dock will respond asynchronously over WS (forwarded via ReceiveData)
                    return json_encode([
                        'ok' => true,
                        'queued' => true,
                        'action' => 'ir_send',
                        'format' => $format,
                        'repeat' => $repeat
                    ], JSON_UNESCAPED_SLASHES);
                default:
                    $this->SendDebug(__FUNCTION__, '⚠️ Unknown action: ' . $action, 0);
                    return json_encode(['error' => 'Unknown action']);
            }
        }

        // Backward compatibility: old style {"method":"..."}
        $method = (string)($buffer['method'] ?? '');
        if ($method !== '') {
            $this->SendDebug(__FUNCTION__, '➡️ Handling legacy method: ' . $method, 0);
            switch ($method) {
                case 'CallGetVersion':
                    // Legacy placeholder; no-op for Dock Manager
                    $this->SendDebug(__FUNCTION__, 'ℹ️ Legacy CallGetVersion requested – not implemented for Dock Manager.', 0);
                    return '';
                default:
                    $this->SendDebug(__FUNCTION__, '⚠️ Unknown legacy method: ' . $method, 0);
                    return json_encode(['error' => 'Unknown method']);
            }
        }

        $this->SendDebug(__FUNCTION__, '⚠️ No action/method provided in child request.', 0);
        return json_encode(['error' => 'No action']);
    }

    public function ReceiveData(string $JSONString): string
    {
        $this->SendDebug(__FUNCTION__, '📥 Envelope: ' . $JSONString, 0);

        $envelope = json_decode($JSONString, true);
        if (!is_array($envelope) || !isset($envelope['Buffer'])) {
            $this->SendDebug(__FUNCTION__, '⚠️ Invalid envelope (missing Buffer).', 0);
            return '';
        }

        $raw = $envelope['Buffer'];

        // 1) Raw debug
        if (is_string($raw)) {
            $this->SendDebug(__FUNCTION__, '📥 Buffer (string) length=' . strlen($raw), 0);
            // Avoid logging extremely long buffers verbatim; log a safe prefix
            $this->SendDebug(__FUNCTION__, '📥 Buffer (string, prefix): ' . substr($raw, 0, 256) . (strlen($raw) > 256 ? '…' : ''), 0);
        } else {
            $this->SendDebug(__FUNCTION__, '📥 Buffer (non-string): ' . json_encode($raw), 0);
        }

        $payload = null;

        // 2) Try JSON decode directly if Buffer is a JSON string
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
                $this->SendDebug(__FUNCTION__, '✅ Buffer is JSON (direct).', 0);
            }
        } elseif (is_array($raw)) {
            // Some IOs may already provide the payload as array
            $payload = $raw;
            $this->SendDebug(__FUNCTION__, '✅ Buffer is array (already decoded).', 0);
        }

        // 3) If not JSON, check if Buffer is HEX-encoded JSON (what you currently see in debug)
        if ($payload === null && is_string($raw)) {
            $maybeHex = $raw;
            $isHex = (strlen($maybeHex) % 2 === 0) && (strlen($maybeHex) >= 2) && ctype_xdigit($maybeHex);
            if ($isHex) {
                $this->SendDebug(__FUNCTION__, 'ℹ️ Buffer looks like HEX – attempting hex2bin + JSON decode.', 0);
                $bin = @hex2bin($maybeHex);
                if ($bin !== false) {
                    $this->SendDebug(__FUNCTION__, '📥 Buffer (hex2bin) length=' . strlen($bin), 0);
                    $this->SendDebug(__FUNCTION__, '📥 Buffer (hex2bin, prefix): ' . substr($bin, 0, 256) . (strlen($bin) > 256 ? '…' : ''), 0);
                    $decoded = json_decode($bin, true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                        $this->SendDebug(__FUNCTION__, '✅ Buffer decoded from HEX JSON.', 0);
                    } else {
                        $this->SendDebug(__FUNCTION__, '⚠️ HEX decoded but JSON parse failed. Raw(bin) prefix logged above.', 0);
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, '⚠️ hex2bin failed (invalid HEX).', 0);
                }
            }
        }

        // 4) If still unknown, log and stop
        if (!is_array($payload)) {
            $this->SendDebug(__FUNCTION__, '⚠️ Payload could not be decoded (neither JSON nor HEX-JSON).', 0);
            return '';
        }

        $this->SendDebug(__FUNCTION__, '📥 Payload (decoded): ' . json_encode($payload), 0);

        // Forward every decoded dock message to child instances (children can filter by msg/type)
        $this->ForwardToChildren($payload);

        // Dock WebSocket API: server sends `auth_required` right after connect.
        // We must respond with `{"type":"auth","token":"..."}`.
        if (($payload['type'] ?? '') === 'auth_required') {
            $this->SendDebug(__FUNCTION__, '🔐 Dock requested authentication (auth_required). Sending {"type":"auth","token":"..."}.', 0);

            if ($this->EnsureApiKey()) {
                $token = (string)$this->ReadAttributeString('api_key');
                $this->SendAuth($token);
            } else {
                $this->SendDebug(__FUNCTION__, '⏸️ Cannot authenticate yet (Token missing).', 0);
            }
            return '';
        }

        // Log authentication result (optional)
        if (($payload['type'] ?? '') === 'authentication') {
            $code = $payload['code'] ?? null;
            $this->SendDebug(__FUNCTION__, '🔐 Authentication result code: ' . json_encode($code), 0);
            return '';
        }

        // Dock sysinfo response comes as: {"type":"dock","msg":"get_sysinfo", ...}
        if (($payload['type'] ?? '') === 'dock' && ($payload['msg'] ?? '') === 'get_sysinfo' && isset($payload['code']) && (int)$payload['code'] === 200) {
            $this->SendDebug(__FUNCTION__, '🧾 Dock sysinfo received (dock/get_sysinfo): ' . json_encode($payload), 0);
            $this->WriteAttributeString('sysinfo_raw', json_encode($payload));
            $this->ForwardToChildren($payload);
            if (isset($payload['req_id'])) {
                $this->WriteAttributeString('sysinfo_last_req_id', (string)$payload['req_id']);
            }
            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
            return '';
        }

        // Dock port modes response: {"type":"dock","msg":"get_port_modes","ports":[...],"code":200,"req_id":7}
        if (($payload['type'] ?? '') === 'dock' && ($payload['msg'] ?? '') === 'get_port_modes' && isset($payload['code']) && (int)$payload['code'] === 200) {
            $this->SendDebug(__FUNCTION__, '🔌 Dock port modes received (dock/get_port_modes): ' . json_encode($payload), 0);

            // Persist raw response for UI + script access
            $this->WriteAttributeString('port_modes_raw', json_encode($payload, JSON_UNESCAPED_SLASHES));
            if (isset($payload['req_id'])) {
                $this->WriteAttributeInteger('port_modes_last_req_id', (int)$payload['req_id']);
            }

            // Forward to children (already forwarded globally above, but keep explicit for clarity)
            $this->ForwardToChildren($payload);

            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
            return '';
        }

        // Dock IR send response: {"type":"dock","msg":"ir_send","code":200,"req_id":...}
        if (($payload['type'] ?? '') === 'dock' && ($payload['msg'] ?? '') === 'ir_send') {
            $this->SendDebug(__FUNCTION__, '📡 Dock ir_send response: ' . json_encode($payload), 0);
            // Forward to children (already forwarded globally above, but keep explicit for clarity)
            $this->ForwardToChildren($payload);
            return '';
        }

        // Dock system info response (expected after get_sysinfo)
        $type = (string)($payload['type'] ?? '');
        if ($type === 'sysinfo' || $type === 'get_sysinfo' || $type === 'sys_info' || $type === 'system' || $type === 'system_info') {
            $this->SendDebug(__FUNCTION__, '🧾 Sysinfo received: ' . json_encode($payload), 0);
            $this->WriteAttributeString('sysinfo_raw', json_encode($payload));
            $this->ForwardToChildren($payload);

            if (isset($payload['req_id'])) {
                $this->WriteAttributeString('sysinfo_last_req_id', (string)$payload['req_id']);
            } elseif (isset($payload['reqId'])) {
                $this->WriteAttributeString('sysinfo_last_req_id', (string)$payload['reqId']);
            }

            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
            return '';
        }

        // Wrapped sysinfo
        if (($payload['kind'] ?? '') === 'result' || ($payload['type'] ?? '') === 'result' || ($payload['type'] ?? '') === 'resp') {
            $msgData = $payload['msg_data'] ?? $payload['data'] ?? null;
            if (is_array($msgData) && (isset($msgData['model']) || isset($msgData['hostname']) || isset($msgData['firmware']) || isset($msgData['hw_rev']))) {
                $this->SendDebug(__FUNCTION__, '🧾 Sysinfo (wrapped) received: ' . json_encode($payload), 0);
                $this->WriteAttributeString('sysinfo_raw', json_encode($payload));
                if (method_exists($this, 'ReloadForm')) {
                    $this->ReloadForm();
                }
                return '';
            }
        }

        // For now, only log other messages; can be extended later.
        return '';
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        //Never delete this line!
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SendDebug(__FUNCTION__, '✅ Kernel READY – sende Initial-Events', 0);
            $this->RegisterHook('unfoldedcircle_dock3/' . $this->InstanceID . '/download');
        }
    }

    /**
     * WebHook handler (download exported files from /media subfolder).
     *
     * URL pattern:
     *   /hook/unfoldedcircle_dock3/<InstanceID>/download?file=<filename>&token=<secret>
     */
    public function ProcessHookData(): void
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
        $this->SendDebug('WEBHOOK', 'QUERY_STRING=' . $qs, 0);
        // Some Symcon environments do not include the query string in REQUEST_URI.
        // Therefore we primarily rely on $_GET for parameters.
        $this->SendDebug('WEBHOOK', 'GET=' . json_encode($_GET, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0);
        // --- Debug: incoming request overview (do NOT leak full token/secret) ---
        $this->SendDebug('WEBHOOK', 'REQUEST_URI=' . $uri, 0);
        $this->SendDebug('WEBHOOK', 'SERVER_ADDR=' . ((string)($_SERVER['SERVER_ADDR'] ?? '')) . ' REMOTE_ADDR=' . ((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0);
        $this->SendDebug('WEBHOOK', 'HTTPS=' . ((string)($_SERVER['HTTPS'] ?? '')) . ' HTTP_HOST=' . ((string)($_SERVER['HTTP_HOST'] ?? '')) . ' SERVER_PORT=' . ((string)($_SERVER['SERVER_PORT'] ?? '')), 0);

        $parsed = parse_url($uri);
        $path = (string)($parsed['path'] ?? '');

        // Prefer QUERY_STRING / $_GET over REQUEST_URI parsing, as REQUEST_URI may be missing the query part.
        $query = (string)($parsed['query'] ?? '');
        if ($query === '' && $qs !== '') {
            $query = $qs;
        }

        $params = [];
        if (!empty($_GET)) {
            $params = $_GET;
        } elseif ($query !== '') {
            parse_str($query, $params);
        }

        $this->SendDebug('WEBHOOK', 'path=' . $path . ' query=' . $query . ' (params_source=' . (!empty($_GET) ? '$_GET' : ($query !== '' ? 'QUERY_STRING' : 'none')) . ')', 0);

        // Only handle our instance-specific hook (must match the registered hook exactly)
        $expected = '/hook/unfoldedcircle_dock3/' . $this->InstanceID . '/download';
        if ($path !== $expected) {
            $this->SendDebug('WEBHOOK', '❌ Path mismatch. expected=' . $expected . ' got=' . $path, 0);
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $this->SendDebug('WEBHOOK', '✅ Path matches expected hook', 0);

        $file = isset($params['file']) ? (string)$params['file'] : '';
        $token = isset($params['token']) ? (string)$params['token'] : '';
        $this->SendDebug('WEBHOOK', 'params[file]=' . $file . ' params[token_prefix]=' . ($token !== '' ? substr($token, 0, 6) . '…' : 'EMPTY') . ' len=' . strlen($token), 0);

        // Basic auth via stored secret
        $secret = (string)$this->ReadAttributeString('DownloadSecret');
        $this->SendDebug('WEBHOOK', 'secret_prefix=' . substr($secret, 0, 6) . '… len=' . strlen($secret), 0);

        if ($secret === '') {
            $this->SendDebug('WEBHOOK', '❌ Forbidden: DownloadSecret attribute is empty', 0);
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        if ($token === '') {
            $this->SendDebug('WEBHOOK', '❌ Forbidden: token parameter is missing/empty', 0);
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        if (!hash_equals($secret, $token)) {
            $this->SendDebug('WEBHOOK', '❌ Forbidden: token mismatch (secret_prefix=' . substr($secret, 0, 6) . '… vs token_prefix=' . substr($token, 0, 6) . '…)', 0);
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $this->SendDebug('WEBHOOK', '✅ Token OK', 0);

        if ($file === '') {
            $this->SendDebug('WEBHOOK', '❌ Missing file parameter', 0);
            http_response_code(400);
            echo 'Missing file parameter';
            return;
        }

        // Prevent directory traversal
        $file = basename($file);
        $this->SendDebug('WEBHOOK', 'sanitized file=' . $file, 0);

        // Location: /media/UCD3Exports/<file>
        $kernelDir = IPS_GetKernelDir();
        $mediaDir = rtrim($kernelDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'media';
        $subDir = $mediaDir . DIRECTORY_SEPARATOR . 'UCD3Exports';
        $fullPath = $subDir . DIRECTORY_SEPARATOR . $file;
        $this->SendDebug('WEBHOOK', 'resolved fullPath=' . $fullPath, 0);

        if (!is_file($fullPath)) {
            $this->SendDebug('WEBHOOK', '❌ File not found at fullPath=' . $fullPath, 0);
            http_response_code(404);
            echo 'File not found';
            return;
        }
        $this->SendDebug('WEBHOOK', '✅ File exists, starting download', 0);

        // Try to set a helpful content-type based on extension
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $contentType = 'application/octet-stream';
        if ($ext === 'json') {
            $contentType = 'application/json; charset=utf-8';
        } elseif ($ext === 'csv') {
            $contentType = 'text/csv; charset=utf-8';
        } elseif ($ext === 'txt') {
            $contentType = 'text/plain; charset=utf-8';
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . (string)filesize($fullPath));
        $this->SendDebug('WEBHOOK', 'sending headers contentType=' . $contentType . ' size=' . (string)filesize($fullPath), 0);

        // Output file
        readfile($fullPath);
    }

    /**
     * Handle request from child instances to get a full download URL for an exported file.
     * Expected payload:
     *  - fileName: string
     *  - deviceInstanceId: int (optional, used for logging)
     * Returns JSON: {"url":"..."} or {"error":"..."}
     */
    private function HandleGetDownloadUrl(array $payload): string
    {
        $fileName = (string)($payload['fileName'] ?? '');
        $childId = (int)($payload['deviceInstanceId'] ?? 0);

        $this->SendDebug('HandleGetDownloadUrl', 'request from child=' . $childId . ' fileName=' . $fileName, 0);

        if (trim($fileName) === '') {
            return json_encode(['error' => 'Missing fileName']);
        }

        // Build absolute URL for the download
        $url = $this->BuildChildDownloadUrl($childId, $fileName);
        if ($url === '') {
            return json_encode(['error' => 'Failed to build download URL']);
        }

        return json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build a full (absolute) URL to download a file via this splitter's webhook.
     * The webhook is implemented by this instance in ProcessHookData().
     */
    public function BuildChildDownloadUrl(int $childInstanceId, string $fileName): string
    {
        // Sanitize file name to avoid traversal
        $fileName = basename($fileName);

        // Relative part (includes token)
        $relative = $this->GetDownloadUrl($fileName);

        // Best-effort base URL discovery
        $baseUrl = $this->DetectWebHookBaseUrl();

        $this->SendDebug('BuildChildDownloadUrl', json_encode([
            'child' => $childInstanceId,
            'file' => $fileName,
            'baseUrl' => $baseUrl,
            'relative' => $relative
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0);
        $this->SendDebug('BuildChildDownloadUrl', 'finalUrl=' . (rtrim($baseUrl, '/') . $relative), 0);

        if ($baseUrl === '') {
            // Fallback: return relative URL if base could not be determined
            return $relative;
        }

        return rtrim($baseUrl, '/') . $relative;
    }

    /**
     * Try to detect a usable base URL (scheme://host:port) for the Symcon webhook.
     * This is best-effort; if it fails, BuildChildDownloadUrl will return a relative URL.
     */
    private function DetectWebHookBaseUrl(): string
    {
        $scheme = 'http';
        $port = 3777; // default WebFront/WebHook port

        // Try to read port/SSL settings from the WebHook Control instance *safely*.
        // IPS_GetProperty throws warnings if a property does not exist in the instance schema.
        // Therefore we read the full configuration JSON and check keys.
        try {
            $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
            if (count($ids) > 0) {
                $whId = $ids[0];
                $cfgJson = IPS_GetConfiguration($whId);
                $cfg = json_decode($cfgJson, true);
                if (is_array($cfg)) {
                    if (isset($cfg['Port']) && is_numeric($cfg['Port']) && (int)$cfg['Port'] > 0) {
                        $port = (int)$cfg['Port'];
                    }

                    // Different Symcon versions may use different SSL property names
                    foreach (['EnableSSL', 'UseSSL', 'SSL'] as $sslKey) {
                        if (!array_key_exists($sslKey, $cfg)) {
                            continue;
                        }
                        $v = $cfg[$sslKey];
                        if (is_bool($v) && $v) {
                            $scheme = 'https';
                            break;
                        }
                        if (is_numeric($v) && (int)$v === 1) {
                            $scheme = 'https';
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore and keep defaults
        }

        // Determine host/IP of the Symcon server.
        // Prefer Sys_GetNetworkInfo (reliable on Symcon), fallback to PHP hostname resolution.
        $host = '';
        try {
            $network = Sys_GetNetworkInfo();
            if (is_array($network)) {
                foreach ($network as $device) {
                    $ip = (string)($device['IP'] ?? '');
                    // Pick the first plausible IPv4 that is not loopback / APIPA
                    if ($ip !== '' && $ip !== '127.0.0.1' && $ip !== 'localhost' && strpos($ip, '169.254.') !== 0) {
                        $host = $ip;
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        if ($host === '') {
            try {
                $host = gethostbyname(gethostname());
                if (!is_string($host)) {
                    $host = '';
                }
            } catch (Throwable $e) {
                $host = '';
            }
        }

        // If hostname resolution fails or returns localhost, try server_addr when available
        if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
            $serverAddr = (string)($_SERVER['SERVER_ADDR'] ?? '');
            if ($serverAddr !== '') {
                $host = $serverAddr;
            }
        }

        if ($host === '') {
            return '';
        }

        return $scheme . '://' . $host . ':' . $port;
    }

    /**
     * Helper: returns the instance-specific download URL (relative).
     * You can build an absolute URL by prefixing your Symcon base URL.
     */
    public function GetDownloadUrl(string $filename): string
    {
        $filename = basename($filename);
        $secret = (string)$this->ReadAttributeString('DownloadSecret');
        return '/hook/unfoldedcircle_dock3/' . $this->InstanceID . '/download?file=' . rawurlencode($filename) . '&token=' . rawurlencode($secret);
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
        $host = $this->ReadPropertyString('host');
        $wsHost = $this->ReadPropertyString('ws_host');
        $manualSetup = ($host === '');

        // Helper for read-only property display
        $ro = function (string $name, string $caption) {
            return [
                'type' => 'ValidationTextBox',
                'name' => $name,
                'caption' => $caption,
                'enabled' => false
            ];
        };

        $form = [];

        // --- Optional: Remote 3 Core instance API key reuse (hidden for now) ---
        //
        // $form[] = [
        //     'type' => 'SelectInstance',
        //     'name' => 'core_instance_id',
        //     'caption' => 'Remote 3 Core instance',
        //     'moduleID' => '{C810D534-2395-7C43-D0BE-6DEC069B2516}'
        // ];
        //
        // $form[] = [
        //     'type' => 'Label',
        //     'caption' => 'Select your Remote 3 Core instance to automatically reuse its API key.'
        // ];

        // Manual setup: allow entering host + websocket host
        if ($manualSetup) {
            $form[] = [
                'type' => 'Select',
                'name' => 'model',
                'caption' => 'Model',
                'options' => [
                    ['caption' => 'Dock 3 (UCD3)', 'value' => self::MODEL_DOCK3],
                    ['caption' => 'Dock 2 (UCD2)', 'value' => self::MODEL_DOCK2]
                ]
            ];
            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'host',
                'caption' => 'Host (IP)',
                'enabled' => true
            ];

            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'ws_host',
                'caption' => 'WebSocket host',
                'enabled' => true
            ];

            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'pin',
                'caption' => 'PIN',
                'value' => $this->ReadAttributeString('api_key'),
                'enabled' => true
            ];
            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'sysinfo_display',
                'caption' => 'Last sysinfo (raw JSON)',
                'value' => $this->ReadAttributeString('sysinfo_raw'),
                'enabled' => false
            ];
            $form[] = [
                'type' => 'ExpansionPanel',
                'caption' => 'Dock Ports (get_port_modes)',
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'Use the button "GetPortModes" (or "Request Dock sysinfo" + custom scripts) to refresh port data.'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'port_modes_list',
                        'caption' => 'Ports',
                        'rowCount' => 6,
                        'add' => false,
                        'delete' => false,
                        'sort' => [
                            'column' => 'port',
                            'direction' => 'ascending'
                        ],
                        'columns' => [
                            ['caption' => 'Port', 'name' => 'port', 'width' => '60px'],
                            ['caption' => 'Mode', 'name' => 'mode', 'width' => '90px'],
                            ['caption' => 'Active', 'name' => 'active_mode', 'width' => '90px'],
                            ['caption' => 'Supported modes', 'name' => 'supported_modes', 'width' => 'auto']
                        ],
                        'values' => $this->BuildPortModesListValues()
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port_modes_raw_display',
                        'caption' => 'Last get_port_modes (raw JSON)',
                        'value' => $this->ReadAttributeString('port_modes_raw'),
                        'enabled' => false,
                        'multiline' => true
                    ]
                ]
            ];
        } else {
            // Discovery setup: show all known properties read-only
            $form[] = [
                'type' => 'Label',
                'caption' => 'System information (read-only)'
            ];

            $form[] = $ro('hostname', 'Hostname');
            $form[] = $ro('model', 'Model');
            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'host',
                'caption' => 'Host (IP)',
                'enabled' => true
            ];
            $form[] = $ro('port', 'Port');
            $form[] = $ro('https_port', 'HTTPS port');

            $form[] = $ro('ws_host', 'WebSocket host');
            $form[] = $ro('ws_port', 'WebSocket port');
            $form[] = $ro('ws_path', 'WebSocket path');

            $form[] = $ro('ws_https_host', 'WebSocket HTTPS host');
            $form[] = $ro('ws_https_port', 'WebSocket HTTPS port');

            // Dock does not use web_config_user; remove this field.

            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'pin',
                'caption' => 'PIN',
                'enabled' => true
            ];
            $form[] = [
                'type' => 'ValidationTextBox',
                'name' => 'sysinfo_display',
                'caption' => 'Last sysinfo (raw JSON)',
                'value' => $this->ReadAttributeString('sysinfo_raw'),
                'enabled' => false
            ];
            $form[] = [
                'type' => 'ExpansionPanel',
                'caption' => 'Dock Ports (get_port_modes)',
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'Use the button "GetPortModes" (or "Request Dock sysinfo" + custom scripts) to refresh port data.'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'port_modes_list',
                        'caption' => 'Ports',
                        'rowCount' => 6,
                        'add' => false,
                        'delete' => false,
                        'sort' => [
                            'column' => 'port',
                            'direction' => 'ascending'
                        ],
                        'columns' => [
                            ['caption' => 'Port', 'name' => 'port', 'width' => '60px'],
                            ['caption' => 'Mode', 'name' => 'mode', 'width' => '90px'],
                            ['caption' => 'Active', 'name' => 'active_mode', 'width' => '90px'],
                            ['caption' => 'Supported modes', 'name' => 'supported_modes', 'width' => 'auto']
                        ],
                        'values' => $this->BuildPortModesListValues()
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'port_modes_raw_display',
                        'caption' => 'Last get_port_modes (raw JSON)',
                        'value' => $this->ReadAttributeString('port_modes_raw'),
                        'enabled' => false,
                        'multiline' => true
                    ]
                ]
            ];
        }

        return $form;
    }

    /**
     * return form actions by token
     *
     * @return array
     */
    protected function FormActions(): array
    {
        return [
            // --- Optional: Remote 3 Core instance API key reuse (hidden for now) ---
            // [
            //     'type' => 'Button',
            //     'caption' => 'Fetch API key from selected Remote 3 Core',
            //     'onClick' => 'UCD_UpdateApiKeyFromCore($id);'
            // ],
            [
                'type' => 'Button',
                'caption' => 'Update WS client configuration',
                'onClick' => 'UCD_UpdateWSClient($id);'
            ],
            [
                'type' => 'Button',
                'caption' => 'Request Dock sysinfo (get_sysinfo)',
                'onClick' => 'UCD_GetSysInfo($id);'
            ],
            [
                'type' => 'Button',
                'caption' => 'Request Port Modes (get_port_modes)',
                'onClick' => 'UCD_GetPortModes($id);'
            ],
            [
                'type' => 'Button',
                'caption' => 'Authenticate using PIN',
                'onClick' => 'UCD_Authenticate($id);'
            ],
            [
                'type' => 'Button',
                'caption' => 'Identify dock (blink LED)',
                'onClick' => 'UCD_Identify($id);'
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
                'caption' => 'Remote 3 Dock Manager created.'],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => 'Interface closed.']];

        return $form;
    }
}

