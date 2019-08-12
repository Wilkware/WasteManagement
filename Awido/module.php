<?php

require_once __DIR__.'/../libs/traits.php';  // Allgemeine Funktionen

class Awido extends IPSModule
{
    use TimerHelper, DebugHelper;

    /**
     * (bekannte) Client IDs - Array.
     *
     * @var array Key ist die clientID, Value ist der Name
     */
    public static $Clients = [
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
    'kaw-guenzburg'     => 'Landkreis Günzburg',
    'azv-hef-rof'       => 'Landkreis Hersfeld-Rotenburg',
    'kelheim'           => 'Landkreis Kelheim',
    'kronach'           => 'Landkreis Kronach',
    'landkreisbetriebe' => 'Landkreis Neuburg-Schrobenhausen',
    'rosenheim'         => 'Landkreis Rosenheim',
    'eww-suew'          => 'Landkreis Südliche Weinstraße',
    'kreis-tir'         => 'Landkreis Tirschenreuth',
    'tuebingen'         => 'Landkreis Tübingen',
    'lra-dah'           => 'Landratsamt Dachau',
    'neustadt'          => 'Neustadt a.d. Waldnaab',
    'pullach'           => 'Pullach im Isartal',
    'rmk'               => 'Rems-Murr-Kreis',
    'memmingen'         => 'Stadt Memmingen',
    'unterschleissheim' => 'Stadt Unterschleissheim',
    'zv-muc-so'         => 'Zweckverband München-Südost',
    // Aktueller Stand = 27
  ];

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('clientID', 'null');
        // Places
        $this->RegisterPropertyString('placeGUID', 'null');
        // Street
        $this->RegisterPropertyString('streetGUID', 'null');
        // Addon
        $this->RegisterPropertyString('addonGUID', 'null');
        // FractionIDs
        $this->RegisterPropertyString('fractionIDs', 'null');
        // Fractions
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyBoolean('fractionID'.$i, false);
        }
        // Variables
        $this->RegisterPropertyBoolean('createVariables', false);
        // Activation
        $this->RegisterPropertyBoolean('activateAWIDO', false);
        // Script
        $this->RegisterPropertyInteger('scriptID', 0);
        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'AWIDO_Update('.$this->InstanceID.');');   
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $fractIds = $this->ReadPropertyString('fractionIDs');
        $activate = $this->ReadPropertyBoolean('activateAWIDO');

        $this->SendDebug('AWIDO', 'GetConfigurationForm: clientID='.$clientId.', placeId='.$placeId.', streetId='.$streetId.', addonId='.$addonId.', fractIds='.$fractIds);

        if ($clientId == 'null') {
            IPS_SetProperty($this->InstanceID, 'placeGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'streetGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'addonGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'fractionIDs', 'null');
            for ($i = 1; $i <= 10; $i++) {
                IPS_SetProperty($this->InstanceID, 'fractionID'.$i, false);
            }
            IPS_SetProperty($this->InstanceID, 'activateAWIDO', false);
            // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
            $placeId = 'null';
            $streetId = 'null';
            $addonId = 'null';
            $fractIds = 'null';
            $activate = false;
        } elseif ($placeId == 'null') {
            IPS_SetProperty($this->InstanceID, 'streetGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'addonGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'fractionIDs', 'null');
            IPS_SetProperty($this->InstanceID, 'activateAWIDO', false);
            // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
            $streetId = 'null';
            $addonId = 'null';
            $fractIds = 'null';
            $activate = false;
        } elseif ($streetId == 'null') {
            IPS_SetProperty($this->InstanceID, 'addonGUID', 'null');
            IPS_SetProperty($this->InstanceID, 'fractionIDs', 'null');
            IPS_SetProperty($this->InstanceID, 'activateAWIDO', false);
            // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
            $addonId = 'null';
            $fractIds = 'null';
            $activate = false;
        } elseif ($addonId == 'null') {
            IPS_SetProperty($this->InstanceID, 'fractionIDs', 'null');
            IPS_SetProperty($this->InstanceID, 'activateAWIDO', false);
            // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
            $fractIds = 'null';
            $activate = false;
        }

        $formclient = $this->FormClient($clientId);
        $formplaces = $this->FormPlaces($clientId, $placeId);
        $formstreet = $this->FormStreet($clientId, $placeId, $streetId);
        $formaddons = $this->FormAddons($clientId, $placeId, $streetId, $addonId);
        $formfracts = $this->FormFractions($clientId, $addonId);
        $formcrvars = $this->FormVariables($clientId, $addonId);
        $formactive = $this->FormActivate($clientId, $addonId);
        $formaction = $this->FormActions($clientId, $addonId);
        $formstatus = $this->FormStatus();

        return '{ "elements": ['.$formclient.$formplaces.$formstreet.$formaddons.$formfracts.$formcrvars.$formactive.'], '.$formaction.'"status": ['.$formstatus.']}';
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
        $this->SendDebug('AWIDO', 'ApplyChanges: clientID='.$clientId.', placeId='.$placeId.', streetId='.$streetId.', addonId='.$addonId.', fractIds='.$fractIds);
        // Safty default
        $this->SetTimerInterval("UpdateTimer", 0);
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

    /**
     * Erstellt ein DropDown-Menü mit den auswählbaren Client IDs (Abfallwirtschaften).
     *
     * @param string $cId Client ID .
     *
     * @return string Client ID Elemente.
     */
    protected function FormClient($cId)
    {
        $form = '{ "type": "Select", "name": "clientID", "caption": "Refuse management:", "options": [';
        $line = [];

        // Reset key
        $line[] = '{"caption": "Please select ...","value": "null"}';

        foreach (static::$Clients as $Client => $Name) {
            if ($cId == 'null') {
                $line[] = '{"caption": "'.$Name.'","value": "'.$Client.'"}';
            } elseif ($Client == $cId) {
                $line[] = '{"caption": "'.$Name.'","value": "'.$Client.'"}';
            }
        }

        return $form.implode(',', $line).']}';
    }

    /**
     * Erstellt ein DropDown-Menü mit den auswählbaren Orte im Entsorkungsgebiet.
     *
     * @param string $cId Client ID .
     * @param string $pId Place GUID  .
     *
     * @return string Places Elemente.
     */
    protected function FormPlaces($cId, $pId)
    {
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getPlaces/client='.$cId;

        if ($cId == 'null') {
            return '';
        }

        $json = file_get_contents($url);
        $data = json_decode($json);

        $form = ',{ "type": "Select", "name": "placeGUID", "caption": "Location:", "options": [';
        $line = [];
        // Reset key
        $line[] = '{"caption": "Please select ...","value": "null"}';

        foreach ($data as $place) {
            if ($pId == 'null') {
                $line[] = '{"caption": "'.$place->value.'","value": "'.$place->key.'"}';
            } elseif ($pId == $place->key) {
                $line[] = '{"caption": "'.$place->value.'","value": "'.$place->key.'"}';
            }
        }

        return $form.implode(',', $line).']}';
    }

    /**
     * Erstellt ein DropDown-Menü mit den auswählbaren OT/Strassen im Entsorkungsgebiet.
     *
     * @param string $cId Client ID.
     * @param string $pId Place GUID.
     * @param string $sId Street GUID.
     *
     * @return string Ortsteil/Strasse Elemente.
     */
    protected function FormStreet($cId, $pId, $sId)
    {
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getGroupedStreets/'.$pId.'?selectedOTId=null&client='.$cId;

        if ($cId == 'null' || $pId == 'null') {
            return '';
        }

        $json = file_get_contents($url);
        $data = json_decode($json);

        $form = ',{ "type": "Select", "name": "streetGUID", "caption": "District/Street:", "options": [';
        $line = [];
        // Reset key
        $line[] = '{"caption": "Please select ...","value": "null"}';

        foreach ($data as $street) {
            if ($sId == 'null') {
                $line[] = '{"caption": "'.$street->value.'","value": "'.$street->key.'"}';
            } elseif ($sId == $street->key) {
                $line[] = '{"caption": "'.$street->value.'","value": "'.$street->key.'"}';
            }
        }

        return $form.implode(',', $line).']}';
    }

    /**
     * Erstellt ein DropDown-Menü mit den auswählbaren Hausnummern im Entsorkungsgebiet.
     *
     * @param string $cId Client ID .
     * @param string $pId Place GUID.
     * @param string $sId Street GUID .
     * @param string $aId Addon GUID .
     *
     * @return string Addon ID Elements.
     */
    protected function FormAddons($cId, $pId, $sId, $aId)
    {
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getStreetAddons/'.$sId.'?client='.$cId;

        if ($cId == 'null' || $pId == 'null' || $sId == 'null') {
            return '';
        }

        $json = file_get_contents($url);
        $data = json_decode($json);

        $form = ',{ "type": "Select", "name": "addonGUID", "caption": "Street number:", "options": [';
        $line = [];
        // Reset key
        $line[] = '{"caption": "Please select ...","value": "null"}';

        foreach ($data as $addon) {
            if ($addon->value == '') {
                $addon->value = 'All';
            }
            if ($aId == 'null') {
                $line[] = '{"caption": "'.$addon->value.'","value": "'.$addon->key.'"}';
            } elseif ($aId == $addon->key) {
                $line[] = '{"caption": "'.$addon->value.'","value": "'.$addon->key.'"}';
            }
        }

        return $form.implode(',', $line).']}';
    }

    /**
     * Erstellt für die angebotenen Entsorgungen Auswahlboxen.
     *
     * @param string $cId Client ID .
     * @param string $aId Addon GUID .
     *
     * @return string Fraction ID Elements.
     */
    protected function FormFractions($cId, $aId)
    {
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client='.$cId;

        if ($cId == 'null' || $aId == 'null') {
            return '';
        }

        $json = file_get_contents($url);
        $data = json_decode($json);

        $form = ',{ "type": "Label", "caption": "The following disposals are offered:" } ,';
        $line = [];
        $ids = [];

        foreach ($data as $fract) {
            $ids[] = $fract->id;
            IPS_SetProperty($this->InstanceID, 'fractionID'.$fract->id, $fract->vb);
            $line[] = '{ "type": "CheckBox", "name": "fractionID'.$fract->id.'", "caption": "'.$fract->nm.' ('.$fract->snm.')" }';
        }
        IPS_SetProperty($this->InstanceID, 'fractionIDs', implode(',', $ids));

        return $form.implode(',', $line);
    }

    /**
     * Fragt ob für die nicht angebotenen bzw. aktivierten Entsorgungen Variablen erstellt werden sollen.
     *
     * @param string $cId Client ID .
     * @param string $aId Addon GUID .
     *
     * @return string Variable  Question Elements.
     */
    protected function FormVariables($cId, $aId)
    {
        if ($cId == 'null' || $aId == 'null') {
            return '';
        }
        $form = ',{ "type": "Label", "caption": "Variable creation:" } ,
              { "type": "CheckBox", "name": "createVariables", "caption": "Create variables for non-selected disposals?" }';

        return $form;
    }

    /**
     * Check zum Aktivieren des Moduls.
     *
     * @param string $cId Client ID .
     * @param string $aId Addon GUID .
     *
     * @return string Activation Elements.
     */
    protected function FormActivate($cId, $aId)
    {
        if ($cId == 'null' || $aId == 'null') {
            return '';
        }
        $form = ',{ "type": "Label", "caption": "The following selection box activates or deactivates the instance:" } ,
              { "type": "CheckBox", "name": "activateAWIDO", "caption": "Activate daily update?" } ,
              { "type": "Label", "caption": "Call the following scipt after update the dates:" } ,       
          		{ "type": "SelectScript", "name": "scriptID", "caption": "Script:" }';

        return $form;
    }

    /**
     * Action zum Aktiualisieren der Daten.
     *
     * @param string $cId Client ID .
     * @param string $aId Addon GUID .
     *
     * @return string Action Elements.
     */
    protected function FormActions($cId, $aId)
    {
        if ($cId == 'null' || $aId == 'null') {
            return '';
        }

        $form = '"actions": [
            { "type": "Label", "caption": "Update dates." } ,
            { "type": "Button", "caption": "Update", "onClick": "AWIDO_Update($id);" } ],';

        return $form;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     *
     * @return string Status Elemente.
     */
    protected function FormStatus()
    {
        $form = '{"code": 101, "icon": "inactive", "caption": "Creating instance."},
              {"code": 102, "icon": "active",   "caption": "AWIDO active."},
              {"code": 104, "icon": "inactive", "caption": "AWIDO inactive."},
              {"code": 201, "icon": "inactive", "caption": "Select a valid refuse management!"},
              {"code": 202, "icon": "inactive", "caption": "Select a valid place!"},
              {"code": 203, "icon": "inactive", "caption": "Select a valid location/street!"},
              {"code": 204, "icon": "inactive", "caption": "Select a valid street number!"},
              {"code": 205, "icon": "inactive", "caption": "Select a offered disapsal!"}';

        return $form;
    }

    /**
     * Create the variables for the fractions.
     *
     * @param string $fIds fract ids.
     * @param string $cId  Client ID .
     */
    protected function CreateVariables($cId, $fIds)
    {
        // should never happends
        if ($cId == 'null' || $fIds == 'null') {
            return;
        }
        // create or update all variables
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client='.$cId;
        $json = file_get_contents($url);
        $data = json_decode($json);
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('createVariables');
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID'.$fract->id);
            $this->MaintainVariable($fract->snm, $fract->nm, vtString, '', $fract->id, $fractID || $variable);
        }
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
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client='.$clientId;

        $json = file_get_contents($url);
        $data = json_decode($json);

        // Fractions mit Kurzzeichen(Short Name)) in Array konvertieren
        $array = [];
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID'.$fract->id);
            $array[$fract->snm] = ['ident' => $fract->snm, 'value' => '', 'exist' => $fractID];
        }

        // update data
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getData/'.$addonId.'?fractions='.$fractIds.'&client='.$clientId;
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
            $tag = substr($day->dt, 6).'.'.substr($day->dt, 4, 2).'.'.substr($day->dt, 0, 4);
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
            }
        } else {
            $this->SendDebug('AWIDO', 'Update: Script #'.$scriptId.' existiert nicht!');
        }
        
        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }

    }
}
