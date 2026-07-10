<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** ICS Parser */
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

use ICal\ICal;

/**
 * CLASS Abfall_IO
 */
class Abfall_IO extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use FormatHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    /** @var string Service Provider */
    private const SERVICE_PROVIDER = 'abpio';

    /** @var string Service User Agent */
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';

    /** @var string Service Form Key */
    private const SERVICE_MODUSKEY = 'd6c5855a62cf32a4dadbc2831f0f295f';

    /** @var string Service Base Url */
    private const SERVICE_BASEURL = 'https://api.abfall.io/';
    // https://api.abfallplus.io/graphql

    // -------------------------------------------------------------------------
    // IO Keys
    // -------------------------------------------------------------------------
    /** @var string Action Key */
    private const IO_ACTION = 'action';

    /** @var string Client Key */
    private const IO_CLIENT = 'key';

    /** @var string Names Key */
    private const IO_NAMES = 'names';

    /** @var string Place Key */
    private const IO_PLACE = 'f_id_kommune';

    /** @var string District Key */
    private const IO_DISTRICT = 'f_id_bezirk';

    /** @var string Street Key */
    private const IO_STREET = 'f_id_strasse';

    /** @var string Addon Key */
    private const IO_ADDON = 'f_id_strasse_hnr';

    /** @var string Fraction Key */
    private const IO_FRACTIONS = 'f_abfallarten';

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /** @var string Action State Init */
    private const ACTION_CLIENT = 'init';

    /** @var string Action State Kommune/Place */
    private const ACTION_PLACE = 'auswahl_kommune_set';

    /** @var string Action State District */
    private const ACTION_DISTRICT = 'auswahl_bezirk_set';

    /** @var string Action State Query */
    private const ACTION_QUERY = 'auswahl_strasse_qry_set';

    /** @var string Action State Street */
    private const ACTION_STREET = 'auswahl_strasse_set';

    /** @var string Action State Addon */
    private const ACTION_ADDON = 'auswahl_hnr_set';

    /** @var string Action State Fractions */
    private const ACTION_FRACTIONS = 'auswahl_fraktionen_set';

    /** @var string Action State Export */
    private const ACTION_EXPORT = 'export_';

    // -------------------------------------------------------------------------
    // Form Elements
    // -------------------------------------------------------------------------

    /** @var int Lement Postion Provi */
    private const ELEM_PROVI = 1;

    /** @var int Panael Element MyMDE */
    private const ELEM_WASTE = 2;

    /** @var int Panael Element Visualisation */
    private const ELEM_VISU = 3;

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        $this->RegisterPropertyString('serviceCountry', 'de');

        // Waste Management
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyString('placeID', 'null');
        $this->RegisterPropertyString('districtID', 'null');
        $this->RegisterPropertyString('streetID', 'null');
        $this->RegisterPropertyString('addonID', 'null');
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }

        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        $this->RegisterPropertyInteger('settingsAccentToday', -1);
        $this->RegisterPropertyInteger('settingsTonneAlpha', 100);
        $this->RegisterPropertyInteger('settingsAccentTomorrow', -1);
        $this->RegisterPropertyBoolean('settingsTonneColor', true);
        $this->RegisterPropertyBoolean('settingsHtmlBox', true);
        $this->RegisterPropertyBoolean('settingsLookAhead', false);
        $this->RegisterPropertyString('settingsLookTime', '{"hour":12,"minute":0,"second":0}');

        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyBoolean('settingsStartsWith', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        $this->RegisterPropertyString('settingsFormat', 'ics');

        // Attributes for dynamic configuration forms (> v3.0)
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));

        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'ABPIO_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'ABPIO_LookAhead(' . $this->InstanceID . ');');

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(0);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Basic Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][1]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);

        // Settings
        $activate = $this->ReadPropertyBoolean('settingsActivate');

        // Service Values
        $country = $this->ReadPropertyString('serviceCountry');

        // IO Values
        $cId = $this->ReadPropertyString('clientID');
        $pId = $this->ReadPropertyString('placeID');
        $dId = $this->ReadPropertyString('districtID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');

        // Debug output
        $this->LogDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', districtId=' . $dId . ', streetId=' . $sId . ', addonId=' . $aId); // . ', fractIds=' . $fId);

        // Service Provider
        $form['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $form['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);

        // Waste Management
        $form['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];

        // go throw thw whole way
        $next = true;

        // Build io array
        $io = $this->PrepareIO();
        $options = [];

        // Client
        if ($cId != 'null') {
            $io[self::IO_CLIENT] = $cId;
            $options = $this->ExecuteAction($io);
            if (($options == null) && ($io[self::IO_ACTION] != self::ACTION_QUERY)) {
                $this->LogDebug(__FUNCTION__, 'clietID ok, but no Options!');
                $next = false;
            }
        } else {
            $this->LogDebug(__FUNCTION__, __LINE__);
            $next = false;
        }

        // Place
        if ($next) {
            // Streets?
            if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
                $options = $this->ExecuteAction($io);
                $this->LogDebug(__FUNCTION__, $io);
            }
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_PLACE) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($pId != 'null') {
                    $io[self::IO_PLACE] = $pId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->LogDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $pId];
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = false;
            }
        }

        // District
        if ($next) {
            // Streets?
            if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
                $options = $this->ExecuteAction($io);
                $this->LogDebug(__FUNCTION__, $io);
            }
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_DISTRICT) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][1]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][1]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($dId != 'null') {
                    $io[self::IO_DISTRICT] = $dId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if (($options == null) && ($io[self::IO_ACTION] != self::ACTION_QUERY)) {
                        $this->LogDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $dId];
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][1]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][1]['visible'] = false;
            }
        }

        // Street
        if ($next) {
            // Streets?
            if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
                $options = $this->ExecuteAction($io);
                $this->LogDebug(__FUNCTION__, $io);
            }
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_STREET) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($sId != 'null') {
                    $io[self::IO_STREET] = $sId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->LogDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $sId];
                $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['visible'] = false;
            }
        }

        // Addon
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($aId != 'null') {
                    $io[self::IO_ADDON] = $aId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->LogDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $aId];
                $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = false;
            }
        }

        // Fractions
        if ($next) {
            //$io[self::IO_FRACTIONS] = $fId;
            if ($io[self::IO_ACTION] == self::ACTION_FRACTIONS) {
                if ($options != null) {
                    // Label
                    $form['elements'][self::ELEM_WASTE]['items'][3]['visible'] = true;
                    $i = 1;
                    foreach ($options as $fract) {
                        $form['elements'][self::ELEM_WASTE]['items'][$i + 3]['caption'] = $fract['caption'];
                        $form['elements'][self::ELEM_WASTE]['items'][$i + 3]['visible'] = true;
                        $i++;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $this->LogDebug(__FUNCTION__, __LINE__);
                $next = false;
            }
        }

        // Write IO array
        $this->WriteAttributeString('io', serialize($io));

        // Debug output
        $this->LogDebug(__FUNCTION__, $io);

        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->LogDebug(__FUNCTION__, 'Translate Waste Visu');
            $form['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }

        // Return Form
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $cId = $this->ReadPropertyString('clientID');
        $pId = $this->ReadPropertyString('placeID');
        $dId = $this->ReadPropertyString('districtID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', districtId=' . $dId . ', streetId=' . $sId . ', addonId=' . $aId);

        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);

        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu && $htmlbox);

        // Set status
        if ($cId == 'null') {
            $status = 201;
        } elseif (($pId == 'null') && ($sId == 'null') && ($aId == 'null')) {
            $status = 202;
        } else {
            $status = 102;
        }

        // All okay
        if ($status == 102) {
            // Update visualization
            $this->SetVisualizationType($tilevisu ? 1 : 0);
            if ($tilevisu) {
                $this->UpdateVisualizationValue(json_encode([
                    'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
                    'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
                    'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
                    'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor')
                ]));
            }
            $this->CreateVariables();
            if ($activate == true) {
                // Time neu berechnen
                $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
                $this->LogDebug(__FUNCTION__, 'Update Timer aktiviert!');
                if ($loakahead & $tilevisu) {
                    $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
                    if (($time['hour'] == 0) && ($time['minute'] <= 30)) {
                        $this->LogDebug(__FUNCTION__, 'LookAhead Time zu niedrieg!');
                    } else {
                        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);
                    }
                }
            } else {
                $status = 104;
            }
        }

        //$this->SetBuffer('ics_csv', '');
        $this->SetStatus($status);
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        eval('$this->' . $ident . '(\'' . $value . '\');');
    }

    /**
     * Fix waste name.
     *
     * @param string $from Old waste name.
     * @param string $to New waste name.
     *
     * @return void
     */
    public function FixWasteName(string $from, string $to): void
    {
        $io = unserialize($this->ReadAttributeString('io'));
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            if ($name === $from) {
                $io[self::IO_NAMES][$ident] = $to;
            }
        }
        $this->LogDebug(__FUNCTION__, $io);
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * ABPIO_LookAhead($id);
     *
     * @return void
     */
    public function LookAhead(): void
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->LogDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }

        // Check tile visu
        if ($this->ReadPropertyBoolean('settingsTileVisu') === false) {
            $this->LogDebug(__FUNCTION__, 'WARNING: TileVisu is not active.');
            return;
        }

        // rebuild informations
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);

        // fractions convert to name => ident
        $i = 1;
        $waste = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i++);
            if ($enabled) {
                $date = $this->GetValue($ident);
                $waste[$name] = ['ident' => $ident, 'date' => $date];
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);

        // update tile widget
        $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        $this->BuildWidget($waste, $list, true);

        // Set Timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);

        // send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * ABPIO_Update($id);
     *
     * @return void
     */
    public function Update(): void
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->LogDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        $fmt = $this->ReadPropertyString('settingsFormat');
        $this->LogDebug(__FUNCTION__, 'Format: ' . $fmt);
        $ssw = $this->ReadPropertyBoolean('settingsStartsWith');
        $this->LogDebug(__FUNCTION__, 'StartsWith: ' . $ssw);

        // Extract Token
        $token = $this->ExecuteInit($io[self::IO_CLIENT]);
        if ($token == null) {
            $this->LogDebug(__FUNCTION__, 'Token is null!');
            return;
        }
        // Build POST Data
        $request = null;
        $params = [];

        // Add Token
        $params[] = $token;

        // Add Form data
        foreach ($io as $key => $entry) {
            if ($this->StartsWith($key, 'f_') && strlen($entry)) {
                $params[] = $key . '=' . $entry;
            }
        }

        // Add Abfallarten
        $i = 0;
        foreach ($io[self::IO_NAMES] as $key => $name) {
            $params[] = 'f_id_abfalltyp_' . $i++ . '=' . $key;
        }

        // Add Abfallarten Max Ids
        $params[] = 'f_abfallarten_index_max=' . $i;

        // Build Timespan
        $date = date('Y');
        $params[] = 'f_zeitraum=' . $date . '0101-' . ($date + 1) . '1231';

        // Build Request
        $request = implode('&', $params);
        $this->LogDebug(__FUNCTION__, $request);

        // Build URL data
        $url = $this->BuildURL($io['key'], self::ACTION_EXPORT . $fmt);

        // Send Export request
        $res = $this->ServiceRequest($url, $request);

        // Store new ICS/CSV data
        if ($res !== false) {
            //$this->SetBuffer('ics_csv', $res);
            //$this->LogDebug(__FUNCTION__, $res);
        } else {
            $this->LogMessage('Update: Service Request failed!');
            $this->LogDebug(__FUNCTION__, 'Service Request failed!');
            return;
        }

        // fractions convert to name => ident
        $i = 1;
        $waste = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i++);
            if ($enabled) {
                $waste[$name] = ['ident' => $ident, 'date' => ''];
            }
        }

        // ICS format
        if ($fmt == 'ics') {
            try {
                $ical = new ICal(false, [
                    'defaultSpan'                 => 2,     // Default value
                    'defaultTimeZone'             => 'UTC',
                    'defaultWeekStart'            => 'MO',  // Default value
                    'disableCharacterReplacement' => false, // Default value
                    'filterDaysAfter'             => null,  // Default value
                    'filterDaysBefore'            => null,  // Default value
                    'skipRecurrence'              => false, // Default value
                ]);
                $ical->initString($res);
            } catch (Exception $e) {
                $this->LogDebug(__FUNCTION__, 'initICS: ' . $e);
                return;
            }

            // get all events
            $events = $ical->events();

            // go throw all events
            $this->LogDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
            foreach ($events as $event) {
                //$this->LogDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
                // echo $event->printData('%s: %s'.PHP_EOL);
                if ($event->dtstart < date('Ymd')) {
                    continue;
                }
                // YYYYMMDD umwandeln in DD.MM.YYYY
                $day = substr($event->dtstart, 6) . '.' . substr($event->dtstart, 4, 2) . '.' . substr($event->dtstart, 0, 4);
                // Update fraction
                $name = $event->summary;
                if ($ssw == true) {
                    foreach ($waste as $key => $var) {
                        if ($this->StartsWith($name, $key)) {
                            $this->LogDebug(__FUNCTION__, 'StartWith: ' . $name . ' = ' . $key);
                            $name = $key;
                            break;
                        }
                    }
                }
                if (isset($waste[$name]) && $waste[$name]['date'] == '') {
                    $waste[$name]['date'] = $day;
                    $this->LogDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $day);
                }
            }
        }
        // CSV format
        else {
            $csv = array_map(function ($v)
            {
                $data = str_getcsv($v, ';');
                return $data;
            }, explode("\n", $res));
            $csv = array_map(null, ...$csv);
            $now = date('Ymd');
            // each events
            foreach ($csv as $line) {
                $this->LogDebug(__FUNCTION__, $line);
                $count = count($line);
                $name = mb_convert_encoding($line[0], 'UTF-8', 'ISO-8859-1');
                $this->LogDebug(__FUNCTION__, 'Fraction name: ' . $name . ', count:' . $count);
                for ($i = 1; $i < $count; $i++) {
                    if (empty($line[$i])) {
                        continue;
                    }
                    $day = substr($line[$i], 6) . substr($line[$i], 3, 2) . substr($line[$i], 0, 2);
                    if ($day < $now) {
                        $this->LogDebug(__FUNCTION__, 'Fraction day: ' . $day);
                        continue;
                    }
                    // Update fraction
                    if ($ssw == true) {
                        foreach ($waste as $key => $var) {
                            if ($this->StartsWith($name, $key)) {
                                $this->LogDebug(__FUNCTION__, 'StartWith: ' . $name . ' = ' . $key);
                                $name = $key;
                                break;
                            }
                        }
                    }
                    if (isset($waste[$name]) && $waste[$name]['date'] == '') {
                        $waste[$name]['date'] = $line[$i];
                        $this->LogDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $line[$i]);
                    }
                }
            }
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $var['ident'], $var['date']);
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->LogDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
        }

        // execute Script
        $script = $this->ReadPropertyInteger('settingsScript');
        if ($script != 0) {
            if (IPS_ScriptExists($script)) {
                $rs = IPS_RunScriptEx($script, ['TIMESTAMP' => time(), 'INSTANCE' => $this->InstanceID, 'DATA' => json_encode($waste)]);
                $this->LogDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs);
            } else {
                $this->LogDebug(__FUNCTION__, 'Update: Script #' . $script . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }

        if ($btw == true) {
            // send a complete update message to the display, as parameters may have changed
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
        }
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $initialHandling;
    }

    /**
     * User has selected a new waste management country.
     *
     * @param string $id Country ID.
     *
     * @return void
     */
    protected function OnChangeCountry(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $options = $this->GetClientOptions(self::SERVICE_PROVIDER, $id);
        $this->UpdateFormField('clientID', 'options', json_encode($options));
        $this->UpdateFormField('clientID', 'visible', true);
        $this->UpdateFormField('clientID', 'value', 'null');
        $this->OnChangeClient('null');
    }

    /**
     * User has selected a new waste management.
     *
     * @param string $id Client ID .
     *
     * @return void
     */
    protected function OnChangeClient(string $id): void
    {
        // ACTION: 'init', KEY: $id
        $io = $this->PrepareIO(self::ACTION_CLIENT, $id);
        $this->LogDebug(__FUNCTION__, $io);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
            $data = $this->ExecuteAction($io);
            $this->LogDebug(__FUNCTION__, $io);
        }
        $this->LogDebug(__FUNCTION__, $io);
        // Bad fix for cities only!!!
        if ($io[self::IO_ACTION] == self::ACTION_STREET) {
            $this->LogDebug(__FUNCTION__, 'Hide place & district');
            $this->UpdateFormField('placeID', 'visible', false);
            $this->UpdateFormField('districtID', 'visible', false);
            // Fix Options
            if ($io[self::IO_PLACE] == '') {
                $this->LogDebug(__FUNCTION__, 'Place == null');
                $this->UpdateFormField('placeID', 'value', 'null');
            } else {
                $this->LogDebug(__FUNCTION__, 'Place == ' . $io[self::IO_PLACE]);
                $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $io[self::IO_PLACE]];
                $this->UpdateFormField('placeID', 'options', json_encode($options));
                $this->UpdateFormField('placeID', 'value', $io[self::IO_PLACE]);
            }
            if ($io[self::IO_DISTRICT] == '') {
                $this->UpdateFormField('districtID', 'value', 'null');
            } else {
                $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $io[self::IO_DISTRICT]];
                $this->UpdateFormField('districtID', 'options', json_encode($options));
                $this->UpdateFormField('districtID', 'value', $io[self::IO_DISTRICT]);
            }
        }
        $this->LogDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new place.
     *
     * @param string $id Place GUID .
     *
     * @return void
     */
    protected function OnChangePlace(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_PLACE, $id);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
            $data = $this->ExecuteAction($io);
        }
        $this->LogDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id District GUID .
     *
     * @return void
     */
    protected function OnChangeDistrict(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_DISTRICT, $id);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        if ($io[self::IO_ACTION] == self::ACTION_QUERY) {
            $data = $this->ExecuteAction($io);
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID .
     *
     * @return void
     */
    protected function OnChangeStreet(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_STREET, $id);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Hausnummer ausgewählt.
     *
     * @param string $id Addon GUID .
     *
     * @return void
     */
    protected function OnChangeAddon(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_ADDON, $id);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Hide/unhide form fields.
     *
     * @param array<string,mixed> $io IO interface data
     * @param ?list<array<string,string>> $options Selecttable options
     *
     * @return void
     */
    protected function UpdateForm(array $io, ?array $options): void
    {
        $this->LogDebug(__FUNCTION__, $io);
        $this->LogDebug(__FUNCTION__, $options);
        if (($options != null) && ($io['action'] != self::ACTION_FRACTIONS)) {
            // Always add the selection prompt
            $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
            array_unshift($options, $prompt);
        }
        switch ($io['action']) {
            // Client selected
            case self::ACTION_CLIENT:
                $this->UpdateFormField('placeID', 'visible', false);
                $this->UpdateFormField('districtID', 'visible', false);
                $this->UpdateFormField('streetID', 'visible', false);
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('placeID', 'value', 'null');
                $this->UpdateFormField('districtID', 'value', 'null');
                $this->UpdateFormField('streetID', 'value', 'null');
                $this->UpdateFormField('addonID', 'value', 'null');
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // Location selected
            case self::ACTION_PLACE:
                $this->UpdateFormField('placeID', 'visible', true);
                $this->UpdateFormField('placeID', 'value', 'null');
                if ($options != null) {
                    $this->UpdateFormField('placeID', 'options', json_encode($options));
                }
                // Elements below
                $this->UpdateFormField('districtID', 'visible', false);
                $this->UpdateFormField('streetID', 'visible', false);
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('districtID', 'value', 'null');
                $this->UpdateFormField('streetID', 'value', 'null');
                $this->UpdateFormField('addonID', 'value', 'null');
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // District selected
            case self::ACTION_DISTRICT:
                $this->UpdateFormField('districtID', 'visible', true);
                $this->UpdateFormField('districtID', 'value', 'null');
                if ($options != null) {
                    $this->UpdateFormField('districtID', 'options', json_encode($options));
                }
                // Elements below
                $this->UpdateFormField('streetID', 'visible', false);
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('streetID', 'value', 'null');
                $this->UpdateFormField('addonID', 'value', 'null');
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // Street selected
            case self::ACTION_STREET:
                if ($io[self::IO_DISTRICT] == '') {
                    $this->UpdateFormField('districtID', 'visible', false);
                    $this->UpdateFormField('districtID', 'value', 'null');
                }
                $this->UpdateFormField('streetID', 'visible', true);
                $this->UpdateFormField('streetID', 'value', 'null');
                if ($options != null) {
                    $this->UpdateFormField('streetID', 'options', json_encode($options));
                }
                // Elements below
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('addonID', 'value', 'null');
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // Addon number selected
            case self::ACTION_ADDON:
                $this->UpdateFormField('addonID', 'visible', true);
                $this->UpdateFormField('addonID', 'value', 'null');
                if ($options != null) {
                    $this->UpdateFormField('addonID', 'options', json_encode($options));
                }
                // Elements below
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // Fractions selected
            case self::ACTION_FRACTIONS:
                $this->UpdateFormField('fractionLabel', 'visible', true);
                $f = 1;
                foreach ($options as $fract) {
                    $this->UpdateFormField('fractionID' . $f, 'visible', true);
                    $this->UpdateFormField('fractionID' . $f, 'caption', $fract['caption']);
                    $f++;
                }
                break;
        }
    }

    /**
     * Create the variables for the fractions.
     *
     * @return void
     */
    protected function CreateVariables(): void
    {
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_NAMES])) {
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        $ids = explode(',', $io[self::IO_FRACTIONS]);
        foreach ($ids as $fract) {
            if ($i <= self::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract, $io[self::IO_NAMES][$fract], VARIABLETYPE_STRING, '', $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @param ?string $n next from action
     * @param string $c client id value
     * @param string $p place id value
     * @param string $d district id value
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(?string $n = null, string $c = 'null', string $p = 'null', string $d = 'null', string $s = 'null', string $a = 'null', string $f = 'null'): array
    {
        $io[self::IO_ACTION] = ($n != null) ? $n : self::ACTION_CLIENT;
        $io[self::IO_CLIENT] = ($c != 'null') ? $c : '';
        $io[self::IO_PLACE] = ($p != 'null') ? $p : '';
        $io[self::IO_DISTRICT] = ($d != 'null') ? $d : '';
        $io[self::IO_STREET] = ($s != 'null') ? $s : '';
        $io[self::IO_ADDON] = ($a != 'null') ? $a : '';
        $io[self::IO_FRACTIONS] = ($f != 'null') ? $f : '';
        $io[self::IO_NAMES] = [];
        // data2array
        return $io;
    }

    /**
     * Everthing has changed - update the IO array
     *
     * @param array<string,mixed> $io IO interface data
     * @param string $action new form action
     * @param string $id new selected form value
     *
     * @return void
     */
    protected function UpdateIO(array &$io, string $action, string $id): void
    {
        $this->LogDebug(__FUNCTION__, $action);
        $this->LogDebug(__FUNCTION__, $id);
        // tage over the action
        $io[self::IO_ACTION] = $action;

        if ($action == self::ACTION_ADDON) {
            $io[self::IO_ADDON] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_ADDON] = '';
            $io[self::IO_FRACTIONS] = '';
            $io[self::IO_NAMES] = [];
        }

        if ($action == self::ACTION_STREET) {
            $io[self::IO_STREET] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_STREET] = '';
        }

        if ($action == self::ACTION_DISTRICT) {
            $io[self::IO_DISTRICT] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_DISTRICT] = '';
        }

        if ($action == self::ACTION_PLACE) {
            $io[self::IO_PLACE] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_PLACE] = '';
        }

        if ($action == self::ACTION_CLIENT) {
            $io[self::IO_CLIENT] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_CLIENT] = '';
        }
    }

    /**
     * Builds the POST/GET Url for the API CALLS
     *
     * @param string $key Client ID
     * @param string $action Get parameter action.
     *
     * @return string Action Url
     */
    protected function BuildURL(string $key, string $action): string
    {
        $url = '{{base}}?key={{key}}&modus={{modus}}&waction={{action}}';
        $str = ['base' => self::SERVICE_BASEURL, 'key' => $key, 'modus' => self::SERVICE_MODUSKEY, 'action' => $action];
        // replace all
        if (preg_match_all('/{{(.*?)}}/', $url, $m)) {
            foreach ($m[1] as $i => $varname) {
                $url = str_replace($m[0][$i], sprintf('%s', $str[$varname]), $url);
            }
        }
        $this->LogDebug(__FUNCTION__, $url);
        return $url;
    }

    /**
     * Sends the action url to extract the token pair
     *
     * @param string $key API key
     *
     * @return string Token for Export.
     */
    protected function ExecuteInit(string $key): string
    {
        $this->LogDebug(__FUNCTION__, $key);
        // Build URL data
        $url = $this->BuildURL($key, self::ACTION_CLIENT);

        // Build GET data
        $request = null;

        // Request FORM (xpath)
        $res = $this->GetDocument($url, $request);
        // Collect DATA
        $token = null;
        if ($res !== false) {
            $inputs = $res->query("//input[@type='hidden']");
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if (!$this->StartsWith($name, 'f_')) {
                    $token = $name . '=' . $value;
                    $this->LogDebug(__FUNCTION__, 'Token: ' . $token);
                }
            }
        }
        return $token;
    }

    /**
     * Sends the action url an d data to the service provider
     *
     * @param array<string,mixed> &$io IO interface data
     *
     * @return ?list<array<string,string>> New selecteable options or null.
     */
    protected function ExecuteAction(array &$io): ?array
    {
        $this->LogDebug(__FUNCTION__, $io);
        // Build URL data
        $url = $this->BuildURL($io['key'], $io['action']);

        // Build POST data
        $request = null;
        $params = [];
        foreach ($io as $key => $entry) {
            if ($this->StartsWith($key, 'f_id') && strlen($entry)) {
                $params[] = $key . '=' . $entry;
            }
        }
        if (!empty($params)) {
            $request = implode('&', $params);
        }

        // Request FORM (xpath)
        $res = $this->GetDocument($url, $request);
        $isData = false;
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $inputs = $res->query("//input[@type='hidden']");
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if (array_key_exists($name, $io)) {
                    $io[$name] = $value;
                }
                $this->LogDebug(__FUNCTION__, 'Hidden: ' . $name . ':' . $value);
            }
            $inputs = $res->query("//input[@type='text']");
            foreach ($inputs as $input) {
                $items = [];
                $name = (string) $input->getAttribute('name');
                $action = $input->getAttribute('awk-data-onchange-submit-waction');
                $this->LogDebug(__FUNCTION__, 'Text: ' . $name . ':' . $action);
                if ($this->StartsWith($name, 'f_qry')) {
                    $io[self::IO_ACTION] = $action;
                    $io[$name] = '';
                    return $data;
                }
            }
            $divs = $res->query("//div[@class='awk-ui-input-tr']");
            if ($divs->length > 0) {
                $items = [];
                $fractions = [];
                $name = [];
                $names = [];
                foreach ($divs as $div) {
                    $labels = $res->query('.//label/text()', $div);
                    $inputs = $res->query('.//input', $div);
                    if (($labels->length > 0) && ($inputs->length > 0)) {
                        $fractions[] = $inputs->item(0)->getAttribute('value');
                        $items[] = ['caption' => $labels->item(0)->nodeValue, 'value' => $inputs->item(0)->getAttribute('value')];
                        $names[$inputs->item(0)->getAttribute('value')] = $labels->item(0)->nodeValue;
                    }
                }
                $io[self::IO_FRACTIONS] = implode(',', array_unique($fractions));
                $io[self::IO_NAMES] = $names;
                // take over the options
                $data = $items;
                $isData = true;
                // we have it!
                $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
            }
            $select = $res->query('//select');
            for ($i = 0; $i < $select->length; $i++) {
                if ($select[$i]->getAttribute('awk-data-onchange-submit-waction') == '') {
                    continue;
                }
                $io['action'] = $select[$i]->getAttribute('awk-data-onchange-submit-waction');
                $this->LogDebug(__FUNCTION__, 'select:' . $io['action']);
                $options = $res->query('.//option', $select[0]);
                if ($options->length > 0) {
                    $items = [];
                    foreach ($options as $option) {
                        $value = $option->getAttribute('value');
                        $name = $option->nodeValue;
                        if ($value == 0) {
                            continue;
                        }
                        $items[] = ['caption' => $name, 'value' => $value];
                    }
                    if (!$isData) {
                        $data = $items;
                    }
                }
                break;
            }
        }
        $this->LogDebug(__FUNCTION__, $data);
        return $data;
    }

    /**
     * Sends the API call and transform it to a XPath Document
     *
     * @param string $url Request URL
     * @param ?string $request Request parameters
     *
     * @return mixed DOM document
     */
    protected function GetDocument(string $url, ?string $request): mixed
    {
        $response = $this->ServiceRequest($url, $request);
        if ($response !== false) {
            // $this->LogDebug(__FUNCTION__, $response);
            $dom = new DOMDocument();
            // disable libxml errors
            libxml_use_internal_errors(true);
            $dom->loadHTML(htmlspecialchars_decode(htmlentities($response, ENT_NOQUOTES), ENT_NOQUOTES));
            // remove errors for yucky html
            libxml_clear_errors();
            $xpath = new DOMXpath($dom);
            return $xpath;
        }
        return $response;
    }

    /**
     * Sends the API call
     *
     * @param string $url Request URL
     * @param string $request If $request not null, we will send a POST request, else a GET request.
     * @param string $method Over the $method parameter can we force a POST or GET request!
     * @return string|bool False if the response is null, otherwise the response
     */
    protected function ServiceRequest($url, $request, $method = 'GET'): string|bool
    {
        // Return
        $ret = false;
        // CURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, self::SERVICE_USERAGENT);
        if ($request != null) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        } else {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        // http code
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code === 404) {
            $response = null;
        }
        curl_close($curl);
        //$this->LogDebug(__FUNCTION__, $response);
        if ($response != null) {
            return $response;
        }
        return $ret;
    }

    /**
     * Checks if a string starts with a given substring
     *
     * @param string $haystack The string to search in.
     * @param string $needle The substring to search for in the haystack.
     *
     * @return bool Returns true if haystack begins with needle, false otherwise.
     */
    private function StartsWith(string $haystack, string $needle): bool
    {
        return (string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $buffer = $this->GetBuffer('WasteData');
        if (empty($buffer)) {
            $buffer = '[]';
        }
        $wd = json_decode($buffer, true);
        $ac = [
            'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
            'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
            'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
            'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor'),
        ];
        $result = array_merge($ac, $wd);

        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }
}
