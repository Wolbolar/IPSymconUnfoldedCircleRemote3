<?php

declare(strict_types=1);

class Remote3IRDockDevice extends IPSModuleStrict
{
    public function GetCompatibleParents(): string
    {
        // Require the WebSocket Client as parent
        return json_encode([
            'type' => 'connect',
            'moduleIDs' => [
                '{9CD1AA03-841E-FB97-8E32-6536A1D4561B}'
            ]
        ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();


        // Wartet, bis der Kernel gestartet ist
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        $this->RegisterPropertyString("name", "");
        $this->RegisterAttributeString("baseURL", "");
        $this->RegisterAttributeString("lastExportFile", "");
        $this->RegisterAttributeString("lastExportUrl", "");
        $this->RegisterAttributeInteger('lastIrSendReqId', -1);
        $this->RegisterPropertyInteger("frequency", 38000);
        $this->RegisterPropertyString("codeFormat", "PRONTO");
        $this->RegisterPropertyString("commands", "[]");
        $this->RegisterPropertyInteger("scriptCategory", 0); // Zielkategorie für automatisch erzeugte Skripte
        $this->RegisterPropertyString("varSettings", "[]");   // Auswahl, welche Variablen erzeugt werden
        $this->RegisterPropertyString("importFile", "");      // Pfad zur Import-Datei
        $this->RegisterPropertyString("importCsvFile", "");      // Pfad zur Import-CSV (Remote 3 Export)
        $this->RegisterPropertyBoolean("exportIncludeMeta", true); // Export enthält Meta/Status
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

        try {
            // If we have no parent connection yet, remain inactive (module not ready)
            $parentID = (int)IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if ($parentID <= 0) {
                $this->SendDebug(__FUNCTION__, '⏸️ No parent connection. Setting status to INACTIVE.', 0);
                $this->SetStatus(IS_INACTIVE);
                return;
            }

            // Mark active early to avoid being stuck in "creating"
            $this->SetStatus(IS_ACTIVE);

            $this->NormalizeVarSettings();
            $this->SendDebug('ApplyChanges', 'After NormalizeVarSettings varSettings(raw)=' . $this->ReadPropertyString('varSettings'), 0);

            $this->EnsureCommandProfileAndVariable();
            $this->UpdateFormVisibility();

        } catch (Throwable $e) {
            $this->SendDebug(__FUNCTION__, '❌ ApplyChanges exception: ' . $e->getMessage(), 0);
            $this->SetStatus(IS_EBASE);
        }
    }


    /**
     * Sends a command payload to the parent splitter (Kincony Hub) via SendDataToParent.
     * The parent will handle the HTTP call to the extender.
     *
     * @return array Decoded JSON response from parent (if any)
     */
    private function ForwardCommandToParent(array $payload): array
    {
        $envelope = [
            'DataID' => '{F975667E-3B5A-0148-4A47-CB4CD513EAD8}',
            'Buffer' => json_encode($payload, JSON_UNESCAPED_SLASHES)
        ];

        $json = json_encode($envelope, JSON_UNESCAPED_SLASHES);
        $this->SendDebug('ForwardCommandToParent', $json, 0);

        $resp = $this->SendDataToParent($json);
        if ($resp === false || $resp === null || $resp === '') {
            $this->SendDebug('ForwardCommandToParent', 'No response from parent (SendDataToParent returned empty/false)', 0);
            return [];
        }

        $this->SendDebug('ForwardCommandToParent', 'Parent raw response: ' . (string)$resp, 0);

        $decoded = json_decode((string)$resp, true);
        return is_array($decoded) ? $decoded : ['_raw' => (string)$resp];
    }

    /**
     * Ask the parent splitter to build a full download URL (including hook secret and file name).
     * The parent must implement the corresponding request handler.
     *
     * Expected parent response (example):
     *  {"url":"http://<ip>:3777/hook/haptique_kinconyhub/<iid>/download?file=...&token=..."}
     */
    private function RequestDownloadUrlFromParent(string $fileName): string
    {
        $fileName = trim($fileName);
        if ($fileName === '') {
            return '';
        }

        $payload = [
            'type' => 'getDownloadUrl',
            'deviceInstanceId' => $this->InstanceID,
            'fileName' => $fileName
        ];

        $resp = $this->ForwardCommandToParent($payload);
        $this->SendDebug('RequestDownloadUrlFromParent', json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);

        if (is_array($resp)) {
            if (isset($resp['url']) && is_string($resp['url'])) {
                return trim($resp['url']);
            }
            // optional: nested reply
            if (isset($resp['data']['url']) && is_string($resp['data']['url'])) {
                return trim($resp['data']['url']);
            }
        }

        return '';
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        switch ($Message) {
            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->SendDebug("MessageSink", "🔄 Kernel Ready", 0);

                }
                break;

            case IPS_KERNELSTARTED:
                $this->SendDebug("MessageSink", "🔄 Kernel Started", 0);

                break;

            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->SendDebug("MessageSink", "🔄 Instanz aktiv", 0);

                }
                break;
        }
    }

    public function ReceiveData($JSONString): string
    {
        $data = json_decode($JSONString, true);

        if (!is_array($data)) {
            $this->SendDebug(__FUNCTION__, '❌ Invalid JSON data received: ' . $JSONString, 0);
            return '';
        }

        // Decode IPS envelope: { DataID, Buffer }
        $bufferRaw = $data['Buffer'] ?? '';
        $payload = null;
        if (is_string($bufferRaw) && $bufferRaw !== '') {
            $payload = json_decode($bufferRaw, true);
        }

        // Keep full payload for diagnostics (only if decoding failed)
        if (!is_array($payload)) {
            $this->SendDebug(__FUNCTION__, $JSONString, 0);
            return '';
        }

        // Handle Dock IR send response
        if (($payload['type'] ?? '') === 'dock' && ($payload['msg'] ?? '') === 'ir_send') {
            $reqId = isset($payload['req_id']) ? (int)$payload['req_id'] : -1;
            $code = isset($payload['code']) ? (int)$payload['code'] : -1;

            // Suppress duplicate notifications for same req_id
            $lastReqId = (int)$this->ReadAttributeInteger('lastIrSendReqId');
            if ($reqId >= 0 && $reqId === $lastReqId) {
                // still keep minimal trace
                $this->SendDebug(__FUNCTION__, '↩️ Duplicate dock ir_send response ignored (req_id=' . $reqId . ', code=' . $code . ')', 0);
                return '';
            }
            if ($reqId >= 0) {
                $this->WriteAttributeInteger('lastIrSendReqId', $reqId);
            }

            $ok = ($code >= 200 && $code < 300);
            $human = $ok ? '✅ queued/accepted by Dock' : '❌ Dock error';

            $this->SendDebug(__FUNCTION__, sprintf('📡 Dock IR send result: %s (code=%d, req_id=%d)', $human, $code, $reqId), 0);
            return '';
        }

        // Default: log compact
        $this->SendDebug(__FUNCTION__, '↩️ Event: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Command':
                $this->SendCommandByIndex((int)$Value);
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    private function EnsureCommandProfileAndVariable(): void
    {
        $profile = 'HKH.Commands.' . $this->InstanceID;

        // Debug: verify translations and current varSettings
        $this->SendDebug('EnsureCommandProfileAndVariable', 'varSettings(raw)=' . $this->ReadPropertyString('varSettings'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Command)=' . $this->Translate('Command'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Last Command (Name))=' . $this->Translate('Last Command (Name)'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Last Command (Alias))=' . $this->Translate('Last Command (Alias)'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Last Command (Index))=' . $this->Translate('Last Command (Index)'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Last Sent (Time))=' . $this->Translate('Last Sent (Time)'), 0);
        $this->SendDebug('EnsureCommandProfileAndVariable', 'T(Current State (Alias))=' . $this->Translate('Current State (Alias)'), 0);
        $captionMap = $this->GetVarCaptionMap();
        $this->SendDebug('EnsureCommandProfileAndVariable', 'captionMap=' . json_encode($captionMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);

        if (IPS_VariableProfileExists($profile)) {
            IPS_DeleteVariableProfile($profile);
        }

        IPS_CreateVariableProfile($profile, 1); // integer
        IPS_SetVariableProfileText($profile, '', '');

        $rows = json_decode($this->ReadPropertyString('commands'), true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $i = 0;
        foreach ($rows as $row) {
            $name = '';
            $alias = '';
            if (is_array($row)) {
                if (isset($row['CommandName'])) {
                    $name = (string)$row['CommandName'];
                }
                if (isset($row['CommandAlias'])) {
                    $alias = trim((string)$row['CommandAlias']);
                }
            }
            if ($name === '') {
                $name = 'Cmd ' . $i;
            }

            // Prefer alias in dropdown if present
            $label = $alias !== '' ? ($alias . ' (' . $name . ')') : $name;
            IPS_SetVariableProfileAssociation($profile, $i, $label, '', 0);
            $i++;
        }

        // Variable selection
        $enabled = $this->GetVarEnabledMap();

        // Command selector (action)
        if (($enabled['Command'] ?? true) === true) {
            $caption = $captionMap['Command'] ?? $this->Translate('Command');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: Command => ' . $caption, 0);
            $this->RegisterVariableInteger('Command', $caption, $profile, 1);
            $this->EnsureVariableCaption('Command', $caption);
            $vid = @($this->GetIDForIdent('Command'));
            if ($vid > 0 && @IPS_VariableExists($vid)) {
                $this->SendDebug('EnsureCommandProfileAndVariable', 'Variable created/exists: Command VID=' . $vid . ' Name=' . IPS_GetName($vid), 0);
            }
            $this->EnableAction('Command');
        } else {
            $this->UnregisterVariableIfExists('Command');
        }

        // Meta / Status
        if (($enabled['LastCommandName'] ?? true) === true) {
            $caption = $captionMap['LastCommandName'] ?? $this->Translate('Last Command (Name)');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: LastCommandName => ' . $caption, 0);
            $this->RegisterVariableString('LastCommandName', $caption, '', 10);
            $this->EnsureVariableCaption('LastCommandName', $caption);
        } else {
            $this->UnregisterVariableIfExists('LastCommandName');
        }

        if (($enabled['LastCommandAlias'] ?? true) === true) {
            $caption = $captionMap['LastCommandAlias'] ?? $this->Translate('Last Command (Alias)');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: LastCommandAlias => ' . $caption, 0);
            $this->RegisterVariableString('LastCommandAlias', $caption, '', 11);
            $this->EnsureVariableCaption('LastCommandAlias', $caption);
        } else {
            $this->UnregisterVariableIfExists('LastCommandAlias');
        }

        if (($enabled['LastCommandIndex'] ?? true) === true) {
            $caption = $captionMap['LastCommandIndex'] ?? $this->Translate('Last Command (Index)');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: LastCommandIndex => ' . $caption, 0);
            $this->RegisterVariableInteger('LastCommandIndex', $caption, '', 12);
            $this->EnsureVariableCaption('LastCommandIndex', $caption);
        } else {
            $this->UnregisterVariableIfExists('LastCommandIndex');
        }

        if (($enabled['LastCommandSentAt'] ?? true) === true) {
            $caption = $captionMap['LastCommandSentAt'] ?? $this->Translate('Last Sent (Time)');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: LastCommandSentAt => ' . $caption, 0);
            $this->RegisterVariableInteger('LastCommandSentAt', $caption, '~UnixTimestamp', 13);
            $this->EnsureVariableCaption('LastCommandSentAt', $caption);
        } else {
            $this->UnregisterVariableIfExists('LastCommandSentAt');
        }

        if (($enabled['CurrentAlias'] ?? true) === true) {
            $caption = $captionMap['CurrentAlias'] ?? $this->Translate('Current State (Alias)');
            $this->SendDebug('EnsureCommandProfileAndVariable', 'RegisterVariable: CurrentAlias => ' . $caption, 0);
            $this->RegisterVariableString('CurrentAlias', $caption, '', 14);
            $this->EnsureVariableCaption('CurrentAlias', $caption);
        } else {
            $this->UnregisterVariableIfExists('CurrentAlias');
        }
    }

    /**
     * Ensure the variable exists and (re)apply the translated name.
     * RegisterVariable* does not rename an existing variable automatically.
     */
    private function EnsureVariableCaption(string $ident, string $caption): void
    {
        try {
            $vid = @$this->GetIDForIdent($ident);
            if ($vid > 0 && @IPS_VariableExists($vid)) {
                IPS_SetName($vid, $caption);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    private function SendCommandByIndex(int $index): void
    {
        $rows = json_decode($this->ReadPropertyString('commands'), true);

        if (!is_array($rows) || !isset($rows[$index]) || !is_array($rows[$index])) {
            throw new Exception('Invalid command index: ' . $index);
        }

        $row = $rows[$index];

        $cmdName = (string)($row['CommandName'] ?? ('Cmd ' . $index));
        $cmdAlias = '';
        if (isset($row['CommandAlias'])) {
            $cmdAlias = trim((string)$row['CommandAlias']);
        }

        $this->SendDebug('SendCommandByIndex', 'Triggered command index=' . $index . ' name=' . $cmdName . ' alias=' . $cmdAlias . ' (IR-only)', 0);

        $enabled = $this->GetVarEnabledMap();

        // Store last command meta (even if IR has no feedback)
        if (($enabled['LastCommandName'] ?? true) && @$this->GetIDForIdent('LastCommandName') > 0) {
            $this->SetValue('LastCommandName', $cmdName);
        }
        if (($enabled['LastCommandAlias'] ?? true) && @$this->GetIDForIdent('LastCommandAlias') > 0) {
            $this->SetValue('LastCommandAlias', $cmdAlias);
        }
        if (($enabled['LastCommandIndex'] ?? true) && @$this->GetIDForIdent('LastCommandIndex') > 0) {
            $this->SetValue('LastCommandIndex', $index);
        }
        if (($enabled['LastCommandSentAt'] ?? true) && @$this->GetIDForIdent('LastCommandSentAt') > 0) {
            $this->SetValue('LastCommandSentAt', time());
        }
        if (($enabled['CurrentAlias'] ?? true) && @$this->GetIDForIdent('CurrentAlias') > 0) {
            // For simple devices like a screen, treat the last alias as the current assumed state
            $this->SetValue('CurrentAlias', $cmdAlias);
        }

        $repeat = isset($row['Repetition']) ? (int)$row['Repetition'] : 1;
        if ($repeat < 1) {
            $repeat = 1;
        }

        $code = isset($row['Command']) ? trim((string)$row['Command']) : '';
        if ($code === '') {
            throw new Exception('IR Command is empty for index: ' . $index);
        }

        $payload = [
            'type' => 'ir',
            'deviceInstanceId' => $this->InstanceID,
            'deviceName' => $this->ReadPropertyString('name'),
            'commandIndex' => $index,
            'commandName' => $cmdName,
            'codeFormat' => $this->ReadPropertyString('codeFormat'),
            'frequency' => (int)$this->ReadPropertyInteger('frequency'),
            'duty' => 33,
            'repeat' => $repeat,
            'code' => $code
        ];

        // Keep payload readable in debug (do not spam with huge IR data)
        $payloadForDebug = $payload;
        if (isset($payloadForDebug['code']) && is_string($payloadForDebug['code']) && strlen($payloadForDebug['code']) > 120) {
            $payloadForDebug['code'] = substr($payloadForDebug['code'], 0, 120) . '...(' . strlen($payloadForDebug['code']) . ' chars)';
        }
        $this->SendDebug('SendCommandByIndex', 'Sending payload to parent: ' . json_encode($payloadForDebug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);

        $res = $this->ForwardCommandToParent($payload);
        $this->SendDebug('SendCommandByIndex', 'Forward result: ' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);
    }

    public function SendCommandByName(string $commandName): void
    {
        $commandName = trim($commandName);
        if ($commandName === '') {
            throw new Exception('Command name is empty');
        }
        $this->SendDebug('SendCommandByName', 'Called with commandName="' . $commandName . '" on InstanceID=' . $this->InstanceID, 0);

        $rows = json_decode($this->ReadPropertyString('commands'), true);
        if (!is_array($rows)) {
            throw new Exception('Commands list is invalid');
        }

        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['CommandName']) ? trim((string)$row['CommandName']) : '';
            if (strcasecmp($name, $commandName) === 0) {
                $this->SendDebug('SendCommandByName', 'Resolved commandName="' . $commandName . '" to index=' . (int)$idx, 0);
                $this->SendCommandByIndex((int)$idx);
                return;
            }
        }

        $this->SendDebug('SendCommandByName', 'Command not found in commands list: "' . $commandName . '"', 0);
        throw new Exception('Command not found: ' . $commandName);
    }

    /**
     * Creates one IP-Symcon script per command entry of this device instance.
     * Scripts will be created/updated under the selected category.
     *
     * Each generated script simply calls USD_SendCommandByName(<instanceId>, <commandName>).
     */
    public function CreateCommandScripts(int $categoryId = 0): void
    {
        if ($categoryId <= 0) {
            $categoryId = (int)$this->ReadPropertyInteger('scriptCategory');
        }

        if ($categoryId <= 0 || !IPS_CategoryExists($categoryId)) {
            throw new Exception('Ungültige Zielkategorie (scriptCategory). Bitte im Formular eine Kategorie auswählen.');
        }

        $type = strtoupper($this->ReadPropertyString('deviceType'));
        $rows = $type === 'RF'
            ? json_decode($this->ReadPropertyString('rfCommands'), true)
            : json_decode($this->ReadPropertyString('commands'), true);

        if (!is_array($rows)) {
            throw new Exception('Commands list is invalid');
        }

        $deviceName = trim($this->ReadPropertyString('name'));
        if ($deviceName === '') {
            $deviceName = 'Remote 3 IR Device ' . $this->InstanceID;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $cmdName = isset($row['CommandName']) ? trim((string)$row['CommandName']) : '';
            if ($cmdName === '') {
                // Fallback: avoid empty script names
                $cmdName = 'Cmd_' . (int)$idx;
            }

            $scriptName = $this->SanitizeObjectName($deviceName . ' - ' . $cmdName);

            $existing = @IPS_GetObjectIDByName($scriptName, $categoryId);
            $isNew = false;

            if ($existing === false || $existing === 0) {
                $scriptId = IPS_CreateScript(0); // PHP
                IPS_SetParent($scriptId, $categoryId);
                IPS_SetName($scriptId, $scriptName);
                $isNew = true;
            } else {
                $scriptId = (int)$existing;
                if (!IPS_ScriptExists($scriptId)) {
                    $scriptId = IPS_CreateScript(0);
                    IPS_SetParent($scriptId, $categoryId);
                    IPS_SetName($scriptId, $scriptName);
                    $isNew = true;
                }
            }

            $content = $this->BuildCommandScriptContent($cmdName);
            IPS_SetScriptContent($scriptId, $content);

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $msg = sprintf('Skripterstellung abgeschlossen. Created=%d, Updated=%d, Skipped=%d, CategoryID=%d', $created, $updated, $skipped, $categoryId);
        $this->SendDebug('CreateCommandScripts', $msg, 0);
    }

    /**
     * Returns caption map from current varSettings: Ident => Caption
     * Note: varSettings captions are already translated during NormalizeVarSettings.
     */
    private function GetVarCaptionMap(): array
    {
        $rows = json_decode($this->ReadPropertyString('varSettings'), true);
        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['Ident'])) {
                continue;
            }
            $ident = (string)$row['Ident'];
            $caption = isset($row['Caption']) ? (string)$row['Caption'] : '';
            if ($caption !== '') {
                $map[$ident] = $caption;
            }
        }
        return $map;
    }

    /**
     * Returns the list of variables that the instance can create.
     */
    private function GetAvailableVariables(): array
    {
        return [
            ['Ident' => 'Command', 'Caption' => 'Command (Selector/Action)'],
            ['Ident' => 'LastCommandName', 'Caption' => 'Last Command (Name)'],
            ['Ident' => 'LastCommandAlias', 'Caption' => 'Last Command (Alias)'],
            ['Ident' => 'LastCommandIndex', 'Caption' => 'Last Command (Index)'],
            ['Ident' => 'LastCommandSentAt', 'Caption' => 'Last Sent (Time)'],
            ['Ident' => 'CurrentAlias', 'Caption' => 'Current State (Alias)']
        ];
    }

    /**
     * Ensures that the property varSettings contains rows for all available variables.
     * Default: all enabled.
     */
    private function NormalizeVarSettings(): void
    {
        $current = json_decode($this->ReadPropertyString('varSettings'), true);
        $this->SendDebug('NormalizeVarSettings', 'Current(raw)=' . $this->ReadPropertyString('varSettings'), 0);
        if (!is_array($current)) {
            $current = [];
        }

        // map existing by Ident
        $map = [];
        foreach ($current as $row) {
            if (is_array($row) && isset($row['Ident'])) {
                $map[(string)$row['Ident']] = $row;
            }
        }

        $normalized = [];
        foreach ($this->GetAvailableVariables() as $v) {
            $ident = (string)$v['Ident'];
            $caption = $this->Translate((string)$v['Caption']);

            $enabled = true;
            if (isset($map[$ident]) && is_array($map[$ident]) && array_key_exists('Enabled', $map[$ident])) {
                $enabled = (bool)$map[$ident]['Enabled'];
            }

            $normalized[] = [
                'Ident' => $ident,
                'Caption' => $caption,
                'Enabled' => $enabled
            ];
        }

        // Only write back if changed (avoid endless ApplyChanges loops)
        $this->SendDebug('NormalizeVarSettings', 'Normalized=' . json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0);
        if (json_encode($normalized) !== json_encode($current)) {
            IPS_SetProperty($this->InstanceID, 'varSettings', json_encode($normalized));
            // do not call IPS_ApplyChanges here; ApplyChanges already running
        }
    }

    /**
     * Returns enabled map: Ident => bool
     */
    private function GetVarEnabledMap(): array
    {
        $rows = json_decode($this->ReadPropertyString('varSettings'), true);
        if (!is_array($rows) || count($rows) === 0) {
            // default: all enabled
            $rows = [];
            foreach ($this->GetAvailableVariables() as $v) {
                $rows[] = ['Ident' => (string)$v['Ident'], 'Enabled' => true];
            }
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['Ident'])) {
                continue;
            }
            $map[(string)$row['Ident']] = isset($row['Enabled']) ? (bool)$row['Enabled'] : true;
        }
        return $map;
    }

    /**
     * Unregisters a variable if it exists (used when user disables it).
     */
    private function UnregisterVariableIfExists(string $ident): void
    {
        try {
            $vid = @$this->GetIDForIdent($ident);
            if ($vid > 0 && @IPS_VariableExists($vid)) {
                $this->UnregisterVariable($ident);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * Export this device definition to a JSON file under /media and return the file path.
     */
    public function ExportDeviceDefinition(): string
    {
        $export = [
            'version' => 1,
            'exportedAt' => time(),
            'name' => $this->ReadPropertyString('name'),
            'frequency' => (int)$this->ReadPropertyInteger('frequency'),
            'codeFormat' => $this->ReadPropertyString('codeFormat'),
            'commands' => json_decode($this->ReadPropertyString('commands'), true)
        ];

        if ($this->ReadPropertyBoolean('exportIncludeMeta')) {
            $export['varSettings'] = json_decode($this->ReadPropertyString('varSettings'), true);
        }

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Export JSON encoding failed');
        }

        $dir = IPS_GetKernelDir() . 'media/KinconyExports/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fileName = 'KinconyDevice_' . $this->InstanceID . '_' . date('Ymd_His') . '.json';
        $file = $dir . $fileName;

        file_put_contents($file, $json);

        // Store last export file for later use
        $this->WriteAttributeString('lastExportFile', $fileName);

        // Ask parent to build the full download URL (hook + secret + file)
        $fullUrl = $this->RequestDownloadUrlFromParent($fileName);
        if ($fullUrl !== '') {
            $this->WriteAttributeString('lastExportUrl', $fullUrl);
            // Update the form label immediately (if form is open)
            $this->UpdateFormField('ExportDownloadLink', 'caption', 'Export download link: ' . $fullUrl);
        } else {
            $this->WriteAttributeString('lastExportUrl', '');
            $this->UpdateFormField('ExportDownloadLink', 'caption', 'Export download link: (not available yet - splitter not updated)');
        }

        $relativeUrl = '/media/KinconyExports/' . $fileName;

        // Hinweis: funktioniert nur, wenn dein Symcon-Webserver /media ausliefert (typisch: localhost:3777)
        $msg = "✅ Export gespeichert:\n" .
            $file . "\n\n" .
            (($fullUrl !== '') ? ("Download-URL (voll):\n" . $fullUrl . "\n\n") : "") .
            "Download-URL (relativ):\n" .
            $relativeUrl;

        $this->LogMessage($msg, KL_NOTIFY);
        $this->SendDebug('ExportDeviceDefinition', $msg, 0);

        // Show a popup with the export result (download path)
        // Text setzen
        // $this->UpdateFormField('ExportResultPopup', 'caption', "Export abgeschlossen");
        $this->UpdateFormField('ExportResultText', 'caption', $msg);

        // Popup öffnen
        $this->UpdateFormField('ExportResultPopup', 'visible', true);

        // Rückgabe (falls per Konsole/Debug genutzt)
        return $msg;
    }

    public function CloseExportPopup(): void
    {
        // Close popup if open and prevent it from reopening next time the form is opened
        $this->UpdateFormField('ExportResultPopup', 'visible', false);
    }

    /**
     * Import a device definition JSON file and write its content into this instance.
     * Supports file path, MediaID, or base64-encoded file content (as returned by SelectFile).
     */
    public function ImportDeviceDefinition(string $filePath = ''): void
    {
        if ($filePath === '') {
            $filePath = (string)$this->ReadPropertyString('importFile');
        }
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new Exception('Keine Import-Datei gewählt.');
        }

        $raw = '';

        // 1) If SelectFile returns a MediaID (numeric), load media content
        if (ctype_digit($filePath)) {
            $mid = (int)$filePath;
            if (@IPS_MediaExists($mid)) {
                $content = IPS_GetMediaContent($mid);
                // Media content is base64 encoded
                $decoded = base64_decode($content, true);
                if ($decoded !== false) {
                    $raw = $decoded;
                }
            }
        }

        // 2) If it looks like a real file path and exists, read it
        if ($raw === '' && file_exists($filePath)) {
            $tmp = file_get_contents($filePath);
            if ($tmp !== false) {
                $raw = $tmp;
            }
        }

        // 3) If SelectFile returns base64 encoded content directly (often starts with "ew" for "{")
        if ($raw === '') {
            $decoded = base64_decode($filePath, true);
            if ($decoded !== false) {
                $raw = $decoded;
            }
        }

        // 4) If nothing worked yet, treat the input as raw JSON (rare)
        if ($raw === '' && (strpos($filePath, '{') !== false || strpos($filePath, '[') !== false)) {
            $raw = $filePath;
        }

        if ($raw === '') {
            throw new Exception('Import-Datei konnte nicht geladen werden (Pfad/Media/Base64). Wert: ' . $filePath);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new Exception('Import-Datei enthält kein gültiges JSON.');
        }

        // Only apply known keys
        if (isset($data['name'])) {
            IPS_SetProperty($this->InstanceID, 'name', (string)$data['name']);
        }
        if (isset($data['frequency'])) {
            IPS_SetProperty($this->InstanceID, 'frequency', (int)$data['frequency']);
        }
        if (isset($data['codeFormat'])) {
            IPS_SetProperty($this->InstanceID, 'codeFormat', (string)$data['codeFormat']);
        }
        if (array_key_exists('commands', $data)) {
            IPS_SetProperty($this->InstanceID, 'commands', json_encode($data['commands'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        if (isset($data['varSettings']) && is_array($data['varSettings'])) {
            IPS_SetProperty($this->InstanceID, 'varSettings', json_encode($data['varSettings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        IPS_ApplyChanges($this->InstanceID);
        $this->SendDebug('ImportDeviceDefinition', 'Imported from ' . $filePath, 0);
    }

    /**
     * Import Remote 3 learned IR codes CSV (columns: key, format, code)
     * and convert to this instance's Commands list.
     */
    public function ImportRemote3Csv(string $filePath = ''): void
    {
        if ($filePath === '') {
            $filePath = (string)$this->ReadPropertyString('importCsvFile');
        }
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new Exception('Keine CSV-Datei gewählt.');
        }

        $raw = '';

        // 1) If SelectFile returns a MediaID (numeric), load media content
        if (ctype_digit($filePath)) {
            $mid = (int)$filePath;
            if (@IPS_MediaExists($mid)) {
                $content = IPS_GetMediaContent($mid);
                $decoded = base64_decode($content, true);
                if ($decoded !== false) {
                    $raw = $decoded;
                }
            }
        }

        // 2) If it looks like a real file path and exists, read it
        if ($raw === '' && file_exists($filePath)) {
            $tmp = file_get_contents($filePath);
            if ($tmp !== false) {
                $raw = $tmp;
            }
        }

        // 3) If SelectFile returns base64 encoded content directly
        if ($raw === '') {
            $decoded = base64_decode($filePath, true);
            if ($decoded !== false) {
                $raw = $decoded;
            }
        }

        if ($raw === '') {
            throw new Exception('CSV-Datei konnte nicht geladen werden (Pfad/Media/Base64). Wert: ' . $filePath);
        }

        $lines = preg_split('~\r\n|\n|\r~', $raw);
        $lines = array_values(array_filter($lines, static function ($l) {
            return trim((string)$l) !== '';
        }));

        if (count($lines) < 2) {
            throw new Exception('CSV scheint leer zu sein oder enthält nur Header.');
        }

        // Parse CSV using PHP's CSV parser line by line to be robust against commas/quotes
        $header = null;
        $commands = [];
        $countPronto = 0;
        $countUcHex = 0;

        foreach ($lines as $idx => $line) {
            $row = str_getcsv($line);
            if (!is_array($row) || count($row) === 0) {
                continue;
            }

            if ($idx === 0) {
                $header = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            // Expect columns: key, format, code
            $map = [];
            if (is_array($header)) {
                foreach ($header as $hIdx => $h) {
                    $map[$h] = $row[$hIdx] ?? '';
                }
            }

            $key = trim((string)($map['key'] ?? ($row[0] ?? '')));
            $format = strtoupper(trim((string)($map['format'] ?? ($row[1] ?? ''))));
            $code = trim((string)($map['code'] ?? ($row[2] ?? '')));

            // Determine format: prefer CSV column, otherwise detect from code
            $detected = '';
            if ($format === 'PRONTO' || $format === 'UC_HEX') {
                $detected = $format;
            } else {
                $detected = $this->DetectIrFormatFromCode($code);
            }

            if ($detected === 'PRONTO') {
                $countPronto++;
            } elseif ($detected === 'UC_HEX') {
                $countUcHex++;
            }

            // Use the key also as alias, but make it more readable
            $alias = $this->MakePrettyAlias($key);

            if ($key === '' || $code === '') {
                continue;
            }

            // Normalize: keep spaces for PRONTO (Remote export uses spaced words)
            // but remove excessive whitespace
            $code = preg_replace('~\s+~', ' ', $code);
            $code = trim((string)$code);

            $commands[] = [
                'CommandName' => $key,
                'CommandAlias' => $alias,
                'Command' => $code,
                'Repetition' => 1
            ];

            // If CSV includes mixed formats, we still store as-is.
            // The actual send will use the instance property codeFormat.
        }

        if (count($commands) === 0) {
            throw new Exception('Keine gültigen Befehle in der CSV gefunden. Erwartet: key/format/code.');
        }

        // Apply to instance
        IPS_SetProperty($this->InstanceID, 'commands', json_encode($commands, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // If instance name is empty, derive a reasonable default from the CSV filename
        $currentName = trim((string)$this->ReadPropertyString('name'));
        if ($currentName === '') {
            $derived = '';

            // If we have a real file path, use its basename
            if (strpos($filePath, DIRECTORY_SEPARATOR) !== false || preg_match('~\.csv$~i', $filePath) === 1) {
                $derived = basename($filePath);
            }

            // Clean typical Remote 3 export naming
            if ($derived !== '') {
                $derived = preg_replace('~\.(csv)$~i', '', (string)$derived);
                $derived = str_replace('Custom - learned IR codes_', '', (string)$derived);
                $derived = preg_replace('~_codeset_\d{4}-\d{2}-\d{2}$~', '', (string)$derived);
                $derived = str_replace(['_', '-'], ' ', (string)$derived);
                $derived = preg_replace('~\s+~', ' ', (string)$derived);
                $derived = trim((string)$derived);
            }

            if ($derived !== '') {
                IPS_SetProperty($this->InstanceID, 'name', $derived);
            }
        }

        // Set instance codeFormat based on imported content.
        // Prefer PRONTO if present, otherwise UC_HEX. If unknown, keep current setting.
        if ($countPronto > 0) {
            IPS_SetProperty($this->InstanceID, 'codeFormat', 'PRONTO');
        } elseif ($countUcHex > 0) {
            IPS_SetProperty($this->InstanceID, 'codeFormat', 'UC_HEX');
        }

        IPS_ApplyChanges($this->InstanceID);
        $this->SendDebug('ImportRemote3Csv', 'Imported ' . count($commands) . ' commands from CSV: ' . $filePath, 0);
    }


    /**
     * Helper: build script body for a single command name.
     */
    private function BuildCommandScriptContent(string $commandName): string
    {
        $commandNameEsc = addslashes($commandName);
        $instanceId = $this->InstanceID;

        return "<?php\n\n" .
            "// Auto-generated by HaptiqueKinconyDevice (#{$instanceId})\n" .
            "// Command: {$commandNameEsc}\n\n" .
            "if (!function_exists('USD_SendCommandByName')) {\n" .
            "    throw new Exception('USD_SendCommandByName() not found. Is the module installed/loaded?');\n" .
            "}\n\n" .
            "USD_SendCommandByName({$instanceId}, \"{$commandNameEsc}\");\n";
    }

    /**
     * Build a human-friendly alias from an internal command key.
     * Examples:
     *  - "RestMode"   => "Rest Mode"
     *  - "Power_On"  => "Power On"
     *  - "Format10"  => "Format 10"
     */
    private function MakePrettyAlias(string $key): string
    {
        $s = trim($key);
        if ($s === '') {
            return '';
        }

        // Replace separators with spaces
        $s = str_replace(['_', '-', '.', '/'], ' ', $s);

        // Insert spaces between camelCase / PascalCase boundaries
        // aB -> a B
        $s = preg_replace('~([a-z0-9])([A-Z])~', '$1 $2', $s);
        // ABCd -> AB Cd (split before last capital when next is lowercase)
        $s = preg_replace('~([A-Z]+)([A-Z][a-z])~', '$1 $2', (string)$s);
        // Letters followed by digits: Format10 -> Format 10
        $s = preg_replace('~([A-Za-z])([0-9])~', '$1 $2', (string)$s);

        // Collapse whitespace
        $s = preg_replace('~\s+~', ' ', (string)$s);
        $s = trim((string)$s);

        return $s;
    }

    /**
     * Best-effort IR format detection from code string.
     * Returns 'PRONTO', 'UC_HEX' or '' if unknown.
     */
    private function DetectIrFormatFromCode(string $code): string
    {
        $s = trim($code);
        if ($s === '') {
            return '';
        }

        // Normalize whitespace for pattern checks
        $ws = preg_replace('~\s+~', ' ', $s);
        $ws = trim((string)$ws);

        // Typical PRONTO: starts with 0000 and space-separated 4-hex words
        if (preg_match('~^0000\s+[0-9A-Fa-f]{4}(\s+[0-9A-Fa-f]{4})+~', $ws) === 1) {
            return 'PRONTO';
        }

        // UC_HEX often appears as a hex stream, sometimes with 0x.. tokens and commas
        if (stripos($ws, '0x') !== false) {
            return 'UC_HEX';
        }

        // If it contains only hex chars plus common separators (spaces, commas, semicolons), assume UC_HEX
        $tmp = str_replace([',', ';', ' ', '\t'], '', $ws);
        $tmp = trim($tmp);
        if ($tmp !== '' && preg_match('~^[0-9A-Fa-f]+$~', $tmp) === 1) {
            return 'UC_HEX';
        }

        return '';
    }

    /**
     * Helper: keep script/object names valid and reasonably short.
     */
    private function SanitizeObjectName(string $name): string
    {
        $name = trim($name);

        // Replace problematic characters without regex delimiter pitfalls
        $invalidChars = [
            "\\", "/", ":", "*", "?", '"', "<", ">", "|"
        ];
        $name = str_replace($invalidChars, ' ', $name);

        // Normalize control chars and whitespace
        $name = preg_replace('~[\r\n\t]+~', ' ', (string)$name);
        $name = preg_replace('~\s+~', ' ', (string)$name);
        $name = trim((string)$name);

        // IP-Symcon object names are typically safe up to ~255 chars; keep a conservative limit
        if (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }

        return $name;
    }

    private function BuildManufacturerCsv(array $rows): string
    {
        $fp = fopen('php://temp', 'r+');

        // Header exakt wie Hersteller
        fputcsv($fp, ['Category', 'Brand', 'Model Number', 'Frequency', 'Control Name', 'Control IR Data']);

        // Optional: Gruppierung nach Device (Category+Brand+Model+Frequency)
        $lastKey = null;

        foreach ($rows as $r) {
            $category = trim((string)($r['Category'] ?? ''));
            $brand = trim((string)($r['Brand'] ?? ''));
            $model = trim((string)($r['ModelNumber'] ?? $r['Model Number'] ?? ''));
            $freq = trim((string)($r['Frequency'] ?? ''));
            $name = trim((string)($r['ControlName'] ?? $r['Control Name'] ?? ''));
            $ir = (string)($r['ControlIRData'] ?? $r['Control IR Data'] ?? '');

            // IR-Daten normalisieren (z.B. Leerzeichen raus)
            $ir = preg_replace('/\s+/', '', $ir ?? '');

            $key = $category . '|' . $brand . '|' . $model . '|' . $freq;

            // Wie im Beispiel: Leerblock zwischen Geräten
            if ($lastKey !== null && $key !== $lastKey) {
                fputcsv($fp, ['', '', '', '', '', '']); // ergibt ",,,,,"
                // manche Beispiele haben sogar zwei Leerzeilen – wenn du willst:
                // fputcsv($fp, ['', '', '', '', '', '']);
            }
            $lastKey = $key;

            fputcsv($fp, [$category, $brand, $model, $freq, $name, $ir]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        // Sicherstellen: UTF-8 ohne BOM (normalerweise ok)
        return $csv;
    }

    public function ExportIRCsv(): string
    {
        $writeToFile = true;

        // HIER den Namen deiner Property anpassen:
        $json = $this->ReadPropertyString('IRCommands');

        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            throw new Exception('IRCommands ist kein gültiges JSON-Array.');
        }

        $csv = $this->BuildManufacturerCsv($rows);

        if ($writeToFile) {
            $file = IPS_GetKernelDir() . 'media/IR_Export_' . $this->InstanceID . '.csv';
            file_put_contents($file, $csv);
            $this->SendDebug('CSV Export', 'Wrote ' . strlen($csv) . ' bytes to ' . $file, 0);
        }

        return $csv;
    }

    public function UpdateFormVisibility(): void
    {
        // IR-only module: always show IR fields
        $this->UpdateFormField('frequency', 'visible', true);
        $this->UpdateFormField('codeFormat', 'visible', true);
        $this->UpdateFormField('commands', 'visible', true);
    }

    private function GetVarSettingsRowsForForm(): array
    {
        $rows = json_decode($this->ReadPropertyString('varSettings'), true);
        if (is_array($rows) && count($rows) > 0) {
            return $rows;
        }

        // Fallback: Default = alle Variablen aktiv
        $out = [];
        foreach ($this->GetAvailableVariables() as $v) {
            $out[] = [
                'Ident' => (string)$v['Ident'],
                'Caption' => $this->Translate((string)$v['Caption']),
                'Enabled' => true
            ];
        }
        return $out;
    }


    public function GetConfigurationForm(): string
    {
        $Form = json_encode([
            'elements' => $this->FormElements(),
            'actions' => $this->FormActions(),
            'status' => $this->FormStatus()
        ]);

        $this->SendDebug('FORM', $Form, 0);
        return $Form;
    }

    /**
     * Definiert die Formularelemente für die Konfiguration.
     *
     * @return array
     */
    protected function FormElements(): array
    {
        return [
            [
                'type' => 'Label',
                'name' => 'Support',
                'italic' => true,
                'caption' => 'The IR codes learned on the Remote 3 can be viewed on the Remote 3 Webinterface.'
            ],
            [
                'type' => 'ValidationTextBox',
                'name' => 'name',
                'caption' => 'Device name'
            ],
            [
                'type' => 'Select',
                'name' => 'frequency',
                'caption' => 'Frequency',
                'visible' => true,
                'options' => [
                    ['caption' => '36000', 'value' => 36000],
                    ['caption' => '38000', 'value' => 38000],
                    ['caption' => '40000', 'value' => 40000],
                    ['caption' => '56000', 'value' => 56000]
                ]
            ],
            [
                'type' => 'Select',
                'name' => 'codeFormat',
                'caption' => 'IR Device Code Format',
                'visible' => true,
                'options' => [
                    ['caption' => 'PRONTO', 'value' => 'PRONTO'],
                    ['caption' => 'UC_HEX', 'value' => 'UC_HEX']
                ]
            ],
            [
                'type' => 'List',
                'name' => 'commands',
                'caption' => 'Commands',
                'visible' => true,
                'add' => true,
                'delete' => true,
                'columns' => [
                    [
                        'caption' => 'Command Name',
                        'name' => 'CommandName',
                        'width' => '200px',
                        'edit' => [
                            'type' => 'ValidationTextBox'
                        ],
                        'add' => ''
                    ],
                    [
                        'caption' => 'Command Alias',
                        'name' => 'CommandAlias',
                        'width' => '300px',
                        'edit' => [
                            'type' => 'ValidationTextBox'
                        ],
                        'add' => ''
                    ],
                    [
                        'caption' => 'Command',
                        'name' => 'Command',
                        'width' => 'auto',
                        'edit' => [
                            'type' => 'ValidationTextBox'
                        ],
                        'add' => ''
                    ],
                    [
                        'caption' => 'Repetition',
                        'name' => 'Repetition',
                        'width' => '150px',
                        'edit' => [
                            'type' => 'NumberSpinner',
                            'minimum' => 1,
                            'digits' => 0
                        ],
                        'add' => 1
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => 'Variableneinstellungen',
                'items' => [
                    [
                        'type' => 'List',
                        'name' => 'varSettings',
                        'caption' => 'Available variables',
                        'rowCount' => 6,
                        'add' => false,
                        'delete' => false,
                        'columns' => [
                            [
                                'caption' => 'Ident',
                                'name' => 'Ident',
                                'width' => '180px',
                                'save' => true
                            ],
                            [
                                'caption' => 'Variable',
                                'name' => 'Caption',
                                'width' => 'auto',
                                'save' => true
                            ],
                            [
                                'caption' => 'Create',
                                'name' => 'Enabled',
                                'width' => '120px',
                                'save' => true,
                                'edit' => [
                                    'type' => 'CheckBox'
                                ]
                            ]
                        ],
                        'values' => $this->GetVarSettingsRowsForForm()
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'Default: all variables are created. If you disable variables, they will be removed on the next ApplyChanges.'
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => 'Skripterstellung',
                'items' => [
                    [
                        'type' => 'SelectCategory',
                        'name' => 'scriptCategory',
                        'caption' => 'Target category for scripts'
                    ],
                    [
                        'type' => 'Button',
                        'caption' => 'Create scripts for commands',
                        'onClick' => 'UCD_CreateCommandScripts($id, $scriptCategory);'
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'Creates/updates one script per command in the selected category.'
                    ]
                ]
            ],
            [
                'type' => 'ExpansionPanel',
                'caption' => 'Import / Export',
                'items' => [
                    [
                        'type' => 'CheckBox',
                        'name' => 'exportIncludeMeta',
                        'caption' => 'Include meta/variable settings in export'
                    ],
                    [
                        'type' => 'Button',
                        'caption' => 'Export (create JSON file)',
                        'onClick' => 'UCD_ExportDeviceDefinition($id);'
                    ],
                    [
                        'type' => 'Label',
                        'name' => 'ExportDownloadLink',
                        'link' => true,
                        'caption' => ($this->ReadAttributeString('lastExportUrl') !== '')
                            ? ('Export download link: ' . $this->ReadAttributeString('lastExportUrl'))
                            : 'Export download link: (create an export to generate the link)'
                    ],
                    [
                        'type' => 'SelectFile',
                        'name' => 'importFile',
                        'caption' => 'Select import file (JSON)',
                        'extensions' => '.json'
                    ],
                    [
                        'type' => 'SelectFile',
                        'name' => 'importCsvFile',
                        'caption' => 'Select Remote 3 CSV (learned IR codes)',
                        'extensions' => '.csv'
                    ],
                    [
                        'type' => 'Button',
                        'caption' => 'Import Remote 3 CSV (learned codes)',
                        'onClick' => 'UCD_ImportRemote3Csv($id, $importCsvFile);'
                    ],
                    [
                        'type' => 'Button',
                        'caption' => 'Import (apply to this instance)',
                        'onClick' => 'UCD_ImportDeviceDefinition($id, $importFile);'
                    ],
                    [
                        'type' => 'Label',
                        'caption' => 'Export creates a JSON file in the Symcon /media folder. Import overwrites deviceType/name/frequency/codeFormat and Commands/RF Commands (including alias and repetition).'
                    ]
                ]
            ],
            [
                'type' => 'Button',
                'caption' => 'Export manufacturer CSV',
                'onClick' => 'UCD_ExportIRCsv($id);'
            ]
        ];
    }

    /**
     * Definiert die Aktionen im Konfigurationsformular.
     *
     * @return array
     */
    protected function FormActions(): array
    {
        return [
            [
                'type' => 'PopupAlert',
                'name' => 'ExportResultPopup',
                'visible' => false,
                'popup' => [
                    'caption' => 'Export',
                    'closeCaption' => 'Close',
                    'items' => [
                        [
                            'type' => 'Label',
                            'name' => 'ExportResultText',
                            'caption' => ''
                        ]
                    ]
                ]
            ],
            [
                'type' => 'TestCenter'
            ]
        ];
    }

    /**
     * Gibt den Status für das Formular zurück.
     *
     * @return array
     */
    protected function FormStatus(): array
    {
        return [
            ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Creating instance...'],
            ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active.'],
            ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive.']
        ];
    }
}

