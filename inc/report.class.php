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
     * @return array{headers: list<string>, rows: list<list<mixed>>}
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

    /**
     * Plain text for one cell (string or ['text'=>…,'html'=>…]).
     */
    public static function cellText(mixed $cell): string
    {
        if (is_array($cell)) {
            if (isset($cell['text'])) {
                return (string) $cell['text'];
            }
            if (isset($cell['html'])) {
                return trim(html_entity_decode(strip_tags((string) $cell['html']), ENT_QUOTES, 'UTF-8'));
            }
            return '';
        }
        return (string) $cell;
    }

    /**
     * HTML for one cell (safe: plain strings are escaped).
     */
    public static function cellHtml(mixed $cell): string
    {
        if (is_array($cell) && isset($cell['html'])) {
            return (string) $cell['html'];
        }
        return Html::entities_deep(self::cellText($cell));
    }

    public static function toCsv(array $report): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $report['headers'] ?? []);
        foreach ($report['rows'] ?? [] as $row) {
            $line = [];
            foreach ($row as $cell) {
                $line[] = self::cellText($cell);
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $csv;
    }

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
                $xml .= '<Cell><Data ss:Type="String">' . $esc(self::cellText($cell)) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }

    /**
     * @return array{text: string, html: string}
     */
    private static function plainCell(string $text): array
    {
        return [
            'text' => $text,
            'html' => Html::entities_deep($text),
        ];
    }

    /**
     * @return array{text: string, html: string}
     */
    private static function linkCell(string $url, string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            $label = '—';
        }
        if ($url === '' || $url === '#') {
            return self::plainCell($label);
        }
        return [
            'text' => $label,
            'html' => '<a href="' . Html::entities_deep($url) . '">'
                . Html::entities_deep($label) . '</a>',
        ];
    }

    /**
     * @return array{text: string, html: string}
     */
    private static function htmlCell(string $html, string $text): array
    {
        return ['text' => $text, 'html' => $html];
    }

    private static function equipmentCell(int $eid, string $name = ''): array
    {
        if ($eid <= 0) {
            return self::plainCell($name !== '' ? $name : '—');
        }
        if ($name === '') {
            $eq = new PluginAuchanassettrackerEquipment();
            if ($eq->getFromDB($eid)) {
                $name = trim((string) ($eq->fields['name'] ?? ''));
            }
        }
        if ($name === '') {
            $name = '#' . $eid;
        }
        return self::linkCell(PluginAuchanassettrackerEquipment::getFormURLWithID($eid), $name);
    }

    private static function userCell(int $uid): array
    {
        if ($uid <= 0) {
            return self::plainCell('');
        }
        $label = PluginAuchanassettrackerAuditlog::userLogin($uid);
        if ($label === '') {
            $label = trim(strip_tags((string) getUserName($uid)));
        }
        if ($label === '') {
            $label = '#' . $uid;
        }
        return self::linkCell(User::getFormURLWithID($uid), $label);
    }

    private static function locationCell(int $lid): array
    {
        if ($lid <= 0) {
            return self::plainCell('');
        }
        $name = trim(strip_tags((string) Dropdown::getDropdownName('glpi_locations', $lid)));
        if ($name === '' || $name === '&nbsp;' || $name === '-') {
            $name = '#' . $lid;
        }
        return self::linkCell(Location::getFormURLWithID($lid), $name);
    }

    private static function containerCell(int $cid): array
    {
        if ($cid <= 0) {
            return self::plainCell('');
        }
        $name = trim(strip_tags((string) Dropdown::getDropdownName(
            PluginAuchanassettrackerContainer::getTable(),
            $cid
        )));
        if ($name === '' || $name === '&nbsp;' || $name === '-') {
            $name = '#' . $cid;
        }
        return self::linkCell(PluginAuchanassettrackerContainer::getFormURLWithID($cid), $name);
    }

    private static function transferCell(int $tid): array
    {
        if ($tid <= 0) {
            return self::plainCell('');
        }
        $label = '#' . $tid;
        $base = plugin_auchanassettracker_web_dir();
        return self::linkCell($base . '/front/transfer.form.php?id=' . $tid, $label);
    }

    /**
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

    private static function filterLocationId(array $filters): ?int
    {
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            return (int) $scope;
        }
        $lid = (int) ($filters['locations_id'] ?? 0);
        return $lid > 0 ? $lid : null;
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
                self::plainCell((string) $r['id']),
                self::equipmentCell((int) $r['id'], (string) ($r['name'] ?? '')),
                self::plainCell((string) ($r['serial'] ?? '')),
                self::plainCell(PluginAuchanassettrackerEquipment::getItemtypeLabel(
                    (string) ($r['itemtype'] ?? '')
                )),
                self::plainCell(PluginAuchanassettrackerEquipment::getStatusLabel((string) $r['status'])),
                self::locationCell((int) $r['locations_id']),
                self::containerCell((int) $r['plugin_auchanassettracker_containers_id']),
                self::userCell((int) ($r['users_id'] ?? 0)),
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
                self::userCell((int) $r['users_id']),
                self::equipmentCell((int) $r['id'], (string) ($r['name'] ?? '')),
                self::plainCell((string) ($r['serial'] ?? '')),
                self::plainCell(PluginAuchanassettrackerEquipment::getItemtypeLabel(
                    (string) ($r['itemtype'] ?? '')
                )),
                self::plainCell(PluginAuchanassettrackerEquipment::getStatusLabel((string) $r['status'])),
                self::locationCell((int) $r['locations_id']),
                self::containerCell((int) $r['plugin_auchanassettracker_containers_id']),
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

        // Same columns as Recent activity (no Entity / itemtype column).
        $headers = [__('Date'), __('User'), __('Action'), __('Details')];
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerAuditlog::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_creation DESC',
            'LIMIT' => 5000,
        ]) as $r) {
            $action = (string) ($r['action'] ?? '');
            $details_html = PluginAuchanassettrackerAuditlog::formatDetailsHtml(
                (string) ($r['details'] ?? ''),
                $r
            );
            $details_text = trim(html_entity_decode(strip_tags($details_html), ENT_QUOTES, 'UTF-8'));
            $rows[] = [
                self::plainCell((string) $r['date_creation']),
                self::userCell((int) $r['users_id']),
                self::plainCell(PluginAuchanassettrackerAuditlog::formatActionLabel($action)),
                self::htmlCell($details_html, $details_text),
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
                self::plainCell($label),
                self::locationCell((int) $r['loc']),
                self::plainCell((string) $r['cpt']),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Threshold-based issues: in-service overtime, unconfirmed allocations, unvalidated transfers.
     */
    private static function issues(array $filters): array
    {
        $loc = self::filterLocationId($filters);
        $uid = (int) ($filters['users_id'] ?? 0);
        $cid = (int) ($filters['containers_id'] ?? 0);

        $headers = [
            __('Issue type', 'auchanassettracker'),
            __('Equipment'),
            __('Location'),
            __('Days', 'auchanassettracker'),
        ];
        $rows = [];

        foreach (PluginAuchanassettrackerTickethook::getOverdueInService($loc) as $r) {
            $eid = (int) ($r['id'] ?? 0);
            if ($uid > 0 && (int) ($r['users_id'] ?? 0) !== $uid) {
                continue;
            }
            if ($cid > 0 && (int) ($r['plugin_auchanassettracker_containers_id'] ?? 0) !== $cid) {
                continue;
            }
            $rows[] = [
                self::plainCell(__('In service overtime', 'auchanassettracker')),
                self::equipmentCell($eid, (string) ($r['name'] ?? '')),
                self::locationCell((int) ($r['locations_id'] ?? 0)),
                self::plainCell((string) PluginAuchanassettrackerDashboard::daysSince(
                    $r['service_since'] ?? null
                )),
            ];
        }

        foreach (PluginAuchanassettrackerAllocation::getOverduePending($loc) as $r) {
            $eid = (int) ($r['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($uid > 0 && (int) ($r['users_id_recipient'] ?? 0) !== $uid) {
                continue;
            }
            if ($cid > 0) {
                $eq = new PluginAuchanassettrackerEquipment();
                if (!$eq->getFromDB($eid)
                    || (int) ($eq->fields['plugin_auchanassettracker_containers_id'] ?? 0) !== $cid
                ) {
                    continue;
                }
            }
            $rows[] = [
                self::plainCell(__('Unconfirmed allocation', 'auchanassettracker')),
                self::equipmentCell($eid, (string) ($r['equipment_name'] ?? '')),
                self::locationCell((int) ($r['locations_id'] ?? 0)),
                self::plainCell((string) PluginAuchanassettrackerDashboard::daysSince(
                    $r['allocation_date'] ?? null
                )),
            ];
        }

        foreach (PluginAuchanassettrackerTransfer::getOverdue($loc) as $r) {
            // Transfers have no single equipment/user/container — skip when those filters are set.
            if ($uid > 0 || $cid > 0) {
                continue;
            }
            $tid = (int) ($r['id'] ?? 0);
            $rows[] = [
                self::plainCell(__('Unvalidated transfer', 'auchanassettracker')),
                self::transferCell($tid),
                self::locationCell((int) ($r['locations_id_dest'] ?? 0)),
                self::plainCell((string) PluginAuchanassettrackerDashboard::daysSince(
                    $r['date_initiated'] ?? null
                )),
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

        $where = self::equipmentWhere($filters, [
            'plugin_auchanassettracker_containers_id' => $cid,
        ]);
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
            'ORDER' => 'itemtype ASC, name ASC, serial ASC, id ASC',
        ]) as $r) {
            $rows[] = [
                self::containerCell($cid),
                self::plainCell(PluginAuchanassettrackerEquipment::getItemtypeLabel(
                    (string) ($r['itemtype'] ?? '')
                )),
                self::equipmentCell((int) $r['id'], (string) ($r['name'] ?? '')),
                self::plainCell((string) ($r['serial'] ?? '')),
                self::plainCell((string) ($r['model'] ?? '')),
                self::plainCell(PluginAuchanassettrackerEquipment::getStatusLabel(
                    (string) ($r['status'] ?? '')
                )),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }
}
