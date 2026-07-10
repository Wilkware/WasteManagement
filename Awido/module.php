<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/**
 * Class Awido
 */
class Awido extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use FormatHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    /** @var string Service Provider */
    private const SERVICE_PROVIDER = 'awido';

    // -------------------------------------------------------------------------
    // Form Elements
    // -------------------------------------------------------------------------

    /** @var int Lement Postion Provi */
    private const ELEM_PROVI = 1;

    /** @var int Panael Element MyMDE */
    private const ELEM_WASTE = 2;

    /** @var int Panael Element Visualisation */
    private const ELEM_VISU = 3;

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        $this->RegisterPropertyString('serviceCountry', 'de');

        // Waste Management
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyString('placeGUID', 'null');
        $this->RegisterPropertyString('streetGUID', 'null');
        $this->RegisterPropertyString('addonGUID', 'null');
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }

        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
        $this->RegisterPropertyInteger('settingsAccentToday', -1);
        $this->RegisterPropertyInteger('settingsAccentTomorrow', -1);
        $this->RegisterPropertyInteger('settingsTonneAlpha', 100);
        $this->RegisterPropertyBoolean('settingsTonneColor', true);
        $this->RegisterPropertyBoolean('settingsHtmlBox', true);
        $this->RegisterPropertyBoolean('settingsLookAhead', false);
        $this->RegisterPropertyString('settingsLookTime', '{"hour":12,"minute":0,"second":0}');

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

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'AWIDO_LookAhead(' . $this->InstanceID . ');');

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(0);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Basic Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][1]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);

        // Service Values
        $country = $this->ReadPropertyString('serviceCountry');

        // Setup einlesen
        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $fractions = [];
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $activate = $this->ReadPropertyBoolean('activateAWIDO');

        // Debug output
        $this->LogDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);

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
        $this->LogDebug(__FUNCTION__, 'cID=' . $clientId . ', pId=' . $placeId . ', sId=' . $streetId . ', aId=' . $addonId . ', fId=' . $fractIds);

        // Service Provider
        $form['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        $form['elements'][self::ELEM_PROVI]['items'][1]['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);

        // Waste Management
        $form['elements'][self::ELEM_WASTE]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['options'] = $this->GetPlaceOptions();
        $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['options'] = $this->GetStreetOptions();
        $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['options'] = $this->GetAddonOptions();
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $form['elements'][self::ELEM_WASTE]['items'][intval($fract['id']) + 3]['caption'] = $fract['caption'];
            $form['elements'][self::ELEM_WASTE]['items'][intval($fract['id']) + 3]['visible'] = true;
        }

        // Elements visible (client always visible)
        $form['elements'][self::ELEM_WASTE]['items'][1]['items'][0]['visible'] = ($clientId != 'null');
        $form['elements'][self::ELEM_WASTE]['items'][2]['items'][0]['visible'] = ($placeId != 'null');
        $form['elements'][self::ELEM_WASTE]['items'][2]['items'][1]['visible'] = ($streetId != 'null');
        $form['elements'][self::ELEM_WASTE]['items'][3]['visible'] = ($addonId != 'null');

        // Actions visible
        $form['actions'][0]['items'][0]['items'][0]['visible'] = ($addonId != 'null');

        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->LogDebug(__FUNCTION__, 'Translate Waste Visu');
            $form['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }

        // Return form
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');

        $fractions = [];
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $this->LogDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu && $htmlbox);
        //$status = 102;
        if ($clientId == 'null') {
            $status = 201;
        } elseif ($placeId == 'null') {
            $status = 202;
        } elseif ($streetId == 'null') {
            $status = 203;
        } elseif ($addonId == 'null') {
            $status = 204;
        } elseif ($fractIds == '') {
            $status = 205;
        } elseif ($activate == true) {
            // Update visualization
            $this->SetVisualizationType($tilevisu ? 1 : 0);
            if ($tilevisu) {
                $this->UpdateVisualizationValue(json_encode([
                    'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
                    'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
                    'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
                    'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor')
                ]));
            }
            $this->CreateVariables($clientId, $fractIds);
            $status = 102;
            // Time neu berechnen
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
            $this->LogDebug(__FUNCTION__, 'Update Timer aktiviert!');
            if ($loakahead & $tilevisu) {
                $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
                if (($time['hour'] == 0) && ($time['minute'] <= 30)) {
                    $this->LogDebug(__FUNCTION__, 'LookAhead Time zu niedrieg!');
                } else {
                    $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);
                }
            }
        } else {
            $status = 104;
        }

        $this->SetStatus($status);
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        eval('$this->' . $ident . '(\'' . $value . '\');');
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * AWIDO_LookAhead($id);
     *
     * @return void
     */
    public function LookAhead(): void
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->LogDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }

        // Check tile visu
        if ($this->ReadPropertyBoolean('settingsTileVisu') === false) {
            $this->LogDebug(__FUNCTION__, 'WARNING: TileVisu is not active.');
            return;
        }

        // rebuild informations
        $clientId = $this->ReadPropertyString('clientID');
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $clientId;
        $json = file_get_contents($url);
        $data = json_decode($json);

        // Fractions mit Kurzzeichen(Short Name)) in Array konvertieren
        $waste = [];
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $fractIDENT = $this->GetVariableIdent($fract->snm);
            $date = '';
            if ($fractID) {
                $date = $this->GetValue($fractIDENT);
            }
            $waste[$fract->snm] = ['ident' => $fractIDENT, 'date' => $date, 'exist' => $fractID];
        }
        $this->LogDebug(__FUNCTION__, $waste);

        // update tile widget
        $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        $this->BuildWidget($waste, $list, true);

        // Set Timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);

        // send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * AWIDO_Update($id);
     *
     * @return void
     */
    public function Update(): void
    {
        $clientId = $this->ReadPropertyString('clientID');
        $placeId = $this->ReadPropertyString('placeGUID');
        $streetId = $this->ReadPropertyString('streetGUID');
        $addonId = $this->ReadPropertyString('addonGUID');
        $fractions = [];
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $fractions[] = $i;
            }
        }
        $fractIds = implode(',', $fractions);
        $scriptId = $this->ReadPropertyInteger('settingsScript');

        $this->LogDebug(__FUNCTION__, 'clientID=' . $clientId . ', placeId=' . $placeId . ', streetId=' . $streetId . ', addonId=' . $addonId . ', fractIds=' . $fractIds);
        if ($clientId == 'null' || $placeId == 'null' || $streetId == 'null' || $addonId == 'null' || $fractIds == '') {
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
        $this->LogDebug(__FUNCTION__, $waste);

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
        $this->LogDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
        }

        // execute Script
        if ($scriptId != 0) {
            if (IPS_ScriptExists($scriptId)) {
                // Filter only exist waste idents
                $data = array_map(
                    fn ($e) => [
                        'ident' => $e['ident'],
                        'date'  => $e['date']
                    ],
                    array_filter($waste, fn ($e) => !empty($e['exist']))
                );
                $rs = IPS_RunScriptEx($scriptId, ['TIMESTAMP' => time(), 'INSTANCE' => $this->InstanceID, 'DATA' => json_encode($data)]);
                $this->LogDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs);
            } else {
                $this->LogDebug(__FUNCTION__, 'Update: Script #' . $scriptId . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('activateAWIDO');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }

        if ($btw == true) {
            // send a complete update message to the display, as parameters may have changed
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
        }
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $initialHandling;
    }

    /**
     * User has selected a new waste management country.
     *
     * @param string $id Country ID.
     *
     * @return void
     */
    protected function OnChangeCountry(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $options = $this->GetClientOptions(self::SERVICE_PROVIDER, $id);
        $this->UpdateFormField('clientID', 'options', json_encode($options));
        $this->UpdateFormField('clientID', 'visible', true);
        $this->UpdateFormField('clientID', 'value', 'null');
        $this->OnChangeClient('null');
    }

    /**
     * User has selected a new waste management.
     *
     * @param string $id Client ID .
     *
     * @return void
     */
    protected function OnChangeClient(string $id): void
    {
        // Update attribute
        $this->WriteAttributeString('cID', $id);
        $this->LogDebug(__FUNCTION__, $id);
        // Places
        $this->UpdateFormField('placeGUID', 'value', 'null');
        $this->UpdateFormField('placeGUID', 'options', json_encode($this->GetPlaceOptions()));
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        // Fraction
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->UpdateForm($id != 'null', false, false, false);
    }

    /**
     * User has selected a new place.
     *
     * @param string $id Place GUID .
     *
     * @return void
     */
    protected function OnChangePlace(string $id): void
    {
        // Update attribute
        $this->WriteAttributeString('pID', $id);
        $this->LogDebug(__FUNCTION__, $id);
        // Street
        $this->UpdateFormField('streetGUID', 'value', 'null');
        $this->UpdateFormField('streetGUID', 'options', json_encode($this->GetStreetOptions()));
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        // Fraction
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->UpdateForm(true, $id != 'null', false, false);
    }

    /**
     * Benutzer hat eine neue Straße oder Ortsteil ausgewählt.
     *
     * @param string $id Street GUID .
     *
     * @return void
     */
    protected function OnChangeStreet(string $id): void
    {
        // Update attribute
        $this->WriteAttributeString('sID', $id);
        $this->LogDebug(__FUNCTION__, $id);
        // Addon
        $this->UpdateFormField('addonGUID', 'value', 'null');
        $this->UpdateFormField('addonGUID', 'options', json_encode($this->GetAddonOptions()));
        // Fraction
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        // Hide or Unhide properties
        $this->UpdateForm(true, true, $id != 'null', false);
    }

    /**
     * Benutzer hat eine neue Hausnummer ausgewählt.
     *
     * @param string $id Addon GUID .
     *
     * @return void
     */
    protected function OnChangeAddon(string $id): void
    {
        // Update attribute
        $this->WriteAttributeString('aID', $id);
        $this->LogDebug(__FUNCTION__, $id);
        // Fraction
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            if ($this->UpdateFormField('fractionID' . $i, 'value', false)) {
            }
        }
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $this->UpdateFormField('fractionID' . $fract['id'], 'caption', $fract['caption']);
        }
        // Hide or Unhide properties
        $this->UpdateForm(true, true, true, $id != 'null');
    }

    /**
     * Returns for the dropdown menu the selectable locations in the desorking area.
     *
     * @return list<array<string,string>> List of places.
     */
    protected function GetPlaceOptions(): array
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->LogDebug(__FUNCTION__, $cId);
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
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable streets in the desorking area.
     *
     * @return list<array<string,string>> List of streets.
     */
    protected function GetStreetOptions(): array
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->LogDebug(__FUNCTION__, $cId);
        // Palces GUID
        $pId = $this->ReadAttributeString('pID');
        $this->LogDebug(__FUNCTION__, $pId);
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
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable house numbers in the desorking area.
     *
     * @return list<array<string,string>> List of house numbers.
     */
    protected function GetAddonOptions(): array
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->LogDebug(__FUNCTION__, $cId);
        // Street GUID
        $sId = $this->ReadAttributeString('sID');
        $this->LogDebug(__FUNCTION__, $sId);
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
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Delivers the offered disposals for the selected street.
     *
     * @return list<array<string,string>> List of Disposals
     */
    protected function GetFractionOptions(): array
    {
        // Client ID
        $cId = $this->ReadAttributeString('cID');
        $this->LogDebug(__FUNCTION__, $cId);
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
        $this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Hide/unhide form fields.
     *
     * @param bool $pl True for enable place, otherwise false.
     * @param bool $st True for enable street, otherwise false.
     * @param bool $ad True for enable addon, otherwise false.
     * @param bool $fr True for enable fractions, otherwise false.
     *
     * @return void
     */
    protected function UpdateForm(bool $pl, bool $st, bool $ad, bool $fr)
    {
        // Select Properties
        $this->UpdateFormField('placeGUID', 'visible', $pl);
        $this->UpdateFormField('streetGUID', 'visible', $st);
        $this->UpdateFormField('addonGUID', 'visible', $ad);
        // Fraction Checkboxes
        $this->UpdateFormField('labelFraction', 'visible', $fr);
        $ids = explode(',', $this->ReadAttributeString('fID'));
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'visible', $fr && in_array($i, $ids));
        }
        // Action area
        $this->UpdateFormField('updateButton', 'visible', $fr);
    }

    /**
     * Create the variables for the fractions.
     *
     * @param string $cId  Client ID.
     * @param string $fIds fraction ids.
     *
     * @retun void
     */
    protected function CreateVariables(string $cId, string $fIds): void
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
    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $buffer = $this->GetBuffer('WasteData');
        if (empty($buffer)) {
            $buffer = '[]';
        }
        $wd = json_decode($buffer, true);
        $ac = [
            'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
            'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
            'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
            'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor'),
        ];
        $result = array_merge($ac, $wd);

        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }
}
