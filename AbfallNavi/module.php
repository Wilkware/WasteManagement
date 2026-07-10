<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/**
 * Class AbfallNavi
 */
class AbfallNavi extends IPSModuleStrict
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
    private const SERVICE_PROVIDER = 'regio';

    /** @var string Service Base Url */
    private const SERVICE_BASEURL = 'https://{{region}}-abfallapp.regioit.de/abfall-app-{{region}}';

    /** @var string Service Fallback Url */
    private const SERVICE_FALLBACK = 'https://abfallapp.regioit.de/abfall-app-{{region}}';

    /** @var string Service User Agent */
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';

    // -------------------------------------------------------------------------
    // IO Keys
    // -------------------------------------------------------------------------

    /** @var string IO Action */
    private const IO_ACTION = 'action';

    /** @var string IO Client */
    private const IO_CLIENT = 'client';

    /** @var string IO Place */
    private const IO_PLACE = 'place';

    /** @var string IO Street */
    private const IO_STREET = 'street';

    /** @var string IO Addon */
    private const IO_ADDON = 'addon';

    /** @var string IO Fractions */
    private const IO_FRACTIONS = 'fractions';

    /** @var string IO Names */
    private const IO_NAMES = 'names';

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /** @var string Action Client */
    private const ACTION_CLIENT = 'client';

    /** @var string Action Place */
    private const ACTION_PLACE = 'places';

    /** @var string Action Street */
    private const ACTION_STREET = 'streets';

    /** @var string Action Addon */
    private const ACTION_ADDON = 'addons';

    /** @var string Action Fraction */
    private const ACTION_FRACTIONS = 'fractions';

    /** @var string Action Dates */
    private const ACTION_DATES = 'dates';

    // -------------------------------------------------------------------------
    // GETs
    // -------------------------------------------------------------------------

    /** @var string Get Cities */
    private const GET_CITIES = '/rest/orte';

    /** @var string Get Streets */
    private const GET_STREETS = '/rest/orte/{{city}}/strassen';

    /** @var string Get Addons */
    private const GET_ADDONS = '/rest/orte/{{city}}/strassen/{{street}}';

    /** @var string Get Fraction Streets */
    private const GET_FRACTIONS_STREET = '/rest/strassen/{{street}}/fraktionen';

    /** @var string Get Fraction Addons */
    private const GET_FRACTIONS_ADDON = '/rest/hausnummern/{{addon}}/fraktionen';

    /** @var string Get Street Dates */
    private const GET_DATES_STREET = '/rest/strassen/{{street}}/termine';

    /** @var string Get Addon Dates */
    private const GET_DATES_ADDON = '/rest/hausnummern/{{addon}}/termine';

    // -------------------------------------------------------------------------
    // Buffers
    // -------------------------------------------------------------------------
    /** @var string String buffer Place */
    private const SB_PLACE = 'place';

    /** @var string String buffer Street */
    private const SB_STREET = 'street';

    /** @var string String buffer Addon */
    private const SB_ADDON = 'addon';

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
        $this->RegisterTimer('UpdateTimer', 0, 'REGIO_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'REGIO_LookAhead(' . $this->InstanceID . ');');

        $this->SetBuffer(self::SB_PLACE, '');
        $this->SetBuffer(self::SB_STREET, '');
        $this->SetBuffer(self::SB_ADDON, '');

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
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');

        // Debug output
        $this->LogDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', streetId=' . $sId . ', addonId=' . $aId);

        // Service Provider
        $form['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $form['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);

        // Waste Management
        $form['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];

        // go throw the whole way
        $next = true;

        // Build io array
        $io = $this->PrepareIO();
        $options = [];

        // Client
        if ($cId != 'null') {
            $io[self::IO_CLIENT] = $cId;
            $options = $this->ExecuteAction($io);
            if ($options == null) {
                $next = false;
            }
        } else {
            $this->LogDebug(__FUNCTION__, 'Line: ' . __LINE__);
            $next = false;
        }

        // Place
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_PLACE) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->LogDebug(__FUNCTION__, 'Line: ' . __LINE__);
                    $next = false;
                }
                if ($pId != 'null') {
                    $json = unserialize($this->GetBuffer(self::SB_PLACE));
                    foreach ($json as $city) {
                        if ($city[0] == $pId) {
                            $io[self::IO_PLACE] = (string) $city[1];
                            break;
                        }
                    }
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->LogDebug(__FUNCTION__, 'Line: ' . __LINE__);
                        $next = false;
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, 'Line: ' . __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $pId];
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $data;
                $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = false;
            }
        }

        // Street
        if ($next) {
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
                    $json = unserialize($this->GetBuffer(self::SB_STREET));
                    foreach ($json as $street) {
                        if ($street[0] == $sId) {
                            $io[self::IO_STREET] = (string) $street[1];
                            break;
                        }
                    }
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $io[self::IO_ADDON] = '';
                        $io[self::IO_ACTION] = self::ACTION_ADDON;
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
            $this->LogDebug(__FUNCTION__, 'ADDON');
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                $this->LogDebug(__FUNCTION__, 'ADDON ACTION');
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $options;
                    $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = true;
                }
                if ($aId != 'null') {
                    $json = unserialize($this->GetBuffer(self::SB_ADDON));
                    foreach ($json as $addon) {
                        if ($addon[0] == $aId) {
                            $io[self::IO_ADDON] = (string) $addon[1];
                            break;
                        }
                    }
                }
                if (($aId != 'null') || (($io[self::IO_ADDON] == '') && ($options == null))) {
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
                }
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
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', streetId=' . $sId . ', addonId=' . $aId);

        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);

        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu && $htmlbox);

        // Set status
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        if ($io[self::IO_CLIENT] == 'null') {
            $status = 201;
        } elseif ($io[self::IO_FRACTIONS] == '') {
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
                if ($loakahead && $tilevisu) {
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
     * REGIO_LookAhead($id);
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
                $waste[$ident] = ['ident' => $ident, 'date' => $date];
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
     * REGIO_Update($id);
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
        // Resync IDs :(
        $this->GetConfigurationForm();
        // Read data
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        // Get data
        $io[self::IO_ACTION] = self::ACTION_DATES;
        $data = $this->ExecuteAction($io);
        if ($data == null) {
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
                $waste[$ident] = ['ident' => $ident, 'date' => ''];
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);
        // Build timestamp
        $today = mktime(0, 0, 0);
        // Iterate dates
        foreach ($data as $day) {
            // date in past we do not need
            if (strtotime($day['date']) < $today) {
                continue;
            }
            // find out disposal type
            foreach ($waste as $key => $time) {
                if ($key == $day['id']) {
                    if ($time['date'] == '') {
                        $waste[$key]['date'] = date('d.m.Y', strtotime($day['date']));
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
        $this->LogDebug(__FUNCTION__, $id);
        $io = $this->PrepareIO(self::ACTION_CLIENT, $id);
        $this->LogDebug(__FUNCTION__, $io);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
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
        $this->LogDebug(__FUNCTION__, $io);
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
        $this->LogDebug(__FUNCTION__, $io);
        // Bad Hack if no addon!!!
        if ($io[self::IO_ACTION] == self::ACTION_FRACTIONS) {
            $this->LogDebug(__FUNCTION__, 'No Addons!!!');
            // Set AddOn = ''
            $this->UpdateIO($io, self::ACTION_ADDON, 'null');
            // Jump over to fractions
            $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
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
        //$this->LogDebug(__FUNCTION__, $options);
        if (($options != null) && ($io[self::IO_ACTION] != self::ACTION_FRACTIONS)) {
            // Always add the selection prompt
            $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
            array_unshift($options, $prompt);
        }
        switch ($io[self::IO_ACTION]) {
            // Client selected
            case self::ACTION_CLIENT:
                $this->UpdateFormField('placeID', 'visible', false);
                $this->UpdateFormField('streetID', 'visible', false);
                $this->UpdateFormField('addonID', 'visible', false);
                $this->UpdateFormField('placeID', 'value', 'null');
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
            $this->LogDebug(__FUNCTION__, 'No names!');
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
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(?string $n = null, string $c = 'null', string $p = 'null', string $s = 'null', string $a = 'null', string $f = 'null'): array
    {
        $io[self::IO_ACTION] = ($n != null) ? $n : self::ACTION_CLIENT;
        $io[self::IO_CLIENT] = ($c != 'null') ? $c : '';
        $io[self::IO_PLACE] = ($p != 'null') ? $p : '';
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
            if ($id != 'null') {
                $json = unserialize($this->GetBuffer(self::SB_ADDON));
                foreach ($json as $addon) {
                    if ($addon[0] == $id) {
                        $io[self::IO_ADDON] = (string) $addon[1];
                        break;
                    }
                }
            } else {
                $io[self::IO_ADDON] = '';
            }
            return;
        } else {
            $io[self::IO_ADDON] = '';
            $io[self::IO_FRACTIONS] = '';
            $io[self::IO_NAMES] = [];
        }

        if ($action == self::ACTION_STREET) {
            if ($id != 'null') {
                $json = unserialize($this->GetBuffer(self::SB_STREET));
                foreach ($json as $street) {
                    if ($street[0] == $id) {
                        $io[self::IO_STREET] = (string) $street[1];
                        break;
                    }
                }
            } else {
                $io[self::IO_STREET] = '';
            }
            return;
        } else {
            $io[self::IO_STREET] = '';
        }

        if ($action == self::ACTION_PLACE) {
            if ($id != 'null') {
                $json = unserialize($this->GetBuffer(self::SB_PLACE));
                foreach ($json as $city) {
                    if ($city[0] == $id) {
                        $io[self::IO_PLACE] = (string) $city[1];
                        break;
                    }
                }
            } else {
                $io[self::IO_PLACE] = '';
            }
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
     * @param ?array<string,mixed> $params Parameters for excution
     *
     * @return string Action Url
     */
    protected function BuildURL(string $key, string $action, ?array $params = null): string
    {
        $url = self::SERVICE_BASEURL . $action;
        // quick hack, later better
        if ($key == 'unna') {
            $url = self::SERVICE_FALLBACK . $action;
        }
        $str = ['region' => $key];
        if ($params != null) {
            $str = array_merge($str, $params);
        }
        $this->LogDebug(__FUNCTION__, $url);
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
     * Sends the action url and data to the service provider
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return ?list<array<string,string>> New selecteable options or null.
     */
    protected function ExecuteAction(array &$io): ?array
    {
        $this->LogDebug(__FUNCTION__, $io);

        $data = null;
        $buffer = null;
        switch ($io[self::IO_ACTION]) {
            case self::ACTION_CLIENT:
                // Build URL data
                $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_CITIES);
                // GET Request
                $res = $this->ServiceRequest($url, null);
                // Collect DATA
                if ($res !== false) {
                    $json = json_decode($res, true);
                    foreach ($json as $city) {
                        $data[] = ['caption' => $city['name'], 'value' => (string) $city['name']];
                        $buffer[] = [$city['name'], $city['id']];
                    }
                    $this->SetBuffer(self::SB_PLACE, serialize($buffer));
                    $io[self::IO_ACTION] = self::ACTION_PLACE;
                }
                break;
            case self::ACTION_PLACE:
                // Build URL data
                $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_STREETS, ['city' => $io[self::IO_PLACE]]);
                // GET Request
                $res = $this->ServiceRequest($url, null);
                // Collect DATA
                if ($res !== false) {
                    $json = json_decode($res, true);
                    foreach ($json as $street) {
                        $data[] = ['caption' => $street['name'], 'value' => (string) $street['name']];
                        $buffer[] = [$street['name'], $street['id']];
                    }
                    $this->SetBuffer(self::SB_STREET, serialize($buffer));
                    $io[self::IO_ACTION] = self::ACTION_STREET;
                }
                break;
            case self::ACTION_STREET:
                // Build URL data
                $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_ADDONS, ['city' => $io[self::IO_PLACE], 'street' => $io[self::IO_STREET]]);
                // GET Request
                $res = $this->ServiceRequest($url, null);
                // Collect DATA
                if ($res !== false) {
                    $json = json_decode($res, true);
                    foreach ($json as $street) {
                        if ($street['id'] == $io[self::IO_STREET]) {
                            foreach ($street['hausNrList'] as $number) {
                                $name = ($number['nr'] == '' ? $this->Translate('All') : $number['nr']);
                                $data[] = ['caption' => $name, 'value' => (string) $number['nr']];
                                $buffer[] = [$number['nr'], $number['id']];
                            }
                        }
                    }
                    $this->SetBuffer(self::SB_ADDON, serialize($buffer));
                    $io[self::IO_ACTION] = (empty($data)) ? self::ACTION_FRACTIONS : self::ACTION_ADDON;
                }
                break;
            case self::ACTION_ADDON:
            case self::ACTION_FRACTIONS:
                // Build URL data
                $url = '';
                if ($io[self::IO_ADDON] == '') {
                    $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_FRACTIONS_STREET, ['street' => $io[self::IO_STREET]]);
                } else {
                    $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_FRACTIONS_ADDON, ['addon' => $io[self::IO_ADDON]]);
                }
                // GET Request
                $res = $this->ServiceRequest($url, null);
                // Collect DATA
                if ($res !== false) {
                    $json = json_decode($res, true);
                    $names = [];
                    $fractions = [];
                    foreach ($json as $fraction) {
                        $data[] = ['caption' => $fraction['name'], 'value' => $fraction['id']];
                        $names[$fraction['id']] = $fraction['name'];
                        $fractions[] = $fraction['id'];
                    }
                    $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                    $io[self::IO_FRACTIONS] = implode(',', array_unique($fractions));
                    $io[self::IO_NAMES] = $names;
                }
                break;
            case self::ACTION_DATES:
                // Build URL data
                $url = '';
                if ($io[self::IO_ADDON] == '') {
                    $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_DATES_STREET, ['street' => $io[self::IO_STREET]]);
                } else {
                    $url = $this->BuildURL($io[self::IO_CLIENT], self::GET_DATES_ADDON, ['addon' => $io[self::IO_ADDON]]);
                }
                // GET Request
                $res = $this->ServiceRequest($url, null);
                // Collect DATA
                if ($res !== false) {
                    $json = json_decode($res, true);
                    foreach ($json as $date) {
                        $data[] = ['id' => $date['bezirk']['fraktionId'], 'date' => $date['datum']];
                    }
                    if ($data != null) {
                        $data = $this->OrderData($data, 'date');
                    } else {
                        $this->LogDebug(__FUNCTION__, 'No date collected, all empty!');
                    }
                    $io[self::IO_ACTION] = self::ACTION_DATES;
                }
                break;
        }
        return $data;
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
