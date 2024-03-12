<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// CLASS Abfall_IO
class Awido extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // Service Provider
    private const SERVICE_PROVIDER = 'awido';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_AWIDO = 3;
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
        $this->RegisterPropertyString('placeGUID', 'null');
        $this->RegisterPropertyString('streetGUID', 'null');
        $this->RegisterPropertyString('addonGUID', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        // Advanced Settings
        $this->RegisterPropertyBoolean('createVariables', false);
        $this->RegisterPropertyBoolean('activateAWIDO', true);
        $this->RegisterPropertyInteger('settingsScript', 0);
        // Attributes for dynamic configuration forms (> v2.0)
        $this->RegisterAttributeString('cID', 'null');
        $this->RegisterAttributeString('pID', 'null');
        $this->RegisterAttributeString('sID', 'null');
        $this->RegisterAttributeString('aID', 'null');
        $this->RegisterAttributeString('fID', 'null');
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'AWIDO_Update(' . $this->InstanceID . ');');
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Setup einlesen
        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $fractions = [];
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $activate = $this->ReadPropertyBoolean('activateAWIDO');

        // Debug output
        $this->SendDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);

        // Check properties
        if ($clientId == 'null') {
            $placeId = 'null';
        }
        if ($placeId == 'null') {
            $streetId = 'null';
        }
        if ($streetId == 'null') {
            $addonId = 'null';
        }
        if ($addonId == 'null') {
            $fractIds = 'null';
            $activate = false;
        }

        // Init attributes
        $this->WriteAttributeString('cID', $clientId);
        $this->WriteAttributeString('pID', $placeId);
        $this->WriteAttributeString('sID', $streetId);
        $this->WriteAttributeString('aID', $addonId);
        $this->WriteAttributeString('fID', $fractIds);
        // Debug output
        $this->SendDebug(__FUNCTION__, 'cID=' . $clientId . ', pId=' . $placeId . ', sId=' . $streetId . ', aId=' . $addonId . ', fId=' . $fractIds);

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][1]['items'][0]['options'] = $this->GetPlaceOptions();
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][2]['items'][0]['options'] = $this->GetStreetOptions();
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][2]['items'][1]['options'] = $this->GetAddonOptions();
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $jsonForm['elements'][self::ELEM_AWIDO]['items'][$fract['id'] + 3]['caption'] = $fract['caption'];
            $jsonForm['elements'][self::ELEM_AWIDO]['items'][$fract['id'] + 3]['visible'] = true;
        }
        // Elements visible (client always visible)
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][1]['items'][0]['visible'] = ($clientId != 'null');
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][2]['items'][0]['visible'] = ($placeId != 'null');
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][2]['items'][1]['visible'] = ($streetId != 'null');
        $jsonForm['elements'][self::ELEM_AWIDO]['items'][3]['visible'] = ($addonId != 'null');
        /*
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $jsonForm['elements'][self::ELEM_AWIDO]['items'][$i + 3]['visible'] = in_array($i, $fractions);
        }
         */
        // Actions visible
        $jsonForm['actions'][0]['visible'] = ($addonId != 'null');
        $jsonForm['actions'][1]['visible'] = ($addonId != 'null');
        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->SendDebug(__FUNCTION__, 'Translate Waste Visu');
            $jsonForm['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }
        // Return form
        return json_encode($jsonForm);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $fractions = [];
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $this->SendDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu);
        //$status = 102;
        if ($clientId == 'null') {
            $status = 201;
        } elseif ($placeId == 'null') {
            $status = 202;
        } elseif ($streetId == 'null') {
            $status = 203;
        } elseif ($addonId == 'null') {
            $status = 204;
        } elseif ($fractIds == 'null') {
            $status = 205;
        } elseif ($activate == true) {
            $this->CreateVariables($clientId, $fractIds);
            $status = 102;
            // Time neu berechnen
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
            $this->SendDebug(__FUNCTION__, 'Timer aktiviert!');
        } else {
            $status = 104;
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
        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $fractions = [];
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $scriptId = $this->ReadPropertyInteger('settingsScript');

        $this->SendDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);
        if ($clientId == 'null' || $placeId == 'null' || $streetId == 'null' || $addonId == 'null' || $fractIds == 'null') {
            return;
        }

        // rebuild informations
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $clientId;

        $json = file_get_contents($url);
        $data = json_decode($json);

        // Fractions mit Kurzzeichen(Short Name)) in Array konvertieren
        $waste = [];
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $fractIDENT = $this->GetVariableIdent($fract->snm);
            $waste[$fract->snm] = ['ident' => $fractIDENT, 'date' => '', 'exist' => $fractID];
        }
        $this->SendDebug(__FUNCTION__, $waste);

        // update data
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getData/' . $addonId . '?fractions=' . $fractIds . '&client=' . $clientId;
        $json = file_get_contents($url);
        $data = json_decode($json);

        // Kalenderdaten durchgehen
        foreach ($data->calendar as $day) {
            // nur Abholdaten nehmen, keine Feiertage
            if ($day->fr == '') {
                continue;
            }
            // Datum in Vergangenheit brauchen wir nicht
            if ($day->dt < date('Ymd')) {
                continue;
            }
            // YYYYMMDD umwandeln in DD.MM.YYYY
            $tag = substr($day->dt, 6) . '.' . substr($day->dt, 4, 2) . '.' . substr($day->dt, 0, 4);
            // Entsorgungsart herausfinden
            foreach ($day->fr as $snm) {
                if ($waste[$snm]['date'] == '') {
                    $waste[$snm]['date'] = $tag;
                }
            }
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            if ($var['exist'] == true) {
                $this->SetValueString((string) $var['ident'], $var['date']);
            }
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->SendDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
        }

        // execute Script
        if ($scriptId != 0) {
            if (IPS_ScriptExists($scriptId)) {
                $rs = IPS_RunScript($scriptId);
                $this->SendDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs, 0);
            } else {
                $this->SendDebug(__FUNCTION__, 'Update: Script #' . $scriptId . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
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
        // Update attribute
        $this->WriteAttributeString('cID', $id);
        $this->SendDebug(__FUNCTION__, $id);
        // Places
        $this->UpdateFormField('placeGUID', 'value', 'null');
        $this->UpdateFormField('placeGUID', 'options', json_encode($this->GetPlaceOptions()));
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        // Fraction
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->ChangeVisiblity($id != 'null', false, false, false);
    }

    /**
     * User has selected a new place.
     *
     * @param string $id Place GUID .
     */
    protected function OnChangePlace($id)
    {
        // Update attribute
        $this->WriteAttributeString('pID', $id);
        $this->SendDebug(__FUNCTION__, $id);
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        $this->UpdateFormField('streetGUID', 'options', json_encode($this->GetStreetOptions()));
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        // Fraction
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->ChangeVisiblity(true, $id != 'null', false, false);
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID .
     */
    protected function OnChangeStreet($id)
    {
        // Update attribute
        $this->WriteAttributeString('sID', $id);
        $this->SendDebug(__FUNCTION__, $id);
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        $this->UpdateFormField('addonGUID', 'options', json_encode($this->GetAddonOptions()));
        // Fraction
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->ChangeVisiblity(true, true, $id != 'null', false);
    }

    /**
     * Benutzer hat eine neue Hausnummer ausgewählt.
     *
     * @param string $id Addon GUID .
     */
    protected function OnChangeAddon($id)
    {
        // Update attribute
        $this->WriteAttributeString('aID', $id);
        $this->SendDebug(__FUNCTION__, $id);
        // Fraction
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $this->UpdateFormField('fractionID' . $fract['id'], 'caption', $fract['caption']);
        }
        // Hide or Unhide properties
        $this->ChangeVisiblity(true, true, true, $id != 'null');
    }

    /**
     * Returns for the dropdown menu the selectable locations in the desorking area.
     *
     * @return array List of places.
     */
    protected function GetPlaceOptions()
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug(__FUNCTION__, $cId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        if ($cId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getPlaces/client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $place) {
                $options[] = ['caption' => $place->value, 'value' => $place->key];
            }
        }
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
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug(__FUNCTION__, $cId);
        // Palces GUID
        $pId = $this->ReadAttributeString('pID');
        $this->SendDebug(__FUNCTION__, $pId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        if ($cId != 'null' & $pId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getGroupedStreets/' . $pId . '?selectedOTId=null&client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $street) {
                $options[] = ['caption' => $street->value, 'value' => $street->key];
            }
        }
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
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug(__FUNCTION__, $cId);
        // Street GUID
        $sId = $this->ReadAttributeString('sID');
        $this->SendDebug(__FUNCTION__, $sId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Data
        if ($cId != 'null' & $sId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getStreetAddons/' . $sId . '?client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $addon) {
                if ($addon->value == '') {
                    $addon->value = 'All';
                }
                $options[] = ['caption' => $addon->value, 'value' => $addon->key];
            }
        }
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Delivers the offered disposals for the selected street.
     */
    protected function GetFractionOptions()
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug(__FUNCTION__, $cId);
        // Options
        $options = [];
        // Active IDs
        $ids = [];
        // Data
        if ($cId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $fract) {
                $ids[] = $fract->id;
                $options[] = ['id' => $fract->id, 'caption' => html_entity_decode($fract->nm) . ' (' . $fract->snm . ')'];
            }
        }
        $this->WriteAttributeString('fID', implode(',', $ids));
        $this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Hide/unhide form fields.
     *
     */
    protected function ChangeVisiblity($pl, $st, $ad, $fr)
    {
        // Select Properties
        $this->UpdateFormField('placeGUID', 'visible', $pl);
        $this->UpdateFormField('streetGUID', 'visible', $st);
        $this->UpdateFormField('addonGUID', 'visible', $ad);
        // Fraction Checkboxes
        $this->UpdateFormField('labelFraction', 'visible', $fr);
        $ids = explode(',', $this->ReadAttributeString('fID'));
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'visible', $fr && in_array($i, $ids));
        }
        // Action area
        $this->UpdateFormField('updateLabel', 'visible', $fr);
        $this->UpdateFormField('updateButton', 'visible', $fr);
    }

    /**
     * Create the variables for the fractions.
     *
     * @param string $fIds fraction ids.
     * @param string $cId  Client ID .
     */
    protected function CreateVariables($cId, $fIds)
    {
        // should never happends
        if ($cId == 'null' || $fIds == 'null') {
            return;
        }
        // create or update all variables
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $cId;
        $json = file_get_contents($url);
        $data = json_decode($json);
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('createVariables');
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $fractIDENT = $this->GetVariableIdent($fract->snm);
            $this->MaintainVariable($fractIDENT, html_entity_decode($fract->nm), VARIABLETYPE_STRING, '', $fract->id, $fractID || $variable);
        }
    }
}
