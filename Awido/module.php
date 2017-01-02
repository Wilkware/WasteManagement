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
    "null" => "Please select ...",
    // =====================================
    "awld"              => "Lahn-Dill-Kreis",
    "awb-ak"            => "Landkreis Altenkirchen",
    "awb-duerkheim"     => "Landkreis Bad Dürkheim",
    "wgv"               => "Landkreis Bad Tölz-Wolfratshausen",
    "awv-nordschwaben"  => "Landkreis Dillingen a.d. Donau und Donau-Ries",
    "Erding"            => "Landkreis Erding",
    "kaw-guenzburg"     => "Landkreis Günzburg",
    "azv-hef-rof"       => "Landkreis Hersfeld-Rotenburg",
    "kehlheim"          => "Landkreis Kelheim",
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
    $this->RegisterPropertyString("placeGUID", "");
    // Street
    $this->RegisterPropertyString("streetGUID", "");
    // Addon
    $this->RegisterPropertyString("addonGUID", "");
    // Fraction

    // Update daily timer
    $this->RegisterTimer("UpdateTimer",0,"AWIDO_Update(\$_IPS['TARGET']);");
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

    // Reset all?
    if ($clientId == "null") {
      $placeId  = "";
      $placeId = "";
      $streetId = "";
      $addonId  = "";
    }

		$formclient = $this->FormClient($clientId);
		$formplaces = $this->FormPlaces($clientId);
		$formstreet = $this->FormStreet($clientId, $placeId);
		$formaddons = $this->FormAddons($clientId, $streetId);
		$formstatus = $this->FormStatus();

		return '{ "elements": [' . $formclient . $formplaces . $formstreet . $formaddons . '], "status": [' . $formstatus . ']}';
	}

  public function ApplyChanges()
  {
    //Never delete this line!
    parent::ApplyChanges();

		$client = $this->ReadPropertyInteger("clientID");

    if($client == "null") {
      $this->SetStatus(201);
    }

    //$this->SetTimerInterval("UpdateTimer", 0);

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


    if ($cId == "null") {
      // alles anbieten
      foreach (static::$Clients as $Client => $Name)
      {
        $line[] = '{"label": "' . $Name . '","value": "' . $Client . '"}';
      }
    }
    else {
      // nur aktuelle Auswahl anbieten, sonst reset
      foreach (static::$Clients as $Client => $Name)
      {
        if ($Client == "null" || $Client == $cId) {
          $line[] = '{"label": "' . $Name . '","value": "' . $Client . '"}';
        }
      }

    }

  	return $form . implode(',', $line) . ']}';
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren Orte im Entsorkungsgebiet.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @return string Places Elemente.
   */
  protected function FormPlaces($cId)
  {
    return '';
  }

  /**
   * Erstellt ein DropDown-Menü mit den auswählbaren Orte im Entsorkungsgebiet.
   *
   * @access protected
   * @param  string $cId Client ID.
   * @param  string $pId Place GUID.
   * @return string Ortsteil/Strasse Elemente.
   */
  protected function FormStreet($cId, $pId)
  {
    return '';
  }

  /**
   * Prüft den Parent auf vorhandensein und Status.
   *
   * @access protected
   * @param  string $cId Client ID .
   * @param  string $sId Street GUID .
   * @return string Client ID Elements.
   */
  protected function FormAddons($cId, $sId)
  {
    return '';
  }

  /**
   * Prüft den Parent auf vorhandensein und Status.
   *
   * @access protected
   * @return string Status Elemente.
   */
  protected function FormStatus()
  {
    $form = '{
                "code": 101,
                "icon": "inactive",
                "caption": "Creating instance."
              },
              {
                "code": 102,
                "icon": "active",
                "caption": "AWIDO active."
              },
              {
                "code": 104,
                "icon": "inactive",
                "caption": "AWIDO inactive."
              },
              {
                "code": 201,
                "icon": "inactive",
                "caption": "Select a valid refuse management!"
              },
              {
                "code": 202,
                "icon": "inactive",
                "caption": "Select a valid place!"
              }';
    return $form;
  }

}

?>