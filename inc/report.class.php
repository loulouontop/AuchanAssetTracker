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
     * Extra filters required once a report type is chosen (report type itself is always required).
     * Other filters stay optional and still narrow results when set.
     *
     * @return list<string>
     */
    public static function getRequiredFilters(string $type): array
    {
        return match ($type) {
            'inventory_container' => ['locations_id', 'containers_id'],
            'equipment_user'      => ['users_id'],
            'movements'           => ['date_from', 'date_to'],
            default               => [],
        };
    }

    /**
     * @return list<string> Human-readable validation errors (empty = ok).
     */
    public static function validate(string $type, array $filters): array
    {
        $errors = [];
        $types = self::getTypes();
        if ($type === '' || !isset($types[$type])) {
            $errors[] = __('Please select a report type.', 'auchanassettracker');
            return $errors;
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        foreach (self::getRequiredFilters($type) as $field) {
            if ($field === 'locations_id' && $scope !== null) {
                // Scoped roles already have a fixed location.
                continue;
            }
            $val = $filters[$field] ?? null;
            if ($field === 'date_from' || $field === 'date_to') {
                if (!is_string($val) || trim($val) === '') {
                    $errors[] = __('Period is required for this report.', 'auchanassettracker');
                    break;
                }
                continue;
            }
            if ((int) $val <= 0) {
                $errors[] = match ($field) {
                    'users_id'      => __('User is required for this report.', 'auchanassettracker'),
                    'containers_id' => __('Container is required for this report.', 'auchanassettracker'),
                    'locations_id'  => __('Location is required for this report.', 'auchanassettracker'),
                    default         => __('Please fill the required filters.', 'auchanassettracker'),
                };
            }
        }

        return array_values(array_unique($errors));
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

    /**
     * Common optional filters for equipment-based reports.
     *
     * @return array<string, mixed>
     */
    private static function equipmentWhere(array $filters, array $extra = []): array
    {
        $where = array_merge(['is_deleted' => 0], $extra);

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $where['locations_id'] = $scope;
        } elseif (!empty($filters['locations_id'])) {
            $where['locations_id'] = (int) $filters['locations_id'];
        }

        if (!empty($filters['users_id'])) {
            $where['users_id'] = (int) $filters['users_id'];
        }
        if (!empty($filters['containers_id'])) {
            $where['plugin_auchanassettracker_containers_id'] = (int) $filters['containers_id'];
        }

        return $where;
    }

    private static function inventoryByLocation(array $filters): array
    {
        global $DB;

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
            'WHERE' => self::equipmentWhere($filters),
            'ORDER' => 'locations_id ASC, name ASC',
        ]) as $r) {
            $rows[] = [
                (string) $r['id'],
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                PluginAuchanassettrackerEquipment::getItemtypeLabel((string) ($r['itemtype'] ?? '')),
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
        $extra = $uid > 0
            ? ['users_id' => $uid]
            : [['users_id' => ['>', 0]]];

        $headers = [
            __('User'),
            __('Equipment'),
            __('Serial number'),
            __('Type'),
            __('Status'),
            __('Location'),
            __('Container', 'auchanassettracker'),
        ];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => self::equipmentWhere($filters, $extra),
            'ORDER' => 'users_id ASC, name ASC',
        ]) as $r) {
            $rows[] = [
                getUserName((int) $r['users_id']),
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                PluginAuchanassettrackerEquipment::getItemtypeLabel((string) ($r['itemtype'] ?? '')),
                PluginAuchanassettrackerEquipment::getStatusLabel((string) $r['status']),
                Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id']),
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerContainer::getTable(),
                    (int) $r['plugin_auchanassettracker_containers_id']
                ),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function movements(array $filters): array
    {
        global $DB;

        $from = (string) ($filters['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $to   = (string) ($filters['date_to'] ?? date('Y-m-d'));

        $where = [
            ['date_creation' => ['>=', $from . ' 00:00:00']],
            ['date_creation' => ['<=', $to . ' 23:59:59']],
        ];
        if (!empty($filters['users_id'])) {
            $where['users_id'] = (int) $filters['users_id'];
        }

        $headers = [__('Date'), __('Action'), __('User'), __('Entity'), __('Details')];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerAuditlog::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_creation DESC',
            'LIMIT' => 5000,
        ]) as $r) {
            $rows[] = [
                (string) $r['date_creation'],
                PluginAuchanassettrackerAuditlog::formatActionLabel((string) ($r['action'] ?? '')),
                getUserName((int) $r['users_id']),
                (string) $r['itemtype'] . ' #' . $r['items_id'],
                strip_tags((string) ($r['details'] ?? '')),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Stock counts by GLPI asset type (Computer, Monitor, custom assets, …).
     */
    private static function stockByType(array $filters): array
    {
        global $DB;

        $headers = [
            __('Type'),
            __('Location'),
            __('Quantity'),
        ];
        $rows = [];
        $agg = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => self::equipmentWhere($filters),
        ]) as $r) {
            $itemtype = (string) ($r['itemtype'] ?? '');
            if ($itemtype === '') {
                $itemtype = '—';
            }
            $loc = (int) ($r['locations_id'] ?? 0);
            $key = $itemtype . ':' . $loc;
            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'itemtype' => $itemtype,
                    'loc'      => $loc,
                    'cpt'      => 0,
                ];
            }
            $agg[$key]['cpt']++;
        }

        uasort($agg, static function (array $a, array $b): int {
            $la = PluginAuchanassettrackerEquipment::getItemtypeLabel((string) $a['itemtype']);
            $lb = PluginAuchanassettrackerEquipment::getItemtypeLabel((string) $b['itemtype']);
            $cmp = strcasecmp($la, $lb);
            return $cmp !== 0 ? $cmp : ($a['loc'] <=> $b['loc']);
        });

        foreach ($agg as $r) {
            $label = ((string) $r['itemtype'] === '—')
                ? '—'
                : PluginAuchanassettrackerEquipment::getItemtypeLabel((string) $r['itemtype']);
            $rows[] = [
                $label,
                Dropdown::getDropdownName('glpi_locations', (int) $r['loc']),
                (string) $r['cpt'],
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * All equipment currently In service (optional location / user / container filters).
     */
    private static function issues(array $filters): array
    {
        global $DB;

        $headers = [
            __('Name'),
            __('Serial number'),
            __('Type'),
            __('Location'),
            __('Container', 'auchanassettracker'),
            __('User'),
            __('Status'),
        ];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => self::equipmentWhere($filters, [
                'status' => PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
            ]),
            'ORDER' => 'locations_id ASC, name ASC',
        ]) as $r) {
            $rows[] = [
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                PluginAuchanassettrackerEquipment::getItemtypeLabel((string) ($r['itemtype'] ?? '')),
                Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id']),
                Dropdown::getDropdownName(
                    PluginAuchanassettrackerContainer::getTable(),
                    (int) $r['plugin_auchanassettracker_containers_id']
                ),
                !empty($r['users_id']) ? getUserName((int) $r['users_id']) : '',
                PluginAuchanassettrackerEquipment::getStatusLabel(
                    PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE
                ),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function inventoryByContainer(array $filters): array
    {
        global $DB;

        $cid = (int) ($filters['containers_id'] ?? 0);
        $headers = [
            __('Container', 'auchanassettracker'),
            __('Type'),
            __('Name'),
            __('Serial number'),
            __('Model'),
            __('Status'),
        ];
        $rows = [];
        if ($cid <= 0) {
            return ['headers' => $headers, 'rows' => $rows];
        }

        // Optional location must match the container when both are set.
        $loc = (int) ($filters['locations_id'] ?? 0);
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $loc = (int) $scope;
        }
        if ($loc > 0) {
            $c = new PluginAuchanassettrackerContainer();
            if (!$c->getFromDB($cid) || (int) ($c->fields['locations_id'] ?? 0) !== $loc) {
                return ['headers' => $headers, 'rows' => $rows];
            }
        }

        $cname = Dropdown::getDropdownName(PluginAuchanassettrackerContainer::getTable(), $cid);
        $where = self::equipmentWhere($filters, [
            'plugin_auchanassettracker_containers_id' => $cid,
        ]);
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
            'ORDER' => 'itemtype ASC, name ASC, serial ASC, id ASC',
        ]) as $r) {
            $rows[] = [
                $cname,
                PluginAuchanassettrackerEquipment::getItemtypeLabel((string) ($r['itemtype'] ?? '')),
                (string) $r['name'],
                (string) ($r['serial'] ?? ''),
                (string) ($r['model'] ?? ''),
                PluginAuchanassettrackerEquipment::getStatusLabel((string) ($r['status'] ?? '')),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }
}
