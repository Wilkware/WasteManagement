<?php

/**
 * ServiceHelper.php
 *
 * Part of the Trait-Libraray for IP-Symcon Modules.
 *
 * @package       traits
 * @author        Heiko Wilknitz <heiko@wilkware.de>
 * @copyright     2020 Heiko Wilknitz
 * @link          https://wilkware.de
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ (CC BY-NC-SA 4.0)
 */

declare(strict_types=1);

/**
 * Helper class for the debug output.
 */
trait ServiceHelper
{
    /**
     * Supported Service Provider
     */
    private static $PROVIDERS = [
        'awido' => [1, '{8A591704-E699-4F78-A728-490210FDE747}', 'AWIDO', 'awido-online.de', 'Die Web-Anwendung mit alle wichtigen Entsorgungstermine online!'],
        'abpio' => [2, '{53922265-6F58-E833-34A1-52D44D1C8D3F}', 'Abfall.IO', 'abfallplus.de', 'Abfall+ ist die Lösung für elektronische Bürgerdienste in der Abfallwirtschaft!'],
        'mymde' => [3, '{BCB84068-9194-754C-436F-F10BDD8E51BE}', 'MyMüll', 'mymuell.de', 'Abfall und Wertstoffe sauber organisiert!'],
        'regio' => [4, '{085BA8B2-118B-208D-3664-3C230C55952E}', 'AbfallNavi', 'regioit.de', 'Der digitale Abfallkalender der regio IT für die Abfallentsorgung.'],
        'maxde' => [5, '{2EC7DFA0-62D9-2E92-ADB1-6B8201D142FA}', 'MüllMax', 'muellmax.de', 'Abfallkalender – barrierefrei online und gedruckt!'],
    ];

    /**
     * API URL for Client IDs
     */
    private static $CLIENTS = 'https://api.asmium.de/waste/de/';

    /**
     * Maximale Anzahl an Entsorgungsarten
     */
    private static $FRACTIONS = 30;

    /**
     * Returns the supported service provider for the dropdown menu.
     *
     * @return array Array of service providers
     */
    protected function GetProviderOptions()
    {
        // Options
        $options = [];
        // Build array
        foreach (static::$PROVIDERS as $key => $value) {
            $options[] = ['caption' => $value[2] . ' (' . $value[3] . ')', 'value' => $key];
        }
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Returns the list of available clients for the dropdown menu.
     *
     * @return array Array of clients.
     */
    protected function GetClientOptions($provider)
    {
        $this->SendDebug(__FUNCTION__, $provider);
        // Options
        $options = [];
        // Default key
        $options[] = ['caption' => $this->Translate('Please select ...') . str_repeat(' ', 79), 'value' => 'null'];
        // Build array
        if ($provider != 'null') {
            $link = static::$CLIENTS . $provider;
            $data = $this->ExtractClients($link);
            foreach ($data as $client) {
                $value = $client['client'];
                if (isset($client['domain'])) {
                    $value = $value . ':' . $client['domain'];
                }
                $options[] = ['caption' => $client['name'], 'value' => $value];
            }
        }
        //$this->SendDebug(__FUNCTION__, $options);
        return $options;
    }

    /**
     * Get and extract clients from json format.
     *
     * @param string $url API URL to receive client information.
     * @return array  array, with name and client id
     */
    private function ExtractClients(string $url): array
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, 'LINK: ' . $url);
        // read API URL
        $json = @file_get_contents($url);
        // error handling
        if ($json === false) {
            $this->LogMessage($this->Translate('Could not load json data!'), KL_ERROR);
            $this->SendDebug(__FUNCTION__, 'ERROR LOAD DATA');
            return [];
        }
        // json decode
        $data = json_decode($json, true);
        // return the events
        return $data['data']['clients'];
    }

    /**
     * Order assoziate array data
     *
     * @param array $arr
     * @param string|null $key
     * @param string $direction
     */
    private function OrderData(array $arr, string $key = null, string $direction = 'ASC')
    {
        // Check "order by key"
        if (!is_string($key) && !is_array($key)) {
            throw new InvalidArgumentException('Order() expects the first parameter to be a valid key or array');
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
        usort($arr, function ($a, $b) use ($props) {
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