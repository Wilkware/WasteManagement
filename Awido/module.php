<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/traits.php';  // Allgemeine Funktionen

// CLASS Awido
class Awido extends IPSModule
{
    use EventHelper;
    use DebugHelper;

    /**
     * Bekannte Client IDs - Array.
     * Key ist die clientID, Value ist der Name
     * Stand 01.10.2020 = 32 Landkreise
     */
    private static $CLIENTS = [
        'unterhaching'      => 'Gemeinde Unterhaching',
        'awld'              => 'Lahn-Dill-Kreis',
        'aic-fdb'           => 'Landkreis Aichach-Friedberg',
        'awb-ak'            => 'Landkreis Altenkirchen',
        'ansbach'           => 'Landkreis Ansbach',
        'awb-duerkheim'     => 'Landkreis Bad Dürkheim',
        'wgv'               => 'Landkreis Bad Tölz-Wolfratshausen',
        'bgl'               => 'Landkreis Berchtesgadener Land',
        'coburg'            => 'Landkreis Coburg',
        'awv-nordschwaben'  => 'Landkreis Dillingen a.d. Donau und Donau-Ries',
        'Erding'            => 'Landkreis Erding',
        'ffb'               => 'Landkreis Fürstenfeldbruck',
        'gotha'             => 'Landkreis Gotha',
        'kaw-guenzburg'     => 'Landkreis Günzburg',
        'azv-hef-rof'       => 'Landkreis Hersfeld-Rotenburg',
        'kelheim'           => 'Landkreis Kelheim',
        'kronach'           => 'Landkreis Kronach',
        'landkreisbetriebe' => 'Landkreis Neuburg-Schrobenhausen',
        'rosenheim'         => 'Landkreis Rosenheim',
        'lra-schweinfurt'   => 'Landkreis Schweinfurt',
        'eww-suew'          => 'Landkreis Südliche Weinstraße',
        'kreis-tir'         => 'Landkreis Tirschenreuth',
        'tuebingen'         => 'Landkreis Tübingen',
        'lra-dah'           => 'Landratsamt Dachau',
        'neustadt'          => 'Neustadt a.d. Waldnaab',
        'pullach'           => 'Pullach im Isartal',
        'rmk'               => 'Rems-Murr-Kreis',
        'kaufbeuren'        => 'Stadt Kaufbeuren',
        'memmingen'         => 'Stadt Memmingen',
        'unterschleissheim' => 'Stadt Unterschleissheim',
        'zv-muc-so'         => 'Zweckverband München-Südost',
        'zaso'              => 'Zweckverband Saale-Orla',
    ];

    /**
     * Maximale Anzahl an Entsorgungsarten
     */
    private static $FRACTIONS = 15;

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyString('placeGUID', 'null');
        $this->RegisterPropertyString('streetGUID', 'null');
        $this->RegisterPropertyString('addonGUID', 'null');
        $this->RegisterPropertyString('fractionIDs', 'null');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        $this->RegisterPropertyBoolean('createVariables', false);
        $this->RegisterPropertyBoolean('activateAWIDO', false);
        $this->RegisterPropertyInteger('scriptID', 0);
        // Attributes für dynmaisches Konfigurationsformular (> v2.0)
        $this->RegisterAttributeString('cID', 'null');
        $this->RegisterAttributeString('pID', 'null');
        $this->RegisterAttributeString('sID', 'null');
        $this->RegisterAttributeString('aID', 'null');
        $this->RegisterAttributeString('fID', 'null');
        // Register täglichen Update-Timer
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
        $fractIds = $this->ReadPropertyString('fractionIDs');
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        // Debug output
        $this->SendDebug('GetConfigurationForm', 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);

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

        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Build Form
        $jsonForm['elements'][0]['options'] = $this->GetClientOptions();
        $jsonForm['elements'][1]['options'] = $this->GetPlaceOptions();
        $jsonForm['elements'][2]['options'] = $this->GetStreetOptions();
        $jsonForm['elements'][3]['options'] = $this->GetAddonOptions();
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $jsonForm['elements'][$fract['id'] + 4]['caption'] = $fract['caption'];
        }
        // Elements visible
        $jsonForm['elements'][1]['visible'] = ($clientId != 'null');
        $jsonForm['elements'][2]['visible'] = ($placeId != 'null');
        $jsonForm['elements'][3]['visible'] = ($streetId != 'null');
        $jsonForm['elements'][4]['visible'] = ($addonId != 'null');
        $ids = explode(',', $fractIds);
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $jsonForm['elements'][$i + 4]['visible'] = in_array($i, $ids);
        }
        $jsonForm['elements'][20]['visible'] = ($addonId != 'null');
        $jsonForm['elements'][21]['visible'] = ($addonId != 'null');
        $jsonForm['elements'][22]['visible'] = ($addonId != 'null');
        $jsonForm['elements'][23]['visible'] = ($addonId != 'null');
        $jsonForm['elements'][24]['visible'] = ($addonId != 'null');
        $jsonForm['elements'][25]['visible'] = ($addonId != 'null');
        // Actions visible
        $jsonForm['actions'][0]['visible'] = ($addonId != 'null');
        $jsonForm['actions'][1]['visible'] = ($addonId != 'null');
        // Debug output
        //$this->SendDebug('GetConfigurationForm', $jsonForm);
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
        $fractIds = $this->ReadPropertyString('fractionIDs');
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        $this->SendDebug('AWIDO', 'ApplyChanges: clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
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
            $this->SendDebug('AWIDO', 'ApplyChanges: Timer aktiviert!');
        } else {
            $status = 104;
        }

        $this->SetStatus($status);
    }

    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug('RequestAction', $ident . ' => ' . $value);
        switch ($ident) {
            case 'OnChangeClient':
                $this->OnChangeClient($value);
            break;
            case 'OnChangePlace':
                $this->OnChangePlace($value);
            break;
            case 'OnChangeStreet':
                $this->OnChangeStreet($value);
            break;
            case 'OnChangeAddon':
                $this->OnChangeAddon($value);
            break;
        }
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
        $fractIds = $this->ReadPropertyString('fractionIDs');
        $scriptId = $this->ReadPropertyInteger('scriptID');

        if ($clientId == 'null' || $placeId == 'null' || $streetId == 'null' || $addonId == 'null' || $fractIds == 'null') {
            return;
        }

        // rebuild informations
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $clientId;

        $json = file_get_contents($url);
        $data = json_decode($json);

        // Fractions mit Kurzzeichen(Short Name)) in Array konvertieren
        $array = [];
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $array[$fract->snm] = ['ident' => $fract->snm, 'value' => '', 'exist' => $fractID];
        }

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
                if ($array[$snm]['value'] == '') {
                    $array[$snm]['value'] = $tag;
                }
            }
        }

        // write data to variable
        foreach ($array as $line) {
            if ($line['exist'] == true) {
                $varId = @$this->GetIDForIdent($line['ident']);
                // falls haendich geloescht, dann eben nicht!
                if ($varId != 0) {
                    SetValueString($varId, $line['value']);
                }
            }
        }

        // execute Script
        if ($scriptId != 0) {
            if (IPS_ScriptExists($scriptId)) {
                $rs = IPS_RunScript($scriptId);
                $this->SendDebug('Script Execute: Return Value', $rs, 0);
            } else {
                $this->SendDebug('AWIDO', 'Update: Script #' . $scriptId . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }
    }

    /**
     * Liefert für DropDown-Menü die auswählbaren Clients/IDs (Abfallwirtschaften).
     *
     * @return array Liste mit Abfallwirtschaften (Clients).
     */
    protected function GetClientOptions()
    {
        $options = [];
        // Default key
        $options[] = ['caption' => 'Please select ...', 'value' => 'null'];
        // Client List
        foreach (static::$CLIENTS as $Client => $Name) {
            $options[] = ['caption' => $Name, 'value'=> $Client];
        }
        return $options;
    }

    /**
     * Liefert für DropDown-Menü die auswählbaren Orte im Entsorkungsgebiet.
     *
     * @return array Liste mit Orten.
     */
    protected function GetPlaceOptions()
    {
        // aktuelle Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug('GetPlaceOptions', $cId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => 'Please select ...', 'value' => 'null'];
        // Daten abholen
        if ($cId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getPlaces/client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $place) {
                $options[] = ['caption' => $place->value, 'value' => $place->key];
            }
        }
        return $options;
    }

    /**
     * Liefert für DropDown-Menü die auswählbaren OT/Strassen im Entsorkungsgebiet.
     *
     * @return array Liste mit Ortsteil/Strasse.
     */
    protected function GetStreetOptions()
    {
        // aktuelle Client ID
        $cId = $this->ReadAttributeString('cID');
        // aktuelle Palces GUID
        $pId = $this->ReadAttributeString('pID');
        $this->SendDebug('GetStreetOptions', $pId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => 'Please select ...', 'value' => 'null'];
        // Daten abholen
        if ($cId != 'null' & $pId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getGroupedStreets/' . $pId . '?selectedOTId=null&client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $street) {
                $options[] = ['caption' => $street->value, 'value' => $street->key];
            }
        }
        return $options;
    }

    /**
     * Liefert für DropDown-Menü die auswählbaren Hausnummern im Entsorkungsgebiet.
     *
     * @return string Liste mit Hausnummern.
     */
    protected function GetAddonOptions()
    {
        // aktuelle Client ID
        $cId = $this->ReadAttributeString('cID');
        // aktuelle Street GUID
        $sId = $this->ReadAttributeString('sID');
        $this->SendDebug('GetAddonOptions', $sId);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => 'Please select ...', 'value' => 'null'];
        // Daten abholen
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
        return $options;
    }

    /**
     * Liefert die angebotenen Entsorgungen für die ausgewählte Strasse.
     */
    protected function GetFractionOptions()
    {
        // aktuelle Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->SendDebug('GetFractionOptions', $cId);
        // Options
        $options = [];
        // Active IDs
        $ids = [];
        // Daten abholen
        if ($cId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $cId;
            $json = file_get_contents($url);
            $data = json_decode($json);
            foreach ($data as $fract) {
                $ids[] = $fract->id;
                $options[] = ['id' => $fract->id, 'caption' => $fract->nm . ' (' . $fract->snm . ')'];
            }
        }
        IPS_SetProperty($this->InstanceID, 'fractionIDs', implode(',', $ids));
        $this->WriteAttributeString('fID', implode(',', $ids));

        return $options;
    }

    /**
     * Benutzer hat einen neues Entsorgungsgebiet ausgewählt.
     *
     * @param string $id Client ID .
     */
    protected function OnChangeClient($id)
    {
        $this->WriteAttributeString('cID', $id);
        // Places
        $this->UpdateFormField('placeGUID', 'value', 'null');
        $this->UpdateFormField('placeGUID', 'options', json_encode($this->GetPlaceOptions()));
        $this->UpdateFormField('placeGUID', 'visible', $id != 'null');
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');

        // Hide or Unhide properties
        $this->ChangeVisiblity(true, $id != 'null', false, false, false, false);
    }

    /**
     * Benutzer hat einen neues Entsorgungsort ausgewählt.
     *
     * @param string $id Place GUID .
     */
    protected function OnChangePlace($id)
    {
        $this->WriteAttributeString('pID', $id);
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        $this->UpdateFormField('streetGUID', 'options', json_encode($this->GetStreetOptions()));
        $this->UpdateFormField('streetGUID', 'visible', $id != 'null');
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');

        // Hide or Unhide properties
        $this->ChangeVisiblity(true, true, $id != 'null', false, false, false);
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID .
     */
    protected function OnChangeStreet($id)
    {
        $this->WriteAttributeString('sID', $id);
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        $this->UpdateFormField('addonGUID', 'options', json_encode($this->GetAddonOptions()));
        $this->UpdateFormField('addonGUID', 'visible', $id != 'null');

        // Hide or Unhide properties
        $this->ChangeVisiblity(true, true, true, $id != 'null', false, false);
    }

    /**
     * Benutzer hat eine neue Hausnummer ausgewählt.
     *
     * @param string $id Addon GUID .
     */
    protected function OnChangeAddon($id)
    {
        $this->WriteAttributeString('aID', $id);
        // Fraction
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $this->UpdateFormField('fractionID' . $fract['id'], 'caption', $fract['caption']);
        }
        // Hide or Unhide properties
        $this->ChangeVisiblity(true, true, true, true, $id != 'null', $id != 'null');
    }

    /**
     * Hide/unhide form fields.
     *
     */
    protected function ChangeVisiblity($cl, $pl, $st, $ad, $fr, $op)
    {
        // Select Properties
        $this->UpdateFormField('clientID', 'visible', $cl);
        $this->UpdateFormField('placeGUID', 'visible', $pl);
        $this->UpdateFormField('streetGUID', 'visible', $st);
        $this->UpdateFormField('addonGUID', 'visible', $ad);
        // Fraction Checkboxes
        $this->UpdateFormField('labelFraction', 'visible', $fr);
        $ids = explode(',', $this->ReadAttributeString('fID'));
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'visible', $fr && in_array($i, $ids));
        }
        // Settings Checkpoxes
        $this->UpdateFormField('labelVariables', 'visible', $op);
        $this->UpdateFormField('createVariables', 'visible', $op);
        $this->UpdateFormField('labelActive', 'visible', $op);
        $this->UpdateFormField('activateAWIDO', 'visible', $op);
        $this->UpdateFormField('labelScript', 'visible', $op);
        $this->UpdateFormField('scriptID', 'visible', $op);
        // Action area
        $this->UpdateFormField('updateLabel', 'visible', $op);
        $this->UpdateFormField('updateButton', 'visible', $op);
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
            $this->MaintainVariable($fract->snm, $fract->nm, vtString, '', $fract->id, $fractID || $variable);
        }
    }
}
