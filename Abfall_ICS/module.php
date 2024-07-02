<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';
// ICS Parser
require_once __DIR__ . '/../libs/ics-parser/src/ICal/ICal.php';
require_once __DIR__ . '/../libs/ics-parser/src/ICal/Event.php';

use ICal\ICal;

// CLASS Abfall_ICS
class Abfall_ICS extends IPSModule
{
    use EventHelper;
    use DebugHelper;
    use ServiceHelper;
    use VariableHelper;
    use VisualisationHelper;

    // Service Provider
    private const SERVICE_PROVIDER = 'wmics';
    private const SERVICE_USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36';

    // IO keys
    private const IO_CLIENT = 'id';
    private const IO_LINK = 'url';
    private const IO_FRACTIONS = 'fract';

    // Form Elements Positions
    private const ELEM_IMAGE = 0;
    private const ELEM_LABEL = 1;
    private const ELEM_PROVI = 2;
    private const ELEM_WMICS = 3;
    private const ELEM_VISU = 4;

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Service Provider
        $this->RegisterPropertyString('serviceProvider', self::SERVICE_PROVIDER);
        // Waste Management
        $this->RegisterPropertyString('clientID', 'null');
        $this->RegisterPropertyString('clientURL', '');
        for ($i = 1; $i <= static::$FRACTIONS; $i++) {
            $this->RegisterPropertyBoolean('fractionID' . $i, false);
        }
        // Attributes for dynamic configuration forms
        $this->RegisterAttributeString('io', serialize($this->PrepareIO()));
        // Visualisation
        $this->RegisterPropertyBoolean('settingsTileVisu', false);
        $this->RegisterPropertyString('settingsTileColors', '[]');
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
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Settings
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        // IO Values
        $id = $this->ReadPropertyString('clientID');
        $url = $this->ReadPropertyString('clientURL');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'clientID=' . $id . ' ,clientURL=' . $url);
        // Get Basic Form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Service Provider
        $jsonForm['elements'][self::ELEM_PROVI]['items'][0]['options'] = $this->GetProviderOptions();
        // Waste Management
        $jsonForm['elements'][self::ELEM_WMICS]['items'][0]['items'][0]['options'] = $this->GetClientOptions(self::SERVICE_PROVIDER);
        // IO Data
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        // Button (more)
        if ($io[self::IO_CLIENT] != 'null') {
            $jsonForm['elements'][self::ELEM_WMICS]['items'][0]['items'][1]['visible'] = true;
        }
        // Fractions
        if (!empty($io[self::IO_FRACTIONS])) {
            // Label
            $jsonForm['elements'][self::ELEM_WMICS]['items'][3]['visible'] = true;
            $i = 1;
            foreach ($io[self::IO_FRACTIONS] as $fract) {
                $jsonForm['elements'][self::ELEM_WMICS]['items'][$i + 3]['caption'] = $fract['name'];
                $jsonForm['elements'][self::ELEM_WMICS]['items'][$i + 3]['value'] = $fract['active'];
                $jsonForm['elements'][self::ELEM_WMICS]['items'][$i + 3]['visible'] = true;
                $i++;
            }
        }
        //Only add default element if we do not have anything in persistence
        $colors = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        if (empty($colors)) {
            $this->SendDebug(__FUNCTION__, 'Translate Waste Visu');
            $jsonForm['elements'][self::ELEM_VISU]['items'][1]['values'] = $this->GetWasteValues();
        }
        // Return Form
        return json_encode($jsonForm);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $id = $this->ReadPropertyString('clientID');
        $url = $this->ReadPropertyString('clientURL');
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        $tilevisu = $this->ReadPropertyBoolean('settingsTileVisu');
        $loakahead = $this->ReadPropertyBoolean('settingsLookAhead');
        $this->SendDebug(__FUNCTION__, 'clientID=' . $id . ' ,clientURL=' . $url);
        // Safty default
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetTimerInterval('LookAheadTimer', 0);
        // IO Data
        $io = unserialize($this->ReadAttributeString('io'));
        // Set status
        if (($url == '') || ($url != $io[self::IO_LINK])) {
            $this->WriteAttributeString('io', serialize($this->PrepareIO($id, $url)));
            $status = 201;
        } else {
            $status = 102;
        }
        // take over the selected fractions
        if ($status == 102) {
            $count = count($io[self::IO_FRACTIONS]);
            for ($i = 1; $i <= $count; $i++) {
                $enabled = $this->ReadPropertyBoolean('fractionID' . $i);
                $io[self::IO_FRACTIONS][$i - 1]['active'] = $enabled;
            }
            $this->WriteAttributeString('io', serialize($io));
        }
        // Support for Tile Viso (v7.x)
        $this->MaintainVariable('Widget', $this->Translate('Pickup'), VARIABLETYPE_STRING, '~HTMLBox', 0, $tilevisu);
        // All okay
        if ($status == 102) {
            $this->CreateVariables();
            if ($activate == true) {
                // Time neu berechnen
                $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
                $this->SendDebug(__FUNCTION__, 'Update Timer aktiviert!');
                if ($loakahead & $tilevisu) {
                    $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
                    if (($time['hour'] == 0) && ($time['minute'] <= 30)) {
                        $this->SendDebug(__FUNCTION__, 'LookAhead Time zu niedrieg!');
                    } else {
                        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second'], 0);
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
     * RequestAction.
     *
     *  @param string $ident Ident (function name).
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        eval('$this->' . $ident . '(\'' . $value . '\');');
        return true;
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * WMICS_LookAhead($id);
     */
    public function LookAhead()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        // rebuild informations
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        // fractions convert to ident => values
        $waste = [];
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $fract['ident'] . ', Name: ' . $fract['name']);
            if ($fract['active']) {
                $date = $this->GetValue($fract['ident']);
                $waste[$fract['name']] = ['ident' => $fract['ident'], 'date' => $date];
            }
        }
        $this->SendDebug(__FUNCTION__, $waste);
        // update tile widget
        $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
        $this->BuildWidget($waste, $list, true);
        // Set Timer to the next day
        $time = json_decode($this->ReadPropertyString('settingsLookTime'), true);
        $this->UpdateTimerInterval('LookAheadTimer', $time['hour'], $time['minute'], $time['second']);
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * WMICS_Update($id);
     */
    public function Update()
    {
        // Check instance state
        if ($this->GetStatus() != 102) {
            $this->SendDebug(__FUNCTION__, 'Status: Instance is not active.');
            return;
        }
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);

        // fractions convert to ident => values
        $waste = [];
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            $this->SendDebug(__FUNCTION__, 'Fraction ident: ' . $fract['ident'] . ', Name: ' . $fract['name']);
            if ($fract['active']) {
                $waste[$fract['name']] = ['ident' => $fract['ident'], 'date' => ''];
            }
        }

        //$res = $this->GetBuffer('ics_cache');
        try {
            $ical = new ICal($io[self::IO_LINK], [
                'defaultSpan'                 => 2,     // Default value
                'defaultTimeZone'             => 'UTC',
                'defaultWeekStart'            => 'MO',  // Default value
                'disableCharacterReplacement' => false, // Default value
                'filterDaysAfter'             => null,  // Default value
                'filterDaysBefore'            => null,  // Default value
                'skipRecurrence'              => false, // Default value
            ]);
        } catch (Exception $e) {
            $this->SendDebug(__FUNCTION__, 'initICS: ' . $e);
            return;
        }
        // get all events
        $events = $ical->events();
        // go throw all events
        $this->SendDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            //$this->SendDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
            $dtstart = substr($event->dtstart, 0, 8);
            if ($dtstart < date('Ymd')) {
                continue;
            }
            // YYYYMMDD umwandeln in DD.MM.YYYY
            $day = substr($event->dtstart, 6, 2) . '.' . substr($event->dtstart, 4, 2) . '.' . substr($event->dtstart, 0, 4);
            // Update fraction
            $name = $event->summary;
            if (isset($waste[$name]) && $waste[$name]['date'] == '') {
                $waste[$name]['date'] = $day;
                $this->SendDebug(__FUNCTION__, 'Fraction date: ' . $name . ' = ' . $day);
            }
        }

        // write data to variable
        foreach ($waste as $key => $var) {
            $this->SetValueString((string) $var['ident'], $var['date']);
        }

        // build tile widget
        $btw = $this->ReadPropertyBoolean('settingsTileVisu');
        $this->SendDebug(__FUNCTION__, 'TileVisu: ' . $btw);
        if ($btw == true) {
            $list = json_decode($this->ReadPropertyString('settingsTileColors'), true);
            $this->BuildWidget($waste, $list);
        }

        // execute Script
        $script = $this->ReadPropertyInteger('settingsScript');
        if ($script != 0) {
            if (IPS_ScriptExists($script)) {
                $rs = IPS_RunScript($script);
                $this->SendDebug(__FUNCTION__, 'Script Execute (Return Value): ' . $rs, 0);
            } else {
                $this->SendDebug(__FUNCTION__, 'Update: Script #' . $script . ' existiert nicht!');
            }
        }

        // calculate next update interval
        $activate = $this->ReadPropertyBoolean('settingsActivate');
        if ($activate == true) {
            $this->UpdateTimerInterval('UpdateTimer', 0, 10, 0);
        }
    }

    /**
     * User has clicked to analyse a new waste management (iCal file).
     *
     * @param string $url Client URL.
     */
    protected function OnChangeClient(string $id)
    {
        $this->UpdateFormField('buttonID', 'visible', ($id != 'null'));
    }

    /**
     * User has clicked to analyse a new waste management (iCal file).
     *
     * @param string $url Client URL.
     */
    protected function OnChangeLink(string $url)
    {
        $this->SendDebug(__FUNCTION__, $url);
        // Reset IO data
        $io = $this->PrepareIO($url);
        // ICS data
        try {
            $ical = new ICal($url, [
                'defaultSpan'                 => 2,     // Default value
                'defaultTimeZone'             => 'UTC',
                'defaultWeekStart'            => 'MO',  // Default value
                'disableCharacterReplacement' => false, // Default value
                'filterDaysAfter'             => null,  // Default value
                'filterDaysBefore'            => null,  // Default value
                'skipRecurrence'              => false, // Default value
            ]);
        } catch (Exception $e) {
            $this->SendDebug(__FUNCTION__, 'initICS: ' . $e);
            $this->EchoMessage($e->getMessage());
            return;
        }
        if (!$ical->hasEvents()) {
            $this->EchoMessage($this->Translate('No entries in data available!'));
            return;
        }

        // get all events
        $events = $ical->events();
        // go throw all events
        $names = [];
        $this->SendDebug(__FUNCTION__, 'ICS Events: ' . $ical->eventCount);
        foreach ($events as $event) {
            //$this->SendDebug(__FUNCTION__, 'Event: ' . $event->summary . ' = ' . $event->dtstart);
            //$this->SendDebug(__FUNCTION__, 'Ident: ' . $this->CreateIdent($event->summary));
            //echo $event->printData('%s: %s'.PHP_EOL);
            $ident = $this->GetVariableIdent($this->CreateIdent($event->summary));
            if (!isset($name[$ident])) {
                $names[$ident] = $event->summary;
            }
        }
        foreach ($names as $ident => $name) {
            $io[self::IO_FRACTIONS][] = ['ident' => $ident, 'name' => $name, 'active' => true];
        }
        // take over the new URL
        $io[self::IO_LINK] = $url;
        $this->SendDebug(__FUNCTION__, $io);
        // Hide or Unhide properties
        $this->UpdateForm($io);
        // Update attribute
        $this->WriteAttributeString('io', serialize($io));
    }

    /**
     * Hide/unhide form fields.
     *
     */
    protected function UpdateForm($io)
    {
        $this->SendDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_FRACTIONS])) {
            $this->UpdateFormField('fractionLabel', 'visible', false);
            for ($i = 1; $i <= static::$FRACTIONS; $i++) {
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
            for ($i; $i <= static::$FRACTIONS; $i++) {
                $this->UpdateFormField('fractionID' . $i, 'visible', false);
                $this->UpdateFormField('fractionID' . $i, 'value', false);
            }
        }
    }

    /**
     * Create the variables for the fractions.
     *
     */
    protected function CreateVariables()
    {
        $io = unserialize($this->ReadAttributeString('io'));
        $this->SendDebug(__FUNCTION__, $io);
        if (empty($io[self::IO_FRACTIONS])) {
            return;
        }
        // how to maintain?
        $variable = $this->ReadPropertyBoolean('settingsVariables');
        $i = 1;
        foreach ($io[self::IO_FRACTIONS] as $fract) {
            if ($i <= static::$FRACTIONS) {
                $enabled = $fract['active'];
                $this->MaintainVariable($fract['ident'], $fract['name'], VARIABLETYPE_STRING, '', $i, $enabled || $variable);
            }
            $i++;
        }
    }

    /**
     * Serialize properties to IO interface array
     *
     * @param string $c client url value
     * @return array IO interface
     */
    protected function PrepareIO(string $c = 'null', string $l = 'null')
    {
        $io[self::IO_CLIENT] = $c;
        $io[self::IO_LINK] = ($l != 'null') ? $l : '';
        $io[self::IO_FRACTIONS] = [];
        // data2array
        return $io;
    }

    /**
     * Createan ident for a given long name
     *
     * @param string $summary Long name
     * @return string Genrated ident
     */
    protected function CreateIdent(string $summary)
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
     */
    private function EchoMessage(string $caption)
    {
        $this->UpdateFormField('EchoMessage', 'caption', $this->Translate($caption));
        $this->UpdateFormField('EchoPopup', 'visible', true);
    }
}
