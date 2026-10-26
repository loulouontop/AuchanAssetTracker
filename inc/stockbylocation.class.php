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
     * Throttled: skip when a rebuild ran recently so Search sort/filter
     * reloads do not TRUNCATE+rescan on every click (Gateway 504).
     */
    public static function rebuild(bool $force = false): void
    {
        global $DB;

        $table = self::getTable();
        $eq = PluginAuchanassettrackerEquipment::getTable();
        if (!$DB->tableExists($table) || !$DB->tableExists($eq)) {
            return;
        }

        $ttl = 120; // seconds
        $now_ts = time();
        if (!$force) {
            $last = (int) ($_SESSION['aat_stockbylocation_rebuilt_at'] ?? 0);
            if ($last > 0 && ($now_ts - $last) < $ttl) {
                return;
            }
            // Also skip if table already has fresh rows (another request rebuilt).
            try {
                foreach ($DB->request([
                    'SELECT' => ['date_mod'],
                    'FROM'   => $table,
                    'ORDER'  => 'date_mod DESC',
                    'LIMIT'  => 1,
                ]) as $row) {
                    $dm = strtotime((string) ($row['date_mod'] ?? ''));
                    if ($dm !== false && ($now_ts - $dm) < $ttl) {
                        $_SESSION['aat_stockbylocation_rebuilt_at'] = $dm;
                        return;
                    }
                    break;
                }
            } catch (Throwable $e) {
                // Continue with rebuild.
            }
        }

        $counts = [];
        // Aggregate in SQL — much cheaper than loading every equipment row in PHP.
        $sql = 'SELECT `locations_id`, `status`, COUNT(*) AS `cpt`'
            . ' FROM `' . str_replace('`', '``', $eq) . '`'
            . ' WHERE `is_deleted` = 0 AND `locations_id` > 0'
            . ' GROUP BY `locations_id`, `status`';
        $aggregated = false;
        try {
            $result = $DB->doQuery($sql);
            if ($result) {
                $aggregated = true;
                while ($row = $result->fetch_assoc()) {
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
                        $counts[$lid][$st] = (int) ($row['cpt'] ?? 0);
                    }
                }
            }
        } catch (Throwable $e) {
            $aggregated = false;
        }
        if (!$aggregated) {
            foreach ($DB->request([
                'SELECT' => ['locations_id', 'status'],
                'FROM'   => $eq,
                'WHERE'  => ['is_deleted' => 0],
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
        $_SESSION['aat_stockbylocation_rebuilt_at'] = $now_ts;
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
