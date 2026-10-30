<?php

declare(strict_types=1);

/** General functions */
require_once __DIR__ . '/../libs/_traits.php';

/** ICS Parser */
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

/** Namespaced traits */
use ICal\ICal;
use Wilkware\WasteManagement\DebugHelper;
use Wilkware\WasteManagement\EventHelper;
use Wilkware\WasteManagement\FormatHelper;
use Wilkware\WasteManagement\FormHelper;
use Wilkware\WasteManagement\ServiceHelper;
use Wilkware\WasteManagement\VariableHelper;

use Wilkware\WasteManagement\VisualisationHelper;

/**
 * CLASS Abfall_ICS
 */
class Abfall_ICS extends IPSModuleStrict
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
    private const SERVICE_PROVIDER = 'wmics';

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var int Import type link */
    private const IMPORT_LINK = 0;

    /** @var int Import type file */
    private const IMPORT_FILE = 1;

    /** @var string IO client id */
    private const IO_CLIENT = 'id';

    /** @var string IO type */
    private const IO_TYPE = 'type';

    /** @var string IO fractions */
    private const IO_FRACTIONS = 'fract';

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
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyInteger('clientTYPE', self::IMPORT_LINK);
        $this->RegisterPropertyString('clientURL', ''); // => clientLINK !!!
        $this->RegisterPropertyString('clientFILE', '');
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
        $this->RegisterTimer('UpdateTimer', 0, 'WMICS_Update(' . $this->InstanceID . ');');

        // Register daily look ahead timer
        $this->RegisterTimer('LookAheadTimer', 0, 'WMICS_LookAhead(' . $this->InstanceID . ');');

        // Buffer for ICS/CSV data
        $this->SetBuffer('ics_cache', '');

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
        $id = $this->ReadPropertyString('clientID');
        $type = $this->ReadPropertyInteger('clientTYPE');

        // Debug output
        $this->LogDebug(__FUNCTION__, 'clientID=' . $id . ' ,clientTYPE=' . $type);

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

        // IO Data
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);

        // Button (more)
        if ($io[self::IO_CLIENT] != 'null') {
            $this->ModifyFormElement($form['elements'], 'buttonID', function (array &$element): void
            {
                $element['visible'] = true;
            });
        }

        // Type (File or Link)
        $this->ModifyFormElement($form['elements'], 'clientURL', function (array &$element) use ($type): void
        {
            $element['visible'] = ($type == self::IMPORT_LINK);
        });
        $this->ModifyFormElement($form['elements'], 'clientFILE', function (array &$element) use ($type): void
        {
            $element['visible'] = ($type == self::IMPORT_FILE);
        });
        $this->ModifyFormElement($form['elements'], 'urlHint', function (array &$element) use ($type): void
        {
            $element['visible'] = ($type == self::IMPORT_LINK);
        });

        // Fractions
        if (!empty($io[self::IO_FRACTIONS])) {
            // Label
            $this->ModifyFormElement($form['elements'], 'fractionLabel', function (array &$element): void
            {
                $element['visible'] = true;
            });
            $i = 1;
            foreach ($io[self::IO_FRACTIONS] as $fract) {
                $this->ModifyFormElement($form['elements'], 'fractionID' . $i, function (array &$element) use ($fract): void
                {
                    $element['caption'] = $fract['name'];
                    $element['value'] = $fract['active'];
                    $element['visible'] = true;
                });
                $i++;
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
        $id = $this->ReadPropertyString('clientID');
        $type = $this->ReadPropertyInteger('clientTYPE');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $htmlbox = $this->ReadPropertyBoolean('settingsHtmlBox');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->LogDebug(__FUNCTION__, 'clientID=' . $id . ' ,clientTYPE=' . $type);
        // Safety default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // Set status
        $status = 102;
        if ($type == self::IMPORT_LINK) {
            $url = $this->ReadPropertyString('clientURL');
            if (($url == '')) {
                $status = 201;
            }
        } else {
            $file = $this->ReadPropertyString('clientFILE');
            if (($file == '')) {
                $status = 201;
            }
        }
        // take over the selected fractions
        if ($status == 102) {
            // IO Type
            $io = unserialize($this->ReadAttributeString('io'));
            $io[self::IO_TYPE] = $type;
            // IO Fractions
            $count = count($io[self::IO_FRACTIONS]);
            for ($i = 1; $i <= $count; $i++) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $io[self::IO_FRACTIONS][$i - 1]['active'] = $enabled;
            }
            $this->WriteAttributeString('io', serialize($io));
        }
        // Support for Tile Visu (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT], 0, $tilevisu && $htmlbox);
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
        $this->SetBuffer('ics_cache', '');
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
            case 'OnChangeImport':
                $this->OnChangeImport((string) $value);
                break;
            case 'OnChangeType':
                $this->OnChangeType((int) $value);
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
     * WMICS_LookAhead($id);
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

        // rebuild informations
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);

        // fractions convert to ident => values
        $waste = [];
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $fract['ident'] . ', Name: ' . $fract['name']);
            if ($fract['active']) {
                $date = $this->GetValue($fract['ident']);
                $waste[$fract['name']] = ['ident' => $fract['ident'], 'date' => $date];
            }
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
     * WMICS_Update($id);
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

        // fractions convert to ident => values
        $waste = [];
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            $this->LogDebug(__FUNCTION__, 'Fraction ident: ' . $fract['ident'] . ', Name: ' . $fract['name']);
            if ($fract['active']) {
                $waste[$fract['name']] = ['ident' => $fract['ident'], 'date' => ''];
            }
        }

        //$res = $this->GetBuffer('ics_cache');
        try {
            $ical = new ICal(false, [
                'defaultSpan'                 => 2,     // Default value
                'defaultTimeZone'             => 'UTC',
                'defaultWeekStart'            => 'MO',  // Default value
                'disableCharacterReplacement' => false, // Default value
                'filterDaysAfter'             => null,  // Default value
                'filterDaysBefore'            => null,  // Default value
                'skipRecurrence'              => false, // Default value
            ]);
            if ($io[self::IO_TYPE] == self::IMPORT_LINK) {
                $ical->initUrl($this->ExpandUrl($this->ReadPropertyString('clientURL')));
            } else {
                $ical->initString(base64_decode($this->ReadPropertyString('clientFILE')));
            }
        } catch (Exception $e) {
            $this->LogDebug(__FUNCTION__, 'initICS: ' . $e);
            $this->LogMessage('Could not retrieve collection dates from service (see debug for details)!', KL_ERROR);
            $this->SetServiceError(self::STATUS_SERVICE_ERROR);
            return;
        }
        $this->SetStatus(102);
        // get all events
        $events = $ical->sortEventsWithOrder($ical->events());
        // go through all events
        $this->LogDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            $this->LogDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
            $dtstart = substr($event->dtstart, 0, 8);
            if ($dtstart < date('Ymd')) {
                continue;
            }
            // convert YYYYMMDD into DD.MM.YYYY
            $day = substr($event->dtstart, 6, 2) . '.' . substr($event->dtstart, 4, 2) . '.' . substr($event->dtstart, 0, 4);
            // Update fraction
            $name = $event->summary;
            if (isset($waste[$name]) && $waste[$name]['date'] == '') {
                $waste[$name]['date'] = $day;
                $this->LogDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $day);
            }
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $var['ident'], $var['date']);
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
        $this->UpdateFormField('clientID', 'options', json_encode($options));
        $this->UpdateFormField('clientID', 'visible', true);
        $this->UpdateFormField('clientID', 'value', 'null');
        $this->OnChangeClient('null');
    }

    /**
     * User has clicked to a new client.
     *
     * @param string $id Client ID.
     *
     * @return void
     */
    protected function OnChangeClient(string $id): void
    {
        $this->UpdateFormField('buttonID', 'visible', ($id != 'null'));
    }

    /**
     * User has clicked a file.
     *
     * @param string $file iCal file.
     *
     * @return void
     */
    protected function OnChangeFile(string $file): void
    {
        $this->LogDebug(__FUNCTION__, $file);
    }

    /**
     * User has clicked to analyse a new waste management.
     *
     * @param string $value Serialized waste management data.
     *
     * @return void
     */
    protected function OnChangeImport(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        // Reset IO data
        $io = $this->PrepareIO($data['c'], $data['t']);
        // ICS data
        try {
            $ical = new ICal(false, [
                'defaultSpan'                 => 2,     // Default value
                'defaultTimeZone'             => 'UTC',
                'defaultWeekStart'            => 'MO',  // Default value
                'disableCharacterReplacement' => false, // Default value
                'filterDaysAfter'             => null,  // Default value
                'filterDaysBefore'            => null,  // Default value
                'skipRecurrence'              => false, // Default value
            ]);
            if ($io[self::IO_TYPE] == self::IMPORT_LINK) {
                $ical->initUrl($this->ExpandUrl($data['l']));
            } else {
                $ical->initString(base64_decode($data['f']));
            }
        } catch (Exception $e) {
            $this->LogDebug(__FUNCTION__, 'initICS: ' . $e);
            $this->EchoMessage($e->getMessage());
            return;
        }
        if (!$ical->hasEvents()) {
            $this->EchoMessage($this->Translate('No entries in data available!'));
            return;
        }

        // get all events
        $events = $ical->events();
        // go through all events
        $names = [];
        $this->LogDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            //$this->LogDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
            //$this->LogDebug(__FUNCTION__, 'Ident: ' . $this->CreateIdent($event->summary));
            //echo $event->printData('%s: %s'.PHP_EOL);
            $ident = $this->GetVariableIdent($this->CreateIdent($event->summary));
            if (!isset($names[$ident])) {
                $names[$ident] = $event->summary;
            }
        }

        // take over only max 30 fractions
        foreach (array_slice($names, 0, self::$FRACTIONS, true) as $ident => $name) {
            $io[self::IO_FRACTIONS][] = [
                'ident'  => $ident,
                'name'   => $name,
                'active' => true,
            ];
        }

        $this->LogDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * User has select an other kind of import type.
     *
     * @param int $value import type.
     *
     * @return void
     */
    protected function OnChangeType(int $value): void
    {
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value);
        $this->UpdateFormField('clientURL', 'visible', ($value == self::IMPORT_LINK));
        $this->UpdateFormField('urlHint', 'visible', ($value == self::IMPORT_LINK));
        $this->UpdateFormField('clientFILE', 'visible', ($value == self::IMPORT_FILE));
    }

    /**
     * Hide/unhide form fields.
     *
     * @param array<string,mixed> $io IO interface data
     *
     * @return void
     */
    protected function UpdateForm(array $io): void
    {
        $this->LogDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_FRACTIONS])) {
            $this->UpdateFormField('fractionLabel', 'visible', false);
            for ($i = 1; $i <= self::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
        else {
            $this->UpdateFormField('fractionLabel', 'visible', true);
            $i = 1;
            foreach ($io[self::IO_FRACTIONS] as $fract) {
                $this->UpdateFormField('fractionID' . $i, 'visible', true);
                $this->UpdateFormField('fractionID' . $i, 'caption', $fract['name']);
                $this->UpdateFormField('fractionID' . $i, 'value', $fract['active']);
                $i++;
            }
            for ($i; $i <= self::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
    }

    /**
     * Create the variables for the fractions.
     *
     * @return void
     */
    protected function CreateVariables(): void
    {
        $io = unserialize($this->ReadAttributeString('io'));
        $this->LogDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_FRACTIONS])) {
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            if ($i <= self::$FRACTIONS) {
                $enabled = $fract['active'];
                $this->MaintainVariable($fract['ident'], $fract['name'], VARIABLETYPE_STRING, ['ICON' => 'trash-can', 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @param ?string $client client value
     * @param ?int $type type value
     *
     * @return array<string,mixed> IO interface data
     */
    protected function PrepareIO(?string $client = 'null', ?int $type = 0): array
    {
        $io[self::IO_CLIENT] = $client;
        $io[self::IO_TYPE] = $type;
        $io[self::IO_FRACTIONS] = [];
        // data2array
        return $io;
    }

    /**
     * Createan ident for a given long name
     *
     * @param string $summary Long name
     *
     * @return string Generated ident
     */
    protected function CreateIdent(string $summary): string
    {
        $hash = hash('sha1', $summary);
        $base = base64_encode($hash);
        $ident = substr($base, 0, 15); // Extract the first 15 characters
        return $ident;
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
     * Expands date placeholders and the webcal scheme of a calendar link.
     *
     * Supported placeholders: {%Y} (2026), {%y} (26), {%m} (01-12), {%d} (01-31)
     *
     * @param string $url Calendar link, e.g. https://example.com/ics?year={%Y}
     *
     * @return string Link with the current date values
     */
    private function ExpandUrl(string $url): string
    {
        $url = preg_replace('/^webcal:\/\//i', 'https://', trim($url));
        return preg_replace_callback('/\{%([Yymd])\}/', fn ($m) => date($m[1]), $url);
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
