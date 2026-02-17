<?php

declare(strict_types=1);
require_once __DIR__ . '/Entity_Button.php';
require_once __DIR__ . '/Entity_Climate.php';
require_once __DIR__ . '/Entity_Cover.php';
require_once __DIR__ . '/Entity_IR_Emitter.php';
require_once __DIR__ . '/Entity_Light.php';
require_once __DIR__ . '/Entity_Media_Player.php';
require_once __DIR__ . '/Entity_Remote.php';
require_once __DIR__ . '/Entity_Sensor.php';
require_once __DIR__ . '/Entity_Switch.php';

class DeviceRegistry
{
    public static function getSupportedDevices(): array
    {
        return [
            [
                'name'            => 'Hue Light',
                'manufacturer'    => 'Signify',
                'guid'            => '{87FA14D1-0ACA-4CBD-BE83-BA4DF8831876}',
                'device_type'     => 'light',
                'device_sub_type' => 'rgbw',
                'features'        => [
                    Entity_Light::FEATURE_ON_OFF,
                    Entity_Light::FEATURE_DIM,
                    Entity_Light::FEATURE_COLOR,
                    Entity_Light::FEATURE_COLOR_TEMP
                ],
                'category'        => 'light_mapping',
                'attributes' => [
                    Entity_Light::ATTR_STATE             => 'on',              // Ident der Schaltvariable
                    Entity_Light::ATTR_BRIGHTNESS        => 'brightness',      // Ident der Helligkeit
                    Entity_Light::ATTR_HUE               => 'color',           // Ident der RGB-Farbe
                    Entity_Light::ATTR_SATURATION        => 'color',           // Ident der RGB-Farbe
                    Entity_Light::ATTR_COLOR_TEMPERATURE => 'color_temperature', // Ident der Farbtemperatur
                ]
            ],
            [
                'name'            => 'Hue Grouped Light',
                'manufacturer'    => 'Signify',
                'guid'            => '{6324AC4A-330C-4CB2-9281-12EECB450024}',
                'device_type'     => 'light',
                'device_sub_type' => 'rgbw',
                'features'        => [
                    Entity_Light::FEATURE_ON_OFF,
                    Entity_Light::FEATURE_DIM,
                    Entity_Light::FEATURE_COLOR,
                    Entity_Light::FEATURE_COLOR_TEMP
                ],
                'category'        => 'light_mapping',
                'attributes' => [
                    Entity_Light::ATTR_STATE             => 'on',              // Ident der Schaltvariable
                    Entity_Light::ATTR_BRIGHTNESS        => 'brightness',      // Ident der Helligkeit
                    Entity_Light::ATTR_HUE               => 'color',           // Ident der RGB-Farbe
                    Entity_Light::ATTR_SATURATION        => 'color',           // Ident der RGB-Farbe
                    Entity_Light::ATTR_COLOR_TEMPERATURE => 'color_temperature', // Ident der Farbtemperatur
                ]
            ],
            [
                'name'            => 'Sonos Speaker',
                'manufacturer'    => 'Sonos',
                'guid'            => '{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_SPEAKER,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                    Entity_Media_Player::FEATURE_SHUFFLE,
                    Entity_Media_Player::FEATURE_REPEAT,
                    Entity_Media_Player::FEATURE_MEDIA_DURATION,
                    Entity_Media_Player::FEATURE_MEDIA_POSITION,
                    Entity_Media_Player::FEATURE_MEDIA_TITLE,
                    Entity_Media_Player::FEATURE_MEDIA_ARTIST,
                    Entity_Media_Player::FEATURE_MEDIA_ALBUM,
                    Entity_Media_Player::FEATURE_MEDIA_IMAGE_URL,
                    Entity_Media_Player::FEATURE_SELECT_SOURCE
                    ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    Entity_Media_Player::ATTR_STATE    => 'N/A',
                    Entity_Media_Player::ATTR_VOLUME   => 'Volume',
                    Entity_Media_Player::ATTR_MUTED     => 'Mute',
                    Entity_Media_Player::ATTR_MEDIA_DURATION  => 'TrackDuration',
                    Entity_Media_Player::ATTR_MEDIA_POSITION  => 'Position',
                    Entity_Media_Player::ATTR_MEDIA_IMAGE_URL  => 'CoverURL',
                    Entity_Media_Player::ATTR_MEDIA_TITLE  => 'Title',
                    Entity_Media_Player::ATTR_MEDIA_ARTIST  => 'Artist',
                    Entity_Media_Player::ATTR_MEDIA_ALBUM  => 'Album',
                    Entity_Media_Player::ATTR_REPEAT  => 'PlayMode',
                    Entity_Media_Player::ATTR_SHUFFLE  => 'Shuffle',
                    Entity_Media_Player::ATTR_SOURCE  => 'Playlist',
                ]
            ],
            [
                'name'            => 'Denon AVR',
                'manufacturer'    => 'Denon',
                'guid'            => '{DC733830-533B-43CD-98F5-23FC2E61287F}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_RECEIVER,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_SELECT_SOURCE,
                    Entity_Media_Player::FEATURE_SELECT_SOUND_MODE
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    Entity_Media_Player::ATTR_STATE    => 'PW',
                    Entity_Media_Player::ATTR_VOLUME   => 'MV',
                    Entity_Media_Player::ATTR_MUTED     => 'MU',
                    Entity_Media_Player::ATTR_SOURCE     => 'SI',
                    Entity_Media_Player::ATTR_SOUND_MODE     => 'MS',
                ]
            ],
            [
                'name'            => 'Spotify',
                'manufacturer'    => 'Spotify',
                'guid'            => '{DCC40FC6-4447-AA1A-E3E5-B5F32DF81806}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_SPEAKER,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                    Entity_Media_Player::FEATURE_MEDIA_DURATION,
                    Entity_Media_Player::FEATURE_MEDIA_POSITION,
                    Entity_Media_Player::FEATURE_MEDIA_TITLE,
                    Entity_Media_Player::FEATURE_MEDIA_ARTIST,
                    Entity_Media_Player::FEATURE_MEDIA_ALBUM,
                    Entity_Media_Player::FEATURE_MEDIA_IMAGE_URL,
                    Entity_Media_Player::FEATURE_SELECT_SOURCE,
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    Entity_Media_Player::ATTR_STATE    => 'N/A',
                    Entity_Media_Player::ATTR_VOLUME   => 'Volume',
                    Entity_Media_Player::ATTR_MEDIA_DURATION  => 'CurrentDuration',
                    Entity_Media_Player::ATTR_MEDIA_POSITION  => 'CurrentPosition',
                    Entity_Media_Player::ATTR_MEDIA_IMAGE_URL  => 'Cover',
                    Entity_Media_Player::ATTR_MEDIA_TITLE  => 'CurrentTrack',
                    Entity_Media_Player::ATTR_MEDIA_ARTIST  => 'CurrentArtist',
                    Entity_Media_Player::ATTR_MEDIA_ALBUM  => 'CurrentAlbum',
                    Entity_Media_Player::ATTR_REPEAT  => 'Repeat',
                    Entity_Media_Player::ATTR_SHUFFLE  => 'Shuffle',
                    Entity_Media_Player::ATTR_SOURCE  => 'Device',
                ]
            ],
            [
                'name'            => 'HEOS',
                'manufacturer'    => 'Denon',
                'guid'            => '{68ED7CBB-76B7-4C24-07A2-61304D38CACD}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_SPEAKER,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    'power_var_id'    => 'Power',
                    'volume_var_id'   => 'Volume',
                    'mute_var_id'     => 'Muted',
                    'control_var_id'  => 'Control',
                    'shuffle_var_id'  => 'Control',
                    'repeat_var_id'  => 'Control',
                    'image_var_id'  => 'Control',
                    'title_var_id'    => 'Title',
                    'artist_var_id'   => 'Artist',
                    'album_var_id'    => 'Album',
                    'source_var_id'    => 'Album',
                    'sound_mode_var_id'    => 'Album',
                ]
            ],
            [
                'name'            => 'HomePod',
                'manufacturer'    => 'Apple',
                'guid'            => '{D5C53262-AEEF-AA8A-9EC5-940E6B95A9A8}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_SPEAKER,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    'power_var_id'    => 'Power',
                    'volume_var_id'   => 'Volume',
                    'mute_var_id'     => 'Muted',
                    'control_var_id'  => 'Control',
                    'shuffle_var_id'  => 'Control',
                    'repeat_var_id'  => 'Control',
                    'image_var_id'  => 'Control',
                    'title_var_id'    => 'Title',
                    'artist_var_id'   => 'Artist',
                    'album_var_id'    => 'Album',
                    'source_var_id'    => 'Album',
                    'sound_mode_var_id'    => 'Album',
                ]
            ],
            [
                'name'            => 'Apple TV',
                'manufacturer'    => 'Apple',
                'guid'            => '{DC733830-533B-43CD-98F5-23FC2E61287F}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_STREAMING_BOX,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    'power_var_id'    => 'Power',
                    'volume_var_id'   => 'Volume',
                    'mute_var_id'     => 'Muted',
                    'control_var_id'  => 'Control',
                    'shuffle_var_id'  => 'Control',
                    'repeat_var_id'  => 'Control',
                    'image_var_id'  => 'Control',
                    'title_var_id'    => 'Title',
                    'artist_var_id'   => 'Artist',
                    'album_var_id'    => 'Album',
                    'source_var_id'    => 'Album',
                    'sound_mode_var_id'    => 'Album',
                ]
            ],
            [
                'name'            => 'PlayStation 4',
                'manufacturer'    => 'Sony',
                'guid'            => '{DC733830-533B-43CD-98F5-23FC2E61287F}',
                'device_type'     => 'media_player',
                'device_sub_type' => Entity_Media_Player::DEVICE_CLASS_STREAMING_BOX,
                'features'        => [
                    Entity_Media_Player::FEATURE_ON_OFF,
                    Entity_Media_Player::FEATURE_VOLUME,
                    Entity_Media_Player::FEATURE_MUTE,
                    Entity_Media_Player::FEATURE_UNMUTE,
                    Entity_Media_Player::FEATURE_PLAY_PAUSE,
                    Entity_Media_Player::FEATURE_NEXT,
                    Entity_Media_Player::FEATURE_PREVIOUS,
                ],
                'category'        => 'media_player_mapping',
                'attributes' => [
                    'power_var_id'    => 'Power',
                    'volume_var_id'   => 'Volume',
                    'mute_var_id'     => 'Muted',
                    'control_var_id'  => 'Control',
                    'shuffle_var_id'  => 'Control',
                    'repeat_var_id'  => 'Control',
                    'image_var_id'  => 'Control',
                    'title_var_id'    => 'Title',
                    'artist_var_id'   => 'Artist',
                    'album_var_id'    => 'Album',
                    'source_var_id'    => 'Album',
                    'sound_mode_var_id'    => 'Album',
                ]
            ],
            [
                'name'            => 'Homematic IP Rollladen',
                'manufacturer'    => 'eQ-3',
                'guid'            => '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}',
                'device_type'     => 'cover',
                'device_sub_type' => Entity_Cover::DEVICE_CLASS_BLIND,
                'features'        => [
                    Entity_Cover::FEATURE_OPEN,
                    Entity_Cover::FEATURE_CLOSE,
                    Entity_Cover::FEATURE_STOP,
                    Entity_Cover::FEATURE_POSITION,
                ],
                'category'        => 'cover_mapping',
                'attributes' => [
                    Entity_Cover::ATTR_POSITION    => 'LEVEL',
                    Entity_Cover::ATTR_STATE   => 'LEVEL'
                ]
            ]
        ];
    }
}

/*
 * 📦 Instanz: Hue Sync Box
🆔 Instanz-ID: 30659
🔗 Modul GUID: {716FA5CE-2292-8EA5-78F9-8B245EFAF0A7}

🧩 Variable: Hintergrundbeleuchtung Video
  🔸 ID: 55661
  🔸 Ident: backlight_video
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: true
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Sync aktiv
  🔸 ID: 54255
  🔸 Ident: syncActive
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Intensität
  🔸 ID: 47679
  🔸 Ident: Intensity
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.Intensity
  🔸 Wert: 0
  🔹 Bereich: 0 – 3 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'subtil'
     ↳ Wert: 1, Anzeige: 'moderat'
     ↳ Wert: 2, Anzeige: 'hoch'
     ↳ Wert: 3, Anzeige: 'extrem'

🧩 Variable: Palette
  🔸 ID: 25105
  🔸 Ident: music_palette
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.Palette
  🔸 Wert: 1
  🔹 Bereich: 0 – 4 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'glücklich energetisch'
     ↳ Wert: 1, Anzeige: 'glücklich Ruhe'
     ↳ Wert: 2, Anzeige: 'melancholisch Ruhe'
     ↳ Wert: 3, Anzeige: 'melancholisch energetisch'
     ↳ Wert: 4, Anzeige: 'neutral'

🧩 Variable: Status
  🔸 ID: 30128
  🔸 Ident: State
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: HDMI Input 1 Name
  🔸 ID: 18129
  🔸 Ident: input1_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Denon 8500 HA"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: HDMI Input 2 Name
  🔸 ID: 51745
  🔸 Ident: input2_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Nintendo Switch"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Firmware
  🔸 ID: 17108
  🔸 Ident: firmwareVersion
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: LED Modus
  🔸 ID: 32830
  🔸 Ident: ledMode
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.LED_Mode
  🔸 Wert: 1
  🔹 Bereich: 0 – 2 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'aus'
     ↳ Wert: 1, Anzeige: 'regulär'
     ↳ Wert: 2, Anzeige: 'gedimmt'

🧩 Variable: CEC Powersave
  🔸 ID: 15463
  🔸 Ident: cecPowersave
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: true
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: USB Powersave
  🔸 ID: 37170
  🔸 Ident: usbPowersave
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: true
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: HDMI Eingang
  🔸 ID: 11045
  🔸 Ident: Input
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.Input
  🔸 Wert: 0
  🔹 Bereich: 0 – 3 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'HDMI 1'
     ↳ Wert: 1, Anzeige: 'HDMI 2'
     ↳ Wert: 2, Anzeige: 'HDMI 3'
     ↳ Wert: 3, Anzeige: 'HDMI 4'

🧩 Variable: Hintergrundbeleuchtung Game
  🔸 ID: 16859
  🔸 Ident: backlight_game
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: HDMI aktiv
  🔸 ID: 56193
  🔸 Ident: hdmiActive
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: ARC-Bypass
  🔸 ID: 38416
  🔸 Ident: arcBypassMode
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: HDMI Input 4 Name
  🔸 ID: 32685
  🔸 Ident: input4_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "PlayStation 4"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Modus
  🔸 ID: 11995
  🔸 Ident: Mode
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.Mode
  🔸 Wert: 1
  🔹 Bereich: 0 – 4 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Passthrough'
     ↳ Wert: 1, Anzeige: 'Powersave'
     ↳ Wert: 2, Anzeige: 'Video'
     ↳ Wert: 3, Anzeige: 'Musik'
     ↳ Wert: 4, Anzeige: 'Game'

🧩 Variable: Helligkeit
  🔸 ID: 58862
  🔸 Ident: Brightness
  🔸 Typ: Integer
  🔸 Profil: Hue.Sync.Brightness
  🔸 Wert: 117
  🔹 Bereich: 0 – 200 (Schritt: 1)

🧩 Variable: HDMI Input 3 Name
  🔸 ID: 38731
  🔸 Ident: input3_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Apple TV"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: LED Modus
  🔸 ID: 39521
  🔸 Ident: LEDMode
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: true
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

════════════════════════════════════════════════════════════

📦 Instanz: HEOS 5
🆔 Instanz-ID: 37837
🔗 Modul GUID: {68ED7CBB-76B7-4C24-07A2-61304D38CACD}

🧩 Variable: Dauer
  🔸 ID: 50385
  🔸 Ident: Duration
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Cover
  🔸 ID: 26488
  🔸 Ident: Cover
  🔸 Typ: String
  🔸 Profil: ~HTMLBox
  🔸 Wert: ""
  🔹 Bereich: 0 – 0 (Schritt: 0)

🧩 Variable: Shuffle
  🔸 ID: 22922
  🔸 Ident: Shuffle
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Position
  🔸 ID: 30364
  🔸 Ident: Position
  🔸 Typ: Integer
  🔸 Profil: HEOS.Position
  🔸 Wert: 0
  🔹 Bereich: 0 – 100 (Schritt: 1)

🧩 Variable: stummschalten
  🔸 ID: 19249
  🔸 Ident: Mute
  🔸 Typ: Boolean
  🔸 Profil: HEOS.Mute
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'stummschalten'
     ↳ Wert: 1, Anzeige: 'lautschalten'

🧩 Variable: Wiederholung
  🔸 ID: 17682
  🔸 Ident: Repeat
  🔸 Typ: Integer
  🔸 Profil: HEOS.Repeat
  🔸 Wert: 0
  🔹 Bereich: 0 – 2 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Wiederholung aus'
     ↳ Wert: 1, Anzeige: 'Alles wiederholen'
     ↳ Wert: 2, Anzeige: 'Einzeln wiederholen'

🧩 Variable: Titel
  🔸 ID: 18025
  🔸 Ident: Title
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Red Falcon"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Verbleibende Zeit
  🔸 ID: 17724
  🔸 Ident: RemainingTime
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Aktuelle Wiedergabeposition
  🔸 ID: 20516
  🔸 Ident: CurrentPosition
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Album
  🔸 ID: 36349
  🔸 Ident: Album
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Dep\u00c3\u00b8lar"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Lautstärke
  🔸 ID: 56180
  🔸 Ident: VolumeSlider
  🔸 Typ: Integer
  🔸 Profil: ~Intensity.100
  🔸 Wert: 26
  🔹 Bereich: 0 – 100 (Schritt: 1)

🧩 Variable: Wiedergabe
  🔸 ID: 34250
  🔸 Ident: Playback
  🔸 Typ: Integer
  🔸 Profil: ~PlaybackPreviousNext
  🔸 Wert: 1
  🔹 Bereich: 0 – 4 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Zurück'
     ↳ Wert: 1, Anzeige: 'Stop'
     ↳ Wert: 2, Anzeige: 'Play'
     ↳ Wert: 3, Anzeige: 'Pause'
     ↳ Wert: 4, Anzeige: 'Weiter'

🧩 Variable: Lautstärke
  🔸 ID: 35902
  🔸 Ident: Volume
  🔸 Typ: Integer
  🔸 Profil: HEOS.Volume
  🔸 Wert: 0
  🔹 Bereich: 0 – 2 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'lauter'
     ↳ Wert: 1, Anzeige: 'leiser'
     ↳ Wert: 2, Anzeige: 'stummschalten'

🧩 Variable: Künstler
  🔸 ID: 40028
  🔸 Ident: Artist
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Deorbiting"
  ⚠️ Kein Variablenprofil zugewiesen.

════════════════════════════════════════════════════════════

📦 Instanz: HomePod Rechts
🆔 Instanz-ID: 37643
🔗 Modul GUID: {D5C53262-AEEF-AA8A-9EC5-940E6B95A9A8}

🧩 Variable: Overview
  🔸 ID: 55745
  🔸 Ident: MediaPlayerOverview
  🔸 Typ: String
  🔸 Profil: ~HTMLBox
  🔸 Wert: ""
  🔹 Bereich: 0 – 0 (Schritt: 0)

🧩 Variable: Medien Künstler
  🔸 ID: 54611
  🔸 Ident: media_artist
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Lautstärke
  🔸 ID: 53696
  🔸 Ident: volume_level
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "0.17"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Medientyp
  🔸 ID: 48354
  🔸 Ident: media_content_type
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Titel
  🔸 ID: 25458
  🔸 Ident: media_title
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Medien Dauer
  🔸 ID: 14244
  🔸 Ident: media_duration
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Wiederholen
  🔸 ID: 18410
  🔸 Ident: repeat
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Medien Position Aktualisiert
  🔸 ID: 25534
  🔸 Ident: media_position_updated_at
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Friendly Name
  🔸 ID: 12049
  🔸 Ident: friendly_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "HomePod Rechts"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: App Name
  🔸 ID: 22042
  🔸 Ident: app_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Safari"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Zustand
  🔸 ID: 36130
  🔸 Ident: state
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "playing"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Unterstützte Funktionen
  🔸 ID: 41289
  🔸 Ident: supported_features
  🔸 Typ: Integer
  🔸 Profil: -
  🔸 Wert: 448439
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Steuerung
  🔸 ID: 57019
  🔸 Ident: control
  🔸 Typ: Integer
  🔸 Profil: MediaPlayer.Control
  🔸 Wert: 0
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Hoch'
     ↳ Wert: 1, Anzeige: 'Runter'
     ↳ Wert: 2, Anzeige: 'Rechts'
     ↳ Wert: 3, Anzeige: 'Links'
     ↳ Wert: 4, Anzeige: 'Enter'

🧩 Variable: App ID
  🔸 ID: 11199
  🔸 Ident: app_id
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Albumname
  🔸 ID: 27451
  🔸 Ident: media_album_name
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Starte App
  🔸 ID: 38208
  🔸 Ident: start_app
  🔸 Typ: Integer
  🔸 Profil: MediaPlayer.AppSelection
  🔸 Wert: 0
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Waipu TV'
     ↳ Wert: 1, Anzeige: 'Apple TV'
     ↳ Wert: 2, Anzeige: 'Disney Plus'
     ↳ Wert: 3, Anzeige: 'Netflix'
     ↳ Wert: 4, Anzeige: 'Prime Video'
     ↳ Wert: 5, Anzeige: 'ZDF'
     ↳ Wert: 6, Anzeige: 'ARD'
     ↳ Wert: 7, Anzeige: 'Spotify'

🧩 Variable: Restored
  🔸 ID: 25738
  🔸 Ident: restored
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "True"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Entity Bild
  🔸 ID: 59839
  🔸 Ident: entity_picture
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Power
  🔸 ID: 30706
  🔸 Ident: power
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Medien Position
  🔸 ID: 32819
  🔸 Ident: media_position
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: ""
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Mmedia position
  🔸 ID: 58591
  🔸 Ident: mmedia_position
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "0"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Shuffle
  🔸 ID: 40664
  🔸 Ident: shuffle
  🔸 Typ: Boolean
  🔸 Profil: ~Switch
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Aktion
  🔸 ID: 44734
  🔸 Ident: action
  🔸 Typ: Integer
  🔸 Profil: MediaPlayer.Action
  🔸 Wert: 0
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Rückwärts Spulen'
     ↳ Wert: 1, Anzeige: 'Zurück'
     ↳ Wert: 2, Anzeige: 'Play'
     ↳ Wert: 3, Anzeige: 'Pause'
     ↳ Wert: 4, Anzeige: 'Weiter'
     ↳ Wert: 5, Anzeige: 'Vorwärts Spulen'

════════════════════════════════════════════════════════════

📦 Instanz: Spotify
🆔 Instanz-ID: 13233
🔗 Modul GUID: {DCC40FC6-4447-AA1A-E3E5-B5F32DF81806}

🧩 Variable: Aktueller Song
  🔸 ID: 52356
  🔸 Ident: CurrentTrack
  🔸 Typ: String
  🔸 Profil: ~Song
  🔸 Wert: "Facing the Sun"
  🔹 Bereich: 0 – 0 (Schritt: 0)

🧩 Variable: Zufällige Wiedergabe
  🔸 ID: 37445
  🔸 Ident: Shuffle
  🔸 Typ: Boolean
  🔸 Profil: ~Shuffle
  🔸 Wert: false
  🔹 Bereich: 0 – 1 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: , Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'An'

🧩 Variable: Lautstärke
  🔸 ID: 57591
  🔸 Ident: Volume
  🔸 Typ: Integer
  🔸 Profil: ~Volume
  🔸 Wert: 29
  🔹 Bereich: 0 – 100 (Schritt: 1)

🧩 Variable: Dauer
  🔸 ID: 24120
  🔸 Ident: CurrentDuration
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "5:13"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Wiederholen
  🔸 ID: 13660
  🔸 Ident: Repeat
  🔸 Typ: Integer
  🔸 Profil: ~Repeat
  🔸 Wert: 0
  🔹 Bereich: 0 – 2 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Aus'
     ↳ Wert: 1, Anzeige: 'Kontext'
     ↳ Wert: 2, Anzeige: 'Lied'

🧩 Variable: Playlist
  🔸 ID: 33789
  🔸 Ident: CurrentPlaylist
  🔸 Typ: String
  🔸 Profil: ~Playlist
  🔸 Wert: "{\"current\":0,\"entries\":[{\"artist\":\"Fritz Kalkbrenner\",\"song\":\"Facing the Sun\",\"duration\":313,\"uri\":\"spotify:track:4Ei691AKbjiGscPcMUdA4s\"},{\"artist\":\"Le Roy, Jyll, Palisade\",\"song\":\"Steady As The Sea - Palisade Remix\",\"duration\":194,\"uri\":\"spotify:track:5NbEX6qiPA1m5pwa4ofuh6\"},{\"artist\":\"Chris Luno\",\"song\":\"See You Again\",\"duration\":250,\"uri\":\"spotify:track:0uUKvGRhXJybk8jzSvjFIm\"},{\"artist\":\"SOLECO, Arkangel\",\"song\":\"Desire\",\"duration\":220,\"uri\":\"spotify:track:3ud6u1lmceQ39CdV82yOPF\"},{\"artist\":\"Mats Westbroek, Jordin Post\",\"song\":\"Heart On The Line - Jordin Post Remix\",\"duration\":216,\"uri\":\"spotify:track:28pydyxL4SDbn5AWX5CQ61\"},{\"artist\":\"Ben B\\u00f6hmer, Lykke Li\",\"song\":\"Hiding\",\"duration\":222,\"uri\":\"spotify:track:0K5k3iEb2POh3xjhb536NG\"},{\"artist\":\"shiny things, Moise\",\"song\":\"hoyo\",\"duration\":226,\"uri\":\"spotify:track:0JT3o1tGkslq0gKAc6rgF7\"},{\"artist\":\"Panuma, Tim Hughes\",\"song\":\"Closing Hour\",\"duration\":171,\"uri\":\"spotify:track:5m8yfomGrRuslN7B3PfF3C\"},{\"artist\":\"Winter Kid, Kaphy, Kevin Kairouz\",\"song\":\"Don't Be Afraid\",\"duration\":257,\"uri\":\"spotify:track:5iEIh05BXGuwcurLOpltS0\"},{\"artist\":\"Banaati, Max Bl\\u00fccher, KARTINI\",\"song\":\"Lose Control\",\"duration\":240,\"uri\":\"spotify:track:7dZDTCKoEYHjW1lHio6gne\"},{\"artist\":\"Fedders, Barmuda\",\"song\":\"Siren\",\"duration\":189,\"uri\":\"spotify:track:1dasrNWDzxdtx9BonLMNsV\"},{\"artist\":\"AY.ATA\",\"song\":\"Deruni\",\"duration\":264,\"uri\":\"spotify:track:77Igob2knPaF1waGWEkU0E\"},{\"artist\":\"Andrew Long, Koppo, Kazmyn\",\"song\":\"Bloom\",\"duration\":225,\"uri\":\"spotify:track:2jQtN8KFkVFqIkvexKWZDQ\"},{\"artist\":\"OKASSUS, Hexlogic\",\"song\":\"Gorongosa\",\"duration\":247,\"uri\":\"spotify:track:6YUDQr2aoeFJrf9VkhJeAt\"},{\"artist\":\"Blank Page, XIX99, Anita Tatlow\",\"song\":\"The Long Wait\",\"duration\":263,\"uri\":\"spotify:track:2vN0teJ9L3WVJHkWEsEGV6\"},{\"artist\":\"Shallou\",\"song\":\"Habitat\",\"duration\":210,\"uri\":\"spotify:track:7l12s69k9iQNrBfpuwWGgo\"},{\"artist\":\"Alex Breitling\",\"song\":\"Drift Away\",\"duration\":228,\"uri\":\"spotify:track:3fc76LcSqH8jYX56DHlZpz\"},{\"artist\":\"nineveh., shiny things\",\"song\":\"here before\",\"duration\":275,\"uri\":\"spotify:track:2v5RzOaPU0pCFqh7Zc0vZN\"},{\"artist\":\"P.A.V\",\"song\":\"Love\",\"duration\":173,\"uri\":\"spotify:track:3fSdGxtm9tod3B7LGMcc1P\"},{\"artist\":\"Tycho, Saint Sinner, Satin Jackets\",\"song\":\"Japan - Satin Jackets Remix\",\"duration\":237,\"uri\":\"spotify:track:4riBORG5X5kvQThwODPDDh\"},{\"artist\":\"Jope, Alex Pich\",\"song\":\"Verdant - Alex Pich Remix\",\"duration\":269,\"uri\":\"spotify:track:51KL5PDfvl8mVsGWFZInTA\"},{\"artist\":\"R\\u00dcF\\u00dcS DU SOL\",\"song\":\"You Were Right\",\"duration\":239,\"uri\":\"spotify:track:5HGxLtYxTriF7mMiriSpaz\"},{\"artist\":\"James Lacey, ODBLU\",\"song\":\"Space + Time\",\"duration\":155,\"uri\":\"spotify:track:4uOIIMZEldmHsFatxB7Qjw\"},{\"artist\":\"TOMB, UOAK\",\"song\":\"Mariner - UOAK Remix\",\"duration\":259,\"uri\":\"spotify:track:6JOyeBeA7S1mHq8Kr1h1fu\"},{\"artist\":\"YOTTO, Eli & Fur\",\"song\":\"Somebody To Love\",\"duration\":230,\"uri\":\"spotify:track:6SPWJ9hUFMA69MaGwUsRJi\"},{\"artist\":\"Boycott\",\"song\":\"Talk To Me\",\"duration\":269,\"uri\":\"spotify:track:073fplZzq5YZZ7E9GnwYSG\"},{\"artist\":\"Nick Raff\",\"song\":\"Open Your Eyes\",\"duration\":162,\"uri\":\"spotify:track:3Ebt1WALSn914WYdfCKV4B\"},{\"artist\":\"Monolink, Ben B\\u00f6hmer\",\"song\":\"Father Ocean - Ben B\\u00f6hmer Remix Edit\",\"duration\":318,\"uri\":\"spotify:track:4oWDaJpusSH1lqIQQkEHsS\"},{\"artist\":\"Alex Cruz, ROBINS\",\"song\":\"My Way Home\",\"duration\":229,\"uri\":\"spotify:track:5WqIMi9BQgfKlgFEOLFSQr\"},{\"artist\":\"RAZZ, EFA, Moody Violet\",\"song\":\"Stay the Night\",\"duration\":176,\"uri\":\"spotify:track:0PbUTx3YxxEMYPoY58ZERn\"},{\"artist\":\"Chris Malinchak\",\"song\":\"At Eighty-First - Original Mix\",\"duration\":226,\"uri\":\"spotify:track:5FsOdZFa2YOEQVCSGIZqA4\"},{\"artist\":\"Lara Nord, Fagin, Soul Engineers\",\"song\":\"When You're High - Soul Engineers Remix\",\"duration\":208,\"uri\":\"spotify:track:5fvdwxUsl0bWaIfkMEWo25\"},{\"artist\":\"AY.ATA\",\"song\":\"Something About You\",\"duration\":214,\"uri\":\"spotify:track:0R2w4r7x9uklYCqHy3GiLW\"},{\"artist\":\"UOAK, Ceci, Bound to Divide\",\"song\":\"Scent of Wood - Bound to Divide Remix\",\"duration\":208,\"uri\":\"spotify:track:7hB5XhraRlHeAE5ONIcSWY\"},{\"artist\":\"Exit Coda, Luis Kuper\",\"song\":\"Ember (Luis Kuper Remix)\",\"duration\":180,\"uri\":\"spotify:track:4YKquPFjsGJPwjYBkWH6F0\"},{\"artist\":\"Elderbrook, Amtrac\",\"song\":\"I'll Be Around\",\"duration\":228,\"uri\":\"spotify:track:32v4XcJEaB3c3NbETfJ3uV\"},{\"artist\":\"Freyer, Dean Andrew\",\"song\":\"Grief\",\"duration\":182,\"uri\":\"spotify:track:6Ns27TYYC0uO1uASyShNVh\"},{\"artist\":\"CallumCantSleep\",\"song\":\"Winds Of Sargasso\",\"duration\":217,\"uri\":\"spotify:track:6klt6HOyBzxZ84FIUBU1FV\"},{\"artist\":\"Soul Engineers\",\"song\":\"Sandprints\",\"duration\":219,\"uri\":\"spotify:track:6JroxOiisAgYHUwpp4lrtg\"},{\"artist\":\"Heard Right, Tailor\",\"song\":\"Your Company\",\"duration\":231,\"uri\":\"spotify:track:2Plx4pFdleNiL0RtnKbE7x\"},{\"artist\":\"Phil Odd\",\"song\":\"Into The Water\",\"duration\":241,\"uri\":\"spotify:track:56AzDKiwv1qQePFXFiXwJN\"},{\"artist\":\"Brendel, Ceci\",\"song\":\"Radiant\",\"duration\":227,\"uri\":\"spotify:track:5MjeL8l8xdhjtM6uEpafuU\"},{\"artist\":\"Vowed, Rolipso, maybealice\",\"song\":\"Keep You\",\"duration\":146,\"uri\":\"spotify:track:12MuNOUBqDSd52jzDIjrLJ\"},{\"artist\":\"dwelyr\",\"song\":\"Last Snow\",\"duration\":260,\"uri\":\"spotify:track:29wngDHVdqnqRQ4Nne0ebr\"},{\"artist\":\"Iskarelyn\",\"song\":\"Briar Hill\",\"duration\":206,\"uri\":\"spotify:track:5VLNZzt8hcy02OzGcbW2rK\"},{\"artist\":\"Matt Leger, Rafa'EL, TOMB\",\"song\":\"Waystone - TOMB Remix\",\"duration\":161,\"uri\":\"spotify:track:0aq8eCONFNJLxJoQMVYqjb\"},{\"artist\":\"Panuma, Nina Carr\",\"song\":\"Right On Time\",\"duration\":206,\"uri\":\"spotify:track:5OeQIoGuZXu0ufLlYsILza\"},{\"artist\":\"TOMB\",\"song\":\"Kingfisher\",\"duration\":170,\"uri\":\"spotify:track:70CB8jQljPe4C8UQBvJnGA\"},{\"artist\":\"Mats Westbroek\",\"song\":\"It's Always the Same\",\"duration\":220,\"uri\":\"spotify:track:5CrKN4Bv7k47PTC2FMas8Z\"},{\"artist\":\"Illumia, Alex Pich, Anita Tatlow\",\"song\":\"Everything Yours\",\"duration\":240,\"uri\":\"spotify:track:38QOtys4ZqbN0mS8G4aQBX\"},{\"artist\":\"Toutounji\",\"song\":\"Horizon\",\"duration\":200,\"uri\":\"spotify:track:3KnGfj7bcLiPIEtnHsM1zI\"},{\"artist\":\"Chris Malinchak\",\"song\":\"So Good To Me - Radio Edit\",\"duration\":158,\"uri\":\"spotify:track:7u0lV6ZS6IzqpWt7GoJEvg\"},{\"artist\":\"Banaati, Run Rivers\",\"song\":\"It All Comes Down To You\",\"duration\":226,\"uri\":\"spotify:track:7cFZNBoDW96EHR4NazEhDv\"},{\"artist\":\"Nora En Pure\",\"song\":\"Come With Me - Radio Mix\",\"duration\":173,\"uri\":\"spotify:track:1Ht4NJdY8adMsW540P5vG0\"},{\"artist\":\"Blancwater, Xerxes-K\",\"song\":\"Over and Over\",\"duration\":193,\"uri\":\"spotify:track:52zPhJxzQvA7bt4bvo1EJP\"},{\"artist\":\"Iskarelyn\",\"song\":\"Like That\",\"duration\":202,\"uri\":\"spotify:track:7o7KcHo8swUwhipVEnRxS5\"},{\"artist\":\"Poli-Poli\",\"song\":\"What About\",\"duration\":259,\"uri\":\"spotify:track:00wqaxOZTNqynWQfvUxeTH\"},{\"artist\":\"Slow Ted, Phil Odd, TMPST\",\"song\":\"Closer\",\"duration\":218,\"uri\":\"spotify:track:4K6j4py6eaG1iBppZQ2dS5\"},{\"artist\":\"TOMB\",\"song\":\"Innerspace\",\"duration\":206,\"uri\":\"spotify:track:67wkHOYVbkX376dwcjJQ4S\"},{\"artist\":\"UOAK, Ceci, Matt Leger\",\"song\":\"Fly Home\",\"duration\":216,\"uri\":\"spotify:track:56uvUDBTcVXAlkIdcEG4Ec\"},{\"artist\":\"Kaz Benson\",\"song\":\"Alone\",\"duration\":174,\"uri\":\"spotify:track:6w8iKYAXb6ZqjBhm7EdYBZ\"},{\"artist\":\"Alex Breitling, Golowko\",\"song\":\"Forever\",\"duration\":263,\"uri\":\"spotify:track:4RHFfNB4qNWrePfzFmq4sI\"},{\"artist\":\"Bound to Divide\",\"song\":\"New Horizons\",\"duration\":253,\"uri\":\"spotify:track:0hCLeP61XUDX1UsX19UyIT\"},{\"artist\":\"Stendahl\",\"song\":\"The Wilt\",\"duration\":222,\"uri\":\"spotify:track:3YVnFXE4hpYMMWxCp3677f\"},{\"artist\":\"LAR, Chris Savor\",\"song\":\"Be So Cold\",\"duration\":196,\"uri\":\"spotify:track:1lTyq9RWS2Zl4kjZfQJLpR\"},{\"artist\":\"Toutounji\",\"song\":\"See The Light\",\"duration\":202,\"uri\":\"spotify:track:19LsBBqs17xYOBJ6uaBgx4\"},{\"artist\":\"Blank Page, Dan Kol\",\"song\":\"Reach Out To Me\",\"duration\":226,\"uri\":\"spotify:track:0wEX42qpVmCcLcZTp0zqzy\"},{\"artist\":\"Elliot Vast\",\"song\":\"Keep Coming Back\",\"duration\":198,\"uri\":\"spotify:track:6HSYNEjRIwP6vZ96w9pvSk\"},{\"artist\":\"RLSA, TOMB\",\"song\":\"Prism\",\"duration\":164,\"uri\":\"spotify:track:1pYuaM5nwS0ofwdGS5djTa\"},{\"artist\":\"Motry\",\"song\":\"Apart\",\"duration\":210,\"uri\":\"spotify:track:2zJtuEOs2or23S15AHOUtq\"},{\"artist\":\"San Mateo Drive\",\"song\":\"Awoken\",\"duration\":176,\"uri\":\"spotify:track:2D5Ocww5PsqzRcyeUUWCmL\"},{\"artist\":\"Sander W., Samuel Miller\",\"song\":\"You & Me\",\"duration\":148,\"uri\":\"spotify:track:4uf8L5rd9P03zMtPqkxgcC\"},{\"artist\":\"Cosmaks, mia coolpa\",\"song\":\"Deeper\",\"duration\":218,\"uri\":\"spotify:track:4hwdDVaM6bx7w2sUuHkqoh\"},{\"artist\":\"Alex Breitling, Golowko\",\"song\":\"The Breeze\",\"duration\":238,\"uri\":\"spotify:track:2LScaKsuBC2ZczEuJr2iR4\"},{\"artist\":\"Wilde, ANY EXIT\",\"song\":\"Here & Now\",\"duration\":179,\"uri\":\"spotify:track:3tdbDNFnV0IE6gNV56elRk\"},{\"artist\":\"Kaz Benson, Matt Leger\",\"song\":\"All That I Want - Matt Leger Remix\",\"duration\":209,\"uri\":\"spotify:track:2qc17uDP4VXcfZdo1sFiWo\"},{\"artist\":\"Damaui\",\"song\":\"When You Want It\",\"duration\":160,\"uri\":\"spotify:track:1Nq07ya6bIsWvRRR9nXEAz\"},{\"artist\":\"Jope, NOWAY\",\"song\":\"Wide Awake\",\"duration\":236,\"uri\":\"spotify:track:0YUsOPqMjVOmDwwpfTCWeb\"},{\"artist\":\"UOAK, CallumCantSleep, Jack Lazarus\",\"song\":\"Alouma - Jack Lazarus Remix\",\"duration\":255,\"uri\":\"spotify:track:1dq2EpyQWJpeVXdZzIlGOf\"},{\"artist\":\"Le P\",\"song\":\"Say It\",\"duration\":145,\"uri\":\"spotify:track:0z8I570Roif1IV6KXRDKOL\"},{\"artist\":\"UOAK, Jope\",\"song\":\"Can You See\",\"duration\":222,\"uri\":\"spotify:track:37p0ugAfXrOeqJIgu1i7it\"},{\"artist\":\"ARIV3\",\"song\":\"This Is Love\",\"duration\":194,\"uri\":\"spotify:track:0Oaeyerz8fSgvXXAPiez5B\"},{\"artist\":\"RAINE, LAR\",\"song\":\"Who You Are\",\"duration\":207,\"uri\":\"spotify:track:0jWR88X5SttlBKaYD1CX3Y\"},{\"artist\":\"Marc Wiese\",\"song\":\"Heaven\",\"duration\":226,\"uri\":\"spotify:track:3qKUB6VHHMFHQNiYqiQbS7\"},{\"artist\":\"Tommy Baynen, Ross Newhouse\",\"song\":\"Time and Space\",\"duration\":263,\"uri\":\"spotify:track:6kj1DFfuN91UaXVnr8fXsW\"},{\"artist\":\"Alex Pich\",\"song\":\"Apollo\",\"duration\":223,\"uri\":\"spotify:track:43BGpujP1LrKTp5QYlGtBO\"},{\"artist\":\"LAR, Banaati\",\"song\":\"Run With Me\",\"duration\":242,\"uri\":\"spotify:track:6kFWUiay3bs876Vyg7dlan\"},{\"artist\":\"Francisco (PT), Paul Hazendonk, Return To Saturn\",\"song\":\"Spacetrip - Paul Hazendonk & Return To Saturn Remix\",\"duration\":193,\"uri\":\"spotify:track:26JFI5muzey6G7ddFNLFbX\"},{\"artist\":\"Bound to Divide, imallryt\",\"song\":\"Hope\",\"duration\":240,\"uri\":\"spotify:track:14CIfSKcEDtvZlMISR6D8W\"},{\"artist\":\"Kaz Benson\",\"song\":\"Dream\",\"duration\":249,\"uri\":\"spotify:track:5Puo4oMXuFavsdRKtFRWLa\"},{\"artist\":\"UOAK, Lara Nord, Ceci\",\"song\":\"Love or Lust\",\"duration\":200,\"uri\":\"spotify:track:23j6H6ro9ROzREhMDhcRbM\"},{\"artist\":\"Cosmaks, mia coolpa\",\"song\":\"Blow My Mind\",\"duration\":234,\"uri\":\"spotify:track:1Sm2cvqgIoEtBAgbpzZDlh\"},{\"artist\":\"Deeparture, Noana, WLHELMINA\",\"song\":\"Island\",\"duration\":216,\"uri\":\"spotify:track:36Cr3mhAWqdyzdLDNQH68A\"},{\"artist\":\"Naws, Milesy, WLDFLOW3R\",\"song\":\"Horizon\",\"duration\":188,\"uri\":\"spotify:track:0lhioSeo3MhnoaRZCvn0xO\"},{\"artist\":\"LAR, MXV\",\"song\":\"On My Own\",\"duration\":222,\"uri\":\"spotify:track:5XXkQcejC0EGsqWpKedqdI\"},{\"artist\":\"UOAK, Jope, Soul Engineers\",\"song\":\"Can You See (Soul Engineers Remix)\",\"duration\":227,\"uri\":\"spotify:track:708VRGurrcmN8ZBBTatr7l\"},{\"artist\":\"Blu Attic, Jujh\",\"song\":\"Departure\",\"duration\":225,\"uri\":\"spotify:track:3kOnfwdMyw9mOuYUSOFGR0\"},{\"artist\":\"Jake Kaiser\",\"song\":\"Flatirons\",\"duration\":198,\"uri\":\"spotify:track:7oEfdvY7CZHpubp1Otcd8h\"},{\"artist\":\"AY.ATA\",\"song\":\"The Deepest Truth\",\"duration\":286,\"uri\":\"spotify:track:1A5HM0vaULLtxS1OZqPHLH\"},{\"artist\":\"Illumia\",\"song\":\"Volga\",\"duration\":221,\"uri\":\"spotify:track:3B5Qul8KrGlwSg1XZZR2mj\"}]}"
  🔹 Bereich: 0 – 0 (Schritt: 0)

🧩 Variable: Aktuelles Album
  🔸 ID: 17129
  🔸 Ident: CurrentAlbum
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "Here Today Gone Tomorrow"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Aktion
  🔸 ID: 10824
  🔸 Ident: Action
  🔸 Typ: Integer
  🔸 Profil: ~PlaybackPreviousNext
  🔸 Wert: 3
  🔹 Bereich: 0 – 4 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 0, Anzeige: 'Zurück'
     ↳ Wert: 1, Anzeige: 'Stop'
     ↳ Wert: 2, Anzeige: 'Play'
     ↳ Wert: 3, Anzeige: 'Pause'
     ↳ Wert: 4, Anzeige: 'Weiter'

🧩 Variable: Position
  🔸 ID: 51247
  🔸 Ident: CurrentPosition
  🔸 Typ: String
  🔸 Profil: -
  🔸 Wert: "0:25"
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Fortschritt
  🔸 ID: 34386
  🔸 Ident: CurrentProgress
  🔸 Typ: Float
  🔸 Profil: ~Progress
  🔸 Wert: 8.101277955271566
  🔹 Bereich: 0 – 100 (Schritt: 0.1)

🧩 Variable: Favorit
  🔸 ID: 42251
  🔸 Ident: Favorite
  🔸 Typ: String
  🔸 Profil: Spotify.Favorites.13233
  🔸 Wert: "spotify:playlist:57rLc4B0AUS2yKNwP9IMUr"
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: spotify:playlist:4kdV8G1c8FNelUUzlN7arJ, Anzeige: 'Playlist: Cycling 2023'
     ↳ Wert: spotify:playlist:57rLc4B0AUS2yKNwP9IMUr, Anzeige: 'Playlist: Electronic Chill & Deep House'
     ↳ Wert: spotify:playlist:09ruLsOLgwqtFGuqDASUA6, Anzeige: 'Playlist: Enya Essentials'

🧩 Variable: Aktueller Künstler
  🔸 ID: 43549
  🔸 Ident: CurrentArtist
  🔸 Typ: String
  🔸 Profil: ~Artist
  🔸 Wert: "Fritz Kalkbrenner"
  🔹 Bereich: 0 – 0 (Schritt: 0)

🧩 Variable: Gerät
  🔸 ID: 45428
  🔸 Ident: Device
  🔸 Typ: String
  🔸 Profil: Spotify.Devices
  🔸 Wert: "b3faf78a-f913-442c-bb86-31509f583206_amzn_1"
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: 41cf3fb06b72e2e3e02a92a028c8afa37fe6a523, Anzeige: 'HEOS 5'
     ↳ Wert: 859e3229-cffb-40f6-bed7-e7183214d01d_amzn_1, Anzeige: 'Bad Echo Dot'
     ↳ Wert: b3faf78a-f913-442c-bb86-31509f583206_amzn_1, Anzeige: 'Echo Show Büro'
     ↳ Wert: 350ece1b-4229-4cc2-8d04-d9b07b7263c8_amzn_1, Anzeige: 'Echo Show Schlafraum'

════════════════════════════════════════════════════════════

📦 Instanz: Rollladen Schlafzimmer Links
🆔 Instanz-ID: 10747
🔗 Modul GUID: {EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}

🧩 Variable: Steuerung
  🔸 ID: 42754
  🔸 Ident: CONTROL
  🔸 Typ: Integer
  🔸 Profil: BlindControl.HM
  🔸 Wert: 0
  🔹 Bereich: 0 – 0 (Schritt: 0)
  🔹 Assoziationen:
     ↳ Wert: -1, Anzeige: 'Ab'
     ↳ Wert: 0, Anzeige: 'Stop'
     ↳ Wert: 1, Anzeige: 'Auf'

🧩 Variable: INHIBIT
  🔸 ID: 54888
  🔸 Ident: INHIBIT
  🔸 Typ: Boolean
  🔸 Profil: -
  🔸 Wert: false
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: Position
  🔸 ID: 21627
  🔸 Ident: LEVEL
  🔸 Typ: Float
  🔸 Profil: Homematic.Shutter.Reversed
  🔸 Wert: 1
  🔹 Bereich: 0 – 1 (Schritt: 0.05)

🧩 Variable: DIRECTION
  🔸 ID: 36575
  🔸 Ident: DIRECTION
  🔸 Typ: Integer
  🔸 Profil: -
  🔸 Wert: 0
  ⚠️ Kein Variablenprofil zugewiesen.

🧩 Variable: WORKING
  🔸 ID: 43977
  🔸 Ident: WORKING
  🔸 Typ: Boolean
  🔸 Profil: -
  🔸 Wert: false
  ⚠️ Kein Variablenprofil zugewiesen.

════════════════════════════════════════════════════════════
 */
