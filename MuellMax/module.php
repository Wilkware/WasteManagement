<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** ICS Parser */
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

use ICal\ICal;

/**
 * Class MuellMax
 */
class MuellMax extends IPSModuleStrict
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
    private const SERVICE_PROVIDER = 'maxde';

    /** @var string Service Base Url */
    private const SERVICE_BASEURL = 'https://www.muellmax.de/abfallkalender/';

    /** @var string Service User Agent */
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';

    // -------------------------------------------------------------------------
    // IO Keys
    // -------------------------------------------------------------------------

    /** @var string IO Action */
    private const IO_ACTION = 'action';

    /** @var string IO Disposal */
    private const IO_DISPOSAL = 'key';

    /** @var string IO Names */
    private const IO_NAMES = 'names';

    /** @var string IO Secure */
    private const IO_SECURE = 'mm_ses';

    /** @var string IO City */
    private const IO_CITY = 'mm_city';

    /** @var string IO Street */
    private const IO_STREET = 'mm_street';

    /** @var string IO Addon */
    private const IO_ADDON = 'mm_addon';

    /** @var string IO Fractions */
    private const IO_FRACTIONS = 'mm_fractions';

    // -------------------------------------------------------------------------
    // ACTION Keys
    // -------------------------------------------------------------------------

    /** @var string Action Init */
    private const ACTION_INIT = 'init';

    /** @var string Action City */
    private const ACTION_CITY = 'city';

    /** @var string Action Street */
    private const ACTION_STREET = 'street';

    /** @var string Action Addon */
    private const ACTION_ADDON = 'addon';

    /** @var string Action Fractions */
    private const ACTION_FRACTIONS = 'fractions';

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
        $this->RegisterPropertyString('disposalID', 'null');
        $this->RegisterPropertyString('cityID', 'null');
        $this->RegisterPropertyString('streetID', 'null');
        $this->RegisterPropertyString('addonID', 'null');
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }

        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        $this->RegisterPropertyInteger('settingsAccentToday', -1);
        $this->RegisterPropertyInteger('settingsAccentTomorrow', -1);
        $this->RegisterPropertyInteger('settingsTonneAlpha', 100);
        $this->RegisterPropertyBoolean('settingsTonneColor', true);
        $this->RegisterPropertyBoolean('settingsHtmlBox', true);
        $this->RegisterPropertyBoolean('settingsLookAhead', false);
        $this->RegisterPropertyString('settingsLookTime', '{"hour":12,"minute":0,"second":0}');

        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);

        // Attributes for dynamic configuration forms (> v3.0)
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));

        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'MAXDE_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'MAXDE_LookAhead(' . $this->InstanceID . ');');

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
        $dId = $this->ReadPropertyString('disposalID');
        $cId = $this->ReadPropertyString('cityID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        // Debug output
        $this->LogDebug(__FUNCTION__, 'disposalID=' . $dId . ', cityID=' . $cId . ', streetId=' . $sId . ', addonId=' . $aId);
        // Service Provider
        $form['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $form['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);
        // Waste Management
        $form['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // go throw the whole way
        $options = [];
        $args = [];
        $next = true;
        // Build io array
        $io = $this->PrepareIO();
        // Disposal
        if ($dId != 'null') {
            $io[self::IO_DISPOSAL] = $dId;
            $this->LogDebug(__FUNCTION__, 'ACTION: Init Disposl!');
            $this->ExecuteAction($io);
            if ($io[self::IO_SECURE] == '') {
                $this->LogDebug(__FUNCTION__, 'Init secure token failed!');
                $next = false;
            }
        } else {
            $this->LogDebug(__FUNCTION__, __LINE__);
            $next = false;
        }
        // City or Streets
        if ($next) {
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'mm_aus_ort.x=1';
            $args[] = 'mm_aus_ort.y=1';
            $this->LogDebug(__FUNCTION__, 'ACTION: City or Street!');
            $options = $this->ExecuteAction($io, $args);
            // no city only streets
            if ($io[self::IO_ACTION] != self::ACTION_CITY) {
                unset($args);
                $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                $args[] = 'xxx=1';
                $args[] = 'mm_frm_str_name=';
                $args[] = 'mm_aus_str_txt_submit=suchen';
                $this->LogDebug(__FUNCTION__, 'ACTION: No city only streets!');
                $options = $this->ExecuteAction($io, $args);
            }
            if (($io[self::IO_ACTION] != self::ACTION_CITY) && ($io[self::IO_ACTION] != self::ACTION_STREET)) {
                $this->LogDebug(__FUNCTION__, 'No city or street received: ' . $io[self::IO_ACTION]);
                $next = false;
            }
        }
        // City
        if ($next) {
            if ($io[self::IO_ACTION] == self::ACTION_CITY) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($cId != 'null') {
                    $io[self::IO_CITY] = $cId;
                    // than prepeare the next
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_frm_ort_sel=' . $io[self::IO_CITY];
                    $args[] = 'mm_aus_ort_submit=weiter';
                    $this->LogDebug(__FUNCTION__, 'ACTION: City and now streets!');
                    $options = $this->ExecuteAction($io, $args);
                    // prepeare street ?
                    if ($io[self::IO_ACTION] != self::ACTION_STREET) {
                        unset($args);
                        $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                        $args[] = 'xxx=1';
                        $args[] = 'mm_frm_str_name=';
                        $args[] = 'mm_aus_str_txt_submit=suchen';
                        $options = $this->ExecuteAction($io, $args);
                    }
                    if ($options == null) {
                        $this->LogDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $cId];
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = false;
            }
        }
        // Street
        if ($next) {
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
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_frm_str_sel=' . $io[self::IO_STREET];
                    $args[] = 'mm_aus_str_sel_submit=weiter';
                    $this->LogDebug(__FUNCTION__, 'ACTION: Street selected!');
                    $options = $this->ExecuteAction($io, $args);
                    // Get Fractions ?
                    if ($io[self::IO_ACTION] != self::ACTION_ADDON) {
                        $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                        unset($args);
                        $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                        $args[] = 'xxx=1';
                        $args[] = 'mm_ica_auswahl=iCalendar-Datei';
                        $this->LogDebug(__FUNCTION__, 'ACTION: No Addon - get fractions!');
                        $options = $this->ExecuteAction($io, $args);
                    }
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
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                $this->LogDebug(__FUNCTION__, 'ADDON');
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
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_frm_hnr_sel=' . $io[self::IO_ADDON];
                    $args[] = 'mm_aus_hnr_sel_submit=weiter';
                    $this->LogDebug(__FUNCTION__, 'ACTION: Addon selected!');
                    $options = $this->ExecuteAction($io, $args);
                    // Get Fractions
                    $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_ica_auswahl=iCalendar-Datei';
                    $this->LogDebug(__FUNCTION__, 'ACTION: And now fractions!');
                    $options = $this->ExecuteAction($io, $args);
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
        $dId = $this->ReadPropertyString('disposalID');
        $cId = $this->ReadPropertyString('cityID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'disposalID=' . $dId . ', cityID=' . $cId . ', streetId=' . $sId . ', addonId=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu && $htmlbox);
        // Set status
        if ($dId == 'null') {
            $status = 201;
        } elseif (($cId == 'null') && ($sId == 'null') && ($aId == 'null')) {
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
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * MAXDE_LookAhead($id);
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
        $i = 1;
        $waste = [];

        // fractions convert to name => ident
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

        // set timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);

        // send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * MAXDE_Update($id);
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

        // (1) Build io array & init
        $uio = $this->PrepareIO();
        $uio[self::IO_DISPOSAL] = $io[self::IO_DISPOSAL];
        $this->ExecuteAction($uio);
        if ($uio[self::IO_SECURE] == '') {
            $this->LogDebug(__FUNCTION__, 'Init secure token failed!');
            return;
        }
        // (2) Request city or an empty street search field
        $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
        $args[] = 'mm_aus_ort.x=1';
        $args[] = 'mm_aus_ort.y=1';
        $this->ExecuteAction($uio, $args);
        // (3) We have a city - select it
        if ($io[self::IO_CITY] != '') {
            unset($args);
            $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_ort_sel=' . $io[self::IO_CITY];
            $args[] = 'mm_aus_ort_submit=weiter';
            $options = $this->ExecuteAction($uio, $args);
        }
        // (4) Select street
        if ($io[self::IO_STREET] != '') {
            unset($args);
            $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_str_name=';
            $args[] = 'mm_aus_str_txt_submit=suchen';
            $this->ExecuteAction($uio, $args);
            unset($args);
            $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_str_sel=' . $io[self::IO_STREET];
            $args[] = 'mm_aus_str_sel_submit=weiter';
            $this->ExecuteAction($uio, $args);
        }
        // (5) Select street addon
        if ($io[self::IO_ADDON] != '') {
            unset($args);
            $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_hnr_sel=' . $io[self::IO_ADDON];
            $args[] = 'mm_aus_hnr_sel_submit=weiter';
            $this->ExecuteAction($uio, $args);
        }
        // (6) Get Fractions
        unset($args);
        $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
        $args[] = 'xxx=1';
        $args[] = 'mm_ica_auswahl=iCalendar-Datei';
        $this->ExecuteAction($uio, $args);
        // (7) Get ics file
        unset($args);
        $args[] = 'mm_ses=' . $uio[self::IO_SECURE];
        $args[] = 'xxx=1';
        $args[] = 'mm_frm_type=termine';
        // fractions convert to name => ident
        $i = 1;
        $waste = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i++);
            if ($enabled) {
                $waste[$name] = ['ident' => $ident, 'date' => ''];
                $args[] = 'mm_frm_fra_' . $ident . '=' . $ident;
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);

        $args[] = 'mm_ica_gen=iCalendar-Datei laden';
        // service request
        $url = $this->BuildURL($io['key']);
        $request = implode('&', $args);
        $response = $this->ServiceRequest($url, $request);
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
            $ical->initString($response);
        } catch (Exception $e) {
            $this->LogDebug(__FUNCTION__, 'initICS: ' . $e);
            return;
        }
        // get all events
        $events = $ical->events();
        // go throw all events
        $this->LogDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            $this->LogDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
            // echo $event->printData('%s: %s'.PHP_EOL);
            if ($event->dtstart < date('Ymd')) {
                continue;
            }
            // YYYYMMDD umwandeln in DD.MM.YYYY
            $day = substr($event->dtstart, 6) . '.' . substr($event->dtstart, 4, 2) . '.' . substr($event->dtstart, 0, 4);
            // Update fraction
            $name = $event->summary;
            // Name could be prefix/suffix :(
            foreach ($io[self::IO_NAMES] as $ident => $fname) {
                if (strstr($name, $fname) !== false) {
                    $name = $fname;
                    break;
                }
            }
            if (isset($waste[$name]) && $waste[$name]['date'] == '') {
                $waste[$name]['date'] = $day;
                $this->LogDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $day);
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
        $this->UpdateFormField('disposalID', 'options', json_encode($options));
        $this->UpdateFormField('disposalID', 'visible', true);
        $this->UpdateFormField('disposalID', 'value', 'null');
        $this->OnChangeDisposal('null');
    }

    /**
     * User has selected a new waste management.
     *
     * @param string $id Disposal ID .
     *
     * @return void
     */
    protected function OnChangeDisposal(string $id): void
    {
        // ACTION: 'init', KEY: $id
        $io = $this->PrepareIO(self::ACTION_INIT, $id);
        $this->LogDebug(__FUNCTION__, $io);
        $data = null;
        if ($id != 'null') {
            // Init
            $data = $this->ExecuteAction($io);
        }
        // City or Streets
        $args = [];
        $args[] = 'mm_ses=' . $io[self::IO_SECURE];
        $args[] = 'mm_aus_ort.x=1';
        $args[] = 'mm_aus_ort.y=1';
        $data = $this->ExecuteAction($io, $args);
        // no city only streets
        if ($io[self::IO_ACTION] != 'city') {
            unset($args);
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_str_name=';
            $args[] = 'mm_aus_str_txt_submit=suchen';
            $data = $this->ExecuteAction($io, $args);
        }
        $this->LogDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new city.
     *
     * @param string $id City ID .
     *
     * @return void
     */
    protected function OnChangeCity(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_CITY, $id);
        $data = null;
        if ($id != 'null') {
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_ort_sel=' . $io[self::IO_CITY];
            $args[] = 'mm_aus_ort_submit=weiter';
            $data = $this->ExecuteAction($io, $args);
        }
        if ($io[self::IO_ACTION] != self::ACTION_STREET) {
            unset($args);
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_str_name=';
            $args[] = 'mm_aus_str_txt_submit=suchen';
            $data = $this->ExecuteAction($io, $args);
        } else {
            $this->LogDebug(__FUNCTION__, 'No street for city!');
            $this->LogDebug(__FUNCTION__, $io);
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Straße ausgewählt.
     *
     * @param string $id Street ID .
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
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_str_sel=' . $io[self::IO_STREET];
            $args[] = 'mm_aus_str_sel_submit=weiter';
            $data = $this->ExecuteAction($io, $args);
            // Get Fractions ?
            if ($io[self::IO_ACTION] != self::ACTION_ADDON) {
                $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                unset($args);
                $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                $args[] = 'xxx=1';
                $args[] = 'mm_ica_auswahl=iCalendar-Datei';
                $data = $this->ExecuteAction($io, $args);
            }
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Hausnummer ausgewählt.
     *
     * @param string $id Addon ID .
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
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_frm_hnr_sel=' . $io[self::IO_ADDON];
            $args[] = 'mm_aus_hnr_sel_submit=weiter';
            $data = $this->ExecuteAction($io, $args);
            // Get Fractions
            $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
            unset($args);
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'xxx=1';
            $args[] = 'mm_ica_auswahl=iCalendar-Datei';
            $data = $this->ExecuteAction($io, $args);
        }
        $this->LogDebug(__FUNCTION__, $data);
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
        if (($options != null) && ($io[self::IO_ACTION] != self::ACTION_FRACTIONS)) {
            // Always add the selection prompt
            $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
            array_unshift($options, $prompt);
        }
        switch ($io[self::IO_ACTION]) {
            // Disposal selected
            case self::ACTION_INIT:
                $this->UpdateFormField('cityID', 'visible', false);
                $this->UpdateFormField('streetID', 'visible', false);
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('cityID', 'value', 'null');
                $this->UpdateFormField('streetID', 'value', 'null');
                $this->UpdateFormField('addonID', 'value', 'null');
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // City selected
            case self::ACTION_CITY:
                $this->UpdateFormField('cityID', 'visible', true);
                $this->UpdateFormField('cityID', 'value', 'null');
                if ($options != null) {
                    $this->UpdateFormField('cityID', 'options', json_encode($options));
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
                if ($io['mm_city'] == '') {
                    $this->UpdateFormField('cityID', 'visible', false);
                    $this->UpdateFormField('cityID', 'value', 'null');
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
                // Fraction Checkboxes
                $this->UpdateFormField('fractionLabel', 'visible', false);
                for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
                // Fractions selected
            case self::ACTION_FRACTIONS:
                $this->UpdateFormField('labelFraction', 'visible', true);
                $f = 1;
                foreach ($options as $fract) {
                    $this->UpdateFormField('fractionID' . $f, 'visible', true);
                    $this->UpdateFormField('fractionID' . $f, 'caption', $fract['caption']);
                    $f++;
                }
                // hide all others
                for ($i = $f; $i <= self::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
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
     * @param string $d disposal id value
     * @param string $c city id value
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(?string $n = null, string $d = 'null', string $c = 'null', string $s = 'null', string $a = 'null', string $f = 'null'): array
    {
        $io[self::IO_ACTION] = ($n != null) ? $n : self::ACTION_INIT;
        $io[self::IO_DISPOSAL] = ($d != 'null') ? $d : '';
        $io[self::IO_CITY] = ($c != 'null') ? $c : '';
        $io[self::IO_STREET] = ($s != 'null') ? $s : '';
        $io[self::IO_ADDON] = ($a != 'null') ? $a : '';
        $io[self::IO_FRACTIONS] = ($f != 'null') ? $f : '';
        $io[self::IO_SECURE] = '';
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

        if ($action == self::ACTION_CITY) {
            $io[self::IO_CITY] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_CITY] = '';
        }

        if ($action == self::ACTION_INIT) {
            $io[self::IO_DISPOSAL] = ($id != 'null') ? $id : '';
            return;
        } else {
            $io[self::IO_DISPOSAL] = '';
        }
    }

    /**
     * Builds the POST/GET Url for the API CALLS
     *
     * @param string $key Endpoint key
     *
     * @return string Endpoint Url
     */
    protected function BuildURL(string $key): string
    {
        $url = '{{base}}{{key}}/res/{{start}}Start.php';
        $str = ['base' => self::SERVICE_BASEURL, 'key' => strtolower($key), 'start' => $key];
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
     * Sends the action url and data to the service provider
     *
     * @param array<string,mixed> $io IO interface data
     * @param array<string> $args forms array
     *
     * @return ?list<array<string,string>> New selecteable options or null.
     */
    protected function ExecuteAction(array &$io, array $args = []): ?array
    {
        $this->LogDebug(__FUNCTION__, $io);
        // Build URL data
        $url = $this->BuildURL($io['key']);
        // GET or POST data
        $request = null;
        if (!empty($args)) {
            $request = implode('&', $args);
        }
        $this->LogDebug(__FUNCTION__, 'Rerquest: ' . $request);
        // Request FORM (xpath)
        $res = $this->GetDocument($url, $request);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            // INIT and all following
            $inputs = $res->query("//input[@type='hidden']");
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if (array_key_exists($name, $io)) {
                    $io[$name] = $value;
                }
                $this->LogDebug(__FUNCTION__, 'Hidden: ' . $name . ':' . $value);
            }
            // INIT and disposal has cities
            $select = $res->query("//select[@name='mm_frm_ort_sel']");
            if ($select->length > 0) {
                $io[self::IO_ACTION] = 'city';
                $options = $res->query('.//option', $select[0]);
                if ($options->length > 0) {
                    foreach ($options as $option) {
                        $value = $option->getAttribute('value');
                        $name = $option->nodeValue;
                        if ($value == 0) {
                            continue;
                        }
                        $data[] = ['caption' => $name, 'value' => $value];
                        //$this->LogDebug(__FUNCTION__, 'City: ' . $name . ':' . $value);
                    }
                    $this->LogDebug(__FUNCTION__, 'RETURN : City');
                    return $data;
                }
            }
            // streets
            $select = $res->query("//select[@name='mm_frm_str_sel']");
            if ($select->length > 0) {
                $io[self::IO_ACTION] = 'street';
                $options = $res->query('.//option', $select[0]);
                if ($options->length > 0) {
                    foreach ($options as $option) {
                        $value = $option->getAttribute('value');
                        $name = $option->nodeValue;
                        if ($value == 0) {
                            continue;
                        }
                        $data[] = ['caption' => $name, 'value' => $value];
                        //$this->LogDebug(__FUNCTION__, 'Street: ' . $name . ':' . $value);
                    }
                    $this->LogDebug(__FUNCTION__, 'RETURN : Street');
                    return $data;
                }
            }
            // addon
            $select = $res->query("//select[@name='mm_frm_hnr_sel']");
            if ($select->length > 0) {
                $io[self::IO_ACTION] = 'addon';
                $options = $res->query('.//option', $select[0]);
                if ($options->length > 0) {
                    foreach ($options as $option) {
                        $value = $option->getAttribute('value');
                        $name = $option->nodeValue;
                        if ($value == 0) {
                            continue;
                        }
                        $data[] = ['caption' => $name, 'value' => $value];
                        //$this->LogDebug(__FUNCTION__, 'Addon: ' . $name . ':' . $value);
                    }
                    $this->LogDebug(__FUNCTION__, 'RETURN : Addon');
                    return $data;
                }
            }
            // fraction

            $divs = $res->query("//div[@class='m_artsel_ical']");
            if ($divs->length > 0) {
                $this->LogDebug(__FUNCTION__, 'Fractions: YES');
                $fractions = [];
                $name = [];
                $names = [];
                foreach ($divs as $div) {
                    $inputs = $res->query(".//input[@type='checkbox']", $div);
                    $spans = $res->query(".//span[@class='m_artsel_text']/text()", $div);
                    $name = $spans->item(0)->nodeValue;  // clear name
                    $value = $inputs->item(0)->getAttribute('value'); // value
                    // store
                    $data[] = ['caption' => $name, 'value' => $inputs->item(0)->getAttribute('value')];
                    $fractions[] = $value;
                    $names[$value] = $name;
                    $this->LogDebug(__FUNCTION__, 'Fraction: ' . $name . ':' . $value);
                }
                $io[self::IO_FRACTIONS] = implode(',', array_unique($fractions));
                $io[self::IO_NAMES] = $names;
            }
        }
        $this->LogDebug(__FUNCTION__, 'RETURN : Last');
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
            //$this->LogDebug(__FUNCTION__, $response);
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
     * @param ?string $request If $request not null, we will send a POST request, else a GET request.
     * @param string $method Over the $method parameter can we force a POST or GET request!
     * @return string|bool False if the response is null, otherwise the response
     */
    protected function ServiceRequest(string $url, ?string $request, string $method = 'GET'): string|bool
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
        curl_close($curl);
        //$this->LogDebug(__FUNCTION__, $response);
        if ($response != null) {
            return $response;
        }
        return $ret;
    }
    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $buffer = $this->GetBuffer('WasteData');
        $this->LogDebug(__FUNCTION__, $buffer);
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
