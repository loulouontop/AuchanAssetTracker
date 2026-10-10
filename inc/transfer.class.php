<?php

/**
 * Inter-location transfer workflow.
 */
class PluginAuchanassettrackerTransfer extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELLED  = 'cancelled';

    public static function getTypeName($nb = 0): string
    {
        return _n('Transfer', 'Transfers', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_transfers';
    }

    public static function getIcon(): string
    {
        return 'ti ti-truck';
    }

    /**
     * @param list<int> $equipment_ids
     */
    public static function initiate(int $locations_id_dest, array $equipment_ids, string $notes = ''): int|false
    {
        if (!PluginAuchanassettrackerRighthelper::canTransfer()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $equipment_ids = array_values(array_unique(array_filter(array_map('intval', $equipment_ids))));
        if ($equipment_ids === [] || $locations_id_dest <= 0) {
            Session::addMessageAfterRedirect(
                __('Select destination and at least one equipment item.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $source = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($source === null) {
            // Central admin must pick source via first equipment location.
            $eq0 = new PluginAuchanassettrackerEquipment();
            if (!$eq0->getFromDB($equipment_ids[0])) {
                return false;
            }
            $source = (int) $eq0->fields['locations_id'];
        }

        if ($source === $locations_id_dest) {
            Session::addMessageAfterRedirect(
                __('Destination must be different from source location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        foreach ($equipment_ids as $eid) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eid)) {
                return false;
            }
            if ((int) $eq->fields['locations_id'] !== (int) $source) {
                Session::addMessageAfterRedirect(
                    __('All equipment must belong to the source location.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            if (($eq->fields['status'] ?? '') !== PluginAuchanassettrackerEquipment::STATUS_AVAILABLE) {
                Session::addMessageAfterRedirect(
                    __('Only available equipment can be transferred.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            if (!PluginAuchanassettrackerRighthelper::canAccessLocation((int) $eq->fields['locations_id'])) {
                Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
                return false;
            }
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $tr = new self();
        $tid = $tr->add([
            'locations_id_source' => (int) $source,
            'locations_id_dest'   => $locations_id_dest,
            'users_id_initiator'  => (int) Session::getLoginUserID(),
            'transfer_status'     => self::STATUS_IN_TRANSIT,
            'date_initiated'      => $now,
            'notes'               => $notes,
            'date_creation'       => $now,
            'date_mod'            => $now,
        ]);

        if (!$tid) {
            return false;
        }

        foreach ($equipment_ids as $eid) {
            $item = new PluginAuchanassettrackerTransferitem();
            $item->add([
                'plugin_auchanassettracker_transfers_id'  => (int) $tid,
                'plugin_auchanassettracker_equipments_id' => $eid,
                'date_creation' => $now,
            ]);

            $eq = new PluginAuchanassettrackerEquipment();
            $eq->update([
                'id'     => $eid,
                'status' => PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT,
                'plugin_auchanassettracker_containers_id' => 0,
            ]);
        }

        PluginAuchanassettrackerAuditlog::record(
            'transfer_initiate',
            self::class,
            (int) $tid,
            sprintf('from=%d to=%d items=%s', $source, $locations_id_dest, implode(',', $equipment_ids))
        );

        return (int) $tid;
    }

    /**
     * Validate arrival: assign container per item, set Available at dest.
     *
     * @param array<int, int> $container_map equipment_id => container_id
     */
    public static function validate(int $transfer_id, array $container_map): bool
    {
        $tr = new self();
        if (!$tr->getFromDB($transfer_id)) {
            return false;
        }

        if (($tr->fields['transfer_status'] ?? '') !== self::STATUS_IN_TRANSIT) {
            return false;
        }

        $dest = (int) $tr->fields['locations_id_dest'];
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($dest)
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(
                __('Only the destination location can validate this transfer.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (PluginAuchanassettrackerContainer::countAtLocation($dest) <= 0) {
            Session::addMessageAfterRedirect(
                __('Create a physical container at this location before validating the transfer.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $items = self::getItems($transfer_id);
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            $eid = (int) $item['plugin_auchanassettracker_equipments_id'];
            $cid = (int) ($container_map[$eid] ?? 0);
            if ($cid <= 0) {
                Session::addMessageAfterRedirect(
                    __('Each received equipment must be placed in a container.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            if (!PluginAuchanassettrackerEquipment::containerBelongsToLocation($cid, $dest)) {
                Session::addMessageAfterRedirect(
                    __('Selected container does not belong to this location.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        foreach ($items as $item) {
            $eid = (int) $item['plugin_auchanassettracker_equipments_id'];
            $cid = (int) $container_map[$eid];

            $ti = new PluginAuchanassettrackerTransferitem();
            $ti->update([
                'id' => (int) $item['id'],
                'plugin_auchanassettracker_containers_id' => $cid,
            ]);

            $eq = new PluginAuchanassettrackerEquipment();
            $eq->update([
                'id'           => $eid,
                'status'       => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                'locations_id' => $dest,
                'plugin_auchanassettracker_containers_id' => $cid,
                'users_id'     => 0,
            ]);
        }

        $tr->update([
            'id'                => $transfer_id,
            'transfer_status'   => self::STATUS_COMPLETED,
            'users_id_validator'=> (int) Session::getLoginUserID(),
            'date_validated'    => $now,
            'date_mod'          => $now,
        ]);

        PluginAuchanassettrackerAuditlog::record(
            'transfer_validate',
            self::class,
            $transfer_id,
            'dest=' . $dest
        );

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItems(int $transfer_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerTransferitem::getTable(),
            'WHERE' => ['plugin_auchanassettracker_transfers_id' => $transfer_id],
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getPendingForLocation(int $locations_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'locations_id_dest' => $locations_id,
                'transfer_status'   => self::STATUS_IN_TRANSIT,
            ],
            'ORDER' => 'date_initiated ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOverdue(?int $locations_id = null): array
    {
        global $DB;
        $days = PluginAuchanassettrackerConfig::getTransferValidateDays();
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $where = [
            'transfer_status' => self::STATUS_IN_TRANSIT,
            ['date_initiated' => ['<', $cutoff]],
        ];
        if ($locations_id !== null) {
            $where['locations_id_dest'] = $locations_id;
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_initiated ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getAll(?string $status = null): array
    {
        global $DB;
        $where = [];
        if ($status !== null) {
            $where['transfer_status'] = $status;
        }
        $rows = [];
        $req = [
            'FROM'  => self::getTable(),
            'ORDER' => 'date_initiated DESC',
            'LIMIT' => 500,
        ];
        if ($where !== []) {
            $req['WHERE'] = $where;
        }
        foreach ($DB->request($req) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }
}
