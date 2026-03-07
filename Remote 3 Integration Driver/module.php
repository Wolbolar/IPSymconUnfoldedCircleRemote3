<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/DnssdRemoteDiscoveryTrait.php';
require_once __DIR__ . '/../libs/DebugTrait.php';
require_once __DIR__ . '/../libs/UcrApiHelper.php';
require_once __DIR__ . '/../libs/WebSocketUtils.php';
require_once __DIR__ . '/../libs/Entity_Button.php';
require_once __DIR__ . '/../libs/Entity_Climate.php';
require_once __DIR__ . '/../libs/Entity_Cover.php';
require_once __DIR__ . '/../libs/Entity_IR_Emitter.php';
require_once __DIR__ . '/../libs/Entity_Light.php';
require_once __DIR__ . '/../libs/Entity_Media_Player.php';
require_once __DIR__ . '/../libs/Entity_Remote.php';
require_once __DIR__ . '/../libs/Entity_Sensor.php';
require_once __DIR__ . '/../libs/Entity_Switch.php';
require_once __DIR__ . '/../libs/DeviceRegistry.php';

include_once __DIR__ . '/../libs/ClientSessionManagement.php';

use WebsocketHandler\WebSocketUtils;

class Remote3IntegrationDriver extends IPSModuleStrict
{
    use ClientSessionTrait;
    use DebugTrait;
    use DnssdRemoteDiscoveryTrait;

    const DEFAULT_WS_PORT = 9988;

    const Socket_Data = 0;
    const Socket_Connected = 1;
    const Socket_Disconnected = 2;
    const Unfolded_Circle_Driver_Version = "0.5.0";
    const Unfolded_Circle_API_Version = "0.12.1";

    const Unfolded_Circle_API_Minimum_Version = "0.12.1";
    const DEVICE_STATE_CONNECTED = "CONNECTED";
    const DEVICE_STATE_CONNECTING = "CONNECTING";
    const DEVICE_STATE_DISCONNECTED = "DISCONNECTED";
    const DEVICE_STATE_ERROR = "ERROR";

    // Remote client session handling
    private const REMOTE_SESSION_TIMEOUT_SEC = 90; // after 90 seconds disconect no message is send anymore

    private ?UcrApiHelper $apiHelper = null;

    protected function Api(): UcrApiHelper
    {
        if ($this->apiHelper === null) {
            $this->apiHelper = new UcrApiHelper($this);
        }
        return $this->apiHelper;
    }

    public function GetApiKey(): string
    {
        return $this->Api()->GetApiKey();
    }

    public function ResetApiKey(): bool
    {
        return $this->Api()->ResetApiKey();
    }

    public function UploadSymconIcon(): string
    {
        return $this->Api()->UploadSymconIcon();
    }

    public function GetToken(): string
    {
        return $this->ReadAttributeString("token");
    }

    /**
     * Ensures a valid API key exists (creates/validates it if needed).
     *
     * This is implemented in the shared UcrApiHelper and exposed here as a local wrapper
     * so existing module code can keep calling $this->EnsureApiKey().
     */
    protected function EnsureApiKey(): bool
    {
        return $this->Api()->EnsureApiKey();
    }

    /**
     * Legacy wrapper: kept for backward compatibility after refactor to UcrApiHelper.
     */
    protected function EnsureRemoteApiAccess(): array
    {
        return $this->Api()->EnsureRemoteApiAccess();
    }

    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type' => 'require',
            'moduleIDs' => ['{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}']
        ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyBoolean('use_manual_host', false);
        $this->RegisterAttributeString('api_key', '');
        $this->RegisterAttributeString('api_key_name', '');
        $this->RegisterAttributeString('auth_mode', '');
        $this->RegisterAttributeString('symcon_uuid', '');
        $this->RegisterAttributeBoolean('icon_uploaded', false);
        $this->RegisterPropertyString('web_config_user', 'web-configurator');
        // REST configuration used by UcrApiHelper
        $this->RegisterAttributeString('web_config_pass', '');
        $this->RegisterAttributeString('remote_host', '');

        // store IPv4/IPv6 fallback addresses
        $this->RegisterAttributeString('remote_host_ipv4', '');
        $this->RegisterAttributeString('remote_host_ipv6', '');
        $this->RegisterAttributeString('remote_host_name', '');

        $this->RegisterAttributeString('token', '');

        $this->RegisterAttributeString('remote_cores', '');

        $this->RegisterAttributeString('client_sessions', '');
        $this->RegisterAttributeString('connected_clients', '');

        $this->RegisterAttributeString('events', '');

        $this->RegisterAttributeString('log_commands', '');

        $this->RegisterAttributeString('vm_update_vars', '[]');

        $this->RegisterPropertyString('device_popup', '[]');

        $this->RegisterAttributeString('media_player_cache', '{}');

        // use Attributes instead
        /*
        $this->RegisterPropertyString('popup_button_suggestions', '[]');
        $this->RegisterPropertyString('popup_climate_suggestions', '[]');
        $this->RegisterPropertyString('popup_cover_suggestions', '[]');
        $this->RegisterPropertyString('popup_light_suggestions', '[]');
        $this->RegisterPropertyString('popup_media_suggestions', '[]');
        $this->RegisterPropertyString('popup_remote_suggestions', '[]');
        $this->RegisterPropertyString('popup_sensor_suggestions', '[]');
        $this->RegisterPropertyString('popup_switch_suggestions', '[]');
        */

        $this->RegisterAttributeString('popup_button_suggestions', '[]');
        $this->RegisterAttributeString('popup_climate_suggestions', '[]');
        $this->RegisterAttributeString('popup_cover_suggestions', '[]');
        $this->RegisterAttributeString('popup_light_suggestions', '[]');
        $this->RegisterAttributeString('popup_media_suggestions', '[]');
        $this->RegisterAttributeString('popup_remote_suggestions', '[]');
        $this->RegisterAttributeString('popup_sensor_suggestions', '[]');
        $this->RegisterAttributeString('popup_switch_suggestions', '[]');
        $this->RegisterAttributeString('popup_select_suggestions', '[]');

        // Properties for Button and Switch mapping configuration
        $this->RegisterPropertyString('button_mapping', '[]');
        $this->RegisterPropertyString('switch_mapping', '[]');
        $this->RegisterPropertyString('climate_mapping', '[]');
        $this->RegisterPropertyString('cover_mapping', '[]');
        $this->RegisterPropertyString('ir_mapping', '[]');
        $this->RegisterPropertyString('light_mapping', '[]');
        $this->RegisterPropertyString('media_player_mapping', '[]');
        $this->RegisterPropertyString('remote_mapping', '[]');
        $this->RegisterPropertyString('sensor_mapping', '[]');
        $this->RegisterPropertyString('ip_whitelist', '[]');
        $this->RegisterPropertyString('select_mapping', '[]');

        // --- Expert Debug / Debug Filtering ---
        $this->RegisterPropertyBoolean('expert_debug', false);
        $this->RegisterPropertyInteger('debug_level', 4); // 0=BASIC,1=ERROR,2=WARN,3=INFO,4=TRACE
        $this->RegisterPropertyBoolean('debug_filter_enabled', false);
        $this->RegisterPropertyString('debug_topics', ''); // comma-separated topics; empty = all
        $this->RegisterPropertyString('debug_entity_ids', ''); // comma-separated entity ids
        $this->RegisterPropertyString('debug_var_ids', ''); // comma-separated var/object ids
        $this->RegisterPropertyString('debug_client_ips', ''); // comma-separated IPs
        $this->RegisterPropertyString('debug_text_filter', ''); // substring or regex
        $this->RegisterPropertyBoolean('debug_text_is_regex', false);
        $this->RegisterPropertyBoolean('debug_strict_match', true); // require match when any filter is set
        $this->RegisterPropertyInteger('debug_throttle_ms', 0); // 0 disables throttling
        $this->RegisterPropertyString('debug_topics_cfg', '');
        $this->RegisterPropertyString('debug_filter_instances', '');
        $this->RegisterPropertyString('debug_client_ips_cfg', '');

        // Properties for expert settings
        $this->RegisterPropertyBoolean('extended_debug', false);
        $this->RegisterPropertyString('callback_IP', '');

        // Add the setup flow attribute registration
        $this->RegisterAttributeBoolean('use_complex_setup', false);

        //We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        // $this->RequireParent('{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}');
        $this->RegisterTimer("PingDeviceState", 0, 'UCR_PingDeviceState($_IPS[\'TARGET\']);');
        $this->RegisterAttributeString('remote_directory', '[]');
        $this->RegisterTimer('RefreshRemoteDirectory', 0, 'UCR_RefreshRemoteDirectory($_IPS["TARGET"]);');

        $this->RegisterTimer('UpdateAllEntityStates', 0, 'UCR_UpdateAllEntityStates($_IPS["TARGET"]);');

    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
        $this->UnregisterHook('unfoldedcircle');
        $this->UnregisterMdnsService();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_EXT, '⚙️ ApplyChanges() called', 0);
        if ($this->IsManualHostEnabled()) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧩 Manual host override enabled → using host=' . $this->ReadPropertyString('host'), 0);
        }
        //Only call this in READY state. On startup the WebHook instance might not be available yet
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterHook('unfoldedcircle');
            $this->RegisterMdnsService();
            $this->SetTimerInterval("PingDeviceState", 30000); // alle 30 Sekunden den Status senden
            $this->SetTimerInterval("UpdateAllEntityStates", 15000); // alle 15 Sekunden den Status senden
            $this->SetTimerInterval('RefreshRemoteDirectory', 60000);
            // Unfiltered debug to verify timer setup (visible even when DebugTrait filters are active)
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ RefreshRemoteDirectory timer interval set to 60000 ms', 0);

            // Run once immediately so users see results without waiting for the first timer tick
            $this->RefreshRemoteDirectory();
            $this->EnsureTokenInitialized();
        }
        // Register for variable updates for all mapped entities (switches, sensors, lights, covers, climate, media)
        $this->SyncVmUpdateRegistrations();

        // Register for status changes of the I/O (WebSocket) instance
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID > 0) {
            $this->RegisterMessage($parentID, IM_CHANGESTATUS);
        }
    }

    /**
     * Fallback: Try to obtain discovered remotes from the dedicated Discovery instance.
     *
     * @return array List of remote entries (best-effort).
     */
    private function GetRemoteDirectoryFromDiscovery(): array
    {
        $guid = '{4C0ABD10-D25B-0D92-9B2A-9E10E24659B0}';
        $ids = @IPS_GetInstanceListByModuleID($guid);
        if (!is_array($ids) || count($ids) === 0) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_EXT, '🔎 No Discovery instance found for GUID ' . $guid, 0);
            return [];
        }

        // Pick the first active instance
        $discoveryId = 0;
        foreach ($ids as $id) {
            $status = (int)@IPS_GetInstance($id)['InstanceStatus'];
            if ($status === IS_ACTIVE) {
                $discoveryId = (int)$id;
                break;
            }
        }
        if ($discoveryId === 0) {
            $discoveryId = (int)$ids[0];
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧭 Fallback to Discovery instance ' . $discoveryId . ' (UCR_GetDevices)', 0);

        // UCR_GetDevices is expected to return either an array or a JSON string (depending on implementation)
        $devices = @UCR_GetDevices($discoveryId);
        if (is_string($devices)) {
            $decoded = json_decode($devices, true);
            if (is_array($decoded)) {
                $devices = $decoded;
            }
        }
        if (!is_array($devices)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '⚠️ Discovery returned no usable devices', 0);
            return [];
        }

        // Best-effort normalize: some implementations return {"devices": [...]}.
        if (isset($devices['devices']) && is_array($devices['devices'])) {
            $devices = $devices['devices'];
        }

        // Ensure list of arrays
        $result = [];
        foreach ($devices as $d) {
            if (is_array($d)) {
                $result[] = $d;
            }
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧭 Discovery fallback returned ' . count($result) . ' device(s)', 0);
        return $result;
    }

    public function RefreshRemoteDirectory(): void
    {
        // Unfiltered debug: helps verifying that the method is actually executed
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_EXT, '🔎 RefreshRemoteDirectory() called', 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_EXT, '🔎 Refresh remote directory (mDNS)', 0);

        $devices = $this->SearchRemotes();          // aus Trait
        $info = $this->GetRemoteInfo($devices);  // aus Trait

        // Fallback: If our own mDNS scan returns nothing, try the dedicated Discovery instance (many users already have it).
        if (!is_array($info) || count($info) === 0) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '⚠️ No remotes found via internal mDNS scan → trying Discovery fallback', 0);
            $info = $this->GetRemoteDirectoryFromDiscovery();
        }

        // Speichern als Referenzliste
        $this->WriteAttributeString('remote_directory', json_encode(array_values($info)));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ remote_directory written (entries=' . count($info) . ')', 0);

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ remote_directory updated: ' . count($info) . ' remote(s)', 0);
    }

    /**
     * Returns true if given string is a valid IPv6 address.
     */
    private function IsIPv6(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }
        // Strip IPv6 zone id (e.g. fe80::1%eth0)
        $ipNoZone = explode('%', $ip, 2)[0];
        return filter_var($ipNoZone, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Returns true if the IP is IPv6 link-local (fe80::/10). Zone ids like "%8" are supported.
     */
    private function IsIPv6LinkLocal(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }
        $ipNoZone = strtolower(explode('%', $ip, 2)[0]);
        // Basic check for fe80::/10 (we treat any fe80: as link-local for our purposes)
        return str_starts_with($ipNoZone, 'fe80:') || str_starts_with($ipNoZone, 'fe80::');
    }

    /**
     * Try to resolve an IPv4 address for a given IPv6 address using the remote_directory attribute.
     * Returns empty string if no match is found.
     */
    private function LookupIPv4ForIPv6(string $ipv6): string
    {
        $ipv6 = trim($ipv6);
        if ($ipv6 === '') {
            return '';
        }
        $ipv6NoZone = strtolower(explode('%', $ipv6, 2)[0]);

        $dirRaw = (string)$this->ReadAttributeString('remote_directory');
        $dir = json_decode($dirRaw, true);
        if (!is_array($dir)) {
            $dir = [];
        }

        // Lazy fallback: if directory is still empty (e.g., first seconds after boot), try to populate it once via Discovery.
        if (count($dir) === 0) {
            $fallback = $this->GetRemoteDirectoryFromDiscovery();
            if (count($fallback) > 0) {
                $this->WriteAttributeString('remote_directory', json_encode(array_values($fallback)));
                $dir = $fallback;
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧭 remote_directory was empty → populated via Discovery fallback (entries=' . count($dir) . ')', 0);
            }
        }

        // If we only have a link-local IPv6 (fe80::) it often differs from the mDNS-advertised global/ULA IPv6.
        // In a single-remote setup we can safely fall back to that remote's IPv4.
        $ipv6IsLinkLocal = $this->IsIPv6LinkLocal($ipv6);
        if ($ipv6IsLinkLocal && count($dir) === 1) {
            $only = $dir[0];
            if (is_array($only)) {
                $only4 = trim((string)($only['host_ipv4'] ?? $only['hostIPv4'] ?? $only['ipv4'] ?? ''));
                if ($only4 !== '') {
                    return $only4;
                }
            }
        }

        foreach ($dir as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $e6 = (string)($entry['host_ipv6'] ?? $entry['hostIPv6'] ?? $entry['ipv6'] ?? '');
            $e4 = (string)($entry['host_ipv4'] ?? $entry['hostIPv4'] ?? $entry['ipv4'] ?? '');
            if ($e6 === '' || $e4 === '') {
                continue;
            }
            $e6NoZone = strtolower(explode('%', $e6, 2)[0]);
            if ($e6NoZone === $ipv6NoZone) {
                return $e4;
            }
        }

        return '';
    }

    /**
     * Resolve the best host to use for REST calls.
     * If the client connected via IPv6 and we have a matching IPv4 in remote_directory, return that IPv4.
     * Otherwise return the original client IP.
     */
    private function ResolveRemoteHostForRest(string $clientIP): string
    {
        $clientIP = trim($clientIP);
        if ($clientIP === '') {
            return '';
        }

        if ($this->IsIPv6($clientIP)) {
            $ipv4 = $this->LookupIPv4ForIPv6($clientIP);
            if ($ipv4 !== '') {
                return $ipv4;
            }

            // As a last resort, avoid using link-local IPv6 for REST calls.
            // If we cannot resolve IPv4, return empty so callers can decide what to do.
            if ($this->IsIPv6LinkLocal($clientIP)) {
                return '';
            }
        }

        return $clientIP;
    }

    /**
     * Returns true if user enabled manual host override AND a host value is present.
     */
    public function IsManualHostEnabled(): bool
    {
        return (bool)$this->ReadPropertyBoolean('use_manual_host')
            && trim((string)$this->ReadPropertyString('host')) !== '';
    }

    /**
     * Returns the effective host to use for REST/API calls.
     * Manual override (property host) wins, otherwise the stored discovered REST host.
     */
    public function GetEffectiveRemoteHost(): string
    {
        if ($this->IsManualHostEnabled()) {
            return trim((string)$this->ReadPropertyString('host'));
        }
        return trim((string)$this->ReadAttributeString('remote_host'));
    }

    public function GetStoredWebPassword(): string
    {
        return (string)$this->ReadAttributeString('remote_web_pin');
    }

    public function GetStoredApiKey(): string
    {
        return (string)$this->ReadAttributeString('api_key');
    }


    private function GetModuleLibraryVersion(): string
    {
        // module.php liegt in: <moduleRoot>/<ModuleName>/module.php
        // library.json liegt in: <moduleRoot>/library.json
        $libraryPath = __DIR__ . '/../library.json';

        if (!is_file($libraryPath)) {
            return self::Unfolded_Circle_Driver_Version; // Fallback
        }

        $raw = @file_get_contents($libraryPath);
        if ($raw === false) {
            return self::Unfolded_Circle_Driver_Version; // Fallback
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['version'])) {
            return self::Unfolded_Circle_Driver_Version; // Fallback
        }

        $v = trim((string)$json['version']);

        // Optional: "0.5" → "0.5.0" (SemVer-Alignment)
        if (preg_match('/^\d+\.\d+$/', $v)) {
            $v .= '.0';
        }

        return $v;
    }

    /**
     * Collect all variable IDs referenced by mapping properties.
     * @return int[]
     */
    private function CollectMappedVarIds(): array
    {
        $ids = [];

        $add = function ($id) use (&$ids) {
            $id = (int)$id;
            if ($id > 0 && IPS_VariableExists($id)) {
                $ids[$id] = true;
            }
        };

        // switch
        $switchMapping = json_decode($this->ReadPropertyString('switch_mapping'), true);
        if (is_array($switchMapping)) {
            foreach ($switchMapping as $e) {
                $add($e['var_id'] ?? 0);
            }
        }

        // sensor
        $sensorMapping = json_decode($this->ReadPropertyString('sensor_mapping'), true);
        if (is_array($sensorMapping)) {
            foreach ($sensorMapping as $e) {
                $add($e['var_id'] ?? 0);
            }
        }

        // light
        $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
        if (is_array($lightMapping)) {
            foreach ($lightMapping as $e) {
                $add($e['switch_var_id'] ?? 0);
                $add($e['brightness_var_id'] ?? 0);
                $add($e['color_var_id'] ?? 0);
                $add($e['color_temp_var_id'] ?? 0);
            }
        }

        // cover
        $coverMapping = json_decode($this->ReadPropertyString('cover_mapping'), true);
        if (is_array($coverMapping)) {
            foreach ($coverMapping as $e) {
                $add($e['position_var_id'] ?? 0);
                $add($e['control_var_id'] ?? 0);
            }
        }

        // climate
        $climateMapping = json_decode($this->ReadPropertyString('climate_mapping'), true);
        if (is_array($climateMapping)) {
            foreach ($climateMapping as $e) {
                $add($e['status_var_id'] ?? 0);
                $add($e['current_temp_var_id'] ?? 0);
                $add($e['target_temp_var_id'] ?? 0);
                $add($e['mode_var_id'] ?? 0);
            }
        }

        // media_player (features)
        $mediaMapping = json_decode($this->ReadPropertyString('media_player_mapping'), true);
        if (is_array($mediaMapping)) {
            foreach ($mediaMapping as $e) {
                if (!isset($e['features']) || !is_array($e['features'])) {
                    continue;
                }
                foreach ($e['features'] as $f) {
                    $add($f['var_id'] ?? 0);
                }
            }
        }

        // select
        $selectMapping = json_decode($this->ReadPropertyString('select_mapping'), true);
        if (is_array($selectMapping)) {
            foreach ($selectMapping as $e) {
                $add($e['var_id'] ?? 0);
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Register VM_UPDATE for all mapped variables and unregister obsolete registrations.
     */
    private function SyncVmUpdateRegistrations(): void
    {
        $newIds = $this->CollectMappedVarIds();
        sort($newIds);

        $oldIds = json_decode($this->ReadAttributeString('vm_update_vars'), true);
        if (!is_array($oldIds)) {
            $oldIds = [];
        }
        $oldIds = array_map('intval', $oldIds);
        sort($oldIds);

        $newSet = array_fill_keys($newIds, true);
        $oldSet = array_fill_keys($oldIds, true);

        // Unregister removed
        foreach ($oldIds as $id) {
            if (!isset($newSet[$id])) {
                $this->UnregisterMessage($id, VM_UPDATE);
            }
        }

        // Register new
        foreach ($newIds as $id) {
            if (!isset($oldSet[$id])) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }

        $this->WriteAttributeString('vm_update_vars', json_encode($newIds));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_VM, '📣 VM_UPDATE synced for ' . count($newIds) . ' variables', 0);
    }


    /**
     * Ensures that a token exists.
     * Generates a token only once (first-time instance setup) and never overwrites an existing token.
     */
    private function EnsureTokenInitialized(): void
    {
        $token = (string)$this->ReadAttributeString('token');
        if ($token !== '') {
            return;
        }

        $token = bin2hex(random_bytes(16)); // 32 characters hex string
        $this->WriteAttributeString('token', $token);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_AUTH, '🔑 Initial token generated: ' . $token, 0);

        // If the configuration form is open, reflect the value immediately.
        $this->UpdateFormField('token', 'value', $token);
    }

    /**
     * Mask token for logs (avoid leaking secrets).
     */
    private function MaskToken(?string $t): string
    {
        $t = (string)$t;
        if ($t === '') {
            return '(none)';
        }
        $len = strlen($t);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($t, 0, 4) . '…' . substr($t, -4) . " (len=$len)";
    }


    public function GetConfigurationForParent(): string
    {

        $Config = [
            // "Open"               => true,
            "Port" => 9988,
            "UseSSL" => false,
            "SilenceErrors" => false
        ];

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '🧩 WS configuration: ' . json_encode($Config), 0);
        return json_encode($Config);
    }

    public function PingDeviceState(): void
    {
        // If no Remote client is alive, avoid periodic spam.
        if (!$this->HasAliveClients()) {
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DEVICE, '🔄 PingDeviceState timer triggered', 0);
        $sessions = $this->getAllClientSessions();
        $whitelist = array_map('trim', array_column(json_decode($this->ReadPropertyString('ip_whitelist'), true), 'ip'));

        foreach ($sessions as $ip => $entry) {
            $isWhitelisted = in_array($ip, $whitelist);
            $isAuthenticated = !empty($entry['authenticated']);
            $hasPort = !empty($entry['port']);

            if (($isAuthenticated || $isWhitelisted) && $hasPort) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DEVICE, "🔁 Sending device_state ping to $ip:{$entry['port']} (auth: " . ($isAuthenticated ? '✅' : '❌') . ", whitelist: " . ($isWhitelisted ? '✅' : '❌') . ")", 0);
                $this->SendDeviceState(self::DEVICE_STATE_CONNECTED, $ip, (int)$entry['port']);
            } else {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DEVICE, "⏭️ Ping skipped for $ip (auth: " . ($isAuthenticated ? '✅' : '❌') . ", whitelist: " . ($isWhitelisted ? '✅' : '❌') . ", port: " . ($entry['port'] ?? '—') . ")", 0);
            }
        }
    }

    public function GetClientSessions(): string
    {
        // $this->WriteAttributeString('client_sessions', "");
        return $this->ReadAttributeString('client_sessions');
    }

    public function GetLoggedEventTypes(): string
    {
        // $this->WriteAttributeString('events', "");
        return $this->ReadAttributeString('events');
    }

    public function GetLoggedCommands(): string
    {
        // $this->WriteAttributeString('events', "");
        return $this->ReadAttributeString('log_commands');
    }

    public function UpdateAllEntityStates(): void
    {
        // If no Remote client is alive, avoid periodic spam.
        if (!$this->HasAliveClients()) {
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, '🔄 Starting periodic update of all entity states...', 0);

        $types = [
            'button' => 'button_mapping',
            'switch' => 'switch_mapping',
            'climate' => 'climate_mapping',
            'cover' => 'cover_mapping',
            'ir' => 'ir_mapping',
            'light' => 'light_mapping',
            'media' => 'media_player_mapping',
            'remote' => 'remote_mapping',
            'sensor' => 'sensor_mapping',
            'select' => 'select_mapping'
        ];

        foreach ($types as $type => $property) {
            $mapping = json_decode($this->ReadPropertyString($property), true);
            if (!is_array($mapping) || count($mapping) === 0) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "ℹ️ No entries for type '$type'.", 0);
                continue;
            }

            foreach ($mapping as $entry) {
                $attributes = [];

                switch ($type) {
                    case 'button':
                        $scriptId = $entry['script_id'] ?? null;
                        if (is_numeric($scriptId) && @IPS_ScriptExists($scriptId)) {
                            $attributes['state'] = 'AVAILABLE';
                            $this->SendEntityChange('button_' . $scriptId, 'button', $attributes);
                        }
                        break;

                    case 'switch':
                        $varId = $entry['var_id'] ?? null;
                        if (is_numeric($varId) && @IPS_VariableExists($varId)) {
                            $value = @GetValue($varId);
                            $attributes['state'] = $value ? 'ON' : 'OFF';
                            $this->SendEntityChange('switch_' . $entry['instance_id'], 'switch', $attributes);
                        }
                        break;

                    case 'climate':
                        $varId = $entry['status_var_id'] ?? null;
                        if (is_numeric($varId) && @IPS_VariableExists($varId)) {
                            $stateVar = @GetValue($varId);
                            // Normalize ON/OFF even if the status variable is not boolean (int/float/string/profile-association).
                            $attributes['state'] = $this->NormalizeOnOffState((int)$varId, $stateVar);

                            // hvac_mode logic from mode_var_id
                            if (!empty($entry['mode_var_id']) && @IPS_VariableExists($entry['mode_var_id'])) {
                                $modeRaw = @GetValue($entry['mode_var_id']);
                                $v = IPS_GetVariable($entry['mode_var_id']);
                                $profile = $v['VariableCustomProfile'] ?: $v['VariableProfile'];

                                $mode = 'OFF';
                                if (IPS_VariableProfileExists($profile)) {
                                    $profileData = IPS_GetVariableProfile($profile);
                                    foreach ($profileData['Associations'] as $assoc) {
                                        if ((int)$assoc['Value'] === (int)$modeRaw) {
                                            $label = strtoupper(trim($assoc['Name']));
                                            $modeMapping = [
                                                'OFF' => 'OFF',
                                                'HEAT' => 'HEAT',
                                                'COOL' => 'COOL',
                                                'AUTO' => 'AUTO',
                                                'FAN' => 'FAN',
                                                'HEAT_COOL' => 'HEAT_COOL',
                                                'HEIZEN' => 'HEAT',
                                                'KÜHLEN' => 'COOL',
                                                'LÜFTEN' => 'FAN',
                                                'HEIZEN/KÜHLEN' => 'HEAT_COOL',
                                                'AUTOMATIK' => 'AUTO',
                                                'AUS' => 'OFF'
                                            ];
                                            $mode = $modeMapping[$label] ?? 'OFF';
                                            break;
                                        }
                                    }
                                }
                                $attributes['hvac_mode'] = $mode;
                            }

                            if (!empty($entry['target_temp_var_id']) && @IPS_VariableExists($entry['target_temp_var_id'])) {
                                $attributes['target_temperature'] = @GetValue($entry['target_temp_var_id']);
                            }

                            if (!empty($entry['current_temp_var_id']) && @IPS_VariableExists($entry['current_temp_var_id'])) {
                                $attributes['current_temperature'] = @GetValue($entry['current_temp_var_id']);
                            }

                            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, '📤 Sending climate entity: ' . json_encode($attributes), 0);
                            $this->SendEntityChange('climate_' . $entry['instance_id'], 'climate', $attributes);
                        }
                        break;

                    case 'cover':
                        $positionVarId = $entry['position_var_id'] ?? null;
                        if (is_numeric($positionVarId) && @IPS_VariableExists((int)$positionVarId)) {
                            $symconPos = @GetValue((int)$positionVarId);
                            if (is_numeric($symconPos)) {
                                $posRemote = $this->ConvertCoverPositionToRemote((int)$positionVarId, $symconPos);
                                $attributes['position'] = (int)$posRemote;
                                // Periodic update is a snapshot. Do not guess OPENING/CLOSING here.
                                $attributes['state'] = ($posRemote <= 0) ? 'CLOSED' : 'OPEN';
                            } else {
                                $attributes['state'] = 'UNKNOWN';
                            }

                            $this->SendEntityChange('cover_' . $entry['instance_id'], 'cover', $attributes);
                        }
                        break;

                    case 'light':
                        $varId = $entry['switch_var_id'] ?? null;
                        if (is_numeric($varId) && @IPS_VariableExists($varId)) {
                            $value = @GetValue($varId);
                            $attributes['state'] = $value ? 'ON' : 'OFF';

                            if (!empty($entry['brightness_var_id']) && @IPS_VariableExists($entry['brightness_var_id'])) {
                                $attributes['brightness'] = $this->ConvertBrightnessToRemote($entry['brightness_var_id']);
                            }
                            if (!empty($entry['color_temp_var_id']) && @IPS_VariableExists($entry['color_temp_var_id'])) {
                                $ctVal = GetValue($entry['color_temp_var_id']);
                                $attributes['color_temperature'] = $this->ConvertColorTemperatureToRemote($entry['color_temp_var_id'], $ctVal);
                            }
                            if (!empty($entry['color_var_id']) && @IPS_VariableExists($entry['color_var_id'])) {
                                $rawColor = @GetValue($entry['color_var_id']);
                                $result = $this->ConvertHexColorToHueSaturation((int)$rawColor);
                                if (is_array($result)) {
                                    $attributes['hue'] = $result['hue'];
                                    $attributes['saturation'] = $result['saturation'];
                                }
                            }

                            $this->SendEntityChange('light_' . $entry['instance_id'], 'light', $attributes);
                        }
                        break;

                    case 'media':
                        if (!isset($entry['features']) || !is_array($entry['features'])) {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Invalid feature array for media player entry: " . json_encode($entry), 0);
                            continue 2;
                        }

                        $instanceId = (string)($entry['instance_id'] ?? '');
                        if ($instanceId === '') {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Missing instance_id for media entry: " . json_encode($entry), 0);
                            continue 2;
                        }

                        $entityId = 'media_player_' . $instanceId;
                        $attributes = $this->BuildMediaPlayerAttributesFromFeatures($entry);

                        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "🎵 Processing media player: $entityId | " . json_encode($attributes), 0);
                        $this->SendEntityChange($entityId, 'media_player', $attributes);
                        break;

                    case 'sensor':
                        $varId = $entry['var_id'] ?? null;
                        if (is_numeric($varId) && @IPS_VariableExists($varId)) {
                            $result = $this->GetSensorValueAndUnit($varId);
                            $attributes['value'] = $result['value'];
                            $attributes['unit'] = $result['unit'];
                            $attributes['state'] = 'ON';
                            $this->SendEntityChange('sensor_' . (int)$varId, 'sensor', $attributes);
                        }
                        break;

                    case 'select':
                        $varId = $entry['var_id'] ?? null;
                        if (is_numeric($varId) && @IPS_VariableExists((int)$varId)) {
                            $varId = (int)$varId;
                            $currentValue = @GetValue($varId);

                            $varInfo = @IPS_GetVariable($varId);
                            $profileName = '';
                            if (is_array($varInfo)) {
                                $profileName = trim((string)($varInfo['VariableCustomProfile'] ?? ''));
                                if ($profileName === '') {
                                    $profileName = trim((string)($varInfo['VariableProfile'] ?? ''));
                                }
                            }

                            $options = [];
                            $currentOption = '';

                            if ($profileName !== '' && @IPS_VariableProfileExists($profileName)) {
                                $profile = @IPS_GetVariableProfile($profileName);
                                $associations = $profile['Associations'] ?? [];

                                if (is_array($associations)) {
                                    foreach ($associations as $assoc) {
                                        if (!is_array($assoc)) {
                                            continue;
                                        }

                                        $label = trim((string)($assoc['Name'] ?? ''));
                                        if ($label === '') {
                                            $label = (string)($assoc['Value'] ?? '');
                                        }

                                        if ($label === '') {
                                            continue;
                                        }

                                        $options[] = $label;

                                        if ((string)($assoc['Value'] ?? '') === (string)$currentValue) {
                                            $currentOption = $label;
                                        }
                                    }
                                }
                            }

                            if ($currentOption === '') {
                                $currentOption = (string)$currentValue;
                            }

                            $attributes['options'] = array_values(array_unique($options));
                            $attributes['current_option'] = $currentOption;
                            $attributes['state'] = 'ON';

                            $entityId = 'select_' . $entry['instance_id'] . '_' . $varId;
                            $this->SendEntityChange($entityId, 'select', $attributes);
                        }
                        break;

                    default:
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unknown entity type: $type", 0);
                        continue 2;
                }
            }
        }
    }

    /**
     * Ermittelt den Wert und die Einheit eines Sensors inklusive Umrechnung bei bestimmten Profilen.
     *
     * @param int $varId
     * @return array ['value' => float|string, 'unit' => string]
     */
    private function GetSensorValueAndUnit(int $varId): array
    {
        // Default raw value
        $value = @GetValue($varId);
        $unit = '';

        $varInfo = @IPS_GetVariable($varId);
        $profile = '';

        if (is_array($varInfo)) {
            $profile = trim((string)($varInfo['VariableCustomProfile'] ?? ''));
            if ($profile === '') {
                $profile = trim((string)($varInfo['VariableProfile'] ?? ''));
            }
        }

        // Always obtain the formatted value – this respects both
        // classic VariableProfiles and the newer Symcon variable presentation.
        $formatted = (string)@GetValueFormatted($varId);

        // --- Case 1: Classic VariableProfile ---
        if ($profile !== '' && @IPS_VariableProfileExists($profile)) {
            $profileInfo = IPS_GetVariableProfile($profile);
            $unit = trim((string)($profileInfo['Suffix'] ?? ''));

            // Remove suffix from formatted value
            $value = $this->StripProfileSuffixFromFormattedValue($formatted, $unit);

            return ['value' => $value, 'unit' => $unit];
        }

        // --- Case 2: New Symcon variable presentation (no profile) ---
        // Try to extract a unit from the formatted value

        $formattedTrim = trim($formatted);

        // Regex: split numeric value and trailing unit
        if (preg_match('/^([-+]?\d+[\d.,]*)\s*(.*)$/u', $formattedTrim, $m)) {
            $value = $m[1];
            $unit = trim($m[2]);

            // Only accept unit if it actually contains letters or symbols
            if ($unit === '' || preg_match('/^[\d.,]+$/', $unit)) {
                $unit = '';
            }
        } else {
            $value = $formattedTrim;
        }

        return ['value' => $value, 'unit' => $unit];
    }

    /**
     * Strip a profile suffix (unit) from the formatted value returned by GetValueFormatted().
     * This keeps the value formatting (digits, decimal separator) but avoids duplicating the unit,
     * because Remote 3 gets the unit separately.
     */
    private function StripProfileSuffixFromFormattedValue(string $formatted, string $unit): string
    {
        $formatted = trim($formatted);
        $unit = (string)$unit;

        if ($formatted === '' || trim($unit) === '') {
            return $formatted;
        }

        // Try exact match at the end (with and without a space).
        $uTrim = trim($unit);

        // Normalize NBSP (some frontends use it between value and suffix)
        $f = str_replace("\xC2\xA0", ' ', $formatted);

        // Case 1: ends with unit as-is
        if (str_ends_with($f, $unit)) {
            return trim(substr($f, 0, -strlen($unit)));
        }

        // Case 2: ends with trimmed unit
        if (str_ends_with($f, $uTrim)) {
            return trim(substr($f, 0, -strlen($uTrim)));
        }

        // Case 3: ends with space + trimmed unit
        if (str_ends_with($f, ' ' . $uTrim)) {
            return trim(substr($f, 0, -strlen(' ' . $uTrim)));
        }

        return $formatted;
    }

    /**
     * Normalize a Symcon variable value to UC ON/OFF.
     * Supports bool, numeric, string and (best-effort) profile associations.
     */
    private function NormalizeOnOffState(int $varId, $rawValue): string
    {
        if (is_bool($rawValue)) {
            return $rawValue ? 'ON' : 'OFF';
        }

        if (is_int($rawValue) || is_float($rawValue) || (is_string($rawValue) && is_numeric($rawValue))) {
            return ((float)$rawValue) > 0 ? 'ON' : 'OFF';
        }

        if (is_string($rawValue)) {
            $v = strtolower(trim($rawValue));
            $onHints = ['on', 'an', 'ein', 'true', 'yes'];
            $offHints = ['off', 'aus', 'false', 'no'];
            if (in_array($v, $onHints, true)) {
                return 'ON';
            }
            if (in_array($v, $offHints, true)) {
                return 'OFF';
            }
        }

        // Best-effort: interpret associations from the (custom) variable profile
        if (@IPS_VariableExists($varId)) {
            $vInfo = @IPS_GetVariable($varId);
            if (is_array($vInfo)) {
                $profile = trim((string)($vInfo['VariableCustomProfile'] ?? ''));
                if ($profile === '') {
                    $profile = trim((string)($vInfo['VariableProfile'] ?? ''));
                }

                if ($profile !== '' && @IPS_VariableProfileExists($profile)) {
                    $p = IPS_GetVariableProfile($profile);
                    $assocs = $p['Associations'] ?? [];
                    if (is_array($assocs)) {
                        foreach ($assocs as $a) {
                            if (!is_array($a) || !isset($a['Value'], $a['Name'])) {
                                continue;
                            }
                            if ((string)$a['Value'] !== (string)$rawValue) {
                                continue;
                            }
                            $label = strtolower(trim((string)$a['Name']));
                            $onLabelHints = ['on', 'an', 'ein', 'active', 'aktiv'];
                            $offLabelHints = ['off', 'aus', 'inactive', 'inaktiv'];
                            foreach ($onLabelHints as $h) {
                                if (str_contains($label, $h)) {
                                    return 'ON';
                                }
                            }
                            foreach ($offLabelHints as $h) {
                                if (str_contains($label, $h)) {
                                    return 'OFF';
                                }
                            }
                        }
                    }
                }
            }
        }

        return 'OFF';
    }

    private function Send(string $Text): void
    {
        $this->SendDataToChildren(json_encode(['DataID' => '{34A21C2C-646B-1014-D032-DF7E7A88B419}', 'Buffer' => $Text]));
    }

    public function ForwardData(string $JSONString): string
    {
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_API, '📥 Incoming data: ' . $JSONString, 0);

        $data = json_decode($JSONString, true);

        // Prüfen, ob ein Buffer existiert
        if (!isset($data['Buffer'])) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_API, '❌ Error: Buffer missing!', 0);
            return json_encode(['error' => 'Buffer fehlt']);
        }

        $buffer = is_string($data['Buffer']) ? json_decode($data['Buffer'], true) : $data['Buffer'];

        // Prüfen, ob "method" vorhanden ist
        if (!isset($buffer['method'])) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_API, '❌ Error: Buffer does not contain a "method" field!', 0);
            return json_encode(['error' => 'method fehlt im Buffer']);
        }

        $method = $buffer['method'];
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_API, "➡️ Processing method: $method", 0);

        switch ($method) {
            case 'CallGetVersion':
                // return $this->CallGetVersion();
            default:
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_API, "⚠️ Unknown method: $method", 0);
                return json_encode(['error' => 'Unbekannter Fehler']);
        }
    }

    private function SendDataWebsocket($payload, string $ClientIP, int $ClientPort): void
    {
        // IPSModuleStrict: Binary data may be transported as HEX strings between instances.
        // Server Socket supports this and will send the decoded bytes on the wire.
        // We therefore ensure the JSON we send to the parent is always valid UTF-8.

        if (!is_string($payload)) {
            $payload = (string)$payload;
        }

        // If payload contains non-UTF8 bytes (typical for WebSocket frames), encode as HEX.
        // This mirrors what we already do in ReceiveData() for incoming buffers.
        $sendBuffer = $payload;
        $isHex = false;

        // Fast path: ASCII / UTF-8 text (handshake HTTP headers etc.)
        if ($sendBuffer !== '' && !mb_check_encoding($sendBuffer, 'UTF-8')) {
            $sendBuffer = bin2hex($sendBuffer);
            $isHex = true;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, sprintf('📤 SendDataWebsocket → %s buffer to %s:%d (len=%d)', $isHex ? 'HEX' : 'TEXT', $ClientIP, $ClientPort, strlen($sendBuffer)), 0);


        $this->SendDataToParent(json_encode([
            'DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}',
            'ClientIP' => $ClientIP,
            'ClientPort' => $ClientPort,
            'Type' => self::Socket_Data,
            'Buffer' => $sendBuffer,
            // Hint for our own debugging; harmless for the parent.
            'BufferIsHex' => $isHex
        ]));
    }

    public function ReceiveData(string $JSONString): string
    {
        // Always show at least a small trace that something arrived
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📥 Incoming (raw length): ' . strlen($JSONString), 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📥 Raw Data: ' . $JSONString, 0);

        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_WS, '❌ JSON decode failed: ' . json_last_error_msg(), 0);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📥 Original JSON string: ' . $JSONString, 0);
            return '';
        }

        $clientIP = (string)($data['ClientIP'] ?? $data['ClientIp'] ?? '');
        $clientPort = (int)($data['ClientPort'] ?? $data['ClientPORT'] ?? 0);
        $type = (int)($data['Type'] ?? -1);

        // --- REST host resolution (IPv6 -> IPv4 fallback via mDNS directory) ---
        $clientIPRest = $this->ResolveRemoteHostForRest($clientIP);
        $manualOverride = $this->IsManualHostEnabled();
        if ($clientIPRest !== '' && $clientIPRest !== $clientIP) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT,
                '🌐 REST host resolved: client_ip=' . $clientIP . ' → rest_host=' . $clientIPRest, 0);
        }

        // Keep a best-effort REST host available for later REST calls (used during setup flow)
        // Overwrite remote_host if it is empty or contains a link-local IPv6 (unusable for REST).
        if (!$manualOverride) {
            $storedRemoteHost = trim((string)$this->ReadAttributeString('remote_host'));
            $storedIsBad = ($storedRemoteHost !== '' && $this->IsIPv6LinkLocal($storedRemoteHost));
            if (($storedRemoteHost === '' || $storedIsBad) && $clientIPRest !== '') {
                if ($storedIsBad) {
                    $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🔁 remote_host was link-local IPv6, replacing with REST host: ' . $clientIPRest, 0);
                }
                $this->WriteAttributeString('remote_host', $clientIPRest);
            }
        }

        if (!isset($data['Buffer'])) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_WS, '❌ Missing Buffer in incoming data.', 0);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📥 Incoming object: ' . json_encode($data), 0);
            return '';
        }

        // Buffer may be plain bytes, plain text, or HEX-encoded (IPSModuleStrict / socket variants)
        $buffer = (string)$data['Buffer'];

        // If Buffer looks like HEX (even length + only hex chars), decode it
        if ($buffer !== '' && (strlen($buffer) % 2 === 0) && ctype_xdigit($buffer)) {
            $decoded = @hex2bin($buffer);
            if ($decoded !== false) {
                $buffer = $decoded;
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '🔁 Buffer was HEX → decoded to bytes (len=' . strlen($buffer) . ')', 0);
            }
        }

        // For string operations (headers), keep raw 1-byte string
        $payload = $buffer;

        // Minimal debug (visible without extended_debug)
        $typeLabel = match ($type) {
            self::Socket_Data => 'Data',
            self::Socket_Connected => 'Connected',
            self::Socket_Disconnected => 'Disconnected',
            default => 'Unknown(' . $type . ')'
        };
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "📡 Socket Type: {$typeLabel} | From: {$clientIP}:{$clientPort} | PayloadLen: " . strlen($payload), 0);

        // Token aus Header extrahieren (nur bis Zeilenende)
        $token = null;
        if (preg_match('/\bauth-token\s*:\s*([^\r\n]+)/i', $payload, $matches)) {
            $token = trim((string)$matches[1]);

            $storedToken = (string)$this->ReadAttributeString('token');
            $hasStored = ($storedToken !== '');
            $match = ($token !== '' && $hasStored && hash_equals($storedToken, $token));

            $this->Debug(
                __FUNCTION__,
                $match ? self::LV_INFO : self::LV_WARN,
                self::TOPIC_AUTH,
                '🔑 Auth token extracted from header: remote=' . $this->MaskToken($token) . ' local=' . $this->MaskToken($storedToken) . ' match=' . ($match ? '✅' : '❌') . ($hasStored ? '' : ' (local token missing)'),
                0
            );

            // Direkt nach Header-Token-Erkennung authentifizieren (nur bei Match)
            if ($match) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_AUTH, '✅ Header token matches → marking client authenticated', 0);
                $this->authenticateClient($clientIP, $clientPort, $token);
            } else {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_AUTH, '⛔ Header token missing/mismatch → client NOT authenticated (commands should be blocked later)', 0);
            }
        }

        // Fallback: Token aus JSON extrahieren (nur wenn Payload bereits gültiges UTF-8 ist)
        if ($token === null) {
            $payloadJson = null;
            if ($payload !== '' && mb_check_encoding($payload, 'UTF-8')) {
                $payloadJson = json_decode($payload, true);
            }
            if (is_array($payloadJson) && isset($payloadJson['auth-token'])) {
                $token = (string)$payloadJson['auth-token'];
                $storedToken = (string)$this->ReadAttributeString('token');
                $hasStored = ($storedToken !== '');
                $match = ($token !== '' && $hasStored && hash_equals($storedToken, $token));
                $this->Debug(
                    __FUNCTION__,
                    $match ? self::LV_INFO : self::LV_WARN,
                    self::TOPIC_AUTH,
                    '🔑 Auth token extracted from JSON message: remote=' . $this->MaskToken($token) . ' local=' . $this->MaskToken($storedToken) . ' match=' . ($match ? '✅' : '❌') . ($hasStored ? '' : ' (local token missing)'),
                    0
                );

                if ($match) {
                    $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_AUTH, '✅ JSON token matches → marking client authenticated', 0);
                    $this->authenticateClient($clientIP, $clientPort, $token);
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_AUTH, '⛔ JSON token missing/mismatch → client NOT authenticated (commands should be blocked later)', 0);
                }
            }
        }

        // Client direkt nach Empfang registrieren (track by IP and update port/last_seen)
        $this->addOrUpdateClientSession($clientIP, $clientPort);

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '✅ Payload length: ' . strlen($payload), 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '✅ Client: ' . $clientIP . ' | Port: ' . $clientPort, 0);
        // $this->SendDebug(__FUNCTION__, print_r($_SERVER, true), 0);

        switch ($type) {
            case self::Socket_Data: // Data
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "🟢 WebSocket Type: Data", 0);
                break;
            case self::Socket_Connected: // Connected
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_WS, "🟢 WebSocket Type: Connected", 0);
                break;
            case self::Socket_Disconnected: // Disconnected
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_WS, "🟠 WebSocket Type: Disconnected", 0);
                break;
            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_WS, "⚠️ WebSocket Type: Unknown ($type)", 0);
                break;
        }

        // Prüfen, ob es sich um ein WebSocket-Upgrade handelt
        if ($this->PerformWebSocketHandshake($payload, $clientIP, $clientPort)) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_WS, '✅ Handshake detected and performed → abort processing', 0);
            return '';
        }

        // WebSocket Payload extrahieren und verarbeiten
        $unpacked = WebSocketUtils::UnpackData($payload, function ($msg, $data) {
            $this->Debug((string)$msg, self::LV_TRACE, self::TOPIC_WS, $data, 0);
        });
        if ($unpacked === null) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_WS, '❌ UnpackData() returned null', 0);
            return '';
        }

        if ($unpacked['opcode'] === 0x9) {
            $now = date('Y-m-d H:i:s');
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "🔁 [$now] PING received from $clientIP:$clientPort", 0);
            $pong = WebSocketUtils::PackPong();
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "📤 [$now] Sende echten PONG-Frame an $clientIP:$clientPort", 0);
            $this->PushPongToRemoteClient($pong, $clientIP, $clientPort);
            return '';
        }

        // Einzelne Debug-Ausgaben für jedes entpackte Feld
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 FIN: ' . var_export($unpacked['fin'], true), 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 Opcode: ' . $unpacked['opcode'], 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 Opcode Name: ' . $unpacked['opcode_name'], 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 Raw Length: ' . $unpacked['length'], 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 Raw Frame (hex): ' . bin2hex($unpacked['raw']), 0);
        // WebSocket payload is bytes; JSON must be UTF-8. Do not re-encode raw bytes.
        $jsonText = (string)$unpacked['payload'];
        if ($jsonText !== '' && !mb_check_encoding($jsonText, 'UTF-8')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_WS, '❌ Payload is not valid UTF-8 – skipping JSON decode (len=' . strlen($jsonText) . ')', 0);
            return '';
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📦 Demaskierter Payload (Klartext): ' . $jsonText, 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '✅ Frame wurde erfolgreich entpackt', 0);

        $json = json_decode($jsonText, true);
        if (!is_array($json)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_WS, '❌ Invalid JSON payload in frame', 0);
            return '';
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, '📥 Unpacked frame: ' . json_encode($json), 0);

        // --- ADDED LOGIC FOR "kind" inspection and event handling ---
        $kind = $json['kind'] ?? '';
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "🧩 Kind: $kind", 0);

        if ($kind === 'event') {
            $this->HandleEventMessage($json, $clientIP, $clientPort);
        }
        // --- END ADDED LOGIC ---

        $msg = $json['msg'] ?? '';
        $reqId = $json['id'] ?? 0;
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_WS, "🧩 Message: $msg", 0);
        switch ($msg) {
            case 'authentication':
                $token = $json['msg_data']['token'] ?? null;
                $this->authenticateClient($clientIP, $clientPort, $token);
                break;

            case 'setup_driver':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🛠️ setup_driver received → starting interactive setup flow', 0);
                $this->SendResultOK($reqId, $clientIP, $clientPort);
                // Remember which Remote connected (needed for REST calls without Discovery/Core Manager)
                // Use IPv4 fallback for REST if the remote connected via IPv6 and we have a matching IPv4 from mDNS.
                if (!$this->IsManualHostEnabled()) {
                    $restHost = $this->ResolveRemoteHostForRest($clientIP);
                    if ($restHost !== '') {
                        $this->WriteAttributeString('remote_host', $restHost);
                    }
                }
                $this->StartDriverSetupFlow($clientIP, $clientPort);
                break;

            case 'set_driver_user_data':
                $this->HandleSetDriverUserData($json, $reqId, $clientIP, $clientPort);
                break;

            case 'abort_driver_setup':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🛑 Remote aborted setup', 0);

                // If this arrived as a request, acknowledge it
                if (($kind ?? '') === 'req' && ($reqId ?? 0) > 0) {
                    $this->SendResultOK($reqId, $clientIP, $clientPort);
                }
                break;

            case 'connect':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_WS, '🔌 Connect received – sending device_state CONNECTED', 0);
                $this->SendDeviceState(self::DEVICE_STATE_CONNECTED, $clientIP, $clientPort);
                break;

            case 'get_device_state':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DEVICE, '📡 get_device_state received → sending device_state', 0);

                // Use ClientSessionTrait helpers for session/auth/whitelist detection
                $sessions = $this->getAllClientSessions();

                $whitelistRaw = json_decode($this->ReadPropertyString('ip_whitelist'), true);
                $whitelist = is_array($whitelistRaw) ? array_map('trim', array_column($whitelistRaw, 'ip')) : [];
                $isWhitelisted = in_array($clientIP, $whitelist, true);

                $isAuthenticated = $this->isClientAuthenticated($clientIP);
                $hasSessionPort = !empty($sessions[$clientIP]['port']);

                // UC allowed states: CONNECTED, CONNECTING, DISCONNECTED, ERROR
                if (($isAuthenticated || $isWhitelisted) && $hasSessionPort) {
                    $state = 'CONNECTED';
                } else {
                    // We are talking to a client, but not yet authenticated/whitelisted.
                    $state = 'CONNECTING';
                }
                $this->SendDeviceState($state, $clientIP, $clientPort);

                // optional: some clients like an OK response too (harmless)
                if (($kind ?? '') === 'req' && ($reqId ?? 0) > 0) {
                    $this->SendResultOK($reqId, $clientIP, $clientPort);
                }
                break;

            case 'entity_command':
                $msg_data = $json['msg_data'] ?? [];
                // Log incoming command before handling it
                $this->LogIncomingCommand($msg_data, $json);
                $this->HandleEntityCommand($msg_data, $clientIP, $clientPort, $reqId);
                break;

            case 'get_driver_metadata':
                $this->SendDriverMetadata($clientIP, $clientPort, $reqId);
                break;

            case 'get_driver_version':
                $this->SendDriverVersion($clientIP, $clientPort, $reqId);
                break;

            case 'get_available_entities':
                $this->SendAvailableEntities($clientIP, $clientPort, $reqId);
                break;

            case 'get_entity_states':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_IO, '📥 get_entity_states msg_data: ' . json_encode($json['msg_data'] ?? new stdClass()), 0);
                $this->SendEntityStates($clientIP, $clientPort, $reqId);
                break;

            case 'subscribe_events':
                $this->subscribeClientToEvents($clientIP, $clientPort);
                $this->SendResultOK($reqId, $clientIP, $clientPort);
                break;

            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_WS, '⚠️ Unknown request: ' . $msg, 0);
                break;
        }
        return '';
    }


    /**
     * Logs unique incoming commands for debugging/audit purposes.
     *
     * @param array $msgData The 'msg_data' array from the incoming message.
     * @param array $fullMessage The full decoded message as array.
     */
    private function LogIncomingCommand(array $msgData, array $fullMessage): void
    {
        $logged = json_decode($this->ReadAttributeString('log_commands'), true);
        if (!is_array($logged)) {
            $logged = [];
        }

        $cmdID = $msgData['cmd_id'] ?? 'undefined';
        $params = $msgData['params'] ?? [];

        // Schlüsselformat für Prüfung
        $key = $cmdID . '|' . json_encode($params);

        if (!array_key_exists($key, $logged)) {
            $logged[$key] = [
                'cmd_id' => $cmdID,
                'params' => $params,
                'full_msg' => $fullMessage
            ];
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "🆕 Neuer Befehl geloggt: $key", 0);
            $this->WriteAttributeString('log_commands', json_encode($logged));
        } else {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "ℹ️ Bereits geloggt: $key", 0);
        }
    }

    /**
     * Handles incoming event messages from the Remote.
     * @param array $json
     * @param string $ip
     * @param int $port
     */
    private function HandleEventMessage(array $json, string $ip, int $port): void
    {
        $msg = $json['msg'] ?? '';
        // --- BEGIN log unique event types ---
        $loggedEvents = json_decode($this->ReadAttributeString('events'), true);
        if (!is_array($loggedEvents)) {
            $loggedEvents = [];
        }
        if (!in_array($msg, $loggedEvents)) {
            $loggedEvents[] = $msg;
            $this->WriteAttributeString('events', json_encode($loggedEvents));
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "📝 Neuer Event-Typ geloggt: $msg", 0);
        }
        // --- END log unique event types ---
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "📩 Empfangener Event: $msg von $ip:$port", 0);
        $instanceID = $this->FindDeviceInstanceByIp('{5894A8B3-7E60-981A-B3BA-6647335B57E4}', 'host', $ip);

        switch ($msg) {
            case 'enter_standby':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "🛌 Remote $ip ist in Standby gegangen", 0);
                if ($instanceID > 0) {
                    UCR_ReceiveDriverEvent($instanceID, $json);
                }
                break;

            case 'connect':
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "🔌 Remote $ip ist wieder aktiv → sende CONNECTED", 0);
                $this->SendDeviceState(self::DEVICE_STATE_CONNECTED, $ip, $port);
                $this->UpdateAllEntityStates();
                if ($instanceID > 0) {
                    UCR_ReceiveDriverEvent($instanceID, $json);
                }
                break;

            case 'button_pressed':
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🟢 Button gedrückt (noch nicht ausgewertet)", 0);
                if ($instanceID > 0) {
                    UCR_ReceiveDriverEvent($instanceID, $json);
                }
                break;

            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unbekannter Event-Typ: $msg", 0);
                break;
        }
    }

    /**
     * Findet eine Geräte-Instanz anhand GUID, Property und IP-Adresse.
     *
     * @param string $guid
     * @param string $property
     * @param string $ip
     * @return int InstanceID oder 0 wenn nicht gefunden
     */
    private function FindDeviceInstanceByIp(string $guid, string $property, string $ip): int
    {
        $instanceIDs = IPS_GetInstanceListByModuleID($guid);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔍 Searching instances for GUID $guid: " . json_encode($instanceIDs), 0);

        foreach ($instanceIDs as $id) {
            $prop = @IPS_GetProperty($id, $property);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔎 Checking instance $id: $property = $prop", 0);

            if ($prop === $ip) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_ENTITY, "🎯 Found instance for IP $ip: $id", 0);
                return $id;
            }
        }

        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ No matching instance found for IP $ip", 0);
        return 0;
    }


    /**
     * Sendet den aktuellen Gerätestatus an den Remote-Client.
     *
     * @param string $state
     * @param string $clientIP
     * @param int $clientPort
     */
    private function SendDeviceState(string $state, string $clientIP, int $clientPort): void
    {
        $response = [
            'kind' => 'event',
            'msg' => 'device_state',
            'msg_data' => [
                'state' => $state
            ],
            'cat' => 'DEVICE'
        ];
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }


    /**
     * Leitet maskierten Payload an den eigenen Webhook-Endpunkt intern weiter
     *
     * @param string $payload
     */
    private function ForwardToWebhook(string $payload): void
    {
        $token = $this->ReadAttributeString('token');
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Auth-Token: $token\r\n",
                'content' => $payload
            ]
        ]);

        $url = 'http://127.0.0.1:3777/hook/unfoldedcircle';
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_HOOK, '🌐 Sending fallback request to webhook: ' . $url, 0);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_HOOK, '❌ Forwarding failed – no response from webhook', 0);
        } else {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '✅ Webhook response: ' . $result, 0);
        }
    }


    /**
     * Extracts and performs the WebSocket handshake process.
     *
     * @param string $payload
     * @param string $clientIP
     * @param int $clientPort
     * @return bool True if handshake was performed, false otherwise.
     */
    private function PerformWebSocketHandshake(string $payload, string $clientIP, int $clientPort): bool
    {
        if (!str_starts_with($payload, 'GET /')) {
            return false;
        }

        if (!preg_match('/Sec-WebSocket-Key: (.*)/i', $payload, $matches)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_WS, '❌ No valid Sec-WebSocket-Key found', 0);
            return false;
        }

        $key = trim($matches[1]);
        $magicGUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $raw = sha1($key . $magicGUID, true);
        $accept = base64_encode($raw);

        $upgradeResponse = "HTTP/1.1 101 Switching Protocols\r\n";
        $upgradeResponse .= "Upgrade: websocket\r\n";
        $upgradeResponse .= "Connection: Upgrade\r\n";
        $upgradeResponse .= "Sec-WebSocket-Accept: $accept\r\n\r\n";

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_WS, "🔁 Sending WebSocket handshake response to $clientIP:$clientPort", 0);
        $this->PushRawToRemoteClient($upgradeResponse, $clientIP, $clientPort);
        IPS_Sleep(50); // Mini-Delay für Stabilität

        // 🔐 Authentifizierungsantwort (wie beim Node-Treiber)
        $authMessage = [
            'kind' => 'resp',
            'req_id' => 0,
            'code' => 200,
            'msg' => 'authentication',
            'msg_data' => new stdClass()
        ];
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_AUTH, "🔁 Sending authentication response to $clientIP:$clientPort", 0);
        $this->PushToRemoteClient($authMessage, $clientIP, $clientPort);

        // Optional (kann auch später durch Anfrage erfolgen)
        // $this->SendDriverMetadata($clientIP, $clientPort);
        return true;
    }

    public function SetUseComplexSetup(bool $enabled): void
    {
        $this->WriteAttributeBoolean('use_complex_setup', $enabled);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, '🧩 Setup flow set: ' . ($enabled ? 'COMPLEX' : 'SIMPLE'), 0);
    }

    public function GetUseComplexSetup(): bool
    {
        return (bool)$this->ReadAttributeBoolean('use_complex_setup');
    }

    private function HandleSetDriverUserData(array $json, int $reqId, string $clientIP, int $clientPort): void
    {
        $useComplex = (bool)$this->ReadAttributeBoolean('use_complex_setup');
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, '🧩 Dispatch setup flow: ' . ($useComplex ? 'COMPLEX' : 'SIMPLE'), 0);

        if ($useComplex) {
            $this->HandleSetDriverUserData_Complex($json, $reqId, $clientIP, $clientPort);
        } else {
            $this->HandleSetDriverUserData_Simple($json, $reqId, $clientIP, $clientPort);
        }
    }

    private function GetPrimaryMacAddress(): string
    {
        $nics = @Sys_GetNetworkInfo();
        if (!is_array($nics)) {
            return '';
        }

        $candidates = [];

        foreach ($nics as $nic) {
            $desc = strtolower((string)($nic['Description'] ?? ''));
            $mac = strtoupper(trim((string)($nic['MAC'] ?? '')));
            $ip = trim((string)($nic['IP'] ?? ''));

            if ($mac === '' || $ip === '' || $ip === '127.0.0.1') {
                continue;
            }

            // Filter obvious virtual adapters
            $virtualHints = ['vmware', 'virtual', 'hyper-v', 'vbox', 'loopback', 'tap', 'tunnel', 'pseudo'];
            $isVirtual = false;
            foreach ($virtualHints as $h) {
                if (strpos($desc, $h) !== false) {
                    $isVirtual = true;
                    break;
                }
            }
            if ($isVirtual) {
                continue;
            }

            $idx = (int)($nic['InterfaceIndex'] ?? 999999);
            $candidates[] = ['idx' => $idx, 'mac' => $mac, 'ip' => $ip, 'desc' => $desc];
        }

        if (empty($candidates)) {
            // fallback: take first NIC with a MAC (even if virtual)
            foreach ($nics as $nic) {
                $mac = strtoupper(trim((string)($nic['MAC'] ?? '')));
                if ($mac !== '') {
                    return $mac;
                }
            }
            return '';
        }

        // deterministic: lowest InterfaceIndex wins
        usort($candidates, fn($a, $b) => $a['idx'] <=> $b['idx']);
        return $candidates[0]['mac'];
    }

    private function GetStableSystemId(): string
    {
        $licensee = strtolower(trim((string)@IPS_GetLicensee()));
        $instanceId = (string)$this->InstanceID;

        // Deterministic seed per Symcon installation + Integration Driver instance
        $seed = $licensee . '|' . $instanceId;

        // Privacy-friendly stable id
        return substr(hash('sha256', $seed), 0, 16);
    }

    public function GetDriverId(): string
    {
        return 'symcon_' . $this->GetStableSystemId();
    }

    private function SendDriverMetadata(string $clientIP, int $clientPort, int $reqId): void
    {
        $response = [
            'kind' => 'resp',
            'req_id' => $reqId,
            'code' => 200,
            'msg' => 'driver_metadata',
            'msg_data' => $this->GetDriverMetadataCommon(),
        ];
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    private function HandleSetDriverUserData_Simple(array $json, int $reqId, string $clientIP, int $clientPort): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📥 set_driver_user_data received (SIMPLE flow)', 0);

        // Always acknowledge the request
        $this->SendResultOK($reqId, $clientIP, $clientPort);

        // Parse input values
        $inputValues = $json['msg_data']['input_values'] ?? [];
        if (isset($inputValues['pin'])) {
            $pin = trim((string)$inputValues['pin']);

            // DEBUG: Log received PIN from Remote
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔐 Received PIN from Remote: "' . $pin . '" (len=' . strlen($pin) . ')', 0);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_SETUP, '🔐 Raw input_values: ' . json_encode($inputValues), 0);

            if ($pin === '') {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '⚠️ No PIN provided → requesting PIN again', 0);
                $this->StartDriverSetupFlow($clientIP, $clientPort);
                return;
            }

            // Store PIN locally (attribute) and configure properties for UcrApiHelper
            $this->WriteAttributeString('web_config_pass', $pin);
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '💾 Stored Remote PIN in attribute web_config_pass', 0);

            // Determine remote host (fallback to client IP via REST resolver)
            $remoteHost = $this->GetEffectiveRemoteHost();

            // IMMER neu resolven (auch wenn remote_host schon gesetzt ist)
            // bevorzugt IPv4 via remote_directory
            $candidate = $remoteHost !== '' ? $remoteHost : $clientIP;
            $resolved = $this->ResolveRemoteHostForRest($candidate);

            if ($resolved !== '') {
                $remoteHost = $resolved;
                $this->WriteAttributeString('remote_host', $remoteHost);
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🌐 Setup REST host selected: ' . $remoteHost . ' (candidate=' . $candidate . ')', 0);

            // Use shared helper to validate/create API key
            // ... nachdem PIN gespeichert wurde:

            $apiKey = trim((string)$this->GetApiKey());
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔎 GetApiKey() returned: "' . $apiKey . '"', 0);

            if ($apiKey === '') {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '❌ UcrApiHelper failed to obtain API key → requesting PIN again', 0);
                // PIN Seite erneut anzeigen
                $this->SendResultOK($reqId, $clientIP, $clientPort);
                $this->StartDriverSetupFlow($clientIP, $clientPort);
                return;
            }

            // ✅ API-Key vorhanden -> Token automatisch setzen
            $tokenStored = trim((string)$this->ReadAttributeString('token'));
            $remoteHost = $this->GetEffectiveRemoteHost();
            if ($remoteHost === '') {
                $remoteHost = $this->ResolveRemoteHostForRest($clientIP);
                if ($remoteHost !== '') {
                    $this->WriteAttributeString('remote_host', $remoteHost);
                }
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔑 API key OK → registering external token on Remote', 0);
            $reg = $this->RemoteUpdateIntegrationDriverToken($remoteHost, $apiKey, $tokenStored);
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📌 RemoteUpdateIntegrationDriverToken result: ' . json_encode($reg), 0);

            // Immer ACK schicken, sonst wartet die Remote ggf.
            $this->SendResultOK($reqId, $clientIP, $clientPort);

            if (($reg['ok'] ?? false) === true) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ External token registered → finishing setup', 0);
                $this->FinishDriverSetupOK($clientIP, $clientPort);
                return;
            }

            // Token setzen fehlgeschlagen -> Flow neu starten (zeigt Status/Fehler-Seite aus StartDriverSetupFlow)
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '❌ External token registration failed → restarting setup flow', 0);
            $this->StartDriverSetupFlow($clientIP, $clientPort);
            return;
        }
        $tokenUser = (string)($inputValues['token'] ?? '');
        $tokenStored = (string)$this->ReadAttributeString('token');

        if ($tokenUser === '') {
            // If nothing provided, still allow user to continue by showing the token page.
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '⚠️ No token provided → requesting input again', 0);
            $this->RequestTokenAgain($clientIP, $clientPort,
                'Bitte trage den Token ein oder bestätige den vorausgefüllten Token.',
                'Please enter the token or confirm the prefilled token.'
            );
            return;
        }

        if ($tokenUser !== $tokenStored) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '❌ Token mismatch from user input', 0);
            $this->RequestTokenAgain($clientIP, $clientPort,
                'Der eingegebene Token stimmt nicht mit dem Symcon-Token überein. Bitte erneut prüfen.',
                'The entered token does not match the Symcon token. Please verify and try again.'
            );
            return;
        }

        // Token accepted. Try to push/register the token to the Remote via REST so the Remote marks it as configured.
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Token accepted → attempting REST registration with token', 0);

        $remoteHost = $this->GetEffectiveRemoteHost();
        $candidate = $remoteHost !== '' ? $remoteHost : $clientIP;
        $resolved = $this->ResolveRemoteHostForRest($candidate);
        if ($resolved !== '') {
            $remoteHost = $resolved;
            if (!$this->IsManualHostEnabled()) {
                $this->WriteAttributeString('remote_host', $remoteHost);
            }
        }

        $apiKey = trim((string)$this->GetApiKey());
        $reg = $this->RemoteUpdateIntegrationDriverToken($remoteHost, $apiKey, $tokenStored);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📌 RemoteUpdateIntegrationDriverToken result: ' . json_encode($reg), 0);

        // Finish setup so the remote creates/updates the integration instance.
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Finishing setup (STOP/OK)', 0);
        $this->FinishDriverSetupOK($clientIP, $clientPort);
    }

    private function HandleSetDriverUserData_Complex(array $json, int $reqId, string $clientIP, int $clientPort): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, '📥 Setup-Daten vom Benutzer empfangen', 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_FORM, '📨 Vollständiger msg_data: ' . json_encode($json['msg_data'], JSON_PRETTY_PRINT), 0);

        $inputValues = $json['msg_data']['input_values'] ?? [];

        if (!empty($inputValues)) {
            foreach ($inputValues as $key => $value) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_FORM, "🔑 Eingabe: $key => $value", 0);
            }
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_FORM, '⚠️ Keine input_values enthalten', 0);
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_FORM, '📊 input_values: ' . json_encode($inputValues), 0);

        // STEP 1: Confirmation
        if (isset($inputValues['step1.confirmation'])) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, '➡️ Schritt 1: Einleitung bestätigt', 0);
            // Always acknowledge set_driver_user_data
            $this->SendResultOK($reqId, $clientIP, $clientPort);
            $this->StartDriverSetupFlow($clientIP, $clientPort);
            return;
        } elseif (isset($inputValues['step2.token'])) {

            $tokenUser = (string)$inputValues['step2.token'];
            $tokenStored = (string)$this->ReadAttributeString('token');

            // Always acknowledge set_driver_user_data
            $this->SendResultOK($reqId, $clientIP, $clientPort);

            if ($tokenUser !== $tokenStored) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, "❌ Invalid token in complex flow: $tokenUser", 0);
                $this->RequestTokenAgain($clientIP, $clientPort,
                    'Ungültiger Token. Bitte erneut eingeben oder den vorausgefüllten Token bestätigen.',
                    'Invalid token. Please re-enter or confirm the prefilled token.'
                );
                return;
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Token confirmed (complex flow) → finishing setup', 0);
            $this->FinishDriverSetupOK($clientIP, $clientPort);
            return;
        } elseif (isset($inputValues['step3.device_selection']) || isset($inputValues['step3.ready'])) {

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, "✅ Geräteauswahl abgeschlossen", 0);

            $nextStep = [
                'kind' => 'resp',
                'req_id' => $reqId,
                'code' => 200,
                'msg' => 'result',
                'msg_data' => [
                    'setup_action' => [
                        'type' => 'setup_complete'
                    ]
                ]
            ];
            $this->PushToRemoteClient($nextStep, $clientIP, $clientPort);

        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_FORM, '⚠️ Unbekannte oder fehlende Eingabewerte', 0);
            $this->SendResultOK($reqId, $clientIP, $clientPort);
            $this->StartDriverSetupFlow($clientIP, $clientPort);
        }
    }

    private function SendAvailableEntities(string $clientIP, int $clientPort, int $reqId): void
    {
        $entities = [];

        // Buttons auslesen
        $buttonMapping = json_decode($this->ReadPropertyString('button_mapping'), true);
        foreach ($buttonMapping as $entry) {
            if (isset($entry['name']) && isset($entry['script_id'])) {
                $entities[] = [
                    'entity_id' => 'button_' . $entry['script_id'],
                    'entity_type' => 'button',
                    'features' => [Entity_Button::FEATURE_PRESS],
                    'name' => [
                        'en' => $entry['name'],
                        'de' => $entry['name']
                    ]
                ];
            }
        }

        // Generische Entitäten
        $mappings = [
            'switch' => ['property' => 'switch_mapping', 'feature' => [Entity_Switch::FEATURE_ON_OFF]],
            'cover' => ['property' => 'cover_mapping', 'feature' => [Entity_Cover::FEATURE_OPEN, Entity_Cover::FEATURE_CLOSE, Entity_Cover::FEATURE_STOP, Entity_Cover::FEATURE_POSITION]],
            'sensor' => ['property' => 'sensor_mapping', 'feature' => []],
            'climate' => ['property' => 'climate_mapping', 'feature' => []],
            'light' => ['property' => 'light_mapping', 'feature' => []],
            'media_player' => ['property' => 'media_player_mapping', 'feature' => []],
            'select' => ['property' => 'select_mapping', 'feature' => []]
        ];

        foreach ($mappings as $type => $info) {
            $mapping = json_decode($this->ReadPropertyString($info['property']), true);
            foreach ($mapping as $entry) {
                if (!isset($entry['name'])) {
                    continue;
                }

                $features = $info['feature'];
                $entityId = null;

                switch ($type) {
                    case 'light':
                        if (!isset($entry['instance_id']) && !isset($entry['switch_var_id'])) continue 2;
                        $entityId = 'light_' . $entry['instance_id'];
                        $features = [
                            Entity_Light::FEATURE_ON_OFF,
                            Entity_Light::FEATURE_TOGGLE
                        ];
                        if (!empty($entry['brightness_var_id'])) {
                            $features[] = Entity_Light::FEATURE_DIM;
                        }
                        if (!empty($entry['color_temp_var_id'])) {
                            $features[] = Entity_Light::FEATURE_COLOR_TEMP;
                        }
                        if (!empty($entry['color_var_id'])) {
                            $features[] = Entity_Light::FEATURE_COLOR;
                        }
                        break;

                    case 'cover':
                        if (!isset($entry['instance_id']) && !isset($entry['position_var_id'])) continue 2;
                        $entityId = 'cover_' . $entry['instance_id'];
                        $features = [
                            Entity_Cover::FEATURE_OPEN,
                            Entity_Cover::FEATURE_CLOSE,
                            Entity_Cover::FEATURE_STOP,
                            Entity_Cover::FEATURE_POSITION
                        ];
                        break;

                    case 'media_player':
                        if (!isset($entry['instance_id'])) continue 2;
                        $entityId = 'media_player_' . $entry['instance_id'];
                        if (isset($entry['features']) && is_array($entry['features'])) {
                            $features = $this->ExtractMediaPlayerFeatures($entry);
                        }
                        break;

                    case 'sensor':
                        // Sensors are 1:1 with a Symcon variable. Instances can expose multiple sensor variables.
                        // Therefore use var_id as unique entity identifier.
                        if (!isset($entry['var_id']) || !is_numeric($entry['var_id'])) {
                            continue 2;
                        }
                        $entityId = 'sensor_' . (int)$entry['var_id'];
                        break;

                    case 'select':
                        // Select entities are variable-based, because one instance can expose multiple selectable variables.
                        // Therefore use a composite entity id based on instance_id + var_id.
                        if (!isset($entry['var_id']) || !is_numeric($entry['var_id'])) {
                            continue 2;
                        }
                        if (!isset($entry['instance_id']) || !is_numeric($entry['instance_id'])) {
                            continue 2;
                        }
                        $entityId = 'select_' . (int)$entry['instance_id'] . '_' . (int)$entry['var_id'];
                        $features = [
                            'select_option',
                            'select_next',
                            'select_previous',
                            'select_first',
                            'select_last'
                        ];
                        break;

                    default:
                        if (!isset($entry['instance_id'])) continue 2;
                        $entityId = $type . '_' . $entry['instance_id'];
                        break;
                }

                $entity = [
                    'entity_id' => $entityId,
                    'entity_type' => $type,
                    'features' => $features,
                    'name' => [
                        'en' => $entry['name'],
                        'de' => $entry['name']
                    ]
                ];

                if ($type === 'media_player' && isset($entry['device_class'])) {
                    $entity['device_class'] = $entry['device_class'];
                }

                $entities[] = $entity;
            }
        }

        $response = [
            'kind' => 'resp',
            'req_id' => $reqId,
            'code' => 200,
            'msg' => 'available_entities',
            'msg_data' => [
                'available_entities' => $entities
            ]
        ];

        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    /**
     * Extrahiert und bereinigt die MediaPlayer-Features aus einem Mapping-Eintrag.
     */
    private function ExtractMediaPlayerFeatures(array $entry): array
    {
        $features = [];

        if (isset($entry['features']) && is_array($entry['features'])) {
            foreach ($entry['features'] as $feature) {
                if (!isset($feature['feature_key']) || !isset($feature['var_id'])) {
                    continue;
                }

                $key = $feature['feature_key'];
                $varId = (int)$feature['var_id'];

                // Skip if varId is invalid
                if ($varId <= 0 || !@IPS_VariableExists($varId)) {
                    continue;
                }

                // Base feature always included
                $features[] = $key;

                // Special rules
                if ($key === 'mute') {
                    $features[] = 'unmute';
                }

                if ($key === 'symcon_control') {
                    $var = IPS_GetVariable($varId);
                    $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];
                    if ($profile && IPS_VariableProfileExists($profile)) {
                        $profileData = IPS_GetVariableProfile($profile);
                        foreach ($profileData['Associations'] as $assoc) {
                            $v = strtolower($assoc['Name']);
                            if (strpos($v, 'play') !== false) $features[] = Entity_Media_Player::FEATURE_PLAY_PAUSE;
                            if (strpos($v, 'stop') !== false) $features[] = Entity_Media_Player::FEATURE_STOP;
                            if (strpos($v, 'rewind') !== false) $features[] = Entity_Media_Player::FEATURE_REWIND;
                            if (strpos($v, 'forward') !== false) $features[] = Entity_Media_Player::FEATURE_FAST_FORWARD;
                            if (strpos($v, 'next') !== false) $features[] = Entity_Media_Player::FEATURE_NEXT;
                            if (strpos($v, 'prev') !== false || strpos($v, 'zurück') !== false) $features[] = Entity_Media_Player::FEATURE_PREVIOUS;
                        }
                    }
                }

                if ($key === 'symcon_commands') {
                    // todo: Profilbasiertes Mapping möglich
                    $features[] = Entity_Media_Player::FEATURE_INFO;
                    $features[] = Entity_Media_Player::FEATURE_MENU;
                    $features[] = Entity_Media_Player::FEATURE_HOME;
                    $features[] = Entity_Media_Player::FEATURE_GUIDE;
                }

                if ($key === 'symcon_dpad') {
                    $features[] = Entity_Media_Player::FEATURE_DPAD;
                }

                if ($key === 'symcon_numpad') {
                    $features[] = Entity_Media_Player::FEATURE_NUMPAD;
                }
            }
        }

        // Fallback legacy support
        if (empty($features) && !empty($entry['features_list']) && is_array($entry['features_list'])) {
            foreach ($entry['features_list'] as $featureEntry) {
                if (!empty($featureEntry['feature'])) {
                    $features[] = $featureEntry['feature'];
                }
            }
        }

        return array_values(array_unique($features));
    }


    private function SendEntityStates(string $clientIP, int $clientPort, int $reqId): void
    {
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "▶️ Starte SendEntityStates", 0);
        $entities = [];
        // Switches
        $switchMapping = json_decode($this->ReadPropertyString('switch_mapping'), true);
        if (is_array($switchMapping)) {
            // $this->SendDebug(__FUNCTION__, "🔍 Verarbeite Switch-Mapping...", 0);
            foreach ($switchMapping as $entry) {
                if (!isset($entry['instance_id']) || !is_numeric($entry['instance_id'])) {
                    continue;
                }
                if (isset($entry['var_id']) && is_numeric($entry['var_id'])) {
                    $varId = (int)$entry['var_id'];
                    $state = @GetValue($varId);
                    $stateStr = ($state) ? 'ON' : 'OFF';
                    $entities[] = [
                        'entity_id' => 'switch_' . (int)$entry['instance_id'],
                        'entity_type' => 'switch',
                        'attributes' => [
                            Entity_Switch::ATTR_STATE => $stateStr
                        ]
                    ];
                }
            }
        }

        // Lights
        $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
        if (is_array($lightMapping)) {
            // $this->SendDebug(__FUNCTION__, "🔍 Verarbeite Light-Mapping...", 0);
            foreach ($lightMapping as $entry) {
                if (
                    isset($entry['switch_var_id']) && is_numeric($entry['switch_var_id']) &&
                    isset($entry['instance_id']) && !empty($entry['instance_id'])
                ) {
                    $varId = (int)$entry['switch_var_id'];
                    $state = @GetValue($varId);
                    $stateStr = ($state) ? 'ON' : 'OFF';
                    $attributes = [Entity_Light::ATTR_STATE => $stateStr];

                    if (!empty($entry['brightness_var_id']) && @IPS_VariableExists($entry['brightness_var_id'])) {
                        $attributes[Entity_Light::ATTR_BRIGHTNESS] = $this->ConvertBrightnessToRemote($entry['brightness_var_id']);
                    }
                    if (!empty($entry['color_temp_var_id']) && @IPS_VariableExists((int)$entry['color_temp_var_id'])) {
                        $ctVarId = (int)$entry['color_temp_var_id'];
                        $ctVal = @GetValue($ctVarId);
                        $attributes[Entity_Light::ATTR_COLOR_TEMPERATURE] = $this->ConvertColorTemperatureToRemote($ctVarId, $ctVal);
                    }
                    if (!empty($entry['color_var_id']) && @IPS_VariableExists($entry['color_var_id'])) {
                        $hex = @GetValue($entry['color_var_id']);
                        $hs = $this->ConvertHexColorToHueSaturation((int)$hex);
                        $attributes[Entity_Light::ATTR_HUE] = $hs['hue'];
                        $attributes[Entity_Light::ATTR_SATURATION] = $hs['saturation'];
                    }

                    $entities[] = [
                        'entity_id' => 'light_' . $entry['instance_id'],
                        'entity_type' => 'light',
                        'attributes' => $attributes
                    ];
                }
            }
        }

        // Covers
        $coverMapping = json_decode($this->ReadPropertyString('cover_mapping'), true);
        if (is_array($coverMapping)) {
            // $this->SendDebug(__FUNCTION__, "🔍 Verarbeite Cover-Mapping...", 0);
            foreach ($coverMapping as $entry) {
                if (
                    isset($entry['position_var_id']) && is_numeric($entry['position_var_id']) &&
                    isset($entry['instance_id']) && !empty($entry['instance_id'])
                ) {
                    $varId = (int)$entry['position_var_id'];
                    $symconPos = @GetValue($varId);
                    $position = $this->ConvertCoverPositionToRemote($varId, $symconPos);
                    $stateStr = ($position <= 0) ? 'CLOSED' : 'OPEN';
                    $entities[] = [
                        'entity_id' => 'cover_' . $entry['instance_id'],
                        'entity_type' => 'cover',
                        'attributes' => [
                            Entity_Cover::ATTR_STATE => $stateStr,
                            Entity_Cover::ATTR_POSITION => $position
                        ]
                    ];
                }
            }
        }

        // Sensors
        $sensorMapping = json_decode($this->ReadPropertyString('sensor_mapping'), true);
        if (is_array($sensorMapping)) {
            foreach ($sensorMapping as $entry) {
                if (!isset($entry['var_id']) || !is_numeric($entry['var_id'])) {
                    continue;
                }
                $varId = (int)$entry['var_id'];
                if ($varId <= 0 || !@IPS_VariableExists($varId)) {
                    continue;
                }

                $result = $this->GetSensorValueAndUnit($varId);

                // Remote sensor entities are read-only. Provide value in a robust way.
                // Use both 'value' and 'state' to maximize compatibility across Core versions.
                $entities[] = [
                    'entity_id' => 'sensor_' . $varId,
                    'entity_type' => 'sensor',
                    'attributes' => [
                        'value' => (string)$result['value'],
                        'unit' => (string)$result['unit'],
                        'state' => 'ON'
                    ]
                ];
            }
        }

        // Climates
        $climateMapping = json_decode($this->ReadPropertyString('climate_mapping'), true);
        if (is_array($climateMapping)) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "🔍 Verarbeite Climate-Mapping...", 0);
            foreach ($climateMapping as $entry) {
                // Robustere Prüfung und ausführliche Debug-Ausgaben
                if (!isset($entry['instance_id'])) {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "⚠️ Eintrag ohne instance_id übersprungen: " . json_encode($entry), 0);
                    continue;
                }

                try {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "➡️ Climate-Instanz: " . $entry['instance_id'], 0);

                    if (!isset($entry['status_var_id']) || !is_numeric($entry['status_var_id'])) {
                        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "⚠️ Kein status_var_id für climate_" . $entry['instance_id'], 0);
                        continue;
                    }

                    $attributes = [];

                    // 1) state = ON/OFF aus status_var_id
                    $statusVarId = (int)$entry['status_var_id'];
                    $statusRaw = @GetValue($statusVarId);
                    $attributes['state'] = $this->NormalizeOnOffState($statusVarId, $statusRaw);

                    // 2) hvac_mode (optional) aus mode_var_id
                    if (!empty($entry['mode_var_id']) && @IPS_VariableExists((int)$entry['mode_var_id'])) {
                        $modeVarId = (int)$entry['mode_var_id'];
                        $modeVal = @GetValue($modeVarId);
                        $modeLabel = $this->GetProfileValueLabel($modeVarId, $modeVal);
                        $allowedModes = ['HEAT', 'COOL', 'HEAT_COOL', 'FAN', 'AUTO', 'OFF'];
                        if (in_array($modeLabel, $allowedModes, true)) {
                            $attributes[Entity_Climate::ATTR_HVAC_MODE] = $modeLabel;
                        }
                    }

                    if (!empty($entry['target_temp_var_id']) && IPS_VariableExists($entry['target_temp_var_id'])) {
                        $attributes[Entity_Climate::ATTR_TARGET_TEMPERATURE] = GetValue($entry['target_temp_var_id']);
                    }

                    if (!empty($entry['current_temp_var_id']) && IPS_VariableExists($entry['current_temp_var_id'])) {
                        $attributes[Entity_Climate::ATTR_CURRENT_TEMPERATURE] = GetValue($entry['current_temp_var_id']);
                    }

                    $entities[] = [
                        'entity_id' => 'climate_' . $entry['instance_id'],
                        'entity_type' => 'climate',
                        'attributes' => $attributes
                    ];
                } catch (Throwable $e) {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "❌ Fehler bei Climate-Instanz {$entry['instance_id']}: " . $e->getMessage(), 0);
                    continue;
                }
            }
        }


        // Media Player
        $mediaMapping = json_decode($this->ReadPropertyString('media_player_mapping'), true);
        if (is_array($mediaMapping)) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "🔍 Verarbeite Media Player-Mapping...", 0);

            foreach ($mediaMapping as $entry) {
                if (!isset($entry['instance_id']) || !isset($entry['features']) || !is_array($entry['features'])) {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "⚠️ Ungültiger Eintrag im Media Mapping übersprungen: " . json_encode($entry), 0);
                    continue;
                }

                $instanceId = (string)$entry['instance_id'];
                if ($instanceId === '') {
                    continue;
                }

                $entityId = 'media_player_' . $instanceId;

                // Use unified helper (includes cache / fallback logic)
                $attributes = $this->BuildMediaPlayerAttributesFromFeatures($entry);

                $entities[] = [
                    'entity_id' => $entityId,
                    'entity_type' => 'media_player',
                    'attributes' => $attributes
                ];
            }
        }

        // Select
        $selectMapping = json_decode($this->ReadPropertyString('select_mapping'), true);
        if (is_array($selectMapping)) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "🔍 Verarbeite Select-Mapping...", 0);

            foreach ($selectMapping as $entry) {
                if (!isset($entry['instance_id']) || !is_numeric($entry['instance_id'])) {
                    continue;
                }
                if (!isset($entry['var_id']) || !is_numeric($entry['var_id'])) {
                    continue;
                }

                $instanceId = (int)$entry['instance_id'];
                $varId = (int)$entry['var_id'];

                if ($instanceId <= 0 || $varId <= 0 || !@IPS_VariableExists($varId)) {
                    continue;
                }

                $currentValue = @GetValue($varId);
                $varInfo = @IPS_GetVariable($varId);
                if (!is_array($varInfo)) {
                    continue;
                }

                $profileName = trim((string)($varInfo['VariableCustomProfile'] ?? ''));
                if ($profileName === '') {
                    $profileName = trim((string)($varInfo['VariableProfile'] ?? ''));
                }

                $options = [];
                $currentOption = '';

                if ($profileName !== '' && @IPS_VariableProfileExists($profileName)) {
                    $profile = @IPS_GetVariableProfile($profileName);
                    $associations = $profile['Associations'] ?? [];

                    if (is_array($associations)) {
                        foreach ($associations as $assoc) {
                            if (!is_array($assoc)) {
                                continue;
                            }

                            $label = trim((string)($assoc['Name'] ?? ''));
                            if ($label === '') {
                                $label = (string)($assoc['Value'] ?? '');
                            }
                            if ($label === '') {
                                continue;
                            }

                            $options[] = $label;

                            if ((string)($assoc['Value'] ?? '') === (string)$currentValue) {
                                $currentOption = $label;
                            }
                        }
                    }
                }

                if ($currentOption === '') {
                    $currentOption = (string)$currentValue;
                }

                $entities[] = [
                    'entity_id' => 'select_' . $instanceId . '_' . $varId,
                    'entity_type' => 'select',
                    'attributes' => [
                        'options' => array_values(array_unique($options)),
                        'current_option' => $currentOption,
                        'state' => 'ON'
                    ]
                ];
            }
        }

        $response = [
            'kind' => 'resp',
            'req_id' => $reqId,
            'code' => 200,
            'msg' => 'entity_states',
            'msg_data' => $entities
        ];
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_IO, '📤 entity_states count=' . count($entities), 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 entity_states payload: ' . json_encode($entities), 0);
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
        // $this->SendDebug(__FUNCTION__, "✅ SendEntityStates abgeschlossen", 0);
    }

    /**
     * Returns the effective (custom or default) variable profile name for a variable id.
     */
    private function GetEffectiveVariableProfile(int $varId): string
    {
        if ($varId <= 0 || !@IPS_VariableExists($varId)) {
            return '';
        }
        $var = IPS_GetVariable($varId);
        return (string)($var['VariableCustomProfile'] ?: $var['VariableProfile'] ?: '');
    }

    /**
     * Detect whether a cover position profile is reversed.
     *
     * Reversed means: minimum value represents OPEN and maximum represents CLOSED.
     * Normal means: minimum represents CLOSED and maximum represents OPEN.
     */
    private function IsCoverProfileReversed(string $profileName, array $profileData): bool
    {
        $p = strtolower($profileName);

        // Explicit reverse hint in profile name
        if (str_contains($p, 'reversed')) {
            return true;
        }

        // Heuristic using associations (if present)
        $min = $profileData['MinValue'] ?? null;
        $max = $profileData['MaxValue'] ?? null;
        $assocs = $profileData['Associations'] ?? [];

        if ($min === null || $max === null || !is_array($assocs) || empty($assocs)) {
            return false;
        }

        $minLabel = '';
        $maxLabel = '';
        foreach ($assocs as $a) {
            if (!is_array($a) || !isset($a['Value'], $a['Name'])) {
                continue;
            }
            if ((string)$a['Value'] === (string)$min) {
                $minLabel = strtolower((string)$a['Name']);
            }
            if ((string)$a['Value'] === (string)$max) {
                $maxLabel = strtolower((string)$a['Name']);
            }
        }

        $openHints = ['open', 'opened', 'auf', 'geöffnet', 'offen'];
        $closedHints = ['close', 'closed', 'zu', 'geschlossen'];

        $minIsOpen = false;
        foreach ($openHints as $h) {
            if ($minLabel !== '' && str_contains($minLabel, $h)) {
                $minIsOpen = true;
                break;
            }
        }

        $maxIsClosed = false;
        foreach ($closedHints as $h) {
            if ($maxLabel !== '' && str_contains($maxLabel, $h)) {
                $maxIsClosed = true;
                break;
            }
        }

        return $minIsOpen && $maxIsClosed;
    }

    /**
     * Convert Remote cover position (0..100, 0=CLOSED, 100=OPEN) to Symcon value for RequestAction().
     */
    private function ConvertCoverPositionFromRemote(int $positionVarId, int $remotePos): float|int
    {
        $remotePos = max(0, min(100, (int)$remotePos));

        $profile = $this->GetEffectiveVariableProfile($positionVarId);
        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            // Fallback assume 0..100 int
            return $remotePos;
        }

        $profileData = IPS_GetVariableProfile($profile);
        $min = (float)($profileData['MinValue'] ?? 0.0);
        $max = (float)($profileData['MaxValue'] ?? 100.0);

        // Remote normalized 0..1 where 0=CLOSED and 1=OPEN
        $norm = $remotePos / 100.0;

        // If Symcon profile is reversed (min=open, max=closed), invert norm
        if ($this->IsCoverProfileReversed($profile, $profileData)) {
            $norm = 1.0 - $norm;
        }

        // Scale to profile min..max
        $scaled = $min + ($norm * ($max - $min));

        // Clamp
        $scaled = max(min($scaled, $max), $min);

        // Return type-correct value
        $var = IPS_GetVariable($positionVarId);
        $type = (int)($var['VariableType'] ?? 1); // 1=int, 2=float
        if ($type === 2) {
            return (float)$scaled;
        }
        return (int)round($scaled);
    }

    /**
     * Convert Symcon cover position value to Remote cover position (0..100, 0=CLOSED, 100=OPEN).
     */
    private function ConvertCoverPositionToRemote(int $positionVarId, $symconValue): int
    {
        if (!is_numeric($symconValue)) {
            return 0;
        }

        $profile = $this->GetEffectiveVariableProfile($positionVarId);
        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            // Fallback assume already 0..100
            return max(0, min(100, (int)round((float)$symconValue)));
        }

        $profileData = IPS_GetVariableProfile($profile);
        $min = (float)($profileData['MinValue'] ?? 0.0);
        $max = (float)($profileData['MaxValue'] ?? 100.0);
        if ($max == $min) {
            return 0;
        }

        $v = (float)$symconValue;
        $v = max(min($v, $max), $min);

        // Normalize 0..1 in profile space
        $norm = ($v - $min) / ($max - $min);
        $norm = max(0.0, min(1.0, $norm));

        // If profile is reversed, invert norm to match Remote semantics
        if ($this->IsCoverProfileReversed($profile, $profileData)) {
            $norm = 1.0 - $norm;
        }

        $remotePos = (int)round($norm * 100.0);
        return max(0, min(100, $remotePos));
    }

    /**
     * Gibt das Label (Name) einer Association anhand des aktuellen Wertes zurück.
     *
     * @param int $varId Die ID der Variablen
     * @param mixed $value Der aktuelle Wert
     * @return string       Das zugehörige Label (Großbuchstaben), oder leer bei Fehler
     */
    private function GetProfileValueLabel(int $varId, $value): string
    {
        if (!IPS_VariableExists($varId)) {
            return '';
        }

        $var = IPS_GetVariable($varId);
        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

        if (!$profile || !IPS_VariableProfileExists($profile)) {
            return '';
        }

        $profileData = IPS_GetVariableProfile($profile);
        foreach ($profileData['Associations'] as $assoc) {
            if ((string)$assoc['Value'] === (string)$value) {
                return strtoupper(trim($assoc['Name']));
            }
        }

        return '';
    }

    private function ConvertTimeStringToSeconds($input): float
    {
        if (!is_string($input)) {
            return 0;
        }

        $parts = explode(':', $input);
        $parts = array_reverse($parts);
        $seconds = 0;

        foreach ($parts as $index => $value) {
            if (!is_numeric($value)) {
                return 0; // ungültig, z.B. 'Pause'
            }
            $seconds += intval($value) * pow(60, $index);
        }

        return (float)$seconds;
    }

    /**
     * Interpretiert den aktuellen Status eines MediaPlayers anhand der Control-Variable und deren Profil.
     *
     * @param int $varId
     * @return string
     */
    private function GetMediaPlayerStateFromControlVariable(int $varId): string
    {
        if (!IPS_VariableExists($varId)) {
            return 'UNKNOWN';
        }

        $value = @GetValue($varId);
        $var = IPS_GetVariable($varId);
        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

        if (!$profile || !IPS_VariableProfileExists($profile)) {
            return 'UNKNOWN';
        }

        $profileData = IPS_GetVariableProfile($profile);
        foreach ($profileData['Associations'] as $assoc) {
            if ((string)$assoc['Value'] === (string)$value) {
                $label = strtolower($assoc['Name']);
                if (strpos($label, 'play') !== false) {
                    return 'PLAYING';
                }
                if (strpos($label, 'pause') !== false) {
                    return 'PAUSED';
                }
                if (strpos($label, 'stop') !== false) {
                    return 'OFF';
                }
                if (strpos($label, 'standby') !== false) {
                    return 'STANDBY';
                }
                if (strpos($label, 'buffer') !== false) {
                    return 'BUFFERING';
                }
            }
        }

        return 'ON'; // fallback wenn kein Mapping passt
    }


    private function SendResultOK(int $id, string $clientIP, int $clientPort): void
    {
        $response = [
            'kind' => 'resp',
            'msg' => 'result',
            'req_id' => $id,
            'code' => 200,
            'msg_data' => new stdClass()
        ];
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    /**
     * Update the integration driver configuration on the Remote via the Core REST API.
     *
     * We use this to set the Symcon access token for the external driver entry.
     *
     * Endpoint:
     *   PATCH /api/intg/drivers/{driverId}
     *
     * Body model: integrationDriverUpdate (token + auth_method).
     *
     * NOTE: This replaces the deprecated/unsupported `/api/auth/external/...` approach, which
     * only applies to installed integrations and may return 404 for external drivers.
     */
    public function RemoteUpdateIntegrationDriverToken(string $remoteHost, string $apiKey, string $token): array
    {
        $remoteHost = trim($remoteHost);
        $apiKey = trim($apiKey);
        $token = trim($token);

        if ($remoteHost === '' || $apiKey === '' || $token === '') {
            return [
                'ok' => false,
                'reason' => 'missing_remoteHost_apiKey_or_token',
                'remoteHost' => $remoteHost,
                'apiKey_len' => strlen($apiKey),
                'token_len' => strlen($token)
            ];
        }

        // The driver id must match the integration driver's driver_id.
        $driverId = (string)$this->GetDriverId();

        // PATCH model: integrationDriverUpdate
        // - token: authentication token for the driver
        // - auth_method: MESSAGE (token is sent with an auth message after WS connection)
        //   HEADER would mean `auth-token` header during WS upgrade.
        $bodyArr = [
            'token' => $token,
            'auth_method' => 'MESSAGE'
        ];

        $body = json_encode($bodyArr, JSON_UNESCAPED_SLASHES);

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔑 Updating driver token via REST: PATCH /api/intg/drivers/' . $driverId, 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_SETUP, '🔑 Driver token update body: ' . (string)$body, 0);

        $remoteHost = $this->FormatRemoteHostForHttpUrl($remoteHost);
        $url = "http://{$remoteHost}/api/intg/drivers/" . rawurlencode($driverId);

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => ($body === false ? '{}' : $body)
        ];

        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string)curl_error($ch);
        curl_close($ch);

        $result = [
            'httpCode' => $code,
            'response' => ($resp === false ? '' : (string)$resp),
            'error' => $err
        ];

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_SETUP, '🔑 PATCH result: ' . json_encode($result), 0);

        if ($err !== '') {
            return ['ok' => false, 'reason' => 'curl_error', 'error' => $err];
        }

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'reason' => 'updated', 'httpCode' => $code, 'response' => $result['response']];
        }

        if ($code === 404) {
            return [
                'ok' => false,
                'reason' => 'driver_not_found_404',
                'hint' => 'Remote does not have an external driver entry for this driver_id yet. Ensure the driver is registered/visible in the Remote before updating its token (driver_id must match).',
                'httpCode' => $code,
                'response' => $result['response']
            ];
        }

        return ['ok' => false, 'reason' => 'patch_failed', 'httpCode' => $code, 'response' => $result['response']];
    }

    /**
     * Formats a Remote host for use inside an HTTP URL.
     *
     * Handles:
     * - IPv6 zone IDs (e.g. fe80::1%eth0 -> fe80::1%25eth0)
     * - IPv6 URL bracket notation (e.g. fe80::1 -> [fe80::1])
     *
     * IMPORTANT: Expects host only (no scheme). If the input already starts with '[', it is left as-is.
     */
    private function FormatRemoteHostForHttpUrl(string $remoteHost): string
    {
        $remoteHost = trim($remoteHost);
        if ($remoteHost === '') {
            return '';
        }

        // Encode IPv6 zone id for URLs: fe80::1%eth0 -> fe80::1%25eth0
        if (str_contains($remoteHost, '%')) {
            $remoteHost = str_replace('%', '%25', $remoteHost);
        }

        // Wrap IPv6 literals in brackets for URLs.
        // Heuristic: IPv6 contains at least 2 ':' and is not already bracketed.
        if ((substr_count($remoteHost, ':') >= 2) && !str_starts_with($remoteHost, '[')) {
            $remoteHost = '[' . $remoteHost . ']';
        }

        return $remoteHost;
    }

    /**
     * Reads the current integration driver configuration from the Remote via REST.
     * Used to check whether a token is already set.
     */
    public function RemoteGetIntegrationDriver(string $remoteHost, string $apiKey): array
    {
        $remoteHost = trim($remoteHost);
        $apiKey = trim($apiKey);

        if ($remoteHost === '' || $apiKey === '') {
            return [
                'ok' => false,
                'reason' => 'missing_remoteHost_or_apiKey'
            ];
        }

        $driverId = (string)$this->GetDriverId();

        $remoteHost = $this->FormatRemoteHostForHttpUrl($remoteHost);

        $url = "http://{$remoteHost}/api/intg/drivers/" . rawurlencode($driverId);

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔎 Reading driver config via REST: GET /api/intg/drivers/' . $driverId, 0);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ]);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string)curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '❌ GET driver config curl error: ' . $err, 0);
            return ['ok' => false, 'reason' => 'curl_error', 'error' => $err];
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_SETUP, '🔎 GET driver config HTTP ' . $code . ' → ' . (string)$resp, 0);

        if ($code < 200 || $code >= 300) {
            return [
                'ok' => false,
                'reason' => 'http_error',
                'httpCode' => $code,
                'response' => $resp
            ];
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'reason' => 'invalid_json',
                'response' => $resp
            ];
        }

        $token = $data['token'] ?? '';
        $authMethod = $data['auth_method'] ?? '';

        $this->Debug(
            __FUNCTION__,
            self::LV_INFO,
            self::TOPIC_SETUP,
            '🔐 Remote driver token read → token_len=' . strlen((string)$token) . ', auth_method=' . (string)$authMethod,
            0
        );

        return [
            'ok' => true,
            'token' => (string)$token,
            'auth_method' => (string)$authMethod,
            'raw' => $data
        ];
    }

    /**
     * Start the driver setup flow by requesting a token from the user.
     * According to the UC integration asyncapi, after confirming `setup_driver`, the driver must emit
     * `driver_setup_change` events (SETUP/WAIT_USER_ACTION/STOP).
     */
    private function StartDriverSetupFlow(string $clientIP, int $clientPort): void
    {
        $this->EnsureTokenInitialized();
        $token = (string)$this->ReadAttributeString('token');

        $remoteHost = $this->GetEffectiveRemoteHost();
        if ($remoteHost === '') {
            $remoteHost = trim($clientIP);
        }

        $candidate = $remoteHost !== '' ? $remoteHost : $clientIP;
        $resolved = $this->ResolveRemoteHostForRest($candidate);
        if ($resolved !== '') {
            $remoteHost = $resolved;
            if (!$this->IsManualHostEnabled()) {
                $this->WriteAttributeString('remote_host', $remoteHost);
            }
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '➡️ Starting setup flow (standalone) – ensuring Remote API access first', 0);

        $apiAccess = $this->EnsureRemoteApiAccess($remoteHost);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📊 EnsureRemoteApiAccess result: ' . json_encode($apiAccess), 0);

        if (!($apiAccess['ok'] ?? false)) {

            $storedPin = trim((string)$this->ReadAttributeString('web_config_pass'));

            $pinInfoTextEn =
                "To configure this integration automatically, Symcon needs permission to call the Remote's REST API.\n\n" .
                "Please enter the 4-digit PIN from the Remote's Web Configurator. Symcon stores the PIN locally and uses it only to request an API key from the Remote.\n\n" .
                "You normally need to enter the PIN only once.";

            $pinInfoTextDe =
                "Damit diese Integration möglichst automatisch eingerichtet werden kann, muss Symcon die REST-API der Remote aufrufen dürfen.\n\n" .
                "Bitte gib den 4-stelligen PIN aus dem Web-Configurator der Remote ein. Symcon speichert den PIN lokal und nutzt ihn ausschließlich, um bei der Remote einen API-Key zu erzeugen.\n\n" .
                "In der Regel musst Du den PIN nur einmal eingeben.";

            if ($storedPin !== '') {
                $pinInfoTextEn .= "\n\nA PIN is already stored, but Symcon could not obtain a working API key. Please confirm the PIN or enter the current one.";
                $pinInfoTextDe .= "\n\nEin PIN ist bereits gespeichert, aber Symcon konnte keinen funktionierenden API-Key erhalten. Bitte bestätige den PIN oder gib den aktuellen ein.";
            }

            $page = [
                'title' => [
                    'en' => 'Remote PIN',
                    'de' => 'Remote PIN'
                ],
                'settings' => [
                    [
                        'id' => 'pin_info',
                        'label' => [
                            'en' => 'Why do we need this?',
                            'de' => 'Warum wird das benötigt?'
                        ],
                        'field' => [
                            'label' => [
                                'value' => [
                                    'en' => $pinInfoTextEn,
                                    'de' => $pinInfoTextDe
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'pin',
                        'label' => [
                            'en' => '4-digit Remote PIN',
                            'de' => '4-stelliger Remote PIN'
                        ],
                        'field' => [
                            'text' => [
                                // Prefill falls vorhanden
                                'value' => $storedPin
                            ]
                        ]
                    ]
                ]
            ];

            $this->SendDriverSetupChange($clientIP, $clientPort, 'SETUP', 'WAIT_USER_ACTION', [
                'input' => $page
            ]);

            return;
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '➡️ Remote API access OK → pushing Symcon token to Remote via REST (no user input)', 0);
        // Keep setup alive (watchdog) while we perform REST calls.
        $this->SendDriverSetupChange($clientIP, $clientPort, 'SETUP', 'SETUP');

        $apiKey = trim((string)($apiAccess['api_key'] ?? ''));
        $tokenStored = trim((string)$this->ReadAttributeString('token'));

        // 1) Zuerst aktuellen Driver-Status vom Remote lesen
        $driverInfo = $this->RemoteGetIntegrationDriver($remoteHost, $apiKey);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📌 RemoteGetIntegrationDriver result: ' . json_encode($driverInfo), 0);

        if (($driverInfo['ok'] ?? false) === true) {

            $remoteToken = (string)($driverInfo['token'] ?? '');

            // a) Token ist bereits korrekt gesetzt → Setup beenden (Remote erstellt danach die Instanz)
            if ($remoteToken !== '' && $remoteToken === $tokenStored) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Remote already has correct token → finishing setup (STOP/OK)', 0);
                $this->FinishDriverSetupOK($clientIP, $clientPort);
                return;
            }

            // b) Token fehlt oder ist anders → jetzt PATCH ausführen
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '🔄 Remote token missing or different → updating token via PATCH', 0);
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_SETUP, '⚠️ Could not read driver info → attempting PATCH anyway', 0);
        }

        // 2) Token setzen/aktualisieren
        $reg = $this->RemoteUpdateIntegrationDriverToken($remoteHost, $apiKey, $tokenStored);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '📌 RemoteUpdateIntegrationDriverToken result: ' . json_encode($reg), 0);

        if (($reg['ok'] ?? false) === true) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Token successfully registered/updated on Remote → finishing setup (STOP/OK)', 0);
            // According to AsyncAPI, the setup process is finished with event_type STOP + state OK.
            // After this, the Remote creates the integration instance and proceeds with entity handling.
            $this->FinishDriverSetupOK($clientIP, $clientPort);
            return;
        }
    }

    /**
     * Emit a driver_setup_change event.
     * @param string $eventType One of: START/SETUP/STOP (spec uses SETUP + STOP here)
     * @param string $state One of: SETUP/WAIT_USER_ACTION/OK/ERROR
     */
    private function SendDriverSetupChange(string $clientIP, int $clientPort, string $eventType, string $state, ?array $requireUserAction = null, string $error = 'NONE'): void
    {
        $msgData = [
            'event_type' => $eventType,
            'state' => $state
        ];

        if ($state === 'ERROR') {
            $msgData['error'] = $error;
        }

        if ($requireUserAction !== null) {
            $msgData['require_user_action'] = $requireUserAction;
        }

        $payload = [
            'kind' => 'event',
            'msg' => 'driver_setup_change',
            'cat' => 'DEVICE',
            'msg_data' => $msgData
        ];

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_SETUP, '📤 driver_setup_change → ' . json_encode($payload), 0);
        $this->PushToRemoteClient($payload, $clientIP, $clientPort);
    }

    /**
     * Convenience: Finish setup successfully.
     */
    private function FinishDriverSetupOK(string $clientIP, int $clientPort): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_SETUP, '✅ Finishing setup: driver_setup_change STOP/OK', 0);
        $this->SendDriverSetupChange($clientIP, $clientPort, 'STOP', 'OK');
    }

    /**
     * Convenience: Ask for token again with an error hint.
     */
    private function RequestTokenAgain(string $clientIP, int $clientPort, string $messageDe, string $messageEn): void
    {
        $token = (string)$this->ReadAttributeString('token');

        $page = [
            'title' => [
                'en' => 'Invalid Token',
                'de' => 'Ungültiger Token'
            ],
            'settings' => [
                [
                    'id' => 'error',
                    'label' => [
                        'en' => 'Error',
                        'de' => 'Fehler'
                    ],
                    'field' => [
                        'label' => [
                            'value' => [
                                'en' => $messageEn,
                                'de' => $messageDe
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'token',
                    'label' => [
                        'en' => 'Token for Symcon remote access',
                        'de' => 'Token für den Remote-Zugriff auf Symcon'
                    ],
                    'field' => [
                        'text' => [
                            'value' => $token
                        ]
                    ]
                ]
            ]
        ];

        $this->SendDriverSetupChange($clientIP, $clientPort, 'SETUP', 'WAIT_USER_ACTION', [
            'input' => $page
        ]);
    }

    private function NotifyDriverSetupComplete(string $clientIP, int $clientPort): void
    {
        $event = [
            'kind' => 'event',
            'msg' => 'driver_setup_change',
            'msg_data' => [
                'event_type' => 'STOP',
                'state' => 'OK'
            ],
            'cat' => 'DEVICE'
        ];
        $this->PushToRemoteClient($event, $clientIP, $clientPort);
    }

    private function sendAuthFailure(string $clientIP, int $clientPort): void
    {
        $response = [
            'kind' => 'resp',
            'req_id' => 0,
            'code' => 401,
            'msg' => 'auth_required',
            'msg_data' => [
                'message' => 'Unauthorized – Invalid or missing token'
            ]
        ];
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    /**
     * Sends driver version information to the remote client.
     *
     * @param string $clientIP
     * @param int $clientPort
     * @param int $reqId
     */
    private function SendDriverVersion(string $clientIP, int $clientPort, int $reqId): void
    {
        $response = [
            'kind' => 'resp',
            'msg' => 'driver_version',
            'req_id' => $reqId,
            'code' => 200,
            'msg_data' => [
                'name' => 'Symcon Integration Driver',
                'version' => [
                    'api' => self::Unfolded_Circle_API_Version,
                    'driver' => $this->GetModuleLibraryVersion()
                ],
                'driver_id' => $this->GetDriverId()
            ]
        ];
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    private function ReadClientSessions(): array
    {
        $raw = (string)$this->ReadAttributeString('client_sessions');
        $sessions = json_decode($raw, true);
        return is_array($sessions) ? $sessions : [];
    }

    // Consider a remote client session "alive" only if we saw traffic recently.
    // Prevents spamming when the Remote is offline.

    /**
     * Returns true if a client session is considered "alive" (recently seen).
     * Prevents endless sending when the Remote is offline but the session still exists.
     */
    private function IsClientSessionAlive(array $info): bool
    {
        $lastSeen = (int)($info['last_seen'] ?? 0);
        if ($lastSeen <= 0) {
            return false;
        }
        return (time() - $lastSeen) <= self::REMOTE_SESSION_TIMEOUT_SEC;
    }

    /**
     * Returns a list of alive client targets (authenticated or whitelisted + has port + recently seen).
     * Each entry: ['ip' => string, 'port' => int]
     */
    private function GetAliveClientTargets(): array
    {
        $sessions = $this->readSessions();

        $whitelistConfig = json_decode($this->ReadPropertyString('ip_whitelist'), true);
        $ipWhitelist = array_column($whitelistConfig ?? [], 'ip');

        $targets = [];
        foreach ($sessions as $ip => $info) {
            $auth = (bool)($info['authenticated'] ?? false);
            $port = (int)($info['port'] ?? 0);
            $whitelisted = in_array($ip, $ipWhitelist, true);

            // Must be authenticated OR whitelisted, and must have a port.
            if ((!$auth && !$whitelisted) || $port <= 0) {
                continue;
            }

            // Must be alive (recently seen).
            if (!$this->IsClientSessionAlive((array)$info)) {
                continue;
            }

            $targets[] = ['ip' => (string)$ip, 'port' => $port];
        }

        return $targets;
    }

    private function HasAliveClients(): bool
    {
        return count($this->GetAliveClientTargets()) > 0;
    }

    private function PushToRemoteClient(array $data, string $clientIP, int $clientPort): void
    {
        // Encode message to JSON
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_IO, '❌ JSON Encoding Error (message): ' . json_last_error_msg(), 0);
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Response to ' . $clientIP . ': ' . $json, 0);

        // Pack into a WebSocket frame (binary)
        $packed = WebSocketUtils::PackData($json);
        $packedHex = bin2hex($packed);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Packed Data (hex): ' . $packedHex, 0);

        // IMPORTANT: Never put binary into JSON. Send HEX and let the parent (Server Socket) convert back to binary.
        $sendPayload = [
            'DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', // Server Socket
            'ClientIP' => $clientIP,
            'ClientPort' => $clientPort,
            'Type' => 0,
            'Buffer' => $packedHex
        ];

        $jsonPayload = json_encode($sendPayload, JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_IO, '❌ JSON Encoding Error (envelope): ' . json_last_error_msg(), 0);
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Final JSON Payload: ' . $jsonPayload, 0);
        $this->SendDataToParent($jsonPayload);
    }

    public function TestPushToRemote()
    {
        $testMessage = [
            'kind' => 'resp',
            'req_id' => 999,
            'msg' => 'test_echo',
            'msg_data' => [
                'text' => 'Dies ist ein Testpaket von Symcon'
            ]
        ];

        // Beispielwerte für ClientIP und Port – bitte im Skript korrekt setzen
        $clientIP = '192.168.55.125';
        $clientPort = 9988;

        $this->PushToRemoteClient($testMessage, $clientIP, $clientPort);
    }

    /**
     * Sende rohe Strings (z.B. HTTP-Header, WebSocket-Frames) direkt an den Client.
     * IMPORTANT: Never put binary / raw frame bytes into JSON. Always send HEX and let the parent (Server Socket) convert back to binary.
     */
    private function PushRawToRemoteClient(string $data, string $clientIP, int $clientPort): void
    {
        // IMPORTANT: Never put binary / raw frame bytes into JSON. Always send HEX and let the parent (Server Socket) convert back to binary.
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Raw response (string) to ' . $clientIP . ': ' . $data, 0);

        // Convert to bytes as-is and send HEX
        $hex = bin2hex($data);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Raw response (hex,len=' . strlen($hex) . '): ' . $hex, 0);

        $payload = [
            'DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', // Server Socket
            'ClientIP' => $clientIP,
            'ClientPort' => $clientPort,
            'Type' => 0,
            'Buffer' => $hex
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_IO, '❌ JSON Encoding Error (raw envelope): ' . json_last_error_msg(), 0);
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 Raw envelope to Server Socket: ' . $json, 0);
        $this->SendDataToParent($json);
    }

    private function PushPongToRemoteClient(string $data, string $clientIP, int $clientPort): void
    {
        // IMPORTANT: Do not perform any encoding conversion here.
        // $data already contains the exact bytes of the WebSocket frame/payload.
        // Any encoding conversion may change the byte sequence and break PONG handling.
        $hex = bin2hex($data);

        $payload = json_encode([
            'DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', // Server Socket
            'ClientIP' => $clientIP,
            'ClientPort' => $clientPort,
            'Type' => 0,
            'Buffer' => $hex
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_IO, '❌ JSON Encoding Error (pong envelope): ' . json_last_error_msg(), 0);
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, '📤 PONG (hex,len=' . strlen($hex) . '): ' . $hex, 0);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, 'PONG', 0);
        $this->SendDataToParent($payload);
    }

    protected function SendPayloadToChildren($data)
    {
        // An Childs weiterleiten
        $payload = json_encode([
            'DataID' => '{34A21C2C-646B-1014-D032-DF7E7A88B419}',
            'Buffer' => $data
        ]);
        $this->SendDataToChildren($payload);
    }

    private function GetSymconFirstName(): string
    {
        $email = @IPS_GetLicensee();
        if (empty($email) || strpos($email, '@') === false) {
            return 'Symcon';
        }
        $username = explode('@', $email)[0];

        // Trenne an Punkt, Unterstrich oder Bindestrich
        $parts = preg_split('/[\._\-]/', $username);

        // Nimm den ersten sinnvollen Teil
        $first = $parts[0] ?? 'Symcon';

        // Großschreibung des ersten Buchstabens
        $first = ucfirst(strtolower($first));

        return $first;
    }

    private function RegisterMdnsService(): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🔧 Registering DNS-SD service', 0);

        $mdnsID = @IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}')[0] ?? 0;
        if ($mdnsID === 0) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '⚠️ No DNS-SD Control instance found!', 0);
            return;
        }

        $entries = json_decode(IPS_GetProperty($mdnsID, 'Services'), true) ?? [];

        // mDNS instance name must be the unique driver_id (required by UC discovery)
        $serviceName = (string)$this->GetDriverId();
        $serviceType = '_uc-integration._tcp';
        $servicePort = self::DEFAULT_WS_PORT;

        // Prevent duplicates:
        // If there is already any _uc-integration._tcp service on the same port, do NOT add another one.
        // Reason: Users may already have created/edited the entry manually in DNS-SD (as in the screenshot),
        // and adding a second entry on the same port is confusing for discovery.
        $existingOnPort = array_filter($entries, function ($e) use ($serviceType, $servicePort) {
            $regType = $e['RegType'] ?? '';
            $port = (int)($e['Port'] ?? 0);
            return ($regType === $serviceType) && ($port === $servicePort);
        });

        if (!empty($existingOnPort)) {
            $names = array_map(fn($e) => ($e['Name'] ?? '?') . '@' . ($e['Port'] ?? '?'), array_values($existingOnPort));
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, 'ℹ️ mDNS entry already exists (RegType=' . $serviceType . ', Port=' . $servicePort . '): ' . json_encode($names) . ' – no additional entry will be added.', 0);
            return;
        }

        $first = $this->GetSymconFirstName();

        // Prefer Symcon network info (Sys_GetNetworkInfo) to obtain a stable IPv4 address.
        // This avoids hostname resolution issues in many Symcon setups.
        $ipv4 = trim((string)$this->GetHostIP());
        if ($ipv4 !== '' && !filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '⚠️ GetHostIP returned non-IPv4 value: ' . $ipv4, 0);
            $ipv4 = '';
        }
        // Avoid loopback
        if ($ipv4 !== '' && strpos($ipv4, '127.') === 0) {
            $ipv4 = '';
        }

        // NOTE: Most drivers use root path `/`. If you later introduce a dedicated endpoint, change this.
        $wsPath = '/';

        // If we can determine a stable IPv4, publish a full ws_url.
        // IMPORTANT (per UC docs): if ws_url is present, ws_path / wss / wss_port are ignored.
        // So we must NOT publish ws_path in that case to avoid confusion.
        $wsUrl = ($ipv4 !== '') ? ('ws://' . $ipv4 . ':' . $servicePort . $wsPath) : '';

        $txt = [
            // Required by UC discovery docs
            ['Value' => 'name=Symcon von ' . $first],
            ['Value' => 'developer=Fonzo'],
            ['Value' => 'ver=' . $this->GetModuleLibraryVersion()],

            // Optional but useful
            ['Value' => 'ver_api=' . self::Unfolded_Circle_API_Version],
            ['Value' => 'pwd=true'],
        ];

        if ($wsUrl !== '') {
            // ws_url overrides everything related to ws path/ssl
            $txt[] = ['Value' => 'ws_url=' . $wsUrl];
        } else {
            // No stable IPv4 found → fall back to host/port discovery; publish ws_path only.
            $txt[] = ['Value' => 'ws_path=' . $wsPath];
        }

        $newEntry = [
            'Name' => $serviceName,
            'RegType' => $serviceType,
            'Domain' => '',
            'Host' => '',
            'Port' => $servicePort,
            'TXTRecords' => $txt
        ];

        $entries[] = $newEntry;

        IPS_SetProperty($mdnsID, 'Services', json_encode(array_values($entries)));
        IPS_ApplyChanges($mdnsID);

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ mDNS entry added: ' . json_encode($newEntry), 0);
    }

    private function UnregisterMdnsService()
    {
        $mdnsID = @IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}')[0] ?? 0;
        if ($mdnsID === 0) {
            return;
        }

        $entries = json_decode(IPS_GetProperty($mdnsID, 'Services'), true) ?? [];
        $serviceType = '_uc-integration._tcp';
        $serviceName = (string)$this->GetDriverId();

        $filtered = array_filter($entries, function ($entry) use ($serviceType, $serviceName) {
            return ($entry['RegType'] ?? '') !== $serviceType || ($entry['Name'] ?? '') !== $serviceName;
        });

        IPS_SetProperty($mdnsID, 'Services', json_encode(array_values($filtered)));
        IPS_ApplyChanges($mdnsID);
    }

    /**
     * Prüft, ob das Symcon-Icon bereits auf Remote 3 existiert.
     *
     * @param string $apiKey
     * @param string $ip
     * @return bool
     */
    private function RemoteIconExists(string $apiKey, string $ip): bool
    {
        $url = "http://{$ip}/api/resources/Icon?page=1&limit=50";
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    "Authorization: $apiKey"
                ]
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '❌ Failed to retrieve icons from Remote 3', 0);
            return false;
        }

        $icons = json_decode($response, true);
        if (!is_array($icons)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '❌ Invalid JSON response received from Remote 3', 0);
            return false;
        }

        foreach ($icons as $icon) {
            if (($icon['id'] ?? '') === 'symcon_icon.png') {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ Symcon icon already exists on Remote 3', 0);
                return true;
            }
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, 'ℹ️ Symcon icon not found on Remote 3', 0);
        return false;
    }

    /**
     * Prüft und lädt das Symcon-Icon hoch, falls es nicht existiert.
     */
    private function CheckAndUploadSymconIcon(): void
    {
        $remotes = json_decode($this->ReadAttributeString('remote_cores'), true);
        if (!is_array($remotes)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '⚠️ Keine gültige Remote Core Liste gefunden', 0);
            return;
        }

        foreach ($remotes as $remote) {
            $ip = $remote['host'];
            $apiKey = $remote['api_key'];
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, "🔍 Prüfe Icon für Remote {$remote['name']} @ $ip", 0);

            if (!$this->RemoteIconExists($apiKey, $ip)) {
                $this->UploadSymconIcon($apiKey, $ip);
            }
        }
    }

    /**
     * Aktualisiert die Liste der Remote Core Instanzen und deren Daten.
     */
    public function RefreshRemoteCores()
    {
        $coreInstances = IPS_GetInstanceListByModuleID('{C810D534-2395-7C43-D0BE-6DEC069B2516}');
        $remotes = [];

        foreach ($coreInstances as $id) {
            $apiKey = @UCR_GetApiKey($id);
            if (empty($apiKey)) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, "⚠️ Kein API-Key für Instanz $id gefunden", 0);
                continue;
            }

            $remote = [
                'instance_id' => $id,
                'api_key' => $apiKey,
                'name' => IPS_GetProperty($id, 'name'),
                'hostname' => IPS_GetProperty($id, 'hostname'),
                'host' => IPS_GetProperty($id, 'host'),
                'remote_id' => IPS_GetProperty($id, 'remote_id'),
                'model' => IPS_GetProperty($id, 'model'),
                'version' => IPS_GetProperty($id, 'version'),
                'ver_api' => IPS_GetProperty($id, 'ver_api'),
                'https_port' => IPS_GetProperty($id, 'https_port')
            ];

            $remotes[] = $remote;
        }

        $this->WriteAttributeString('remote_cores', json_encode($remotes));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ Remote Cores aktualisiert: ' . json_encode($remotes), 0);
        return $remotes;
    }

    private function HandleEntityCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityType = $msgData['entity_type'] ?? '';

        switch ($entityType) {
            case 'button':
                $this->HandleButtonCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'climate':
                $this->HandleClimateCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'cover':
                $this->HandleCoverCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'ir_emitter':
                $this->HandleIREmitterCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'light':
                $this->HandleLightCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'media_player':
                $this->HandleMediaPlayerCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'remote':
                $this->HandleRemoteCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'switch':
                $this->HandleSwitchCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            case 'select':
                $this->HandleSelectCommand($msgData, $clientIP, $clientPort, $reqId);
                break;

            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unbekannter entity_type: $entityType", 0);
                break;
        }
    }

    private function HandleSelectCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = (string)($msgData['entity_id'] ?? '');
        $cmdId = (string)($msgData['cmd_id'] ?? '');
        $params = $msgData['params'] ?? [];

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔽 Select-Command: $cmdId für $entityId", 0);

        if (!preg_match('/^select_(\d+)_(\d+)$/', $entityId, $match)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Konnte instance_id / var_id aus Select-Entity-ID nicht extrahieren: $entityId", 0);
            return;
        }

        $instanceId = (int)$match[1];
        $varId = (int)$match[2];
        $lockName = 'UCR_' . $instanceId . '_' . $varId;

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' konnte nicht gesetzt werden (Timeout)", 0);
            return;
        }

        try {
            $mapping = json_decode((string)$this->ReadPropertyString('select_mapping'), true);
            if (!is_array($mapping)) {
                $mapping = [];
            }

            $found = null;
            foreach ($mapping as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                if ((int)($entry['instance_id'] ?? 0) === $instanceId && (int)($entry['var_id'] ?? 0) === $varId) {
                    $found = $entry;
                    break;
                }
            }

            if ($found === null) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender Select-Eintrag gefunden für Entity-ID $entityId", 0);
                return;
            }

            if (!@IPS_VariableExists($varId)) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Select-Variable existiert nicht: var_id=$varId", 0);
                return;
            }

            $targetValue = null;

            switch ($cmdId) {
                case 'select_option':
                    $option = $params['option'] ?? ($params['selected_option'] ?? null);
                    if (!is_string($option) || trim($option) === '') {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ select_option ohne gültigen Optionsnamen empfangen", 0);
                        return;
                    }
                    $targetValue = $this->GetSelectProfileValueByLabel($varId, trim($option));
                    if ($targetValue === null) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Keine passende Select-Option im Profil gefunden: '" . trim($option) . "'", 0);
                        return;
                    }
                    break;

                case 'select_next':
                    $targetValue = $this->GetRelativeSelectProfileValue($varId, +1);
                    break;

                case 'select_previous':
                    $targetValue = $this->GetRelativeSelectProfileValue($varId, -1);
                    break;

                case 'select_first':
                    $targetValue = $this->GetBoundarySelectProfileValue($varId, true);
                    break;

                case 'select_last':
                    $targetValue = $this->GetBoundarySelectProfileValue($varId, false);
                    break;

                default:
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unbekannter Select-Command: $cmdId", 0);
                    return;
            }

            if ($targetValue === null) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein Zielwert für Select-Command ermittelt: $cmdId", 0);
                return;
            }

            $currentValue = @GetValue($varId);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔽 RequestAction für Select var_id=$varId | current=" . json_encode($currentValue) . " → target=" . json_encode($targetValue), 0);
            RequestAction($varId, $targetValue);
            usleep(10000);

            $updatedValue = @GetValue($varId);
            $updatedLabel = $this->GetProfileValueLabelForSelect($varId, $updatedValue);
            if ($updatedLabel === '') {
                $updatedLabel = (string)$updatedValue;
            }

            $options = $this->GetSelectProfileLabels($varId);

            $attributes = [
                'options' => $options,
                'current_option' => $updatedLabel,
                'state' => 'ON'
            ];

            $this->SendEntityChange($entityId, 'select', $attributes);
            $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        } finally {
            IPS_SemaphoreLeave($lockName);
        }
    }

    private function GetSelectProfileLabels(int $varId): array
    {
        if (!@IPS_VariableExists($varId)) {
            return [];
        }

        $var = @IPS_GetVariable($varId);
        if (!is_array($var)) {
            return [];
        }

        $profile = trim((string)($var['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($var['VariableProfile'] ?? ''));
        }

        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            return [];
        }

        $profileData = @IPS_GetVariableProfile($profile);
        if (!is_array($profileData)) {
            return [];
        }

        $labels = [];
        foreach (($profileData['Associations'] ?? []) as $assoc) {
            if (!is_array($assoc)) {
                continue;
            }
            $label = trim((string)($assoc['Name'] ?? ''));
            if ($label === '') {
                $label = (string)($assoc['Value'] ?? '');
            }
            if ($label === '') {
                continue;
            }
            $labels[] = $label;
        }

        return array_values(array_unique($labels));
    }

    private function GetProfileValueLabelForSelect(int $varId, $value): string
    {
        if (!@IPS_VariableExists($varId)) {
            return '';
        }

        $var = @IPS_GetVariable($varId);
        if (!is_array($var)) {
            return '';
        }

        $profile = trim((string)($var['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($var['VariableProfile'] ?? ''));
        }

        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            return '';
        }

        $profileData = @IPS_GetVariableProfile($profile);
        if (!is_array($profileData)) {
            return '';
        }

        foreach (($profileData['Associations'] ?? []) as $assoc) {
            if (!is_array($assoc)) {
                continue;
            }
            if ((string)($assoc['Value'] ?? '') === (string)$value) {
                $label = trim((string)($assoc['Name'] ?? ''));
                return $label !== '' ? $label : (string)($assoc['Value'] ?? '');
            }
        }

        return '';
    }

    private function GetSelectProfileValueByLabel(int $varId, string $searchLabel)
    {
        if (!@IPS_VariableExists($varId)) {
            return null;
        }

        $var = @IPS_GetVariable($varId);
        if (!is_array($var)) {
            return null;
        }

        $profile = trim((string)($var['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($var['VariableProfile'] ?? ''));
        }

        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            return null;
        }

        $profileData = @IPS_GetVariableProfile($profile);
        if (!is_array($profileData)) {
            return null;
        }

        $searchLabelNorm = mb_strtolower(trim($searchLabel));

        foreach (($profileData['Associations'] ?? []) as $assoc) {
            if (!is_array($assoc)) {
                continue;
            }
            $label = trim((string)($assoc['Name'] ?? ''));
            if ($label === '') {
                $label = (string)($assoc['Value'] ?? '');
            }
            if ($label === '') {
                continue;
            }
            if (mb_strtolower($label) === $searchLabelNorm) {
                return $assoc['Value'] ?? null;
            }
        }

        return null;
    }

    private function GetRelativeSelectProfileValue(int $varId, int $direction)
    {
        if (!@IPS_VariableExists($varId)) {
            return null;
        }

        $var = @IPS_GetVariable($varId);
        if (!is_array($var)) {
            return null;
        }

        $profile = trim((string)($var['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($var['VariableProfile'] ?? ''));
        }

        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            return null;
        }

        $profileData = @IPS_GetVariableProfile($profile);
        if (!is_array($profileData)) {
            return null;
        }

        $associations = $profileData['Associations'] ?? [];
        if (!is_array($associations) || empty($associations)) {
            return null;
        }

        usort($associations, static function ($a, $b) {
            return ((float)($a['Value'] ?? 0)) <=> ((float)($b['Value'] ?? 0));
        });

        $currentValue = @GetValue($varId);
        $currentIndex = null;

        foreach ($associations as $idx => $assoc) {
            if ((string)($assoc['Value'] ?? '') === (string)$currentValue) {
                $currentIndex = $idx;
                break;
            }
        }

        if ($currentIndex === null) {
            return null;
        }

        $targetIndex = $currentIndex + $direction;
        if ($targetIndex < 0) {
            $targetIndex = 0;
        }
        if ($targetIndex >= count($associations)) {
            $targetIndex = count($associations) - 1;
        }

        return $associations[$targetIndex]['Value'] ?? null;
    }

    private function GetBoundarySelectProfileValue(int $varId, bool $first)
    {
        if (!@IPS_VariableExists($varId)) {
            return null;
        }

        $var = @IPS_GetVariable($varId);
        if (!is_array($var)) {
            return null;
        }

        $profile = trim((string)($var['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($var['VariableProfile'] ?? ''));
        }

        if ($profile === '' || !@IPS_VariableProfileExists($profile)) {
            return null;
        }

        $profileData = @IPS_GetVariableProfile($profile);
        if (!is_array($profileData)) {
            return null;
        }

        $associations = $profileData['Associations'] ?? [];
        if (!is_array($associations) || empty($associations)) {
            return null;
        }

        usort($associations, static function ($a, $b) {
            return ((float)($a['Value'] ?? 0)) <=> ((float)($b['Value'] ?? 0));
        });

        $assoc = $first ? reset($associations) : end($associations);
        if (!is_array($assoc)) {
            return null;
        }

        return $assoc['Value'] ?? null;
    }

    private function HandleButtonCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔘 Button-Command: $cmdId für $entityId", 0);
        // Semaphore Lock hinzufügen (analog zu HandleSwitchCommand)
        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Konnte Objekt-ID aus Entity-ID nicht extrahieren: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' konnte nicht gesetzt werden (Timeout)", 0);
            return;
        }

        if ($cmdId === 'push') {
            $mapping = json_decode($this->ReadPropertyString('button_mapping'), true);
            foreach ($mapping as $entry) {
                if ('button_' . $entry['script_id'] === $entityId) {
                    if (IPS_ScriptExists($entry['script_id'])) {
                        IPS_RunScript($entry['script_id']);
                    } else {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Skript-ID {$entry['script_id']} existiert nicht", 0);
                    }
                    $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
                    IPS_SemaphoreLeave($lockName);
                    return;
                }
            }
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender Button gefunden für Entity-ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
        } else {
            IPS_SemaphoreLeave($lockName);
        }
    }

    private function HandleClimateCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🌡️ Climate-Command: $cmdId für $entityId", 0);

        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Konnte Objekt-ID aus Entity-ID nicht extrahieren: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' konnte nicht gesetzt werden (Timeout)", 0);
            return;
        }

        $climateMapping = json_decode($this->ReadPropertyString('climate_mapping'), true);
        $status_var_id = $current_temp_var_id = $target_temp_var_id = $mode_var_id = null;

        foreach ($climateMapping as $entry) {
            if ('climate_' . $entry['instance_id'] === $entityId) {
                $status_var_id = $entry['status_var_id'] ?? null;
                $current_temp_var_id = $entry['current_temp_var_id'] ?? null;
                $target_temp_var_id = $entry['target_temp_var_id'] ?? null;
                $mode_var_id = $entry['mode_var_id'] ?? null;
                break;
            }
        }

        if (!$status_var_id) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender Climate-Eintrag gefunden für Entity-ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
            return;
        }

        $attributes = [];

        switch ($cmdId) {
            case 'on':
                if ($status_var_id) {
                    RequestAction($status_var_id, true);
                    $attributes['state'] = 'ON';
                }
                break;
            case 'off':
                if ($status_var_id) {
                    RequestAction($status_var_id, false);
                    $attributes['state'] = 'OFF';
                }
                break;
            case 'target_temperature':
                if (isset($params['target_temperature']) && $target_temp_var_id) {
                    RequestAction($target_temp_var_id, (float)$params['target_temperature']);
                    $attributes['temperature'] = (float)$params['target_temperature'];
                }
                break;
            case 'hvac_mode':
                if (isset($params['hvac_mode']) && $mode_var_id) {
                    RequestAction($mode_var_id, $params['hvac_mode']);
                    $attributes['hvac_mode'] = $params['hvac_mode'];
                }
                break;
            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unbekannter Climate-Command: $cmdId", 0);
                IPS_SemaphoreLeave($lockName);
                return;
        }

        if (!empty($attributes)) {
            $this->SendEntityChange($entityId, 'climate', $attributes);
        }
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        IPS_SemaphoreLeave($lockName);
    }

    private function HandleCoverCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';
        $params = $msgData['params'] ?? [];

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🪟 Cover-Command: $cmdId für $entityId", 0);

        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Konnte Objekt-ID aus Entity-ID nicht extrahieren: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' konnte nicht gesetzt werden (Timeout)", 0);
            return;
        }

        $mapping = json_decode($this->ReadPropertyString('cover_mapping'), true);
        $positionVar = $controlVar = null;
        $entryFound = null;
        foreach ($mapping as $entry) {
            if ('cover_' . $entry['instance_id'] === $entityId) {
                $positionVar = $entry['position_var_id'] ?? null;
                $controlVar = $entry['control_var_id'] ?? null;
                $entryFound = $entry;
                break;
            }
        }

        if (!$positionVar && !$controlVar) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender Cover-Eintrag gefunden für Entity-ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
            return;
        }

        $attributes = [];

        switch ($cmdId) {
            case 'open':
                // Prefer position var if available (profile-aware), else use control var.
                if ($positionVar && IPS_VariableExists($positionVar)) {
                    $currentRemote = $this->ConvertCoverPositionToRemote($positionVar, @GetValue($positionVar));
                    $targetRemote = 100;
                    $symconValue = $this->ConvertCoverPositionFromRemote($positionVar, $targetRemote);
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Öffne Cover per PositionVar $positionVar → remote=$targetRemote symcon=" . json_encode($symconValue), 0);
                    RequestAction($positionVar, $symconValue);
                    $attributes['state'] = $this->GetCoverMoveStateFromRemotePos($currentRemote, $targetRemote);
                    $attributes['position'] = $targetRemote;
                } elseif ($controlVar && IPS_VariableExists($controlVar)) {
                    $value = $this->GetProfileValueByLabel($controlVar, 'öffn');
                    if ($value === null) {
                        $value = $this->GetProfileValueByLabel($controlVar, 'open');
                    }

                    if ($value !== null) {
                        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Öffne Cover (RequestAction $controlVar mit Profilwert $value)", 0);
                        RequestAction($controlVar, $value);
                        $attributes['state'] = 'OPENING';
                        $attributes['position'] = 100;
                    } else {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender OPEN Wert im Variablenprofil gefunden", 0);
                    }
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Keine gültige Variable für open (positionVar/controlVar)", 0);
                }
                break;

            case 'close':
                if ($positionVar && IPS_VariableExists($positionVar)) {
                    $currentRemote = $this->ConvertCoverPositionToRemote($positionVar, @GetValue($positionVar));
                    $targetRemote = 0;
                    $symconValue = $this->ConvertCoverPositionFromRemote($positionVar, $targetRemote);
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Schließe Cover per PositionVar $positionVar → remote=$targetRemote symcon=" . json_encode($symconValue), 0);
                    RequestAction($positionVar, $symconValue);
                    $attributes['state'] = $this->GetCoverMoveStateFromRemotePos($currentRemote, $targetRemote);
                    $attributes['position'] = $targetRemote;
                } elseif ($controlVar && IPS_VariableExists($controlVar)) {
                    $value = $this->GetProfileValueByLabel($controlVar, 'schließ');
                    if ($value === null) {
                        $value = $this->GetProfileValueByLabel($controlVar, 'close');
                    }

                    if ($value !== null) {
                        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Schließe Cover (RequestAction $controlVar mit Profilwert $value)", 0);
                        RequestAction($controlVar, $value);
                        $attributes['state'] = 'CLOSING';
                        $attributes['position'] = 0;
                    } else {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender CLOSE Wert im Variablenprofil gefunden", 0);
                    }
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Keine gültige Variable für close (positionVar/controlVar)", 0);
                }
                break;

            case 'stop':
                if ($controlVar && IPS_VariableExists($controlVar)) {
                    $value = $this->GetProfileValueByLabel($controlVar, 'stop');

                    if ($value !== null) {
                        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Stoppe Cover (RequestAction $controlVar mit Profilwert $value)", 0);
                        RequestAction($controlVar, $value);
                    } else {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender STOP Wert im Variablenprofil gefunden", 0);
                    }
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ controlVar für stop fehlt oder existiert nicht", 0);
                }

                // UC cover has no STOPPED state. Send a snapshot based on current position if possible.
                if ($positionVar && IPS_VariableExists($positionVar)) {
                    $posRemote = $this->ConvertCoverPositionToRemote($positionVar, @GetValue($positionVar));
                    $attributes['position'] = $posRemote;
                    $attributes['state'] = $this->GetCoverSnapshotStateFromRemotePos($posRemote);
                } else {
                    $attributes['state'] = 'UNKNOWN';
                }
                break;

            case 'position':
                if (isset($params['position']) && $positionVar && IPS_VariableExists($positionVar)) {
                    $targetRemote = (int)$params['position'];
                    $targetRemote = max(0, min(100, $targetRemote));

                    $currentRemote = $this->ConvertCoverPositionToRemote($positionVar, @GetValue($positionVar));
                    $symconValue = $this->ConvertCoverPositionFromRemote($positionVar, $targetRemote);

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔧 Zielposition remote=$targetRemote (current=$currentRemote) → RequestAction $positionVar symcon=" . json_encode($symconValue), 0);
                    RequestAction($positionVar, $symconValue);

                    // Optimistic UI: show movement direction; UC has no SETTING.
                    $attributes['state'] = $this->GetCoverMoveStateFromRemotePos($currentRemote, $targetRemote);
                    $attributes['position'] = $targetRemote;
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Position-Parameter oder positionVar fehlt / existiert nicht", 0);
                }
                break;

            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unbekannter Cover-Command: $cmdId", 0);
                IPS_SemaphoreLeave($lockName);
                return;
        }

        if (!empty($attributes)) {
            $this->SendEntityChange($entityId, 'cover', $attributes);
        }
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        IPS_SemaphoreLeave($lockName);
    }

    private function GetProfileValueByLabel(int $varId, string $search): ?int
    {
        if (!IPS_VariableExists($varId)) {
            return null;
        }

        $var = IPS_GetVariable($varId);
        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

        if (!$profile || !IPS_VariableProfileExists($profile)) {
            return null;
        }

        $profileData = IPS_GetVariableProfile($profile);

        // Normalize search keyword
        $search = strtolower($search);

        // Known keyword groups
        $keywords = [
            'open' => ['open', 'öffn', 'auf', 'hoch', 'up'],
            'close' => ['close', 'schließ', 'zu', 'down', 'runter'],
            'stop' => ['stop', 'halt', 'stopp']
        ];

        $searchTerms = $keywords[$search] ?? [$search];

        foreach ($profileData['Associations'] as $assoc) {
            $label = strtolower((string)$assoc['Name']);

            foreach ($searchTerms as $term) {
                if (strpos($label, $term) !== false) {
                    return (int)$assoc['Value'];
                }
            }
        }

        return null;
    }

    private function HandleIREmitterCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📡 IR Emitter command: $cmdId for $entityId", 0);
        // TODO: Ansteuerung einer Climate-Instanz basierend auf cmdId
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
    }

    private function HandleLightCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';
        $params = $msgData['params'] ?? [];

        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Konnte Objekt-ID aus Entity-ID nicht extrahieren: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' konnte nicht gesetzt werden (Timeout)", 0);
            return;
        }

        if (!empty($params)) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "💡 Light-Command: $cmdId für $entityId (mit Parametern: " . json_encode($params) . ")", 0);
        } else {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "💡 Light-Command: $cmdId für $entityId", 0);
        }

        $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
        $switch_var_id = $brightness_var_id = $color_var_id = $color_temp_var_id = null;

        foreach ($lightMapping as $entry) {
            if ('light_' . $entry['instance_id'] === $entityId) {
                $switch_var_id = $entry['switch_var_id'] ?? null;
                $brightness_var_id = $entry['brightness_var_id'] ?? null;
                $color_var_id = $entry['color_var_id'] ?? null;
                $color_temp_var_id = $entry['color_temp_var_id'] ?? null;
                break;
            }
        }

        if (!$switch_var_id) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Kein passender Light-Eintrag gefunden für Entity-ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
            return;
        }

        // Unterstützte cmd_id Werte: on, off, toggle
        $newState = null;
        $currentState = @GetValue($switch_var_id);

        if ($cmdId === 'on') {
            $newState = true;
        } elseif ($cmdId === 'off') {
            $newState = false;
        } elseif ($cmdId === 'toggle') {
            if (is_bool($currentState)) {
                $newState = !$currentState;
            }
        }
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "💡 Light-Command: $cmdId für $entityId, setze Status von " . json_encode($currentState) . " auf " . json_encode($newState), 0);
        // NEU: Block ersetzt, damit Parameter immer weiterverarbeitet werden!
        if ($newState !== null && $newState !== $currentState) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ RequestAction für Switch VarID $switch_var_id mit Wert " . json_encode($newState), 0);
            RequestAction($switch_var_id, $newState);
            usleep(10000); // Wartezeit zur Synchronisation
        }

        // Auch wenn kein Schaltvorgang notwendig war, verarbeite die Parameter weiter unten

        // Auswertung der optionalen Parameter
        if (isset($params['brightness']) && $brightness_var_id && IPS_VariableExists($brightness_var_id)) {
            $brightness = $this->ConvertBrightnessToSymcon((int)$params['brightness'], $brightness_var_id);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Set brightness to $brightness", 0);
            RequestAction($brightness_var_id, $brightness);
            usleep(10000);
        }

        if (isset($params['color_temperature']) && $color_temp_var_id && IPS_VariableExists($color_temp_var_id)) {
            $remoteValue = (int)$params['color_temperature'];
            $symconValue = $this->ConvertColorTemperatureFromRemote($color_temp_var_id, $remoteValue);

            // RequestAction must receive the correct PHP type for the target variable.
            $varInfo = IPS_GetVariable((int)$color_temp_var_id);
            $varType = (int)($varInfo['VariableType'] ?? 1); // 1=int, 2=float
            $requestValue = ($varType === 2) ? (float)$symconValue : (int)round($symconValue);

            $beforeValue = @GetValue((int)$color_temp_var_id);
            $this->Debug(
                __FUNCTION__,
                self::LV_TRACE,
                self::TOPIC_ENTITY,
                "✅ Set color temperature VarID $color_temp_var_id | remote=$remoteValue → symcon=" . json_encode($requestValue) . " | before=" . json_encode($beforeValue),
                0
            );

            RequestAction((int)$color_temp_var_id, $requestValue);
            usleep(10000);

            $afterValue = @GetValue((int)$color_temp_var_id);
            $this->Debug(
                __FUNCTION__,
                self::LV_TRACE,
                self::TOPIC_ENTITY,
                "🌡️ Color temperature write result VarID $color_temp_var_id | after=" . json_encode($afterValue),
                0
            );
        }

        if ((isset($params['hue']) || isset($params['saturation'])) && $color_var_id && IPS_VariableExists($color_var_id)) {
            $h = $params['hue'] ?? 0;
            $s = $params['saturation'] ?? 0;
            $hexColor = $this->ConvertHueSaturationToHexColor($h, $s);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Set color to HEX $hexColor (aus Hue $h / Sat $s)", 0);
            RequestAction($color_var_id, $hexColor);
            usleep(10000);
        }

        // Aktualisiere den tatsächlichen Zustand nach RequestAction
        $updatedState = @GetValue($switch_var_id);
        $attributes = ['state' => $updatedState ? 'ON' : 'OFF'];
        if (isset($params['brightness'])) {
            $attributes['brightness'] = $this->ConvertBrightnessToRemote($brightness_var_id);
        }
        if (!empty($color_var_id) && IPS_VariableExists($color_var_id)) {
            $hex = @GetValue($color_var_id);
            $hs = $this->ConvertHexColorToHueSaturation((int)$hex);
            $attributes['hue'] = $hs['hue'];
            $attributes['saturation'] = $hs['saturation'];
        }
        if (!empty($color_temp_var_id) && IPS_VariableExists($color_temp_var_id)) {
            $symconValue = @GetValue($color_temp_var_id);
            $attributes['color_temperature'] = $this->ConvertColorTemperatureToRemote($color_temp_var_id, $symconValue);
        }
        $this->SendEntityChange($entityId, 'light', $attributes);
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        IPS_SemaphoreLeave($lockName);
    }

    private function ConvertBrightnessToSymcon(int $remoteValue, int $varId): int
    {
        $var = IPS_GetVariable($varId);
        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

        if (!$profile || !IPS_VariableProfileExists($profile)) {
            return $remoteValue;
        }

        $profileData = IPS_GetVariableProfile($profile);
        $min = $profileData['MinValue'];
        $max = $profileData['MaxValue'];

        if ($min >= $max) {
            return $remoteValue;
        }

        return (int)round(($remoteValue / 255) * ($max - $min) + $min);
    }

    private function ConvertBrightnessToRemote(int $varId): int
    {
        $var = IPS_GetVariable($varId);
        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

        if (!$profile || !IPS_VariableProfileExists($profile)) {
            return (int)GetValue($varId);
        }

        $profileData = IPS_GetVariableProfile($profile);
        $min = $profileData['MinValue'];
        $max = $profileData['MaxValue'];

        if ($min >= $max) {
            return (int)GetValue($varId);
        }

        $symconValue = (int)GetValue($varId);
        return (int)round((($symconValue - $min) / ($max - $min)) * 255);
    }

    /**
     * Snapshot state for the Remote based on position only.
     */
    private function GetCoverSnapshotStateFromRemotePos(int $remotePos): string
    {
        $remotePos = max(0, min(100, $remotePos));
        return ($remotePos <= 0) ? 'CLOSED' : 'OPEN';
    }

    /**
     * Movement state helper (OPENING/CLOSING) from current/target remote position.
     */
    private function GetCoverMoveStateFromRemotePos(int $currentRemotePos, int $targetRemotePos): string
    {
        $currentRemotePos = max(0, min(100, $currentRemotePos));
        $targetRemotePos = max(0, min(100, $targetRemotePos));
        if ($targetRemotePos === $currentRemotePos) {
            return $this->GetCoverSnapshotStateFromRemotePos($targetRemotePos);
        }
        return ($targetRemotePos > $currentRemotePos) ? 'OPENING' : 'CLOSING';
    }

    private function ConvertHueSaturationToHexColor(int $hue, int $saturation): int
    {
        $h = $hue / 360;
        $s = $saturation / 255;
        $v = 1;

        $i = floor($h * 6);
        $f = $h * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        switch ($i % 6) {
            case 0:
                $r = $v;
                $g = $t;
                $b = $p;
                break;
            case 1:
                $r = $q;
                $g = $v;
                $b = $p;
                break;
            case 2:
                $r = $p;
                $g = $v;
                $b = $t;
                break;
            case 3:
                $r = $p;
                $g = $q;
                $b = $v;
                break;
            case 4:
                $r = $t;
                $g = $p;
                $b = $v;
                break;
            case 5:
                $r = $v;
                $g = $p;
                $b = $q;
                break;
        }

        return ((int)($r * 255) << 16) + ((int)($g * 255) << 8) + (int)($b * 255);
    }

    private function ConvertHexColorToHueSaturation(int $hexColor): array
    {
        $r = (($hexColor >> 16) & 0xFF) / 255;
        $g = (($hexColor >> 8) & 0xFF) / 255;
        $b = ($hexColor & 0xFF) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // Hue
        if ($delta == 0) {
            $h = 0;
        } elseif ($max == $r) {
            $h = 60 * fmod((($g - $b) / $delta), 6);
        } elseif ($max == $g) {
            $h = 60 * ((($b - $r) / $delta) + 2);
        } else {
            $h = 60 * ((($r - $g) / $delta) + 4);
        }

        if ($h < 0) {
            $h += 360;
        }

        // Saturation
        $s = ($max == 0) ? 0 : $delta / $max;

        return [
            'hue' => (int)round($h),
            'saturation' => (int)round($s * 255)
        ];
    }

    private function HandleMediaPlayerCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🎵 MediaPlayer-Command: $cmdId for $entityId", 0);

        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Could not extract object ID from entity ID: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' could not be acquired (timeout)", 0);
            return;
        }

        $mapping = json_decode($this->ReadPropertyString('media_player_mapping'), true);
        $found = null;
        foreach ($mapping as $entry) {
            if (!isset($entry['features']) || !is_array($entry['features'])) {
                continue;
            }
            foreach ($entry['features'] as $feature) {
                if ('media_player_' . $entry['instance_id'] === $entityId) {
                    $found = $entry;
                    break 2;
                }
            }
        }

        if (!$found) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ No matching media player mapping found for entity ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
            return;
        }

        $attributes = [];

        // Build feature map for lookup
        $featureMap = [];
        foreach ($found['features'] as $feature) {
            if (isset($feature['feature_key']) && isset($feature['var_id'])) {
                $featureMap[$feature['feature_key']] = $feature['var_id'];
            }
        }

        switch ($cmdId) {
            case 'on':
            case 'off':
            case 'toggle':
                if (isset($featureMap['on_off'])) {
                    $newValue = ($cmdId === 'toggle') ? !GetValue($featureMap['on_off']) : ($cmdId === 'on');
                    RequestAction($featureMap['on_off'], $newValue);
                    $attributes['state'] = $newValue ? 'ON' : 'OFF';
                }
                break;

            case 'play_pause':
                if (isset($featureMap['symcon_control'])) {
                    $varId = $featureMap['symcon_control'];
                    if (IPS_VariableExists($varId)) {
                        $var = IPS_GetVariable($varId);
                        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

                        if ($profile && IPS_VariableProfileExists($profile)) {
                            $profileData = IPS_GetVariableProfile($profile);
                            $currentValue = GetValue($varId);
                            $newValue = null;

                            foreach ($profileData['Associations'] as $assoc) {
                                $label = strtolower($assoc['Name']);
                                if (strpos($label, 'play') !== false && (string)$assoc['Value'] !== (string)$currentValue) {
                                    $newValue = $assoc['Value'];
                                    $attributes['state'] = 'PLAYING';
                                    break;
                                }
                                if (strpos($label, 'pause') !== false && (string)$assoc['Value'] !== (string)$currentValue) {
                                    $newValue = $assoc['Value'];
                                    $attributes['state'] = 'PAUSED';
                                    break;
                                }
                            }

                            if ($newValue !== null) {
                                RequestAction($varId, $newValue);
                            } else {
                                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⏭ No suitable alternative for play/pause found in profile", 0);
                            }
                        } else {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠ No valid profile available for play/pause", 0);
                        }
                    }
                }
                break;

            case 'back':
                if (isset($featureMap['symcon_control'])) {
                    $varId = $featureMap['symcon_control'];
                    if (IPS_VariableExists($varId)) {
                        $var = IPS_GetVariable($varId);
                        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

                        if ($profile && IPS_VariableProfileExists($profile)) {
                            $profileData = IPS_GetVariableProfile($profile);
                            $currentValue = GetValue($varId);
                            $newValue = null;

                            foreach ($profileData['Associations'] as $assoc) {
                                $label = strtolower($assoc['Name']);
                                if ((strpos($label, 'back') !== false || strpos($label, 'zurück') !== false) && (string)$assoc['Value'] !== (string)$currentValue) {
                                    $newValue = $assoc['Value'];
                                    break;
                                }
                            }

                            if ($newValue !== null) {
                                RequestAction($varId, $newValue);
                                $attributes['state'] = strtoupper($cmdId);
                            } else {
                                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⏭ No suitable alternative for $cmdId found in profile", 0);
                            }
                        } else {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠ No valid profile available for $cmdId", 0);
                        }
                    }
                }
                break;
            case 'stop':
            case 'previous':
            case 'next':
            case 'fast_forward':
            case 'rewind':
                if (isset($featureMap['symcon_control'])) {
                    $varId = $featureMap['symcon_control'];
                    if (IPS_VariableExists($varId)) {
                        $var = IPS_GetVariable($varId);
                        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

                        if ($profile && IPS_VariableProfileExists($profile)) {
                            $profileData = IPS_GetVariableProfile($profile);
                            $currentValue = GetValue($varId);
                            $newValue = null;

                            foreach ($profileData['Associations'] as $assoc) {
                                $label = strtolower($assoc['Name']);
                                if (
                                    (strpos($label, 'stop') !== false && $cmdId === 'stop') ||
                                    (strpos($label, 'previous') !== false && $cmdId === 'previous') ||
                                    (strpos($label, 'next') !== false && $cmdId === 'next') ||
                                    ((strpos($label, 'fast') !== false || strpos($label, 'vor') !== false) && strpos($label, 'forward') !== false && $cmdId === 'fast_forward') ||
                                    (strpos($label, 'rewind') !== false || strpos($label, 'zurück') !== false && $cmdId === 'rewind')
                                ) {
                                    if ((string)$assoc['Value'] !== (string)$currentValue) {
                                        $newValue = $assoc['Value'];
                                        break;
                                    }
                                }
                            }

                            if ($newValue !== null) {
                                RequestAction($varId, $newValue);
                                $attributes['state'] = strtoupper($cmdId);
                            } else {
                                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⏭ No suitable alternative for $cmdId found in profile", 0);
                            }
                        } else {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠ No valid profile available for $cmdId", 0);
                        }
                    }
                }
                break;
            case 'cursor_up':
            case 'cursor_down':
            case 'cursor_left':
            case 'cursor_right':
            case 'cursor_enter':
                if (isset($featureMap['symcon_dpad'])) {
                    $varId = $featureMap['symcon_dpad'];
                    if (IPS_VariableExists($varId)) {
                        $var = IPS_GetVariable($varId);
                        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

                        if ($profile && IPS_VariableProfileExists($profile)) {
                            $profileData = IPS_GetVariableProfile($profile);
                            $currentValue = GetValue($varId);
                            $newValue = null;

                            foreach ($profileData['Associations'] as $assoc) {
                                $label = strtolower($assoc['Name']);
                                if (
                                    (strpos($label, 'up') !== false && $cmdId === 'cursor_up') ||
                                    (strpos($label, 'down') !== false && $cmdId === 'cursor_down') ||
                                    (strpos($label, 'left') !== false && $cmdId === 'cursor_left') ||
                                    (strpos($label, 'right') !== false && $cmdId === 'cursor_right') ||
                                    (strpos($label, 'enter') !== false && $cmdId === 'cursor_enter')
                                ) {
                                    if ((string)$assoc['Value'] !== (string)$currentValue) {
                                        $newValue = $assoc['Value'];
                                        break;
                                    }
                                }
                            }

                            if ($newValue !== null) {
                                RequestAction($varId, $newValue);
                                $attributes['state'] = strtoupper($cmdId);
                            } else {
                                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⏭ No suitable alternative for $cmdId found in profile", 0);
                            }
                        } else {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠ No valid profile available for $cmdId", 0);
                        }
                    }
                }
                break;
            case 'digit_0':
            case 'digit_1':
            case 'digit_2':
            case 'digit_3':
            case 'digit_4':
            case 'digit_5':
            case 'digit_6':
            case 'digit_7':
            case 'digit_8':
            case 'digit_9':
                if (isset($featureMap['symcon_numpad'])) {
                    $varId = $featureMap['symcon_numpad'];
                    if (IPS_VariableExists($varId)) {
                        $var = IPS_GetVariable($varId);
                        $profile = $var['VariableCustomProfile'] ?: $var['VariableProfile'];

                        if ($profile && IPS_VariableProfileExists($profile)) {
                            $profileData = IPS_GetVariableProfile($profile);
                            $digit = str_replace('digit_', '', $cmdId);
                            $targetValue = null;

                            foreach ($profileData['Associations'] as $assoc) {
                                if ((string)$assoc['Name'] === $digit) {
                                    $targetValue = $assoc['Value'];
                                    break;
                                }
                            }

                            if ($targetValue !== null) {
                                RequestAction($varId, $targetValue);
                                $attributes['state'] = strtoupper($cmdId);
                            } else {
                                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⏭ No matching digit $digit found in profile", 0);
                            }
                        } else {
                            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠ No valid profile available for $cmdId", 0);
                        }
                    }
                }
                break;
            case 'function_red':
            case 'function_green':
            case 'function_yellow':
            case 'function_blue':
            case 'home':
            case 'menu':
            case 'context_menu':
            case 'guide':
            case 'info':
            case 'back':
            case 'record':
            case 'my_recordings':
            case 'live':
            case 'eject':
            case 'open_close':
            case 'audio_track':
            case 'subtitle':
            case 'settings':
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Command $cmdId is documented but requires manual mapping or script execution", 0);
                break;

            case 'seek':
                if (isset($msgData['params']['media_position']) && isset($featureMap['media_position'])) {
                    RequestAction($featureMap['media_position'], (int)$msgData['params']['media_position']);
                    $attributes['media_position'] = (int)$msgData['params']['media_position'];
                }
                break;

            case 'volume':
                if (isset($msgData['params']['volume']) && isset($featureMap['volume'])) {
                    RequestAction($featureMap['volume'], (float)$msgData['params']['volume']);
                    $attributes['volume'] = (float)$msgData['params']['volume'];
                }
                break;

            case 'volume_up':
            case 'volume_down':
                if (isset($featureMap['volume']) && IPS_VariableExists($featureMap['volume'])) {
                    $cur = GetValue($featureMap['volume']);
                    $delta = ($cmdId === 'volume_up') ? 5 : -5;
                    RequestAction($featureMap['volume'], max(0, $cur + $delta));
                    $attributes['volume'] = max(0, $cur + $delta);
                }
                break;

            case 'mute_toggle':
                if (isset($featureMap['muted'])) {
                    $val = GetValue($featureMap['muted']);
                    RequestAction($featureMap['muted'], !$val);
                    $attributes['muted'] = !$val;
                }
                break;

            case 'mute':
                if (isset($featureMap['muted'])) {
                    RequestAction($featureMap['muted'], true);
                    $attributes['muted'] = true;
                }
                break;

            case 'unmute':
                if (isset($featureMap['muted'])) {
                    RequestAction($featureMap['muted'], false);
                    $attributes['muted'] = false;
                }
                break;

            case 'repeat':
                if (isset($msgData['params']['repeat']) && isset($featureMap['repeat'])) {
                    RequestAction($featureMap['repeat'], (bool)$msgData['params']['repeat']);
                    $attributes['repeat'] = (bool)$msgData['params']['repeat'];
                }
                break;

            case 'shuffle':
                if (isset($msgData['params']['shuffle']) && isset($featureMap['shuffle'])) {
                    RequestAction($featureMap['shuffle'], (bool)$msgData['params']['shuffle']);
                    $attributes['shuffle'] = (bool)$msgData['params']['shuffle'];
                }
                break;

            case 'channel_up':
            case 'channel_down':
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Command $cmdId is documented but no direct variable is mapped", 0);
                break;

            case 'select_source':
                if (isset($msgData['params']['source']) && isset($featureMap['source'])) {
                    RequestAction($featureMap['source'], $msgData['params']['source']);
                    $attributes['source'] = $msgData['params']['source'];
                }
                break;

            case 'select_sound_mode':
                if (isset($msgData['params']['mode']) && isset($featureMap['sound_mode'])) {
                    RequestAction($featureMap['sound_mode'], $msgData['params']['mode']);
                    $attributes['sound_mode'] = $msgData['params']['mode'];
                }
                break;

            default:
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unknown media player command: $cmdId", 0);
                break;
        }

        if (!empty($attributes)) {
            $this->SendEntityChange($entityId, 'media_player', $attributes);
        }
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        IPS_SemaphoreLeave($lockName);
    }

    /**
     * Führt einen Remote-Befehl aus, indem das im Mapping hinterlegte Skript aufgerufen wird.
     * Überträgt die cmd_id sowie params als $_IPS-Daten an das Skript.
     */
    private function HandleRemoteCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🎮 Remote-Command: $cmdId for $entityId", 0);

        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Could not extract object ID from entity ID: $entityId", 0);
            return;
        }

        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' could not be acquired (timeout)", 0);
            return;
        }

        $mapping = json_decode($this->ReadPropertyString('remote_mapping'), true);
        $commandScript = null;

        foreach ($mapping as $entry) {
            if ('remote_' . $entry['instance_id'] === $entityId) {
                $commandScript = $entry['script_id'] ?? null;
                break;
            }
        }

        if (!$commandScript || !IPS_ScriptExists($commandScript)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ No matching remote mapping or script found for entity ID $entityId", 0);
            IPS_SemaphoreLeave($lockName);
            return;
        }
        $params = "";
        // Übergabe der cmd_id und weiterer Daten an das Skript
        $cmdData = [
            'cmd' => $cmdId,
            'params' => $params
        ];

        IPS_RunScriptEx($commandScript, $cmdData);
        $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
        IPS_SemaphoreLeave($lockName);
    }

    private function HandleSwitchCommand(array $msgData, $clientIP, $clientPort, $reqId): void
    {
        $entityId = $msgData['entity_id'] ?? '';
        $cmdId = $msgData['cmd_id'] ?? '';

        // Semaphore Lock hinzufügen
        if (preg_match('/_(\d+)$/', $entityId, $match)) {
            $objectId = (int)$match[1];
            $lockName = 'UCR_' . $objectId;
        } else {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Could not extract object ID from entity ID: $entityId", 0);
            return;
        }
        if (!IPS_SemaphoreEnter($lockName, 5000)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "❌ Semaphore '$lockName' could not be acquired (timeout)", 0);
            return;
        }
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🔌 Switch-Command: $cmdId for $entityId", 0);
        $mapping = json_decode($this->ReadPropertyString('switch_mapping'), true);
        foreach ($mapping as $entry) {
            if ('switch_' . $entry['instance_id'] === $entityId) {
                $varId = (int)$entry['var_id'];
                $current = @GetValue($varId);

                if ($cmdId === 'on') {
                    $newState = true;
                } elseif ($cmdId === 'off') {
                    $newState = false;
                } elseif ($cmdId === 'toggle') {
                    if (is_bool($current)) {
                        $newState = !$current;
                    } else {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Current value is not boolean: $current", 0);
                        IPS_SemaphoreLeave($lockName);
                        return;
                    }
                } else {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ Unknown switch command: $cmdId", 0);
                    IPS_SemaphoreLeave($lockName);
                    return;
                }

                if ($newState !== null && $current !== $newState) {
                    // Optimistic UI update: send the intended state immediately so the UI flips instantly.
                    $optimisticStateStr = $newState ? 'ON' : 'OFF';
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "⚡ Optimistic entity_change for $entityId → $optimisticStateStr", 0);
                    $this->SendEntityChange($entityId, 'switch', ['state' => $optimisticStateStr]);

                    // Execute the action in Symcon
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ RequestAction for VarID $varId with value " . json_encode($newState), 0);
                    RequestAction($varId, $newState);

                    // Optional: read back shortly after and correct UI if the real state differs
                    usleep(10000); // 10ms
                    $updated = @GetValue($varId);
                    $stateStr = $updated ? 'ON' : 'OFF';
                    if ($stateStr !== $optimisticStateStr) {
                        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "🩹 Correcting entity_change for $entityId → $stateStr (was optimistic $optimisticStateStr)", 0);
                        $this->SendEntityChange($entityId, 'switch', ['state' => $stateStr]);
                    }
                } else {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "⏩ No RequestAction required – state unchanged", 0);
                }
                $this->SendSuccessResponse((int)$reqId, $clientIP, (int)$clientPort);
                // Semaphore am Ende freigeben
                IPS_SemaphoreLeave($lockName);
                return;
            }
        }
        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ No matching switch mapping found for entity ID $entityId", 0);
        IPS_SemaphoreLeave($lockName);
    }

    /**
     * Sendet eine Abschlussantwort ("kind":"resp", "code":200) an den Remote-Client nach erfolgreichem Switch-Command.
     */
    private function SendSuccessResponse(int $reqId, string $clientIP, int $clientPort): void
    {
        $response = [
            'kind' => 'resp',
            'req_id' => $reqId,
            'code' => 200,
            'msg' => 'result',
            'msg_data' => new stdClass()
        ];
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_IO, "📤 Abschlussantwort an $clientIP:$clientPort für req_id $reqId", 0);
        $this->PushToRemoteClient($response, $clientIP, $clientPort);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        //Never delete this line!
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '✅ Kernel READY – sending initial events', 0);
            $this->RegisterHook('unfoldedcircle');
            $this->RegisterMdnsService();
            $this->RefreshRemoteCores();
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🔁 Setting timer intervals: PingDeviceState=30s, UpdateAllEntityStates=15s', 0);
            $this->SetTimerInterval("PingDeviceState", 30000); // alle 30 Sekunden den Status senden
            $this->SetTimerInterval("UpdateAllEntityStates", 15000); // alle 15 Sekunden den Status senden
            $this->SendInitialOnlineEventsForAllClients();
            $this->EnsureTokenInitialized();
        }
        if ($Message == VM_UPDATE) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_VM, "📣 VM_UPDATE received: VarID $SenderID", 0);

            // Semaphore-Check für Switches (Events von RequestAction blockieren)
            $lockName = 'UCR_' . $SenderID;
            if (!IPS_SemaphoreEnter($lockName, 1)) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_VM, "⏸ $SenderID locked by active command – suppressing event", 0);
                return;
            }
            IPS_SemaphoreLeave($lockName);

            $this->SendEntityStateUpdate($SenderID);
        }
    }

    /**
     * Sendet ein entity_change Event an alle authentifizierten oder freigegebenen Remote-Clients.
     */
    private function SendEntityChange(string $entityId, string $entityType, array $attributes): void
    {
        $targets = $this->GetAliveClientTargets();
        if (count($targets) === 0) {
            // Nobody alive -> do nothing, no logs.
            return;
        }

        // For light entities, if color_var_id is available, add hue/saturation from hex color
        if ($entityType === 'light') {
            $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
            if (is_array($lightMapping)) {
                foreach ($lightMapping as $entry) {
                    if ('light_' . ($entry['instance_id'] ?? '') === $entityId) {
                        if (!empty($entry['color_var_id']) && @IPS_VariableExists($entry['color_var_id'])) {
                            $hex = @GetValue($entry['color_var_id']);
                            $hs = $this->ConvertHexColorToHueSaturation((int)$hex);
                            $attributes['hue'] = $hs['hue'];
                            $attributes['saturation'] = $hs['saturation'];
                        }
                        break;
                    }
                }
            }
        }

        $event = [
            'kind' => 'event',
            'msg' => 'entity_change',
            'msg_data' => [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'attributes' => $attributes
            ],
            'cat' => 'ENTITY'
        ];

        foreach ($targets as $t) {
            $ip = $t['ip'];
            $port = (int)$t['port'];
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Sending entity_change for $entityId to $ip:$port", 0);
            $this->PushToRemoteClient($event, $ip, $port);
        }
    }

    /**
     * Prüft, ob die Variable in der switch_mapping referenziert ist und sendet ein entity_state Event an alle authentifizierten Remote-Clients.
     * Fügt detaillierte Debug-Ausgaben für bessere Nachvollziehbarkeit hinzu.
     * Verwendet einen RAM-Puffer für den letzten gesendeten Zustand, um Attributschreibungen zu vermeiden.
     */
    private array $stateBuffer = [];
    // Separate buffer for cover movement detection (avoid mixing with switch stateBuffer keys)
    private array $coverStateBuffer = [];

    public function SendEntityStateUpdate(int $varId): void
    {
        // If no Remote client is currently alive, do nothing (prevents log spam and unnecessary work).
        if (!$this->HasAliveClients()) {
            return;
        }

        // $this->SendDebug(__FUNCTION__, "🔄 Aktualisiere Zustand für VarID: $varId", 0);

        // 1. Switches
        $switchMapping = json_decode($this->ReadPropertyString('switch_mapping'), true);
        if (is_array($switchMapping)) {
            foreach ($switchMapping as $entry) {
                if (isset($entry['var_id']) && (int)$entry['var_id'] === $varId) {
                    $state = @GetValue($varId);
                    $stateStr = ($state) ? 'ON' : 'OFF';
                    $currentBool = (bool)$state;
                    // RAM-Puffer für Zustand
                    if (isset($this->stateBuffer[$varId]) && $this->stateBuffer[$varId] === $currentBool) {
                        // $this->SendDebug(__FUNCTION__, "⏭️ Zustand hat sich nicht geändert (weiter: $currentBool)", 0);
                        return;
                    }
                    $this->stateBuffer[$varId] = $currentBool;

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Switch mapping found for VarID $varId → State: $stateStr", 0);

                    $mappedEntityId = 'switch_' . (string)$entry['instance_id'];

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_state',
                        'msg_data' => [
                            'entity_id' => $mappedEntityId,
                            'entity_type' => 'switch',
                            'attributes' => [
                                'state' => $stateStr
                            ]
                        ]
                    ];

                    $this->BroadcastEventToClients($event);
                    return;
                }
            }
        }

        // 2. Buttons
        $buttonMapping = json_decode($this->ReadPropertyString('button_mapping'), true);
        if (is_array($buttonMapping)) {
            foreach ($buttonMapping as $entry) {
                if (isset($entry['var_id']) && (int)$entry['var_id'] === $varId) {
                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "✅ Button mapping found for VarID $varId", 0);

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_state',
                        'msg_data' => [
                            'entity_id' => 'button_' . $entry['script_id'],
                            'entity_type' => 'button',
                            'attributes' => [
                                'state' => 'AVAILABLE'
                            ]
                        ]
                    ];

                    $this->BroadcastEventToClients($event);
                    return;
                }
            }
        }

        // 3. Sensoren
        $sensorMapping = json_decode($this->ReadPropertyString('sensor_mapping'), true);
        if (is_array($sensorMapping)) {
            foreach ($sensorMapping as $entry) {
                if (!isset($entry['var_id']) || (int)$entry['var_id'] !== $varId) {
                    continue;
                }

                $sensorType = $entry['sensor_type'] ?? 'generic';
                $result = $this->GetSensorValueAndUnit($varId);
                $value = $result['value'];
                $unit = $result['unit'];
                $instanceId = (string)$varId; // Sensoren verwenden var_id damit mehrere Datenpunkte möglich sind

                $event = [
                    'kind' => 'event',
                    'msg' => 'entity_change',
                    'cat' => 'ENTITY',
                    'msg_data' => [
                        'entity_type' => 'sensor',
                        'entity_id' => 'sensor_' . $instanceId,
                        'attributes' => [
                            'state' => 'ON',
                            'value' => $value,
                            'unit' => $unit
                        ]
                    ]
                ];

                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Entity change for sensor_{$instanceId} (VarID $varId, type $sensorType) | value=" . json_encode($value) . " unit=" . json_encode($unit), 0);
                $this->BroadcastEventToClients($event);
                return;
            }
        }

        // 4. Light (Leuchtmittel)
        $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
        if (is_array($lightMapping)) {
            foreach ($lightMapping as $entry) {
                $switchVarId = isset($entry['switch_var_id']) ? (int)$entry['switch_var_id'] : 0;
                $brightnessVarId = isset($entry['brightness_var_id']) ? (int)$entry['brightness_var_id'] : 0;
                $colorVarId = isset($entry['color_var_id']) ? (int)$entry['color_var_id'] : 0;
                $colorTempVarId = isset($entry['color_temp_var_id']) ? (int)$entry['color_temp_var_id'] : 0;

                // React not only to switch updates, but also to brightness/color/color temperature updates.
                if ($varId !== $switchVarId && $varId !== $brightnessVarId && $varId !== $colorVarId && $varId !== $colorTempVarId) {
                    continue;
                }

                $attributes = [];

                if ($switchVarId > 0 && @IPS_VariableExists($switchVarId)) {
                    $switchValue = @GetValue($switchVarId);
                    $attributes['state'] = $switchValue ? 'ON' : 'OFF';
                } else {
                    $attributes['state'] = 'OFF';
                }

                if ($brightnessVarId > 0 && @IPS_VariableExists($brightnessVarId)) {
                    $attributes['brightness'] = $this->ConvertBrightnessToRemote($brightnessVarId);
                }

                if ($colorVarId > 0 && @IPS_VariableExists($colorVarId)) {
                    $rawColor = @GetValue($colorVarId);

                    if (is_numeric($rawColor)) {
                        $hs = $this->ConvertHexColorToHueSaturation((int)$rawColor);
                        $attributes['hue'] = $hs['hue'];
                        $attributes['saturation'] = $hs['saturation'];
                    } else {
                        $color = json_decode((string)$rawColor, true);
                        if (is_array($color)) {
                            $attributes['hue'] = (int)($color['hue'] ?? 0);
                            $attributes['saturation'] = (int)($color['saturation'] ?? 0);
                        }
                    }
                }

                if ($colorTempVarId > 0 && @IPS_VariableExists($colorTempVarId)) {
                    $ctVal = @GetValue($colorTempVarId);
                    $attributes['color_temperature'] = $this->ConvertColorTemperatureToRemote($colorTempVarId, $ctVal);
                }

                $eid = 'light_' . (string)($entry['instance_id'] ?? $switchVarId ?: $varId);
                $event = [
                    'kind' => 'event',
                    'msg' => 'entity_change',
                    'cat' => 'ENTITY',
                    'msg_data' => [
                        'entity_type' => 'light',
                        'entity_id' => $eid,
                        'attributes' => $attributes
                    ]
                ];

                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Entity change for light $eid (trigger VarID $varId) | " . json_encode($attributes), 0);
                $this->BroadcastEventToClients($event);
                return;
            }
        }

        // 5. Cover (Jalousie, Rollladen)
        $coverMapping = json_decode($this->ReadPropertyString('cover_mapping'), true);
        if (is_array($coverMapping)) {
            foreach ($coverMapping as $entry) {
                if (!isset($entry['position_var_id']) || (int)$entry['position_var_id'] !== $varId) {
                    continue;
                }

                // Always convert Symcon value -> Remote position (0..100, 0=CLOSED, 100=OPEN)
                $symconPosRaw = @GetValue($varId);
                $position = (is_numeric($symconPosRaw)) ? $this->ConvertCoverPositionToRemote($varId, $symconPosRaw) : null;

                // Fallback if position is not readable
                if ($position === null) {
                    $attributes = ['state' => 'UNKNOWN'];
                } else {
                    // Detect movement based on position changes
                    $now = time();
                    $buf = $this->coverStateBuffer[$varId] ?? null;
                    $lastPos = is_array($buf) && isset($buf['pos']) ? (int)$buf['pos'] : null;
                    $lastMoveTs = is_array($buf) && isset($buf['last_move_ts']) ? (int)$buf['last_move_ts'] : 0;
                    $lastDir = is_array($buf) && isset($buf['dir']) ? (string)$buf['dir'] : '';

                    $state = null;

                    if ($lastPos === null) {
                        // First observation: assume stable
                        $state = ($position <= 0) ? 'CLOSED' : 'OPEN';
                        $this->coverStateBuffer[$varId] = ['pos' => $position, 'last_move_ts' => 0, 'dir' => ''];
                    } elseif ($position !== $lastPos) {
                        // Position changed -> moving
                        $dir = ($position > $lastPos) ? 'OPENING' : 'CLOSING';
                        $state = $dir;
                        $this->coverStateBuffer[$varId] = ['pos' => $position, 'last_move_ts' => $now, 'dir' => $dir];
                    } else {
                        // Position unchanged -> may be stable or we just missed intermediate updates
                        // If we recently saw movement, keep OPENING/CLOSING only for a short grace period,
                        // otherwise report stable OPEN/CLOSED based on current position.
                        $graceSeconds = 2;
                        if ($lastMoveTs > 0 && ($now - $lastMoveTs) <= $graceSeconds && ($lastDir === 'OPENING' || $lastDir === 'CLOSING')) {
                            $state = $lastDir;
                        } else {
                            $state = ($position <= 0) ? 'CLOSED' : 'OPEN';
                            $this->coverStateBuffer[$varId] = ['pos' => $position, 'last_move_ts' => 0, 'dir' => ''];
                        }
                    }

                    $attributes = [
                        'state' => $state,
                        'position' => $position
                    ];
                }

                $eid = 'cover_' . (string)($entry['instance_id'] ?? $varId);
                $event = [
                    'kind' => 'event',
                    'msg' => 'entity_change',
                    'cat' => 'ENTITY',
                    'msg_data' => [
                        'entity_type' => 'cover',
                        'entity_id' => $eid,
                        'attributes' => $attributes
                    ]
                ];

                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Entity change for cover $eid (VarID $varId) | " . json_encode($attributes), 0);
                $this->BroadcastEventToClients($event);
                return;
            }
        }

        // 6. Climate (Klima)
        $climateMapping = json_decode($this->ReadPropertyString('climate_mapping'), true);
        if (is_array($climateMapping)) {
            foreach ($climateMapping as $entry) {
                if (!isset($entry['status_var_id']) || (int)$entry['status_var_id'] !== $varId) {
                    continue;
                }

                $attributes = [];

                // UC Climate state: ON/OFF based on status_var_id
                $statusVarId = (int)$entry['status_var_id'];
                $statusRaw = @GetValue($statusVarId);
                $attributes['state'] = $this->NormalizeOnOffState($statusVarId, $statusRaw);

                // Temperatures
                if (!empty($entry['current_temp_var_id']) && @IPS_VariableExists((int)$entry['current_temp_var_id'])) {
                    $attributes['current_temperature'] = (float)@GetValue((int)$entry['current_temp_var_id']);
                }
                if (!empty($entry['target_temp_var_id']) && @IPS_VariableExists((int)$entry['target_temp_var_id'])) {
                    $attributes['target_temperature'] = (float)@GetValue((int)$entry['target_temp_var_id']);
                }

                // UC HVAC mode (optional)
                if (!empty($entry['mode_var_id']) && @IPS_VariableExists((int)$entry['mode_var_id'])) {
                    $modeVarId = (int)$entry['mode_var_id'];
                    $modeVal = @GetValue($modeVarId);
                    $modeLabel = $this->GetProfileValueLabel($modeVarId, $modeVal);
                    $allowedModes = ['HEAT', 'COOL', 'HEAT_COOL', 'FAN', 'AUTO', 'OFF'];
                    if (in_array($modeLabel, $allowedModes, true)) {
                        // Use the constant if present, fallback to plain key
                        if (class_exists('Entity_Climate') && defined('Entity_Climate::ATTR_HVAC_MODE')) {
                            $attributes[Entity_Climate::ATTR_HVAC_MODE] = $modeLabel;
                        } else {
                            $attributes['hvac_mode'] = $modeLabel;
                        }
                    }
                }

                // Use instance_id for entity_id (fallback to status var id)
                $eid = 'climate_' . (string)($entry['instance_id'] ?? $entry['status_var_id']);

                $event = [
                    'kind' => 'event',
                    'msg' => 'entity_change',
                    'cat' => 'ENTITY',
                    'msg_data' => [
                        'entity_type' => 'climate',
                        'entity_id' => $eid,
                        'attributes' => $attributes
                    ]
                ];

                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Entity change for climate $eid (VarID $varId) | " . json_encode($attributes), 0);
                $this->BroadcastEventToClients($event);
                return;
            }
        }

        // 7. Media Player
        $mediaMapping = json_decode($this->ReadPropertyString('media_player_mapping'), true);
        if (is_array($mediaMapping)) {
            foreach ($mediaMapping as $entry) {
                if (!isset($entry['features']) || !is_array($entry['features'])) {
                    continue;
                }

                foreach ($entry['features'] as $feature) {
                    if (!isset($feature['var_id']) || (int)$feature['var_id'] !== $varId) {
                        continue;
                    }

                    $instanceId = (string)($entry['instance_id'] ?? '');
                    if ($instanceId === '') {
                        continue;
                    }

                    $attributes = $this->BuildMediaPlayerAttributesFromFeatures($entry);
                    $entityId = 'media_player_' . $instanceId;

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'media_player',
                            'entity_id' => $entityId,
                            'attributes' => $attributes
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Entity change for media player $entityId (VarID $varId) | " . json_encode($attributes), 0);
                    $this->BroadcastEventToClients($event);
                    return;
                }
            }
        }

        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_ENTITY, "⚠️ No mapping found for VarID $varId", 0);
    }

    private function ReadMediaPlayerCache(): array
    {
        $raw = $this->ReadAttributeString('media_player_cache');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function WriteMediaPlayerCache(array $cache): void
    {
        $this->WriteAttributeString('media_player_cache', json_encode($cache));
    }

    private function IsMediaCacheEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        return false;
    }

    private function UpdateMediaPlayerCacheValue(string $instanceId, string $key, $value): void
    {
        if ($instanceId === '' || $key === '' || $this->IsMediaCacheEmptyValue($value)) {
            return;
        }

        $cache = $this->ReadMediaPlayerCache();
        if (!isset($cache[$instanceId]) || !is_array($cache[$instanceId])) {
            $cache[$instanceId] = [];
        }

        $cache[$instanceId][$key] = $value;
        $this->WriteMediaPlayerCache($cache);
    }

    private function GetMediaPlayerLiveOrCachedValue(string $instanceId, string $key, ?int $varId)
    {
        $liveValue = null;

        if (!empty($varId) && @IPS_VariableExists($varId)) {
            $liveValue = @GetValue($varId);

            if (!$this->IsMediaCacheEmptyValue($liveValue)) {
                $this->UpdateMediaPlayerCacheValue($instanceId, $key, $liveValue);
                return $liveValue;
            }
        }

        $cache = $this->ReadMediaPlayerCache();
        if (isset($cache[$instanceId]) && is_array($cache[$instanceId]) && array_key_exists($key, $cache[$instanceId])) {
            return $cache[$instanceId][$key];
        }

        return $liveValue;
    }

    private function GetComputedMediaPlayerPosition(string $instanceId, $positionValue, $durationValue, string $state): ?float
    {
        $cache = $this->ReadMediaPlayerCache();
        if (!isset($cache[$instanceId]) || !is_array($cache[$instanceId])) {
            $cache[$instanceId] = [];
        }

        $now = time();

        if ($positionValue !== null && $positionValue !== '') {
            $cache[$instanceId]['media_position'] = (float)$positionValue;
            $cache[$instanceId]['last_position_ts'] = $now;
        }

        if ($durationValue !== null && $durationValue !== '') {
            $cache[$instanceId]['media_duration'] = (float)$durationValue;
        }

        $cache[$instanceId]['last_state'] = $state;
        $this->WriteMediaPlayerCache($cache);

        $basePos = (float)($cache[$instanceId]['media_position'] ?? 0);
        $baseTs = (int)($cache[$instanceId]['last_position_ts'] ?? $now);
        $duration = (float)($cache[$instanceId]['media_duration'] ?? 0);

        if ($state === 'PLAYING') {
            $computed = $basePos + max(0, $now - $baseTs);
            if ($duration > 0) {
                $computed = min($computed, $duration);
            }
            return $computed;
        }

        return $basePos;
    }

    private function BuildMediaPlayerAttributesFromFeatures(array $entry): array
    {
        $attributes = [];
        $instanceId = (string)($entry['instance_id'] ?? '');
        $features = $entry['features'] ?? [];

        if ($instanceId === '' || !is_array($features)) {
            return $attributes;
        }

        foreach ($features as $f) {
            if (!is_array($f) || !isset($f['feature_key']) || !isset($f['var_id'])) {
                continue;
            }

            $featureKey = (string)$f['feature_key'];
            $featureVarId = (int)$f['var_id'];

            if ($featureVarId <= 0 || !@IPS_VariableExists($featureVarId)) {
                continue;
            }

            $val = @GetValue($featureVarId);

            switch ($featureKey) {
                case 'on_off':
                    $attributes['state'] = $val ? 'ON' : 'OFF';
                    break;

                case 'volume':
                    $attributes['volume'] = (float)$val;
                    break;

                case 'muted':
                    $attributes['muted'] = (bool)$val;
                    break;

                case 'media_title':
                    $cachedVal = $this->GetMediaPlayerLiveOrCachedValue($instanceId, 'media_title', $featureVarId);
                    if (!$this->IsMediaCacheEmptyValue($cachedVal)) {
                        $attributes['media_title'] = (string)$cachedVal;
                    }
                    break;

                case 'media_artist':
                    $cachedVal = $this->GetMediaPlayerLiveOrCachedValue($instanceId, 'media_artist', $featureVarId);
                    if (!$this->IsMediaCacheEmptyValue($cachedVal)) {
                        $attributes['media_artist'] = (string)$cachedVal;
                    }
                    break;

                case 'media_album':
                    $cachedVal = $this->GetMediaPlayerLiveOrCachedValue($instanceId, 'media_album', $featureVarId);
                    if (!$this->IsMediaCacheEmptyValue($cachedVal)) {
                        $attributes['media_album'] = (string)$cachedVal;
                    }
                    break;

                case 'media_image_url':
                    $cachedVal = $this->GetMediaPlayerLiveOrCachedValue($instanceId, 'media_image_url', $featureVarId);
                    if (!$this->IsMediaCacheEmptyValue($cachedVal)) {
                        $attributes['media_image_url'] = (string)$cachedVal;
                    }
                    break;

                case 'source':
                    $cachedVal = $this->GetMediaPlayerLiveOrCachedValue($instanceId, 'source', $featureVarId);
                    if (!$this->IsMediaCacheEmptyValue($cachedVal)) {
                        $attributes['source'] = (string)$cachedVal;
                    }
                    break;
            }
        }

        if (!isset($attributes['state'])) {
            $attributes['state'] = 'ON';
        }

        return $attributes;
    }

    private function GetVariableProfileDetails(int $varId): array
    {
        $profile = $this->GetEffectiveVariableProfile($varId);
        if ($profile === '' || !IPS_VariableProfileExists($profile)) {
            return ['profile' => $profile, 'min' => null, 'max' => null, 'suffix' => ''];
        }
        $p = IPS_GetVariableProfile($profile);
        return [
            'profile' => $profile,
            'min' => isset($p['MinValue']) ? (float)$p['MinValue'] : null,
            'max' => isset($p['MaxValue']) ? (float)$p['MaxValue'] : null,
            'suffix' => (string)($p['Suffix'] ?? '')
        ];
    }

    private function ConvertColorTemperatureFromRemote(int $varId, $remoteValue): float
    {
        $r = is_numeric($remoteValue) ? (float)$remoteValue : 0.0;
        $r = max(0.0, min(100.0, $r));

        $d = $this->GetVariableProfileDetails($varId);
        $min = $d['min'];
        $max = $d['max'];

        // Kein Profil? Dann nehmen wir an: Symcon erwartet auch 0..100
        if (!is_numeric($min) || !is_numeric($max) || $max <= $min) {
            return $r;
        }

        return $min + (($max - $min) * ($r / 100.0));
    }

    private function ConvertColorTemperatureToRemote(int $varId, $symconValue): int
    {
        if (!is_numeric($symconValue)) {
            return 0;
        }

        $v = (float)$symconValue;

        $d = $this->GetVariableProfileDetails($varId);
        $min = $d['min'];
        $max = $d['max'];

        // Kein Profil? Dann nehmen wir an: Symcon liefert schon 0..100
        if (!is_numeric($min) || !is_numeric($max) || $max <= $min) {
            $v = max(0.0, min(100.0, $v));
            return (int)round($v);
        }

        $v = max($min, min($max, $v));
        $r = (($v - $min) / ($max - $min)) * 100.0;
        $r = max(0.0, min(100.0, $r));
        return (int)round($r);
    }

    /**
     * Broadcasts an event to all authenticated or whitelisted clients.
     *
     * @param array $event
     * @return void
     */
    private function BroadcastEventToClients(array $event): void
    {
        $targets = $this->GetAliveClientTargets();
        if (count($targets) === 0) {
            return;
        }

        foreach ($targets as $t) {
            $ip = $t['ip'];
            $port = (int)$t['port'];
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Sending event to $ip:$port", 0);
            $this->PushToRemoteClient($event, $ip, $port);
        }
    }

    /**
     * Sendet Initial-Online-Events für alle authentifizierten oder freigegebenen Remote-Clients.
     */
    private function SendInitialOnlineEventsForAllClients(): void
    {
        // Only send initial online events if at least one client is actually alive.
        if (!$this->HasAliveClients()) {
            return;
        }

        $sessions = $this->readSessions();
        $whitelistConfig = json_decode($this->ReadPropertyString('ip_whitelist'), true);
        $ipWhitelist = array_column($whitelistConfig ?? [], 'ip');

        foreach ($sessions as $clientIP => $entry) {
            if (!$this->IsClientSessionAlive((array)$entry)) {
                continue;
            }
            if (!is_array($entry) || !($entry['authenticated'] ?? false) || !isset($entry['port'])) {
                // Auch Whitelist berücksichtigen
                $whitelisted = in_array($clientIP, $ipWhitelist);
                if (!$whitelisted || !isset($entry['port'])) {
                    continue;
                }
            }

            $port = (int)$entry['port'];

            // Sensoren melden sich online
            $sensorMapping = json_decode($this->ReadPropertyString('sensor_mapping'), true);
            if (is_array($sensorMapping)) {
                foreach ($sensorMapping as $sensor) {
                    if (!isset($sensor['var_id'])) {
                        continue;
                    }

                    $varId = (int)$sensor['var_id'];
                    if (!@IPS_VariableExists($varId)) {
                        continue;
                    }

                    $instanceId = (string)$varId; // Sensoren nutzen var_id als eindeutige entity_id
                    $result = $this->GetSensorValueAndUnit($varId);
                    $value = $result['value'];
                    $unit = $result['unit'];

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'sensor',
                            'entity_id' => 'sensor_' . $instanceId,
                            'attributes' => [
                                'state' => 'ON',
                                'value' => $value,
                                'unit' => $unit
                            ]
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Online event for sensor_{$instanceId} to $clientIP:$port | value=" . json_encode($value) . " unit=" . json_encode($unit), 0);
                    $this->PushToRemoteClient($event, $clientIP, $port);
                }
            }

            // Schalter melden sich online
            $switchMapping = json_decode($this->ReadPropertyString('switch_mapping'), true);
            if (is_array($switchMapping)) {
                foreach ($switchMapping as $switch) {
                    if (!isset($switch['var_id'])) {
                        continue;
                    }
                    $value = @GetValue($switch['var_id']);
                    $state = $value ? 'ON' : 'OFF';

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'switch',
                            'entity_id' => 'switch_' . (string)$switch['instance_id'],
                            'attributes' => [
                                'state' => $state
                            ]
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Online event for switch_" . (string)$switch['instance_id'] . " to $clientIP:$port", 0);
                    $this->PushToRemoteClient($event, $clientIP, $port);
                }
            }

            // Leuchtmittel melden sich online
            $lightMapping = json_decode($this->ReadPropertyString('light_mapping'), true);
            if (is_array($lightMapping)) {
                foreach ($lightMapping as $light) {
                    if (!isset($light['switch_var_id'])) {
                        continue;
                    }

                    $switchVarId = (int)$light['switch_var_id'];
                    $instanceId = (string)($light['instance_id'] ?? $switchVarId);

                    $value = @GetValue($switchVarId);
                    $state = $value ? 'ON' : 'OFF';
                    $attributes = ['state' => $state];

                    if (!empty($light['brightness_var_id']) && @IPS_VariableExists((int)$light['brightness_var_id'])) {
                        $attributes['brightness'] = $this->ConvertBrightnessToRemote((int)$light['brightness_var_id']);
                    }

                    if (!empty($light['color_var_id']) && @IPS_VariableExists((int)$light['color_var_id'])) {
                        $rawColor = @GetValue((int)$light['color_var_id']);

                        if (is_numeric($rawColor)) {
                            $hs = $this->ConvertHexColorToHueSaturation((int)$rawColor);
                            $attributes['hue'] = $hs['hue'];
                            $attributes['saturation'] = $hs['saturation'];
                        } else {
                            $color = json_decode((string)$rawColor, true);
                            if (is_array($color)) {
                                $attributes['hue'] = (int)($color['hue'] ?? 0);
                                $attributes['saturation'] = (int)($color['saturation'] ?? 0);
                            }
                        }
                    }

                    if (!empty($light['color_temp_var_id']) && @IPS_VariableExists((int)$light['color_temp_var_id'])) {
                        $ctVarId = (int)$light['color_temp_var_id'];
                        $ctVal = @GetValue($ctVarId);
                        $attributes['color_temperature'] = $this->ConvertColorTemperatureToRemote($ctVarId, $ctVal);
                    }

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'light',
                            'entity_id' => 'light_' . $instanceId,
                            'attributes' => $attributes
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Online event for light_$instanceId to $clientIP:$port | " . json_encode($attributes), 0);
                    $this->PushToRemoteClient($event, $clientIP, $port);
                }
            }

            // Klima-Geräte melden sich online
            $climateMapping = json_decode($this->ReadPropertyString('climate_mapping'), true);
            if (is_array($climateMapping)) {
                foreach ($climateMapping as $climate) {
                    if (!isset($climate['status_var_id']) || !is_numeric($climate['status_var_id'])) {
                        continue;
                    }

                    $statusVarId = (int)$climate['status_var_id'];
                    if (!@IPS_VariableExists($statusVarId)) {
                        continue;
                    }

                    $instanceId = (string)($climate['instance_id'] ?? $statusVarId);
                    $statusRaw = @GetValue($statusVarId);

                    $attributes = [
                        'state' => $this->NormalizeOnOffState($statusVarId, $statusRaw)
                    ];

                    if (!empty($climate['current_temp_var_id']) && @IPS_VariableExists((int)$climate['current_temp_var_id'])) {
                        $attributes['current_temperature'] = (float)@GetValue((int)$climate['current_temp_var_id']);
                    }

                    if (!empty($climate['target_temp_var_id']) && @IPS_VariableExists((int)$climate['target_temp_var_id'])) {
                        $attributes['target_temperature'] = (float)@GetValue((int)$climate['target_temp_var_id']);
                    }

                    if (!empty($climate['mode_var_id']) && @IPS_VariableExists((int)$climate['mode_var_id'])) {
                        $modeVarId = (int)$climate['mode_var_id'];
                        $modeVal = @GetValue($modeVarId);
                        $modeLabel = $this->GetProfileValueLabel($modeVarId, $modeVal);
                        $allowedModes = ['HEAT', 'COOL', 'HEAT_COOL', 'FAN', 'AUTO', 'OFF'];
                        if (in_array($modeLabel, $allowedModes, true)) {
                            if (class_exists('Entity_Climate') && defined('Entity_Climate::ATTR_HVAC_MODE')) {
                                $attributes[Entity_Climate::ATTR_HVAC_MODE] = $modeLabel;
                            } else {
                                $attributes['hvac_mode'] = $modeLabel;
                            }
                        }
                    }

                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'climate',
                            'entity_id' => 'climate_' . $instanceId,
                            'attributes' => $attributes
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Online event for climate_$instanceId to $clientIP:$port | " . json_encode($attributes), 0);
                    $this->PushToRemoteClient($event, $clientIP, $port);
                }
            }

            // Media-Player melden sich online
            $mediaMapping = json_decode($this->ReadPropertyString('media_player_mapping'), true);
            if (is_array($mediaMapping)) {
                foreach ($mediaMapping as $media) {
                    if (!isset($media['instance_id']) || empty($media['instance_id'])) {
                        continue;
                    }
                    if (!isset($media['features']) || !is_array($media['features'])) {
                        continue;
                    }

                    $instanceId = (string)$media['instance_id'];
                    $attributes = $this->BuildMediaPlayerAttributesFromFeatures($media);

                    // TODO: Media-Player Initial-Online-Event später vollständig nach UC-Doku ausbauen
                    // (Play/Pause-Status, Position, Duration, Source, Sound Mode, Media Type, Artwork etc.).
                    $event = [
                        'kind' => 'event',
                        'msg' => 'entity_change',
                        'cat' => 'ENTITY',
                        'msg_data' => [
                            'entity_type' => 'media_player',
                            'entity_id' => 'media_player_' . $instanceId,
                            'attributes' => $attributes
                        ]
                    ];

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_ENTITY, "📤 Online event for media_player_$instanceId to $clientIP:$port | " . json_encode($attributes), 0);
                    $this->PushToRemoteClient($event, $clientIP, $port);
                }
            }
        }
    }

    /**
     * This function will be called by the hook control. Visibility should be protected!
     */
    protected function ProcessHookData(): void
    {
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '🛜 SERVER REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? '---'), 0);

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';
        $remotePort = intval($_SERVER['REMOTE_PORT']) ?? 0;
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, "📥 Request URI: $uri | Method: $method | IP: $remoteIP", 0);

        if (strpos($uri, '/hook/unfoldedcircle') !== 0) {
            return;
        }

        if (!$this->authenticateClient($remoteIP, $remotePort, $_SERVER['HTTP_AUTH_TOKEN'] ?? null)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_HOOK, '❌ Webhook access denied – authentication failed', 0);

            $this->PushToRemoteClientHook([
                'kind' => 'resp',
                'msg' => 'auth_required',
                'req_id' => 0,
                'msg_data' => [
                    'code' => 401,
                    'message' => 'Unauthorized – Invalid or missing token'
                ]
            ], $remoteIP, $remotePort);
            http_response_code(401);
            return;
        }

        $payload = file_get_contents('php://input');
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, "Raw Data: " . $payload, 0);


        // Prüfen auf PING-Frame (WebSocket)
        if (WebSocketUtils::IsPingFrame($payload)) {
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '🔁 PING detected – would send PONG', 0);
            // $pong = WebSocketUtils::PackPong();
            // todo is webhook sending PONG ?
            // $this->PushPongToRemoteClient($pong);
            return;
        }

        // JSON-Nutzdaten lesen
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_HOOK, '❌ Error: invalid JSON received!', 0);
            return;
        }

        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '📨 Received data: ' . json_encode($data), 0);


        $response = [];

        if (isset($data['msg'])) {
            switch ($data['msg']) {
                case 'get_driver_version':
                    $response = [
                        'kind' => 'resp',
                        'msg' => 'driver_version',
                        'req_id' => $data['req_id'] ?? 0,
                        'msg_data' => [
                            'name' => 'Symcon Integration Driver',
                            'version' => '0.1.0',
                            'api_version' => '1.0.0'
                        ]
                    ];
                    break;

                case 'get_device_state':
                    $response = [
                        'kind' => 'resp',
                        'msg' => 'device_state',
                        'req_id' => $data['req_id'] ?? 0,
                        'msg_data' => [
                            'state' => 'ready'
                        ]
                    ];
                    break;

                default:
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_HOOK, '⚠️ Unknown request: ' . $data['msg'], 0);
                    $response = [
                        'kind' => 'resp',
                        'msg' => 'result',
                        'req_id' => $data['req_id'] ?? 0,
                        'msg_data' => [
                            'code' => 501,
                            'message' => 'Not implemented'
                        ]
                    ];
                    break;
            }

            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '📤 Response: ' . json_encode($response), 0);
            $this->PushToRemoteClientHook($response, $remoteIP, $remotePort);
        }
    }

    private function PushToRemoteClientHook(array $data, string $remoteIP, int $remotePort): void
    {
        $json = json_encode($data);
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_HOOK, '📡 Sending to remote: ' . $json, 0);
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            WC_PushMessageEx($ids[0], '/hook/unfoldedcircle', $json, $remoteIP, $remotePort);
        }
    }

    public function GenerateToken(): void
    {
        $token = bin2hex(random_bytes(16)); // 32 characters hex string
        $this->WriteAttributeString('token', $token);
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_AUTH, '🔑 New token generated: ' . $token, 0);
        $this->UpdateFormField("token", "value", $token);
    }

    /**
     * Scans the object tree for known variable profiles and suggests device mappings
     */
    public function SuggestDeviceMappings(): void
    {
        $result = [
            'switches' => [],
            'buttons' => []
        ];

        $allObjects = IPS_GetObjectList();

        foreach ($allObjects as $id) {
            if (!IPS_VariableExists($id)) {
                continue;
            }

            $v = IPS_GetVariable($id);
            $profile = $v['VariableCustomProfile'] ?: $v['VariableProfile'];
            $name = IPS_GetName($id);
            $parent = IPS_GetName(IPS_GetParent($id));

            // Check for Switch (bool with ~Switch or similar profile)
            if ($v['VariableType'] === 0 && preg_match('/switch|toggle/i', $profile)) {
                $result['switches'][] = [
                    'name' => "$parent → $name",
                    'var_id' => $id,
                    'profile' => $profile
                ];
                continue;
            }

            // Check for Button (bool without feedback, likely script trigger)
            if ($v['VariableType'] === 0 && $profile === '') {
                $result['buttons'][] = [
                    'name' => "$parent → $name",
                    'var_id' => $id,
                    'profile' => '(none)'
                ];
                continue;
            }
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '📋 Device suggestions: ' . json_encode($result), 0);

        echo json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Manuelle Registrierung des Treibers bei Remote-Instanzen
     */
    public function RegisterDriverManually(): array
    {
        // Refresh cached remote cores list first
        $this->RefreshRemoteCores();

        $remotes = json_decode($this->ReadAttributeString('remote_cores'), true);
        if (!is_array($remotes) || empty($remotes)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '❌ No remote instances found (remote_cores empty)', 0);
            return [
                'ok' => false,
                'error' => 'No remote instances found',
                'results' => []
            ];
        }

        // Ensure we have a token
        $this->EnsureTokenInitialized();
        $token = (string)$this->ReadAttributeString('token');

        // Determine Symcon host IP for driver_url
        $hostValue = trim((string)$this->ReadPropertyString('callback_IP'));
        $hostAuto = $this->GetHostIP();

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧭 Host selection (callback_IP vs auto) = ' . json_encode([
                'callback_IP' => $hostValue,
                'auto' => $hostAuto
            ], JSON_UNESCAPED_SLASHES), 0);

        $results = [];

        foreach ($remotes as $remote) {
            $ip = (string)($remote['host'] ?? '');
            $apiKey = (string)($remote['api_key'] ?? '');

            if ($ip === '') {
                $results[] = [
                    'ok' => false,
                    'ip' => '',
                    'url' => '',
                    'status' => 0,
                    'error' => 'Remote entry has no host',
                    'response' => ''
                ];
                continue;
            }

            // Prefer explicit callback_IP; otherwise use Symcon host IP detected via Sys_GetNetworkInfo
            $hostForRemote = ($hostValue !== '') ? $hostValue : $hostAuto;

            // If still empty, do not proceed
            if ($hostForRemote === '') {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, '❌ Cannot determine host IP for driver_url (callback_IP empty and auto-detect failed)', 0);
                $results[] = [
                    'ok' => false,
                    'ip' => $ip,
                    'url' => '',
                    'status' => 0,
                    'error' => 'Cannot determine host IP for driver_url',
                    'response' => ''
                ];
                continue;
            }

            $driverUrl = 'ws://' . $hostForRemote . ':9988';
            $url = "http://{$ip}/api/intg/drivers";

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, "🔍 Registering driver on remote=$ip | driver_url=$driverUrl", 0);
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, '🧭 Host decision = ' . json_encode([
                    'remote_ip' => $ip,
                    'callback_IP' => $hostValue,
                    'auto_host_ip' => $hostAuto,
                    'hostForRemote' => $hostForRemote,
                    'driver_url' => $driverUrl,
                    'note' => ($hostValue !== '' ? 'using callback_IP' : 'using auto host IP from Sys_GetNetworkInfo')
                ], JSON_UNESCAPED_SLASHES), 0);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_AUTH,
                '📡 API key present=' . (!empty($apiKey) ? 'yes' : 'no') . ' | token=' . (method_exists($this, 'MaskToken') ? $this->MaskToken($token) : (!empty($token) ? '***' : '(none)')),
                0
            );

            if ($apiKey === '') {
                $results[] = [
                    'ok' => false,
                    'ip' => $ip,
                    'url' => $url,
                    'status' => 0,
                    'error' => 'Missing api_key for this remote core (Bearer token)',
                    'response' => ''
                ];
                continue;
            }

            $payload = array_merge(
                $this->GetDriverMetadataCommon(),
                [
                    'driver_url' => $driverUrl,
                    'token' => $token,
                    'enabled' => true,
                    'device_discovery' => false,
                ]
            );

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'ignore_errors' => true, // allow reading body on non-2xx
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ],
                    'content' => $jsonPayload,
                    'timeout' => 8
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            // Determine HTTP status code (if available)
            $status = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                        $status = (int)$m[1];
                        break;
                    }
                }
            }

            if ($response === false) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, "❌ POST to $url failed (file_get_contents=false)", 0);
                $results[] = [
                    'ok' => false,
                    'ip' => $ip,
                    'url' => $url,
                    'status' => $status,
                    'error' => 'POST failed (no response body)',
                    'response' => ''
                ];
                continue;
            }

            $ok = ($status >= 200 && $status < 300);
            if ($ok) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_EXT, "✅ Driver registration succeeded on $ip (HTTP $status)", 0);
            } else {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_EXT, "⚠️ Driver registration returned HTTP $status on $ip", 0);
            }

            // Keep response as raw string; script can json_decode if needed
            $results[] = [
                'ok' => $ok,
                'ip' => $ip,
                'url' => $url,
                'status' => $status,
                'error' => $ok ? '' : 'Non-2xx response',
                'response' => (string)$response
            ];
        }

        $allOk = true;
        foreach ($results as $r) {
            if (empty($r['ok'])) {
                $allOk = false;
                break;
            }
        }

        return [
            'ok' => $allOk,
            'count' => count($results),
            'results' => $results
        ];
    }

    /**
     * Returns localized name and description used for driver metadata and manual registration.
     * Keeping this in one place prevents divergence.
     */
    private function GetDriverNameAndDescription(): array
    {
        $first = $this->GetSymconFirstName();

        // Updated descriptions
        $descriptions = [
            'de' => "Verbindet dein Symcon-System mit der Remote 3. Ermöglicht die Steuerung von Systemen wie KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus und viele weitere. \n\nBevor die Einrichtung durchgeführt werden kann, klicken Sie bitte in der Instanz „Remote Integration Driver“ im Objektbaum auf „Token generieren“. Dort wählen Sie auch die Geräte aus, die über die Remote 3 gesteuert werden sollen.\n\nEs werden ausschließlich Geräte angezeigt, die explizit vom Benutzer zur Steuerung freigegeben wurden.\n\nBesuchen Sie die Support-Seite der Firma Symcon für weitergehende Informationen und Dokumentation zum System.",
            'en' => "Connects your Symcon system to Remote 3. Enables control of systems such as KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus and many more. \n\nBefore setup can be performed, please click on “Generate Token” in the “Remote Integration Driver” instance in the object tree. There you also select the devices to be controlled via Remote 3.\n\nOnly devices explicitly enabled by the user for control will be displayed.\n\nVisit the Symcon support page for further information and system documentation.",
            'fr' => "Connecte votre système Symcon à la Remote 3. Permet le contrôle de systèmes tels que KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus et bien d'autres. \n\nAvant de procéder à la configuration, cliquez sur « Générer un jeton » dans l'instance « Remote Integration Driver » de l'arborescence des objets. Vous y sélectionnez également les appareils à contrôler via la Remote 3.\n\nSeuls les appareils explicitement autorisés par l'utilisateur pour le contrôle seront affichés.\n\nConsultez la page d'assistance Symcon pour plus d'informations et de documentation sur le système.",
            'it' => "Collega il tuo sistema Symcon a Remote 3. Consente il controllo di sistemi come KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus e molti altri. \n\nPrima di procedere con la configurazione, clicca su \"Genera token\" nell'istanza \"Remote Integration Driver\" nell'albero degli oggetti. Lì selezioni anche i dispositivi da controllare tramite Remote 3.\n\nVerranno mostrati solo i dispositivi esplicitamente autorizzati dall'utente per il controllo.\n\nVisita la pagina di supporto Symcon per ulteriori informazioni e documentazione sul sistema.",
            'es' => "Conecta tu sistema Symcon con Remote 3. Permite el control de sistemas como KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus y muchos más. \n\nAntes de realizar la configuración, haz clic en \"Generar token\" en la instancia \"Remote Integration Driver\" en el árbol de objetos. Allí también seleccionas los dispositivos que se controlarán a través de Remote 3.\n\nSolo se mostrarán los dispositivos que el usuario haya autorizado explícitamente para el control.\n\nVisita la página de soporte de Symcon para obtener más información y documentación sobre el sistema.",
            'da' => "Forbinder dit Symcon-system med Remote 3. Muliggør styring af systemer som KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus og mange flere. \n\nInden opsætningen kan udføres, skal du klikke på \"Generer token\" i instansen \"Remote Integration Driver\" i objekttræet. Her vælger du også de enheder, der skal styres via Remote 3.\n\nKun enheder, som brugeren eksplicit har givet tilladelse til, vil blive vist.\n\nBesøg Symcons supportside for yderligere information og dokumentation om systemet.",
            'nl' => "Verbindt je Symcon-systeem met Remote 3. Maakt bediening mogelijk van systemen zoals KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus en vele andere. \n\nVoordat de installatie kan worden uitgevoerd, klik je in de instantie \"Remote Integration Driver\" in de objectboom op \"Token genereren\". Daar selecteer je ook de apparaten die via Remote 3 moeten worden bediend.\n\nAlleen apparaten die door de gebruiker expliciet voor bediening zijn vrijgegeven, worden weergegeven.\n\nBezoek de Symcon-supportpagina voor meer informatie en documentatie over het systeem.",
            'pl' => "Łączy system Symcon z Remote 3. Umożliwia sterowanie systemami takimi jak KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus i wieloma innymi. \n\nPrzed rozpoczęciem konfiguracji kliknij „Generuj token” w instancji „Remote Integration Driver” w drzewie obiektów. Tam również wybierasz urządzenia, które mają być sterowane przez Remote 3.\n\nWyświetlane będą wyłącznie urządzenia, które użytkownik wyraźnie udostępnił do sterowania.\n\nOdwiedź stronę wsparcia Symcon, aby uzyskać więcej informacji i dokumentacji dotyczącej systemu.",
            'de-CH' => "Verbindet dein Symcon-System mit der Remote 3. Ermöglicht die Steuerung von Systemen wie KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus und viele weitere. \n\nBevor die Einrichtung durchgeführt werden kann, klicken Sie bitte in der Instanz „Remote Integration Driver“ im Objektbaum auf „Token generieren“. Dort wählen Sie auch die Geräte aus, die über die Remote 3 gesteuert werden sollen.\n\nEs werden ausschließlich Geräte angezeigt, die explizit vom Benutzer zur Steuerung freigegeben wurden.\n\nBesuchen Sie die Support-Seite der Firma Symcon für weitergehende Informationen und Dokumentation zum System.",
            'de-AT' => "Verbindet dein Symcon-System mit der Remote 3. Ermöglicht die Steuerung von Systemen wie KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus und viele weitere. \n\nBevor die Einrichtung durchgeführt werden kann, klicken Sie bitte in der Instanz „Remote Integration Driver“ im Objektbaum auf „Token generieren“. Dort wählen Sie auch die Geräte aus, die über die Remote 3 gesteuert werden sollen.\n\nEs werden ausschließlich Geräte angezeigt, die explizit vom Benutzer zur Steuerung freigegeben wurden.\n\nBesuchen Sie die Support-Seite der Firma Symcon für weitergehende Informationen und Dokumentation zum System.",
            'nl-BE' => "Verbindt je Symcon-systeem met Remote 3. Maakt bediening mogelijk van systemen zoals KNX, LCN, BACnet, Homematic IP, DMX, OPUS, Modbus en vele andere. \n\nVoordat de installatie kan worden uitgevoerd, klik je in de instantie \"Remote Integration Driver\" in de objectboom op \"Token genereren\". Daar selecteer je ook de apparaten die via Remote 3 moeten worden bediend.\n\nAlleen apparaten die door de gebruiker expliciet voor bediening zijn vrijgegeven, worden weergegeven.\n\nBezoek de Symcon-supportpagina voor meer informatie en documentatie over het systeem."
        ];

        $name = [
            'fr' => 'Symcon (Symcon de ' . $first . ')',
            'en' => 'Symcon (Symcon from ' . $first . ')',
            'de' => 'Symcon (Symcon von ' . $first . ')',
            'it' => 'Symcon (Symcon da ' . $first . ')',
            'es' => 'Symcon (Symcon de ' . $first . ')',
            'da' => 'Symcon (Symcon fra ' . $first . ')',
            'nl' => 'Symcon (Symcon van ' . $first . ')',
            'pl' => 'Symcon (Symcon od ' . $first . ')',
            'de-CH' => 'Symcon (Symcon von ' . $first . ')',
            'de-AT' => 'Symcon (Symcon von ' . $first . ')',
            'nl-BE' => 'Symcon (Symcon van ' . $first . ')'
        ];

        return [
            'name' => $name,
            'description' => $descriptions
        ];
    }

    /**
     * Returns the Unfolded Circle setup_data_schema used in driver metadata and manual registration.
     * Keep this in ONE place so changes apply to both paths.
     */
    private function GetSetupDataSchema(): array
    {
        return [
            'title' => [
                'en' => 'Symcon',
                'de' => 'Symcon',
                'fr' => 'Symcon'
            ],
            'settings' => [
                [
                    'id' => 'info',
                    'label' => [
                        'en' => 'Setup progress for Symcon integration',
                        'de' => 'Setup Fortschritt Anbindung von Symcon',
                        'fr' => 'Progression de l’intégration Symcon',
                        'it' => 'Avanzamento configurazione Symcon',
                        'es' => 'Progreso de la integración Symcon',
                        'nl' => 'Voortgang van Symcon-integratie'
                    ],
                    'field' => [
                        'label' => [
                            'value' => [
                                'de' => "Diese Integration ermöglicht die Verbindung zwischen der Remote von Unfolded Circle und Symcon – der zentralen Plattform für professionelle Gebäude- und Hausautomation.\n\n\n🔑 **Wichtig vor dem Start:**\n\n• Navigieren Sie in Symcon zur *Remote Integration Driver*-Instanz und klicken Sie auf „Token generieren“.\n\n• Wählen Sie dort ebenfalls die Geräte aus, die über die Remote von Unfolded Circle gesteuert werden sollen. Nur explizit vom Nutzer freigegebene Geräte erscheinen in der Integration.\n\n\n\nℹ️ **Was ist Symcon?**\n\n• Symcon verbindet viele Systeme in einer leistungsstarken Plattform:\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • AV-Systeme, MQTT u. v. m.\n\nDamit können Licht, Klima, Jalousien, Sensoren und Szenarien nahtlos gesteuert werden.\n\n👉 [Weitere Informationen](https://www.symcon.de)",
                                'en' => "This integration enables connecting the Unfolded Circle Remote with Symcon – the central platform for professional building and home automation.\n\n\n🔑 **Before you begin:**\n\n• In Symcon, go to the *Remote Integration Driver* instance and click “Generate Token”.\n\n• There, select the devices to be controlled via the Unfolded Circle Remote. Only explicitly enabled devices will appear.\n\n\n\nℹ️ **What is Symcon?**\n\n• Symcon brings together many systems into one powerful platform:\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • AV systems, MQTT and more.\n\nThis allows seamless control of lighting, climate, blinds, sensors and automation scenes.\n\n👉 [Learn more](https://www.symcon.de/en)",
                                'fr' => "Cette intégration permet de connecter la télécommande Unfolded Circle à Symcon – la plateforme centrale pour l’automatisation des bâtiments et maisons intelligentes.\n\n\n🔑 **Avant de commencer :**\n• Dans Symcon, accédez à l’instance *Remote Integration Driver* et cliquez sur « Générer un jeton ».\n• Sélectionnez ensuite les appareils à contrôler via la télécommande. Seuls les appareils explicitement autorisés apparaîtront.\n\n\n\nℹ️ **Qu’est-ce que Symcon ?**\n\n• Symcon unifie de nombreux systèmes dans une plateforme puissante :\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • systèmes AV, MQTT, etc.\n\nCela permet un contrôle fluide de l’éclairage, du climat, des stores, des capteurs et des scènes.\n\n👉 [En savoir plus](https://www.symcon.de/fr)",
                                'it' => "Questa integrazione consente di collegare il telecomando Unfolded Circle a Symcon – la piattaforma centrale per l’automazione professionale di edifici e case.\n\n\n🔑 **Prima di iniziare:**\n• In Symcon, vai all'istanza *Remote Integration Driver* e fai clic su “Genera token”.\n• Seleziona i dispositivi da controllare con il telecomando. Solo i dispositivi autorizzati appariranno nell'integrazione.\n\n\n\nℹ️ **Cos’è Symcon?**\n\n• Symcon unisce molti sistemi in una potente piattaforma:\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • sistemi AV, MQTT e altro.\n\nPuoi controllare illuminazione, clima, tende, sensori e scenari complessi in modo fluido.\n\n👉 [Ulteriori informazioni](https://www.symcon.de/it)",
                                'es' => "Esta integración conecta el control remoto de Unfolded Circle con Symcon – la plataforma central para la automatización profesional de edificios y hogares.\n\n\n🔑 **Antes de comenzar:**\n• En Symcon, ve a la instancia *Remote Integration Driver* y haz clic en “Generar token”.\n• Luego selecciona los dispositivos a controlar. Solo aparecerán los autorizados explícitamente.\n\n\n\nℹ️ **¿Qué es Symcon?**\n\n• Symcon integra muchos sistemas en una plataforma potente:\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • sistemas AV, MQTT y más.\n\nPermite controlar fácilmente luces, clima, persianas, sensores y escenas automatizadas.\n\n👉 [Más información](https://www.symcon.de/es)",
                                'nl' => "Deze integratie verbindt de Unfolded Circle afstandsbediening met Symcon – het centrale platform voor professionele gebouw- en huisautomatisering.\n\n\n🔑 **Voordat je begint:**\n• Ga in Symcon naar de *Remote Integration Driver*-instantie en klik op “Token genereren”.\n• Selecteer de apparaten die via de afstandsbediening bediend moeten worden. Alleen expliciet geactiveerde apparaten worden weergegeven.\n\n\n\nℹ️ **Wat is Symcon?**\n\n• Symcon combineert vele systemen in één krachtig platform:\n\n  • KNX, LCN, DMX, Modbus, BACnet\n\n • Homematic IP, EnOcean, ZigBee, Z-Wave\n\n • AV-systemen, MQTT en meer.\n\nHiermee kunnen verlichting, klimaat, zonwering, sensoren en scènes eenvoudig worden bediend.\n\n👉 [Meer informatie](https://www.symcon.de/nl)"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function GetDriverMetadataCommon(): array
    {
        return array_merge(
            [
                'driver_id' => $this->GetDriverId(),
                'auth_method' => 'MESSAGE',
                'version' => $this->GetModuleLibraryVersion(),
                'min_core_api' => self::Unfolded_Circle_API_Minimum_Version,
            ],
            $this->GetDriverNameAndDescription(),
            [
                'icon' => 'custom:symcon_icon.png',
                'port' => 9988,
                'developer' => [
                    'name' => 'Fonzo',
                    'email' => 'aggadur@gmail.com',
                    'url' => 'https://www.symcon.de/en/module-store/'
                ],
                'home_page' => 'https://www.symcon.de/en/',
                'release_date' => '2026-03-05',
                'setup_data_schema' => $this->GetSetupDataSchema(),
            ]
        );
    }

    // IP-Adresse des Symcon Hosts ermitteln (erste gefundene IPv4 aus Sys_GetNetworkInfo)
    private function GetHostIP(): string
    {
        $network = Sys_GetNetworkInfo();
        $ip_host = [];
        foreach ($network as $device) {
            if (!isset($device['IP'])) {
                continue;
            }
            $ips = $device['IP'];
            if (!is_array($ips)) {
                $ips = [$ips];
            }
            foreach ($ips as $ip) {
                $ip = trim((string)$ip);
                // accept only IPv4 here
                if ($ip !== '' && preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $ip)) {
                    $ip_host[] = $ip;
                }
            }
        }
        return $ip_host[0] ?? '';
    }

    /**
     * Formats the client session list for display in the configuration form.
     *
     * @return array
     */
    private function FormatSessionList(): array
    {
        $sessions = $this->readSessions();  // uses the persistent client_sessions attribute
        $result = [];

        // Discovery-Instanz finden
        $discoveryId = @IPS_GetInstanceListByModuleID('{4C0ABD10-D25B-0D92-9B2A-9E10E24659B0}')[0] ?? 0;
        $knownRemotes = [];
        if ($discoveryId) {
            $knownRemotes = @UCR_GetKnownRemotes($discoveryId);
        }

        $seenIPs = [];

        foreach ($sessions as $clientKey => $info) {
            if (strpos($clientKey, ':') !== false) {
                [$ip, $port] = explode(':', $clientKey, 2);
            } else {
                $ip = $clientKey;
                $port = $info['port'] ?? '';
                if ($port === '') {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_FORM, "⚠️ No port found for clientKey: $clientKey", 0);
                    continue;
                }
            }

            if (in_array($ip, $seenIPs)) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_FORM, "ℹ️ Skipping duplicate IP: $ip", 0);
                continue;
            }
            $seenIPs[] = $ip;
            $remote = array_filter($knownRemotes, fn($r) => $r['host'] === $ip);
            $remote = array_values($remote)[0] ?? [];

            $result[] = [
                'name' => $remote['name'] ?? '—',
                'version' => $remote['version'] ?? '—',
                'api_version' => $remote['ver_api'] ?? '—',
                'model' => $remote['model'] ?? '—',
                'ip' => $ip,
                'port' => $port,
                'authenticated' => $info['authenticated'] ? '✅ Yes' : '❌ No',
                'last_seen' => $info['last_seen'] ?? 'N/A'
            ];
        }

        return $result;
    }

    /**
     * Entfernt alle client_sessions-Einträge mit ungültigem Key-Format oder fehlerhafter Struktur.
     * Kann manuell über ein Aktionsfeld im Formular ausgelöst werden.
     */
    public function CleanupClientSessions(): void
    {
        $sessions = json_decode($this->ReadAttributeString('client_sessions'), true);
        if (!is_array($sessions)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_FORM, '⚠️ client_sessions is not an array', 0);
            return;
        }

        $cleaned = [];

        foreach ($sessions as $clientKey => $info) {
            // Akzeptiere IP:Port oder IP-only, wenn Port im Info-Block vorhanden und numerisch
            if (strpos($clientKey, ':') === false) {
                if (!isset($info['port']) || !is_numeric($info['port'])) {
                    $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, "🧹 Removing stale IP key without valid port: $clientKey", 0);
                    continue;
                }
            }

            if (!is_array($info) || !isset($info['authenticated']) || !isset($info['subscribed'])) {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, "🧹 Removing invalid data block for $clientKey", 0);
                continue;
            }

            $cleaned[$clientKey] = $info;
        }

        $this->WriteAttributeString('client_sessions', json_encode($cleaned));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_FORM, '✅ Cleaned sessions: ' . json_encode($cleaned), 0);
    }

    /**
     * Resolves a Symcon variable ID for a given feature key within an instance.
     *
     * Strategy:
     * - Look up the instance module GUID and fetch its DeviceRegistry definition.
     * - Use the registry's `attributes` map to translate UC attributes to Symcon Idents.
     * - For media_player features: map feature -> required attributes via Entity_Media_Player::featureToAttributes()
     *   (fallback: treat featureKey itself as an attribute key).
     * - For lights: keep backward compatible mapping (on_off/dim/color/color_temperature).
     */
    private function ResolveFeatureVarID(int $instanceID, string $featureKey): ?int
    {
        if ($instanceID <= 0 || !@IPS_InstanceExists($instanceID)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "❌ Instance $instanceID does not exist", 0);
            return null;
        }

        $instance = IPS_GetInstance($instanceID);
        $guid = (string)($instance['ModuleInfo']['ModuleID'] ?? '');

        // DeviceRegistry mapping
        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_DISCOVERY, '❌ DeviceRegistry class not found', 0);
            return null;
        }

        $deviceDef = DeviceRegistry::resolveDeviceMapping($guid, $instanceID, null);
        if (!is_array($deviceDef)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "❌ No DeviceRegistry entry for GUID $guid (instance=$instanceID)", 0);
            return null;
        }

        $attrs = $deviceDef['attributes'] ?? null;
        if (!is_array($attrs)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "❌ DeviceRegistry entry for GUID $guid has no attributes map", 0);
            return null;
        }

        $deviceType = (string)($deviceDef['device_type'] ?? '');
        $featureKey = trim($featureKey);

        // Determine which UC attribute keys we need to satisfy this feature
        $attrKeys = [];

        if ($deviceType === 'media_player') {
            // Preferred: use Entity_Media_Player::featureToAttributes if available
            if (class_exists('Entity_Media_Player') && method_exists('Entity_Media_Player', 'featureToAttributes')) {
                try {
                    $mapped = Entity_Media_Player::featureToAttributes($featureKey);
                    if (is_array($mapped) && !empty($mapped)) {
                        $attrKeys = array_values(array_filter(array_map('strval', $mapped), fn($v) => trim($v) !== ''));
                    }
                } catch (Throwable $e) {
                    // ignore and fallback
                    $attrKeys = [];
                }
            }
            // Fallback: treat feature key itself as attribute key
            if (empty($attrKeys) && $featureKey !== '') {
                $attrKeys = [$featureKey];
            }
        } else {
            // Backward compatible for lights and others
            switch ($featureKey) {
                case 'on_off':
                    $attrKeys = ['state'];
                    break;
                case 'dim':
                    $attrKeys = ['brightness'];
                    break;
                case 'color':
                    // some registries may map hue or a combined color ident
                    $attrKeys = ['hue', 'saturation', 'color'];
                    break;
                case 'color_temperature':
                    $attrKeys = ['color_temperature'];
                    break;
                default:
                    // Generic fallback: try the feature key as attribute key
                    if ($featureKey !== '') {
                        $attrKeys = [$featureKey];
                    }
                    break;
            }
        }

        if (empty($attrKeys)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "❌ No attribute keys resolved for feature '$featureKey' (GUID $guid)", 0);
            return null;
        }

        // Resolve first usable attribute -> ident -> var id
        foreach ($attrKeys as $attrKey) {
            $attrKey = trim((string)$attrKey);
            if ($attrKey === '') {
                continue;
            }

            $ident = $attrs[$attrKey] ?? null;
            $ident = trim((string)$ident);

            // allow explicit opt-out for optional attrs
            if ($ident === '' || strtoupper($ident) === 'N/A') {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                    "ℹ️ Feature '$featureKey': attribute '$attrKey' has no ident (or N/A) for GUID $guid", 0);
                continue;
            }

            $varID = @IPS_GetObjectIDByIdent($ident, $instanceID);
            if ($varID && @IPS_VariableExists($varID)) {
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                    "✅ Resolved feature '$featureKey' via attr '$attrKey' ident '$ident' -> VarID $varID (instance=$instanceID)", 0);
                return (int)$varID;
            }

            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                "ℹ️ Feature '$featureKey': ident '$ident' not found in instance $instanceID", 0);
        }

        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
            "❌ Could not resolve VarID for feature '$featureKey' (instance=$instanceID, GUID $guid)", 0);

        return null;
    }

    /**
     * Erkennt den Sensor-Typ einer Variable anhand des Profils und gibt diesen per Debug aus.
     * Nutzt ausschließlich die übergebene Variable-ID und greift nicht auf Mapping oder RowIndex zu.
     *
     * Zusätzlich:
     * - Ermittelt die Unit aus dem Variablenprofil (Suffix)
     * - Übernimmt die Unit automatisch in das Formular
     * - Unit ist NUR bei Typ "generic" editierbar
     *
     * @param int $VariableID
     */
    public function AutoDetectSensorType(int $VariableID): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, "🔍 Auto-Erkennung Sensor-Typ für VarID $VariableID", 0);

        if (!@IPS_VariableExists($VariableID)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "❌ Variable $VariableID existiert nicht", 0);
            return;
        }

        $v = @IPS_GetVariable($VariableID);
        $profile = '';
        if (is_array($v)) {
            $profile = (string)($v['VariableCustomProfile'] ?? '');
            if (trim($profile) === '') {
                $profile = (string)($v['VariableProfile'] ?? '');
            }
        }

        $profileLower = strtolower(trim($profile));

        // Unit aus Variablenprofil (Suffix)
        $unit = '';
        if ($profileLower !== '' && @IPS_VariableProfileExists($profile)) {
            try {
                $p = IPS_GetVariableProfile($profile);
                $unit = trim((string)($p['Suffix'] ?? ''));
            } catch (Throwable $e) {
                $unit = '';
            }
        }

        // Sensor type detection (profile name + unit)
        // NOTE: Some Symcon profiles like ~Intensity.* also use '%' as suffix.
        // Those must NOT be mapped to 'humidity'. If Remote 3 has no matching type, we use 'generic'.
        $type = 'generic';

        $isIntensityLike = (
            strpos($profileLower, 'intensity') !== false ||
            strpos($profileLower, 'brightness') !== false ||
            strpos($profileLower, 'dimmer') !== false ||
            strpos($profileLower, '~intensity') !== false ||
            strpos($profileLower, '~brightness') !== false
        );

        // temperature
        if (
            strpos($profileLower, 'temp') !== false ||
            strpos($profileLower, 'temperature') !== false ||
            strpos($profileLower, 'celsius') !== false ||
            strpos($profileLower, '°c') !== false ||
            stripos($unit, '°c') !== false
        ) {
            $type = 'temperature';
        } // illuminance
        elseif (
            strpos($profileLower, 'lux') !== false ||
            strpos($profileLower, 'illum') !== false ||
            stripos($unit, 'lx') !== false ||
            stripos($unit, 'lux') !== false
        ) {
            $type = 'illuminance';
        } // voltage
        elseif (
            strpos($profileLower, 'volt') !== false ||
            strpos($profileLower, '~volt') !== false ||
            preg_match('/\bv\b/i', $unit) === 1
        ) {
            $type = 'voltage';
        } // humidity (ONLY if not intensity/brightness)
        elseif (
            !$isIntensityLike && (
                strpos($profileLower, 'humid') !== false ||
                strpos($profileLower, 'humidity') !== false ||
                strpos($profileLower, '~humidity') !== false ||
                trim($unit) === '%'
            )
        ) {
            $type = 'humidity';
        } // intensity/brightness is not a Remote 3 sensor type -> generic
        elseif ($isIntensityLike) {
            $type = 'generic';
        }

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, "✅ Ermittelter Typ für Profil '$profileLower' (unit='$unit'): $type", 0);

        // --- UI behaviour in row editor (sensor_mapping) ---
        $hasProfile = ($profile !== '' && @IPS_VariableProfileExists($profile));

        // If there is NO profile, we enforce generic (no other selection allowed)
        if (!$hasProfile) {
            $type = 'generic';
            $unit = '';
        }
        $type = $this->NormalizeRemoteSensorType($type);

        // Always write the resulting values into the editor fields
        $this->UpdateFormField('sensor_type', 'value', $type);
        $this->UpdateFormField('unit', 'value', $unit);

        // Never allow manual selection of sensor_type in the form.
        // Selection is driven by Symcon profile, or forced to generic when no profile exists.
        $this->UpdateFormField('sensor_type', 'visible', false);
        $this->UpdateFormField('sensor_type', 'enabled', false);

        // Unit: editable ONLY when we are in the "no profile" case (forced generic).
        // If a profile exists, unit is derived from Symcon suffix and must not be edited.
        $unitEditable = (!$hasProfile);
        $this->UpdateFormField('unit', 'visible', true);
        $this->UpdateFormField('unit', 'enabled', $unitEditable);
    }

    /**
     * Loads the unfoldedcircle logo as a base64 data URI for embedding in the form.
     *
     * @return string
     */
    private function LoadImageAsBase64(): string
    {
        $path = __DIR__ . '/../libs/unfoldedcircle_logo.png';
        $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_FORM, $path, 0);
        if (!file_exists($path)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_FORM, 'File not found: ' . $path, 0);
            return '';
        }
        $imageData = file_get_contents($path);
        $base64 = base64_encode($imageData);
        return 'data:image/png;base64,' . $base64;
    }

    /**
     * Loads suggestions for the device search popup.
     * First step: fill the Button (Script) list with all scripts from the Symcon object tree.
     */
    public function LoadDeviceSearchSuggestions(): void
    {
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '🔍 Loading device search suggestions (buttons + devices)', 0);

        // Step 1: Buttons (Scripts)
        $rows = $this->BuildButtonScriptSuggestions();
        $rows = $this->ApplyPopupSelectionState('popup_button_suggestions', 'script_id', $rows);
        $this->UpdateFormField('popup_button_suggestions', 'values', json_encode($rows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Button script suggestions loaded: ' . count($rows), 0);

        // Step 2: Lights (Instances)
        $lightRows = $this->BuildLightSuggestions();
        $lightRows = $this->ApplyPopupSelectionState('popup_light_suggestions', 'instance_id', $lightRows);
        $this->UpdateFormField('popup_light_suggestions', 'values', json_encode($lightRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Light suggestions loaded: ' . count($lightRows), 0);

        // Step 3: Covers (Instances)
        $coverRows = $this->BuildCoverSuggestions();
        $coverRows = $this->ApplyPopupSelectionState('popup_cover_suggestions', 'instance_id', $coverRows);
        $this->UpdateFormField('popup_cover_suggestions', 'values', json_encode($coverRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Cover suggestions loaded: ' . count($coverRows), 0);

        // Step 4: Mediaplayers (Instances)
        $mediaRows = $this->BuildMediaPlayerSuggestions();
        $mediaRows = $this->ApplyPopupSelectionState('popup_media_suggestions', 'instance_id', $mediaRows);
        $this->UpdateFormField('popup_media_suggestions', 'values', json_encode($mediaRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Media player suggestions loaded: ' . count($mediaRows), 0);

        // Step 5: Climate (Instances)
        $climateRows = $this->BuildClimateSuggestions();
        $climateRows = $this->ApplyPopupSelectionState('popup_climate_suggestions', 'instance_id', $climateRows);
        $this->UpdateFormField('popup_climate_suggestions', 'values', json_encode($climateRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Climate suggestions loaded: ' . count($climateRows), 0);

        // Step 6: Sensors (Variables)
        $sensorRows = $this->BuildSensorSuggestions();
        $sensorRows = $this->ApplyPopupSelectionState('popup_sensor_suggestions', 'var_id', $sensorRows);
        $this->UpdateFormField('popup_sensor_suggestions', 'values', json_encode($sensorRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Sensor suggestions loaded: ' . count($sensorRows), 0);

        // Step 7: Remotes (Instances)
        $remoteRows = $this->BuildRemoteSuggestions();
        $remoteRows = $this->ApplyPopupSelectionState('popup_remote_suggestions', 'instance_id', $remoteRows);
        $this->UpdateFormField('popup_remote_suggestions', 'values', json_encode($remoteRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Remote suggestions loaded: ' . count($remoteRows), 0);

        // Step 8: Switches (Instances)
        $switchRows = $this->BuildSwitchSuggestions();
        $switchRows = $this->ApplyPopupSelectionState('popup_switch_suggestions', 'instance_id', $switchRows);
        $this->UpdateFormField('popup_switch_suggestions', 'values', json_encode($switchRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Switch suggestions loaded: ' . count($switchRows), 0);

        // Step 9: Selects (Variables)
        $selectRows = $this->BuildSelectSuggestions();
        $selectRows = $this->ApplyPopupSelectionState('popup_select_suggestions', 'var_id', $selectRows);
        $this->UpdateFormField('popup_select_suggestions', 'values', json_encode($selectRows, JSON_UNESCAPED_SLASHES));
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY, '✅ Select suggestions loaded: ' . count($selectRows), 0);
    }

    /**
     * Build suggestions list for "Button (Script)".
     * A Remote "button" simply triggers a Symcon script.
     *
     * @return array[] Rows for the popup list.
     */
    private function BuildButtonScriptSuggestions(): array
    {
        $rows = [];

        // Get all scripts
        $scriptIDs = @IPS_GetScriptList();
        if (!is_array($scriptIDs)) {
            $scriptIDs = [];
        }

        foreach ($scriptIDs as $sid) {
            if (!is_int($sid) || !@IPS_ScriptExists($sid)) {
                continue;
            }

            $name = @IPS_GetName($sid);
            $path = $this->GetObjectPath($sid);

            $rows[] = [
                'register' => false,
                'label' => ($path !== '' ? ($path . ' → ') : '') . $name,
                'name' => $name,
                'script_id' => $sid
            ];
        }

        // Sort by label for a stable UI
        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Light" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     * First iteration: only list instances; mapping happens later.
     *
     * @return array[] Rows for the popup list.
     */
    private function BuildLightSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build light suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
            '🔎 Registry entries total: ' . (is_array($devices) ? count($devices) : 0), 0);

        if (!is_array($devices)) {
            return $rows;
        }

        // Collect unique GUIDs that have at least one light mapping.
        $lightGuids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_LIGHT) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $lightGuids[strtoupper($g)] = $g;
            }
        }

        if (empty($lightGuids)) {
            return $rows;
        }

        $preferredType = DeviceRegistry::DEVICE_TYPE_LIGHT;
        $seenInstanceIds = [];

        foreach ($lightGuids as $moduleGuid) {
            // Find instances by module GUID
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '📦 Instances found for GUID ' . $moduleGuid . ': ' . (is_array($instanceIDs) ? count($instanceIDs) : 0), 0);

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }

                if (isset($seenInstanceIds[$iid])) {
                    continue;
                }

                // Resolve best mapping for this concrete instance (supports duplicate GUIDs)
                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);

                // Filter: only real lights
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_LIGHT)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Light');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') {
                    $tag = 'Light';
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);

                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seenInstanceIds[$iid] = true;
            }
        }

        // Sort by label for a stable UI
        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Media Player" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     *
     * @return array[] Rows for the popup list.
     */
    private function BuildMediaPlayerSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build media player suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        // Collect unique GUIDs that have at least one media_player mapping.
        $mediaGuids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_MEDIA_PLAYER) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $mediaGuids[strtoupper($g)] = $g;
            }
        }

        if (empty($mediaGuids)) {
            return $rows;
        }

        $preferredType = DeviceRegistry::DEVICE_TYPE_MEDIA_PLAYER;
        $seenInstanceIds = [];

        foreach ($mediaGuids as $moduleGuid) {
            // Find instances by module GUID
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }

                if (isset($seenInstanceIds[$iid])) {
                    continue;
                }

                // Resolve best mapping for this concrete instance (supports duplicate GUIDs)
                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);

                // Filter: only real media players
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_MEDIA_PLAYER)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Media Player');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') {
                    $tag = 'Media Player';
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);

                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seenInstanceIds[$iid] = true;
            }
        }

        // Sort by label for stable UI
        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Cover" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     *
     * @return array[] Rows for the popup list.
     */
    private function BuildCoverSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build cover suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        // 1) Unique GUIDs that have at least one cover mapping
        $coverGuids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) continue;
            if (($def['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_COVER) continue;

            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $coverGuids[strtoupper($g)] = $g;
            }
        }

        if (empty($coverGuids)) {
            return $rows;
        }

        $preferredType = DeviceRegistry::DEVICE_TYPE_COVER;

        // 2) Avoid duplicates (important when multiple cover entries share same GUID)
        $seenInstanceIds = [];

        // 3) Iterate instances and resolve per instance
        foreach ($coverGuids as $moduleGuid) {

            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) continue;
                if (isset($seenInstanceIds[$iid])) continue;

                // Resolve mapping for this concrete instance (supports duplicate GUIDs)
                $deviceDef = null;
                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);

                // Filter: only real covers
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== DeviceRegistry::DEVICE_TYPE_COVER)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Cover');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') $tag = 'Cover';

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);

                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seenInstanceIds[$iid] = true;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Climate" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     */
    private function BuildClimateSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build climate suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        $preferredType = defined('DeviceRegistry::DEVICE_TYPE_CLIMATE') ? DeviceRegistry::DEVICE_TYPE_CLIMATE : 'climate';

        // Collect unique GUIDs that have at least one climate mapping.
        $guids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== $preferredType) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $guids[strtoupper($g)] = $g;
            }
        }

        if (empty($guids)) {
            return $rows;
        }

        $seen = [];

        foreach ($guids as $moduleGuid) {
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }
                if (isset($seen[$iid])) {
                    continue;
                }

                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== $preferredType)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Climate');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') {
                    $tag = 'Climate';
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);
                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seen[$iid] = true;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Sensor" devices.
     * IMPORTANT: Remote 3 has a 1-sensor-1-value concept.
     * Symcon instances may expose multiple sensor values (multiple child variables).
     * Therefore we list ONE ROW PER SENSOR VARIABLE (per Ident/VarID).
     */
    private function BuildSensorSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build sensor suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        $preferredType = defined('DeviceRegistry::DEVICE_TYPE_SENSOR') ? DeviceRegistry::DEVICE_TYPE_SENSOR : 'sensor';

        // Build an index of sensor definitions per module GUID.
        $defsByGuid = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== $preferredType) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g === '') {
                continue;
            }
            $defsByGuid[strtoupper($g)][] = $def;
        }

        if (empty($defsByGuid)) {
            return $rows;
        }

        $seenVarIds = [];

        foreach ($defsByGuid as $moduleGuidUpper => $defs) {
            $moduleGuid = (string)($defs[0]['guid'] ?? '');
            if ($moduleGuid === '') {
                // fallback to upper key
                $moduleGuid = $moduleGuidUpper;
            }

            // Find instances by module GUID
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);

                // For each registry def, check via matcher, and create one row per matching ident.
                foreach ($defs as $deviceDef) {
                    if (!is_array($deviceDef)) {
                        continue;
                    }

                    $attrs = $deviceDef['attributes'] ?? null;
                    if (!is_array($attrs)) {
                        continue;
                    }

                    // Determine the value-ident for this def (Netatmo uses ATTR_VALUE => Ident like 'Temperature').
                    $valueIdent = '';
                    if (class_exists('Entity_Sensor') && defined('Entity_Sensor::ATTR_VALUE')) {
                        $valueIdent = trim((string)($attrs[Entity_Sensor::ATTR_VALUE] ?? ''));
                    }
                    if ($valueIdent === '') {
                        // Fallback key
                        $valueIdent = trim((string)($attrs['value'] ?? ''));
                    }

                    // Match filter via encapsulated matcher
                    if (!$this->DoesSensorDefinitionMatchInstance($deviceDef, $iid)) {
                        continue;
                    }

                    if ($valueIdent === '') {
                        continue;
                    }

                    $varId = @IPS_GetObjectIDByIdent($valueIdent, $iid);
                    if (!$varId || !@IPS_VariableExists($varId)) {
                        continue;
                    }

                    $varId = (int)$varId;
                    if (isset($seenVarIds[$varId])) {
                        continue;
                    }

                    // Unit: prefer registry literal unit (unit:...), else infer from profile.
                    $unit = '';
                    if (class_exists('Entity_Sensor') && defined('Entity_Sensor::ATTR_UNIT')) {
                        try {
                            $u = DeviceRegistry::ResolveFeatureVarID(DeviceRegistry::DEVICE_TYPE_SENSOR, $attrs, Entity_Sensor::ATTR_UNIT);
                            if (is_string($u)) {
                                $unit = trim($u);
                            }
                        } catch (Throwable $e) {
                            $unit = '';
                        }
                    }
                    if ($unit === '') {
                        $unit = $this->GuessUnitForVariable($varId);
                    }

                    // Sensor type: prefer custom_sub_type (Netatmo), else device_sub_type, else 'custom'
                    $sensorType = trim((string)($deviceDef['custom_sub_type'] ?? ($deviceDef['device_sub_type'] ?? 'custom')));
                    if ($sensorType === '') {
                        $sensorType = 'custom';
                    }

                    $registryName = (string)($deviceDef['name'] ?? 'Sensor');
                    $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                    $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                    if ($tag === '') {
                        $tag = 'Sensor';
                    }

                    $varName = (string)@IPS_GetName($varId);

                    // Label shows instance path + instance + variable
                    $base = ($path !== '' ? ($path . ' → ') : '') . $instName . ' → ' . $varName;
                    $label = '[' . $tag . '] ' . $base;

                    $rows[] = [
                        'register' => false,
                        'label' => $label,
                        'name' => $varName,
                        'instance_id' => (int)$iid,
                        'var_id' => (int)$varId,
                        'sensor_type' => (string)$sensorType,
                        'unit' => (string)$unit,
                        'registry_name' => $registryName
                    ];

                    $seenVarIds[$varId] = true;
                }
            }
        }

        // Sort by label for a stable UI
        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Remote" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     */
    private function BuildRemoteSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build remote suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        $preferredType = defined('DeviceRegistry::DEVICE_TYPE_REMOTE') ? DeviceRegistry::DEVICE_TYPE_REMOTE : 'remote';

        $guids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== $preferredType) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $guids[strtoupper($g)] = $g;
            }
        }

        if (empty($guids)) {
            return $rows;
        }

        $seen = [];

        foreach ($guids as $moduleGuid) {
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }
                if (isset($seen[$iid])) {
                    continue;
                }

                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== $preferredType)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Remote');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') {
                    $tag = 'Remote';
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);
                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seen[$iid] = true;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }


    /**
     * Build suggestions list for "Switch" devices.
     * Uses DeviceRegistry definitions (module GUID) to find matching instances.
     */
    private function BuildSwitchSuggestions(): array
    {
        $rows = [];

        if (!class_exists('DeviceRegistry')) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                '⚠️ DeviceRegistry class not found – cannot build switch suggestions', 0);
            return $rows;
        }

        $devices = DeviceRegistry::getSupportedDevices();
        if (!is_array($devices)) {
            return $rows;
        }

        $preferredType = defined('DeviceRegistry::DEVICE_TYPE_SWITCH') ? DeviceRegistry::DEVICE_TYPE_SWITCH : 'switch';

        $guids = [];
        foreach ($devices as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (($def['device_type'] ?? '') !== $preferredType) {
                continue;
            }
            $g = trim((string)($def['guid'] ?? ''));
            if ($g !== '') {
                $guids[strtoupper($g)] = $g;
            }
        }

        if (empty($guids)) {
            return $rows;
        }

        $seen = [];

        foreach ($guids as $moduleGuid) {
            $instanceIDs = [];
            try {
                $instanceIDs = @IPS_GetInstanceListByModuleID($moduleGuid);
            } catch (Throwable $e) {
                $instanceIDs = [];
            }

            if (!is_array($instanceIDs) || empty($instanceIDs)) {
                continue;
            }

            foreach ($instanceIDs as $iid) {
                if (!is_int($iid) || !@IPS_InstanceExists($iid)) {
                    continue;
                }
                if (isset($seen[$iid])) {
                    continue;
                }

                $deviceDef = DeviceRegistry::resolveDeviceMapping($moduleGuid, $iid, $preferredType);
                if (!is_array($deviceDef) || (($deviceDef['device_type'] ?? '') !== $preferredType)) {
                    continue;
                }

                $registryName = (string)($deviceDef['name'] ?? 'Switch');
                $manufacturer = (string)($deviceDef['manufacturer'] ?? '');
                $tag = trim(($manufacturer !== '' ? ($manufacturer . ' ') : '') . $registryName);
                if ($tag === '') {
                    $tag = 'Switch';
                }

                $instName = (string)@IPS_GetName($iid);
                $path = $this->GetObjectPath($iid);
                $label = ($path !== '' ? ($path . ' → ') : '') . $instName;
                $label = '[' . $tag . '] ' . $label;

                $rows[] = [
                    'register' => false,
                    'label' => $label,
                    'name' => $instName,
                    'instance_id' => $iid,
                    'registry_name' => $registryName
                ];

                $seen[$iid] = true;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $rows;
    }

    /**
     * Build suggestions list for "Select" devices.
     * A Select in Symcon is typically represented by a variable with a profile
     * that contains associations (discrete selectable options).
     *
     * We therefore scan all variables and offer those that:
     * - have a valid variable profile (custom or default)
     * - the profile contains at least 2 associations
     * - are not simple binary on/off variables
     *
     * @return array[] Rows for the popup list.
     */
    private function BuildSelectSuggestions(): array
    {
        $rows = [];
        $seenVarIds = [];

        $variableIds = @IPS_GetVariableList();
        if (!is_array($variableIds)) {
            $variableIds = [];
        }

        foreach ($variableIds as $varId) {
            if (!is_int($varId) || !@IPS_VariableExists($varId)) {
                continue;
            }

            $var = @IPS_GetVariable($varId);
            if (!is_array($var)) {
                continue;
            }

            $profileName = trim((string)($var['VariableCustomProfile'] ?? ''));
            if ($profileName === '') {
                $profileName = trim((string)($var['VariableProfile'] ?? ''));
            }
            if ($profileName === '' || !@IPS_VariableProfileExists($profileName)) {
                continue;
            }

            try {
                $profile = @IPS_GetVariableProfile($profileName);
            } catch (Throwable $e) {
                $profile = null;
            }

            if (!is_array($profile)) {
                continue;
            }

            $associations = $profile['Associations'] ?? [];
            if (!is_array($associations) || count($associations) < 2) {
                continue;
            }

            // Exclude simple binary variables (typical switch profiles with 2 states 0/1)
            $isBinary = false;
            if (count($associations) === 2) {
                $values = array_values(array_map(static function ($a) {
                    return (int)($a['Value'] ?? -999999);
                }, $associations));
                sort($values);
                if ($values === [0, 1]) {
                    $isBinary = true;
                }
            }
            if ($isBinary) {
                continue;
            }

            $parentId = (int)@IPS_GetParent($varId);
            if ($parentId <= 0) {
                continue;
            }

            $instanceId = $parentId;
            if (!@IPS_InstanceExists($instanceId)) {
                $instanceId = (int)@IPS_GetParent($parentId);
            }
            if ($instanceId <= 0 || !@IPS_InstanceExists($instanceId)) {
                continue;
            }

            if (isset($seenVarIds[$varId])) {
                continue;
            }

            $instName = (string)@IPS_GetName($instanceId);
            $varName = (string)@IPS_GetName($varId);
            $path = $this->GetObjectPath($varId);

            $displayName = $varName !== '' ? $varName : ('Select ' . $varId);
            $labelBase = ($path !== '' ? ($path . ' → ') : '') . $displayName;
            $label = '[Select] ' . $labelBase;

            $name = $instName;
            if ($name !== '' && $varName !== '') {
                $name .= ' – ' . $varName;
            } elseif ($varName !== '') {
                $name = $varName;
            } elseif ($name === '') {
                $name = 'Select';
            }

            $rows[] = [
                'register' => false,
                'label' => $label,
                'name' => $name,
                'instance_id' => (int)$instanceId,
                'var_id' => (int)$varId,
                'profile' => $profileName
            ];

            $seenVarIds[$varId] = true;
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
            '✅ Built select suggestions: ' . count($rows), 0);

        return $rows;
    }

    /**
     * Stores a single selected row from a popup list into an attribute.
     * Universal: supports different key fields (e.g. script_id for buttons, instance_id for others).
     * IPSModuleStrict: public methods must use scalar types; we accept strings only.
     *
     * @param string $listName Attribute name (e.g. "popup_button_suggestions")
     * @param string $register "1"/"0" or "true"/"false"
     * @param string $keyField Key column name (e.g. "script_id" or "instance_id")
     * @param string $keyValue Key value (e.g. "12345")
     */
    public function StorePopupList(string $listName, string $register, string $keyField, string $keyValue): void
    {
        $reg = in_array(strtolower(trim($register)), ['1', 'true', 'yes', 'on'], true);
        $keyField = trim($keyField);
        $keyId = (int)trim($keyValue);

        if ($keyField === '') {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "⚠️ StorePopupList: empty keyField for list '$listName'", 0);
            return;
        }

        // Defensive: ignore empty keys
        if ($keyId <= 0) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY, "⚠️ StorePopupList: invalid keyValue='$keyValue' for keyField='$keyField'", 0);
            return;
        }

        // Read current attribute content (JSON array)
        $raw = trim((string)$this->ReadAttributeString($listName));
        $rows = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        // Update/insert row by key field
        $updated = false;
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int)($row[$keyField] ?? 0) === $keyId) {
                $row['register'] = $reg;
                $row[$keyField] = $keyId;
                $updated = true;
                break;
            }
        }
        unset($row);

        if (!$updated) {
            $rows[] = [
                'register' => $reg,
                $keyField => $keyId
            ];
        }

        $this->WriteAttributeString($listName, json_encode($rows, JSON_UNESCAPED_SLASHES));

        $this->Debug(
            __FUNCTION__,
            self::LV_INFO,
            self::TOPIC_DISCOVERY,
            "💾 Stored selected row into attribute '$listName' ($keyField=$keyId register=" . ($reg ? 'true' : 'false') . ")",
            0
        );
    }

    public function StorePopupSensorSelection(string $register, string $instanceId, string $varId): void
    {
        $reg = in_array(strtolower(trim($register)), ['1', 'true', 'yes', 'on'], true);
        $iid = (int)trim($instanceId);
        $vid = (int)trim($varId);

        if ($vid <= 0) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                "⚠️ StorePopupSensorSelection: invalid varId='$varId'", 0);
            return;
        }

        // Read current attribute content (JSON array)
        $listName = 'popup_sensor_suggestions';
        $raw = trim((string)$this->ReadAttributeString($listName));
        $rows = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        // Update/insert row by var_id
        $updated = false;
        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            if ((int)($row['var_id'] ?? 0) === $vid) {
                $row['register'] = $reg;
                $row['var_id'] = $vid;
                $row['instance_id'] = $iid; // keep it even if 0, but normally >0
                $updated = true;
                break;
            }
        }
        unset($row);

        if (!$updated) {
            $rows[] = [
                'register' => $reg,
                'var_id' => $vid,
                'instance_id' => $iid
            ];
        }

        $this->WriteAttributeString($listName, json_encode($rows, JSON_UNESCAPED_SLASHES));

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
            "💾 Stored sensor selection (var_id=$vid instance_id=$iid register=" . ($reg ? 'true' : 'false') . ")",
            0
        );
    }

    /**
     * Applies cached register-state from an attribute to freshly built popup rows.
     *
     * @param string $listName Attribute name (e.g. popup_button_suggestions)
     * @param string $keyField Key column (e.g. script_id / instance_id)
     * @param array $rows Fresh rows built for the list
     * @return array Updated rows with register state restored
     */
    private function ApplyPopupSelectionState(string $listName, string $keyField, array $rows): array
    {
        $raw = trim((string)$this->ReadAttributeString($listName));
        if ($raw === '') {
            return $rows;
        }

        $cached = json_decode($raw, true);
        if (!is_array($cached)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                "⚠️ Cached attribute '$listName' is not valid JSON", 0);
            return $rows;
        }

        // Build map: keyId => register(bool)
        $map = [];
        foreach ($cached as $c) {
            if (!is_array($c)) continue;
            $id = (int)($c[$keyField] ?? 0);
            if ($id <= 0) continue;
            $map[$id] = !empty($c['register']);
        }

        if (empty($map)) {
            return $rows;
        }

        // Apply to fresh rows
        foreach ($rows as &$r) {
            if (!is_array($r)) continue;
            $id = (int)($r[$keyField] ?? 0);
            if ($id <= 0) continue;

            if (array_key_exists($id, $map)) {
                $r['register'] = (bool)$map[$id];
            }
        }
        unset($r);

        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
            "✅ Restored selection state for '$listName' (" . count($map) . " cached keys)", 0);

        return $rows;
    }

    /**
     * Reads a popup list cache attribute (stored by StorePopupList) and returns only selected rows.
     *
     * @param string $listName Attribute name, e.g. "popup_media_suggestions"
     * @param string $keyField Key column, e.g. "instance_id" or "script_id"
     * @return array Selected rows (register=true) with a valid keyField value
     */
    private function ReadSelectedFromPopupCache(string $listName, string $keyField): array
    {
        $raw = trim((string)$this->ReadAttributeString($listName));
        if ($raw === '') {
            return [];
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                "⚠️ Cached attribute '$listName' is not valid JSON", 0);
            return [];
        }

        $selected = array_values(array_filter($rows, function ($r) use ($keyField) {
            if (!is_array($r)) {
                return false;
            }
            if (empty($r['register'])) {
                return false;
            }
            $id = (int)($r[$keyField] ?? 0);
            return $id > 0;
        }));

        return $selected;
    }

    public function ApplySuggestedDevices(): void
    {
        try {
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 1: buttons)', 0);

            $raw = (string)$this->ReadAttributeString('popup_button_suggestions');
            $raw = trim($raw);
            if ($raw === '') {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                    '⚠️ No cached popup_button_suggestions attribute found (did onEdit fire?)', 0);
                return;
            }

            $rows = json_decode($raw, true);
            if (!is_array($rows)) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                    '⚠️ Cached popup_button_suggestions attribute is not valid JSON', 0);
                $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                    'Raw popup_button_suggestions attribute:' . $raw, 0);
                return;
            }

            $selected = array_values(array_filter($rows, fn($r) => is_array($r) && !empty($r['register'])));
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected button rows: ' . count($selected), 0);

            if (!$selected) {
                return;
            }

            // existierendes Mapping holen
            $existing = json_decode((string)$this->ReadPropertyString('button_mapping'), true);
            if (!is_array($existing)) $existing = [];

            $existingIds = [];
            foreach ($existing as $e) {
                if (is_array($e) && isset($e['script_id'])) $existingIds[(int)$e['script_id']] = true;
            }

            $added = 0;
            foreach ($selected as $s) {
                $sid = (int)($s['script_id'] ?? 0);
                if ($sid <= 0 || !IPS_ScriptExists($sid)) continue;
                if (isset($existingIds[$sid])) continue;

                $name = (string)($s['name'] ?? IPS_GetName($sid));
                $existing[] = ['name' => $name, 'script_id' => $sid];
                $existingIds[$sid] = true;
                $added++;
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Buttons added to mapping: ' . $added, 0);

            // UI updaten
            $this->UpdateFormField('button_mapping', 'values', json_encode($existing, JSON_UNESCAPED_SLASHES));

            // -------------------------
            // Step 2: Lights
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 2: lights)', 0);

            $rawLights = trim((string)$this->ReadAttributeString('popup_light_suggestions'));
            if ($rawLights === '') {
                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    'ℹ️ No cached popup_light_suggestions attribute found (no light selections)', 0);
                return;
            }

            $lightRows = json_decode($rawLights, true);
            if (!is_array($lightRows)) {
                $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                    '⚠️ Cached popup_light_suggestions attribute is not valid JSON', 0);
                return;
            }

            $selectedLights = array_values(array_filter($lightRows, fn($r) => is_array($r) && !empty($r['register']) && !empty($r['instance_id'])
            ));

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected light rows: ' . count($selectedLights), 0);

            if (!$selectedLights) {
                return;
            }

            // existierendes Light-Mapping holen
            $existingLights = json_decode((string)$this->ReadPropertyString('light_mapping'), true);
            if (!is_array($existingLights)) $existingLights = [];

            $existingInstanceIds = [];
            foreach ($existingLights as $e) {
                if (is_array($e) && isset($e['instance_id'])) {
                    $existingInstanceIds[(int)$e['instance_id']] = true;
                }
            }

            $addedLights = 0;
            foreach ($selectedLights as $s) {
                $iid = (int)($s['instance_id'] ?? 0);
                if ($iid <= 0 || !IPS_InstanceExists($iid)) continue;
                if (isset($existingInstanceIds[$iid])) continue;

                // Resolve variables via DeviceRegistry mapping
                $switchVar = $this->ResolveFeatureVarID($iid, 'on_off');
                if (!$switchVar || !IPS_VariableExists($switchVar)) {
                    $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                        "⚠️ Skipping light instance $iid: no 'on_off' variable found via DeviceRegistry", 0);
                    continue;
                }

                $brightnessVar = $this->ResolveFeatureVarID($iid, 'dim') ?? 0;
                $colorVar = $this->ResolveFeatureVarID($iid, 'color') ?? 0;
                $colorTempVar = $this->ResolveFeatureVarID($iid, 'color_temperature') ?? 0;

                $existingLights[] = [
                    'name' => IPS_GetName($iid),
                    'instance_id' => $iid,
                    'switch_var_id' => $switchVar,
                    'brightness_var_id' => (int)$brightnessVar,
                    'color_var_id' => (int)$colorVar,
                    'color_temp_var_id' => (int)$colorTempVar
                ];

                $existingInstanceIds[$iid] = true;
                $addedLights++;
            }

            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Lights added to mapping: ' . $addedLights, 0);

            // UI updaten
            $this->UpdateFormField('light_mapping', 'values', json_encode($existingLights, JSON_UNESCAPED_SLASHES));

            // -------------------------
            // Step 2b: Climate
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 2b: climate)', 0);

            $climateSelected = $this->ReadSelectedFromPopupCache('popup_climate_suggestions', 'instance_id');
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected climate rows: ' . count($climateSelected), 0);

            if ($climateSelected) {
                // existing Climate mapping
                $existingClimate = json_decode((string)$this->ReadPropertyString('climate_mapping'), true);
                if (!is_array($existingClimate)) {
                    $existingClimate = [];
                }

                $existingClimateInstanceIds = [];
                foreach ($existingClimate as $e) {
                    if (is_array($e) && isset($e['instance_id'])) {
                        $existingClimateInstanceIds[(int)$e['instance_id']] = true;
                    }
                }

                $addedClimate = 0;

                foreach ($climateSelected as $s) {
                    $iid = (int)($s['instance_id'] ?? 0);
                    if ($iid <= 0 || !@IPS_InstanceExists($iid)) {
                        continue;
                    }
                    if (isset($existingClimateInstanceIds[$iid])) {
                        continue;
                    }

                    // Resolve variables via DeviceRegistry mapping.
                    // Fallback keys cover common thermostat implementations.
                    $statusVar = (int)($this->ResolveFeatureVarID($iid, 'status') ?? 0);
                    if ($statusVar <= 0) {
                        $statusVar = (int)($this->ResolveFeatureVarID($iid, 'state') ?? 0);
                    }

                    $currentTempVar = (int)($this->ResolveFeatureVarID($iid, 'current_temperature') ?? 0);
                    if ($currentTempVar <= 0) {
                        $currentTempVar = (int)($this->ResolveFeatureVarID($iid, 'temperature') ?? 0);
                    }

                    $targetTempVar = (int)($this->ResolveFeatureVarID($iid, 'target_temperature') ?? 0);
                    if ($targetTempVar <= 0) {
                        $targetTempVar = (int)($this->ResolveFeatureVarID($iid, 'setpoint') ?? 0);
                    }

                    $modeVar = (int)($this->ResolveFeatureVarID($iid, 'mode') ?? 0);
                    if ($modeVar <= 0) {
                        $modeVar = (int)($this->ResolveFeatureVarID($iid, 'operation_mode') ?? 0);
                    }

                    // Validate variables (0 is allowed for optional fields, but at least one should exist)
                    foreach (['statusVar' => $statusVar, 'currentTempVar' => $currentTempVar, 'targetTempVar' => $targetTempVar, 'modeVar' => $modeVar] as $k => $vid) {
                        if ($vid > 0 && !@IPS_VariableExists($vid)) {
                            $$k = 0;
                        }
                    }

                    if ($statusVar <= 0 && $currentTempVar <= 0 && $targetTempVar <= 0 && $modeVar <= 0) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            "⚠️ Skipping climate instance $iid: no climate variables resolved via DeviceRegistry", 0);
                        continue;
                    }

                    $existingClimate[] = [
                        'name' => (string)@IPS_GetName($iid),
                        'instance_id' => $iid,
                        'status_var_id' => $statusVar,
                        'current_temp_var_id' => $currentTempVar,
                        'target_temp_var_id' => $targetTempVar,
                        'mode_var_id' => $modeVar
                    ];

                    $existingClimateInstanceIds[$iid] = true;
                    $addedClimate++;
                }

                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    '➕ Climate devices added to mapping: ' . $addedClimate, 0);

                $this->UpdateFormField('climate_mapping', 'values', json_encode($existingClimate, JSON_UNESCAPED_SLASHES));
            }

            // -------------------------
            // Step 3: Covers
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 3: covers)', 0);

            $coverSelected = $this->ReadSelectedFromPopupCache('popup_cover_suggestions', 'instance_id');
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected cover rows: ' . count($coverSelected), 0);

            if (!$coverSelected) {
                // continue with next step
            } else {
                // existing Cover mapping
                $existingCovers = json_decode((string)$this->ReadPropertyString('cover_mapping'), true);
                if (!is_array($existingCovers)) {
                    $existingCovers = [];
                }

                $existingCoverInstanceIds = [];
                foreach ($existingCovers as $e) {
                    if (is_array($e) && isset($e['instance_id'])) {
                        $existingCoverInstanceIds[(int)$e['instance_id']] = true;
                    }
                }

                $addedCovers = 0;

                foreach ($coverSelected as $s) {
                    $iid = (int)($s['instance_id'] ?? 0);
                    if ($iid <= 0 || !IPS_InstanceExists($iid)) {
                        continue;
                    }
                    if (isset($existingCoverInstanceIds[$iid])) {
                        continue;
                    }

                    // Resolve variables via DeviceRegistry mapping
                    // For covers, position is typically the primary control/state variable (e.g. Ident 'LEVEL').
                    $positionVar = $this->ResolveFeatureVarID($iid, 'position');
                    if (!$positionVar || !IPS_VariableExists($positionVar)) {
                        // Fallback: try open/close features (both usually map to position)
                        $positionVar = $this->ResolveFeatureVarID($iid, 'open');
                    }

                    if (!$positionVar || !IPS_VariableExists($positionVar)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            "⚠️ Skipping cover instance $iid: no 'position' variable found via DeviceRegistry", 0);
                        continue;
                    }

                    // Some cover integrations may provide a separate control/action variable.
                    // If not present (common for Homematic IP/HCU where position variable is writable),
                    // fall back to using the position variable for control.
                    $controlVar = $this->ResolveFeatureVarID($iid, 'control') ?? 0;
                    if ($controlVar && !IPS_VariableExists($controlVar)) {
                        $controlVar = 0;
                    }

                    if ((int)$controlVar <= 0) {
                        $controlVar = (int)$positionVar;
                        $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                            "ℹ️ Cover instance $iid: control variable not resolved – using position_var_id=$controlVar as control", 0);
                    }

                    $existingCovers[] = [
                        'name' => IPS_GetName($iid),
                        'instance_id' => $iid,
                        'position_var_id' => (int)$positionVar,
                        'control_var_id' => (int)$controlVar
                    ];

                    $existingCoverInstanceIds[$iid] = true;
                    $addedCovers++;
                }

                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    '➕ Covers added to mapping: ' . $addedCovers, 0);

                $this->UpdateFormField('cover_mapping', 'values', json_encode($existingCovers, JSON_UNESCAPED_SLASHES));
            }

            // -------------------------
            // Step 4: Media Players
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 4: media players)', 0);

            $mediaSelected = $this->ReadSelectedFromPopupCache('popup_media_suggestions', 'instance_id');
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected media player rows: ' . count($mediaSelected), 0);

            if (!$mediaSelected) {
                // continue with next step
            } else {
                // existing Media-Player mapping
                $existingMedia = json_decode((string)$this->ReadPropertyString('media_player_mapping'), true);
                if (!is_array($existingMedia)) $existingMedia = [];

                $existingMediaInstanceIds = [];
                foreach ($existingMedia as $e) {
                    if (is_array($e) && isset($e['instance_id'])) {
                        $existingMediaInstanceIds[(int)$e['instance_id']] = true;
                    }
                }

                $addedMedia = 0;

                foreach ($mediaSelected as $s) {
                    $iid = (int)($s['instance_id'] ?? 0);
                    if ($iid <= 0 || !IPS_InstanceExists($iid)) {
                        continue;
                    }
                    if (isset($existingMediaInstanceIds[$iid])) {
                        continue;
                    }

                    // Determine module GUID of this instance
                    $inst = IPS_GetInstance($iid);
                    $guid = $inst['ModuleInfo']['ModuleID'] ?? '';

                    // Lookup device definition from registry
                    $deviceDef = null;
                    if (class_exists('DeviceRegistry')) {
                        $deviceDef = DeviceRegistry::resolveDeviceMapping($guid, $iid, null);
                    }

                    if (!is_array($deviceDef)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            "⚠️ Skipping media player instance $iid: no DeviceRegistry entry for GUID $guid", 0);
                        continue;
                    }

                    $features = $deviceDef['features'] ?? [];
                    if (!is_array($features)) {
                        $features = [];
                    }

                    $featureRows = [];
                    foreach ($features as $featureKey) {
                        $featureKey = (string)$featureKey;
                        if ($featureKey === '') continue;

                        // Uses ResolveFeatureVarID() — you will map features via DeviceRegistry
                        $varId = $this->ResolveFeatureVarID($iid, $featureKey);
                        if (!$varId || !IPS_VariableExists($varId)) {
                            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                                "ℹ️ Media player $iid: feature '$featureKey' not resolved (missing ident/var)", 0);
                            continue;
                        }

                        $featureRows[] = [
                            'feature_key' => $featureKey,
                            'var_id' => (int)$varId
                        ];
                    }

                    $existingMedia[] = [
                        'name' => IPS_GetName($iid),
                        'instance_id' => $iid,
                        'features' => $featureRows
                    ];

                    $existingMediaInstanceIds[$iid] = true;
                    $addedMedia++;
                }

                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    '➕ Media players added to mapping: ' . $addedMedia, 0);

                $this->UpdateFormField('media_player_mapping', 'values', json_encode($existingMedia, JSON_UNESCAPED_SLASHES));
            }

            // -------------------------
            // Step 5: Sensors
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 5: sensors)', 0);

            $sensorSelected = $this->ReadSelectedFromPopupCache('popup_sensor_suggestions', 'var_id');
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected sensor rows: ' . count($sensorSelected), 0);

            if (!$sensorSelected) {
                // continue with next step
            } else {
                // existing Sensor mapping
                $existingSensors = json_decode((string)$this->ReadPropertyString('sensor_mapping'), true);
                if (!is_array($existingSensors)) {
                    $existingSensors = [];
                }

                // Uniqueness: allow multiple rows per instance if they map to different var_id
                $existingKeys = [];
                foreach ($existingSensors as $e) {
                    if (!is_array($e)) continue;
                    $iid0 = (int)($e['instance_id'] ?? 0);
                    $vid0 = (int)($e['var_id'] ?? 0);
                    if ($iid0 > 0 && $vid0 > 0) {
                        $existingKeys[$iid0 . ':' . $vid0] = true;
                    }
                }

                $addedSensors = 0;

                foreach ($sensorSelected as $s) {
                    $iid = (int)($s['instance_id'] ?? 0);
                    $varId = (int)($s['var_id'] ?? 0);

                    if ($varId <= 0 || !@IPS_VariableExists($varId)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            '⚠️ Skipping sensor row: invalid/missing var_id', 0);
                        continue;
                    }

                    // Popup cache may only store {register,var_id}. Derive instance_id from variable parent if missing.
                    if ($iid <= 0) {
                        $iid = (int)@IPS_GetParent($varId);
                    }

                    if ($iid <= 0 || !@IPS_InstanceExists($iid)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            "⚠️ Skipping sensor varId=$varId: could not resolve valid instance_id (parent=$iid)", 0);
                        continue;
                    }

                    // Unit comes from Symcon variable profile suffix/prefix (if any)
                    $unit = $this->GuessUnitForVariable($varId);

                    // Derive a Remote 3 compatible sensor type; unknown => generic
                    $sensorType = $this->DeriveRemoteSensorTypeForVariable($varId, $unit);

                    $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                        "🧪 Sensor candidate: iid=$iid varId=$varId type=$sensorType unit=$unit", 0);

                    $key = $iid . ':' . (int)$varId;
                    if (isset($existingKeys[$key])) {
                        continue;
                    }

                    // Insert helper for short sensor name
                    $instName = trim((string)@IPS_GetName($iid));
                    $varName = trim((string)@IPS_GetName($varId));
                    $shortName = $instName;
                    if ($shortName !== '' && $varName !== '') {
                        $shortName .= ' – ' . $varName;
                    } elseif ($varName !== '') {
                        $shortName = $varName;
                    } elseif ($shortName === '') {
                        $shortName = 'Sensor';
                    }

                    $existingSensors[] = [
                        'name' => $shortName,
                        'instance_id' => $iid,
                        'var_id' => (int)$varId,
                        'unit' => (string)$unit,
                        'sensor_type' => (string)$sensorType
                    ];

                    $existingKeys[$key] = true;
                    $addedSensors++;
                }

                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    '➕ Sensors added to mapping: ' . $addedSensors, 0);

                $this->UpdateFormField('sensor_mapping', 'values', json_encode($existingSensors, JSON_UNESCAPED_SLASHES));
            }

            // -------------------------
            // Step 6: Select
            // -------------------------
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '➕ Applying suggested devices (step 6: select)', 0);

            $selectSelected = $this->ReadSelectedFromPopupCache('popup_select_suggestions', 'var_id');
            $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                '✅ Selected select rows: ' . count($selectSelected), 0);

            if (!$selectSelected) {
                // continue with next step
            } else {
                // existing Select mapping
                $existingSelects = json_decode((string)$this->ReadPropertyString('select_mapping'), true);
                if (!is_array($existingSelects)) {
                    $existingSelects = [];
                }

                // Uniqueness: allow multiple rows per instance only if var_id differs
                $existingKeys = [];
                foreach ($existingSelects as $e) {
                    if (!is_array($e)) {
                        continue;
                    }
                    $iid0 = (int)($e['instance_id'] ?? 0);
                    $vid0 = (int)($e['var_id'] ?? 0);
                    if ($iid0 > 0 && $vid0 > 0) {
                        $existingKeys[$iid0 . ':' . $vid0] = true;
                    }
                }

                $addedSelects = 0;

                foreach ($selectSelected as $s) {
                    $iid = (int)($s['instance_id'] ?? 0);
                    $varId = (int)($s['var_id'] ?? 0);

                    if ($varId <= 0 || !@IPS_VariableExists($varId)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            '⚠️ Skipping select row: invalid/missing var_id', 0);
                        continue;
                    }

                    // Popup cache may only store {register,var_id}. Derive instance_id from variable parent if missing.
                    if ($iid <= 0) {
                        $iid = (int)@IPS_GetParent($varId);
                    }

                    if ($iid <= 0 || !@IPS_InstanceExists($iid)) {
                        $parentId = (int)@IPS_GetParent($varId);
                        if ($parentId > 0) {
                            $iid = (int)@IPS_GetParent($parentId);
                        }
                    }

                    if ($iid <= 0 || !@IPS_InstanceExists($iid)) {
                        $this->Debug(__FUNCTION__, self::LV_WARN, self::TOPIC_DISCOVERY,
                            "⚠️ Skipping select varId=$varId: could not resolve valid instance_id (instance=$iid)", 0);
                        continue;
                    }

                    $key = $iid . ':' . (int)$varId;
                    if (isset($existingKeys[$key])) {
                        continue;
                    }

                    $instName = trim((string)@IPS_GetName($iid));
                    $varName = trim((string)@IPS_GetName($varId));
                    $shortName = $instName;
                    if ($shortName !== '' && $varName !== '') {
                        $shortName .= ' – ' . $varName;
                    } elseif ($varName !== '') {
                        $shortName = $varName;
                    } elseif ($shortName === '') {
                        $shortName = 'Select';
                    }

                    $existingSelects[] = [
                        'name' => $shortName,
                        'instance_id' => $iid,
                        'var_id' => (int)$varId
                    ];

                    $existingKeys[$key] = true;
                    $addedSelects++;
                }

                $this->Debug(__FUNCTION__, self::LV_INFO, self::TOPIC_DISCOVERY,
                    '➕ Selects added to mapping: ' . $addedSelects, 0);

                $this->UpdateFormField('select_mapping', 'values', json_encode($existingSelects, JSON_UNESCAPED_SLASHES));
            }
        } catch (Throwable $e) {
            $this->Debug(__FUNCTION__, self::LV_ERROR, self::TOPIC_DISCOVERY,
                '💥 ApplySuggestedDevices crashed: ' . $e->getMessage(), 0);
            $this->Debug(__FUNCTION__, self::LV_TRACE, self::TOPIC_DISCOVERY,
                $e->getTraceAsString(), 0);
        }
    }

    /**
     * Checks whether a sensor registry definition matches a given instance.
     * Uses match.required_child_idents if present.
     * A definition matches if at least one required ident exists in the instance.
     *
     * @param array $deviceDef
     * @param int $instanceID
     * @return bool
     */
    private function DoesSensorDefinitionMatchInstance(array $deviceDef, int $instanceID): bool
    {
        if ($instanceID <= 0 || !@IPS_InstanceExists($instanceID)) {
            return false;
        }

        $match = $deviceDef['match'] ?? null;
        if (!is_array($match)) {
            // No match restrictions defined → accept definition
            return true;
        }

        $required = $match['required_child_idents'] ?? [];
        if (!is_array($required) || empty($required)) {
            // No required idents defined → accept definition
            return true;
        }

        foreach ($required as $ident) {
            $ident = trim((string)$ident);
            if ($ident === '') {
                continue;
            }

            $varId = @IPS_GetObjectIDByIdent($ident, $instanceID);
            if ($varId && @IPS_VariableExists($varId)) {
                // At least one required ident exists → definition matches
                return true;
            }
        }

        // None of the required idents found → definition does not match
        return false;
    }

    /**
     * Try to infer a unit from a variable profile (Suffix/Prefix).
     * Returns empty string if none found.
     */
    private function GuessUnitForVariable(int $varId): string
    {
        if ($varId <= 0 || !@IPS_VariableExists($varId)) {
            return '';
        }

        $v = @IPS_GetVariable($varId);
        if (!is_array($v)) {
            return '';
        }

        // Prefer custom profile if present
        $profile = trim((string)($v['VariableCustomProfile'] ?? ''));
        if ($profile === '') {
            $profile = trim((string)($v['VariableProfile'] ?? ''));
        }
        if ($profile === '') {
            return '';
        }

        $p = null;
        try {
            $p = @IPS_GetVariableProfile($profile);
        } catch (Throwable $e) {
            $p = null;
        }

        if (!is_array($p)) {
            return '';
        }

        $suffix = trim((string)($p['Suffix'] ?? ''));
        $prefix = trim((string)($p['Prefix'] ?? ''));

        return $suffix !== '' ? $suffix : ($prefix !== '' ? $prefix : '');
    }

    private function NormalizeRemoteSensorType(string $type): string
    {
        $type = strtolower(trim($type));
        $allowed = ['temperature', 'humidity', 'illuminance', 'voltage', 'generic'];
        return in_array($type, $allowed, true) ? $type : 'generic';
    }

    private function DeriveRemoteSensorTypeForVariable(int $varId, string $unit = ''): string
    {
        $unit = trim($unit);

        // Prefer profile name heuristics (robust across different suffixes)
        $profile = '';
        $v = @IPS_GetVariable($varId);
        if (is_array($v)) {
            $profile = trim((string)($v['VariableCustomProfile'] ?? ''));
            if ($profile === '') {
                $profile = trim((string)($v['VariableProfile'] ?? ''));
            }
        }
        $p = strtolower($profile);

        $type = 'generic';

        if ($p !== '') {
            if (strpos($p, 'temperature') !== false || strpos($p, 'temp') !== false || strpos($p, 'celsius') !== false) {
                $type = 'temperature';
            } elseif (strpos($p, 'humidity') !== false || strpos($p, 'feuchtigkeit') !== false) {
                $type = 'humidity';
            } elseif (
                strpos($p, 'illumin') !== false ||
                strpos($p, 'lux') !== false ||
                strpos($p, 'beleucht') !== false ||
                strpos($p, 'intensity') !== false ||
                strpos($p, 'brightness') !== false ||
                strpos($p, 'helligkeit') !== false ||
                strpos($p, 'dimmer') !== false ||
                strpos($p, 'level') !== false
            ) {
                $type = 'illuminance';
            } elseif (strpos($p, 'voltage') !== false || strpos($p, 'volt') !== false) {
                $type = 'voltage';
            }
        }

        // If still generic, try unit heuristics (strict)
        if ($type === 'generic' && $unit !== '') {
            $u = strtolower($unit);
            if ($u === '°c' || $u === 'c' || strpos($u, 'celsius') !== false) {
                $type = 'temperature';
            } elseif (strpos($u, 'rh') !== false || strpos($u, '%rh') !== false) {
                $type = 'humidity';
            } elseif ($u === 'lx' || strpos($u, 'lux') !== false) {
                $type = 'illuminance';
            } elseif ($u === 'v' || strpos($u, 'volt') !== false) {
                $type = 'voltage';
            }
        }

        return $this->NormalizeRemoteSensorType($type);
    }

    /**
     * Returns a readable path for an object id.
     * Uses IPS_GetLocation if available.
     */
    private function GetObjectPath(int $objectId): string
    {
        $loc = '';
        try {
            $loc = (string)@IPS_GetLocation($objectId);
        } catch (Throwable $e) {
            $loc = '';
        }

        $loc = trim($loc);
        // IPS_GetLocation often ends with a backslash; normalize
        $loc = rtrim($loc, "\\ ");

        // Normalize separators for display
        $loc = str_replace('\\', ' → ', $loc);

        return trim($loc);
    }

    // -----------------------------
    // Expert Debug / Debug Filtering
    // -----------------------------

    private function ParseCsvList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn($v) => $v !== '');
        return array_values(array_unique($parts));
    }

    private function GetMappedVarIdsForInstance(int $instanceID): array
    {
        $varIds = [];

        // Switch mapping
        $map = json_decode($this->ReadPropertyString('switch_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID && !empty($e['var_id'])) {
                    $varIds[] = (int)$e['var_id'];
                }
            }
        }

        // Sensor mapping
        $map = json_decode($this->ReadPropertyString('sensor_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID && !empty($e['var_id'])) {
                    $varIds[] = (int)$e['var_id'];
                }
            }
        }

        // IR mapping
        $map = json_decode($this->ReadPropertyString('ir_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID && !empty($e['var_id'])) {
                    $varIds[] = (int)$e['var_id'];
                }
            }
        }

        // Remote mapping
        $map = json_decode($this->ReadPropertyString('remote_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID && !empty($e['var_id'])) {
                    $varIds[] = (int)$e['var_id'];
                }
            }
        }

        // Cover mapping
        $map = json_decode($this->ReadPropertyString('cover_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID) {
                    if (!empty($e['position_var_id'])) $varIds[] = (int)$e['position_var_id'];
                    if (!empty($e['control_var_id'])) $varIds[] = (int)$e['control_var_id'];
                }
            }
        }

        // Climate mapping
        $map = json_decode($this->ReadPropertyString('climate_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID) {
                    foreach (['status_var_id', 'current_temp_var_id', 'target_temp_var_id', 'mode_var_id'] as $k) {
                        if (!empty($e[$k])) $varIds[] = (int)$e[$k];
                    }
                }
            }
        }

        // Light mapping
        $map = json_decode($this->ReadPropertyString('light_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) === $instanceID) {
                    foreach (['switch_var_id', 'brightness_var_id', 'color_var_id', 'color_temp_var_id'] as $k) {
                        if (!empty($e[$k])) $varIds[] = (int)$e[$k];
                    }
                }
            }
        }

        // Media player mapping (features list)
        $map = json_decode($this->ReadPropertyString('media_player_mapping'), true);
        if (is_array($map)) {
            foreach ($map as $e) {
                if ((int)($e['instance_id'] ?? 0) !== $instanceID) continue;
                $features = $e['features'] ?? null;
                if (!is_array($features)) continue;
                foreach ($features as $f) {
                    if (!empty($f['var_id'])) $varIds[] = (int)$f['var_id'];
                }
            }
        }

        // Cleanup
        $varIds = array_filter(array_unique($varIds), fn($v) => $v > 0 && IPS_VariableExists($v));
        return array_values($varIds);
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
    protected function FormHead()
    {
        $this->EnsureTokenInitialized();
        $token = $this->ReadAttributeString('token');

        $form = [
            [
                'type' => 'Image',
                'image' => $this->LoadImageAsBase64()
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'token',
                'caption' => '🔑 Token',
                'value' => $token,
                'enabled' => true
            ],
            [
                'type' => 'PopupButton',
                'name' => 'device_popup',
                'caption' => '🔍 Search for Devices',
                'onClick' => 'UCR_LoadDeviceSearchSuggestions($id);',
                'popup' => [
                    'caption' => '🔍 Device Search',
                    'items' => [
                        [
                            'type' => 'List',
                            'name' => 'popup_button_suggestions',
                            'caption' => '🔘 Button (Script)',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '125px', 'add' => false, 'edit' => ['type' => 'CheckBox'], 'save' => true],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => 'auto', 'save' => true],
                                ['caption' => 'Name', 'name' => 'name', 'width' => '300px', 'add' => '', 'edit' => ['type' => 'ValidationTextBox'], 'save' => true],
                                ['caption' => 'Script ID', 'name' => 'script_id', 'width' => '100px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_button_suggestions", (string)$popup_button_suggestions["register"], "script_id", (string)$popup_button_suggestions["script_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_climate_suggestions',
                            'caption' => '🔥 Climate (Thermostat)',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_climate_suggestions", (string)$popup_climate_suggestions["register"], "instance_id", (string)$popup_climate_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_cover_suggestions',
                            'caption' => '🪟 Cover (Roller Blind)',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_cover_suggestions", (string)$popup_cover_suggestions["register"], "instance_id", (string)$popup_cover_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_light_suggestions',
                            'caption' => '💡 Light (Switch)',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '125px', 'add' => false, 'edit' => ['type' => 'CheckBox'], 'save' => true],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => 'auto', 'save' => true],
                                ['caption' => 'Name', 'name' => 'name', 'width' => '300px', 'add' => '', 'edit' => ['type' => 'ValidationTextBox'], 'save' => true],
                                ['caption' => 'Registry', 'name' => 'registry_name', 'width' => '100px', 'visible' => false, 'save' => true],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_light_suggestions", (string)$popup_light_suggestions["register"], "instance_id", (string)$popup_light_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_media_suggestions',
                            'caption' => '🎵 Media Player',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_media_suggestions", (string)$popup_media_suggestions["register"], "instance_id", (string)$popup_media_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_remote_suggestions',
                            'caption' => '🎮 Remote Device',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_remote_suggestions", (string)$popup_remote_suggestions["register"], "instance_id", (string)$popup_remote_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_sensor_suggestions',
                            'caption' => '📈 Sensor',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox'], 'save' => true],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px', 'save' => true],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox'], 'save' => true],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                                ['caption' => 'Var ID', 'name' => 'var_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupSensorSelection($id, (string)$popup_sensor_suggestions["register"], (string)$popup_sensor_suggestions["instance_id"], (string)$popup_sensor_suggestions["var_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_switch_suggestions',
                            'caption' => '💡 Switch (Binary)',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupList($id, "popup_switch_suggestions", (string)$popup_switch_suggestions["register"], "instance_id", (string)$popup_switch_suggestions["instance_id"]);'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'popup_select_suggestions',
                            'caption' => '🔽 Select',
                            'columns' => [
                                ['caption' => 'Register', 'name' => 'register', 'width' => '140px', 'add' => false, 'edit' => ['type' => 'CheckBox']],
                                ['caption' => '📦 Object', 'name' => 'label', 'width' => '200px'],
                                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                                ['caption' => 'Instance ID', 'name' => 'instance_id', 'width' => '10px', 'visible' => false, 'save' => true],
                                ['caption' => 'Var ID', 'name' => 'var_id', 'width' => '10px', 'visible' => false, 'save' => true],
                            ],
                            'add' => false,
                            'delete' => false,
                            'rowCount' => 8,
                            'onEdit' => 'UCR_StorePopupSensorSelection($id, (string)$popup_select_suggestions["register"], (string)$popup_select_suggestions["instance_id"], (string)$popup_select_suggestions["var_id"]);'
                        ]
                    ],
                    'buttons' => [
                        [
                            'type' => 'Button',
                            'caption' => '➕ Add Devices',
                            'onClick' => 'UCR_ApplySuggestedDevices($id);'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🟢 Button Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'button_mapping',
                        'caption' => 'Button Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '400px',
                                'add' => 'Button',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Script',
                                'name' => 'script_id',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectScript'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '💡 Switch Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'switch_mapping',
                        'caption' => 'Switch Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '400px',
                                'add' => 'Switch',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => 'auto',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'var_id',
                                'width' => '800px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable',
                                    'filters' => [
                                        [
                                            'caption' => 'Nur boolsche Variablen',
                                            'expression' => 'is_bool($Variable["VariableType"])'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🔥 Climate Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'climate_mapping',
                        'caption' => 'Climate Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => 'auto',
                                'add' => 'Climate',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Status Variable',
                                'name' => 'status_var_id',
                                'width' => '400px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Current Temperature Variable',
                                'name' => 'current_temp_var_id',
                                'width' => '400px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Target Temperature Variable',
                                'name' => 'target_temp_var_id',
                                'width' => '400px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Mode Variable',
                                'name' => 'mode_var_id',
                                'width' => '400px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🪟 Cover Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'cover_mapping',
                        'caption' => 'Cover Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '400px',
                                'add' => 'Cover',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Position Variable',
                                'name' => 'position_var_id',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Control Variable',
                                'name' => 'control_var_id',
                                'width' => '650px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '📡 IR Emitter Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'ir_mapping',
                        'caption' => 'IR Emitter Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '200px',
                                'add' => 'IR Emitter',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'var_id',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '💡 Light Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'light_mapping',
                        'caption' => 'Light Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => 'auto',
                                'add' => 'Light',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Switch Variable',
                                'name' => 'switch_var_id',
                                'width' => '250px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Brightness Variable',
                                'name' => 'brightness_var_id',
                                'width' => '250px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Color Variable',
                                'name' => 'color_var_id',
                                'width' => '250px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ],
                            [
                                'caption' => 'Color Temperature Variable',
                                'name' => 'color_temp_var_id',
                                'width' => '250px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            // Media Player
            [
                'type' => 'ExpansionPanel',
                'caption' => '🎵 Media Player Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'media_player_mapping',
                        'caption' => 'Media Player Mapping',
                        'add' => true,
                        'delete' => true,
                        'rowCount' => 5,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '300px',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ],
                                'add' => 'New Media Player',
                                'save' => true
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ],
                                'save' => true
                            ],
                            [
                                'caption' => 'Device Class',
                                'name' => 'device_class',
                                'width' => 'auto',
                                'edit' => [
                                    'type' => 'Select',
                                    'options' => [
                                        ['caption' => 'receiver', 'value' => 'receiver'],
                                        ['caption' => 'set_top_box', 'value' => 'set_top_box'],
                                        ['caption' => 'speaker', 'value' => 'speaker'],
                                        ['caption' => 'streaming_box', 'value' => 'streaming_box'],
                                        ['caption' => 'tv', 'value' => 'tv']
                                    ]
                                ],
                                'add' => 'speaker',
                                'save' => true
                            ],
                            [
                                'caption' => 'Features',
                                'name' => 'features',
                                'width' => '300px',
                                'edit' => [
                                    'type' => 'List',
                                    'rowCount' => 10,
                                    'columns' => [
                                        [
                                            'caption' => 'Name',
                                            'name' => 'feature_name',
                                            'width' => '300px',
                                            'save' => true
                                        ],
                                        [
                                            'caption' => 'Attribute',
                                            'name' => 'attribute_key',
                                            'width' => '300px',
                                            'save' => true
                                        ],
                                        [
                                            'caption' => 'Feature Key',
                                            'name' => 'feature_key',
                                            'width' => '10px',
                                            'save' => true,
                                            'visible' => false
                                        ],
                                        [
                                            'caption' => 'Description',
                                            'name' => 'description',
                                            'width' => 'auto',
                                            'save' => false
                                        ],
                                        [
                                            'caption' => 'Variable',
                                            'name' => 'var_id',
                                            'width' => '350px',
                                            'edit' => [
                                                'type' => 'SelectVariable'
                                            ],
                                            'add' => 0,
                                            'save' => true
                                        ]
                                    ],
                                    'values' => [
                                        ['feature_name' => 'State', 'attribute_key' => Entity_Media_Player::ATTR_STATE, 'feature_key' => Entity_Media_Player::FEATURE_ON_OFF, 'description' => 'State of the media player'],
                                        ['feature_name' => 'Volume', 'attribute_key' => Entity_Media_Player::ATTR_VOLUME, 'feature_key' => Entity_Media_Player::FEATURE_VOLUME, 'description' => 'Current volume level (0–100)'],
                                        ['feature_name' => 'Muted', 'attribute_key' => Entity_Media_Player::ATTR_MUTED, 'feature_key' => Entity_Media_Player::FEATURE_MUTE, 'description' => 'Mute status of the player'],
                                        ['feature_name' => 'Navigation Control', 'attribute_key' => 'symcon_control', 'feature_key' => 'symcon_control', 'description' => 'Playback Control (Play/Pause/ Stop)'],
                                        ['feature_name' => 'Repeat', 'attribute_key' => Entity_Media_Player::ATTR_REPEAT, 'feature_key' => Entity_Media_Player::FEATURE_REPEAT, 'description' => 'Repeat mode: OFF, ALL, ONE'],
                                        ['feature_name' => 'Shuffle', 'attribute_key' => Entity_Media_Player::ATTR_SHUFFLE, 'feature_key' => Entity_Media_Player::FEATURE_SHUFFLE, 'description' => 'Shuffle mode: on/off'],
                                        ['feature_name' => 'Duration', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_DURATION, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_DURATION, 'description' => 'Duration of the current media (in seconds)'],
                                        ['feature_name' => 'Position', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_POSITION, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_POSITION, 'description' => 'Playback position (in seconds)'],
                                        ['feature_name' => 'Title', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_TITLE, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_TITLE, 'description' => 'Title of the current media'],
                                        ['feature_name' => 'Artist', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_ARTIST, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_ARTIST, 'description' => 'Artist of the current media'],
                                        ['feature_name' => 'Album', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_ALBUM, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_ALBUM, 'description' => 'Album of the current media'],
                                        ['feature_name' => 'Image', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_IMAGE_URL, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_IMAGE_URL, 'description' => 'URL of image representing the media'],
                                        ['feature_name' => 'Type', 'attribute_key' => Entity_Media_Player::ATTR_MEDIA_TYPE, 'feature_key' => Entity_Media_Player::FEATURE_MEDIA_TYPE, 'description' => 'Type of media being played'],
                                        ['feature_name' => 'Direction Pad', 'attribute_key' => 'symcon_dpad', 'feature_key' => Entity_Media_Player::FEATURE_DPAD, 'description' => 'Directional pad navigation, provides up / down / left / right / enter commands.'],
                                        ['feature_name' => 'Number Pad', 'attribute_key' => 'symcon_numpad', 'feature_key' => Entity_Media_Player::FEATURE_NUMPAD, 'description' => 'Number pad, provides digit_0, .. , digit_9 commands'],
                                        ['feature_name' => 'Commands', 'attribute_key' => 'symcon_commands', 'feature_key' => Entity_Media_Player::FEATURE_HOME, 'description' => 'Commands like Home, Menu, Guide, Info; color ButonsList of available input/media sources'],
                                        ['feature_name' => 'Channel', 'attribute_key' => 'symcon_channel', 'feature_key' => Entity_Media_Player::FEATURE_CHANNEL_SWITCHER, 'description' => 'Channels'],
                                        ['feature_name' => 'Source', 'attribute_key' => Entity_Media_Player::ATTR_SOURCE, 'feature_key' => Entity_Media_Player::FEATURE_SELECT_SOURCE, 'description' => 'Current input or media source'],
                                        ['feature_name' => 'Sound Mode', 'attribute_key' => Entity_Media_Player::ATTR_SOUND_MODE, 'feature_key' => Entity_Media_Player::FEATURE_SELECT_SOUND_MODE, 'description' => 'Current sound mode']
                                    ]
                                ],
                                'add' => []
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🔽 Select Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'select_mapping',
                        'caption' => 'Select Mapping',
                        'add' => true,
                        'delete' => true,
                        'rowCount' => 5,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '300px',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ],
                                'add' => 'New Select',
                                'save' => true
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ],
                                'save' => true
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'var_id',
                                'width' => 'auto',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ],
                                'save' => true
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🎮 Remote Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'remote_mapping',
                        'caption' => 'Remote Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '200px',
                                'add' => 'Remote',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'var_id',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '📈 Sensor Assignment',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'sensor_mapping',
                        'caption' => 'Sensor Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name' => 'name',
                                'width' => '400px',
                                'add' => 'Sensor',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                            [
                                'caption' => 'Instance ID',
                                'name' => 'instance_id',
                                'width' => '400px',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'var_id',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'SelectVariable',
                                    'onChange' => 'UCR_AutoDetectSensorType($id, $var_id);'
                                ]
                            ],
                            [
                                'caption' => 'Sensor Type',
                                'name' => 'sensor_type',
                                'width' => '200px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'Select',
                                    'options' => [
                                        ['caption' => 'Bitte auswählen...', 'value' => ''],
                                        ['caption' => 'temperature', 'value' => 'temperature'],
                                        ['caption' => 'humidity', 'value' => 'humidity'],
                                        ['caption' => 'illuminance', 'value' => 'illuminance'],
                                        ['caption' => 'voltage', 'value' => 'voltage'],
                                        ['caption' => 'generic', 'value' => 'generic']
                                    ],
                                    'visible' => false
                                ]
                            ],
                            [
                                'caption' => 'Unit',
                                'name' => 'unit',
                                'width' => '200px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'ValidationTextBox'
                                ]
                            ],
                        ]
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🧾 Client Session Log',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'session_log',
                        'caption' => 'Clients',
                        'rowCount' => 6,
                        'add' => false,
                        'delete' => false,
                        'columns' => [
                            ['caption' => 'Remote Name', 'name' => 'name', 'width' => '150px'],
                            ['caption' => 'Version', 'name' => 'version', 'width' => '100px'],
                            ['caption' => 'API Version', 'name' => 'api_version', 'width' => '100px'],
                            ['caption' => 'Model', 'name' => 'model', 'width' => '150px'],
                            ['caption' => 'IP Address', 'name' => 'ip', 'width' => '150px'],
                            ['caption' => 'Port', 'name' => 'port', 'width' => '80px'],
                            ['caption' => 'Authenticated', 'name' => 'authenticated', 'width' => '120px'],
                            ['caption' => 'Last Seen', 'name' => 'last_seen', 'width' => 'auto']
                        ],
                        'values' => $this->FormatSessionList()
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => '🔐 IP Whitelist (temporary access)',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'ip_whitelist',
                        'caption' => 'Allowed IP Addresses',
                        'rowCount' => 3,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'IP Address',
                                'name' => 'ip',
                                'width' => '300px',
                                'add' => '',
                                'edit' => [
                                    'type' => 'Select',
                                    'options' => $this->GetKnownClientIPOptions()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            // EXPANSION PANEL: Expert Settings
            [
                'type' => 'ExpansionPanel',
                'caption' => '⚙️ Expert Settings',
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'This driver communicates via TCP port 9988.'
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'If Symcon is running inside a Docker container, this port must be mapped externally.'
                    ],
                    [
                        'type' => 'CheckBox',
                        'name' => 'extended_debug',
                        'caption' => 'Enable extended debug output'
                    ],
                    [
                        'type' => 'CheckBox',
                        'name' => 'use_manual_host',
                        'caption' => 'Manuelle IP-Adresse nutzen (Übergangslösung bei IPv6/cURL)'
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'host',
                        'caption' => 'Host (manuelle IP-Adresse)',
                        'width' => '90%',
                        'enabled' => $this->ReadPropertyBoolean('use_manual_host'),
                        'visible' => true
                    ],
                    [
                        'type' => 'Button',
                        'caption' => '🔧 Manually register driver with Remote 3',
                        'onClick' => 'UCR_RegisterDriverManually($id);'
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'callback_IP',
                        'caption' => 'Callback IP (IP of Symcon Server, only needed if automatic DNS name is not working)',
                        'width' => '90%'
                    ],
                    [
                        'type' => 'Button',
                        'caption' => '🧪 Debug: Dump client_sessions',
                        'onClick' => 'UCR_DumpClientSessions($id);'
                    ]
                ]
            ],
            [
                'type' => 'CheckBox',
                'name' => 'expert_debug',
                'caption' => '🧪 Expert Debug'
            ]
        ];

        // Show debug settings only when enabled
        if ($this->ReadPropertyBoolean('expert_debug')) {
            $form[] = [
                'type' => 'ExpansionPanel',
                'caption' => '🪲 Debugging',
                'items' => [
                    [
                        'type' => 'Label',
                        'caption' => 'Use filters to reduce debug output to specific entities/IDs/IPs. Example topics: WS, HOOK, ENTITY, VM, AUTH.'
                    ],
                    [
                        'type' => 'Select',
                        'name' => 'debug_level',
                        'caption' => 'Minimum debug level',
                        'options' => [
                            ['caption' => 'BASIC', 'value' => self::LV_BASIC],
                            ['caption' => 'ERROR', 'value' => self::LV_ERROR],
                            ['caption' => 'WARN', 'value' => self::LV_WARN],
                            ['caption' => 'INFO', 'value' => self::LV_INFO],
                            ['caption' => 'TRACE', 'value' => self::LV_TRACE],
                        ]
                    ],
                    [
                        'type' => 'CheckBox',
                        'name' => 'debug_filter_enabled',
                        'caption' => 'Enable filters'
                    ],
                    // Available topics: GEN, AUTH, HOOK, WS, ENTITY, VM, DISCOVERY, API, FORM, EXT
                    [
                        'type' => 'List',
                        'name' => 'debug_topics_cfg',
                        'caption' => 'Topics',
                        'rowCount' => 10,
                        'add' => false,
                        'delete' => false,
                        'columns' => [
                            [
                                'caption' => 'Show',
                                'name' => 'enabled',
                                'width' => '80px',
                                'add' => true,
                                'edit' => ['type' => 'CheckBox']
                            ],
                            [
                                'caption' => 'Topic',
                                'name' => 'topic',
                                'width' => '120px',
                                'add' => '',
                                'edit' => ['type' => 'Label']
                            ],
                            [
                                'caption' => 'Description',
                                'name' => 'description',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => ['type' => 'Label']
                            ]
                        ],
                        'values' => $this->BuildDebugTopicsConfig()
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'Filter by device/object (Symcon): select an instance to reduce debug output for its mapped variables.'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'debug_filter_instances',
                        'caption' => 'Devices / Instances',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Instance',
                                'name' => 'instance_id',
                                'width' => 'auto',
                                'add' => 0,
                                'edit' => [
                                    'type' => 'SelectInstance'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'debug_var_ids',
                        'caption' => 'Var/Object IDs (CSV)'
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'Client IP filter: select one or more Remote client IPs to reduce debug output.'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'debug_client_ips_cfg',
                        'caption' => 'Client IPs',
                        'rowCount' => 5,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'caption' => 'Client IP',
                                'name' => 'ip',
                                'width' => 'auto',
                                'add' => '',
                                'edit' => [
                                    'type' => 'Select',
                                    'options' => $this->GetKnownClientIPOptions()
                                ]
                            ]
                        ],
                        'values' => $this->BuildDebugClientIPsConfig()
                    ],
                    [
                        'type' => 'ValidationTextBox',
                        'name' => 'debug_text_filter',
                        'caption' => 'Text filter (substring or regex)'
                    ],
                    [
                        'type' => 'CheckBox',
                        'name' => 'debug_text_is_regex',
                        'caption' => 'Text filter is regex'
                    ],
                    [
                        'type' => 'CheckBox',
                        'name' => 'debug_strict_match',
                        'caption' => 'Log matches only (strict)'
                    ],
                    [
                        'type' => 'NumberSpinner',
                        'name' => 'debug_throttle_ms',
                        'caption' => 'Throttle (ms, 0=off)',
                        'minimum' => 0,
                        'maximum' => 60000
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
    protected function FormActions()
    {
        $form = [
            [
                'type' => 'Button',
                'caption' => '🔄 Generate new token',
                'onClick' => 'UCR_GenerateToken($id);'
            ]
        ];
        return $form;
    }

    /**
     * return from status
     *
     * @return array
     */
    protected function FormStatus()
    {
        $form = [
            [
                'code' => IS_CREATING,
                'icon' => 'inactive',
                'caption' => '🛠 Creating instance'],
            [
                'code' => IS_ACTIVE,
                'icon' => 'active',
                'caption' => '✅ Remote 3 Integration Driver created'],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => '🔌 Interface closed']];

        return $form;
    }
}
