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

    // Service Provider
    private const SERVICE_PROVIDER = 'mymde';
    private const SERVICE_BASEURL = 'https://mymuell.jumomind.com/mmapp/api.php';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_MYMDE = 3;

    /**
     * Create.
     */
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        // Waste Management
        $this->RegisterPropertyString('cityID', 'null');
        $this->RegisterPropertyString('areaID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Advanced Settings
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'MYMDE_Update(' . $this->InstanceID . ');');
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
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'cityID=' . $cId . ', areaId=' . $aId);

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_MYMDE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];

        // Streets/Areas
        if ($cId != 'null') {
            $options = $this->RequestAreas($cId);
            if ($options != null) {
                // Always add the selection prompt
                array_unshift($options, $prompt);
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][1]['items'][0]['options'] = $options;
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][1]['items'][0]['visible'] = true;
            }
        } else {
            $aId = null;
        }

        // Fractions
        if ($aId != null) {
            $options = $this->RequestFractions($cId, $aId);
            if ($options != null) {
                // Label
                $jsonForm['elements'][self::ELEM_MYMDE]['items'][2]['visible'] = true;
                $i = 1;
                foreach ($options as $fract) {
                    $jsonForm['elements'][self::ELEM_MYMDE]['items'][$i + 2]['caption'] = $fract['caption'];
                    $jsonForm['elements'][self::ELEM_MYMDE]['items'][$i + 2]['visible'] = true;
                    $i++;
                }
            }
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
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $this->SendDebug(__FUNCTION__, 'cityID=' . ', areaID=' . $aId);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        // Set status
        if ($cId == 'null') {
            $status = 201;
        } elseif ($aId == 'null') {
            $status = 202;
        } else {
            $status = 102;
        }
        // All okay
        if ($status == 102) {
            $this->CreateVariables($cId, $aId);
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
     * MYMDE_Update($id);
     */
    public function Update()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');

        $waste = [];
        // Build URL data
        $url = $this->BuildURL('dates', $cId, $aId);
        // Request Data
        $json = @file_get_contents($url);
        // Collect DATA
        if ($json !== false) {
            $data = json_decode($json, true);
            foreach ($data as $entry) {
                if (!isset($waste[$entry['trash_name']])) {
                    $waste[$entry['trash_name']] = ['title' => $entry['title'], 'date' => strtotime($entry['day'])];
                }
            }
        } else {
            $this->LogMessage($this->Translate('Could not load json data!'), KL_ERROR);
            $this->SendDebug(__FUNCTION__, 'Error: Could not load json data!');
            return;
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $varId = @$this->GetIDForIdent($key);
            if ($varId != 0) {
                SetValueString($varId, date('d.m.Y', $var['date']));
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
     * User has selected a new waste management city.
     *
     * @param string $id City ID .
     */
    protected function OnChangeCity($id)
    {
        $this->SendDebug(__FUNCTION__, $id);
        $options = null;
        if ($id != 'null') {
            $options = $this->RequestAreas($id);
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
     * @param string $value City & Area ID.
     */
    protected function OnChangeArea($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        $cId = $data['city'];
        $aId = $data['area'];

        $options = null;
        if ($aId != 'null') {
            $options = $this->RequestFractions($cId, $aId);
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
     * Create the variables for the fractions.
     *
     *  @param string $city City ID.
     *  @param string $area Area ID.
     */
    protected function CreateVariables($city, $area)
    {
        $this->SendDebug(__FUNCTION__, $city . ' : ' . $area);
        if (($city == 'null') || ($area == 'null')) {
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $options = $this->RequestFractions($city, $area);
        $i = 1;
        foreach ($options as $fract) {
            if ($i <= static::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract['value'], $fract['caption'], vtString, '', $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Builds the POST/GET Url for the API CALLS
     *
     * @param string $type Request type.
     * @param string $city City ID.
     * @param string $area Area ID.
     * @return string Service Url
     */
    protected function BuildURL($type, $city, $area = null)
    {
        $url = self::SERVICE_BASEURL . '?';

        // Type
        $url = $url . 'r=' . $type;
        // City
        $url = $url . '&city_id=' . $city;
        // Area
        if ($area != null) {
            $url = $url . '&area_id=' . $area;
        }
        // Debug
        $this->SendDebug(__FUNCTION__, $url);
        return $url;
    }

    /**
     * Call the service provider to get areas for a given city
     *
     * @param $city City ID
     * @return array New selecteable options or null.
     */
    protected function RequestAreas($city)
    {
        $this->SendDebug(__FUNCTION__, $city);
        // Build URL data
        $url = $this->BuildURL('streets', $city);
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
                    if ($area['street_comment'] != '') {
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
     * @param $city City ID
     * @param $area Area ID
     * @return array New selecteable options or null.
     */
    protected function RequestFractions($city, $area)
    {
        $this->SendDebug(__FUNCTION__, $city . ':' . $area);
        // Build URL data
        $url = $this->BuildURL('trash', $city, $area);
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
