<?php

/**
 * Dashboard “Stock by location” summary — searchable via native GLPI Search.
 * Rows are rebuilt from live equipment counts before each display.
 */
class PluginAuchanassettrackerStockbylocation extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker_equipment';

    public static function getTypeName($nb = 0): string
    {
        return __('Stock by location', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_stockbylocations';
    }

    public static function getIcon(): string
    {
        return 'ti ti-map-pin';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/stockbylocation.php';
    }

    public static function getFormURL($full = true): string
    {
        // Prefer Location form when opening a row; Search uses the Location itemlink column.
        if (class_exists('Location')) {
            return Location::getFormURL($full);
        }
        return plugin_auchanassettracker_web_dir($full) . '/front/stockbylocation.php';
    }

    public static function canView(): bool
    {
        return PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || PluginAuchanassettrackerRighthelper::canSeeEquipment();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
    }

    /**
     * Rebuild summary rows from current equipment stock.
     */
    public static function rebuild(): void
    {
        global $DB;

        $table = self::getTable();
        $eq = PluginAuchanassettrackerEquipment::getTable();
        if (!$DB->tableExists($table) || !$DB->tableExists($eq)) {
            return;
        }

        $counts = [];
        foreach ($DB->request([
            'FROM'  => $eq,
            'WHERE' => ['is_deleted' => 0],
        ]) as $row) {
            $lid = (int) ($row['locations_id'] ?? 0);
            if ($lid <= 0) {
                continue;
            }
            if (!isset($counts[$lid])) {
                $counts[$lid] = [
                    'available'  => 0,
                    'allocated'  => 0,
                    'in_service' => 0,
                    'in_transit' => 0,
                ];
            }
            $st = (string) ($row['status'] ?? '');
            if (isset($counts[$lid][$st])) {
                $counts[$lid][$st]++;
            }
        }

        try {
            $DB->doQuery('TRUNCATE TABLE `' . $table . '`');
        } catch (Throwable $e) {
            $DB->delete($table, ['id' => ['>', 0]]);
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        foreach ($counts as $lid => $c) {
            $DB->insert($table, [
                'locations_id' => (int) $lid,
                'available'    => (int) $c['available'],
                'allocated'    => (int) $c['allocated'],
                'in_service'   => (int) $c['in_service'],
                'in_transit'   => (int) $c['in_transit'],
                'date_mod'     => $now,
            ]);
        }
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => 'glpi_locations',
            'field'         => 'completename',
            'name'          => __('Location'),
            'datatype'      => 'itemlink',
            'linkfield'     => 'locations_id',
            'massiveaction' => false,
            'searchtype'    => ['contains', 'equals', 'notequals'],
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => self::getTable(),
            'field'         => 'available',
            'name'          => __('Available', 'auchanassettracker'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 4,
            'table'         => self::getTable(),
            'field'         => 'allocated',
            'name'          => __('Allocated', 'auchanassettracker'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 5,
            'table'         => self::getTable(),
            'field'         => 'in_service',
            'name'          => __('In service', 'auchanassettracker'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 6,
            'table'         => self::getTable(),
            'field'         => 'in_transit',
            'name'          => __('In transit', 'auchanassettracker'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];

        return $tab;
    }

    /**
     * Seed default visible columns for Search.
     */
    public static function ensureDisplayPreferences(): void
    {
        global $DB;

        if (!$DB->tableExists('glpi_displaypreferences')) {
            return;
        }

        $itemtype = self::class;
        $existing = 0;
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $itemtype,
                'users_id' => 0,
            ],
        ]) as $row) {
            $existing = (int) ($row['cpt'] ?? 0);
        }
        if ($existing > 0) {
            return;
        }

        $rank = 1;
        foreach ([2, 3, 4, 5, 6] as $num) {
            try {
                $DB->insert('glpi_displaypreferences', [
                    'itemtype' => $itemtype,
                    'num'      => $num,
                    'rank'     => $rank,
                    'users_id' => 0,
                ]);
                $rank++;
            } catch (Throwable $e) {
                // Ignore duplicates.
            }
        }
    }
}
