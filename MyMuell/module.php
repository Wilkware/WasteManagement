<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// CLASS MyMuell
class MyMuell extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // Service Provider
    private const SERVICE_PROVIDER = 'mymde';
    private const SERVICE_APIURL = '.jumomind.com/mmapp/api.php?';

    // IO keys
    private const IO_NAMES = 'names';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_MYMDE = 3;
    private const ELEM_VISU = 4;

    /**
     * Create.
     */
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        $this->RegisterPropertyString('serviceCountry', 'de');
        // Waste Management
        $this->RegisterPropertyString('domainID', 'null');
        $this->RegisterPropertyString('cityID', 'null');
        $this->RegisterPropertyString('areaID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Attributes for dynamic configuration forms
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));
        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        $this->RegisterPropertyBoolean('settingsLookAhead', false);
        $this->RegisterPropertyString('settingsLookTime', '{"hour":12,"minute":0,"second":0}');
        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'MYMDE_Update(' . $this->InstanceID . ');');
        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'MYMDE_LookAhead(' . $this->InstanceID . ');');
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
        // Service Values
        $country = $this->ReadPropertyString('serviceCountry');
        // IO Values
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'domainID=' . $dId . ',cityID=' . $cId . ', areaId=' . $aId);
        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $jsonForm['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);
        // Waste Management
        $jsonForm['elements'][self::ELEM_MYMDE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Domain (client)
        if ($dId != 'null') {
            $options = $this->RequestCities($dId);
            if ($options != null) {
                // Always add the selection prompt
                array_unshift($options, $prompt);
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][1]['items'][0]['options'] = $options;
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][1]['items'][0]['visible'] = true;
            }
        } else {
            $this->SendDebug(__FUNCTION__, __LINE__);
            $cId = null;
        }
        // Streets/Areas
        if ($cId != 'null') {
            $options = $this->RequestAreas($dId, $cId);
            if ($options != null) {
                // Always add the selection prompt
                array_unshift($options, $prompt);
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][2]['items'][0]['options'] = $options;
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][2]['items'][0]['visible'] = true;
            }
        } else {
            $aId = null;
        }
        // Fractions
        if ($aId != null) {
            $options = $this->RequestFractions($dId, $cId, $aId);
            if ($options != null) {
                // Label
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][3]['visible'] = true;
                $i = 1;
                foreach ($options as $fract) {
                    $jsonForm['elements'][self::ELEM_MYMDE]['items'][$i + 3]['caption'] = $fract['caption'];
                    $jsonForm['elements'][self::ELEM_MYMDE]['items'][$i + 3]['visible'] = true;
                    $i++;
                }
            }
        }
        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->SendDebug(__FUNCTION__, 'Translate Waste Visu');
            $jsonForm['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }

        // Return Form
        return json_encode($jsonForm);
    }

    /**
     * Apply Changes.
     *
     */
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->SendDebug(__FUNCTION__, 'domainID=' . $dId . 'cityID=' . $cId . ', areaID=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu);
        // Set status
        if ($dId == 'null') {
            $status = 201;
        } elseif ($cId == 'null') {
            $status = 201;
        } elseif ($aId == 'null') {
            $status = 202;
        } else {
            $status = 102;
        }
        // All okay
        if ($status == 102) {
            $this->CreateVariables($dId, $cId, $aId);
            if ($activate == true) {
                // Time neu berechnen
                $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
                $this->SendDebug(__FUNCTION__, 'Update Timer aktiviert!');
                if ($loakahead & $tilevisu) {
                    $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
                    if (($time['hour'] == 0) && ($time['minute'] <= 30)) {
                        $this->SendDebug(__FUNCTION__, 'LookAhead Time zu niedrieg!');
                    } else {
                        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second'], 0);
                    }
                }
            } else {
                $status = 104;
            }
        }
        $this->SetStatus($status);
    }

    /**
     * Request Action.
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
     * MYMDE_LookAhead($id);
     */
    public function LookAhead()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        // rebuild informations
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        $i = 1;
        $waste = [];
        // fractions convert to name => ident
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i++);
            if ($enabled) {
                $date = $this->GetValue($ident);
                $waste[$name] = ['ident' => $ident, 'date' => $date];
            }
        }
        $this->SendDebug(__FUNCTION__, $waste);
        // update tile widget
        $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        $this->BuildWidget($waste, $list, true);
        // Set Timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * MYMDE_Update($id);
     */
    public function Update()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        $waste = [];
        // Build URL data
        $url = $this->BuildURL('dates', $dId, $cId, $aId);
        // Request Data
        $json = @file_get_contents($url);
        // Collect DATA
        if ($json !== false) {
            $data = json_decode($json, true);
            foreach ($data as $entry) {
                if ((!isset($waste[$entry['trash_name']])) && (in_array($entry['title'], $io[self::IO_NAMES]))) {
                    $waste[$entry['trash_name']] = ['ident' => $entry['trash_name'], 'date' => date('d.m.Y', strtotime($entry['day'])), 'title' => $entry['title']];
                }
            }
        } else {
            $this->LogMessage($this->Translate('Could not load json data!'), KL_ERROR);
            $this->SendDebug(__FUNCTION__, 'Error: Could not load json data!');
            return;
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $key, $var['date']);
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
     * User has selected a new waste management country.
     *
     * @param string $id Country ID.
     */
    protected function OnChangeCountry($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
        $options = $this->GetClientOptions(self::SERVICE_PROVIDER, $id);
        $this->UpdateFormField('domainID', 'options', json_encode($options));
        $this->UpdateFormField('domainID', 'visible', true);
        $this->UpdateFormField('domainID', 'value', 'null');
        $this->OnChangeDomain('null');
    }

    /**
     * User has selected a new waste management domain.
     *
     * @param string $id Domain ID.
     */
    protected function OnChangeDomain($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
        $options = null;
        if ($id != 'null') {
            $options = $this->RequestCities($id);
        }
        // Cities
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        if ($options != null) {
            // Always add the selection prompt
            array_unshift($options, $prompt);
            $this->SendDebug(__FUNCTION__, $options);
            $this->UpdateFormField('cityID', 'options', json_encode($options));
            $this->UpdateFormField('cityID', 'visible', true);
            $this->UpdateFormField('cityID', 'value', 'null');
        } else {
            $options = [];
            // Only add the selection prompt
            array_unshift($options, $prompt);
            $this->UpdateFormField('cityID', 'options', json_encode($options));
            $this->UpdateFormField('cityID', 'visible', false);
            $this->UpdateFormField('cityID', 'value', 'null');
        }
        // Area
        $options = [];
        // Only add the selection prompt
        array_unshift($options, $prompt);
        $this->UpdateFormField('areaID', 'options', json_encode($options));
        $this->UpdateFormField('areaID', 'visible', false);
        $this->UpdateFormField('areaID', 'value', 'null');
        // Fraction
        $this->UpdateFormField('fractionLabel', 'visible', false);
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'value', false);
            $this->UpdateFormField('fractionID' . $i, 'visible', false);
        }
    }

    /**
     * User has selected a new waste management city.
     *
     * @param string $value Domain & City ID.
     */
    protected function OnChangeCity($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        $dId = $data['domain'];
        $cId = $data['city'];

        $options = null;
        if ($cId != 'null') {
            $options = $this->RequestAreas($dId, $cId);
        }
        // Area
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        if ($options != null) {
            // Always add the selection prompt
            array_unshift($options, $prompt);
            $this->UpdateFormField('areaID', 'options', json_encode($options));
            $this->UpdateFormField('areaID', 'visible', true);
            $this->UpdateFormField('areaID', 'value', 'null');
        } else {
            $options = [];
            // Only add the selection prompt
            array_unshift($options, $prompt);
            $this->UpdateFormField('areaID', 'options', json_encode($options));
            $this->UpdateFormField('areaID', 'visible', false);
            $this->UpdateFormField('areaID', 'value', 'null');
        }
        // Fraction
        $this->UpdateFormField('fractionLabel', 'visible', false);
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'value', false);
            $this->UpdateFormField('fractionID' . $i, 'visible', false);
        }
    }

    /**
     * User has selected a new street or district.
     *
     * @param string $value Domain & City & Area ID.
     */
    protected function OnChangeArea($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        $dId = $data['domain'];
        $cId = $data['city'];
        $aId = $data['area'];

        $options = null;
        if ($aId != 'null') {
            $options = $this->RequestFractions($dId, $cId, $aId);
        }
        if ($options != null) {
            // Label
            $this->UpdateFormField('fractionLabel', 'visible', true);
            $i = 1;
            // only available fraction visible
            foreach ($options as $fract) {
                $this->UpdateFormField('fractionID' . $i, 'caption', $fract['caption']);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
                $this->UpdateFormField('fractionID' . $i, 'visible', true);
                $i++;
            }
            // rest hidden
            for ($r = $i; $r <= static::$FRACTIONS; $r++) {
                $this->UpdateFormField('fractionID' . $r, 'visible', false);
                $this->UpdateFormField('fractionID' . $r, 'value', false);
            }
        } else {
            $this->UpdateFormField('fractionLabel', 'visible', false);
            for ($i = 1; $i <= static::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @return array IO interface
     */
    protected function PrepareIO()
    {
        $io[self::IO_NAMES] = [];
        // data2array
        return $io;
    }

    /**
     * Create the variables for the fractions.
     *
     *  @param string $domain Domain ID.
     *  @param string $city City ID.
     *  @param string $area Area ID.
     */
    protected function CreateVariables($domain, $city, $area)
    {
        $this->SendDebug(__FUNCTION__, $domain . ' : ' . $city . ' : ' . $area);
        if (($domain == 'null') || ($city == 'null') || ($area == 'null')) {
            return;
        }
        $io = unserialize($this->ReadAttributeString('io'));
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $options = $this->RequestFractions($domain, $city, $area);
        $i = 1;
        $names = [];
        foreach ($options as $fract) {
            if ($i <= static::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract['value'], $fract['caption'], VARIABLETYPE_STRING, '', $i, $enabled || $variable);
                if ($enabled) {
                    $names[$fract['value']] = $fract['caption'];
                }
            }
            $i++;
        }
        $io[self::IO_NAMES] = $names;
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Builds the POST/GET Url for the API CALLS
     *
     * @param string $type Request type.
     * @param string $domain Domain ID.
     * @param string $city City ID.
     * @param string $area Area ID.
     * @return string Service Url
     */
    protected function BuildURL($type, $domain, $city = null, $area = null)
    {
        $url = 'https://' . $domain . self::SERVICE_APIURL;
        // Type
        $url = $url . 'r=' . $type;
        // City
        if ($city != null) {
            $url = $url . '&city_id=' . $city;
        }
        // Area
        if ($area != null) {
            $url = $url . '&area_id=' . $area;
        }
        // Debug
        $this->SendDebug(__FUNCTION__, $url);
        return $url;
    }

    /**
     * Call the service provider to get cities for a given client
     *
     * @param $domain Domain ID
     * @return array New selecteable options or null.
     */
    protected function RequestCities($domain)
    {
        $this->SendDebug(__FUNCTION__, $domain);
        // Build URL data
        $url = $this->BuildURL('cities', $domain);
        // Request Data
        $res = @file_get_contents($url);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $json = json_decode($res, true);
            foreach ($json as $city) {
                if (($city['has_streets'] == false) && $city['area_id'] == 0) {
                    continue;
                }
                if (str_contains($city['name'], '2021') || str_contains($city['name'], 'Musterstadt')) {
                    continue;
                }
                $city['name'] = str_replace(' mit allen Ortsteilen', '', $city['name']);
                $this->SendDebug(__FUNCTION__, $city['name']);
                $data[] = ['caption' => $city['name'], 'value' => $city['id']];
            }
        }
        return $data;
    }

    /**
     * Call the service provider to get areas for a given city
     *
     * @param $domain Domain ID
     * @param $city City ID
     * @return array New selecteable options or null.
     */
    protected function RequestAreas($domain, $city)
    {
        $this->SendDebug(__FUNCTION__, $domain . ':' . $city);
        // Build URL data
        $url = $this->BuildURL('streets', $domain, $city);
        // Request Data
        $res = @file_get_contents($url);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $json = json_decode($res, true);
            if (empty($json)) {
                $data[] = ['caption' => $this->Translate('All'), 'value' => $city];
            } else {
                foreach ($json as $area) {
                    if (isset($area['street_comment']) && $area['street_comment'] != '') {
                        $data[] = ['caption' => $area['name'] . ' (' . $area['street_comment'] . ')', 'value' => $area['area_id']];
                    } else {
                        $data[] = ['caption' => $area['name'], 'value' => $area['area_id']];
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Call the service provider to get fractions for a given city
     *
     * @param $domain Domain ID
     * @param $city City ID
     * @param $area Area ID
     * @return array New selecteable options or null.
     */
    protected function RequestFractions($domain, $city, $area)
    {
        $this->SendDebug(__FUNCTION__, $domain . ':' . $city . ':' . $area);
        // Build URL data
        $url = $this->BuildURL('trash', $domain, $city, $area);
        // Request Data
        $res = @file_get_contents($url);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $json = json_decode($res, true);
            $waste = [];
            foreach ($json as $fract) {
                if (!isset($waste[$fract['name']])) {
                    $waste[$fract['name']] = $fract['title'];
                }
            }
            foreach ($waste as $key => $value) {
                $data[] = ['caption' => $value, 'value' => $key];
            }
        }
        return $data;
    }
}
