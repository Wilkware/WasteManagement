<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/**
 * Class WasteManagementConfigurator
 *
 * Lists all available waste management providers and makes them
 *  available for creation and configuration in Symcon.
 */
class WasteManagementConfigurator extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use ServiceHelper;

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
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][1]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);

        // Collect all values
        $values = [];

        // Build configuration list values
        foreach (self::$PROVIDERS as $key => $value) {
            $values[] = [
                'id'            => $value[0],
                'provider'      => $value[2],
                'description'   => $this->Translate($value[4]),
                'url'           => $value[3],
            ];
        }
        foreach (self::$PROVIDERS as $key => $value) {
            $modules = @IPS_GetInstanceListByModuleID($value[1]);
            $count = 0;
            foreach ($modules as $id) {
                $values[] = [
                    'parent'        => $value[0],
                    'id'            => ($value[0] * 100) + $count,
                    'instanceID'    => $id,
                    'provider'      => IPS_GetName($id),
                    'description'   => '-',
                    'url'           => '-',
                    'create'        => [
                        [
                            'moduleID'      => $value[1],
                            'configuration' => ['serviceProvider' => $key],
                        ],
                    ],
                ];
            }
            $values[] = [
                'parent'            => $value[0],
                'id'                => ($value[0] * 100) + $count,
                'provider'          => $this->Translate('New disposal calendar'),
                'description'       => '-',
                'url'               => '-',
                'create'            => [
                    [
                        'moduleID'      => $value[1],
                        'configuration' => ['serviceProvider' => $key],
                    ],
                ],
            ];
        }

        // Set available values
        if (!empty($values)) {
            $form['actions'][0]['values'] = $values;
        }

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
    }
}
