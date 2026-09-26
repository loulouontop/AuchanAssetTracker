<?php

/**
 * Tracked equipment (Sprint 2: stock → allocation → user confirm).
 */
class PluginAuchanassettrackerEquipment extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_AVAILABLE           = 'available';
    public const STATUS_AWAITING_VALIDATION = 'awaiting_validation';
    public const STATUS_ALLOCATED           = 'allocated';

    /** Skip native item_add/update hooks while we create the linked GLPI asset. */
    private static bool $suppress_native_hook = false;

    public static function suppressNativeHook(bool $on): void
    {
        self::$suppress_native_hook = $on;
    }

    public static function isNativeHookSuppressed(): bool
    {
        return self::$suppress_native_hook;
    }

    public static function getTypeName($nb = 0): string
    {
        if ((int) $nb === 1) {
            return __('Equipment', 'auchanassettracker');
        }
        return __('Equipments', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipments';
    }

    public static function getIcon(): string
    {
        return 'ti ti-device-desktop';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_EQUIPMENT];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.form.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE           => __('Available', 'auchanassettracker'),
            self::STATUS_AWAITING_VALIDATION => __('Awaiting validation', 'auchanassettracker'),
            self::STATUS_ALLOCATED           => __('Allocated', 'auchanassettracker'),
        ];
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
        return $ong;
    }

    /**
     * Tab on Physical container: equipment currently in this shelf / box.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof PluginAuchanassettrackerContainer || (int) $item->getID() <= 0) {
            return '';
        }
        if (!$item->can($item->getID(), READ)) {
            return '';
        }

        $nb = 0;
        if ($_SESSION['glpishow_count_on_tabs'] ?? true) {
            $nb = countElementsInTable(self::getTable(), [
                'plugin_auchanassettracker_containers_id' => (int) $item->getID(),
                'is_deleted'                              => 0,
            ]);
        }

        return self::createTabEntry(
            __('Contents', 'auchanassettracker'),
            $nb,
            $item->getType(),
            self::getIcon()
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof PluginAuchanassettrackerContainer) {
            return self::showForContainer($item);
        }
        return false;
    }

    /**
     * GLPI search table of equipment stored in a physical container.
     */
    public static function showForContainer(PluginAuchanassettrackerContainer $container): bool
    {
        $id = (int) $container->getID();
        if ($id <= 0 || !$container->can($id, READ)) {
            return false;
        }

        echo "<div class='spaced aat-container-contents'>";
        echo "<div class='center mb-2'>";
        echo "<h3 class='mb-0'>"
            . Html::entities_deep(__('Equipment in this container', 'auchanassettracker'))
            . '</h3>';
        echo "<p class='text-muted mb-0'>"
            . Html::entities_deep(__(
                'Stock currently assigned to this shelf / box. Use search, columns and actions like elsewhere in GLPI.',
                'auchanassettracker'
            ))
            . '</p>';
        echo '</div>';

        // Search option id 7 = Physical container (dropdown / FK).
        $params = [
            'reset'              => 'reset',
            'usesession'         => false,
            'is_deleted'         => 0,
            'sort'               => 1,
            'order'              => 'ASC',
            'showmassiveactions' => true,
            'criteria'           => [
                [
                    'link'       => 'AND',
                    'field'      => 7,
                    'searchtype' => 'equals',
                    'value'      => $id,
                ],
            ],
        ];

        if (class_exists(\Glpi\Search\SearchEngine::class)
            && method_exists(\Glpi\Search\SearchEngine::class, 'showList')
        ) {
            \Glpi\Search\SearchEngine::showList(self::class, $params);
        } else {
            Search::showList(self::class, $params);
        }

        echo '</div>';
        return true;
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
            'id'         => 4,
            'table'      => self::getTable(),
            'field'      => 'status',
            'name'       => __('Status'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'            => 5,
            'table'         => 'glpi_locations',
            'field'         => 'completename',
            'name'          => __('Location'),
            'datatype'      => 'itemlink',
            'itemlink_type' => 'Location',
            'linkfield'     => 'locations_id',
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
            'table'     => self::getTable(),
            'field'     => 'itemtype',
            'name'      => __('Equipment type', 'auchanassettracker'),
            'datatype'  => 'itemtypename',
            'itemtype_list' => 'asset_types',
        ];
        $tab[] = [
            'id'        => 9,
            'table'     => 'glpi_manufacturers',
            'field'     => 'name',
            'name'      => __('Manufacturer'),
            'datatype'  => 'dropdown',
            'linkfield' => 'manufacturers_id',
        ];
        $tab[] = [
            'id'        => 10,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('User'),
            'datatype'  => 'dropdown',
            'linkfield' => 'users_id',
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
        $from_glpi = !empty($input['_aat_from_glpi']);
        unset($input['_aat_from_glpi']);

        if (!$from_glpi
            && !PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null && !$from_glpi) {
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

        $itemtype  = (string) ($input['itemtype'] ?? '');
        $mfr_id    = (int) ($input['manufacturers_id'] ?? 0);
        $models_id = (int) ($input['models_id'] ?? 0);
        $serial    = trim((string) ($input['serial'] ?? ''));

        if ($itemtype === '' || !self::isAllowedAssetType($itemtype)) {
            Session::addMessageAfterRedirect(
                __('Equipment type is mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $model = self::resolveModelName($itemtype, $models_id, (string) ($input['model'] ?? ''));

        // Serial is optional for all types; uniqueness only when a value is provided.
        if (!$from_glpi && $serial !== '' && self::serialExists($serial)) {
            Session::addMessageAfterRedirect(
                __('Serial number must be unique.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // New receipt always Available + mandatory container (unless importing a GLPI asset).
        if (!$from_glpi) {
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

            $input['items_id'] = 0;
            $input['users_id'] = 0;
        } else {
            $container_id = (int) ($input['plugin_auchanassettracker_containers_id'] ?? 0);
            if ($container_id > 0 && !self::containerBelongsToLocation($container_id, $locations_id)) {
                $input['plugin_auchanassettracker_containers_id'] = 0;
            }
            if (!isset($input['status']) || $input['status'] === '') {
                $input['status'] = self::STATUS_AVAILABLE;
            }
            $input['items_id'] = (int) ($input['items_id'] ?? 0);
            $input['users_id'] = (int) ($input['users_id'] ?? 0);
        }

        $input['itemtype'] = $itemtype;
        $input['manufacturers_id'] = $mfr_id;
        $input['models_id'] = $models_id;
        $input['model'] = $model;
        $input['plugin_auchanassettracker_equipmenttypes_id'] = 0;
        $input['plugin_auchanassettracker_manufacturers_id'] = 0;
        $input['serial'] = $serial !== '' ? $serial : null;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

        // Optional display name — never prefix with asset type.
        $input['name'] = trim((string) ($input['name'] ?? ''));

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_creation'] = $now;
        $input['date_mod'] = $now;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $current_status = (string) ($this->fields['status'] ?? '');
        $new_status = isset($input['status']) ? (string) $input['status'] : $current_status;

        // Enforce container when Available — skipped for allocate/reject internal updates.
        $skip_container = !empty($input['_aat_skip_container_check']);
        unset($input['_aat_skip_container_check']);
        if ($new_status === self::STATUS_AVAILABLE && !$skip_container) {
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

        $itemtype = (string) ($input['itemtype'] ?? $this->fields['itemtype'] ?? 'Computer');
        if (isset($input['models_id']) || isset($input['model'])) {
            $models_id = (int) ($input['models_id'] ?? $this->fields['models_id'] ?? 0);
            $model = self::resolveModelName(
                $itemtype,
                $models_id,
                (string) ($input['model'] ?? $this->fields['model'] ?? '')
            );
            $input['models_id'] = $models_id;
            $input['model'] = $model;
        }

        $loc = (int) ($input['locations_id'] ?? $this->fields['locations_id'] ?? 0);
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)) {
            Session::addMessageAfterRedirect(
                __('You cannot modify equipment from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // Preserve workflow status unless the caller explicitly changes it.
        if (!isset($input['status'])) {
            unset($input['status']);
        } else {
            $allowed = array_keys(self::getStatuses());
            if (!in_array((string) $input['status'], $allowed, true)) {
                $input['status'] = $current_status;
            }
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        // Import from an existing GLPI asset — do not create a second native item.
        if ((int) ($this->fields['items_id'] ?? 0) <= 0) {
            $asset_id = self::createLinkedGlpiAsset($this->fields);
            if ($asset_id > 0) {
                global $DB;
                $DB->update(self::getTable(), [
                    'items_id' => $asset_id,
                ], ['id' => (int) $this->getID()]);
                $this->fields['items_id'] = $asset_id;
            }
        }

        PluginAuchanassettrackerAuditlog::record(
            'equipment_receipt',
            self::class,
            (int) $this->getID(),
            sprintf(
                'serial=%s location=%d container=%d itemtype=%s items_id=%d',
                $this->fields['serial'] ?? '',
                (int) ($this->fields['locations_id'] ?? 0),
                (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0),
                (string) ($this->fields['itemtype'] ?? ''),
                (int) ($this->fields['items_id'] ?? 0)
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
        $req = " <span class='aat-required'>*</span>";
        $current_itemtype = (string) ($this->fields['itemtype'] ?? 'Computer');
        if ($current_itemtype === '' || !self::isAllowedAssetType($current_itemtype)) {
            $current_itemtype = 'Computer';
        }

        echo "<tr class='tab_bg_1'><td>" . __('Name') . "</td><td colspan='3'>";
        echo Html::input('name', [
            'value' => $this->fields['name'] ?? '',
            'class' => 'form-control aat-input-sm',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . $req . "</td><td>";
        $type_choices = [];
        foreach (self::getAllowedAssetTypes() as $class) {
            $type_choices[$class] = $class::getTypeName(1);
        }
        Dropdown::showFromArray('itemtype', $type_choices, [
            'value' => $current_itemtype,
            'width' => '220px',
        ]);
        echo "</td><td>" . __('Manufacturer') . "</td><td>";
        Manufacturer::dropdown([
            'name'  => 'manufacturers_id',
            'value' => (int) ($this->fields['manufacturers_id'] ?? 0),
            'width' => '220px',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Model') . "</td><td>";
        $models_id = (int) ($this->fields['models_id'] ?? 0);
        if ($models_id <= 0) {
            $models_id = self::findModelIdByName(
                $current_itemtype,
                (string) ($this->fields['model'] ?? '')
            );
        }
        echo "<span class='aat-model-field'>";
        self::dropdownModel([
            'itemtype' => $current_itemtype,
            'value'    => $models_id,
            'width'    => '220px',
        ]);
        echo "</span>";
        self::scriptSyncItemtypeModel();
        echo "</td><td>" . __('Serial number') . "</td><td>";
        echo Html::input('serial', [
            'value' => $this->fields['serial'] ?? '',
            'class' => 'form-control aat-input-sm',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . $req . "</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
                . "</div>";
            $loc_for_container = $scope;
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
                'width' => '220px',
            ]);
            $loc_for_container = (int) ($this->fields['locations_id'] ?? 0);
        }
        echo "</td><td>" . __('Status') . "</td><td>";
        $is_new = $ID <= 0;
        $status = (string) ($this->fields['status'] ?? self::STATUS_AVAILABLE);
        if ($is_new) {
            echo self::getStatusLabel(self::STATUS_AVAILABLE);
            echo Html::hidden('status', ['value' => self::STATUS_AVAILABLE]);
        } else {
            echo self::getStatusLabel($status);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker');
        if ($is_new || $status === self::STATUS_AVAILABLE) {
            echo $req;
        }
        echo "</td><td>";
        $container_condition = ['is_deleted' => 0];
        $container_value = (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0);

        if ($loc_for_container > 0) {
            $container_condition['locations_id'] = $loc_for_container;
            if ($container_value > 0) {
                $tmp = new PluginAuchanassettrackerContainer();
                if (!$tmp->getFromDB($container_value)
                    || (int) ($tmp->fields['locations_id'] ?? 0) !== $loc_for_container
                    || (int) ($tmp->fields['is_deleted'] ?? 0) === 1) {
                    $container_value = 0;
                }
            }
        } else {
            // No location selected → no containers until a location is chosen.
            $container_condition['locations_id'] = -1;
            $container_value = 0;
        }

        echo "<span class='aat-container-field'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'value'         => $container_value,
            'condition'     => $container_condition,
            'width'         => '280px',
            // Central admin: reload native dropdown when Location changes.
            'sync_location' => ($scope === null),
        ]);
        echo "</span>";
        echo "</td><td colspan='2'></td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
        echo "<textarea name='notes' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['notes'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    /**
     * @return list<class-string>
     */
    public static function getAllowedAssetTypes(): array
    {
        global $CFG_GLPI;

        $types = $CFG_GLPI['asset_types'] ?? ['Computer'];
        $out = [];
        foreach ($types as $type) {
            if (is_string($type) && $type !== '' && class_exists($type)) {
                $out[] = $type;
            }
        }
        return $out !== [] ? $out : ['Computer'];
    }

    public static function isAllowedAssetType(string $itemtype): bool
    {
        return in_array($itemtype, self::getAllowedAssetTypes(), true);
    }

    /**
     * Native GLPI model class for an asset itemtype (ComputerModel, …).
     *
     * @return class-string<CommonDropdown>|null
     */
    public static function getModelClassForItemtype(string $itemtype): ?string
    {
        if ($itemtype === '' || !class_exists($itemtype)) {
            return null;
        }

        if (method_exists($itemtype, 'getDefinition')) {
            try {
                $def = $itemtype::getDefinition();
                if (is_object($def) && method_exists($def, 'getAssetModelClassName')) {
                    $cls = $def->getAssetModelClassName();
                    if (is_string($cls) && $cls !== '' && class_exists($cls)) {
                        return $cls;
                    }
                }
            } catch (Throwable) {
                // Fall through to ClassNameModel convention.
            }
        }

        $candidate = $itemtype . 'Model';
        if (class_exists($candidate) && is_a($candidate, CommonDropdown::class, true)) {
            return $candidate;
        }

        return null;
    }

    /**
     * Resolve posted models_id (or legacy free-text) to a display name.
     */
    public static function resolveModelName(string $itemtype, int $models_id, string $fallback = ''): string
    {
        if ($models_id > 0) {
            $model_class = self::getModelClassForItemtype($itemtype);
            if ($model_class !== null) {
                $name = Dropdown::getDropdownName($model_class::getTable(), $models_id);
                if (is_string($name) && $name !== '' && $name !== '&nbsp;') {
                    return $name;
                }
            }
        }
        return trim($fallback);
    }

    public static function findModelIdByName(string $itemtype, string $name): int
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $model_class = self::getModelClassForItemtype($itemtype);
        if ($model_class === null) {
            return 0;
        }

        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => $model_class::getTable(),
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1,
        ]) as $row) {
            return (int) ($row['id'] ?? 0);
        }
        return 0;
    }

    /**
     * Render native GLPI model dropdown for the given asset itemtype.
     *
     * @param array<string, mixed> $options
     */
    public static function dropdownModel(array $options = []): void
    {
        $itemtype = (string) ($options['itemtype'] ?? 'Computer');
        $value = (int) ($options['value'] ?? 0);
        $width = (string) ($options['width'] ?? '220px');
        $rand = (int) ($options['rand'] ?? mt_rand());

        $model_class = self::getModelClassForItemtype($itemtype);
        if ($model_class === null) {
            echo "<span class='text-muted'>"
                . __('No model list for this type.', 'auchanassettracker')
                . "</span>";
            echo Html::hidden('models_id', ['value' => 0]);
            return;
        }

        if ($value > 0) {
            $tmp = new $model_class();
            if (!$tmp->getFromDB($value)) {
                $value = 0;
            }
        }

        $model_class::dropdown([
            'name'  => 'models_id',
            'value' => $value,
            'rand'  => $rand,
            'width' => $width,
            'display_emptychoice' => true,
        ]);
    }

    /**
     * When equipment type changes, reload the native model dropdown.
     */
    public static function scriptSyncItemtypeModel(): void
    {
        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/models.php',
            JSON_UNESCAPED_SLASHES
        );

        echo Html::scriptBlock(<<<JS
$(function () {
   var \$field = $('.aat-model-field').first();
   if (!\$field.length) {
      return;
   }

   function reloadForType(itemtype) {
      itemtype = itemtype || 'Computer';
      $.ajax({
         url: {$ajax},
         data: {
            itemtype: itemtype,
            value: 0
         },
         dataType: 'html'
      }).done(function (html) {
         \$field.html(html);
      });
   }

   $(document).off('change.aatModel sync.aatModel')
      .on('change.aatModel', 'select[name="itemtype"]', function () {
         reloadForType($(this).val());
      })
      .on('select2:select.aatModel select2:clear.aatModel', 'select[name="itemtype"]', function () {
         reloadForType($(this).val());
      });
});
JS);
    }

    /**
     * Serial number is optional for every equipment type.
     */
    public static function itemtypeRequiresSerial(string $itemtype): bool
    {
        return false;
    }

    /**
     * Create the matching GLPI asset (Computer, custom asset, …).
     *
     * @param array<string, mixed> $fields
     */
    public static function createLinkedGlpiAsset(array $fields): int
    {
        $itemtype = (string) ($fields['itemtype'] ?? '');
        if ($itemtype === '' || !class_exists($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
            return 0;
        }

        /** @var CommonDBTM $asset */
        $asset = new $itemtype();
        if (!$asset::canCreate()) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Stock saved, but the GLPI %s could not be created (missing rights).', 'auchanassettracker'),
                    $itemtype::getTypeName(1)
                ),
                false,
                WARNING
            );
            return 0;
        }

        $input = [
            'name'             => self::resolveAssetName($fields),
            'serial'           => $fields['serial'] ?? '',
            'locations_id'     => (int) ($fields['locations_id'] ?? 0),
            'manufacturers_id' => (int) ($fields['manufacturers_id'] ?? 0),
            'entities_id'      => (int) ($fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0)),
            'comment'          => trim((string) ($fields['notes'] ?? '')),
            'is_deleted'       => 0,
            'is_template'      => 0,
        ];

        $models_id = (int) ($fields['models_id'] ?? 0);
        $model_class = self::getModelClassForItemtype($itemtype);
        if ($models_id > 0 && $model_class !== null) {
            $input[$model_class::getForeignKeyField()] = $models_id;
        }

        // Avoid item_add → ensureFromGlpiAsset creating a second plugin row (duplicate serial).
        self::suppressNativeHook(true);
        try {
            $new_id = $asset->add($input);
        } finally {
            self::suppressNativeHook(false);
        }

        if (!$new_id) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Stock saved, but creating the GLPI %s failed.', 'auchanassettracker'),
                    $itemtype::getTypeName(1)
                ),
                false,
                WARNING
            );
            return 0;
        }

        return (int) $new_id;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function resolveAssetName(array $fields): string
    {
        $name = trim((string) ($fields['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $serial = trim((string) ($fields['serial'] ?? ''));
        if ($serial !== '') {
            return $serial;
        }
        $model = trim((string) ($fields['model'] ?? ''));
        return $model !== '' ? $model : __('Equipment', 'auchanassettracker');
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
        // Allow inactive shelves for assignment; only block deleted / wrong location.
        return (int) ($c->fields['locations_id'] ?? 0) === $locations_id
            && (int) ($c->fields['is_deleted'] ?? 0) === 0;
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
        $role = PluginAuchanassettrackerRighthelper::getCurrentRole();
        if ($role === PluginAuchanassettrackerRighthelper::ROLE_USER) {
            return (int) ($this->fields['users_id'] ?? 0) === (int) Session::getLoginUserID();
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canUpdateItem(): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::canAllocate()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
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
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::canAllocate()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public function delete(array $input, $force = 0, $history = 1)
    {
        if ($force) {
            return parent::delete($input, $force, $history);
        }

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
     * Available stock missing a container after a reject (previous shelf gone).
     *
     * @return list<array<string, mixed>>
     */
    public static function findNeedsContainer(?int $locations_id = null): array
    {
        global $DB;
        $where = [
            'status'     => self::STATUS_AVAILABLE,
            'is_deleted' => 0,
            'plugin_auchanassettracker_containers_id' => 0,
        ];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_mod DESC',
        ]) as $row) {
            // Only surface items that were rejected (not random empty-container stock).
            $eid = (int) ($row['id'] ?? 0);
            $rejected = false;
            foreach ($DB->request([
                'FROM'  => PluginAuchanassettrackerAllocation::getTable(),
                'WHERE' => [
                    'plugin_auchanassettracker_equipments_id' => $eid,
                    'allocation_status' => PluginAuchanassettrackerAllocation::STATUS_REJECTED,
                ],
                'LIMIT' => 1,
            ]) as $_) {
                $rejected = true;
            }
            if (!$rejected) {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Set / clear users_id on the linked native GLPI asset.
     *
     * @param array<string, mixed> $fields
     */
    public static function syncGlpiAssetOwner(array $fields, int $users_id): void
    {
        $itemtype = (string) ($fields['itemtype'] ?? '');
        $items_id = (int) ($fields['items_id'] ?? 0);
        if ($itemtype === '' || $items_id <= 0 || !class_exists($itemtype)) {
            return;
        }
        if (!is_a($itemtype, CommonDBTM::class, true)) {
            return;
        }

        try {
            /** @var CommonDBTM $asset */
            $asset = new $itemtype();
            if (!$asset->getFromDB($items_id)) {
                return;
            }
            if (!$asset->isField('users_id')) {
                return;
            }
            $next = max(0, $users_id);
            if ((int) ($asset->fields['users_id'] ?? 0) === $next) {
                return;
            }
            // Silent DB write — avoid GLPI “User or group updated / connected items…” noise.
            global $DB;
            $DB->update($asset::getTable(), [
                'users_id' => $next,
            ], ['id' => $items_id]);
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'syncGlpiAssetOwner');
        }
    }

    /**
     * Plugin row linked to a native GLPI asset, if any.
     * Prefers the row that already has a physical container.
     *
     * @return array<string, mixed>|null
     */
    public static function findByGlpiAsset(string $itemtype, int $items_id): ?array
    {
        global $DB;

        if ($itemtype === '' || $items_id <= 0 || !$DB->tableExists(self::getTable())) {
            return null;
        }

        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'itemtype'   => $itemtype,
                'items_id'   => $items_id,
                'is_deleted' => 0,
            ],
            'ORDER' => 'plugin_auchanassettracker_containers_id DESC, id DESC',
            'LIMIT' => 1,
        ]) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Plugin row created from the plugin form before items_id was linked.
     *
     * @return array<string, mixed>|null
     */
    public static function findOrphanBySerial(string $itemtype, string $serial): ?array
    {
        global $DB;

        $serial = trim($serial);
        if ($itemtype === '' || $serial === '' || !$DB->tableExists(self::getTable())) {
            return null;
        }

        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'itemtype'   => $itemtype,
                'serial'     => $serial,
                'items_id'   => 0,
                'is_deleted' => 0,
            ],
            'ORDER' => 'id DESC',
            'LIMIT' => 1,
        ]) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Ensure a plugin equipment row exists for a native GLPI asset (import / sync).
     *
     * @return int plugin equipment id (0 on failure)
     */
    public static function ensureFromGlpiAsset(
        string $itemtype,
        int $items_id,
        ?int $container_id = null
    ): int {
        global $DB;

        if (!self::isAllowedAssetType($itemtype) || $items_id <= 0 || !class_exists($itemtype)) {
            return 0;
        }
        if (!is_a($itemtype, CommonDBTM::class, true)) {
            return 0;
        }

        /** @var CommonDBTM $asset */
        $asset = new $itemtype();
        if (!$asset->getFromDB($items_id)) {
            return 0;
        }

        $locations_id = (int) ($asset->fields['locations_id'] ?? 0);
        // Silent skip — never flash “Location is required” during list sync.
        if ($locations_id <= 0) {
            return 0;
        }
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            return 0;
        }

        $users_id = (int) ($asset->fields['users_id'] ?? 0);
        $name = trim((string) ($asset->fields['name'] ?? ''));
        $serial = trim((string) ($asset->fields['serial'] ?? ''));
        $mfr_id = (int) ($asset->fields['manufacturers_id'] ?? 0);
        $entities_id = (int) ($asset->fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));

        $models_id = 0;
        $model_class = self::getModelClassForItemtype($itemtype);
        if ($model_class !== null) {
            $fk = $model_class::getForeignKeyField();
            $models_id = (int) ($asset->fields[$fk] ?? 0);
        }
        $model = self::resolveModelName($itemtype, $models_id, '');

        $existing = self::findByGlpiAsset($itemtype, $items_id);
        // Plugin form just created the row; items_id not linked yet → reuse it.
        if ($existing === null && $serial !== '') {
            $existing = self::findOrphanBySerial($itemtype, $serial);
        }
        // Never insert a second row for the same serial.
        if ($existing === null && $serial !== '') {
            foreach ($DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'serial'     => $serial,
                    'is_deleted' => 0,
                ],
                'ORDER' => 'plugin_auchanassettracker_containers_id DESC, id DESC',
                'LIMIT' => 1,
            ]) as $row) {
                $existing = $row;
            }
        }

        $eq = new self();

        if ($existing !== null) {
            $id = (int) $existing['id'];
            if (!$eq->getFromDB($id)) {
                return 0;
            }

            $status = (string) ($existing['status'] ?? self::STATUS_AVAILABLE);
            if ($status !== self::STATUS_AWAITING_VALIDATION) {
                $status = $users_id > 0 ? self::STATUS_ALLOCATED : self::STATUS_AVAILABLE;
            }

            // Keep an already-saved container unless the form posts a new value.
            $kept_container = (int) ($existing['plugin_auchanassettracker_containers_id'] ?? 0);
            $next_container = $kept_container;
            if ($container_id !== null) {
                if ($container_id > 0
                    && $locations_id > 0
                    && !self::containerBelongsToLocation($container_id, $locations_id)) {
                    $container_id = 0;
                }
                $next_container = max(0, $container_id);
            }

            $next_name = $name !== '' ? $name : (string) ($existing['name'] ?? '');
            $next_serial = $serial !== '' ? $serial : null;

            $fields = [
                'name'             => $next_name,
                'serial'           => $next_serial,
                'itemtype'         => $itemtype,
                'items_id'         => $items_id,
                'locations_id'     => $locations_id,
                'manufacturers_id' => $mfr_id,
                'models_id'        => $models_id,
                'model'            => $model,
                'users_id'         => $users_id,
                'status'           => $status,
                'entities_id'      => $entities_id,
                'plugin_auchanassettracker_containers_id' => $next_container,
            ];

            $changed = false;
            foreach ($fields as $key => $val) {
                $old = $existing[$key] ?? null;
                if ((string) ($old ?? '') !== (string) ($val ?? '')) {
                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                $fields['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
                // Silent write — no CommonDBTM success / validation flash for background sync.
                $DB->update(self::getTable(), $fields, ['id' => $id]);
            }
            return $id;
        }

        $status = $users_id > 0 ? self::STATUS_ALLOCATED : self::STATUS_AVAILABLE;
        $cid = $container_id !== null ? max(0, $container_id) : 0;
        if ($cid > 0 && $locations_id > 0 && !self::containerBelongsToLocation($cid, $locations_id)) {
            $cid = 0;
        }

        // Background import: suppress “Item successfully added” noise from CommonDBTM.
        $new_id = $eq->add([
            'name'             => $name,
            'serial'           => $serial,
            'itemtype'         => $itemtype,
            'items_id'         => $items_id,
            'locations_id'     => $locations_id,
            'manufacturers_id' => $mfr_id,
            'models_id'        => $models_id,
            'model'            => $model,
            'users_id'         => $users_id,
            'status'           => $status,
            'entities_id'      => $entities_id,
            'plugin_auchanassettracker_containers_id' => $cid,
            '_aat_from_glpi'   => 1,
            '_no_message'      => true,
            '_disablenotif'    => true,
        ]);

        return $new_id ? (int) $new_id : 0;
    }

    /**
     * Import native GLPI assets (Global / All assets) into the Equipment list.
     *
     * @return int number of rows created or refreshed
     */
    public static function syncVisibleGlpiAssets(?int $locations_id = null, int $limit = 400): int
    {
        global $DB, $CFG_GLPI;

        if (!$DB->tableExists(self::getTable())) {
            return 0;
        }

        $done = 0;
        $types = $CFG_GLPI['asset_types'] ?? ['Computer'];
        foreach ($types as $itemtype) {
            if ($done >= $limit) {
                break;
            }
            if (!is_string($itemtype) || $itemtype === '' || !class_exists($itemtype)) {
                continue;
            }
            if (!is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            /** @var CommonDBTM $probe */
            $probe = new $itemtype();
            $table = $probe::getTable();
            if (!$DB->tableExists($table)) {
                continue;
            }

            if (!$DB->fieldExists($table, 'locations_id')) {
                continue;
            }

            $where = [
                // Only assets with a location (import requires it).
                'locations_id' => ['>', 0],
            ];
            if ($DB->fieldExists($table, 'is_deleted')) {
                $where['is_deleted'] = 0;
            }
            if ($DB->fieldExists($table, 'is_template')) {
                $where['is_template'] = 0;
            }
            if ($locations_id !== null) {
                if ($locations_id <= 0) {
                    continue;
                }
                $where['locations_id'] = $locations_id;
            }

            $remaining = $limit - $done;

            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => $table,
                'WHERE'  => $where,
                'LIMIT'  => max(1, $remaining),
            ]) as $row) {
                $iid = (int) ($row['id'] ?? 0);
                if ($iid <= 0) {
                    continue;
                }
                if (self::ensureFromGlpiAsset($itemtype, $iid) > 0) {
                    $done++;
                }
                if ($done >= $limit) {
                    break;
                }
            }
        }

        return $done;
    }

    /**
     * Native GLPI assets where the user is the owner (users_id).
     *
     * @return list<array<string, mixed>>
     */
    public static function findGlpiAssetsForUser(int $users_id): array
    {
        global $DB, $CFG_GLPI;

        if ($users_id <= 0) {
            return [];
        }

        $rows = [];
        $types = $CFG_GLPI['asset_types'] ?? ['Computer'];
        foreach ($types as $itemtype) {
            if (!is_string($itemtype) || $itemtype === '' || !class_exists($itemtype)) {
                continue;
            }
            if (!is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }
            /** @var CommonDBTM $probe */
            $probe = new $itemtype();
            $table = $probe::getTable();
            if (!$DB->tableExists($table) || !$DB->fieldExists($table, 'users_id')) {
                continue;
            }

            $select = ['id', 'name', 'serial', 'users_id'];
            foreach (['manufacturers_id', 'is_deleted'] as $col) {
                if ($DB->fieldExists($table, $col)) {
                    $select[] = $col;
                }
            }

            $where = ['users_id' => $users_id];
            if ($DB->fieldExists($table, 'is_deleted')) {
                $where['is_deleted'] = 0;
            }

            foreach ($DB->request([
                'SELECT' => $select,
                'FROM'   => $table,
                'WHERE'  => $where,
                'LIMIT'  => 100,
            ]) as $row) {
                $rows[] = [
                    'id'       => 0,
                    'items_id' => (int) ($row['id'] ?? 0),
                    'itemtype' => $itemtype,
                    'name'     => (string) ($row['name'] ?? ''),
                    'serial'   => (string) ($row['serial'] ?? ''),
                    'status'   => 'glpi',
                    'model'    => '',
                    'users_id' => $users_id,
                ];
            }
        }
        return $rows;
    }
}
