<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// CLASS AbfallNavi
class AbfallNavi extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // Service Provider
    private const SERVICE_PROVIDER = 'regio';
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';
    private const SERVICE_BASEURL = 'https://{{region}}-abfallapp.regioit.de/abfall-app-{{region}}';

    // GET actions
    private const GET_CITIES = '/rest/orte';
    private const GET_STREETS = '/rest/orte/{{city}}/strassen';
    private const GET_ADDONS = '/rest/orte/{{city}}/strassen/{{street}}';
    private const GET_FRACTIONS_STREET = '/rest/strassen/{{street}}/fraktionen';
    private const GET_FRACTIONS_ADDON = '/rest/hausnummern/{{addon}}/fraktionen';
    private const GET_DATES_STREET = '/rest/strassen/{{street}}/termine';
    private const GET_DATES_ADDON = '/rest/hausnummern/{{addon}}/termine';

    // IO keys
    private const IO_ACTION = 'action';
    private const IO_CLIENT = 'client';
    private const IO_PLACE = 'place';
    private const IO_STREET = 'street';
    private const IO_ADDON = 'addon';
    private const IO_FRACTIONS = 'fractions';
    private const IO_NAMES = 'names';

    // Buffer keys
    private const SB_PLACE = 'place';
    private const SB_STREET = 'street';
    private const SB_ADDON = 'addon';

    // ACTION Keys
    private const ACTION_CLIENT = 'client';
    private const ACTION_PLACE = 'places';
    private const ACTION_STREET = 'streets';
    private const ACTION_ADDON = 'addons';
    private const ACTION_FRACTIONS = 'fractions';
    private const ACTION_DATES = 'dates';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_REGIO = 3;
    private const ELEM_VISU = 4;

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
        $this->RegisterPropertyString('streetID', 'null');
        $this->RegisterPropertyString('addonID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        // Attributes for dynamic configuration forms (> v3.0)
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'REGIO_Update(' . $this->InstanceID . ');');

        $this->SetBuffer(self::SB_PLACE, '');
        $this->SetBuffer(self::SB_STREET, '');
        $this->SetBuffer(self::SB_ADDON, '');
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
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', streetId=' . $sId . ', addonId=' . $aId);

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_REGIO]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // go throw the whole way
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
            $this->SendDebug(__FUNCTION__, 'Line: ' . __LINE__);
            $next = false;
        }
        // Place
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_PLACE) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][1]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][1]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, 'Line: ' . __LINE__);
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
                        $this->SendDebug(__FUNCTION__, 'Line: ' . __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, 'Line: ' . __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $pId];
                $jsonForm['elements'][self::ELEM_REGIO]['items'][1]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_REGIO]['items'][1]['items'][0]['visible'] = false;
            }
        }
        // Street
        if ($next) {
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_STREET) {
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][0]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][0]['visible'] = true;
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
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
                    $this->SendDebug(__FUNCTION__, __LINE__);
                    $next = false;
                }
            } else {
                $data[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => $sId];
                $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][0]['options'] = $data;
                $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][0]['visible'] = false;
            }
        }
        // Addon
        if ($next) {
            $this->SendDebug(__FUNCTION__, 'ADDON');
            // Fix or Dynamic
            if ($io[self::IO_ACTION] == self::ACTION_ADDON) {
                $this->SendDebug(__FUNCTION__, 'ADDON ACTION');
                if ($options != null) {
                    // Always add the selection prompt
                    array_unshift($options, $prompt);
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][1]['options'] = $options;
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][2]['items'][1]['visible'] = true;
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
                        $this->SendDebug(__FUNCTION__, __LINE__);
                        $next = false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, __LINE__);
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
                    $jsonForm['elements'][self::ELEM_REGIO]['items'][3]['visible'] = true;
                    $i = 1;
                    foreach ($options as $fract) {
                        $jsonForm['elements'][self::ELEM_REGIO]['items'][$i + 3]['caption'] = $fract['caption'];
                        $jsonForm['elements'][self::ELEM_REGIO]['items'][$i + 3]['visible'] = true;
                        $i++;
                    }
                }
            }
        }
        // Write IO array
        $this->WriteAttributeString('io', serialize($io));
        // Debug output
        $this->SendDebug(__FUNCTION__, $io);
        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->SendDebug(__FUNCTION__, 'Translate Waste Visu');
            $jsonForm['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }
        // Return Form
        return json_encode($jsonForm);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $cId = $this->ReadPropertyString('clientID');
        $pId = $this->ReadPropertyString('placeID');
        $sId = $this->ReadPropertyString('streetID');
        $aId = $this->ReadPropertyString('addonID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->SendDebug(__FUNCTION__, 'clientID=' . $cId . ', placeId=' . $pId . ', streetId=' . $sId . ', addonId=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu);
        // Set status
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        if ($io[self::IO_CLIENT] == 'null') {
            $status = 201;
        } elseif ($io[self::IO_FRACTIONS] == '') {
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
     * AWIDO_Update($id);
     */
    public function Update()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        // Resync IDs :(
        $this->GetConfigurationForm();
        // Read data
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        // Get data
        $io[self::IO_ACTION] = self::ACTION_DATES;
        $data = $this->ExecuteAction($io);
        if ($data == null) {
            $this->SendDebug(__FUNCTION__, 'Service Request failed!');
            return;
        }
        // fractions convert to name => ident
        $i = 1;
        $waste = [];
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i++);
            if ($enabled) {
                $waste[$ident] = ['ident' => $ident, 'date' => ''];
            }
        }
        $this->SendDebug(__FUNCTION__, $waste);
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
        $this->SendDebug(__FUNCTION__, $waste);
        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $var['ident'], $var['date']);
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->SendDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
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
        $this->SendDebug(__FUNCTION__, $id);
        $io = $this->PrepareIO(self::ACTION_CLIENT, $id);
        $this->SendDebug(__FUNCTION__, $io);
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
        $this->SendDebug(__FUNCTION__, $io);
        // Bad Hack if no addon!!!
        if ($io[self::IO_ACTION] == self::ACTION_FRACTIONS) {
            $this->SendDebug(__FUNCTION__, 'No Addons!!!');
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
     * Hide/unhide form fields.
     *
     */
    protected function UpdateForm($io, $options)
    {
        $this->SendDebug(__FUNCTION__, $io);
        //$this->SendDebug(__FUNCTION__, $options);
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
     */
    protected function CreateVariables()
    {
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_NAMES])) {
            $this->SendDebug(__FUNCTION__, 'No names!');
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        $ids = explode(',', $io[self::IO_FRACTIONS]);
        foreach ($ids as $fract) {
            if ($i <= static::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract, $io[self::IO_NAMES][$fract], VARIABLETYPE_STRING, '', $i, $enabled || $variable);
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
     * @param string $s street id value
     * @param string $a addon id value
     * @param string $f fraction id value
     * @return array IO interface
     */
    protected function PrepareIO($n = null, $c = 'null', $p = 'null', $s = 'null', $a = 'null', $f = 'null')
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
     * @param array $params Parameters for excution
     * @return string Action Url
     */
    protected function BuildURL($key, $action, $params = null)
    {
        $url = self::SERVICE_BASEURL . $action;
        $str = ['region' => $key];
        if ($params != null) {
            $str = array_merge($str, $params);
        }
        $this->SendDebug(__FUNCTION__, $url);
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
     * Sends the action url an  data to the service provider
     *
     * @param $io IO forms array
     * @return array New selecteable options or null.
     */
    protected function ExecuteAction(&$io)
    {
        $this->SendDebug(__FUNCTION__, $io);

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
                    $data = $this->OrderData($data, 'date');
                    $io[self::IO_ACTION] = self::ACTION_DATES;
                }
                break;
        }
        return $data;
    }
}
