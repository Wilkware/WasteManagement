<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// Waste Management Configurator
class WasteManagementConfigurator extends IPSModule
{
    // Helper Traits
    use DebugHelper;
    use ServiceHelper;

    /**
     * Overrides the internal IPS_Create($id) function
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Properties
        $this->RegisterPropertyInteger('TargetCategory', 0);
    }

    /**
     * Overrides the internal IPS_ApplyChanges($id) function
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Save location
        $location = $this->GetPathOfCategory($this->ReadPropertyInteger('TargetCategory'));
        // Build configuration list values
        foreach (static::$PROVIDERS as $key => $value) {
            $values[] = [
                'id'            => $value[0],
                'provider'      => $value[2],
                'description'   => $value[4],
                'url'           => $value[3],
            ];
        }
        foreach (static::$PROVIDERS as $key => $value) {
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
                            'location'      => $location,
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
                        'location'      => $location,
                    ],
                ],
            ];
        }
        $form['actions'][0]['values'] = $values;

        return json_encode($form);
    }

    /**
     * Returns the ascending list of category names for a given category id
     *
     * @param int $categoryId Category ID.
     * @return array List of reverse catergory names.
     */
    private function GetPathOfCategory(int $categoryId): array
    {
        if ($categoryId === 0) {
            return [];
        }

        $path[] = IPS_GetName($categoryId);
        $parentId = IPS_GetObject($categoryId)['ParentID'];

        while ($parentId > 0) {
            $path[] = IPS_GetName($parentId);
            $parentId = IPS_GetObject($parentId)['ParentID'];
        }

        return array_reverse($path);
    }
}
