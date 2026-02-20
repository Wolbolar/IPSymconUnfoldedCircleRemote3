<?php

declare(strict_types=1);

class Remote3MediaPlayer extends IPSModuleStrict
{
    public function GetCompatibleParents(): string
    {
        // Prefer an existing Remote 3 Core Manager as parent (connect semantics).
        // A new one should only be created if no compatible parent exists.
        return json_encode([
            'type' => 'connect',
            'moduleIDs' => [
                '{C810D534-2395-7C43-D0BE-6DEC069B2516}'
            ]
        ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Selection + discovery of media player entities
        $this->RegisterPropertyString('SelectedEntityID', '');
        $this->RegisterAttributeString('KnownEntityIDs', '[]');

        // Media player state variables
        $this->RegisterVariableString('EntityID', 'Entity ID');
        $this->RegisterVariableString('State', 'State');
        $this->RegisterVariableString('MediaTitle', 'Media title');
        $this->RegisterVariableString('MediaArtist', 'Media artist');
        $this->RegisterVariableString('MediaAlbum', 'Media album');
        $this->RegisterVariableString('MediaType', 'Media type');
        $this->RegisterVariableString('Source', 'Source');
        $this->RegisterVariableString('Repeat', 'Repeat');
        $this->RegisterVariableBoolean('Shuffle', 'Shuffle');
        $this->RegisterVariableString('ImageUrl', 'Image URL');
        $this->RegisterVariableInteger('Position', 'Position (s)');
        $this->RegisterVariableInteger('Duration', 'Duration (s)');

        // Default values
        $this->SetValue('Shuffle', false);
        $this->SetValue('Position', 0);
        $this->SetValue('Duration', 0);
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

        $selected = $this->ReadPropertyString('SelectedEntityID');

        if ($selected === '') {
            // No filter: accept all messages (or only basic entity_change/media_player if you prefer)
            $this->SetReceiveDataFilter('');
            $this->SendDebug(__FUNCTION__, 'Receive filter: (none)', 0);
            return;
        }

        // Build a regex that matches the selected entity id inside the JSON payload.
        // This only works if the forwarded Buffer contains plain JSON (not HEX).
        $quoted = preg_quote($selected, '/');

        // Match either envelope or payload representation.
        // - Ensure it's an entity_change
        // - Ensure media_player entity type
        // - Ensure the selected entity_id appears
        $regex = '"msg"\s*:\s*"entity_change".*"entity_type"\s*:\s*"media_player".*"entity_id"\s*:\s*"' . $quoted . '"';

        $this->SetReceiveDataFilter($regex);
        $this->SendDebug(__FUNCTION__, 'Receive filter set: /' . $regex . '/', 0);
    }

    public function ClearKnownEntityIDs(): void
    {
        $this->WriteAttributeString('KnownEntityIDs', '[]');
        $this->SendDebug(__FUNCTION__, '🧹 Cleared learned entity IDs.', 0);
        if (method_exists($this, 'ReloadForm')) {
            $this->ReloadForm();
        }
    }

    private function Send(): void
    {
        $this->SendDataToParent(json_encode(['DataID' => '{AC2A1323-0258-76DC-5AA8-9B0C092820A5}']));
    }

    public function ReceiveData(string $JSONString): string
    {
        // Symcon passes an envelope JSON string, usually containing DataID + Buffer.
        $this->SendDebug(__FUNCTION__, '📥 Envelope: ' . $this->Shorten($JSONString, 400), 0);

        $envelope = json_decode($JSONString, true);
        if (!is_array($envelope)) {
            $this->SendDebug(__FUNCTION__, '⚠️ Envelope is not JSON (raw): ' . $this->Shorten($JSONString, 400), 0);
            return '';
        }

        $buffer = $envelope['Buffer'] ?? null;
        $payload = $this->DecodePayload($buffer);

        if (!is_array($payload)) {
            $this->SendDebug(__FUNCTION__, '⚠️ Payload could not be decoded. BufferType=' . gettype($buffer), 0);
            return '';
        }

        // We currently expect events like:
        // {"kind":"event","msg":"entity_change","cat":"ENTITY",...,"msg_data":{...}}
        $kind = (string)($payload['kind'] ?? '');
        $msg = (string)($payload['msg'] ?? '');

        if ($kind !== 'event' || $msg !== 'entity_change') {
            // Not relevant for this instance
            return '';
        }

        $msgData = $payload['msg_data'] ?? null;
        if (!is_array($msgData)) {
            return '';
        }

        $entityType = (string)($msgData['entity_type'] ?? '');
        if ($entityType !== 'media_player') {
            return '';
        }

        $entityId = (string)($msgData['entity_id'] ?? '');
        if ($entityId !== '') {
            // learn entity IDs for the dropdown
            $this->RememberEntityID($entityId);
            $this->SetValue('EntityID', $entityId);
        }

        // If user selected a specific entity, only process matching messages
        $selected = $this->ReadPropertyString('SelectedEntityID');
        if ($selected !== '' && $entityId !== '' && $entityId !== $selected) {
            return '';
        }

        $newState = $msgData['new_state'] ?? null;
        if (!is_array($newState)) {
            return '';
        }

        $attributes = $newState['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }

        // Extract common attributes
        $state = (string)($attributes['state'] ?? '');
        $title = (string)($attributes['media_title'] ?? '');
        $artist = (string)($attributes['media_artist'] ?? '');
        $album = (string)($attributes['media_album'] ?? '');
        $mediaType = (string)($attributes['media_type'] ?? '');
        $source = (string)($attributes['source'] ?? '');
        $repeat = (string)($attributes['repeat'] ?? '');
        $shuffle = (bool)($attributes['shuffle'] ?? false);
        $imageUrl = (string)($attributes['media_image_url'] ?? '');

        $position = $attributes['media_position'] ?? 0;
        $duration = $attributes['media_duration'] ?? 0;

        // Normalize numeric values (Remote sometimes sends null)
        $posInt = is_numeric($position) ? (int)$position : 0;
        $durInt = is_numeric($duration) ? (int)$duration : 0;

        $this->SetValue('State', $state);
        $this->SetValue('MediaTitle', $title);
        $this->SetValue('MediaArtist', $artist);
        $this->SetValue('MediaAlbum', $album);
        $this->SetValue('MediaType', $mediaType);
        $this->SetValue('Source', $source);
        $this->SetValue('Repeat', $repeat);
        $this->SetValue('Shuffle', $shuffle);
        $this->SetValue('ImageUrl', $imageUrl);
        $this->SetValue('Position', $posInt);
        $this->SetValue('Duration', $durInt);

        $this->SendDebug(__FUNCTION__, '🎵 Media player updated: ' . json_encode([
                'entity_id' => $entityId,
                'state' => $state,
                'title' => $title,
                'artist' => $artist
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0);

        return '';
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
        $known = $this->GetKnownEntityIDs();
        $options = [
            [
                'caption' => '— (no filter / any media player) —',
                'value' => ''
            ]
        ];

        foreach ($known as $id) {
            $options[] = [
                'caption' => $id,
                'value' => $id
            ];
        }

        return [
            [
                'type' => 'Select',
                'name' => 'SelectedEntityID',
                'caption' => 'Entity ID',
                'options' => $options
            ],
            [
                'type' => 'Label',
                'caption' => 'Receive filter is applied when an Entity ID is selected (requires JSON-forwarding, not HEX).'
            ],
            [
                'type' => 'Label',
                'caption' => 'Tip: Open Apple TV / start playback once so the entity appears in the list.'
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
                'caption' => 'Clear learned entity IDs',
                'onClick' => 'UCR_ClearKnownEntityIDs($id);'
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
                'caption' => 'Remote 3 Media Player created.'],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => 'Interface closed.']];

        return $form;
    }

    /**
     * @return string[]
     */
    private function GetKnownEntityIDs(): array
    {
        $raw = $this->ReadAttributeString('KnownEntityIDs');
        $arr = json_decode($raw, true);
        if (!is_array($arr)) {
            return [];
        }
        // keep only strings
        $arr = array_values(array_filter($arr, static fn($v) => is_string($v) && $v !== ''));
        // unique + stable sort
        $arr = array_values(array_unique($arr));
        sort($arr);
        return $arr;
    }

    private function RememberEntityID(string $entityId): void
    {
        if ($entityId === '') {
            return;
        }
        $known = $this->GetKnownEntityIDs();
        if (!in_array($entityId, $known, true)) {
            $known[] = $entityId;
            $known = array_values(array_unique($known));
            sort($known);
            $this->WriteAttributeString('KnownEntityIDs', json_encode($known));
            $this->SendDebug(__FUNCTION__, '✅ Learned new EntityID: ' . $entityId, 0);
            if (method_exists($this, 'ReloadForm')) {
                $this->ReloadForm();
            }
        }
    }

    /**
     * Decode the forwarded payload.
     * Buffer can be array, JSON string, or hex string containing JSON.
     *
     * @param mixed $buffer
     * @return array|null
     */
    private function DecodePayload($buffer): ?array
    {
        if (is_array($buffer)) {
            return $buffer;
        }

        if (!is_string($buffer) || $buffer === '') {
            return null;
        }

        // If buffer already looks like JSON
        $trim = ltrim($buffer);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = json_decode($buffer, true);
            return is_array($decoded) ? $decoded : null;
        }

        // If buffer is hex-encoded JSON
        if (ctype_xdigit($buffer) && (strlen($buffer) % 2 === 0)) {
            $raw = @hex2bin($buffer);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * Helper to keep debug readable.
     */
    private function Shorten(string $text, int $maxLen = 250): string
    {
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        return substr($text, 0, $maxLen) . '…';
    }
}