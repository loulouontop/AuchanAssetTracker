<?php

/**
 * Allocation history + confirmation workflow.
 */
class PluginAuchanassettrackerAllocation extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_RETURNED  = 'returned';

    public static function getTypeName($nb = 0): string
    {
        return _n('Allocation', 'Allocations', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_allocations';
    }

    /**
     * Start allocation: equipment Available → Awaiting validation.
     */
    public static function initiate(int $equipment_id, int $users_id_recipient): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $eq = new PluginAuchanassettrackerEquipment();
        if (!$eq->getFromDB($equipment_id)) {
            return false;
        }

        $loc = (int) ($eq->fields['locations_id'] ?? 0);
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)) {
            Session::addMessageAfterRedirect(
                __('You cannot allocate equipment from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (($eq->fields['status'] ?? '') !== PluginAuchanassettrackerEquipment::STATUS_AVAILABLE) {
            Session::addMessageAfterRedirect(
                __('Only available equipment can be allocated.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (self::hasPendingForEquipment($equipment_id)) {
            Session::addMessageAfterRedirect(
                __('Cancel the current pending allocation first.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $alloc = new self();
        $aid = $alloc->add([
            'plugin_auchanassettracker_equipments_id' => $equipment_id,
            'users_id_recipient'  => $users_id_recipient,
            'users_id_allocator'  => (int) Session::getLoginUserID(),
            'allocation_date'     => $now,
            'allocation_status'   => self::STATUS_PENDING,
            'date_creation'       => $now,
            'date_mod'            => $now,
        ]);

        if (!$aid) {
            return false;
        }

        $ok = $eq->update([
            'id'     => $equipment_id,
            'status' => PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
            'users_id' => $users_id_recipient,
            'plugin_auchanassettracker_containers_id' => 0,
        ]);

        if ($ok) {
            PluginAuchanassettrackerAuditlog::record(
                'allocation_initiate',
                self::class,
                (int) $aid,
                sprintf('equipment=%d user=%d', $equipment_id, $users_id_recipient)
            );
            PluginAuchanassettrackerMailhelper::notifyAllocationPending($users_id_recipient, $equipment_id);
        }

        return (bool) $ok;
    }

    public static function confirm(int $allocation_id): bool
    {
        $alloc = new self();
        if (!$alloc->getFromDB($allocation_id)) {
            return false;
        }

        $uid = (int) Session::getLoginUserID();
        if ((int) ($alloc->fields['users_id_recipient'] ?? 0) !== $uid
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        if (($alloc->fields['allocation_status'] ?? '') !== self::STATUS_PENDING) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $eq_id = (int) $alloc->fields['plugin_auchanassettracker_equipments_id'];

        $alloc->update([
            'id'                  => $allocation_id,
            'allocation_status'   => self::STATUS_CONFIRMED,
            'confirmation_date'   => $now,
            'date_mod'            => $now,
        ]);

        $eq = new PluginAuchanassettrackerEquipment();
        $ok = $eq->update([
            'id'     => $eq_id,
            'status' => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
            'users_id' => (int) $alloc->fields['users_id_recipient'],
            'plugin_auchanassettracker_containers_id' => 0,
        ]);

        PluginAuchanassettrackerAuditlog::record(
            'allocation_confirm',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        return (bool) $ok;
    }

    /**
     * Did not receive → back to Available (manager must pick container).
     */
    public static function reject(int $allocation_id, int $container_id = 0): bool
    {
        $alloc = new self();
        if (!$alloc->getFromDB($allocation_id)) {
            return false;
        }

        $uid = (int) Session::getLoginUserID();
        $is_recipient = (int) ($alloc->fields['users_id_recipient'] ?? 0) === $uid;
        if (!$is_recipient && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        if (($alloc->fields['allocation_status'] ?? '') !== self::STATUS_PENDING) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $eq_id = (int) $alloc->fields['plugin_auchanassettracker_equipments_id'];

        $eq = new PluginAuchanassettrackerEquipment();
        if (!$eq->getFromDB($eq_id)) {
            return false;
        }

        $loc = (int) ($eq->fields['locations_id'] ?? 0);

        // User reject: status awaiting → available but container may be 0 until manager assigns.
        // If container provided (manager resolving), validate it.
        $update = [
            'id'       => $eq_id,
            'status'   => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            'users_id' => 0,
        ];

        if ($container_id > 0) {
            if (!PluginAuchanassettrackerEquipment::containerBelongsToLocation($container_id, $loc)) {
                Session::addMessageAfterRedirect(
                    __('Selected container does not belong to this location.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            $update['plugin_auchanassettracker_containers_id'] = $container_id;
        } else {
            // Keep available but without container — manager must assign a shelf again.
            $update['plugin_auchanassettracker_containers_id'] = 0;
        }

        $alloc->update([
            'id'                => $allocation_id,
            'allocation_status' => self::STATUS_REJECTED,
            'confirmation_date' => $now,
            'date_mod'          => $now,
        ]);

        $ok = $eq->update($update);

        PluginAuchanassettrackerAuditlog::record(
            'allocation_reject',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        $allocator = (int) ($alloc->fields['users_id_allocator'] ?? 0);
        if ($allocator > 0) {
            PluginAuchanassettrackerMailhelper::notifyAllocationRejected($allocator, $eq_id);
        }

        return (bool) $ok;
    }

    public static function hasPendingForEquipment(int $equipment_id): bool
    {
        global $DB;
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_auchanassettracker_equipments_id' => $equipment_id,
                'allocation_status' => self::STATUS_PENDING,
            ],
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getPendingForUser(int $users_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'users_id_recipient' => $users_id,
                'allocation_status'  => self::STATUS_PENDING,
            ],
            'ORDER' => 'allocation_date ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOverduePending(?int $locations_id = null): array
    {
        global $DB;

        $days = PluginAuchanassettrackerConfig::getAllocationConfirmDays();
        // Approximate working days as calendar days * 1.4 for threshold checks.
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) ceil($days * 1.4) . ' days'));

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'allocation_status' => self::STATUS_PENDING,
                ['allocation_date' => ['<', $cutoff]],
            ],
            'ORDER' => 'allocation_date ASC',
        ]) as $row) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB((int) $row['plugin_auchanassettracker_equipments_id'])) {
                continue;
            }
            if ($locations_id !== null && (int) $eq->fields['locations_id'] !== $locations_id) {
                continue;
            }
            $row['equipment_name'] = $eq->fields['name'] ?? '';
            $row['locations_id'] = (int) ($eq->fields['locations_id'] ?? 0);
            $row['serial'] = $eq->fields['serial'] ?? '';
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Equipment currently allocated (confirmed) to a user.
     *
     * @return list<array<string, mixed>>
     */
    public static function getUserEquipment(int $users_id): array
    {
        return array_merge(
            PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                null,
                $users_id
            ),
            PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                null,
                $users_id
            ),
            PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
                null,
                $users_id
            )
        );
    }

    /**
     * Informative list of gear already with user (any non-stock status).
     */
    public static function getCurrentGearForUser(int $users_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => [
                'users_id'   => $users_id,
                'is_deleted' => 0,
                'status'     => [
                    PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
                    PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                    PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                ],
            ],
            'ORDER' => 'date_mod DESC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }
}
