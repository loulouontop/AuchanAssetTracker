<?php

class PluginAuchanassettrackerEquipmenttype extends CommonDropdown
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return _n('Equipment type', 'Equipment types', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipmenttypes';
    }

    /** Legacy dropdown — send any leftover links to Equipment list. */
    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'category',
                'label' => __('Category', 'auchanassettracker'),
                'type'  => 'dropdown',
                'list'  => true,
            ],
            [
                'name'  => 'is_active',
                'label' => __('Active'),
                'type'  => 'bool',
                'list'  => true,
            ],
        ];
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $tab[] = [
            'id'       => 11,
            'table'    => self::getTable(),
            'field'    => 'category',
            'name'     => __('Category', 'auchanassettracker'),
            'datatype' => 'string',
        ];
        return $tab;
    }

    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        if (($field['name'] ?? '') === 'category') {
            Dropdown::showFromArray('category', [
                'A' => __('Category A (serial required)', 'auchanassettracker'),
                'B' => __('Category B (accessories)', 'auchanassettracker'),
            ], ['value' => $this->fields['category'] ?? 'A']);
        }
    }

    public static function seedDefaults(): void
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return;
        }

        $defaults = [
            ['Laptop', 'A'],
            ['Desktop computer', 'A'],
            ['Tablet', 'A'],
            ['PDA', 'A'],
            ['Service mobile phone', 'A'],
            ['Monitor', 'A'],
            ['Mouse', 'B'],
            ['Keyboard', 'B'],
            ['Charger', 'B'],
            ['Cable', 'B'],
            ['Docking station', 'B'],
            ['Headset', 'B'],
        ];

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        foreach ($defaults as [$name, $cat]) {
            $exists = false;
            foreach ($DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['name' => $name],
                'LIMIT' => 1,
            ]) as $_) {
                $exists = true;
            }
            if (!$exists) {
                $DB->insert(self::getTable(), [
                    'name'          => $name,
                    'category'      => $cat,
                    'is_active'     => 1,
                    'date_creation' => $now,
                    'date_mod'      => $now,
                ]);
            }
        }
    }

    public static function isCategoryA(int $type_id): bool
    {
        global $DB;
        foreach ($DB->request([
            'SELECT' => ['category'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['id' => $type_id],
            'LIMIT'  => 1,
        ]) as $row) {
            return ($row['category'] ?? 'A') === 'A';
        }
        return true;
    }
}
