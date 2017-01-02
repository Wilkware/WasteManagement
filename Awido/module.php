<?

class Awido extends IPSModule
{

  /**
   * (bekannte) Client IDs - Array
   *
   * @access private
   *  @var array Key ist die clientID, Value ist der Name
   */
  static $Clients = array(
    "null" => "Please select ...",
    // =====================================
    "rmk" => "Rems-Murr-Kreis",
    "neustadt" => "Neustadt a.d. Waldnaab",
    "awb-duerkheim" => "Landkreis Bad Dürkheim",
    "wgv" => "Landkreis Bad Tölz - Wolfratshausen",
    "kehlheim" => "Landkreis Kelheim",
    "kaw-guenzburg" => "Landkreis Günzburg",
    "memmingen" => "Stadt Memmingen",
    "eww-suew" => "Landkreis Südliche Weinstraße",
    "lra-dah" => "Landratsamt Dachau",
    "landkreisbetriebe" => "Landkreis Neuburg-Schrobenhausen",
    "awb-ak" => "Landkreis Altenkirchen",
    "awld" => "Lahn-Dill-Kreis",
    "azv-hef-rof" => "Landkreis Hersfeld-Rotenburg",
    "awv-nordschwaben" => "Landkreise Dillingen a.d. Donau und Donau-Ries (AWV Nordschwaben)",
    "Erding" => "Landkreis Erding"
    //"???" => "Landratsamt Aichach-Friedberg",
  );

  
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

	//Configuration Form
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
    return '';
  }

}

?>