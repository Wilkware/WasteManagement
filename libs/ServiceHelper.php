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
                $options[] = ['caption' => $client['name'], 'value' => $client['client']];
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
        $this->SendDebug(__FUNCTION__, 'LINK: ' . $url, 0);
        // read API URL
        $json = @file_get_contents($url);
        // error handling
        if ($json === false) {
            $this->LogMessage($this->Translate('Could not load json data!'), KL_ERROR);
            $this->SendDebug(__FUNCTION__, 'ERROR LOAD DATA', 0);
            return [];
        }
        // json decode
        $data = json_decode($json, true);
        // return the events
        return $data['data']['clients'];
    }
}
