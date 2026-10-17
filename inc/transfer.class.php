<?php

/**
 * Inter-location transfer workflow.
 */
class PluginAuchanassettrackerTransfer extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker_transfer';

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

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_TRANSFER];
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/transfer.php';
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/transfer.form.php';
    }

    public static function canView(): bool
    {
        return PluginAuchanassettrackerRighthelper::canTransfer();
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, CREATE);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE)
            || Session::haveRight(self::$rightname, CREATE);
    }

    public function getRights($interface = 'central')
    {
        return PluginAuchanassettrackerProfile::getStandardRightsSet();
    }

    /**
     * Destination Location: GLPI-looking Select2 + i/+ from first paint (no native ----- flash).
     * Options are preloaded; Select2 is bound in an inline script (same pattern as recipient user).
     *
     * @param array{name?: string, value?: int, used?: list<int>, width?: string, display_emptychoice?: bool} $options
     */
    public static function dropdownDestinationLocation(array $options = []): void
    {
        global $DB, $CFG_GLPI;

        $name = (string) ($options['name'] ?? 'locations_id_dest');
        $value = (int) ($options['value'] ?? 0);
        $width = (string) ($options['width'] ?? '100%');
        $empty = array_key_exists('display_emptychoice', $options)
            ? (bool) $options['display_emptychoice']
            : ($value <= 0);
        $rand = (int) ($options['rand'] ?? mt_rand());
        $field_id = Html::cleanId('dropdown_' . $name . $rand);

        $used = [];
        if (isset($options['used']) && is_array($options['used'])) {
            foreach ($options['used'] as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $used[$uid] = $uid;
                }
            }
        }
        foreach (self::destinationLocationsToExclude() as $uid) {
            $used[$uid] = $uid;
        }
        if ($value > 0) {
            unset($used[$value]);
        }

        $locations = [];
        if ($DB->tableExists('glpi_locations')) {
            $where = [];
            if ($DB->fieldExists('glpi_locations', 'is_deleted')) {
                $where['is_deleted'] = 0;
            }
            if (function_exists('getEntitiesRestrictCriteria')) {
                $ent = getEntitiesRestrictCriteria('glpi_locations', '', '', true);
                if (is_array($ent) && $ent !== []) {
                    $where[] = $ent;
                }
            }
            $req = [
                'SELECT' => ['id', 'completename', 'name'],
                'FROM'   => 'glpi_locations',
                'ORDER'  => 'completename ASC',
            ];
            if ($where !== []) {
                $req['WHERE'] = $where;
            }
            foreach ($DB->request($req) as $row) {
                $lid = (int) ($row['id'] ?? 0);
                if ($lid <= 0 || isset($used[$lid])) {
                    continue;
                }
                $label = trim((string) ($row['completename'] ?? $row['name'] ?? ''));
                $locations[$lid] = $label !== '' ? $label : ('#' . $lid);
            }
        }
        if ($value > 0 && !isset($locations[$value])) {
            $label = Dropdown::getDropdownName('glpi_locations', $value);
            $plain = trim(strip_tags((string) $label));
            $locations[$value] = ($plain !== '' && $plain !== '&nbsp;' && $plain !== '-')
                ? $plain
                : ('#' . $value);
        }

        $width_js = json_encode($width, JSON_UNESCAPED_SLASHES);
        $field_js = json_encode($field_id, JSON_UNESCAPED_SLASHES);
        $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');

        $placeholder_label = Dropdown::EMPTY_VALUE;
        if ($value > 0 && isset($locations[$value])) {
            $placeholder_label = $locations[$value];
        }

        // btn-group like native Location::dropdown (Select2 + i + +).
        // Placeholder mimics Select2 until inline init finishes — no native ----- flash.
        echo "<div class='btn-group btn-group-sm aat-location-dropdown aat-location-booting' role='group'>";
        echo "<span class='aat-location-s2-ph' aria-hidden='true'>"
            . "<span class='aat-location-s2-ph-rendered'>"
            . "<span class='aat-location-s2-ph-text'>" . Html::entities_deep($placeholder_label) . "</span>"
            . "<span class='aat-location-s2-ph-arrow'></span>"
            . "</span></span>";
        echo "<select name='" . Html::entities_deep($name) . "' id='"
            . Html::entities_deep($field_id)
            . "' class='form-select aat-dest-location aat-location-pending'"
            . " data-glpicontainername='" . Html::entities_deep($name) . "'"
            . " style='width:" . Html::entities_deep($width) . "'>";
        if ($empty) {
            echo "<option value='0'>" . Html::entities_deep(Dropdown::EMPTY_VALUE) . "</option>";
        }
        foreach ($locations as $lid => $label) {
            $sel = ((int) $lid === $value) ? ' selected' : '';
            echo "<option value='" . (int) $lid . "'" . $sel . ">"
                . Html::entities_deep($label) . "</option>";
        }
        echo '</select>';

        // Info (i) — opens location form or list (GLPI comment button).
        $info_href = Location::getSearchURL(false);
        if ($value > 0 && method_exists(Location::class, 'getFormURLWithID')) {
            $info_href = Location::getFormURLWithID($value);
        }
        if ($info_href !== '' && $info_href[0] !== '/') {
            $info_href = $root . '/' . ltrim($info_href, '/');
        } elseif ($info_href !== '' && str_starts_with($info_href, '/')) {
            // already root-relative
        } else {
            $info_href = $root . '/front/location.php';
        }
        $comment_id = Html::cleanId('comment_' . $name . $rand);
        $link_id = Html::cleanId('comment_link_' . $name . $rand);
        echo "<a class='btn btn-outline-secondary' id='" . Html::entities_deep($link_id) . "'"
            . " href='" . Html::entities_deep($info_href) . "' target='_blank'"
            . " title='" . Html::entities_deep(sprintf(__('Show %1$s'), Location::getTypeName(1))) . "'>"
            . "<i class='ti ti-info-circle'></i>"
            . "<span class='sr-only'>" . Html::entities_deep(__('Show')) . "</span>"
            . "</a>";
        echo "<span id='" . Html::entities_deep($comment_id) . "' class='d-none'></span>";

        // Add (+) — same iframe modal as native Location dropdown.
        if (Location::canCreate() && !isset($_REQUEST['_in_modal'])) {
            $modal_id = 'add_' . $field_id;
            echo Ajax::createIframeModalWindow($modal_id, Location::getFormURL(), ['display' => false]);
            echo "<div class='btn btn-outline-secondary' title='" . Html::entities_deep(__('Add')) . "'"
                . " data-bs-toggle='modal' data-bs-target='#" . Html::entities_deep($modal_id) . "'>"
                . "<span data-bs-toggle='tooltip'><i class='ti ti-plus'></i>"
                . "<span class='sr-only'>" . Html::entities_deep(__('Add')) . "</span></span>"
                . "</div>";
        }
        echo '</div>';

        // Keep i link in sync with selection (open the chosen location).
        $form_base = json_encode($root . '/front/location.form.php?id=', JSON_UNESCAPED_SLASHES);
        $search_url = json_encode($root . '/front/location.php', JSON_UNESCAPED_SLASHES);
        echo Html::scriptBlock(<<<JS
(function () {
  function aatInitDestLocation() {
    var \$sel = $('#' + {$field_js});
    if (!\$sel.length || \$sel.data('aatSelect2Ready')) { return; }
    if (typeof \$sel.select2 !== 'function') { return; }
    if (\$sel.hasClass('select2-hidden-accessible')) {
      try { \$sel.select2('destroy'); } catch (e) {}
    }
    \$sel.select2({
      width: {$width_js},
      allowClear: false,
      minimumResultsForSearch: 0,
      dropdownAutoWidth: false
    });
    \$sel.removeClass('aat-location-pending').data('aatSelect2Ready', true);
    \$sel.on('change.aatDestLoc', function () {
      var v = parseInt(\$sel.val(), 10) || 0;
      var \$link = $('#{$link_id}');
      if (\$link.length) {
        \$link.attr('href', v > 0 ? ({$form_base} + v) : {$search_url});
      }
    });
  }
  if (window.jQuery) {
    aatInitDestLocation();
    \$(aatInitDestLocation);
  }
})();
JS);
    }

    /**
     * Location IDs that must not appear as transfer destinations (own / scoped site).
     *
     * @return list<int>
     */
    public static function destinationLocationsToExclude(): array
    {
        $out = [];
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null && (int) $scope > 0) {
            $out[] = (int) $scope;
        }
        $uid = (int) Session::getLoginUserID();
        if ($uid > 0) {
            $user = new User();
            if ($user->getFromDB($uid)) {
                $loc = (int) ($user->fields['locations_id'] ?? 0);
                if ($loc > 0) {
                    $out[] = $loc;
                }
            }
        }
        return array_values(array_unique($out));
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
        if (in_array($locations_id_dest, self::destinationLocationsToExclude(), true)) {
            Session::addMessageAfterRedirect(
                __('Destination must be different from your location.', 'auchanassettracker'),
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
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->getFromDB($eid);
            $prev_container = (int) ($eq->fields['plugin_auchanassettracker_containers_id'] ?? 0);

            $item = new PluginAuchanassettrackerTransferitem();
            $item->add([
                'plugin_auchanassettracker_transfers_id'              => (int) $tid,
                'plugin_auchanassettracker_equipments_id'             => $eid,
                'plugin_auchanassettracker_containers_id_previous'    => $prev_container,
                'date_creation' => $now,
            ]);

            PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, [
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
     * Sender may edit destination, notes, and equipment while still in transit.
     *
     * @param list<int>|null $equipment_ids null = keep current items; otherwise replace set
     */
    public static function updateInTransit(
        int $transfer_id,
        int $locations_id_dest,
        string $notes = '',
        ?array $equipment_ids = null
    ): bool {
        $tr = new self();
        if (!$tr->getFromDB($transfer_id)) {
            return false;
        }
        if (($tr->fields['transfer_status'] ?? '') !== self::STATUS_IN_TRANSIT) {
            Session::addMessageAfterRedirect(
                __('Only in-transit transfers can be edited.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }
        if (!self::isSenderForTransfer($tr)) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $source = (int) ($tr->fields['locations_id_source'] ?? 0);
        if ($locations_id_dest <= 0 || $locations_id_dest === $source) {
            Session::addMessageAfterRedirect(
                __('Destination must be different from source location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $ok = (bool) $tr->update([
            'id'                => $transfer_id,
            'locations_id_dest' => $locations_id_dest,
            'notes'             => $notes,
            'date_mod'          => $now,
        ]);
        if (!$ok) {
            return false;
        }

        if ($equipment_ids !== null) {
            if (!self::syncEquipmentSet($transfer_id, $source, $equipment_ids)) {
                return false;
            }
        }

        PluginAuchanassettrackerAuditlog::record(
            'transfer_update',
            self::class,
            $transfer_id,
            'dest=' . $locations_id_dest
            . ($equipment_ids !== null ? ' items=' . implode(',', $equipment_ids) : '')
        );

        return true;
    }

    /**
     * Replace in-transit equipment set: restore removed items, pull in newly selected stock.
     *
     * @param list<int> $equipment_ids
     */
    public static function syncEquipmentSet(int $transfer_id, int $source, array $equipment_ids): bool
    {
        global $DB;

        $equipment_ids = array_values(array_unique(array_filter(array_map('intval', $equipment_ids))));
        if ($equipment_ids === []) {
            Session::addMessageAfterRedirect(
                __('Select destination and at least one equipment item.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $current = self::getItems($transfer_id);
        $current_by_eq = [];
        foreach ($current as $row) {
            $eid = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($eid > 0) {
                $current_by_eq[$eid] = $row;
            }
        }

        $wanted = array_fill_keys($equipment_ids, true);

        // Remove unchecked items → Available at source again.
        foreach ($current_by_eq as $eid => $row) {
            if (isset($wanted[$eid])) {
                continue;
            }
            $prev = (int) ($row['plugin_auchanassettracker_containers_id_previous'] ?? 0);
            $fields = [
                'status'       => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                'locations_id' => $source,
                'users_id'     => 0,
            ];
            if ($prev > 0
                && PluginAuchanassettrackerEquipment::containerBelongsToLocation($prev, $source)
            ) {
                $fields['plugin_auchanassettracker_containers_id'] = $prev;
            } else {
                $fields['plugin_auchanassettracker_containers_id'] = 0;
            }
            if (!PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, $fields)) {
                Session::addMessageAfterRedirect(
                    __('Unable to restore equipment after transfer cancel.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            $ti_id = (int) ($row['id'] ?? 0);
            if ($ti_id > 0) {
                $DB->delete(PluginAuchanassettrackerTransferitem::getTable(), ['id' => $ti_id]);
            }
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        // Keep or add selected items.
        foreach ($equipment_ids as $eid) {
            if (isset($current_by_eq[$eid])) {
                continue;
            }

            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eid)) {
                Session::addMessageAfterRedirect(__('Unable to load item'), false, ERROR);
                return false;
            }
            if ((int) ($eq->fields['locations_id'] ?? 0) !== $source) {
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
            $prev_container = (int) ($eq->fields['plugin_auchanassettracker_containers_id'] ?? 0);
            if ($prev_container <= 0) {
                Session::addMessageAfterRedirect(
                    __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }

            $item = new PluginAuchanassettrackerTransferitem();
            $item->add([
                'plugin_auchanassettracker_transfers_id'           => $transfer_id,
                'plugin_auchanassettracker_equipments_id'          => $eid,
                'plugin_auchanassettracker_containers_id_previous' => $prev_container,
                'date_creation'                                   => $now,
            ]);

            PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, [
                'status'                                  => PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT,
                'plugin_auchanassettracker_containers_id' => 0,
            ]);
        }

        return true;
    }

    /**
     * Equipment eligible for an in-transit edit: currently on the transfer + Available at source.
     *
     * @return list<array<string, mixed>>
     */
    public static function getEditableEquipmentRows(int $transfer_id, int $source): array
    {
        $rows = [];
        $seen = [];

        foreach (self::getItems($transfer_id) as $it) {
            $eid = (int) ($it['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB($eid)) {
                continue;
            }
            $fields = $eq->fields;
            $fields['id'] = $eid;
            $fields['_on_transfer'] = true;
            $fields['_prev_container'] = (int) ($it['plugin_auchanassettracker_containers_id_previous'] ?? 0);
            $rows[] = $fields;
            $seen[$eid] = true;
        }

        foreach (PluginAuchanassettrackerEquipment::findByStatus(
            PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            $source
        ) as $e) {
            $eid = (int) ($e['id'] ?? 0);
            if ($eid <= 0 || isset($seen[$eid])) {
                continue;
            }
            if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
                continue;
            }
            $e['_on_transfer'] = false;
            $rows[] = $e;
            $seen[$eid] = true;
        }

        return $rows;
    }

    /**
     * Sender cancels an in-transit transfer and restores stock at source.
     */
    public static function cancelBySender(int $transfer_id): bool
    {
        $tr = new self();
        if (!$tr->getFromDB($transfer_id)) {
            return false;
        }
        if (($tr->fields['transfer_status'] ?? '') !== self::STATUS_IN_TRANSIT) {
            Session::addMessageAfterRedirect(
                __('Only in-transit transfers can be deleted.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }
        if (!self::isSenderForTransfer($tr)) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        if (!self::restoreItemsToSource($transfer_id)) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $tr->update([
            'id'              => $transfer_id,
            'transfer_status' => self::STATUS_CANCELLED,
            'date_mod'        => $now,
        ]);

        PluginAuchanassettrackerAuditlog::record(
            'transfer_cancel',
            self::class,
            $transfer_id,
            'by_sender'
        );

        return true;
    }

    /**
     * Destination recipient rejects an in-transit transfer (mandatory comment).
     */
    public static function rejectByRecipient(int $transfer_id, string $comment): bool
    {
        $tr = new self();
        if (!$tr->getFromDB($transfer_id)) {
            return false;
        }
        if (($tr->fields['transfer_status'] ?? '') !== self::STATUS_IN_TRANSIT) {
            return false;
        }

        $dest = (int) ($tr->fields['locations_id_dest'] ?? 0);
        $can_reject = PluginAuchanassettrackerRighthelper::canAccessLocation($dest)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
        if (!$can_reject) {
            Session::addMessageAfterRedirect(
                __('Only the destination location can reject this transfer.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $comment = trim($comment);
        if ($comment === '') {
            Session::addMessageAfterRedirect(
                __('A comment is mandatory when rejecting a transfer.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!self::restoreItemsToSource($transfer_id)) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        global $DB;
        $patch = [
            'id'              => $transfer_id,
            'transfer_status' => self::STATUS_CANCELLED,
            'reject_comment'  => $comment,
            'date_mod'        => $now,
        ];
        // Who rejected (destination side). Prefer dedicated column when present.
        $rejector = (int) Session::getLoginUserID();
        if ($DB->fieldExists(self::getTable(), 'users_id_rejector')) {
            $patch['users_id_rejector'] = $rejector;
        } else {
            // Fallback: reuse validator slot (approve and reject are mutually exclusive).
            $patch['users_id_validator'] = $rejector;
        }
        $tr->update($patch);

        PluginAuchanassettrackerAuditlog::record(
            'transfer_reject',
            self::class,
            $transfer_id,
            'comment=' . mb_substr($comment, 0, 200)
        );

        $initiator = (int) ($tr->fields['users_id_initiator'] ?? 0);
        if ($initiator > 0) {
            PluginAuchanassettrackerMailhelper::notifyTransferRejected($initiator, $transfer_id, $comment);
        }

        return true;
    }

    /**
     * Current user is the sender (transfer initiator). Central admin can also act.
     */
    public static function isSenderForTransfer(self $tr): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        $uid = (int) Session::getLoginUserID();
        return $uid > 0 && (int) ($tr->fields['users_id_initiator'] ?? 0) === $uid;
    }

    /**
     * Put transferred equipment back to Available at the source location.
     */
    public static function restoreItemsToSource(int $transfer_id): bool
    {
        $tr = new self();
        if (!$tr->getFromDB($transfer_id)) {
            return false;
        }
        $source = (int) ($tr->fields['locations_id_source'] ?? 0);
        $items = self::getItems($transfer_id);

        foreach ($items as $item) {
            $eid = (int) ($item['plugin_auchanassettracker_equipments_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $prev = (int) ($item['plugin_auchanassettracker_containers_id_previous'] ?? 0);
            $fields = [
                'status'       => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                'locations_id' => $source,
                'users_id'     => 0,
            ];
            if ($prev > 0
                && PluginAuchanassettrackerEquipment::containerBelongsToLocation($prev, $source)
            ) {
                $fields['plugin_auchanassettracker_containers_id'] = $prev;
            } else {
                $fields['plugin_auchanassettracker_containers_id'] = 0;
            }
            if (!PluginAuchanassettrackerEquipment::applyWorkflowFields($eid, $fields)) {
                Session::addMessageAfterRedirect(
                    __('Unable to restore equipment after transfer cancel.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Transfers visible for the current location scope (all statuses — history).
     *
     * @return list<array<string, mixed>>
     */
    public static function getForLocationScope(?int $locations_id): array
    {
        global $DB;

        if ($locations_id === null) {
            return self::getAll();
        }
        if ($locations_id <= 0) {
            return [];
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'OR' => [
                    'locations_id_source' => $locations_id,
                    'locations_id_dest'   => $locations_id,
                ],
            ],
            'ORDER' => 'date_initiated DESC',
            'LIMIT' => 500,
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
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
