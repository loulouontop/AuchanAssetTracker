<?php

/**
 * Tracked equipment (stock → user → transfer → service → final).
 */
class PluginAuchanassettrackerEquipment extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_AVAILABLE           = 'available';
    public const STATUS_AWAITING_VALIDATION = 'awaiting_validation';
    public const STATUS_ALLOCATED           = 'allocated';
    public const STATUS_IN_TRANSIT          = 'in_transit';
    public const STATUS_IN_SERVICE          = 'in_service';
    public const STATUS_WRITTEN_OFF         = 'written_off';
    public const STATUS_LOST                = 'lost';
    public const STATUS_STOLEN              = 'stolen';

    public static function getTypeName($nb = 0): string
    {
        return _n('Equipment', 'Equipment', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipments';
    }

    public static function getIcon(): string
    {
        return 'ti ti-device-desktop';
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE           => __('Available', 'auchanassettracker'),
            self::STATUS_AWAITING_VALIDATION => __('Awaiting validation', 'auchanassettracker'),
            self::STATUS_ALLOCATED           => __('Allocated', 'auchanassettracker'),
            self::STATUS_IN_TRANSIT          => __('In transit', 'auchanassettracker'),
            self::STATUS_IN_SERVICE          => __('In service', 'auchanassettracker'),
            self::STATUS_WRITTEN_OFF         => __('Written off', 'auchanassettracker'),
            self::STATUS_LOST                => __('Lost', 'auchanassettracker'),
            self::STATUS_STOLEN              => __('Stolen', 'auchanassettracker'),
        ];
    }

    public static function getFinalStatuses(): array
    {
        return [
            self::STATUS_WRITTEN_OFF,
            self::STATUS_LOST,
            self::STATUS_STOLEN,
        ];
    }

    public static function isFinalStatus(string $status): bool
    {
        return in_array($status, self::getFinalStatuses(), true);
    }

    public static function getStatusLabel(string $status): string
    {
        $all = self::getStatuses();
        return $all[$status] ?? $status;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof self || $item->getID() <= 0) {
            return '';
        }

        $status = (string) ($item->fields['status'] ?? '');
        if (self::isFinalStatus($status)
            && PluginAuchanassettrackerRighthelper::canChangeFinalStatus()) {
            return __('Reintroduce into stock', 'auchanassettracker');
        }

        if (PluginAuchanassettrackerRighthelper::canWriteOff()
            && !self::isFinalStatus($status)
            && !in_array($status, [
                self::STATUS_IN_TRANSIT,
                self::STATUS_AWAITING_VALIDATION,
            ], true)) {
            return __('Write-off / Lost / Stolen', 'auchanassettracker');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof self) {
            return false;
        }
        $item->showFinalActionsForm();
        return true;
    }

    /**
     * Write-off / reintroduce actions (GLPI item tab).
     */
    public function showFinalActionsForm(): void
    {
        $id = (int) $this->getID();
        if ($id <= 0) {
            return;
        }

        $status = (string) ($this->fields['status'] ?? '');
        $base = Plugin::getWebDir(plugin_auchanassettracker_dir());

        if (PluginAuchanassettrackerRighthelper::canWriteOff()
            && !self::isFinalStatus($status)
            && !in_array($status, [
                self::STATUS_IN_TRANSIT,
                self::STATUS_AWAITING_VALIDATION,
            ], true)) {
            echo "<div class='asset'><form method='post' action='"
                . Html::entities_deep($base . '/front/equipment.form.php') . "'>";
            echo Html::hidden('id', ['value' => $id]);
            echo "<div class='card'><div class='card-body'>";
            echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>"
                . __('Write-off / Lost / Stolen', 'auchanassettracker') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>" . __('Action') . "</td><td>";
            Dropdown::showFromArray('final_status', [
                self::STATUS_WRITTEN_OFF => __('Written off', 'auchanassettracker'),
                self::STATUS_LOST        => __('Lost', 'auchanassettracker'),
                self::STATUS_STOLEN      => __('Stolen', 'auchanassettracker'),
            ]);
            echo "</td></tr><tr class='tab_bg_1'><td>"
                . __('Reason', 'auchanassettracker') . " *</td><td>";
            echo "<textarea name='final_reason' class='form-control' required rows='3'></textarea></td></tr>";
            echo "<tr class='tab_bg_1'><td>"
                . __('Document (optional)', 'auchanassettracker') . "</td><td>";
            echo Html::input('final_document', ['value' => '']);
            echo "</td></tr><tr class='tab_bg_2'><td colspan='2' class='center'>";
            echo Html::submit(__('Apply', 'auchanassettracker'), [
                'name'  => 'mark_final',
                'class' => 'btn btn-warning',
            ]);
            echo "</td></tr></table></div></div>";
            Html::closeForm();
            echo "</div>";
        }

        if (PluginAuchanassettrackerRighthelper::canChangeFinalStatus()
            && self::isFinalStatus($status)) {
            echo "<div class='asset'><form method='post' action='"
                . Html::entities_deep($base . '/front/equipment.form.php') . "'>";
            echo Html::hidden('id', ['value' => $id]);
            echo "<div class='card'><div class='card-body'>";
            echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>"
                . __('Reintroduce into stock', 'auchanassettracker') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>"
                . __('Container', 'auchanassettracker') . " *</td><td>";
            PluginAuchanassettrackerContainer::dropdown([
                'name'      => 'plugin_auchanassettracker_containers_id',
                'condition' => [
                    'locations_id' => (int) $this->fields['locations_id'],
                    'is_active'    => 1,
                    'is_deleted'   => 0,
                ],
            ]);
            echo "</td></tr><tr class='tab_bg_2'><td colspan='2' class='center'>";
            echo Html::submit(__('Reintroduce', 'auchanassettracker'), [
                'name'  => 'reintroduce',
                'class' => 'btn btn-primary',
            ]);
            echo "</td></tr></table></div></div>";
            Html::closeForm();
            echo "</div>";
        }
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(1)];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => 2,
            'table'    => self::getTable(),
            'field'    => 'serial',
            'name'     => __('Serial number'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => 3,
            'table'    => self::getTable(),
            'field'    => 'model',
            'name'     => __('Model'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'status',
            'name'     => __('Status'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'        => 5,
            'table'     => 'glpi_locations',
            'field'     => 'completename',
            'name'      => __('Location'),
            'datatype'  => 'dropdown',
            'linkfield' => 'locations_id',
        ];
        $tab[] = [
            'id'        => 6,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('Allocated user', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'users_id',
        ];
        $tab[] = [
            'id'        => 7,
            'table'     => PluginAuchanassettrackerContainer::getTable(),
            'field'     => 'name',
            'name'      => __('Physical container', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_auchanassettracker_containers_id',
        ];
        $tab[] = [
            'id'        => 8,
            'table'     => PluginAuchanassettrackerEquipmenttype::getTable(),
            'field'     => 'name',
            'name'      => __('Equipment type', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_auchanassettracker_equipmenttypes_id',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            return self::getStatusLabel((string) ($values[$field] ?? ''));
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if ($field === 'status') {
            return Dropdown::showFromArray($name, self::getStatuses(), [
                'value'   => is_array($values) ? ($values[$field] ?? '') : $values,
                'display' => false,
            ]);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function prepareInputForAdd($input)
    {
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $input['locations_id'] = $scope;
        }

        $locations_id = (int) ($input['locations_id'] ?? 0);
        if ($locations_id <= 0) {
            Session::addMessageAfterRedirect(
                __('Location is required.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot add equipment in another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $type_id = (int) ($input['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
        $mfr_id  = (int) ($input['plugin_auchanassettracker_manufacturers_id'] ?? 0);
        $model   = trim((string) ($input['model'] ?? ''));
        $serial  = trim((string) ($input['serial'] ?? ''));

        if ($type_id <= 0 || $mfr_id <= 0 || $model === '') {
            Session::addMessageAfterRedirect(
                __('Type, manufacturer and model are mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (PluginAuchanassettrackerEquipmenttype::isCategoryA($type_id) && $serial === '') {
            Session::addMessageAfterRedirect(
                __('Serial number is required for this equipment type.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if ($serial !== '' && self::serialExists($serial)) {
            Session::addMessageAfterRedirect(
                __('Serial number must be unique.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // New receipt always Available + mandatory container.
        $input['status'] = self::STATUS_AVAILABLE;
        $container_id = (int) ($input['plugin_auchanassettracker_containers_id'] ?? 0);
        if ($container_id <= 0) {
            Session::addMessageAfterRedirect(
                __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!self::containerBelongsToLocation($container_id, $locations_id)) {
            Session::addMessageAfterRedirect(
                __('Selected container does not belong to this location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $input['users_id'] = 0;
        $input['serial'] = $serial !== '' ? $serial : null;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

        $type = new PluginAuchanassettrackerEquipmenttype();
        $typeName = $type->getFromDB($type_id) ? ($type->fields['name'] ?? 'Equipment') : 'Equipment';
        $input['name'] = trim((string) ($input['name'] ?? ''));
        if ($input['name'] === '') {
            $input['name'] = $typeName . ($serial !== '' ? ' - ' . $serial : ' - ' . $model);
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_creation'] = $now;
        $input['date_mod'] = $now;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $current_status = (string) ($this->fields['status'] ?? '');
        $new_status = isset($input['status']) ? (string) $input['status'] : $current_status;

        if (self::isFinalStatus($current_status) && !PluginAuchanassettrackerRighthelper::canChangeFinalStatus()) {
            Session::addMessageAfterRedirect(
                __('Only the Central Admin can modify final statuses.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // Enforce container when status is / becomes Available.
        if ($new_status === self::STATUS_AVAILABLE) {
            $container_id = (int) ($input['plugin_auchanassettracker_containers_id']
                ?? $this->fields['plugin_auchanassettracker_containers_id']
                ?? 0);
            if ($container_id <= 0) {
                Session::addMessageAfterRedirect(
                    __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        if (isset($input['serial'])) {
            $serial = trim((string) $input['serial']);
            if ($serial !== '' && self::serialExists($serial, (int) $this->getID())) {
                Session::addMessageAfterRedirect(
                    __('Serial number must be unique.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            $input['serial'] = $serial !== '' ? $serial : null;
        }

        $loc = (int) ($input['locations_id'] ?? $this->fields['locations_id'] ?? 0);
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(
                __('You cannot modify equipment from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        PluginAuchanassettrackerAuditlog::record(
            'equipment_receipt',
            self::class,
            (int) $this->getID(),
            sprintf(
                'serial=%s location=%d container=%d',
                $this->fields['serial'] ?? '',
                (int) ($this->fields['locations_id'] ?? 0),
                (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0)
            )
        );
    }

    public function post_updateItem($history = true)
    {
        PluginAuchanassettrackerAuditlog::record(
            'equipment_update',
            self::class,
            (int) $this->getID(),
            json_encode([
                'updates' => array_keys($this->updates ?? []),
                'status'  => $this->fields['status'] ?? '',
            ])
        );
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $is_new = $ID <= 0;
        $status = (string) ($this->fields['status'] ?? self::STATUS_AVAILABLE);

        echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . " *</td><td>";
        PluginAuchanassettrackerEquipmenttype::dropdown([
            'name'  => 'plugin_auchanassettracker_equipmenttypes_id',
            'value' => (int) ($this->fields['plugin_auchanassettracker_equipmenttypes_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td><td>" . __('Manufacturer', 'auchanassettracker') . " *</td><td>";
        PluginAuchanassettrackerManufacturer::dropdown([
            'name'  => 'plugin_auchanassettracker_manufacturers_id',
            'value' => (int) ($this->fields['plugin_auchanassettracker_manufacturers_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Model') . " *</td><td>";
        echo Html::input('model', ['value' => $this->fields['model'] ?? '', 'required' => true]);
        echo "</td><td>" . __('Serial number') . "</td><td>";
        echo Html::input('serial', ['value' => $this->fields['serial'] ?? '']);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . " *</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
            $loc_for_container = $scope;
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
            ]);
            $loc_for_container = (int) ($this->fields['locations_id'] ?? 0);
        }
        echo "</td><td>" . __('Status') . "</td><td>";
        if ($is_new) {
            echo self::getStatusLabel(self::STATUS_AVAILABLE);
            echo Html::hidden('status', ['value' => self::STATUS_AVAILABLE]);
        } else {
            echo self::getStatusLabel($status);
            if (PluginAuchanassettrackerRighthelper::canChangeFinalStatus() && self::isFinalStatus($status)) {
                echo " — ";
                Dropdown::showFromArray('status', self::getStatuses(), ['value' => $status]);
            }
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker');
        if ($is_new || $status === self::STATUS_AVAILABLE) {
            echo " *";
        }
        echo "</td><td>";
        $container_condition = ['is_deleted' => 0, 'is_active' => 1];
        if ($loc_for_container > 0) {
            $container_condition['locations_id'] = $loc_for_container;
        }
        PluginAuchanassettrackerContainer::dropdown([
            'name'      => 'plugin_auchanassettracker_containers_id',
            'value'     => (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0),
            'condition' => $container_condition,
            'comments'  => false,
        ]);
        echo "</td><td>" . __('Allocated user', 'auchanassettracker') . "</td><td>";
        $uid = (int) ($this->fields['users_id'] ?? 0);
        echo $uid > 0 ? getUserName($uid) : '—';
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
        echo "<textarea name='notes' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['notes'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        if (!$is_new && self::isFinalStatus($status)) {
            echo "<tr class='tab_bg_1'><td>" . __('Final reason', 'auchanassettracker') . "</td><td colspan='3'>";
            echo nl2br(Html::entities_deep($this->fields['final_reason'] ?? ''));
            echo "</td></tr>";
        }

        $this->showFormButtons($options);
        return true;
    }

    public static function serialExists(string $serial, int $except_id = 0): bool
    {
        global $DB;
        $where = ['serial' => $serial];
        if ($except_id > 0) {
            $where[] = ['NOT' => ['id' => $except_id]];
        }
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    public static function containerBelongsToLocation(int $container_id, int $locations_id): bool
    {
        $c = new PluginAuchanassettrackerContainer();
        if (!$c->getFromDB($container_id)) {
            return false;
        }
        return (int) ($c->fields['locations_id'] ?? 0) === $locations_id
            && (int) ($c->fields['is_deleted'] ?? 0) === 0
            && (int) ($c->fields['is_active'] ?? 0) === 1;
    }

    /**
     * Bulk create Category B accessories (type + quantity).
     *
     * @return int number created
     */
    public static function bulkAddAccessories(array $input, int $quantity): int
    {
        $quantity = max(0, min(500, $quantity));
        if ($quantity <= 0) {
            return 0;
        }

        $created = 0;
        for ($i = 1; $i <= $quantity; $i++) {
            $row = $input;
            $row['serial'] = $row['serial'] ?? null;
            if (!empty($row['serial'])) {
                $row['serial'] = $row['serial'] . '-' . $i;
            }
            $eq = new self();
            if ($eq->add($row)) {
                $created++;
            }
        }
        return $created;
    }

    public function canViewItem(): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        $role = PluginAuchanassettrackerRighthelper::getCurrentRole();
        if ($role === PluginAuchanassettrackerRighthelper::ROLE_USER) {
            return (int) ($this->fields['users_id'] ?? 0) === (int) Session::getLoginUserID();
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canUpdateItem(): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::canAllocate()) {
            return false;
        }
        return PluginAuchanassettrackerRighthelper::canAccessLocation(
            (int) ($this->fields['locations_id'] ?? 0)
        );
    }

    public function canCreateItem(): bool
    {
        return PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, CREATE));
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canUpdate(): bool
    {
        return Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::isSupportTech()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, UPDATE));
    }

    public function delete(array $input, $force = 0, $history = 1)
    {
        $input['is_deleted'] = 1;
        return $this->update($input);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findByStatus(string $status, ?int $locations_id = null, ?int $users_id = null): array
    {
        global $DB;
        $where = [
            'status'     => $status,
            'is_deleted' => 0,
        ];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        if ($users_id !== null) {
            $where['users_id'] = $users_id;
        }
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_mod DESC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function countByStatus(?int $locations_id = null): array
    {
        global $DB;
        $counts = [];
        foreach (array_keys(self::getStatuses()) as $st) {
            $counts[$st] = 0;
        }
        $where = ['is_deleted' => 0];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
        ]) as $row) {
            $st = (string) ($row['status'] ?? '');
            if (!isset($counts[$st])) {
                $counts[$st] = 0;
            }
            $counts[$st]++;
        }
        return $counts;
    }

    public static function listInContainer(int $container_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_auchanassettracker_containers_id' => $container_id,
                'status'     => self::STATUS_AVAILABLE,
                'is_deleted' => 0,
            ],
            'ORDER' => 'plugin_auchanassettracker_equipmenttypes_id ASC, model ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Mark as written off / lost / stolen.
     */
    public function markFinal(string $status, string $reason, string $document = ''): bool
    {
        if (!in_array($status, self::getFinalStatuses(), true)) {
            return false;
        }
        if (!PluginAuchanassettrackerRighthelper::canWriteOff()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return false;
        }

        $current = (string) ($this->fields['status'] ?? '');
        if (in_array($current, [
            self::STATUS_IN_TRANSIT,
            self::STATUS_AWAITING_VALIDATION,
        ], true) || self::isFinalStatus($current)) {
            return false;
        }

        $reason = trim($reason);
        if ($reason === '') {
            Session::addMessageAfterRedirect(
                __('A reason is mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $ok = $this->update([
            'id'       => $this->getID(),
            'status'   => $status,
            'plugin_auchanassettracker_containers_id' => 0,
            'final_reason'   => $reason,
            'final_document' => $document,
            'final_users_id' => (int) Session::getLoginUserID(),
            'final_date'     => $now,
        ]);

        if ($ok) {
            PluginAuchanassettrackerAuditlog::record(
                'equipment_final_' . $status,
                self::class,
                (int) $this->getID(),
                $reason
            );
        }
        return $ok;
    }

    /**
     * Central admin reintroduces equipment into stock.
     */
    public function reintroduceToStock(int $container_id): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canChangeFinalStatus()) {
            return false;
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        if (!self::containerBelongsToLocation($container_id, $loc)) {
            Session::addMessageAfterRedirect(
                __('Selected container does not belong to this location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $ok = $this->update([
            'id'     => $this->getID(),
            'status' => self::STATUS_AVAILABLE,
            'plugin_auchanassettracker_containers_id' => $container_id,
            'users_id' => 0,
            'final_reason' => null,
            'final_document' => null,
            'final_users_id' => 0,
            'final_date' => null,
        ]);

        if ($ok) {
            PluginAuchanassettrackerAuditlog::record(
                'equipment_reintroduce',
                self::class,
                (int) $this->getID(),
                'container=' . $container_id
            );
        }
        return $ok;
    }
}
