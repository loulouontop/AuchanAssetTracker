<?php

/**
 * When a GLPI ticket is opened for tracked equipment → In service.
 * On close/solved with repair → back to Allocated.
 */
class PluginAuchanassettrackerTickethook
{
    public static function postTicketAdd(Ticket $ticket): void
    {
        // Linking often happens via Item_Ticket after ticket create.
    }

    public static function postItemTicketAdd(CommonDBTM $item): void
    {
        if (!($item instanceof Item_Ticket)) {
            return;
        }

        $tickets_id = (int) ($item->fields['tickets_id'] ?? 0);
        if ($tickets_id <= 0) {
            return;
        }

        self::maybeMarkInServiceFromTicket($tickets_id);
    }

    public static function postTicketUpdate(Ticket $ticket): void
    {
        $status = (int) ($ticket->fields['status'] ?? 0);
        // Closed / solved
        if (in_array($status, [Ticket::SOLVED, Ticket::CLOSED], true)) {
            self::maybeReturnFromService($ticket);
        } else {
            self::maybeMarkInServiceFromTicket((int) $ticket->getID());
        }
    }

    public static function maybeMarkInServiceFromTicket(int $tickets_id): void
    {
        // Match by serial in ticket content/name, or by linked Computer serial matching our equipment.
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return;
        }

        $equipment_ids = self::findEquipmentIdsForTicket($ticket);
        $fallback_link = self::findPrimaryGlpiAssetForTicket($ticket);

        foreach ($equipment_ids as $eid) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eid)) {
                continue;
            }
            $st = (string) ($eq->fields['status'] ?? '');
            // Also allow Available: ticket may be opened from stock / another plugin path.
            if (!in_array($st, [
                PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
                PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            ], true)) {
                continue;
            }

            $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            $patch = [
                'status'             => PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                'service_tickets_id' => $tickets_id,
                'service_since'      => $now,
            ];

            // Ensure plugin row is linked to a GLPI asset so states_id can sync.
            if ((int) ($eq->fields['items_id'] ?? 0) <= 0) {
                $asset_link = self::findGlpiAssetLinkForEquipment($ticket, $eq->fields)
                    ?? $fallback_link;
                if ($asset_link !== null) {
                    $patch['itemtype'] = $asset_link['itemtype'];
                    $patch['items_id'] = $asset_link['items_id'];
                }
            }

            if (!PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, $patch)) {
                continue;
            }

            PluginAuchanassettrackerAuditlog::record(
                'equipment_in_service',
                PluginAuchanassettrackerEquipment::class,
                $eid,
                'ticket=' . $tickets_id
            );
        }
    }

    public static function maybeReturnFromService(Ticket $ticket): void
    {
        global $DB;

        $tickets_id = (int) $ticket->getID();
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => [
                'service_tickets_id' => $tickets_id,
                'status'             => PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                'is_deleted'         => 0,
            ],
        ]) as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }

            if (!PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, [
                'status'             => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                // Keep users_id; skip stock. Keep ticket id for history.
                'service_tickets_id' => $tickets_id,
            ])) {
                continue;
            }

            PluginAuchanassettrackerAuditlog::record(
                'equipment_return_service',
                PluginAuchanassettrackerEquipment::class,
                $eid,
                'ticket=' . $tickets_id
            );
        }
    }

    /**
     * First linked GLPI asset on the ticket (for plugin items_id linking).
     *
     * @return array{itemtype: string, items_id: int}|null
     */
    public static function findPrimaryGlpiAssetForTicket(Ticket $ticket): ?array
    {
        global $DB;

        foreach ($DB->request([
            'FROM'  => 'glpi_items_tickets',
            'WHERE' => ['tickets_id' => (int) $ticket->getID()],
            'ORDER' => 'id ASC',
            'LIMIT' => 20,
        ]) as $link) {
            $itemtype = (string) ($link['itemtype'] ?? '');
            $items_id = (int) ($link['items_id'] ?? 0);
            if ($itemtype === '' || $items_id <= 0) {
                continue;
            }
            if (!PluginAuchanassettrackerEquipment::isAllowedAssetType($itemtype)) {
                continue;
            }
            return ['itemtype' => $itemtype, 'items_id' => $items_id];
        }

        return null;
    }

    /**
     * Prefer the ticket-linked GLPI asset that matches this equipment (serial).
     *
     * @param array<string, mixed> $eq_fields
     * @return array{itemtype: string, items_id: int}|null
     */
    public static function findGlpiAssetLinkForEquipment(Ticket $ticket, array $eq_fields): ?array
    {
        global $DB;

        $eq_serial = trim((string) ($eq_fields['serial'] ?? ''));
        $eq_itemtype = (string) ($eq_fields['itemtype'] ?? '');

        foreach ($DB->request([
            'FROM'  => 'glpi_items_tickets',
            'WHERE' => ['tickets_id' => (int) $ticket->getID()],
            'ORDER' => 'id ASC',
            'LIMIT' => 20,
        ]) as $link) {
            $itemtype = (string) ($link['itemtype'] ?? '');
            $items_id = (int) ($link['items_id'] ?? 0);
            if ($itemtype === '' || $items_id <= 0) {
                continue;
            }
            if (!PluginAuchanassettrackerEquipment::isAllowedAssetType($itemtype)) {
                continue;
            }

            // Prefer same itemtype when equipment already knows it.
            if ($eq_itemtype !== '' && $eq_itemtype !== $itemtype) {
                continue;
            }

            if ($eq_serial === '' || !class_exists($itemtype)) {
                return ['itemtype' => $itemtype, 'items_id' => $items_id];
            }

            $asset = getItemForItemtype($itemtype);
            if (!$asset || !$asset->getFromDB($items_id)) {
                continue;
            }
            $asset_serial = trim((string) ($asset->fields['serial'] ?? ''));
            if ($asset_serial !== '' && strcasecmp($asset_serial, $eq_serial) === 0) {
                return ['itemtype' => $itemtype, 'items_id' => $items_id];
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public static function findEquipmentIdsForTicket(Ticket $ticket): array
    {
        global $DB;

        $ids = [];

        // 1) Plugin may store equipment id in ticket content marker.
        $content = (string) ($ticket->fields['content'] ?? '') . ' ' . (string) ($ticket->fields['name'] ?? '');
        if (preg_match_all('/AAT-EQ-(\d+)/i', $content, $m)) {
            foreach ($m[1] as $id) {
                $ids[] = (int) $id;
            }
        }

        // 2) Match serial numbers mentioned in ticket against our equipment.
        if (preg_match_all('/\b([A-Z0-9][A-Z0-9\-_]{4,})\b/i', $content, $sm)) {
            foreach (array_unique($sm[1]) as $serial) {
                foreach ($DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => PluginAuchanassettrackerEquipment::getTable(),
                    'WHERE'  => ['serial' => $serial, 'is_deleted' => 0],
                    'LIMIT'  => 1,
                ]) as $row) {
                    $ids[] = (int) $row['id'];
                }
            }
        }

        // 3) Linked GLPI assets → match by items_id, then by serial.
        foreach ($DB->request([
            'FROM'  => 'glpi_items_tickets',
            'WHERE' => ['tickets_id' => (int) $ticket->getID()],
        ]) as $link) {
            $itemtype = (string) ($link['itemtype'] ?? '');
            $items_id = (int) ($link['items_id'] ?? 0);
            if ($itemtype === '' || $items_id <= 0 || !class_exists($itemtype)) {
                continue;
            }
            if (!PluginAuchanassettrackerEquipment::isAllowedAssetType($itemtype)) {
                continue;
            }

            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => PluginAuchanassettrackerEquipment::getTable(),
                'WHERE'  => [
                    'itemtype'   => $itemtype,
                    'items_id'   => $items_id,
                    'is_deleted' => 0,
                ],
                'LIMIT'  => 1,
            ]) as $row) {
                $ids[] = (int) $row['id'];
            }

            $asset = getItemForItemtype($itemtype);
            if (!$asset || !$asset->getFromDB($items_id)) {
                continue;
            }
            $serial = trim((string) ($asset->fields['serial'] ?? ''));
            if ($serial === '') {
                continue;
            }
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => PluginAuchanassettrackerEquipment::getTable(),
                'WHERE'  => ['serial' => $serial, 'is_deleted' => 0],
                'LIMIT'  => 1,
            ]) as $row) {
                $ids[] = (int) $row['id'];
            }
        }

        // 4) Direct link: equipment owned by ticket requester currently allocated.
        $requester = 0;
        foreach ($DB->request([
            'FROM'  => 'glpi_tickets_users',
            'WHERE' => [
                'tickets_id' => (int) $ticket->getID(),
                'type'       => CommonITILActor::REQUESTER,
            ],
            'LIMIT' => 1,
        ]) as $tu) {
            $requester = (int) ($tu['users_id'] ?? 0);
        }
        if ($requester > 0 && $ids === []) {
            // Prefer explicit match; if ticket name contains model, skip auto-all.
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Equipment overdue in service.
     *
     * @return list<array<string, mixed>>
     */
    public static function getOverdueInService(?int $locations_id = null): array
    {
        global $DB;
        $days = PluginAuchanassettrackerConfig::getServiceMaxDays();
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $where = [
            'status'     => PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
            'is_deleted' => 0,
            ['service_since' => ['<', $cutoff]],
        ];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => $where,
            'ORDER' => 'service_since ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }
}
