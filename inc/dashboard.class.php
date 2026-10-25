<?php

/**
 * Role-aware dashboard data builders (Sprint 5).
 */
class PluginAuchanassettrackerDashboard
{
    public static function getAdminData(): array
    {
        $counts = PluginAuchanassettrackerEquipment::countByStatus(null);
        $written_off_30 = self::countWrittenOffLastDays(30);
        $total = array_sum($counts);

        return [
            'indicators' => [
                'total'          => $total,
                'available'      => $counts[PluginAuchanassettrackerEquipment::STATUS_AVAILABLE] ?? 0,
                'allocated'      => $counts[PluginAuchanassettrackerEquipment::STATUS_ALLOCATED] ?? 0,
                'in_service'     => $counts[PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE] ?? 0,
                'in_transit'     => $counts[PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT] ?? 0,
                'written_off_30' => $written_off_30,
            ],
            'alerts' => [
                'service'     => PluginAuchanassettrackerTickethook::getOverdueInService(null),
                'allocations' => PluginAuchanassettrackerAllocation::getOverduePending(null),
                'transfers'   => PluginAuchanassettrackerTransfer::getOverdue(null),
            ],
            'stock_by_location' => self::stockByLocation(),
            'recent'            => PluginAuchanassettrackerAuditlog::getRecent(15),
        ];
    }

    public static function getManagerData(int $locations_id): array
    {
        $containers = $locations_id > 0
            ? PluginAuchanassettrackerContainer::listForLocation($locations_id)
            : [];
        foreach ($containers as &$c) {
            $c['available_count'] = count(
                PluginAuchanassettrackerEquipment::listInContainer((int) $c['id'])
            );
        }
        unset($c);

        $loc_or_null = $locations_id > 0 ? $locations_id : null;

        return [
            'containers' => $containers,
            'to_resolve' => [
                'allocations'     => PluginAuchanassettrackerAllocation::getOverduePending($loc_or_null),
                'transfers'       => $locations_id > 0
                    ? PluginAuchanassettrackerTransfer::getPendingForLocation($locations_id)
                    : [],
                'needs_container' => $locations_id > 0
                    ? self::availableWithoutContainer($locations_id)
                    : [],
            ],
            'in_movement' => self::buildInMovement($loc_or_null),
            'alerts' => [
                'service'     => PluginAuchanassettrackerTickethook::getOverdueInService($loc_or_null),
                'allocations' => PluginAuchanassettrackerAllocation::getOverduePending($loc_or_null),
            ],
        ];
    }

    public static function getUserData(int $users_id): array
    {
        $pending = PluginAuchanassettrackerAllocation::getPendingForUser($users_id);
        foreach ($pending as &$row) {
            $eq = new PluginAuchanassettrackerEquipment();
            $eid = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
            $row['equipment_name'] = '';
            if ($eid > 0 && $eq->getFromDB($eid)) {
                $row['equipment_name'] = (string) ($eq->fields['name'] ?? '');
            }
        }
        unset($row);

        return [
            'pending'      => $pending,
            'my_equipment' => PluginAuchanassettrackerAllocation::getCurrentGearForUser($users_id),
        ];
    }

    /**
     * Equipment in service or in transit at a location, with since-date and destination/service.
     *
     * @return array{in_service: list<array<string, mixed>>, in_transit_out: list<array<string, mixed>>}
     */
    public static function buildInMovement(?int $locations_id): array
    {
        $in_service = PluginAuchanassettrackerEquipment::findByStatus(
            PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
            $locations_id
        );
        foreach ($in_service as &$e) {
            $tid = (int) ($e['service_tickets_id'] ?? 0);
            $e['since'] = (string) ($e['service_since'] ?? '');
            $e['service_label'] = $tid > 0
                ? sprintf(__('Ticket #%d', 'auchanassettracker'), $tid)
                : __('Service', 'auchanassettracker');
        }
        unset($e);

        $in_transit = PluginAuchanassettrackerEquipment::findByStatus(
            PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT,
            $locations_id
        );
        foreach ($in_transit as &$e) {
            $info = self::findOpenTransferForEquipment((int) ($e['id'] ?? 0));
            $e['since'] = (string) ($info['date_initiated'] ?? '');
            $dest_id = (int) ($info['locations_id_dest'] ?? 0);
            $e['destination_label'] = $dest_id > 0
                ? Dropdown::getDropdownName('glpi_locations', $dest_id)
                : __('Destination', 'auchanassettracker');
            $e['transfer_id'] = (int) ($info['id'] ?? 0);
        }
        unset($e);

        return [
            'in_service'     => $in_service,
            'in_transit_out' => $in_transit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function findOpenTransferForEquipment(int $equipment_id): array
    {
        global $DB;

        if ($equipment_id <= 0) {
            return [];
        }

        $item_table = PluginAuchanassettrackerTransferitem::getTable();
        if (!$DB->tableExists($item_table)) {
            return [];
        }

        foreach ($DB->request([
            'FROM'  => $item_table,
            'WHERE' => ['plugin_auchanassettracker_equipments_id' => $equipment_id],
            'ORDER' => 'id DESC',
            'LIMIT' => 30,
        ]) as $item) {
            $tr = new PluginAuchanassettrackerTransfer();
            $tid = (int) ($item['plugin_auchanassettracker_transfers_id'] ?? 0);
            if ($tid > 0
                && $tr->getFromDB($tid)
                && ($tr->fields['transfer_status'] ?? '') === PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT
            ) {
                return [
                    'id'                => $tid,
                    'locations_id_dest' => (int) ($tr->fields['locations_id_dest'] ?? 0),
                    'date_initiated'    => (string) ($tr->fields['date_initiated'] ?? ''),
                ];
            }
        }

        return [];
    }

    public static function formatSinceDate(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }
        $t = strtotime($datetime);
        if ($t === false) {
            return '';
        }
        return date('Y-m-d', $t);
    }

    public static function countWrittenOffLastDays(int $days): int
    {
        global $DB;

        $table = PluginAuchanassettrackerEquipment::getTable();
        if (!$DB->tableExists($table)) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
        $where = [
            'status' => [
                PluginAuchanassettrackerEquipment::STATUS_WRITTEN_OFF,
                PluginAuchanassettrackerEquipment::STATUS_LOST,
                PluginAuchanassettrackerEquipment::STATUS_STOLEN,
            ],
            'is_deleted' => 0,
        ];
        if ($DB->fieldExists($table, 'final_date')) {
            $where[] = ['final_date' => ['>=', $cutoff]];
        }

        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => $table,
            'WHERE' => $where,
        ]) as $row) {
            return (int) ($row['cpt'] ?? 0);
        }
        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function stockByLocation(): array
    {
        global $DB;

        $by = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => ['is_deleted' => 0],
        ]) as $row) {
            $lid = (int) ($row['locations_id'] ?? 0);
            if (!isset($by[$lid])) {
                $by[$lid] = [
                    'locations_id' => $lid,
                    'available'    => 0,
                    'allocated'    => 0,
                    'in_service'   => 0,
                    'in_transit'   => 0,
                ];
            }
            $st = (string) ($row['status'] ?? '');
            if (isset($by[$lid][$st])) {
                $by[$lid][$st]++;
            }
        }
        return array_values($by);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function availableWithoutContainer(int $locations_id): array
    {
        global $DB;

        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => [
                'locations_id' => $locations_id,
                'status'       => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                'plugin_auchanassettracker_containers_id' => 0,
                'is_deleted'   => 0,
            ],
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function daysSince(?string $datetime): int
    {
        if (!$datetime) {
            return 0;
        }
        $t = strtotime($datetime);
        if ($t === false) {
            return 0;
        }
        return (int) floor((time() - $t) / 86400);
    }
}
