<?php

/**
 * Interactive reports + CSV / Excel export (Sprint 5).
 */
class PluginAuchanassettrackerReport
{
    public static function getTypes(): array
    {
        return [
            'inventory_location'  => __('Current inventory by location', 'auchanassettracker'),
            'equipment_user'      => __('Equipment per user', 'auchanassettracker'),
            'movements'           => __('Movements over a period', 'auchanassettracker'),
            'stock_by_type'       => __('Stock by type', 'auchanassettracker'),
            'issues'              => __('Equipment with issues', 'auchanassettracker'),
            'inventory_container' => __('Inventory by container', 'auchanassettracker'),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public static function run(string $type, array $filters): array
    {
        return match ($type) {
            'inventory_location'  => self::inventoryByLocation($filters),
            'equipment_user'      => self::equipmentPerUser($filters),
            'movements'           => self::movements($filters),
            'stock_by_type'       => self::stockByType($filters),
            'issues'              => self::issues($filters),
            'inventory_container' => self::inventoryByContainer($filters),
            default               => ['headers' => [], 'rows' => []],
        };
    }

    public static function toCsv(array $report): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        // UTF-8 BOM so Excel opens Romanian diacritics correctly.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $report['headers'] ?? []);
        foreach ($report['rows'] ?? [] as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $csv;
    }

    /**
     * SpreadsheetML XML that Excel opens as .xls (no external library).
     */
    public static function toExcelXml(array $report): string
    {
        $esc = static function ($v): string {
            return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="Report"><Table>' . "\n";

        $xml .= '<Row>';
        foreach ($report['headers'] ?? [] as $h) {
            $xml .= '<Cell><Data ss:Type="String">' . $esc($h) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($report['rows'] ?? [] as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . $esc($cell) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }

    private static function inventoryByLocation(array $filters): array
    {
        global $DB;

        $where = ['is_deleted' => 0];
        if (!empty($filters['locations_id'])) {
            $where['locations_id'] = (int) $filters['locations_id'];
        }
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $where['locations_id'] = $scope;
        }

        $headers = [
            __('ID'),
            __('Name'),
            __('Serial number'),
            __('Type'),
            __('Status'),
            __('Location'),
            __('Container', 'auchanassettracker'),
            __('User'),
        ];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
            'ORDER' => 'locations_id ASC, name ASC',
        ]) as $r) {
            $rows[] = [
                (string) $r['id'],
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerEquipmenttype::getTable(),
                    (int) $r['plugin_auchanassettracker_equipmenttypes_id']
                ),
                PluginAuchanassettrackerEquipment::getStatusLabel((string) $r['status']),
                Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id']),
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerContainer::getTable(),
                    (int) $r['plugin_auchanassettracker_containers_id']
                ),
                !empty($r['users_id']) ? getUserName((int) $r['users_id']) : '',
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function equipmentPerUser(array $filters): array
    {
        global $DB;

        $uid = (int) ($filters['users_id'] ?? 0);
        $where = ['is_deleted' => 0];
        if ($uid > 0) {
            $where['users_id'] = $uid;
        } else {
            $where[] = ['users_id' => ['>', 0]];
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $where['locations_id'] = $scope;
        }

        $headers = [
            __('User'),
            __('Equipment'),
            __('Serial number'),
            __('Status'),
            __('Allocation date', 'auchanassettracker'),
        ];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
            'ORDER' => 'users_id ASC',
        ]) as $r) {
            $rows[] = [
                getUserName((int) $r['users_id']),
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                PluginAuchanassettrackerEquipment::getStatusLabel((string) $r['status']),
                (string) ($r['date_mod'] ?? ''),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function movements(array $filters): array
    {
        global $DB;

        $from = (string) ($filters['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $to   = (string) ($filters['date_to'] ?? date('Y-m-d'));

        $headers = [__('Date'), __('Action'), __('User'), __('Entity'), __('Details')];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerAuditlog::getTable(),
            'WHERE' => [
                ['date_creation' => ['>=', $from . ' 00:00:00']],
                ['date_creation' => ['<=', $to . ' 23:59:59']],
            ],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => 5000,
        ]) as $r) {
            $rows[] = [
                (string) $r['date_creation'],
                (string) $r['action'],
                getUserName((int) $r['users_id']),
                (string) $r['itemtype'] . ' #' . $r['items_id'],
                (string) ($r['details'] ?? ''),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function stockByType(array $filters): array
    {
        global $DB;

        $where = [
            'status'     => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            'is_deleted' => 0,
        ];
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $where['locations_id'] = $scope;
        } elseif (!empty($filters['locations_id'])) {
            $where['locations_id'] = (int) $filters['locations_id'];
        }

        $headers = [__('Type'), __('Location'), __('Quantity')];
        $rows = [];
        $agg = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
        ]) as $r) {
            $key = (int) $r['plugin_auchanassettracker_equipmenttypes_id'] . ':' . (int) $r['locations_id'];
            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'type' => (int) $r['plugin_auchanassettracker_equipmenttypes_id'],
                    'loc'  => (int) $r['locations_id'],
                    'cpt'  => 0,
                ];
            }
            $agg[$key]['cpt']++;
        }
        foreach ($agg as $r) {
            $rows[] = [
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerEquipmenttype::getTable(),
                    $r['type']
                ),
                Dropdown::getDropdownName('glpi_locations', $r['loc']),
                (string) $r['cpt'],
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function issues(array $filters): array
    {
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $headers = [
            __('Issue type', 'auchanassettracker'),
            __('Equipment'),
            __('Location'),
            __('Days', 'auchanassettracker'),
        ];
        $rows = [];

        foreach (PluginAuchanassettrackerTickethook::getOverdueInService($scope) as $r) {
            $rows[] = [
                __('In service overtime', 'auchanassettracker'),
                (string) ($r['name'] ?? ''),
                Dropdown::getDropdownName('glpi_locations', (int) ($r['locations_id'] ?? 0)),
                (string) PluginAuchanassettrackerDashboard::daysSince($r['service_since'] ?? null),
            ];
        }
        foreach (PluginAuchanassettrackerAllocation::getOverduePending($scope) as $r) {
            $rows[] = [
                __('Unconfirmed allocation', 'auchanassettracker'),
                (string) ($r['equipment_name'] ?? ''),
                Dropdown::getDropdownName('glpi_locations', (int) ($r['locations_id'] ?? 0)),
                (string) PluginAuchanassettrackerDashboard::daysSince($r['allocation_date'] ?? null),
            ];
        }
        foreach (PluginAuchanassettrackerTransfer::getOverdue($scope) as $r) {
            $rows[] = [
                __('Unvalidated transfer', 'auchanassettracker'),
                '#' . $r['id'],
                Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id_dest']),
                (string) PluginAuchanassettrackerDashboard::daysSince($r['date_initiated'] ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function inventoryByContainer(array $filters): array
    {
        $cid = (int) ($filters['containers_id'] ?? 0);
        $headers = [
            __('Container', 'auchanassettracker'),
            __('Type'),
            __('Name'),
            __('Serial number'),
            __('Model'),
        ];
        $rows = [];
        if ($cid <= 0) {
            return ['headers' => $headers, 'rows' => $rows];
        }

        $cname = Dropdown::getDropdownName(PluginAuchanassettrackerContainer::getTable(), $cid);
        foreach (PluginAuchanassettrackerEquipment::listInContainer($cid) as $r) {
            $rows[] = [
                $cname,
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerEquipmenttype::getTable(),
                    (int) $r['plugin_auchanassettracker_equipmenttypes_id']
                ),
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                (string) ($r['model'] ?? ''),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }
}
