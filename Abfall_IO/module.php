<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';
// ICS Parser
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

use ICal\ICal;

// CLASS Abfall_IO
class Abfall_IO extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;

    // Service Provider
    const SERVICE_PROVIDER = 'abpio';
    const SERVICE_USERAGENT = "'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36";
    const SERVICE_MODUSKEY = 'd6c5855a62cf32a4dadbc2831f0f295f';
    const SERVICE_BASEURL = 'https://api.abfall.io/';

    // IO keys
    const IO_ACTION = 'action';
    const IO_CLIENT = 'key';
    const IO_NAMES = 'names';
    const IO_PLACE = 'f_id_kommune';
    const IO_DISTRICT = 'f_id_bezirk';
    const IO_STREET = 'f_id_strasse';
    const IO_ADDON = 'f_id_strasse_hnr';
    const IO_FRACTIONS = 'f_abfallarten';

    // ACTION Keys
    const ACTION_CLIENT = 'init';
    const ACTION_PLACE = 'auswahl_kommune_set';
    const ACTION_DISTRICT = 'auswahl_bezirk_set';
    const ACTION_STREET = 'auswahl_strasse_set';
    const ACTION_ADDON = 'auswahl_hnr_set';
    const ACTION_FRACTIONS = 'auswahl_fraktionen_set';
    const ACTION_EXPORT = 'export_';

    // Form Elements Positions
    const ELEM_IMAGE = 0;
    const ELEM_LABEL = 1;
    const ELEM_PROVI = 2;
    const ELEM_ABPIO = 3;

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
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyString('placeID', 'null');
        $this->RegisterPropertyString('districtID', 'null');
        $this->RegisterPropertyString('streetID', 'null');
        $this->RegisterPropertyString('addonID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        $this->RegisterPropertyString('settingsFormat', 'ics');
        // Attributes for dynamic configuration forms (> v3.0)
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'ABPIO_Update(' . $this->InstanceID . ');');
        // Buffer for ICS/CSV data
        //$this->SetBuffer('ics_csv', '');
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
        $cId = $this->ReadPropertyString('clientID');
        $pId = $this->ReadPropertyString('placeID');
        $dId = $this->ReadPropertyString('districtID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', districtId=' . $dId . ', streetId=' . $sId . ', addonId=' . $aId); // . ', fractIds=' . $fId);

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_ABPIO]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // go throw thw whole way
        $next = true;
        // Build io array
        $io = $this->PrepareIO();
        // Client
        if ($cId != 'null') {
            $io[self::IO_CLIENT] = $cId;
            $options = $this->ExecuteAction($io);
            if ($options == null) {
                $next = false;
            }
        } else {
            $this->SendDebug(__FUNCTION__, __LINE__);
            $next = false;
        }
        // Place
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_PLACE) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($pId != 'null') {
                    $io[self::IO_PLACE] = $pId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $pId];
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][0]['visible'] = false;
            }
        }
        // District
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_DISTRICT) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][1]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][1]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($dId != 'null') {
                    $io[self::IO_DISTRICT] = $dId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
                    if ($options == null) {
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $dId];
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][1]['options'] = $data;
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][1]['items'][1]['visible'] = false;
            }
        }
        // Street
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_STREET) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($sId != 'null') {
                    $io[self::IO_STREET] = $sId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
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
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][0]['visible'] = false;
            }
        }
        // Addon
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][1]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][1]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
                if ($aId != 'null') {
                    $io[self::IO_ADDON] = $aId;
                    // than prepeare the next
                    $options = $this->ExecuteAction($io);
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
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][1]['options'] = $data;
                $jsonForm['elements'][self::ELEM_ABPIO]['items'][2]['items'][1]['visible'] = false;
            }
        }
        // Fractions
        if ($next) {
            //$io[self::IO_FRACTIONS] = $fId;
            if ($io[self::IO_ACTION] == self::ACTION_FRACTIONS) {
                if ($options != null) {
                    // Label
                    $jsonForm['elements'][self::ELEM_ABPIO]['items'][3]['visible'] = true;
                    $i = 1;
                    foreach ($options as $fract) {
                        $jsonForm['elements'][self::ELEM_ABPIO]['items'][$i + 3]['caption'] = $fract['caption'];
                        $jsonForm['elements'][self::ELEM_ABPIO]['items'][$i + 3]['visible'] = true;
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
        $cId = $this->ReadPropertyString('clientID');
        $pId = $this->ReadPropertyString('placeID');
        $dId = $this->ReadPropertyString('districtID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $this->SendDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', districtId=' . $dId . ', streetId=' . $sId . ', addonId=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
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
            $this->CreateVariables();
            if ($activate == true) {
                // Time neu berechnen
                $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
                $this->SendDebug(__FUNCTION__, 'Timer aktiviert!');
            } else {
                $status = 104;
            }
        }
        //$this->SetBuffer('ics_csv', '');
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
     * Fix waste name.
     *
     *  @param string $from Old waste name.
     *  @param string $to New waste name.
     */
    public function FixWasteName(string $from, string $to)
    {
        $io = unserialize($this->ReadAttributeString('io'));
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            if ($name === $from) {
                $io[self::IO_NAMES][$ident] = $to;
            }
        }
        $this->SendDebug(__FUNCTION__, $io);
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * AWIDO_Update($id);
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
        $fmt = $this->ReadPropertyString('settingsFormat');
        $this->SendDebug(__FUNCTION__, 'Formnat: ' . $fmt);

        //$res = $this->GetBuffer('ics_csv');
        if (true) {
            // Extract Token
            $token = $this->ExecuteInit($io[self::IO_CLIENT]);
            if ($token == null) {
                $this->SendDebug(__FUNCTION__, 'Token is null!');
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
            if (!empty($params)) {
                $request = implode('&', $params);
            }
            $this->SendDebug(__FUNCTION__, $request);
            // Build URL data
            $url = $this->BuildURL($io['key'], self::ACTION_EXPORT . $fmt);
            // Send Export request
            $res = $this->ServiceRequest($url, $request);
            // Store new ICS/CSV data
            if ($res !== false) {
                //$this->SetBuffer('ics_csv', $res);
                //$this->SendDebug(__FUNCTION__, $res);
            } else {
                $this->SendDebug(__FUNCTION__, 'Service Request failed!');
                return;
            }
        }

        // fractions convert to name => ident
        $vars = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $vars[$name] = ['ident' => $ident, 'date' => ''];
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
                $this->SendDebug(__FUNCTION__, 'initICS: ' . $e);
                return;
            }

            // get all events
            $events = $ical->events();

            // go throw all events
            $this->SendDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
            foreach ($events as $event) {
                //$this->SendDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
                // echo $event->printData('%s: %s'.PHP_EOL);
                if ($event->dtstart < date('Ymd')) {
                    continue;
                }
                // YYYYMMDD umwandeln in DD.MM.YYYY
                $day = substr($event->dtstart, 6) . '.' . substr($event->dtstart, 4, 2) . '.' . substr($event->dtstart, 0, 4);
                // Update fraction
                if (isset($vars[$event->summary]) && $vars[$event->summary]['date'] == '') {
                    $vars[$event->summary]['date'] = $day;
                    $this->SendDebug(__FUNCTION__, 'Fraction date: ' . $event->summary . ' = ' . $event->dtstart);
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
            foreach ($csv as $waste) {
                $count = count($waste);
                $name = utf8_encode($waste[0]);
                $this->SendDebug(__FUNCTION__, 'Fraction name: ' . $name);
                for ($i = 1; $i < $count; $i++) {
                    if (empty($waste[$i])) {
                        continue;
                    }
                    $day = substr($waste[$i], 6) . substr($waste[$i], 3, 2) . substr($waste[$i], 0, 2);
                    if ($day < $now) {
                        continue;
                    }
                    // Update fraction
                    if (isset($vars[$name]) && $vars[$name]['date'] == '') {
                        $vars[$name]['date'] = $waste[$i];
                        $this->SendDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $waste[$i]);
                    }
                }
            }
        }

        // write data to variable
        foreach ($vars as $key => $var) {
            $varId = @$this->GetIDForIdent($var['ident']);
            if ($varId != 0) {
                SetValueString($varId, $var['date']);
            }
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
     * @param string $id Client ID .
     */
    protected function OnChangeClient($id)
    {
        // ACTION: 'init', KEY: $id
        $io = $this->PrepareIO(self::ACTION_CLIENT, $id);
        $this->SendDebug(__FUNCTION__, $io);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        $this->SendDebug(__FUNCTION__, $io);
        // Bad fix for cities only!!!
        if ($io[self::IO_ACTION] == self::ACTION_STREET) {
            $this->UpdateFormField('placeID', 'visible', false);
            $this->UpdateFormField('districtID', 'visible', false);
            // Fix Options
            if ($io[self::IO_PLACE] == '') {
                $this->UpdateFormField('placeID', 'value', 'null');
            } else {
                $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $io[self::IO_PLACE]];
                $this->UpdateFormField('placeID', 'options', json_encode($options));
                $this->UpdateFormField('placeID', 'value', $io[self::IO_PLACE]);
            }
            if ($io[self::IO_DISTRICT] == '') {
                $this->UpdateFormField('placeID', 'value', 'null');
            } else {
                $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $io[self::IO_DISTRICT]];
                $this->UpdateFormField('placeID', 'options', json_encode($options));
                $this->UpdateFormField('placeID', 'value', $io[self::IO_DISTRICT]);
            }
        }
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new place.
     *
     * @param string $id Place GUID .
     */
    protected function OnChangePlace($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_PLACE, $id);
        $data = null;
        if ($id != 'null') {
            $data = $this->ExecuteAction($io);
        }
        $this->SendDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io, $data);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id istrict GUID .
     */
    protected function OnChangeDistrict($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $this->UpdateIO($io, self::ACTION_DISTRICT, $id);
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
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID .
     */
    protected function OnChangeStreet($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
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
     */
    protected function OnChangeAddon($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
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
     * Returns for the dropdown menu the selectable district in the desorking area.
     *
     * @return array List of places.
     */
    protected function GetDistinctOptions()
    {
        //$this->SendDebug(__FUNCTION__, $cId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        //$options[] = ['caption' => $place->value, 'value' => $place->key];
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable streets in the desorking area.
     *
     * @return array List of streets.
     */
    protected function GetStreetOptions()
    {
        //$this->SendDebug(__FUNCTION__, $pId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        //$options[] = ['caption' => $street->value, 'value' => $street->key];
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable house numbers in the desorking area.
     *
     * @return string List of house numbers.
     */
    protected function GetAddonOptions()
    {
        //$this->SendDebug(__FUNCTION__, $sId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        //$options[] = ['caption' => $addon->value, 'value' => $addon->key];
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Delivers the offered disposals for the selected street.
     */
    protected function GetFractionOptions()
    {
        //$this->SendDebug(__FUNCTION__, $cId);
        // Options
        $options = [];
        // $options[] = ['id' => $fract->id, 'caption' => $fract->nm . ' (' . $fract->snm . ')'];
        return $options;
    }

    /**
     * Hide/unhide form fields.
     *
     */
    protected function UpdateForm($io, $options)
    {
        $this->SendDebug(__FUNCTION__, $io);
        $this->SendDebug(__FUNCTION__, $options);
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
                // Elements below
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
     * @param string $c client id value
     * @param string $p place id value
     * @param string $d district id value
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     * @return array IO interface
     */
    protected function PrepareIO($n = null, $c = 'null', $p = 'null', $d = 'null', $s = 'null', $a = 'null', $f = 'null')
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
     * @return string Action Url
     */
    protected function BuildURL($key, $action)
    {
        $url = '{{base}}?key={{key}}&modus={{modus}}&waction={{action}}';
        $str = ['base' => self::SERVICE_BASEURL, 'key' => $key, 'modus' => self::SERVICE_MODUSKEY, 'action' => $action];
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
     * Sends the action url to extract the token pair
     *
     * @param string API key
     * @return string Token for Export.
     */
    protected function ExecuteInit($key)
    {
        $this->SendDebug(__FUNCTION__, $key);
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
                    $this->SendDebug(__FUNCTION__, 'Token: ' . $token);
                }
            }
        }
        return $token;
    }

    /**
     * Sends the action url an d data to the service provider
     *
     * @param $io IO forms array
     * @return array New selecteable options or null.
     */
    protected function ExecuteAction(&$io)
    {
        $this->SendDebug(__FUNCTION__, $io);
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
                $this->SendDebug(__FUNCTION__, 'Hidden: ' . $name . ':' . $value);
            }
            $divs = $res->query("//div[@class='awk-ui-input-tr']");
            if ($divs->length > 0) {
                $items = [];
                $fractions = [];
                $name = [];
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
                $this->SendDebug(__FUNCTION__, 'select:' . $io['action']);
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
            // $this->SendDebug(__FUNCTION__, $response);
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
