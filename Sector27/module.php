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
 * Class Sector27
 */
class Sector27 extends IPSModuleStrict
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
    private const SERVICE_PROVIDER = 'sec27';

    /** @var string Service Base Url */
    private const SERVICE_BASEURL = 'https://muellkalender.sector27.de/';

    /** @var string Service User Agent */
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';

    /** @var string Livewire component name of the calendar */
    private const SERVICE_COMPONENT = 'pickup-calendar';

    /** @var array<int,array{0:string,1:string,2:string}> Fractions (index => [css class, ident, name]) */
    private const SERVICE_FRACTIONS = [
        1 => ['bg-black', 'ResidualWaste', 'Residual waste'],
        2 => ['bg-yellow-700', 'OrganicWaste', 'Biowaste'],
        3 => ['bg-blue-700', 'PaperWaste', 'Paper'],
        4 => ['bg-yellow-400', 'PackagingWaste', 'Yellow bin'],
    ];

    // -------------------------------------------------------------------------
    // IO Keys
    // -------------------------------------------------------------------------

    /** @var string IO Street Name */
    private const IO_NAME = 'name';

    /** @var string IO Street Search Result */
    private const IO_STREETS = 'streets';

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
        //Never delete this line!
        parent::Create();

        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        $this->RegisterPropertyString('serviceCountry', 'de');

        // Waste Management
        $this->RegisterPropertyString('disposalID', 'null');
        $this->RegisterPropertyString('streetID', 'null');
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
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
        $this->RegisterPropertyBoolean('settingsActivate', true);
        $this->RegisterPropertyBoolean('settingsVariables', false);
        $this->RegisterPropertyInteger('settingsScript', 0);

        // Attributes for dynamic configuration forms
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));

        // Register daily update timer
        $this->RegisterTimer('UpdateTimer', 0, 'SECDE_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'SECDE_LookAhead(' . $this->InstanceID . ');');

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
        // IO Values
        $dId = $this->ReadPropertyString('disposalID');
        $sId = $this->ReadPropertyString('streetID');
        $this->LogDebug(__FUNCTION__, 'disposalID=' . $dId . ', streetID=' . $sId);
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
        $this->ModifyFormElement($form['elements'], 'disposalID', function (array &$element) use ($country): void
        {
            $element['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER, $country);
        });
        // City selected - street search
        if ($dId != 'null') {
            $this->ModifyFormElement($form['elements'], 'streetSearch', function (array &$element): void
            {
                $element['visible'] = true;
            });
            $this->ModifyFormElement($form['elements'], 'searchButton', function (array &$element): void
            {
                $element['visible'] = true;
            });
        }
        // Street selected or searched
        $io = unserialize($this->ReadAttributeString('io'));
        $options = $io[self::IO_STREETS];
        if (($sId != 'null') && empty($options)) {
            $options[] = ['caption' => $io[self::IO_NAME], 'value' => $sId];
        }
        if (!empty($options)) {
            array_unshift($options, ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null']);
            $this->ModifyFormElement($form['elements'], 'streetID', function (array &$element) use ($options): void
            {
                $element['options'] = $options;
                $element['visible'] = true;
            });
        }
        // Fractions
        if ($sId != 'null') {
            $this->ModifyFormElement($form['elements'], 'fractionLabel', function (array &$element): void
            {
                $element['visible'] = true;
            });
            foreach (self::SERVICE_FRACTIONS as $i => $fract) {
                $this->ModifyFormElement($form['elements'], 'fractionID' . $i, function (array &$element): void
                {
                    $element['visible'] = true;
                });
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
        //Never delete this line!
        parent::ApplyChanges();
        $dId = $this->ReadPropertyString('disposalID');
        $sId = $this->ReadPropertyString('streetID');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'disposalID=' . $dId . ', streetID=' . $sId);
        // Safety default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Support for Tile Visu (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT], 0, $tilevisu && $htmlbox);
        // Set status
        if ($dId == 'null') {
            $status = 201;
        } elseif ($sId == 'null') {
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
            $this->CreateVariables();
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
            case 'OnChangeDisposal':
                $this->OnChangeDisposal((string) $value);
                break;
            case 'OnSearchStreet':
                $this->OnSearchStreet((string) $value);
                break;
            case 'OnChangeStreet':
                $this->OnChangeStreet((string) $value);
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
     * SECDE_LookAhead($id);
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

        // fractions convert to name => ident
        $waste = [];
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $waste[$this->Translate($fract[2])] = ['ident' => $fract[1], 'date' => $this->GetValue($fract[1])];
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);

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
     * SECDE_Update($id);
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
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);

        // (1) Get all pickup dates (current year, from september also the next year)
        $city = (int) $this->ReadPropertyString('disposalID');
        $street = $this->ReadPropertyString('streetID');
        $pickups = $this->GetPickupDates($city, $street, $io[self::IO_NAME]);
        if ($pickups === false) {
            $this->LogDebug(__FUNCTION__, 'Get dates failed!');
            $this->LogMessage('Could not retrieve collection dates from service (see debug for details)!', KL_ERROR);
            $this->SetServiceError(self::STATUS_SERVICE_ERROR);
            return;
        }
        $this->SetStatus(102);

        // (2) fractions convert to name => ident
        $waste = [];
        $classes = [];
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
            if ($this->ReadPropertyBoolean('fractionID' . $i)) {
                $name = $this->Translate($fract[2]);
                $waste[$name] = ['ident' => $fract[1], 'date' => ''];
                $classes[$fract[0]] = $name;
            }
        }

        // (3) find next date per fraction
        $today = date('Ymd');
        foreach ($pickups as $pickup) {
            if ($pickup['date'] < $today) {
                continue;
            }
            if (isset($classes[$pickup['class']])) {
                $name = $classes[$pickup['class']];
                if ($waste[$name]['date'] == '') {
                    $waste[$name]['date'] = date('d.m.Y', strtotime($pickup['date']));
                }
            }
        }
        $this->LogDebug(__FUNCTION__, $waste);

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString($var['ident'], $var['date']);
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
        $this->UpdateFormField('disposalID', 'options', json_encode($options));
        $this->UpdateFormField('disposalID', 'visible', true);
        $this->UpdateFormField('disposalID', 'value', 'null');
        $this->OnChangeDisposal('null');
    }

    /**
     * User has selected a new waste management (city).
     *
     * @param string $id Disposal (city) ID.
     *
     * @return void
     */
    protected function OnChangeDisposal(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        // Reset street
        $io = $this->PrepareIO();
        $this->UpdateForm($id != 'null', null);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has started a street search.
     *
     * @param string $value JSON with the selected city (not yet applied) and the search term.
     *
     * @return void
     */
    protected function OnSearchStreet(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
        $data = json_decode($value, true);
        $city = (int) ($data['city'] ?? 0);
        $search = trim((string) ($data['search'] ?? ''));
        if ($city == 0) {
            return;
        }
        if (mb_strlen($search) < 2) {
            $this->EchoMessage('Please enter at least the first 2 characters of your street!');
            return;
        }
        $streets = $this->SearchStreets($city, $search);
        if ($streets === false) {
            $this->EchoMessage('Service request failed (see log/debug)!');
            return;
        }
        if (empty($streets)) {
            $this->EchoMessage('No street found!');
        }
        $io = $this->PrepareIO();
        $io[self::IO_STREETS] = $streets;
        $this->UpdateForm(true, $streets);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has selected a new street.
     *
     * @param string $id Street ID.
     *
     * @return void
     */
    protected function OnChangeStreet(string $id): void
    {
        $this->LogDebug(__FUNCTION__, $id);
        $io = unserialize($this->ReadAttributeString('io'));
        $io[self::IO_NAME] = '';
        foreach ($io[self::IO_STREETS] as $street) {
            if ($street['value'] == $id) {
                $io[self::IO_NAME] = $street['caption'];
            }
        }
        // Fraction Checkboxes
        $this->UpdateFormField('fractionLabel', 'visible', $id != 'null');
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
            $this->UpdateFormField('fractionID' . $i, 'visible', $id != 'null');
        }
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Hide/unhide form fields.
     *
     * @param bool $city City is selected
     * @param ?list<array<string,string>> $streets Street search result
     *
     * @return void
     */
    protected function UpdateForm(bool $city, ?array $streets): void
    {
        $this->LogDebug(__FUNCTION__, $streets);
        // Street search
        $this->UpdateFormField('streetSearch', 'visible', $city);
        $this->UpdateFormField('searchButton', 'visible', $city);
        if ($streets === null) {
            $this->UpdateFormField('streetSearch', 'value', '');
        }
        // Street selection
        $options = $streets ?? [];
        array_unshift($options, ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null']);
        $this->UpdateFormField('streetID', 'options', json_encode($options));
        $this->UpdateFormField('streetID', 'value', 'null');
        $this->UpdateFormField('streetID', 'visible', !empty($streets));
        // Fraction Checkboxes
        $this->UpdateFormField('fractionLabel', 'visible', false);
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
            $this->UpdateFormField('fractionID' . $i, 'visible', false);
        }
    }

    /**
     * Create the variables for the fractions.
     *
     * @return void
     */
    protected function CreateVariables(): void
    {
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        foreach (self::SERVICE_FRACTIONS as $i => $fract) {
            $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
            $this->MaintainVariable($fract[1], $this->Translate($fract[2]), VARIABLETYPE_STRING, ['ICON' => 'trash-can', 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], $i, $enabled || $variable);
        }
    }

    /**
     * Initialize the IO interface array
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(): array
    {
        $io[self::IO_NAME] = '';
        $io[self::IO_STREETS] = [];
        // data2array
        return $io;
    }

    /**
     * Show message via popup
     *
     * @param string $caption echo message
     *
     * @return void
     */
    private function EchoMessage(string $caption): void
    {
        $this->UpdateFormField('EchoMessage', 'caption', $this->Translate($caption));
        $this->UpdateFormField('EchoPopup', 'visible', true);
    }

    /**
     * Search streets of a city.
     *
     * @param int $city City ID
     * @param string $search Search term
     *
     * @return list<array<string,string>>|false Select options or false on error
     */
    private function SearchStreets(int $city, string $search): array|false
    {
        $session = $this->InitSession();
        if ($session === false) {
            return false;
        }
        if ($this->LivewireRequest($session, ['data.city_id' => $city, 'data.year' => (int) date('Y')]) === false) {
            return false;
        }
        $effects = $this->LivewireRequest($session, [], [$this->BuildSearchCall($search)]);
        if ($effects === false) {
            return false;
        }
        $streets = [];
        foreach ($effects['returns'][0] ?? [] as $street) {
            $streets[] = ['caption' => trim((string) $street['label']), 'value' => (string) $street['value']];
        }
        $this->LogDebug(__FUNCTION__, $streets);
        return $streets;
    }

    /**
     * Get all pickup dates of the current year (from september also of the next year).
     *
     * @param int $city City ID
     * @param string $street Street ID
     * @param string $name Street name (for the search call, the service only accepts offered options)
     *
     * @return list<array{date:string,class:string}>|false Pickup dates or false on error
     */
    private function GetPickupDates(int $city, string $street, string $name): array|false
    {
        $year = (int) date('Y');
        $session = $this->InitSession();
        if ($session === false) {
            return false;
        }
        if ($this->LivewireRequest($session, ['data.city_id' => $city, 'data.year' => $year]) === false) {
            return false;
        }
        if (($name != '') && ($this->LivewireRequest($session, [], [$this->BuildSearchCall($name)]) === false)) {
            return false;
        }
        $effects = $this->LivewireRequest($session, ['data.street_id' => $street]);
        if ($effects === false) {
            return false;
        }
        $pickups = $this->ParseCalendar($effects['html'] ?? '', $year);
        if ($pickups === false) {
            return false;
        }
        // next year is already published in autumn
        if ((int) date('n') >= 9) {
            $effects = $this->LivewireRequest($session, ['data.year' => $year + 1]);
            if ($effects !== false) {
                $next = $this->ParseCalendar($effects['html'] ?? '', $year + 1);
                if ($next !== false) {
                    $pickups = array_merge($pickups, $next);
                }
            }
        }
        $this->LogDebug(__FUNCTION__, 'Pickups: ' . count($pickups));
        return $pickups;
    }

    /**
     * Builds the livewire call for the street search.
     *
     * @param string $search Search term
     *
     * @return array<string,mixed> Livewire call
     */
    private function BuildSearchCall(string $search): array
    {
        return [
            'path'   => '',
            'method' => 'callSchemaComponentMethod',
            'params' => ['form.street_id', 'getSearchResultsForJs', ['search' => $search]],
        ];
    }

    /**
     * Loads the start page and extracts the livewire session data (update uri, csrf token, snapshot).
     *
     * @return array{curl:\CurlHandle,uri:string,csrf:string,snapshot:string}|false Session data
     */
    private function InitSession(): array|false
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::$CONNECT_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::$TIMEOUT);
        // Empty cookie file activates the cookie engine for this handle
        curl_setopt($curl, CURLOPT_COOKIEFILE, '');
        curl_setopt($curl, CURLOPT_USERAGENT, self::SERVICE_USERAGENT);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        $html = $this->ServiceRequest($curl, self::SERVICE_BASEURL);
        if ($html === false) {
            return false;
        }
        $dom = new DOMDocument();
        $internal = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        $uri = '';
        $csrf = '';
        $snapshot = '';
        // wire:* attributes can not be queried by xpath (namespace prefix)
        foreach ($dom->getElementsByTagName('*') as $node) {
            if (($uri == '') && $node->hasAttribute('data-update-uri')) {
                $uri = $node->getAttribute('data-update-uri');
                $csrf = $node->getAttribute('data-csrf');
            }
            if (($snapshot == '') && ($node->getAttribute('wire:name') == self::SERVICE_COMPONENT)) {
                $snapshot = $node->getAttribute('wire:snapshot');
            }
        }
        if (($uri == '') || ($csrf == '') || ($snapshot == '')) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Session data not found (uri=' . $uri . ', csrf=' . ($csrf != '' ? 'yes' : 'no') . ', snapshot=' . strlen($snapshot) . ')');
            return false;
        }
        if (str_starts_with($uri, '/')) {
            $uri = rtrim(self::SERVICE_BASEURL, '/') . $uri;
        }
        $this->LogDebug(__FUNCTION__, 'Update uri: ' . $uri);
        return ['curl' => $curl, 'uri' => $uri, 'csrf' => $csrf, 'snapshot' => $snapshot];
    }

    /**
     * Sends a livewire update request and takes over the new snapshot.
     *
     * @param array{curl:\CurlHandle,uri:string,csrf:string,snapshot:string} $session Session data
     * @param array<string,mixed> $updates Property updates
     * @param list<array<string,mixed>> $calls Method calls
     *
     * @return array<string,mixed>|false Effects of the component or false on error
     */
    private function LivewireRequest(array &$session, array $updates, array $calls = []): array|false
    {
        $body = json_encode([
            '_token'     => $session['csrf'],
            'components' => [
                [
                    'snapshot' => $session['snapshot'],
                    'updates'  => (object) $updates,
                    'calls'    => $calls,
                ],
            ],
        ]);
        $headers = [
            'Content-Type: application/json',
            'X-Livewire: 1',
            'X-CSRF-TOKEN: ' . $session['csrf'],
        ];
        $response = $this->ServiceRequest($session['curl'], $session['uri'], $body, $headers);
        if ($response === false) {
            return false;
        }
        $data = json_decode($response, true);
        $snapshot = $data['components'][0]['snapshot'] ?? null;
        if (!is_string($snapshot)) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Invalid livewire response!');
            return false;
        }
        $session['snapshot'] = $snapshot;
        return $data['components'][0]['effects'] ?? [];
    }

    /**
     * Parses the calendar html of one year.
     *
     * @param string $html Calendar html
     * @param int $year Year of the calendar
     *
     * @return list<array{date:string,class:string}>|false Pickup dates or false on error
     */
    private function ParseCalendar(string $html, int $year): array|false
    {
        $dom = new DOMDocument();
        $internal = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        $xpath = new DOMXPath($dom);
        // one grid per month
        $months = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' grid ') and contains(concat(' ', normalize-space(@class), ' '), ' grid-cols-7 ')]");
        if ($months->length != 12) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Expected 12 month grids, found ' . $months->length . ' (' . $year . ')!');
            return false;
        }
        $known = array_column(self::SERVICE_FRACTIONS, 0);
        $pickups = [];
        foreach ($months as $index => $grid) {
            $cells = $xpath->query("./div[contains(concat(' ', normalize-space(@class), ' '), ' flex-col ')]", $grid);
            foreach ($cells as $cell) {
                // day number is the direct child with pure numeric text
                $day = 0;
                foreach ($xpath->query('./div', $cell) as $child) {
                    $text = trim($child->textContent);
                    if (ctype_digit($text)) {
                        $day = (int) $text;
                        break;
                    }
                }
                if ($day == 0) {
                    continue;
                }
                // waste type only by css class
                $markers = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' min-h-10 ')]/div", $cell);
                foreach ($markers as $marker) {
                    if (!$marker instanceof DOMElement) {
                        continue;
                    }
                    foreach (explode(' ', $marker->getAttribute('class')) as $class) {
                        if (!str_starts_with($class, 'bg-')) {
                            continue;
                        }
                        $date = sprintf('%04d%02d%02d', $year, $index + 1, $day);
                        if (!in_array($class, $known)) {
                            $this->LogDebug(__FUNCTION__, 'Unknown waste class: ' . $class . ' (' . $date . ')');
                        }
                        $pickups[] = ['date' => $date, 'class' => $class];
                        break;
                    }
                }
            }
        }
        return $pickups;
    }

    /**
     * Sends the HTTP request.
     *
     * @param \CurlHandle $curl Curl handle (with cookie engine)
     * @param string $url Request URL
     * @param ?string $body JSON body (POST) or null (GET)
     * @param list<string> $headers Additional headers
     *
     * @return string|false Response body or false on error
     */
    private function ServiceRequest(\CurlHandle $curl, string $url, ?string $body = null, array $headers = []): string|false
    {
        curl_setopt($curl, CURLOPT_URL, $url);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        } else {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->LogDebug(__FUNCTION__, 'cURL Error: ' . curl_error($curl));
            return false;
        }
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $this->LogDebug(__FUNCTION__, ($body !== null ? 'POST ' : 'GET ') . $url . ' => HTTP ' . $code . ': ' . substr((string) $response, 0, 200));
        if (($code >= 400) || !is_string($response)) {
            $this->LogDebug(__FUNCTION__, 'ERROR: HTTP ' . $code . ' for ' . $url);
            return false;
        }
        return $response;
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
