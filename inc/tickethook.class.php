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
        global $DB;

        // Match by serial in ticket content/name, or by linked Computer serial matching our equipment.
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return;
        }

        $equipment_ids = self::findEquipmentIdsForTicket($ticket);
        foreach ($equipment_ids as $eid) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eid)) {
                continue;
            }
            $st = (string) ($eq->fields['status'] ?? '');
            if (!in_array($st, [
                PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
            ], true)) {
                continue;
            }

            $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            $eq->update([
                'id'                  => $eid,
                'status'              => PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                'service_tickets_id'  => $tickets_id,
                'service_since'       => $now,
            ]);

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
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->update([
                'id'     => (int) $row['id'],
                'status' => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                // Keep users_id; skip stock.
                'service_tickets_id' => $tickets_id,
            ]);

            PluginAuchanassettrackerAuditlog::record(
                'equipment_return_service',
                PluginAuchanassettrackerEquipment::class,
                (int) $row['id'],
                'ticket=' . $tickets_id
            );
        }
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

        // 3) Linked GLPI assets → match by serial.
        foreach ($DB->request([
            'FROM'  => 'glpi_items_tickets',
            'WHERE' => ['tickets_id' => (int) $ticket->getID()],
        ]) as $link) {
            $itemtype = (string) ($link['itemtype'] ?? '');
            $items_id = (int) ($link['items_id'] ?? 0);
            if ($itemtype === '' || $items_id <= 0 || !class_exists($itemtype)) {
                continue;
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
