<?php

/**
 * ServiceHelper.php
 *
 * Part of the Trait-Libraray for Symcon Modules.
 *
 * @package       traits
 * @author        Heiko Wilknitz <heiko@wilkware.de>
 * @copyright     2026 Heiko Wilknitz
 * @link          https://wilkware.de
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ (CC BY-NC-SA 4.0)
 */

declare(strict_types=1);

/** @symcon-namespace */

namespace Wilkware\WasteManagement;

/**
 * Helper class for the debug output.
 */
trait ServiceHelper
{
    /** @var array<string,array{int,string,string,string,string}> $PROVIDERS Supported Service Provider */
    private static array $PROVIDERS = [
        'awido' => [1, '{8A591704-E699-4F78-A728-490210FDE747}', 'AWIDO', 'awido-online.de', 'The web application with all important waste disposal dates online!'],
        'abpio' => [2, '{53922265-6F58-E833-34A1-52D44D1C8D3F}', 'Abfall.IO', 'abfallplus.de', 'Abfall+ is the solution for electronic citizen services in waste management!'],
        'mymde' => [3, '{BCB84068-9194-754C-436F-F10BDD8E51BE}', 'MyMüll', 'mymuell.de', 'Waste and recyclables neatly organised!'],
        'regio' => [4, '{085BA8B2-118B-208D-3664-3C230C55952E}', 'AbfallNavi', 'regioit.de', 'The digital waste calendar from regio IT for waste disposal.'],
        'maxde' => [5, '{2EC7DFA0-62D9-2E92-ADB1-6B8201D142FA}', 'MüllMax', 'muellmax.de', 'Waste calendar - barrier-free online and printed!'],
        'wmics' => [6, '{9E99E213-F884-FC95-A8ED-4F3FCC368E70}', 'Abfall.ICS', 'asmium.de', 'Read out waste data via ICS calendar file.'],
        'apapp' => [7, '{E3371D9B-9206-9584-4ACE-03AB9DB9C346}', 'Abfall.APP', 'abfallplus.de', 'Abfall+ is the solution to the challenges of waste management.'],
        'sec27' => [8, '{7A9CE295-F3E3-4299-9A2D-8DFE75EB085D}', 'sector27', 'sector27.de', 'The digital waste calendar from sector27.'],
    ];

    /** @var array<string,array<string,string>> $COUNTRIES Supported Countries per Service */
    private static array $COUNTRIES = [
        'awido' => ['de' => 'Germany'],
        'abpio' => ['de' => 'Germany'],
        'mymde' => ['de' => 'Germany'],
        'regio' => ['de' => 'Germany'],
        'maxde' => ['de' => 'Germany'],
        'wmics' => ['de' => 'Germany', 'at' => 'Austria', 'ch' => 'Switzerland'],
        'apapp' => ['de' => 'Germany', 'at' => 'Austria'],
        'sec27' => ['de' => 'Germany'],
    ];

    /** @var string $CLIENTS API URL for Client IDs */
    private static string $CLIENTS = 'https://api.asmium.de/waste/';

    /** @var int $FRACTIONS Maximum number of waste types */
    private static int $FRACTIONS = 30;

    /** @var int $TIMEOUT Timeout in seconds for service requests */
    private static int $TIMEOUT = 30;

    /** @var int $CONNECT_TIMEOUT Connect timeout in seconds for service requests */
    private static int $CONNECT_TIMEOUT = 10;

    /** @var int $RETRY Retry interval in seconds after a failed update */
    private static int $RETRY = 3600;

    /**
     * Returns the supported service provider for the dropdown menu.
     *
     * @return list<array<string,string>> Array of service providers
     */
    protected function GetProviderOptions(): array
    {
        // Options
        $options = [];
        // Build array
        foreach (self::$PROVIDERS as $key => $value) {
            $options[] = ['caption' => $value[2] . ' (' . $value[3] . ')', 'value' => $key];
        }
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns the list of available countries per services provider.
     *
     * @param string $provider Service provider identifier
     *
     * @return list<array<string,string>> Array of countries.
     */
    protected function GetCountryOptions(string $provider): array
    {
        $this->LogDebug(__FUNCTION__, $provider);
        // Options
        $options = [];
        // Default key
        foreach (self::$COUNTRIES[$provider] as $key => $value) {
            $options[] = ['caption' => $this->Translate($value), 'value' => $key];
        }
        $this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns the list of available clients for the dropdown menu.
     *
     * @param string $provider Service provider identifier
     * @param string $country Service country identifier
     *
     * @return list<array<string,string>> Array of clients.
     */
    protected function GetClientOptions(string $provider, string $country = 'de'): array
    {
        $this->LogDebug(__FUNCTION__, $provider);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Build array
        if ($provider != 'null') {
            $link = self::$CLIENTS . $country . '/' . $provider;
            $data = $this->ExtractClients($link);
            foreach ($data as $client) {
                $value = $client['client'];
                if (isset($client['domain'])) {
                    $value = $value . ':' . $client['domain'];
                }
                $options[] = ['caption' => $client['name'], 'value' => $value];
            }
        }
        // Debug
        // $options[] = ['caption' => 'Testgebiet', 'value' => '12345678790'];
        //$this->LogDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Sets the service error status and schedules a retry of the update (only if the daily update is active).
     * After a successful update the timer is set back to the daily schedule.
     *
     * @param int $status Service error status code
     * @param string $activate Name of the property which activates the daily update
     *
     * @return void
     */
    protected function SetServiceError(int $status, string $activate = 'settingsActivate'): void
    {
        $this->SetStatus($status);
        if ($this->ReadPropertyBoolean($activate)) {
            $this->SetTimerInterval('UpdateTimer', self::$RETRY * 1000);
            $this->LogDebug(__FUNCTION__, 'Retry update in ' . self::$RETRY . ' seconds');
        }
    }

    /**
     * Get and extract clients from json format.
     *
     * @param string $url API URL to receive client information.
     *
     * @return list<array<string,string>> Array, with name and client id
     */
    private function ExtractClients(string $url): array
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, 'LINK: ' . $url);
        // read API URL
        $json = $this->GetUrlContent($url);
        // error handling (GetUrlContent has already notified the user)
        if ($json === false) {
            return [];
        }
        // json decode
        $data = json_decode($json, true);
        if (!is_array($data['data']['clients'] ?? null)) {
            $this->LogMessage($this->Translate('Could not load json data!'), KL_ERROR);
            $this->LogDebug(__FUNCTION__, 'ERROR: Invalid json data!');
            return [];
        }
        // return the clients
        return $data['data']['clients'];
    }

    /**
     * Reads the content of an URL (with timeout).
     *
     * @param string $url URL to read
     *
     * @return string|false Content or false on error
     */
    private function GetUrlContent(string $url): string|false
    {
        $context = stream_context_create(['http' => ['timeout' => self::$TIMEOUT]]);
        // the result is checked and the user is notified on failure
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $this->LogDebug(__FUNCTION__, 'ERROR: Could not load ' . $url);
            $this->LogMessage('Could not load data from ' . $url, KL_WARNING);
        }
        return $content;
    }

    /**
     * Order assoziate array data
     *
     * @param array<mixed> $arr Array to order
     * @param string|list<string>|null $key Key or array of keys to order by
     * @param string $direction Direction of sorting (ASC or DESC)
     * @return array<mixed> Ordered array
     */
    private function OrderData(array $arr, string|array|null $key = null, string $direction = 'ASC'): array
    {
        // Check "order by key"
        if (!is_string($key) && !is_array($key)) {
            throw new \InvalidArgumentException('Order() expects the first parameter to be a valid key or array');
        }
        // Build order-by clausel
        $props = [];
        if (is_string($key)) {
            $props[$key] = strtolower($direction) == 'asc' ? 1 : -1;
        } else {
            $i = count($key);
            foreach ($key as $k => $dir) {
                $props[$k] = strtolower($dir) == 'asc' ? $i : -($i);
                $i--;
            }
        }
        // Sort by passed keys
        usort($arr, function ($a, $b) use ($props)
        {
            foreach ($props as $key => $val) {
                if ($a[$key] == $b[$key]) {
                    continue;
                }
                return $a[$key] > $b[$key] ? $val : -($val);
            }
            return 0;
        });
        // Return sorted array
        return $arr;
    }
}