<?php

declare(strict_types=1);

/** General functions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\WasteManagement\DebugHelper;
use Wilkware\WasteManagement\EventHelper;
use Wilkware\WasteManagement\FormatHelper;
use Wilkware\WasteManagement\FormHelper;
use Wilkware\WasteManagement\ServiceHelper;
use Wilkware\WasteManagement\VariableHelper;
use Wilkware\WasteManagement\VisualisationHelper;

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
    use FormHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    /** @var string Service Provider */
    private const SERVICE_PROVIDER = 'awido';

    // -------------------------------------------------------------------------
    // Status Codes
    // -------------------------------------------------------------------------

    /** @var int Status Service Error */
    private const STATUS_SERVICE_ERROR = 206;

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
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
        $this->RegisterPropertyInteger('settingsTileMaxView', 2);
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

        // Set visualization type to 0 (no visualization until enabled in ApplyChanges)
        $this->SetVisualizationType(0);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting Symcon.
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
        $this->ModifyFormElement($form['actions'], 'Version', function (array &$element) use ($lib): void
        {
            $element['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        });

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
        $this->ModifyFormElement($form['elements'], 'serviceProvider', function (array &$element): void
        {
            $element['options'] = $this->GetProviderOptions();
        });
        $this->ModifyFormElement($form['elements'], 'serviceCountry', function (array &$element): void
        {
            $element['options'] = $this->GetCountryOptions(self::SERVICE_PROVIDER);
        });

        // Waste Management
        $this->ModifyFormElement($form['elements'], 'clientID', function (array &$element) use ($country): void
        {
            $element['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        });
        $this->ModifyFormElement($form['elements'], 'placeGUID', function (array &$element): void
        {
            $element['options'] = $this->GetPlaceOptions();
        });
        $this->ModifyFormElement($form['elements'], 'streetGUID', function (array &$element): void
        {
            $element['options'] = $this->GetStreetOptions();
        });
        $this->ModifyFormElement($form['elements'], 'addonGUID', function (array &$element): void
        {
            $element['options'] = $this->GetAddonOptions();
        });
        $data = $this->GetFractionOptions();
        foreach ($data as $fract) {
            $this->ModifyFormElement($form['elements'], 'fractionID' . $fract['id'], function (array &$element) use ($fract): void
            {
                $element['caption'] = $fract['caption'];
                $element['visible'] = true;
            });
        }

        // Elements visible (client always visible)
        $this->ModifyFormElement($form['elements'], 'placeGUID', function (array &$element) use ($clientId): void
        {
            $element['visible'] = ($clientId != 'null');
        });
        $this->ModifyFormElement($form['elements'], 'streetGUID', function (array &$element) use ($placeId): void
        {
            $element['visible'] = ($placeId != 'null');
        });
        $this->ModifyFormElement($form['elements'], 'addonGUID', function (array &$element) use ($streetId): void
        {
            $element['visible'] = ($streetId != 'null');
        });
        $this->ModifyFormElement($form['elements'], 'labelFraction', function (array &$element) use ($addonId): void
        {
            $element['visible'] = ($addonId != 'null');
        });

        // Actions visible
        $this->ModifyFormElement($form['actions'], 'updateButton', function (array &$element) use ($addonId): void
        {
            $element['visible'] = ($addonId != 'null');
        });
        $this->ModifyFormElement($form['actions'], 'lookAheadButton', function (array &$element) use ($addonId): void
        {
            $element['visible'] = ($addonId != 'null');
        });

        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->LogDebug(__FUNCTION__, 'Translate Waste Visu');
            $this->ModifyFormElement($form['elements'], 'settingsTileColors', function (array &$element): void
            {
                $element['values'] = $this->GetWasteValues();
            });
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
        // Safety default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Visu (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT], 0, $tilevisu && $htmlbox);
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
            $this->SetVisualizationType($tilevisu ? ((float) IPS_GetKernelVersion() < 9.1 ? 1 : 2) : 0);
            if ($tilevisu) {
                $this->UpdateVisualizationValue(json_encode([
                    'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
                    'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
                    'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
                    'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor'),
                    'tileMaxView'   => $this->ReadPropertyInteger('settingsTileMaxView')
                ]));
            }
            $this->CreateVariables($clientId, $fractIds);
            $status = 102;
            // Recalculate time
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
            $this->LogDebug(__FUNCTION__, 'Update timer activated!');
            if ($loakahead && $tilevisu) {
                $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
                if (($time['hour'] == 0) && ($time['minute'] <= 30)) {
                    $this->LogDebug(__FUNCTION__, 'LookAhead time too early!');
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
        // Dispatch form actions (no eval, values can contain user input)
        switch ($ident) {
            case 'OnChangeCountry':
                $this->OnChangeCountry((string) $value);
                break;
            case 'OnChangeClient':
                $this->OnChangeClient((string) $value);
                break;
            case 'OnChangePlace':
                $this->OnChangePlace((string) $value);
                break;
            case 'OnChangeStreet':
                $this->OnChangeStreet((string) $value);
                break;
            case 'OnChangeAddon':
                $this->OnChangeAddon((string) $value);
                break;
            case 'LookAhead':
                // Tap on the 'Today' badge of the tile
                $this->LookAhead();
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'Unknown ident: ' . $ident);
        }
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
        // Check instance state (after a service error the variables still hold valid dates)
        if (!in_array($this->GetStatus(), [102, self::STATUS_SERVICE_ERROR])) {
            $this->LogDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }

        // Check tile visu
        if ($this->ReadPropertyBoolean('settingsTileVisu') === false) {
            $this->LogDebug(__FUNCTION__, 'WARNING: TileVisu is not active.');
            return;
        }

        // Set Timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);

        // rebuild informations
        $clientId = $this->ReadPropertyString('clientID');
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getFractions/client=' . $clientId;
        $data = $this->ServiceRequest($url);
        if (!is_array($data)) {
            // keep the current widget, the dates are still valid
            $this->LogDebug(__FUNCTION__, 'Service request failed, widget not updated!');
            return;
        }

        // Convert fractions with short name into array
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
        // Check instance state (a previous service error must not block the retry)
        if (!in_array($this->GetStatus(), [102, self::STATUS_SERVICE_ERROR])) {
            $this->LogDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
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

        $data = $this->ServiceRequest($url);
        if (!is_array($data)) {
            $this->ServiceError();
            return;
        }

        // Convert fractions with short name into array
        $waste = [];
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $fractIDENT = $this->GetVariableIdent($fract->snm);
            $waste[$fract->snm] = ['ident' => $fractIDENT, 'date' => '', 'exist' => $fractID];
        }
        $this->LogDebug(__FUNCTION__, $waste);

        // update data
        $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getData/' . $addonId . '?fractions=' . $fractIds . '&client=' . $clientId;
        $data = $this->ServiceRequest($url);
        if (!isset($data->calendar)) {
            $this->ServiceError();
            return;
        }
        if ($this->GetStatus() == self::STATUS_SERVICE_ERROR) {
            $this->SetStatus(102);
        }

        // go through the calendar data (sorted, the first future date per fraction wins)
        $calendar = (array) $data->calendar;
        usort($calendar, fn (object $a, object $b): int => strcmp((string) $a->dt, (string) $b->dt));
        foreach ($calendar as $day) {
            // only pickup dates, no holidays
            if ($day->fr == '') {
                continue;
            }
            // skip dates in the past
            if ($day->dt < date('Ymd')) {
                continue;
            }
            // convert YYYYMMDD into DD.MM.YYYY
            $tag = substr($day->dt, 6) . '.' . substr($day->dt, 4, 2) . '.' . substr($day->dt, 0, 4);
            // find out the waste type
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
                $this->LogDebug(__FUNCTION__, 'Update: Script #' . $scriptId . ' does not exist!');
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
     * User has selected a new street or district.
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
     * User has selected a new house number.
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
     * Returns for the dropdown menu the selectable locations in the disposal area.
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
            $data = $this->ServiceRequest($url);
            foreach ($data ?? [] as $place) {
                $options[] = ['caption' => $place->value, 'value' => $place->key];
            }
        }
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable streets in the disposal area.
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
        if ($cId != 'null' && $pId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getGroupedStreets/' . $pId . '?selectedOTId=null&client=' . $cId;
            $data = $this->ServiceRequest($url);
            foreach ($data ?? [] as $street) {
                $options[] = ['caption' => $street->value, 'value' => $street->key];
            }
        }
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns for the dropdown menu the selectable house numbers in the disposal area.
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
        if ($cId != 'null' && $sId != 'null') {
            $url = 'https://awido.cubefour.de/WebServices/Awido.Service.svc/getStreetAddons/' . $sId . '?client=' . $cId;
            $data = $this->ServiceRequest($url);
            foreach ($data ?? [] as $addon) {
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
            $data = $this->ServiceRequest($url);
            foreach ($data ?? [] as $fract) {
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
        $this->UpdateFormField('lookAheadButton', 'visible', $fr);
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
        $data = $this->ServiceRequest($url) ?? [];
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('createVariables');
        foreach ($data as $fract) {
            $fractID = $this->ReadPropertyBoolean('fractionID' . $fract->id);
            $fractIDENT = $this->GetVariableIdent($fract->snm);
            $this->MaintainVariable($fractIDENT, html_entity_decode($fract->nm), VARIABLETYPE_STRING, ['ICON' => 'trash-can', 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], $fract->id, $fractID || $variable);
        }
    }
    /**
     * Requests json data from the service.
     *
     * @param string $url Service URL
     *
     * @return mixed Decoded json data or null on error
     */
    private function ServiceRequest(string $url): mixed
    {
        $json = $this->GetUrlContent($url);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json);
        if ($data === null) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Invalid json data from ' . $url);
        }
        return $data;
    }

    /**
     * Logs a failed service request and sets the error status.
     *
     * @return void
     */
    private function ServiceError(): void
    {
        $this->LogMessage('Could not retrieve collection dates from service (see debug for details)!', KL_ERROR);
        $this->SetServiceError(self::STATUS_SERVICE_ERROR, 'activateAWIDO');
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
            $this->Update();

            // Read the buffer again
            $buffer = $this->GetBuffer('WasteData');

            // If there is still no data available, return an empty result
            if (empty($buffer)) {
                $buffer = '[]';
            }
        }
        $wd = json_decode($buffer, true);
        $ac = [
            'todayColor'    => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentToday')),
            'tomorrowColor' => $this->GetColorFormatted($this->ReadPropertyInteger('settingsAccentTomorrow')),
            'tonneAlpha'    => $this->ReadPropertyInteger('settingsTonneAlpha') . '%',
            'tonneColor'    => $this->ReadPropertyBoolean('settingsTonneColor'),
            'tileMaxView'   => $this->ReadPropertyInteger('settingsTileMaxView'),
        ];
        $result = array_merge($ac, $wd);

        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }
}
