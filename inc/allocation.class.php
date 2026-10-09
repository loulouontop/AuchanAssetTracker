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

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_ALLOCATION];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/allocation.form.php';
    }

    public static function getIcon(): string
    {
        return 'ti ti-user-plus';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public static function canView(): bool
    {
        return PluginAuchanassettrackerRighthelper::canAllocate()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return PluginAuchanassettrackerRighthelper::canAllocate();
    }

    public function canCreateItem(): bool
    {
        return self::canCreate();
    }

    public function canViewItem(): bool
    {
        return self::canView();
    }

    /**
     * Native GLPI form chrome (blue bookmark ribbon) for New allocation.
     */
    public function showForm($ID, array $options = [])
    {
        $this->initForm(-1, $options);
        $title = sprintf('%s - %s', __('New item'), self::getTypeName(1));
        PluginAuchanassettrackerMenu::beginNativeFormCard($title, self::getIcon());
        self::renderAllocationWorkspace();
        PluginAuchanassettrackerMenu::endNativeFormCard();
        return true;
    }

    /** Clickable equipment name → plugin equipment form. */
    public static function equipmentNameLink(array $row): string
    {
        $base = plugin_auchanassettracker_web_dir();
        $name = trim((string) ($row['name'] ?? $row['equipment_name'] ?? ''));
        if ($name === '') {
            $name = '#' . (int) ($row['id'] ?? $row['equipments_id'] ?? 0);
        }
        $src = (string) ($row['source'] ?? 'plugin');
        $href = '';
        if ($src === 'glpi') {
            $type = (string) ($row['itemtype'] ?? '');
            $iid = (int) ($row['items_id'] ?? 0);
            if ($type !== '' && $iid > 0 && class_exists($type) && method_exists($type, 'getFormURLWithID')) {
                $href = $type::getFormURLWithID($iid);
            }
        } else {
            $eid = (int) ($row['id'] ?? $row['equipments_id'] ?? $row['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($eid > 0) {
                $href = $base . '/front/equipment.form.php?id=' . $eid;
            }
        }
        $safe = Html::entities_deep($name);
        return $href !== '' ? "<a href='" . Html::entities_deep($href) . "'>$safe</a>" : $safe;
    }

    /** Clickable user → GLPI user form. */
    public static function userNameLink(int $users_id): string
    {
        if ($users_id <= 0) {
            return '—';
        }
        $label = getUserName($users_id);
        if ($label === '' || $label === null) {
            $label = '#' . $users_id;
        }
        // getUserName may already return HTML; keep plain text for our link.
        $plain = trim(strip_tags((string) $label));
        $href = User::getFormURLWithID($users_id);
        return "<a href='" . Html::entities_deep($href) . "'>" . Html::entities_deep($plain) . "</a>";
    }

    /** Clickable location → GLPI location form. */
    public static function locationNameLink(int $locations_id): string
    {
        if ($locations_id <= 0) {
            return '—';
        }
        $name = Dropdown::getDropdownName('glpi_locations', $locations_id);
        $plain = trim(strip_tags((string) $name));
        if ($plain === '' || $plain === '&nbsp;' || $plain === '-') {
            $plain = '#' . $locations_id;
        }
        $href = Location::getFormURLWithID($locations_id);
        return "<a href='" . Html::entities_deep($href) . "'>" . Html::entities_deep($plain) . "</a>";
    }

    /**
     * New allocation page body (alerts, gear line, stock table, history).
     */
    public static function renderAllocationWorkspace(): void
    {
        $base = plugin_auchanassettracker_web_dir();
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $preview_user = (int) ($_GET['users_id'] ?? $_POST['users_id'] ?? 0);
        // Silently ignore out-of-scope deep-links (no flash on every refresh).
        if ($preview_user > 0
            && !PluginAuchanassettrackerRighthelper::canAccessRecipientUser($preview_user)) {
            $preview_user = 0;
        }

        echo "<div class='aat-workspace'>";
        self::displayActiveAlerts($scope);

        // No <form> here: GLPI User::dropdown + Select2 is unreliable inside nested forms.
        // Button navigates with ?users_id= via JS.
        $gear_url = json_encode(self::getFormURL(), JSON_UNESCAPED_SLASHES);
        echo "<div class='row g-2 align-items-end mb-3' id='aat-gear-picker'>";
        echo "<div class='col-md-6'><label class='form-label'>"
            . __('Recipient user', 'auchanassettracker') . "</label>";
        PluginAuchanassettrackerRighthelper::dropdownRecipientUser([
            'name'  => 'users_id',
            'value' => $preview_user,
            'width' => '100%',
        ]);
        echo "</div><div class='col-md-auto'>";
        echo "<button type='button' class='btn btn-secondary' id='aat-show-gear'>"
            . Html::entities_deep(__('Show current gear', 'auchanassettracker'))
            . "</button>";
        echo "</div></div>";
        echo "<div id='aat-gear-user-msg' class='alert alert-warning d-none' role='alert'>"
            . Html::entities_deep(__('Please select a recipient user first.', 'auchanassettracker'))
            . "</div>";
        $msg_js = json_encode(
            __('Please select a recipient user first.', 'auchanassettracker'),
            JSON_UNESCAPED_UNICODE
        );
        echo Html::scriptBlock(<<<JS
$(function () {
  function aatSelectedUserId() {
    var \$root = $('#aat-gear-picker');
    var \$sel = \$root.find('select[name="users_id"]').first();
    if (!\$sel.length) {
      \$sel = \$root.find('select').first();
    }
    var v = \$sel.val();
    if (v === undefined || v === null || v === '') {
      v = \$root.find('.select2-hidden-accessible').val();
    }
    return parseInt(v, 10) || 0;
  }
  $('#aat-show-gear').on('click', function (e) {
    e.preventDefault();
    var uid = aatSelectedUserId();
    var \$msg = $('#aat-gear-user-msg');
    if (uid <= 0) {
      \$msg.removeClass('d-none').text({$msg_js});
      return;
    }
    \$msg.addClass('d-none');
    var url = {$gear_url};
    window.location.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'users_id=' + uid;
  });
});
JS);

        if ($preview_user > 0) {
            $current = self::getCurrentGearForUser($preview_user);
            echo "<div class='mb-3'><span class='fw-semibold me-2'>"
                . __('Equipment already with this user', 'auchanassettracker') . ":</span> ";
            if ($current === []) {
                echo "<span class='text-muted'>" . __('None.', 'auchanassettracker') . "</span>";
            } else {
                $parts = [];
                foreach ($current as $e) {
                    $parts[] = self::equipmentNameLink($e);
                }
                echo implode('<span class="text-muted"> · </span>', $parts);
            }
            echo "</div>";

            $available = PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                $scope
            );

            echo "<form method='post' action='" . Html::entities_deep(self::getFormURL()) . "'>";
            echo Html::hidden('users_id', ['value' => $preview_user]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<div class='table-responsive'><table class='table table-sm table-hover' id='aat-alloc-stock'>";
            echo "<thead><tr><th><input type='checkbox' class='form-check-input' id='aat-check-all' title='"
                . __('Select all') . "'></th><th>" . __('Name') . "</th><th>" . __('Type') . "</th><th>"
                . __('Serial number') . "</th><th>" . __('Container', 'auchanassettracker') . "</th></tr></thead><tbody>";
            $shown = 0;
            foreach ($available as $e) {
                if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
                    continue;
                }
                $shown++;
                $type = (string) ($e['itemtype'] ?? '');
                $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
                $cname = Dropdown::getDropdownName(
                    PluginAuchanassettrackerContainer::getTable(),
                    (int) $e['plugin_auchanassettracker_containers_id']
                );
                $e['source'] = 'plugin';
                echo "<tr><td>"
                    . "<input type='checkbox' class='form-check-input aat-eq-check' name='equipment_ids[]' value='"
                    . (int) $e['id'] . "'></td><td>" . self::equipmentNameLink($e)
                    . "</td><td>" . Html::entities_deep($type_label)
                    . "</td><td>" . Html::entities_deep((string) ($e['serial'] ?? ''))
                    . "</td><td>" . Html::entities_deep((string) $cname) . "</td></tr>";
            }
            if ($shown === 0) {
                echo "<tr><td colspan='5' class='text-muted'>"
                    . __('No available stock with a container at this location.', 'auchanassettracker')
                    . "</td></tr>";
            }
            echo "</tbody></table></div>";
            echo Html::scriptBlock(<<<'JS'
$(function () {
  $('#aat-check-all').on('change', function () {
    $('.aat-eq-check').prop('checked', this.checked);
  });
});
JS);
            echo "<div class='mt-3'>";
            echo Html::submit(__('Allocate', 'auchanassettracker'), ['name' => 'allocate', 'class' => 'btn btn-primary']);
            echo "</div>";
            Html::closeForm();
        }

        $history = self::getHistory($scope, 40);
        echo "<div class='aat-history-card mt-4'>";
        echo "<h3 class='fs-5'>" . __('Allocation history', 'auchanassettracker') . "</h3>";
        if ($history === []) {
            echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
        } else {
            echo "<div class='table-responsive'><table class='table table-sm table-striped mb-0'><thead><tr>";
            echo "<th>" . __('Equipment') . "</th><th>" . __('Type') . "</th><th>" . __('Serial number') . "</th>";
            echo "<th>" . __('Recipient user', 'auchanassettracker') . "</th>";
            echo "<th>" . __('Technician', 'auchanassettracker') . "</th>";
            echo "<th>" . __('Allocated on', 'auchanassettracker') . "</th>";
            echo "<th>" . __('Status') . "</th><th></th></tr></thead><tbody>";
            foreach ($history as $row) {
                $aid = (int) ($row['id'] ?? 0);
                $st = (string) ($row['allocation_status'] ?? '');
                $type = (string) ($row['itemtype'] ?? '');
                $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
                $eq_row = [
                    'name' => $row['equipment_name'] ?? '',
                    'equipments_id' => $row['equipments_id'] ?? $row['plugin_auchanassettracker_equipments_id'] ?? 0,
                    'source' => 'plugin',
                ];
                $confirm_href = $base . '/front/confirm.php?allocation_id=' . $aid;
                echo "<tr><td>" . self::equipmentNameLink($eq_row)
                    . "</td><td>" . Html::entities_deep($type_label)
                    . "</td><td>" . Html::entities_deep((string) ($row['serial'] ?? ''))
                    . "</td><td>" . self::userNameLink((int) ($row['users_id_recipient'] ?? 0))
                    . "</td><td>" . self::userNameLink((int) ($row['users_id_allocator'] ?? 0))
                    . "</td><td>" . Html::entities_deep((string) ($row['allocation_date'] ?? ''))
                    . "</td><td>" . Html::entities_deep(self::getStatusLabel($st))
                    . "</td><td>";
                if ($st === self::STATUS_PENDING && $aid > 0) {
                    echo "<a class='btn btn-sm btn-primary' href='"
                        . Html::entities_deep($confirm_href) . "'>"
                        . __('Open confirmation', 'auchanassettracker') . "</a>";
                }
                echo "</td></tr>";
            }
            echo "</tbody></table></div>";
        }
        echo "</div></div>";
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

        if (!PluginAuchanassettrackerRighthelper::canAccessRecipientUser($users_id_recipient)) {
            Session::addMessageAfterRedirect(
                __('You can only allocate to users from your location.', 'auchanassettracker'),
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

        $prev_container = (int) ($eq->fields['plugin_auchanassettracker_containers_id'] ?? 0);
        if ($prev_container <= 0) {
            Session::addMessageAfterRedirect(
                __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
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
            'plugin_auchanassettracker_containers_id_previous' => $prev_container,
            'allocation_date'     => $now,
            'allocation_status'   => self::STATUS_PENDING,
            'date_creation'       => $now,
            'date_mod'            => $now,
        ]);

        if (!$aid) {
            return false;
        }

        $ok = PluginAuchanassettrackerEquipment::applyWorkflowFields($equipment_id, [
            'status'   => PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
            'users_id' => $users_id_recipient,
            'plugin_auchanassettracker_containers_id' => 0,
        ]);

        if (!$ok) {
            // Do not leave a pending allocation without the equipment transition.
            $alloc->delete(['id' => (int) $aid], true);
            return false;
        }

        PluginAuchanassettrackerAuditlog::record(
            'allocation_initiate',
            self::class,
            (int) $aid,
            sprintf('equipment=%d user=%d', $equipment_id, $users_id_recipient)
        );
        PluginAuchanassettrackerMailhelper::notifyAllocationPending($users_id_recipient, $equipment_id);

        return true;
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
        $recipient = (int) $alloc->fields['users_id_recipient'];

        // Silent write: end users confirm receipt but cannot CommonDBTM-update equipment.
        $ok = PluginAuchanassettrackerEquipment::applyWorkflowFields($eq_id, [
            'status'   => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
            'users_id' => $recipient,
            'plugin_auchanassettracker_containers_id' => 0,
        ]);

        if (!$ok) {
            Session::addMessageAfterRedirect(
                __('Unable to confirm this allocation.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // Mark confirmed only after equipment is allocated (avoids history/gear desync).
        $alloc->update([
            'id'                  => $allocation_id,
            'allocation_status'   => self::STATUS_CONFIRMED,
            'confirmation_date'   => $now,
            'date_mod'            => $now,
        ]);

        $eq = new PluginAuchanassettrackerEquipment();
        if ($eq->getFromDB($eq_id)) {
            // Ensure a linked native GLPI asset exists, then set its owner.
            if ((int) ($eq->fields['items_id'] ?? 0) <= 0) {
                $asset_id = PluginAuchanassettrackerEquipment::createLinkedGlpiAsset($eq->fields);
                if ($asset_id > 0) {
                    PluginAuchanassettrackerEquipment::applyWorkflowFields($eq_id, [
                        'items_id' => $asset_id,
                    ]);
                    $eq->fields['items_id'] = $asset_id;
                }
            }
            PluginAuchanassettrackerEquipment::syncGlpiAssetOwner($eq->fields, $recipient);
        }

        PluginAuchanassettrackerAuditlog::record(
            'allocation_confirm',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        return true;
    }

    /**
     * Did not receive → Available and restore previous container.
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
        $prev = (int) ($alloc->fields['plugin_auchanassettracker_containers_id_previous'] ?? 0);
        if ($container_id <= 0) {
            $container_id = $prev;
        }

        $fields = [
            'status'   => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            'users_id' => 0,
        ];

        if ($container_id > 0) {
            if (!PluginAuchanassettrackerEquipment::containerBelongsToLocation($container_id, $loc)) {
                // Previous shelf missing/inactive — leave empty for manager.
                $fields['plugin_auchanassettracker_containers_id'] = 0;
            } else {
                $fields['plugin_auchanassettracker_containers_id'] = $container_id;
            }
        } else {
            $fields['plugin_auchanassettracker_containers_id'] = 0;
        }

        // Silent write: end users reject receipt but cannot CommonDBTM-update equipment.
        $ok = PluginAuchanassettrackerEquipment::applyWorkflowFields($eq_id, $fields);

        if (!$ok) {
            Session::addMessageAfterRedirect(
                __('Unable to reject this allocation.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $alloc->update([
            'id'                => $allocation_id,
            'allocation_status' => self::STATUS_REJECTED,
            'confirmation_date' => $now,
            'date_mod'          => $now,
        ]);

        if ($eq->getFromDB($eq_id)) {
            PluginAuchanassettrackerEquipment::syncGlpiAssetOwner($eq->fields, 0);
        }

        PluginAuchanassettrackerAuditlog::record(
            'allocation_reject',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        $allocator = (int) ($alloc->fields['users_id_allocator'] ?? 0);
        if ($allocator > 0) {
            $restored = (int) ($fields['plugin_auchanassettracker_containers_id'] ?? 0) > 0;
            PluginAuchanassettrackerMailhelper::notifyAllocationRejected($allocator, $eq_id, $restored);
        }

        return true;
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
     * Repair confirmed allocations whose equipment row never reached "allocated"
     * (e.g. end-user confirm failed CommonDBTM rights, or GLPI sync wiped users_id).
     * Only touches stuck awaiting_validation / mis-owned allocated rows — never stock.
     */
    public static function healConfirmedEquipmentForUser(int $users_id): void
    {
        global $DB;

        if ($users_id <= 0
            || !$DB->tableExists(self::getTable())
            || !$DB->tableExists(PluginAuchanassettrackerEquipment::getTable())) {
            return;
        }

        $seen_eq = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'plugin_auchanassettracker_equipments_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id_recipient' => $users_id,
                'allocation_status'  => self::STATUS_CONFIRMED,
            ],
            'ORDER'  => 'id DESC',
        ]) as $row) {
            $eq_id = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($eq_id <= 0 || isset($seen_eq[$eq_id])) {
                continue;
            }
            $seen_eq[$eq_id] = true;

            // Newer pending/returned allocation supersedes this confirm — skip.
            if (self::hasPendingForEquipment($eq_id)) {
                continue;
            }

            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eq_id)) {
                continue;
            }

            $status = (string) ($eq->fields['status'] ?? '');
            $owner  = (int) ($eq->fields['users_id'] ?? 0);

            // Only stuck mid-confirm (or allocated with wrong/zero owner).
            $stuck_pending = $status === PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION;
            $stuck_owner   = $status === PluginAuchanassettrackerEquipment::STATUS_ALLOCATED
                && $owner !== $users_id;
            if (!$stuck_pending && !$stuck_owner) {
                continue;
            }

            if (!PluginAuchanassettrackerEquipment::applyWorkflowFields($eq_id, [
                'status'   => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                'users_id' => $users_id,
                'plugin_auchanassettracker_containers_id' => 0,
            ])) {
                continue;
            }

            if ($eq->getFromDB($eq_id)) {
                if ((int) ($eq->fields['items_id'] ?? 0) <= 0) {
                    $asset_id = PluginAuchanassettrackerEquipment::createLinkedGlpiAsset($eq->fields);
                    if ($asset_id > 0) {
                        PluginAuchanassettrackerEquipment::applyWorkflowFields($eq_id, [
                            'items_id' => $asset_id,
                        ]);
                        $eq->fields['items_id'] = $asset_id;
                    }
                }
                PluginAuchanassettrackerEquipment::syncGlpiAssetOwner($eq->fields, $users_id);
            }
        }
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
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));

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
            $row['itemtype'] = $eq->fields['itemtype'] ?? '';
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getHistory(?int $locations_id = null, int $limit = 50): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => 'allocation_date DESC',
            'LIMIT' => $limit,
        ]) as $row) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB((int) $row['plugin_auchanassettracker_equipments_id'])) {
                continue;
            }
            if ($locations_id !== null && (int) $eq->fields['locations_id'] !== $locations_id) {
                continue;
            }
            $row['equipment_name'] = $eq->fields['name'] ?? '';
            $row['serial'] = $eq->fields['serial'] ?? '';
            $row['itemtype'] = $eq->fields['itemtype'] ?? '';
            $row['equipments_id'] = (int) $eq->getID();
            $rows[] = $row;
        }
        return $rows;
    }

    public static function getStatusLabel(string $status): string
    {
        $map = [
            self::STATUS_PENDING   => __('Pending confirmation', 'auchanassettracker'),
            self::STATUS_CONFIRMED => __('Confirmed', 'auchanassettracker'),
            self::STATUS_REJECTED  => __('Did not receive', 'auchanassettracker'),
            self::STATUS_RETURNED  => __('Returned', 'auchanassettracker'),
        ];
        return $map[$status] ?? $status;
    }

    /**
     * Plugin equipment currently with a user (confirmed only).
     * Pending / awaiting_validation stays in Confirm receipt until validated.
     *
     * @return list<array<string, mixed>>
     */
    public static function getUserEquipment(int $users_id): array
    {
        return PluginAuchanassettrackerEquipment::findByStatus(
            PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
            null,
            $users_id
        );
    }

    /**
     * Plugin gear + native GLPI assets assigned to the user.
     *
     * @return list<array<string, mixed>>
     */
    public static function getCurrentGearForUser(int $users_id): array
    {
        self::healConfirmedEquipmentForUser($users_id);
        $plugin = self::getUserEquipment($users_id);
        foreach ($plugin as &$row) {
            $row['source'] = 'plugin';
        }
        unset($row);

        $native = PluginAuchanassettrackerEquipment::findGlpiAssetsForUser($users_id);
        // Avoid duplicate display when the same asset is linked from plugin stock.
        $linked = [];
        foreach ($plugin as $p) {
            $it = (string) ($p['itemtype'] ?? '');
            $iid = (int) ($p['items_id'] ?? 0);
            if ($it !== '' && $iid > 0) {
                $linked[$it . ':' . $iid] = true;
            }
        }

        $out = $plugin;
        foreach ($native as $n) {
            $key = ($n['itemtype'] ?? '') . ':' . (int) ($n['items_id'] ?? 0);
            if (isset($linked[$key])) {
                continue;
            }
            $n['source'] = 'glpi';
            $out[] = $n;
        }
        return $out;
    }

    /**
     * User IDs of managers who should see location-scoped alerts.
     *
     * @return list<int>
     */
    public static function getManagerUserIds(?int $locations_id = null): array
    {
        global $DB;

        if (!$DB->tableExists(PluginAuchanassettrackerProfile::getTable())
            || !$DB->tableExists('glpi_profiles_users')) {
            return [];
        }

        $profile_ids = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerProfile::getTable(),
            'WHERE' => [
                'role' => [
                    PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN,
                    PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER,
                ],
            ],
        ]) as $row) {
            $role = (string) ($row['role'] ?? '');
            $loc  = (int) ($row['locations_id'] ?? 0);
            if ($role === PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN) {
                $profile_ids[(int) $row['profiles_id']] = true;
                continue;
            }
            if ($locations_id === null || $loc === 0 || $loc === $locations_id) {
                $profile_ids[(int) $row['profiles_id']] = true;
            }
        }

        if ($profile_ids === []) {
            return [];
        }

        $uids = [];
        foreach ($DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_profiles_users',
            'WHERE'  => ['profiles_id' => array_keys($profile_ids)],
        ]) as $row) {
            $uid = (int) ($row['users_id'] ?? 0);
            if ($uid > 0) {
                $uids[$uid] = true;
            }
        }
        return array_keys($uids);
    }

    /**
     * Render Active alerts (operational state only — not duplicated as notices).
     *
     * - Late confirmations: pending longer than the calendar-day threshold
     * - Needs a container: Did not receive and previous shelf could not be restored
     */
    public static function displayActiveAlerts(?int $locations_id = null): void
    {
        $base = plugin_auchanassettracker_web_dir();
        $overdue = self::getOverduePending($locations_id);
        $needs_container = PluginAuchanassettrackerEquipment::findNeedsContainer($locations_id);
        if ($overdue === [] && $needs_container === []) {
            return;
        }

        echo "<div class='alert alert-warning aat-alert-block'>";
        echo "<div class='aat-alert-title'>" . __('Active alerts', 'auchanassettracker') . "</div>";
        if ($overdue !== []) {
            echo "<p class='mb-1 fw-semibold'>"
                . __('Late confirmations (calendar days)', 'auchanassettracker') . "</p>";
            echo "<ul>";
            foreach ($overdue as $row) {
                $eid = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
                $label = trim((string) ($row['equipment_name'] ?? ''));
                if ($label === '') {
                    $label = '#' . $eid;
                }
                echo "<li><a href='" . $base . "/front/equipment.form.php?id=$eid'>"
                    . Html::entities_deep($label) . "</a></li>";
            }
            echo "</ul>";
        }
        if ($needs_container !== []) {
            echo "<p class='mb-1 fw-semibold'>"
                . __('Needs a container (rejected)', 'auchanassettracker') . "</p>";
            echo "<ul class='mb-0'>";
            foreach ($needs_container as $eq) {
                $label = trim((string) ($eq['name'] ?? ''));
                if ($label === '') {
                    $label = '#' . (int) ($eq['id'] ?? 0);
                }
                echo "<li><a href='" . $base . "/front/equipment.form.php?id=" . (int) $eq['id'] . "'>"
                    . Html::entities_deep($label) . "</a></li>";
            }
            echo "</ul>";
        }
        echo "</div>";
    }
}
