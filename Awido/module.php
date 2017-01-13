<?

class Awido extends IPSModule
{
  /**
   * (bekannte) Client IDs - Array
   *
   * @access private
   * @var array Key ist die clientID, Value ist der Name
   */
  static $Clients = array(
    "awld"              => "Lahn-Dill-Kreis",
    "awb-ak"            => "Landkreis Altenkirchen",
    "awb-duerkheim"     => "Landkreis Bad Dürkheim",
    "wgv"               => "Landkreis Bad Tölz-Wolfratshausen",
    "awv-nordschwaben"  => "Landkreis Dillingen a.d. Donau und Donau-Ries",
    "Erding"            => "Landkreis Erding",
    "kaw-guenzburg"     => "Landkreis Günzburg",
    "azv-hef-rof"       => "Landkreis Hersfeld-Rotenburg",
    "kelheim"           => "Landkreis Kelheim",
    "landkreisbetriebe" => "Landkreis Neuburg-Schrobenhausen",
    "eww-suew"          => "Landkreis Südliche Weinstraße",
    "lra-dah"           => "Landratsamt Dachau",
    "neustadt"          => "Neustadt a.d. Waldnaab",
    "rmk"               => "Rems-Murr-Kreis",
    "memmingen"         => "Stadt Memmingen"
    //"???"             => "Landratsamt Aichach-Friedberg"
  );

  /**
   * Create.
   *
   * @access public
   */
  public function Create()
  {
    //Never delete this line!
    parent::Create();

    $this->RegisterPropertyString("clientID", "null");
    // Places
    $this->RegisterPropertyString("placeGUID", "null");
    // Street
    $this->RegisterPropertyString("streetGUID", "null");
    // Addon
    $this->RegisterPropertyString("addonGUID", "null");
    // FractionIDs
    $this->RegisterPropertyString("fractionIDs", "null");
    // Fractions
    for ($i=1; $i<=10; $i++) {
			$this->RegisterPropertyBoolean("fractionID".$i, false);
		}
    // Activation
		$this->RegisterPropertyBoolean("activateAWIDO", false);
    // Update daily timer
    //old $this->RegisterTimer("UpdateTimer",0,"AWIDO_Update(\$_IPS['TARGET']);");
    $this->RegisterCyclicTimer("UpdateTimer", 0, 10, 0, 'AWIDO_Update('.$this->InstanceID.');');
  }

  /**
   * Configuration Form.
   *
   * @access public
   * @return JSON configuration string.
   */
  public function GetConfigurationForm()
  {
    $clientId = $this->ReadPropertyString("clientID");
    $placeId  = $this->ReadPropertyString("placeGUID");
    $streetId = $this->ReadPropertyString("streetGUID");
    $addonId  = $this->ReadPropertyString("addonGUID");
    $fractIds = $this->ReadPropertyString("fractionIDs");
    $activate = $this->ReadPropertyString("activateAWIDO");

    $this->SendDebug("GetConfigurationForm", "clientID=".$clientId.", placeId=".$placeId.", streetId=".$streetId.", addonId=".$addonId.", fractIds=".$fractIds, 0);

    if($clientId == "null") {
      IPS_SetProperty($this->InstanceID, "placeGUID", "null");
      IPS_SetProperty($this->InstanceID, "streetGUID", "null");
      IPS_SetProperty($this->InstanceID, "addonGUID", "null");
      IPS_SetProperty($this->InstanceID, "fractionIDs", "null");
      for ($i=1; $i<=10; $i++) {
  			IPS_SetProperty($this->InstanceID, "fractionID".$i, false);
  		}
 			IPS_SetProperty($this->InstanceID, "activateAWIDO", false);
      // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
      $placeId  = "null";
      $streetId = "null";
      $addonId  = "null";
      $fractIds = "null";
      $activate = false;
    }
    else if($placeId == "null") {
      IPS_SetProperty($this->InstanceID, "streetGUID", "null");
      IPS_SetProperty($this->InstanceID, "addonGUID", "null");
      IPS_SetProperty($this->InstanceID, "fractionIDs", "null");
 			IPS_SetProperty($this->InstanceID, "activateAWIDO", false);
      // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
      $streetId = "null";
      $addonId  = "null";
      $fractIds = "null";
      $activate = false;
    }
    else if($streetId == "null") {
      IPS_SetProperty($this->InstanceID, "addonGUID", "null");
      IPS_SetProperty($this->InstanceID, "fractionIDs", "null");
 			IPS_SetProperty($this->InstanceID, "activateAWIDO", false);
      // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
      $addonId  = "null";
      $fractIds = "null";
      $activate = false;
    }
    else if($addonId == "null") {
      IPS_SetProperty($this->InstanceID, "fractionIDs", "null");
 			IPS_SetProperty($this->InstanceID, "activateAWIDO", false);
      // zusätzlich da Werte mit IPS_SetProperty nicht sofort übernommen werden
      $fractIds = "null";
      $activate = false;
    }

    $formclient = $this->FormClient($clientId);
    $formplaces = $this->FormPlaces($clientId, $placeId);
    $formstreet = $this->FormStreet($clientId, $placeId, $streetId);
    $formaddons = $this->FormAddons($clientId, $placeId, $streetId, $addonId);
    $formfracts = $this->FormFractions($clientId, $addonId);
    $formactive = $this->FormActivate($clientId, $addonId);
    $formaction = $this->FormActions($clientId, $addonId);

    $formstatus = $this->FormStatus();

    return '{ "elements": [' . $formclient . $formplaces . $formstreet . $formaddons . $formfracts . $formactive . '], ' . $formaction . '"status": [' . $formstatus . ']}';
  }

  public function ApplyChanges()
  {
    //Never delete this line!
    parent::ApplyChanges();

    $clientId = $this->ReadPropertyString("clientID");
    $placeId  = $this->ReadPropertyString("placeGUID");
    $streetId = $this->ReadPropertyString("streetGUID");
    $addonId  = $this->ReadPropertyString("addonGUID");
    $fractIds = $this->ReadPropertyString("fractionIDs");
    $activate = $this->ReadPropertyString("activateAWIDO");
    $this->SendDebug("ApplyChanges", "clientID=".$clientId.", placeId=".$placeId.", streetId=".$streetId.", addonId=".$addonId.", fractIds=".$fractIds, 0);

    // Safty default
    $eId = $this->GetIDForIdent("UpdateTimer");
    IPS_SetEventActive($eId, false);
    //old $this->SetTimerInterval("UpdateTimer", 0);

    //$status = 102;
    if($clientId == "null") {
      $status = 201;
    }
    else if($placeId == "null") {
      $status = 202;
    }
    else if($streetId == "null") {
      $status = 203;
    }
    else if($addonId == "null") {
      $status = 204;
    }
    else if($fractIds == "null") {
      $status = 205;
    }
    else if($activate == true) {
      $this->CreateVariables($clientId, $fractIds);
      $status = 102;
      IPS_SetEventActive($eId, true);
      //old $this->SetTimerInterval("UpdateTimer", 1000*60*60*24);
      $this->SendDebug("ApplyChanges", "Timer aktiviert!", 0);
    }
    else {
      $status = 104;
    }

    $this->SetStatus($status);
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren Client IDs (Abfallwirtschaften).
   *
   * @access protected
   * @param  string $cId Client ID .
   * @return string Client ID Elemente.
   */
  protected function FormClient($cId)
  {
    $form = '{ "type": "Select", "name": "clientID", "caption": "Refuse management:", "options": [';
    $line = array();

    // Reset key
    $line[] = '{"label": "Please select ...","value": "null"}';

    foreach (static::$Clients as $Client => $Name)
    {
      if ($cId == "null") {
        $line[] = '{"label": "' . $Name . '","value": "' . $Client . '"}';
      }
      else if ($Client == $cId) {
          $line[] = '{"label": "' . $Name . '","value": "' . $Client . '"}';
      }
    }
    return $form . implode(',', $line) . ']}';
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren Orte im Entsorkungsgebiet.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $pId Place GUID  .
   * @return string Places Elemente.
   */
  protected function FormPlaces($cId, $pId)
  {
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getPlaces/client=".$cId;

    if($cId == "null") {
      return '';
    }

    $json = file_get_contents($url);
    $data = json_decode($json);

    $form = ',{ "type": "Select", "name": "placeGUID", "caption": "Location:", "options": [';
    $line = array();
    // Reset key
    $line[] = '{"label": "Please select ...","value": "null"}';

    foreach($data as $place) {
      if($pId == "null") {
        $line[] = '{"label": "' . $place->value . '","value": "' . $place->key . '"}';
      }
      else if ($pId == $place->key) {
        $line[] = '{"label": "' . $place->value . '","value": "' . $place->key . '"}';
      }
    }
    return $form . implode(',', $line) . ']}';
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren OT/Strassen im Entsorkungsgebiet.
   *
   * @access protected
   * @param  string $cId Client ID.
   * @param  string $pId Place GUID.
   * @param  string $sId Street GUID.
   * @return string Ortsteil/Strasse Elemente.
   */
  protected function FormStreet($cId, $pId, $sId)
  {
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getGroupedStreets/".$pId."?selectedOTId=null&client=".$cId;

    if($cId == "null" || $pId == "null") {
      return '';
    }

    $json = file_get_contents($url);
    $data = json_decode($json);

    $form = ',{ "type": "Select", "name": "streetGUID", "caption": "District/Street:", "options": [';
    $line = array();
    // Reset key
    $line[] = '{"label": "Please select ...","value": "null"}';

    foreach($data as $street) {
      if($sId == "null") {
        $line[] = '{"label": "' . $street->value . '","value": "' . $street->key . '"}';
      }
      else if ($sId == $street->key) {
        $line[] = '{"label": "' . $street->value . '","value": "' . $street->key . '"}';
      }
    }
    return $form . implode(',', $line) . ']}';
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren Hausnummern im Entsorkungsgebiet.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $pId Place GUID.
   * @param  string $sId Street GUID .
   * @param  string $aId Addon GUID .
   * @return string Addon ID Elements.
   */
  protected function FormAddons($cId, $pId, $sId, $aId)
  {
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getStreetAddons/".$sId."?client=".$cId;

    if($cId == "null" || $pId == "null" || $sId == "null") {
      return '';
    }

    $json = file_get_contents($url);
    $data = json_decode($json);

    $form = ',{ "type": "Select", "name": "addonGUID", "caption": "Street number:", "options": [';
    $line = array();
    // Reset key
    $line[] = '{"label": "Please select ...","value": "null"}';

    foreach($data as $addon) {
      if($addon->value == "") {
        $addon->value = "All";
      }
      if($aId == "null") {
        $line[] = '{"label": "' . $addon->value . '","value": "' . $addon->key . '"}';
      }
      else if ($aId == $addon->key) {
        $line[] = '{"label": "' . $addon->value . '","value": "' . $addon->key . '"}';
      }
    }
    return $form . implode(',', $line) . ']}';
  }

  /**
   * Erstellt für die angebotenen Entsorgungen Auswahlboxen.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $aId Addon GUID .
   * @return string Fraction ID Elements.
   */
  protected function FormFractions($cId, $aId)
  {
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=".$cId;

    if($cId == "null" || $aId == "null") {
      return '';
    }

    $json = file_get_contents($url);
    $data = json_decode($json);

    $form = ',{ "type": "Label", "label": "The following disposals are offered:" } ,';
    $line = array();
    $ids  = array();

    foreach($data as $fract) {
        $ids[]  = $fract->id;
  			IPS_SetProperty($this->InstanceID, "fractionID".$fract->id, $fract->vb);
        $line[] = '{ "type": "CheckBox", "name": "fractionID' . $fract->id .'", "caption": "' . $fract->nm . ' (' . $fract->snm .')" }';
    }
    IPS_SetProperty($this->InstanceID, "fractionIDs", implode(',', $ids));
    return $form . implode(',', $line);
  }

  /**
   * Check zum Aktivieren des Moduls.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $aId Addon GUID .
   * @return string Activation Elements.
   */
  protected function FormActivate($cId, $aId)
  {
    if($cId == "null" || $aId == "null") {
      return '';
    }
    $form = ',{ "type": "Label", "label": "The following selection box activates or deactivates the instance:" } ,
              { "type": "CheckBox", "name": "activateAWIDO", "caption": "Activate daily update?" }';       

    return $form;
  }

  /**
   * Action zum Aktiualisieren der Daten.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $aId Addon GUID .
   * @return string Action Elements.
   */
  protected function FormActions($cId, $aId)
  {
    if($cId == "null" || $aId == "null") {
      return '';
    }

    $form = '"actions": [
            { "type": "Label", "label": "Update dates." } ,
            { "type": "Button", "label": "Update", "onClick": "AWIDO_Update($id);" } ],';        

    return $form;
  }

  /**
   * Prüft den Parent auf vorhandensein und Status.
   *
   * @access protected
   * @return string Status Elemente.
   */
  protected function FormStatus()
  {
    $form =  '{"code": 101, "icon": "inactive", "caption": "Creating instance."},
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
   * Create the varables for the fractions.
   *
   * @access protected
   * @param  string $fIds fract ids.
   * @param  string $cId Client ID .
   */
  protected function CreateVariables($cId, $fIds)
  {
    // should never happends
    if($cId == "null" || $fIds == "null") {
      return;
    }
    // delete all existing variables
    $childs = IPS_GetChildrenIDs($this->InstanceID);
    foreach($childs as $object) {
      if(IPS_GetName($object) != "UpdateTimer") {
        IPS_DeleteVariable($object);
      }
    }
    // create all new
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=".$cId;

    $json = file_get_contents($url);
    $data = json_decode($json);

    foreach($data as $fract) {
        $fractID = $this->ReadPropertyBoolean("fractionID".$fract->id);
        if($fractID == true) {
          $this->RegisterVariableString($fract->snm, $fract->nm, "~String", $fract->id);
        }
    }
  }

  /**
   * Create the cyclic Update Timer.
   *
   * @access protected
   * @param  string $ident Name and Ident of the Timer.
   * @param  string $cId Client ID .
   */
  protected function RegisterCyclicTimer($ident, $hour, $minute, $second, $script)
	{
		$id = @$this->GetIDForIdent($ident);
		$name = $ident;
		if ($id && IPS_GetEvent($id)['EventType'] <> 1)
		{
		  IPS_DeleteEvent($id);
		  $id = 0;
		}
		if (!$id)
		{
		  $id = IPS_CreateEvent(1);
		  IPS_SetParent($id, $this->InstanceID);
		  IPS_SetIdent($id, $ident);
		}
		IPS_SetName($id, $name);
		// IPS_SetInfo($id, "Update AstroTimer");
		// IPS_SetHidden($id, true);
		IPS_SetEventScript($id, $script);
		if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");
		//IPS_SetEventCyclic($id, 0, 0, 0, 0, 0, 0);
		IPS_SetEventCyclicTimeFrom($id, $hour, $minute, $second);
		IPS_SetEventActive($id, false);
	}

  /**
  * This function will be available automatically after the module is imported with the module control.
  * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
  *
  * AWIDO_Update($id);
  *
  */
  public function Update()
  {
    $clientId = $this->ReadPropertyString("clientID");
    $placeId  = $this->ReadPropertyString("placeGUID");
    $streetId = $this->ReadPropertyString("streetGUID");
    $addonId  = $this->ReadPropertyString("addonGUID");
    $fractIds = $this->ReadPropertyString("fractionIDs");

    if($clientId == "null" || $placeId == "null" || $streetId == "null" || $addonId == "null" || $fractIds == "null") {
      return;
    }

    // rebuild informations
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=".$clientId;

    $json = file_get_contents($url);
    $data = json_decode($json);

    // Fractions mit Kurzzeichen(Short Name)) in Array konvertieren
    $array = array();
    foreach($data as $fract) {
        $fractID = $this->ReadPropertyBoolean("fractionID".$fract->id);
        $array[$fract->snm] = array('ident' => $fract->snm, 'value' => '', 'exist' => $fractID);
    }

    // update data
    $url = "http://awido.cubefour.de/WebServices/Awido.Service.svc/getData/".$addonId."?fractions=".$fractIds."&client=".$clientId;
    $json = file_get_contents($url);
    $data = json_decode($json);

    // Kalenderdaten durchgehen
		foreach($data->calendar as $day) {
      // nur Abholdaten nehmen, keine Feiertage
			if($day->fr == "") {
				continue;
      }
      // Datum in Vergangenheit brauchen wir nicht
			if($day->dt < date("Ymd")) {
				continue;
      }
      // YYYYMMDD umwandeln in DD.MM.YYYY
			$tag = substr($day->dt, 6).".".substr($day->dt, 4, 2).".".substr($day->dt, 0, 4);
      // Entsorgungsart herausfinden
      foreach($day->fr as $snm) { 
        if ($array[$snm]['value'] == "" ) {
          $array[$snm]['value'] = $tag;
        }
      }
		}

    // write data to variable
    foreach($array as $line) {
      if($line['exist'] == true) {
        $varId = $this->GetIDForIdent($line['ident']);
        // falls haendich geloescht, dann eben nicht!
        if ($varId != 0) {
          SetValueString($varId, $line['value']);
        }
      }
    }
  }

}
?>