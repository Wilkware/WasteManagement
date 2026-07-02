<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/**
 * Class Abfall_APP
 */
class Abfall_APP extends IPSModuleStrict
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
    private const SERVICE_PROVIDER = 'apapp';

    /** @var string Service Base Url */
    private const SERVICE_BASE = 'https://app.abfallplus.de/';

    /** @var string Service Assitant Url */
    private const SERVICE_ASSISTANT = self::SERVICE_BASE . 'assistent/';  # ignore: E501

    /** @var string Service User Agent */
    private const SERVICE_USERAGENT = 'Android / {} 8.1.1 (1915081010) / DM=unknown;DT=vbox86p;SN=Google;SV=8.1.0 (27);MF=unknown';

    /** @var string Service User Assitant Agent */
    private const SERVICE_USERASSISTANT = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Abfallwecker';

    /** @var array<int,string> Service Skip Fractions */
    private const SERVICE_H2_SKIP = ['Sondermüll', 'Giftmobil'];

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /** @var int Action State Country */
    private const CHANGE_COUNTRY = 0;

    /** @var int Action State Clienty */
    private const CHANGE_CLIENT = 1;

    /** @var int Action State Federal */
    private const CHANGE_FEDERAL = 2;

    /** @var int Action State County */
    private const CHANGE_COUNTY = 3;

    /** @var int Action State Commune */
    private const CHANGE_COMMUNE = 4;

    /** @var int Action State District */
    private const CHANGE_DISTRICT = 5;

    /** @var int Action State Street */
    private const CHANGE_STREET = 6;

    /** @var int Action State Addony */
    private const CHANGE_ADDON = 7;

    // -------------------------------------------------------------------------
    // Form Elements
    // -------------------------------------------------------------------------

    /** @var int Lement Postion Provi */
    private const ELEM_PROVI = 1;

    /** @var int Panael Element MyMDE */
    private const ELEM_WASTE = 2;

    /** @var int Panael Element Visualisation */
    private const ELEM_VISU = 3;

    /** @var array<int,mixed>  Form Elements Mapping */
    private const FORM_MAPPING = [
        self::CHANGE_FEDERAL    => ['bundesland', 'bundesland_id', 'federalID', 1, 0],
        self::CHANGE_COUNTY     => ['landkreis', 'landkreis_id', 'countyID', 1, 1],
        self::CHANGE_COMMUNE    => ['kommune', 'kommune_id', 'communeID', 2, 0],
        self::CHANGE_DISTRICT   => ['bezirk', 'bezirk_id', 'districtID', 2, 1],
        self::CHANGE_STREET     => ['strasse', 'strasse_id', 'streetID', 3, 1],
        self::CHANGE_ADDON      => ['hnr', 'hnr_id', 'addonID', 3, 2],
    ];

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
        $this->RegisterPropertyString('federalID', 'null');
        $this->RegisterPropertyString('countyID', 'null');
        $this->RegisterPropertyString('communeID', 'null');
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
        $this->RegisterPropertyInteger('settingsAccentTomorrow', -1);
        $this->RegisterPropertyBoolean('settingsTonneColor', true);
        $this->RegisterPropertyBoolean('settingsHtmlBox', true);
        $this->RegisterPropertyBoolean('settingsLookAhead', false);
        $this->RegisterPropertyString('settingsLookTime', '{"hour":12,"minute":0,"second":0}');

        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);

        // Attributes
        $this->RegisterAttributeString('io', serialize([]));

        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'APAPP_Update(' . $this->InstanceID . ');');
        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'APAPP_LookAhead(' . $this->InstanceID . ');');

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

        // Service Values
        $country = $this->ReadPropertyString('serviceCountry');
        // Service Provider
        $form['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $form['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);
        // Waste Management
        $form['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);

        $client = $this->ReadPropertyString('clientID');
        // Client
        if ($client != 'null') {
            // Prompt
            $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
            // IO Values
            $io = unserialize($this->ReadAttributeString('io'));
            $this->LogDebug(__FUNCTION__, $io);
            // Update form elements
            foreach (self::FORM_MAPPING as $key => $field) {
                // display to select?
                if (isset($io[$field[0]]) && !empty($io[$field[0]])) {
                    $value = $this->ReadPropertyString($field[2]);
                    $options = [];
                    $this->LogDebug(__FUNCTION__, 'Read: ' . $value);

                    foreach ($io[$field[0]] as $data) {
                        $options[] = [
                            'caption'   => $data['name'],
                            'value'     => $data['value'],
                        ];
                    }
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][$field[3]]['items'][$field[4]]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][$field[3]]['items'][$field[4]]['visible'] = true;
                    $form['elements'][self::ELEM_WASTE]['items'][$field[3]]['items'][$field[4]]['enabled'] = ($value == 'null');
                }
            }

            // Fraction Checkboxes
            if (isset($io['abfallarten']) && !empty($io['abfallarten'])) {
                // Label
                $form['elements'][self::ELEM_WASTE]['items'][4]['visible'] = true;
                $f = 1;
                foreach ($io['abfallarten'] as $fract) {
                    $form['elements'][self::ELEM_WASTE]['items'][$f + 4]['caption'] = $fract['name'];
                    $form['elements'][self::ELEM_WASTE]['items'][$f + 4]['visible'] = true;
                    $f++;
                }
            }
        } else {
            $this->LogDebug(__FUNCTION__, __LINE__);
        }
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
        // IO Values
        $client = $this->ReadPropertyString('clientID');
        $federal = $this->ReadPropertyString('federalID');
        $county = $this->ReadPropertyString('countyID');
        $commune = $this->ReadPropertyString('communeID');
        $district = $this->ReadPropertyString('districtID');
        $street = $this->ReadPropertyString('streetID');
        $addon = $this->ReadPropertyString('addonID');

        $this->LogDebug(__FUNCTION__, 'clientID=' . $client . ', federalID=' . $federal . ', countyID=' . $county . ', communeID=' . $commune . ', districtID=' . $district . ', streetID=' . $street . ', addonID=' . $addon);

        // Settings
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');

        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu && $htmlbox);

        // Set status
        $io = unserialize($this->ReadAttributeString('io'));
        if ($client == 'null') {
            $status = 201;
        } elseif (empty($io['abfallarten_ids'])) {
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
                    'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor')
                ]));
            }
            $this->CreateVariables($io);
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
     * APAPP_LookAhead($id);
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

        $f = 1;
        $waste = [];

        if (isset($io['abfallarten']) && !empty($io['abfallarten'])) {
            foreach ($io['abfallarten'] as $fract) {
                $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $fract['value'] . ', Name: ' . $fract['name']);
                $enabled = $this->ReadPropertyBoolean('fractionID' . $f++);
                if ($enabled) {
                    $date = $this->GetValue((string) $fract['value']);
                    $waste[$fract['name']] = ['ident' =>  $fract['value'], 'date' => $date];
                }
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
     * APAPP_Update($id);
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

        //$ret = $this->SelectAllWasteTypes($io);
        if (!$this->Validate($io)) {
            $this->LogDebug(__FUNCTION__, 'Validation failed!');
            return;
        }
        $collection = $this->GetCollectionDates($io);
        //$this->LogDebug(__FUNCTION__, $collection);
        if ($collection === false) {
            $this->LogDebug(__FUNCTION__, 'Get dates failed!');
            return;
        }

        // fractions convert to name => ident
        $f = 1;
        $waste = [];
        if (isset($io['abfallarten']) && !empty($io['abfallarten'])) {
            foreach ($io['abfallarten'] as $fract) {
                $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $fract['value'] . ', Name: ' . $fract['name']);
                $enabled = $this->ReadPropertyBoolean('fractionID' . $f++);
                if ($enabled) {
                    $waste[$fract['name']] = ['ident' =>  $fract['value'], 'date' => ''];
                }
            }
        }

        // Build timestamp
        $today = date('Ymd');

        // Iterate dates
        foreach ($collection as $event) {
            // date in past we do not need
            if ($event['date'] < $today) {
                continue;
            }
            $parts = explode('-', $event['id']);
            $ident = $parts[1]; // → "uvw-<id>-xyz"
            // find out disposal type
            foreach ($waste as $key => $var) {
                if ($ident == $var['ident']) {
                    if ($var['date'] == '') {
                        $waste[$key]['date'] = date('d.m.Y', strtotime($event['date']));
                    }
                    break;
                }
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);
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
                $rs = IPS_RunScriptEx($script, ['TIMESTAMP' => time(), 'DATA' => json_encode($waste)]);
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
        $this->UpdateFormField('buttonStreet', 'visible', false);
        $this->UpdateForm([], self::CHANGE_COUNTRY);
    }

    /**
     * User has selected a new waste management.
     *
     * @param string $id Client ID.
     *
     * @return void
     */
    protected function OnChangeClient(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Build io array
            $io = $this->PrepareIO($id);
            $ret = $this->InitConnection($io);
            $this->LogDebug(__FUNCTION__, $io);
            if ($ret) {
                $ret = $this->ExecuteAction($io);
                $this->LogDebug(__FUNCTION__, $io);
                if (!$ret) $io = [];
            }
        }
        // Hide or Unhide properties
        $this->UpdateFormField('buttonStreet', 'visible', false);
        $this->UpdateForm($io, self::CHANGE_CLIENT);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new federal state.
     *
     * @param string $id Federal state ID.
     *
     * @return void
     */
    protected function OnChangeFederal(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            // set selection
            $io['bundesland_id'] = $id;
            $ret = $this->ExecuteAction($io);
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_FEDERAL);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new county district.
     *
     * @param string $id County ID.
     *
     * @return void
     */
    protected function OnChangeCounty(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            foreach ($io['landkreis'] as $landkreis) {
                if ($landkreis['value'] == $id) {
                    // Bundesland ID
                    if ($landkreis['bundesland_id'] != null) {
                        $io['bundesland_id'] = $landkreis['bundesland_id'];
                    }
                    // Landkreis ID
                    $io['landkreis_id'] = ($landkreis['landkreis_id'] !== null) ? $landkreis['landkreis_id'] : $landkreis['value'];
                    // Kommune ID
                    if ($landkreis['kommune_id'] != null) {
                        $io['kommune_id'] = $landkreis['kommune_id'];
                    }
                    // Overstep
                    if ($landkreis['next_step'] != null) {
                        $index = array_search($landkreis['next_step'], $io['actions']);
                        if ($index !== false) {
                            // Remove all actions befor
                            $io['actions'] = array_slice($io['actions'], $index);
                        }
                    }
                }
            }
            // set selection
            //???? $io['landkreis_id'] = $id;
            $ret = $this->ExecuteAction($io);
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_COUNTY);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new commune.
     *
     * @param string $id Commune ID.
     *
     * @return void
     */
    protected function OnChangeCommune(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            // set selection
            $finished = false;
            foreach ($io['kommune'] as $kommune) {
                if ($kommune['value'] == $id) {
                    // Bundesland ID
                    if ($kommune['bundesland_id'] != null) {
                        $io['bundesland_id'] = $kommune['bundesland_id'];
                    }
                    // Landkreis ID
                    if ($kommune['landkreis_id'] != null) {
                        $io['landkreis_id'] = $kommune['landkreis_id'];
                    }
                    // Region ID
                    $io['region_id'] = $kommune['value'];
                    // Kommune ID
                    $io['kommune_id'] = ($kommune['kommune_id'] !== null) ? $kommune['kommune_id'] : $kommune['value'];
                    // Overstep
                    if ($kommune['next_step'] != $io['actions'][0]) {
                        $index = array_search($kommune['next_step'], $io['actions']);
                        if ($index !== false) {
                            // Remove all actions befor
                            $io['actions'] = array_slice($io['actions'], $index);
                        }
                    }
                    // Finished
                    if ($kommune['finished'] || array_key_exists('street_id', $kommune)) {
                        $io['strasse_id'] = ($kommune['street_id'] !== null) ? $kommune['street_id'] : null;
                    }
                    $finished = $kommune['finished'];
                }
            }
            if ($finished) {
                $ret = $this->SelectAllWasteTypes($io);
            } else {
                $ret = $this->ExecuteAction($io);
            }
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_COMMUNE);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new commune.
     *
     * @param string $id District ID.
     *
     * @return void
     */
    protected function OnChangeDistrict(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            // set selection
            $finished = false;
            foreach ($io['bezirk'] as $destrict) {
                if ($destrict['value'] == $id) {
                    // Bundesland ID
                    if ($destrict['bundesland_id'] != null) {
                        $io['bundesland_id'] = $destrict['bundesland_id'];
                    }
                    // Landkreis ID
                    if ($destrict['landkreis_id'] != null) {
                        $io['landkreis_id'] = $destrict['landkreis_id'];
                    }
                    // Kommune ID
                    if ($destrict['kommune_id'] != null) {
                        $io['kommune_id'] = $destrict['kommune_id'];
                    }
                    // Bezirk ID
                    $io['bezirk_id'] = $destrict['value'];

                    // Finished
                    if ($destrict['finished'] || array_key_exists('street_id', $destrict)) {
                        $io['strasse_id'] = ($destrict['street_id'] !== null) ? $destrict['street_id'] : $destrict['value'];
                    }
                    $finished = $destrict['finished'];
                }
            }
            if ($finished) {
                $ret = $this->SelectAllWasteTypes($io);
            } else {
                $ret = $this->ExecuteAction($io);
            }
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_DISTRICT);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID.
     *
     * @return void
     */
    protected function OnChangeStreet(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            // set selection
            $finished = false;
            foreach ($io['strasse'] as $street) {
                if ($street['value'] == $id) {
                    // Kommune ID
                    if ($street['kommune_id'] != null) {
                        $io['kommune_id'] = $street['kommune_id'];
                    }
                    // Bezirk ID
                    if ($street['bezirk_id'] != null) {
                        $io['kommune_id'] = $street['bezirk_id'];
                    }
                    // Strasse ID
                    $io['strasse_id'] = $street['value'];
                    // Finished
                    $finished = $street['finished'];
                }
            }
            if ($finished) {
                $ret = $this->SelectAllWasteTypes($io);
            } else {
                $ret = $this->ExecuteAction($io);
            }
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_STREET);
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
        $io = [];
        if ($id != 'null') {
            // Get io array
            $io = unserialize($this->ReadAttributeString('io'));
            foreach ($io['hnr'] as $addon) {
                if ($addon['value'] == $id) {
                    // Strasse ID
                    if ($addon['strasse_id'] != null) {
                        $io['strasse_id'] = $addon['strasse_id'];
                    }
                    // Hnr ID
                    $io['hnr_id'] = $addon['value'];
                }
            }
            $ret = $this->SelectAllWasteTypes($io);
            $this->LogDebug(__FUNCTION__, $io);
            if (!$ret) $io = [];
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_ADDON);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Hide/unhide form fields.
     *
     * @param array<string,mixed> $io IO interface data
     * @param int $step The step until which the form should be updated (e.g. if step is 2, all fields of step 2 and below will be updated, all above will be hidden).
     *
     * @return void
     */
    protected function UpdateForm(array $io, int $step): void
    {
        //$this->LogDebug(__FUNCTION__, $io);
        $this->LogDebug(__FUNCTION__, $step);
        // Always add the selection prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];

        foreach (self::FORM_MAPPING as $key => $field) {
            // Update only if not touched
            if ($key <= $step) {
                $this->UpdateFormField($field[2], 'enabled', false);
                continue;
            }
            // display to select?
            if (isset($io[$field[0]]) && !empty($io[$field[0]])) {
                $value = 'null';
                $options = [];
                foreach ($io[$field[0]] as $data) {
                    $options[] = [
                        'caption'   => $data['name'],
                        'value'     => $data['value'],
                    ];
                    if (isset($io[$field[1]]) && ($io[$field[1]] == $data['value'])) {
                        $value = $data['value'];
                        $this->LogDebug(__FUNCTION__, 'Value:' . $value);
                    }
                }
                array_unshift($options, $prompt);
                $this->UpdateFormField($field[2], 'enabled', $value == 'null');
                $this->UpdateFormField($field[2], 'visible', true);
                $this->UpdateFormField($field[2], 'options', json_encode($options));
                $this->UpdateFormField($field[2], 'value', $value);
            } else {
                $this->UpdateFormField($field[2], 'visible', false);
                $this->UpdateFormField($field[2], 'value', 'null');
            }
        }

        // Fraction Checkboxes
        if (isset($io['abfallarten']) && !empty($io['abfallarten'])) {
            $this->UpdateFormField('fractionLabel', 'visible', true);
            $f = 1;
            foreach ($io['abfallarten'] as $fract) {
                $this->UpdateFormField('fractionID' . $f, 'visible', true);
                $this->UpdateFormField('fractionID' . $f, 'caption', $fract['name']);
                $f++;
            }
        } else {
            $this->UpdateFormField('fractionLabel', 'visible', false);
            for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
    }

    /**
     * Create the variables for the fractions.
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return void
     */
    protected function CreateVariables(array $io): void
    {
        $this->LogDebug(__FUNCTION__, $io);
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        foreach ($io['abfallarten'] as $fract) {
            if ($i <= self::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract['value'], $fract['name'], VARIABLETYPE_STRING, '', $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @param string $app the client id and domain (id:domain)
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(string $app): array
    {
        $cd = explode(':', $app);

        $io['client'] = uniqid();
        $io['app'] = $cd[1];
        $io['app_id'] = $cd[0];
        $io['cookies'] = [];
        $io['actions'] = [];
        $io['bundesland'] = null;
        $io['bundesland_id'] = null;
        $io['landkreis'] = null;
        $io['landkreis_id'] = null;
        $io['kommune'] = null;
        $io['kommune_id'] = null;
        $io['region_id'] = null;
        $io['bezirk'] = null;
        $io['bezirk_id'] = null;
        $io['strasse'] = null;
        $io['strasse_id'] = null;
        $io['hnr'] = null;
        $io['hnr_id'] = null;
        $io['abfallarten'] = null;
        $io['abfallarten_ids'] = null;
        // data2array
        return $io;
    }

    /**
     * Search for a street
     *
     * @param string $search Search term
     *
     * @return void
     */
    protected function SearchStreet(string $search): void
    {
        $this->LogDebug(__FUNCTION__, $search);
        if (empty($search) || strlen($search) < 2) {
            return;
        }
        // Get io array
        $io = unserialize($this->ReadAttributeString('io'));
        $ret = $this->GetStreets($io, $search);
        if (!$ret) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Strasse suchen!');
            return;
        }
        $this->UpdateFormField('buttonStreet', 'visible', false);
        // Hide or Unhide properties
        $this->UpdateForm($io, self::CHANGE_DISTRICT);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Init a connection to the service (session)
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if connection is successful, false otherwise.
     */
    private function InitConnection(array &$io): bool
    {
        $ret = [];
        //$io['cookies'] = @tempnam("/tmp", 'apa');

        $data = [
            'client' => $io['client'],
            'app_id' => $io['app_id'],
        ];
        //$response = $this->ServiceRequest($io, "config.xml", self::SERVICE_BASE, $data);
        $response = $this->ServiceRequest($io, 'login/', self::SERVICE_BASE, $data);
        $this->LogDebug(__FUNCTION__, $response);

        if ($response !== false) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($response);
            libxml_clear_errors();
            // find all INPUTs
            $inputs = $dom->getElementsByTagName('input');
            if ($inputs->length === 0) {
                $this->LogDebug(__FUNCTION__, 'No Inputs found!');
                return false;
            }
            foreach ($inputs as $input) {
                if ($input->getAttribute('name') == 'f_id_bundesland') {
                    $io['bundesland_id'] = $input->getAttribute('value');
                }
                if ($input->getAttribute('name') == 'f_id_landkreis') {
                    $io['landkreis_id'] = $input->getAttribute('value');
                }
                if ($input->getAttribute('name') == 'f_id_kommune') {
                    $io['kommune_id'] = $input->getAttribute('value');
                }
            }
            // Read all anchor elements with href attribute
            $links = $dom->getElementsByTagName('a');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                // Check whether the href pattern begins with '#awk_assistant_step_location_'
                if (preg_match('/#awk_assistent_step_standort_([a-z]+)/', $href, $matches)) {
                    $ret[] = $matches[1]; // Den Teil nach dem Präfix speichern
                }
            }
        }
        $io['actions'] = $ret;
        return true;
    }

    /**
     * Sends requests to the service API.
     *
     * @param array<string,mixed> $io IO interface data
     * @param string $ending URL ending
     * @param string $base Base URL
     * @param ?array<string,mixed> $data POST data
     * @param ?array<string,int> $params GET parameters
     * @param string $method Request methode (GET|POST)
     * @param array<string,string> $headers Header content
     *
     * @return string|false Response Answer of the service
     */
    private function ServiceRequest(array &$io, string $ending, string $base, ?array $data = null, ?array $params = null, string $method = 'post', array $headers = []): string|false
    {
        // Build headers
        if ($base == self::SERVICE_ASSISTANT) {
            $headers['User-Agent'] = self::SERVICE_USERASSISTANT;
            $headers['Accept'] = '*/*';
            $headers['Origin'] = 'https://app.abfallplus.de';
            $headers['X-Requested-With'] = 'XMLHttpRequest';
            $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            $headers['Referer'] = 'https://app.abfallplus.de/login/';
            $headers['Accept-Encoding'] = 'gzip, deflate, br';
            $headers['Accept-Language'] = 'de-DE,de;q=0.9';
        } else {
            // Here you would have to set the user agent dynamically if necessary
            $headers['User-Agent'] = str_replace('{}', $io['app'], self::SERVICE_USERAGENT);
        }
        if (strpos($ending, 'config.xml') !== false) {
            $headers['Accept-Encoding'] = 'gzip, deflate, br';
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            sleep(1);
        }
        // Build url
        $url = $base . $ending;
        // Default headers
        $header = [];
        foreach ($headers as $key => $value) {
            $header[] = "$key: $value";
        }
        $this->LogDebug(__FUNCTION__, $header);
        // Prepare curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        //      curl_setopt($curl, CURLOPT_COOKIEJAR, $io['cookies']);
        //      curl_setopt($curl, CURLOPT_COOKIEFILE, $io['cookies']);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Setze Cookies, falls vorhanden
        if (!empty($io['cookies'])) {
            curl_setopt($curl, CURLOPT_COOKIE, $this->GetCookies($io['cookies']));
        }

        if (($method == 'get') && $params) {
            $url .= '?' . http_build_query($params); // Appending params to the URL for GET requests
        }
        if ($method == 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            // POST data
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        // Execute the request and get the response
        $response = curl_exec($curl);
        // Check for errors
        if (curl_errno($curl)) {
            $this->LogDebug(__FUNCTION__, 'cURL Error: ' . curl_error($curl));
            curl_close($curl);
            return false;
        }
        // Cut response in header und body
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $size);
        $body = substr($response, $size);
        // Save cookies?
        $io['cookies'] = $this->SetCookies($header);
        // Send data back
        curl_close($curl);
        return $body;
    }

    /**
     * Execute an action
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if action is successful, false otherwise
     */
    private function ExecuteAction(array &$io): bool
    {
        $ret = true;
        $state = array_shift($io['actions']);
        $this->LogDebug(__FUNCTION__, $state);
        switch ($state) {
            // BUNDESLAND auswählen
            case 'bundesland':
                $ret = $this->GetFederalStates($io);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Bundesländer auslesen!');
                }
                break;
                // LANDKREIS auswählen
            case 'region':
            case 'landkreis':
                $ret = $this->GetCountyDistricts($io, $state);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Landkreise auslesen!');
                }
                break;
                // KOMMUNE auswählen
            case 'kommune':
                $ret = $this->GetCommunes($io);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Kommunen auslesen!');
                }
                break;
                // BEZIRK auswählen
            case 'bezirk':
                $ret = $this->GetDistricts($io);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Bezirke auslesen!');
                }
                break;
                // STRASSE auswählen
            case 'strasse':
                $ret = $this->GetStreets($io);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Strasse muss eingegeben werden!');
                    $this->UpdateFormField('buttonStreet', 'visible', true);
                    $ret = true;
                }
                break;
                // HAUSNUMMER auswählen
            case 'hnr':
                $ret = $this->GetAddOns($io);
                if (!$ret) {
                    $this->LogDebug(__FUNCTION__, 'ERROR: Hausnummer auslesen!');
                }
                break;
        }
        return $ret;
    }

    /**
     * ExtractOnClicks
     *
     * @param string $data HTML data containing the onclick handlers
     * @param bool $hnr House number search activated (if true, an additional search for the house number will be performed in the onclick string and added to the result array)
     *
     * @return array<mixed> Array of extracted data from the onclick handlers (if $hnr is false, each element is an array of the parameters passed to the onclick handler; if $hnr is true, each element is an array of the parameters passed to the onclick handler plus the house number if found)
     */
    private function ExtractOnClicks(string $data, bool $hnr = false): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        @$doc->loadHTML(mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8'));

        // Suppress errors due to possible HTML warnings
        libxml_clear_errors();
        $ret = [];
        $links = $doc->getElementsByTagName('a');

        // iterate over all links
        foreach ($links as $data) {
            // only with onclick handler
            if (!$data->hasAttribute('onclick')) {
                continue;
            }
            $onclick = $data->getAttribute('onclick');
            $onclick = str_replace("('#f_ueberspringen').val('0')", '', $onclick);
            // find breaks
            $start = strpos($onclick, '(');
            $end = strpos($onclick, '})');
            $minus = 0;
            if ($end === false) {
                $end = strpos($onclick, ')');
                $minus = 1;
            }
            if ($start === false || $end === false || $start >= $end) {
                continue;
            }
            // Prepare JSON-like string
            $string = '[' . substr($onclick, $start + 1, $end - $start - $minus) . ']';
            $string = str_replace(["'", "\t", "\r\n", "\n"], ['"', '', '', ''], $string);
            // check JSON
            $json = json_decode($string, true);
            if ($json === null) {
                throw new Exception("Error during JSON parsing: '$string', onclick: '$onclick'");
            }
            // if hnr is activated, perform an additional search in the onclick string
            if ($hnr) {
                if (preg_match('/\.val\(([0-9]+)\)/', $onclick, $matches)) {
                    $json[] = $matches[1];
                }
            }
            $ret[] = $json;
        }
        return $ret;
    }

    /**
     * Reads all available federal states (Bundesländer)
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if federal states are successfully read, false otherwise
     */
    private function GetFederalStates(array &$io): bool
    {
        // Target url endpoint
        $ending = 'bundesland/';
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, null, null, 'get');
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['bundesland'] = [];
        foreach ($this->ExtractOnClicks($response) as $data) {
            $io['bundesland'][] = [
                'value' => $data[0],
                'name'  => $data[1]
            ];
        }
        return true;
    }

    /**
     * Reads all available country districts (Landkreise)
     *
     * @param array<string,mixed> $io IO interface data
     * @param string $region name of the url endpoint
     *
     * @return bool True if country districts are successfully read, false otherwise
     */
    private function GetCountyDistricts(array &$io, string $region = 'landkreis'): bool
    {
        $data = [];

        if (!empty($io['bundesland_id'])) {
            $data['id_bundesland'] = $io['bundesland_id'];
        }

        // Target url endpoint
        $ending = $region . '/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['landkreis'] = [];
        foreach ($this->ExtractOnClicks($response) as $data) {
            if (isset($data[4])) {
                if (stripos($data[4], 'wird aktuell nicht unterstützt') !== false) continue;
                if (stripos($data[4], 'gibt es bereits eine eigene App') !== false) continue;
                if (stripos($data[4], 'in Kürze unterstützt.') !== false) continue;
            }
            $io['landkreis'][] = [
                'value'         => $data[0],
                'name'          => $data[1],
                'bundesland_id' => $data[5]['set_id_bundesland'] ?? null,
                'landkreis_id'  => $data[5]['set_id_landkreis'] ?? null,
                'kommune_id'    => $data[5]['set_id_kommune'] ?? null,
                'next_step'     => $data[5]['next_step'] ?? null,
            ];
        }

        // If no 'landkreis' were found, try again with 'region'
        if ($region === 'landkreis' && empty($io['landkreis'])) {
            return $this->GetCountyDistricts($io, 'region');
        }

        return true;
    }

    /**
     * Reads all available communes (Kommunen)
     *
     * @param array<string,mixed> $io IO interface data
     * @param string $region name of the url endpoint
     *
     * @return bool True if communes are successfully read, false otherwise
     */
    private function GetCommunes(array &$io, string $region = 'kommune'): bool
    {
        $data = [];

        if (!empty($io['bundesland_id'])) {
            $data['id_bundesland'] = $io['bundesland_id'];
        }
        if (!empty($io['landkreis_id'])) {
            $data['id_landkreis'] = $io['landkreis_id'];
        }

        // Target url endpoint
        $ending = $region . '/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['kommune'] = [];
        foreach ($this->ExtractOnClicks($response) as $data) {
            $commune = [
                'value'         => $data[0],
                'name'          => $data[1],
                'bundesland_id' => $data[5]['set_id_bundesland'] ?? null,
                'landkreis_id'  => $data[5]['set_id_landkreis'] ?? null,
                'kommune_id'    => $data[5]['set_id_kommune'] ?? null,
                'next_step'     => $data[3],
                'finished'      => false
            ];
            if (isset($data[5]['step_follow_data']['step_akt']) && $data[5]['step_follow_data']['step_akt'] === 'strasse') {
                $commune['finished'] = true;
                $commune['street_id'] = $data[5]['step_follow_data']['id'];
            }
            $io['kommune'][] = $commune;
        }

        // If no 'kommune' were found, try again with 'region'
        if ($region === 'kommune' && empty($io['kommune'])) {
            return $this->GetCommunes($io, 'region');
        }
        return true;
    }

    /**
     * Reads all available destrictes (Bezirke)
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if districts are successfully read, false otherwise
     */
    private function GetDistricts(array &$io): bool
    {
        $data = [];

        if (!empty($io['bundesland_id'])) {
            $data['id_bundesland'] = $io['bundesland_id'];
        }
        if (!empty($io['landkreis_id'])) {
            $data['id_landkreis'] = $io['landkreis_id'];
        }
        if (!empty($io['kommune_id'])) {
            $data['id_kommune'] = $io['kommune_id'];
        }

        // Target url endpoint
        $ending = 'bezirk/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['bezirk'] = [];
        foreach ($this->ExtractOnClicks($response) as $data) {
            $district = [
                'value'         => $data[0],
                'name'          => $data[1],
                'bundesland_id' => $data[5]['set_id_bundesland'] ?? null,
                'landkreis_id'  => $data[5]['set_id_landkreis'] ?? null,
                'kommune_id'    => $data[5]['set_id_kommune'] ?? null,
                'finished'      => false
            ];
            if (isset($data[5])) {
                //$this->Debug($data[5]);
            }
            if (isset($data[5]['step_follow_data']['step_akt']) && $data[5]['step_follow_data']['step_akt'] === 'strasse') {
                $district['finished'] = true;
                $district['street_id'] = $data[5]['step_follow_data']['id'];
            }
            $io['bezirk'][] = $district;
        }
        return true;
    }

    /**
     * Reads all available communes (Kommunen)
     *
     * @param array<string,mixed> $io IO interface data
     * @param ?string $search Search term for streets
     *
     * @return bool True if streets are successfully read, false otherwise
     */
    private function GetStreets(array &$io, ?string $search = null): bool
    {
        $data = [
            'id_landkreis'   => $io['landkreis_id'],
            'id_bezirk'      => $io['bezirk_id'],
            'id_kommune'     => $io['kommune_id'],
            'id_kommune_qry' => $io['kommune_id'],
            'strasse_qry'    => $search,
        ];

        // Target url endpoint
        $ending = 'strasse/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        $this->LogDebug(__FUNCTION__, $response);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['strasse'] = [];
        foreach ($this->ExtractOnClicks($response) as $data) {
            $io['strasse'][] = [
                'value'      => $data[0],
                'name'       => $data[1],
                'kommune_id' => $data[5]['set_id_kommune'] ?? null,
                'bezirk_id'  => $data[5]['set_id_bezirk'] ?? null,
                'finished'   => $data[3] == 'fertig',
            ];
        }
        // Empty means we have to search a street ...
        if (empty($io['strasse'])) {
            return false;
        }
        return true;
    }

    /**
     * Reads all available addons (Hausnummern)
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if addons are successfully read, false otherwise
     */
    private function GetAddOns(array &$io): bool
    {
        $data = [
            'id_landkreis' => $io['landkreis_id'],
            'id_kommune'   => $io['kommune_id'],
            'id_bezirk'    => $io['bezirk_id'] ?: '',
            'id_strasse'   => $io['strasse_id'],
        ];

        // Target url endpoint
        $ending = 'hnr/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $io['hnr'] = [];
        foreach ($this->ExtractOnClicks($response, true) as $data) {
            $io['hnr'][] = [
                'value'      => $data[0],
                'name'       => explode('|', urldecode($data[0]))[0],
                'strasse_id' => $data[6] ?? null,
            ];
        }

        return true;
    }

    /**
     * SelectAllWasteTypes
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return bool True if waste types are successfully selected, false otherwise
     */
    private function SelectAllWasteTypes(array &$io): bool
    {
        $data = [
            'f_id_region'     => $io['region_id'] != null ? $io['region_id'] : '',
            'f_id_bundesland' => $io['bundesland_id'],
            'f_id_landkreis'  => $io['landkreis_id'],
            'f_id_kommune'    => $io['kommune_id'],
            'f_id_bezirk'     => '', // $io['bezirk_id']
            'f_id_strasse'    => $io['strasse_id'],
            'f_hnr'           => $io['hnr_id'],
            'f_kdnr'          => '',
        ];

        // Target url endpoint
        $ending = 'abfallarten/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        @$doc->loadHTML(mb_convert_encoding($response, 'HTML-ENTITIES', 'UTF-8'));
        // Suppress errors due to possible HTML warnings
        libxml_clear_errors();

        $io['abfallarten'] = [];
        $io['abfallarten_ids'] = [];

        foreach (self::SERVICE_H2_SKIP as $skip) {
            $xpath = new DOMXPath($doc);
            //$elements = $xpath->query("//h2[contains(text(), $skip)]");
            $elements = $xpath->query("//h2[contains(text(), '" . htmlspecialchars($skip) . "')]");

            foreach ($elements as $element) {
                $parentDiv = $element->parentNode;
                if ($parentDiv) {
                    // Entferne das Element aus dem DOM
                    $parentDiv->parentNode->removeChild($parentDiv);
                }
            }
        }

        $inputs = $doc->getElementsByTagName('input');
        $texts = $doc->getElementsByTagName('ion-text');
        $it = 0;
        foreach ($inputs as $input) {
            if ($input->getAttribute('name') === 'f_id_abfallart[]') {
                if ($input->getAttribute('value') === '0') {
                    if (!$input->hasAttribute('id')) {
                        continue;
                    }
                    $parts = explode('_', $input->getAttribute('id'));
                    $id = end($parts);
                } else {
                    $id = $input->getAttribute('value');
                }
                $io['abfallarten_ids'][] = $id;
                $io['abfallarten'][] = [
                    'value' => $id,
                    'name'  => $texts[$it++]->textContent,
                ];
            }
        }

        return true;
    }

    /**
     * Validate all collect information and confirm Datenschutz
     *
     * @param array<string,mixed> $io IO interface values
     *
     * @return bool True if validation is successful, false otherwise
     */
    private function Validate(array &$io): bool
    {
        $data = [
            'f_id_bundesland'   => $io['bundesland_id'],
            'f_id_landkreis'    => $io['landkreis_id'],
            'f_id_kommune'      => $io['kommune_id'],
            'f_id_bezirk'       => '',
            'f_id_strasse'      => $io['strasse_id'],
            'f_hnr'             => $io['hnr_id'],
            'f_kdnr'            => '',
            'f_id_abfallart'    => $io['abfallarten_ids'],
            'f_uhrzeit_tag'     => '86400|0',
            'f_uhrzeit_stunden' => 54000,
            'f_uhrzeit_minuten' => 600,
            'f_anonym'          => 1,
            'f_ausgangspunkt'   => 1,
            'f_ueberspringen'   => 0,
        ];

        // Target url endpoint
        $ending = 'ueberpruefen/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        $data['f_datenschutz'] = date('YmdHis');
        // Target url endpoint
        $ending = 'finish/';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_ASSISTANT, $data);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }
        $value = json_decode($response, true);
        $this->LogDebug(__FUNCTION__, $response);
        // Error handling
        if (!$value['assistantCompleted']) {
            $this->LogDebug(__FUNCTION__, 'Einrichtung NICHT erfolgreich abgeschlossen!');
            return false;
        }
        return true;
    }

    /**
     * Get all collection dates of a specific setup
     *
     * @param array<string,mixed> $io IO interface values
     *
     * @return list<array<string,string>>|false Collection dates
     */
    private function GetCollectionDates(array &$io): array|false
    {
        // Target url endpoint
        $ending = 'version.xml';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_BASE, ['client' => $io['client'], 'app_id' => $io['app_id']]);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_BASE, ['client' => $io['client'], 'app_id' => $io['app_id']], ['renew' => 1]);
        // Error handling
        if (!$response) {
            $this->LogDebug(__FUNCTION__, "Error in the request to: $ending");
            return false;
        }

        // Target url endpoint
        $ending = 'struktur.xml.zip';  // Ziel-URL
        // Send HTTP request
        $response = $this->ServiceRequest($io, $ending, self::SERVICE_BASE, ['client' => $io['client'], 'app_id' => $io['app_id']]);

        $xml = new SimpleXMLElement($response);
        // Extract waste types
        $categories = [];
        $dict = $xml->xpath("//key[text()='categories']/following-sibling::array")[0] ?? null;
        if (!$dict) {
            $this->LogDebug(__FUNCTION__, 'No categories found.');
            return false;
        }

        foreach ($dict->dict as $category) {
            $id = (string) $category->xpath("key[text()='id']/following-sibling::string")[0];
            $name = trim(str_replace(['![CDATA[', ']]'], '', (string) $category->xpath("key[text()='name']/following-sibling::string")[0]));
            $categories[$id] = $name;
        }
        // Extract pick-up dates
        $collections = [];
        $dates = $xml->xpath("//key[text()='dates']/following-sibling::array")[0] ?? null;
        if (!$dates) {
            $this->LogDebug(__FUNCTION__, 'No collection dates found.');
            return false;
        }

        foreach ($dates->dict as $collection) {
            $category_id = (string) $collection->xpath("key[text()='category_id']/following-sibling::string")[0];
            $pickup_date = (string) $collection->xpath("key[text()='pickup_date']/following-sibling::string")[0];
            $pickup_date = DateTime::createFromFormat("Y-m-d\TH:i:sP", $pickup_date)->format('Ymd');

            $collections[] = [
                'id'   => $category_id,
                'name' => $categories[$category_id] ?? '<unknown>',
                'date' => $pickup_date
            ];
        }
        return $collections;
    }

    /**
     * SetCookies
     *
     * @param string $header Request header
     * @return array<string,string> Extracted session cookies
     */
    private function SetCookies(string $header): array
    {
        $cookies = [];
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
        foreach ($matches[1] as $cookieString) {
            parse_str($cookieString, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        return $cookies;
    }

    /**
     * Gets the stored cookies as string
     *
     * @param array<string,string> $cookies Session cookies
     *
     * @return string Cookies
     */
    private function GetCookies(array $cookies): string
    {
        $cookiePairs = [];
        foreach ($cookies as $key => $value) {
            $cookiePairs[] = "$key=$value";
        }
        return implode('; ', $cookiePairs);
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
            'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor'),
        ];
        $result = array_merge($ac, $wd);

        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }
}
