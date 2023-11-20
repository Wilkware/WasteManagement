<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';
// ICS Parser
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

use ICal\ICal;

// CLASS MuellMax
class MuellMax extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // Service Provider
    private const SERVICE_PROVIDER = 'maxde';
    private const SERVICE_USERAGENT = "'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36";
    private const SERVICE_BASEURL = 'https://www.muellmax.de/abfallkalender/';

    // IO keys
    private const IO_ACTION = 'action';
    private const IO_DISPOSAL = 'key';
    private const IO_NAMES = 'names';
    private const IO_SECURE = 'mm_ses';
    private const IO_CITY = 'mm_city';
    private const IO_STREET = 'mm_street';
    private const IO_ADDON = 'mm_addon';
    private const IO_FRACTIONS = 'mm_fractions';

    // ACTION Keys
    private const ACTION_INIT = 'init';
    private const ACTION_CITY = 'city';
    private const ACTION_STREET = 'street';
    private const ACTION_ADDON = 'addon';
    private const ACTION_FRACTIONS = 'fractions';
    private const ACTION_EXPORT = 'export';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_WASTE = 3;

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        // Waste Management
        $this->RegisterPropertyString('disposalID', 'null');
        $this->RegisterPropertyString('cityID', 'null');
        $this->RegisterPropertyString('streetID', 'null');
        $this->RegisterPropertyString('addonID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileSkin', 'dark');
        // Attributes for dynamic configuration forms (> v3.0)
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'MAXDE_Update(' . $this->InstanceID . ');');
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Settings
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        // IO Values
        $dId = $this->ReadPropertyString('disposalID');
        $cId = $this->ReadPropertyString('cityID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'disposalID=' . $dId . ', cityID=' . $cId . ', streetId=' . $sId . ', addonId=' . $aId);

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // go throw the whole way
        $args = [];
        $next = true;
        // Build io array
        $io = $this->PrepareIO();
        // Disposal
        if ($dId != 'null') {
            $io[self::IO_DISPOSAL] = $dId;
            $this->SendDebug(__FUNCTION__, 'ACTION: Init Disposl!');
            $this->ExecuteAction($io);
            if ($io[self::IO_SECURE] == '') {
                $this->SendDebug(__FUNCTION__, 'Init secure token failed!');
                $next = false;
            }
        } else {
            $this->SendDebug(__FUNCTION__, __LINE__);
            $next = false;
        }
        // City or Streets
        if ($next) {
            $args[] = 'mm_ses=' . $io[self::IO_SECURE];
            $args[] = 'mm_aus_ort.x=1';
            $args[] = 'mm_aus_ort.y=1';
            $this->SendDebug(__FUNCTION__, 'ACTION: City or Street!');
            $options = $this->ExecuteAction($io, $args);
            // no city only streets
            if ($io[self::IO_ACTION] != self::ACTION_CITY) {
                unset($args);
                $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                $args[] = 'xxx=1';
                $args[] = 'mm_frm_str_name=';
                $args[] = 'mm_aus_str_txt_submit=suchen';
                $this->SendDebug(__FUNCTION__, 'ACTION: No city only streets!');
                $options = $this->ExecuteAction($io, $args);
            }
            if (($io[self::IO_ACTION] != self::ACTION_CITY) && ($io[self::IO_ACTION] != self::ACTION_STREET)) {
                $this->SendDebug(__FUNCTION__, 'No city or street received: ' . $io[self::IO_ACTION]);
                $next = false;
            }
        }
        // City
        if ($next) {
            if ($io[self::IO_ACTION] == self::ACTION_CITY) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
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
                    $this->SendDebug(__FUNCTION__, 'ACTION: City and now streets!');
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
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $cId];
                $jsonForm['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = false;
            }
        }
        // Street
        if ($next) {
            if ($io[self::IO_ACTION] == self::ACTION_STREET) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
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
                    $this->SendDebug(__FUNCTION__, 'ACTION: Street selected!');
                    $options = $this->ExecuteAction($io, $args);
                    // Get Fractions ?
                    if ($io[self::IO_ACTION] != self::ACTION_ADDON) {
                        $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                        unset($args);
                        $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                        $args[] = 'xxx=1';
                        $args[] = 'mm_ica_auswahl=iCalendar-Datei';
                        $this->SendDebug(__FUNCTION__, 'ACTION: No Addon - get fractions!');
                        $options = $this->ExecuteAction($io, $args);
                    }
                    if ($options == null) {
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $sId];
                $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['visible'] = false;
            }
        }
        // Addon
        if ($next) {
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                $this->SendDebug(__FUNCTION__, 'ADDON');
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($aId != 'null') {
                    $io[self::IO_ADDON] = $aId;
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_frm_hnr_sel=' . $io[self::IO_ADDON];
                    $args[] = 'mm_aus_hnr_sel_submit=weiter';
                    $this->SendDebug(__FUNCTION__, 'ACTION: Addon selected!');
                    $options = $this->ExecuteAction($io, $args);
                    // Get Fractions
                    $io[self::IO_ACTION] = self::ACTION_FRACTIONS;
                    unset($args);
                    $args[] = 'mm_ses=' . $io[self::IO_SECURE];
                    $args[] = 'xxx=1';
                    $args[] = 'mm_ica_auswahl=iCalendar-Datei';
                    $this->SendDebug(__FUNCTION__, 'ACTION: And now fractions!');
                    $options = $this->ExecuteAction($io, $args);
                    if ($options == null) {
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $aId];
                $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $data;
                $jsonForm['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = false;
            }
        }
        // Fractions
        if ($next) {
            if ($io[self::IO_ACTION] == self::ACTION_FRACTIONS) {
                if ($options != null) {
                    // Label
                    $jsonForm['elements'][self::ELEM_WASTE]['items'][3]['visible'] = true;
                    $i = 1;
                    foreach ($options as $fract) {
                        $jsonForm['elements'][self::ELEM_WASTE]['items'][$i + 3]['caption'] = $fract['caption'];
                        $jsonForm['elements'][self::ELEM_WASTE]['items'][$i + 3]['visible'] = true;
                        $i++;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $this->SendDebug(__FUNCTION__, __LINE__);
                $next = false;
            }
        }

        // Write IO array
        $this->WriteAttributeString('io', serialize($io));

        // Debug output
        $this->SendDebug(__FUNCTION__, $io);
        // Return Form
        return json_encode($jsonForm);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $dId = $this->ReadPropertyString('disposalID');
        $cId = $this->ReadPropertyString('cityID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->SendDebug(__FUNCTION__, 'disposalID=' . $dId . ', cityID=' . $cId . ', streetId=' . $sId . ', addonId=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), vtString, '~HTMLBox', 0, $tilevisu);
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
            $this->CreateVariables();
            if ($activate == true) {
                // Time neu berechnen
                $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
                $this->SendDebug(__FUNCTION__, 'Timer aktiviert!');
            } else {
                $status = 104;
            }
        }
        $this->SetStatus($status);
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident (function name).
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        eval('$this->' . $ident . '(\'' . $value . '\');');
        return true;
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * MAXDE_Update($id);
     */
    public function Update()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);

        // (1) Build io array & init
        $uio = $this->PrepareIO();
        $uio[self::IO_DISPOSAL] = $io[self::IO_DISPOSAL];
        $this->ExecuteAction($uio);
        if ($uio[self::IO_SECURE] == '') {
            $this->SendDebug(__FUNCTION__, 'Init secure token failed!');
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
        $waste = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $waste[$name] = ['ident' => $ident, 'date' => ''];
            $args[] = 'mm_frm_fra_' . $ident . '=' . $ident;
        }
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
            $this->SendDebug(__FUNCTION__, 'initICS: ' . $e);
            return;
        }
        // get all events
        $events = $ical->events();
        // go throw all events
        $this->SendDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            $this->SendDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
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
                $this->SendDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $day);
            }
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $var['ident'], $var['date']);
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $skin = $this->ReadPropertyString('settingsTileSkin');
        $this->SendDebug(__FUNCTION__, 'TileVisu: ' . $btw . '(' . $skin . ')');
        if ($btw == true) {
            $this->BuildWidget($waste, $skin);
        }

        // execute Script
        $script = $this->ReadPropertyInteger('settingsScript');
        if ($script != 0) {
            if (IPS_ScriptExists($script)) {
                $rs = IPS_RunScript($script);
                $this->SendDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs, 0);
            } else {
                $this->SendDebug(__FUNCTION__, 'Update: Script #' . $script . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }
    }

    /**
     * User has selected a new waste management.
     *
     * @param string $id Disposal ID .
     */
    protected function OnChangeDisposal($id)
    {
        // ACTION: 'init', KEY: $id
        $io = $this->PrepareIO(self::ACTION_INIT, $id);
        $this->SendDebug(__FUNCTION__, $io);
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
        $this->SendDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new city.
     *
     * @param string $id City ID .
     */
    protected function OnChangeCity($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
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
            $this->SendDebug(__FUNCTION__, 'No street for city!');
            $this->SendDebug(__FUNCTION__, $io);
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
     */
    protected function OnChangeStreet($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
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
     */
    protected function OnChangeAddon($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
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
        $this->SendDebug(__FUNCTION__, $data);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Hide/unhide form fields.
     *
     */
    protected function UpdateForm($io, $options)
    {
        $this->SendDebug(__FUNCTION__, $io);
        $this->SendDebug(__FUNCTION__, $options);
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = $f; $i <= static::$FRACTIONS; $i++) {
                    $this->UpdateFormField('fractionID' . $i, 'visible', false);
                    $this->UpdateFormField('fractionID' . $i, 'value', false);
                }
                break;
        }
    }

    /**
     * Create the variables for the fractions.
     *
     */
    protected function CreateVariables()
    {
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_NAMES])) {
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        $ids = explode(',', $io[self::IO_FRACTIONS]);
        foreach ($ids as $fract) {
            if ($i <= static::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract, $io[self::IO_NAMES][$fract], vtString, '', $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @param string $n next from action
     * @param string $d disposal id value
     * @param string $c city id value
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     * @return array IO interface
     */
    protected function PrepareIO($n = null, $d = 'null', $c = 'null', $s = 'null', $a = 'null', $f = 'null')
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
     * @param array $io IO interface array
     * @param string $action new form action
     * @param string $id new selected form value
     */
    protected function UpdateIO(&$io, $action, $id)
    {
        $this->SendDebug(__FUNCTION__, $action);
        $this->SendDebug(__FUNCTION__, $id);
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
     * @param string $key Disposal ID
     * @param string $action Get parameter action.
     */
    protected function BuildURL($key)
    {
        $url = '{{base}}{{key}}/res/{{start}}Start.php';
        $str = ['base' => self::SERVICE_BASEURL, 'key' => strtolower($key), 'start' => $key];
        // replace all
        if (preg_match_all('/{{(.*?)}}/', $url, $m)) {
            foreach ($m[1] as $i => $varname) {
                $url = str_replace($m[0][$i], sprintf('%s', $str[$varname]), $url);
            }
        }
        $this->SendDebug(__FUNCTION__, $url);
        return $url;
    }

    /**
     * Sends the action url and data to the service provider
     *
     * @param $io service array
     * @param $args forms array
     * @return array New selecteable options or null.
     */
    protected function ExecuteAction(&$io, $args = [])
    {
        $this->SendDebug(__FUNCTION__, $io);
        // Build URL data
        $url = $this->BuildURL($io['key']);
        // GET or POST data
        $request = null;
        if (!empty($args)) {
            $request = implode('&', $args);
        }
        $this->SendDebug(__FUNCTION__, 'Rerquest: ' . $request);
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
                $this->SendDebug(__FUNCTION__, 'Hidden: ' . $name . ':' . $value);
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
                        //$this->SendDebug(__FUNCTION__, 'City: ' . $name . ':' . $value);
                    }
                    $this->SendDebug(__FUNCTION__, 'RETURN : City');
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
                        //$this->SendDebug(__FUNCTION__, 'Street: ' . $name . ':' . $value);
                    }
                    $this->SendDebug(__FUNCTION__, 'RETURN : Street');
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
                        //$this->SendDebug(__FUNCTION__, 'Addon: ' . $name . ':' . $value);
                    }
                    $this->SendDebug(__FUNCTION__, 'RETURN : Addon');
                    return $data;
                }
            }
            // fraction

            $divs = $res->query("//div[@class='m_artsel_ical']");
            if ($divs->length > 0) {
                $this->SendDebug(__FUNCTION__, 'Fractions: YES');
                $fractions = [];
                $name = [];
                foreach ($divs as $div) {
                    $inputs = $res->query(".//input[@type='checkbox']", $div);
                    $spans = $res->query(".//span[@class='m_artsel_text']/text()", $div);
                    $name = $spans->item(0)->nodeValue;  // clear name
                    $value = $inputs->item(0)->getAttribute('value'); // value
                    // store
                    $data[] = ['caption' => $name, 'value' => $inputs->item(0)->getAttribute('value')];
                    $fractions[] = $value;
                    $names[$value] = $name;
                    $this->SendDebug(__FUNCTION__, 'Fraction: ' . $name . ':' . $value);
                }
                $io[self::IO_FRACTIONS] = implode(',', array_unique($fractions));
                $io[self::IO_NAMES] = $names;
            }
        }
        $this->SendDebug(__FUNCTION__, 'RETURN : Last');
        return $data;
    }

    /**
     * Sends the API call and transform it to a XPath Document
     *
     * @param string $url Request URL
     * @param string $request Request parameters
     * @return mixed DOM document
     */
    protected function GetDocument($url, $request)
    {
        $response = $this->ServiceRequest($url, $request);
        if ($response !== false) {
            //$this->SendDebug(__FUNCTION__, $response);
            $dom = new DOMDocument();
            // disable libxml errors
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($response, 'HTML-ENTITIES', 'UTF-8'));
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
     * @param string $url Rewquest URL
     * @param string $request If $request not null, we will send a POST request, else a GET request.
     * @param string $method Over the $method parameter can we force a POST or GET request!
     * @return mixed False if the response is null, otherwise the response
     */
    protected function ServiceRequest($url, $request, $method = 'GET')
    {
        // Return
        $ret = false;
        // CURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
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
        //$this->SendDebug(__FUNCTION__, $response);
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
     * @param bool Returns true if haystack begins with needle, false otherwise.
     */
    private function StartsWith($haystack, $needle)
    {
        return (string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
