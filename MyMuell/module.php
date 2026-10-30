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
 *  Class MyMuell
 */
class MyMuell extends IPSModuleStrict
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
    // Constants
    // -------------------------------------------------------------------------

    /** @var string Service Provider */
    private const SERVICE_PROVIDER = 'mymde';

    /** @var string Service API Url */
    private const SERVICE_APIURL = '.jumomind.com/mmapp/api.php?';

    /** @var string IO key 'names' */
    private const IO_NAMES = 'names';

    // -------------------------------------------------------------------------
    // Status Codes
    // -------------------------------------------------------------------------

    /** @var int Status Service Error */
    private const STATUS_SERVICE_ERROR = 203;

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
        // Never delete this line!
        parent::Create();

        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        $this->RegisterPropertyString('serviceCountry', 'de');

        // Waste Management
        $this->RegisterPropertyString('domainID', 'null');
        $this->RegisterPropertyString('cityID', 'null');
        $this->RegisterPropertyString('areaID', 'null');
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }

        // Attributes for dynamic configuration forms
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));

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
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);

        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'MYMDE_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'MYMDE_LookAhead(' . $this->InstanceID . ');');

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

        // Settings
        $activate = $this->ReadPropertyBoolean('settingsActivate');

        // Service Values
        $country = $this->ReadPropertyString('serviceCountry');

        // IO Values
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');

        // Debug output
        $this->LogDebug(__FUNCTION__, 'domainID=' . $dId . ',cityID=' . $cId . ', areaId=' . $aId);

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
        $this->ModifyFormElement($form['elements'], 'domainID', function (array &$element) use ($country): void
        {
            $element['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        });

        // Prompt
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Domain (client)
        if ($dId != 'null') {
            $options = $this->RequestCities($dId);
            if ($options != null) {
                // Always add the selection prompt
                array_unshift($options, $prompt);
                $this->ModifyFormElement($form['elements'], 'cityID', function (array &$element) use ($options): void
                {
                    $element['options'] = $options;
                    $element['visible'] = true;
                });
            }
        } else {
            $this->LogDebug(__FUNCTION__, __LINE__);
            $cId = null;
        }

        // Streets/Areas
        if ($cId != 'null') {
            $options = $this->RequestAreas($dId, $cId);
            if ($options != null) {
                // Always add the selection prompt
                array_unshift($options, $prompt);
                $this->ModifyFormElement($form['elements'], 'areaID', function (array &$element) use ($options): void
                {
                    $element['options'] = $options;
                    $element['visible'] = true;
                });
            }
        } else {
            $aId = null;
        }

        // Fractions
        if ($aId != null) {
            $options = $this->RequestFractions($dId, $cId, $aId);
            if ($options != null) {
                // Label
                $this->ModifyFormElement($form['elements'], 'fractionLabel', function (array &$element): void
                {
                    $element['visible'] = true;
                });
                $i = 1;
                foreach ($options as $fract) {
                    $this->ModifyFormElement($form['elements'], 'fractionID' . $i, function (array &$element) use ($fract): void
                    {
                        $element['caption'] = $fract['caption'];
                        $element['visible'] = true;
                    });
                    $i++;
                }
            }
        }

        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->LogDebug(__FUNCTION__, 'Translate Waste Visu');
            $this->ModifyFormElement($form['elements'], 'settingsTileColors', function (array &$element): void
            {
                $element['values'] = $this->GetWasteValues();
            });
        }

        // Return Form
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        // Never delete this line!
        parent::ApplyChanges();
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'domainID=' . $dId . 'cityID=' . $cId . ', areaID=' . $aId);
        // Safety default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Visu (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT], 0, $tilevisu && $htmlbox);
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
            $this->CreateVariables($dId, $cId, $aId);
            if ($activate == true) {
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
            case 'OnChangeDomain':
                $this->OnChangeDomain((string) $value);
                break;
            case 'OnChangeCity':
                $this->OnChangeCity((string) $value);
                break;
            case 'OnChangeArea':
                $this->OnChangeArea((string) $value);
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
     * MYMDE_LookAhead($id);
     *
     * @return void
     */
    public function LookAhead(): void
    {
        // Check instance state (after a service error the variables still hold valid dates)
        if (!in_array($this->GetStatus(), [102, self::STATUS_SERVICE_ERROR])) {
            $this->LogDebug(__FUNCTION__, 'STATUS: Instance is not active.');
            return;
        }

        // Check tile visu
        if ($this->ReadPropertyBoolean('settingsTileVisu') === false) {
            $this->LogDebug(__FUNCTION__, 'WARNING: TileVisu is not active.');
            return;
        }

        // rebuild informations
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        $i = 1;
        $waste = [];

        // fractions convert to name => ident
        foreach ($io[self::IO_NAMES] as $ident => $name) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $ident . ', Name: ' . $name);
            $date = $this->GetValue($ident);
            $waste[$name] = ['ident' => $ident, 'date' => $date];
        }

        // update tile widget
        $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        $this->BuildWidget($waste, $list, true);

        // set timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);

        // send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * MYMDE_Update($id);
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
        $dId = $this->ReadPropertyString('domainID');
        $cId = $this->ReadPropertyString('cityID');
        $aId = $this->ReadPropertyString('areaID');
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        $waste = [];
        // Build URL data
        $url = $this->BuildURL('dates', $dId, $cId, $aId);
        // Request Data
        $json = $this->GetUrlContent($url);
        // Collect DATA
        $data = ($json !== false) ? json_decode($json, true) : null;
        if (is_array($data)) {
            foreach ($data as $entry) {
                if ((!isset($waste[$entry['trash_name']])) && (in_array($entry['title'], $io[self::IO_NAMES]))) {
                    $waste[$entry['trash_name']] = ['ident' => $entry['trash_name'], 'date' => date('d.m.Y', strtotime($entry['day'])), 'title' => $entry['title']];
                }
            }
        } else {
            $this->LogDebug(__FUNCTION__, 'ERROR: Could not load json data!');
            $this->LogMessage('Could not retrieve collection dates from service (see debug for details)!', KL_ERROR);
            $this->SetServiceError(self::STATUS_SERVICE_ERROR);
            return;
        }
        $this->SetStatus(102);
        $this->LogDebug(__FUNCTION__, $waste);

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $key, $var['date']);
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->LogDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
        }

        // execute Script
        $script = $this->ReadPropertyInteger('settingsScript');
        if ($script != 0) {
            if (IPS_ScriptExists($script)) {
                $rs = IPS_RunScriptEx($script, ['TIMESTAMP' => time(), 'INSTANCE' => $this->InstanceID, 'DATA' => json_encode($waste)]);
                $this->LogDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs);
            } else {
                $this->LogDebug(__FUNCTION__, 'Update: Script #' . $script . ' does not exist!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('settingsActivate');
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
        $this->UpdateFormField('domainID', 'options', json_encode($options));
        $this->UpdateFormField('domainID', 'visible', true);
        $this->UpdateFormField('domainID', 'value', 'null');
        $this->OnChangeDomain('null');
    }

    /**
     * User has selected a new waste management domain.
     *
     * @param string $id Domain ID.
     *
     * @return void
     */
    protected function OnChangeDomain(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $options = null;
        if ($id != 'null') {
            $options = $this->RequestCities($id);
        }
        // Cities
        $prompt = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        if ($options != null) {
            // Always add the selection prompt
            array_unshift($options, $prompt);
            $this->LogDebug(__FUNCTION__, $options);
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
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'value', false);
            $this->UpdateFormField('fractionID' . $i, 'visible', false);
        }
    }

    /**
     * User has selected a new waste management city.
     *
     * @param string $value Domain & City ID.
     *
     * @return void
     */
    protected function OnChangeCity(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
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
        for ($i = 1; $i <= self::$FRACTIONS; $i++) {
            $this->UpdateFormField('fractionID' . $i, 'value', false);
            $this->UpdateFormField('fractionID' . $i, 'visible', false);
        }
    }

    /**
     * User has selected a new street or district.
     *
     * @param string $value Domain & City & Area ID.
     *
     * @return void
     */
    protected function OnChangeArea(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
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
            for ($r = $i; $r <= self::$FRACTIONS; $r++) {
                $this->UpdateFormField('fractionID' . $r, 'visible', false);
                $this->UpdateFormField('fractionID' . $r, 'value', false);
            }
        } else {
            $this->UpdateFormField('fractionLabel', 'visible', false);
            for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(): array
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
     *
     * @return void
     */
    protected function CreateVariables(string $domain, string $city, string $area): void
    {
        $this->LogDebug(__FUNCTION__, $domain . ' : ' . $city . ' : ' . $area);
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
            if ($i <= self::$FRACTIONS) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $this->MaintainVariable($fract['value'], $fract['caption'], VARIABLETYPE_STRING, ['ICON' => 'trash-can', 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], $i, $enabled || $variable);
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
     * @param ?string $city City ID.
     * @param ?string $area Area ID.
     *
     * @return string Service Url
     */
    protected function BuildURL(string $type, string $domain, ?string $city = null, ?string $area = null): string
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
        $this->LogDebug(__FUNCTION__, $url);
        return $url;
    }

    /**
     * Call the service provider to get cities for a given client
     *
     * @param $domain Domain ID
     *
     * @return ?list<array<string,mixed>> New selecteable options or null.
     */
    protected function RequestCities(string $domain): ?array
    {
        $this->LogDebug(__FUNCTION__, $domain);
        // Build URL data
        $url = $this->BuildURL('cities', $domain);
        // Request Data
        $res = $this->GetUrlContent($url);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $json = json_decode($res, true);
            foreach ($json ?? [] as $city) {
                if (($city['has_streets'] == false) && $city['area_id'] == 0) {
                    continue;
                }
                if (str_contains($city['name'], '2021') || str_contains($city['name'], 'Musterstadt')) {
                    continue;
                }
                $city['name'] = str_replace(' mit allen Ortsteilen', '', $city['name']);
                $this->LogDebug(__FUNCTION__, $city['name']);
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
     * @return ?list<array<string,mixed>> New selecteable options or null.
     */
    protected function RequestAreas(string $domain, string $city): ?array
    {
        $this->LogDebug(__FUNCTION__, $domain . ':' . $city);
        // Build URL data
        $url = $this->BuildURL('streets', $domain, $city);
        // Request Data
        $res = $this->GetUrlContent($url);
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
     * @return ?list<array<string,mixed>> New selecteable options or null.
     */
    protected function RequestFractions(string $domain, string $city, string $area): ?array
    {
        $this->LogDebug(__FUNCTION__, $domain . ':' . $city . ':' . $area);
        // Build URL data
        $url = $this->BuildURL('trash', $domain, $city, $area);
        // Request Data
        $res = $this->GetUrlContent($url);
        $data = null;
        // Collect DATA
        if ($res !== false) {
            $json = json_decode($res, true);
            $waste = [];
            foreach ($json ?? [] as $fract) {
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
