<?php

/**
 * Physical container (shelf / box) for stock.
 */
class PluginAuchanassettrackerContainer extends CommonDropdown
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        if ((int) $nb === 1) {
            return __('Physical container', 'auchanassettracker');
        }
        return __('Physical containers', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_containers';
    }

    public static function getIcon(): string
    {
        return 'ti ti-box';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_CONTAINER];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/container.form.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/container.php';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'code',
                'label' => __('Container code', 'auchanassettracker'),
                'type'  => 'text',
                'list'  => true,
            ],
            [
                'name'  => 'locations_id',
                'label' => __('Location'),
                'type'  => 'dropdownValue',
                'list'  => true,
            ],
            [
                'name'  => 'is_active',
                'label' => __('Active'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'  => 'description',
                'label' => __('Description'),
                'type'  => 'textarea',
                'list'  => false,
            ],
        ];
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        // GLPI search table of equipment currently stored in this container.
        $this->addStandardTab('PluginAuchanassettrackerEquipment', $ong, $options);
        return $ong;
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
            'field'    => 'code',
            'name'     => __('Container code', 'auchanassettracker'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'            => 3,
            'table'         => 'glpi_locations',
            'field'         => 'completename',
            'name'          => __('Location'),
            'datatype'      => 'itemlink',
            'itemlink_type' => 'Location',
            'linkfield'     => 'locations_id',
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id'       => 5,
            'table'    => self::getTable(),
            'field'    => 'description',
            'name'     => __('Description'),
            'datatype' => 'text',
        ];

        return $tab;
    }

    public function prepareInputForAdd($input)
    {
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $input['locations_id'] = $scope;
        }

        $locations_id = (int) ($input['locations_id'] ?? 0);
        if ($locations_id <= 0) {
            Session::addMessageAfterRedirect(
                __('Location is required for a container.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot create a container in another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(__('Name is mandatory.'), false, ERROR);
            return false;
        }

        $input['code'] = $input['code'] ?? '';
        if (trim((string) $input['code']) === '') {
            $input['code'] = self::generateCode($locations_id);
        }

        // Heal UNIQUE qr_token from older installs (empty '' collides).
        global $DB;
        if ($DB->fieldExists(self::getTable(), 'qr_token')) {
            $token = trim((string) ($input['qr_token'] ?? ''));
            if ($token === '') {
                $input['qr_token'] = bin2hex(random_bytes(16));
            }
        }

        $input['is_active'] = isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1;
        $input['is_deleted'] = 0;
        $input['entities_id'] = (int) ($input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));
        $input['is_recursive'] = isset($input['is_recursive']) ? (int) (bool) $input['is_recursive'] : 0;

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_creation'] = $now;
        $input['date_mod'] = $now;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['locations_id'])) {
            $loc = (int) $input['locations_id'];
            if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)) {
                Session::addMessageAfterRedirect(
                    __('You cannot move a container to another location.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        // Soft-delete only — never hard delete from UI.
        if (isset($input['is_deleted']) && (int) $input['is_deleted'] === 1) {
            $input['is_active'] = 0;
        }

        if (isset($input['is_recursive'])) {
            $input['is_recursive'] = (int) (bool) $input['is_recursive'];
        }
        if (isset($input['entities_id'])) {
            $input['entities_id'] = (int) $input['entities_id'];
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        PluginAuchanassettrackerAuditlog::record(
            'container_create',
            self::class,
            (int) $this->getID(),
            sprintf('code=%s location=%d', $this->fields['code'] ?? '', (int) ($this->fields['locations_id'] ?? 0))
        );
    }

    public function post_updateItem($history = true)
    {
        PluginAuchanassettrackerAuditlog::record(
            'container_update',
            self::class,
            (int) $this->getID(),
            json_encode(array_keys($this->updates ?? []))
        );
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);

        // GLPI prefixes “New item - ” itself — pass only the type name.
        if ($ID <= 0 && empty($options['formtitle'])) {
            $options['formtitle'] = self::getTypeName(1);
        }

        $this->showFormHeader($options);

        if (!empty($_REQUEST['_in_modal']) || !empty($options['in_modal'])) {
            echo Html::hidden('_in_modal', ['value' => 1]);
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $req = " <span class='aat-required'>*</span>";

        echo "<tr class='tab_bg_1'><td>" . __('Name') . $req . "</td><td>";
        echo Html::input('name', [
            'value'    => $this->fields['name'] ?? '',
            'required' => true,
            'class'    => 'form-control aat-input-sm',
        ]);
        echo "</td><td>" . __('Container code', 'auchanassettracker') . "</td><td>";
        $code_opts = [
            'value' => $this->fields['code'] ?? '',
            'class' => 'form-control aat-input-sm',
        ];
        if ($ID > 0) {
            $code_opts['readonly'] = true;
        } else {
            $code_opts['placeholder'] = __('Auto-generated if empty', 'auchanassettracker');
        }
        echo Html::input('code', $code_opts);
        if ($ID <= 0) {
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Leave empty to auto-generate a code like BUC-A1.', 'auchanassettracker'))
                . "</div>";
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . $req . "</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
                . "</div>";
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
                'width' => '220px',
            ]);
        }
        echo "</td><td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', (int) ($this->fields['is_active'] ?? 1));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Description') . "</td><td colspan='3'>";
        echo "<textarea name='description' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['description'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public static function generateCode(int $locations_id): string
    {
        global $DB;

        $prefix = 'LOC';
        $loc = new Location();
        if ($loc->getFromDB($locations_id)) {
            $name = preg_replace('/[^A-Za-z0-9]/', '', (string) ($loc->fields['name'] ?? 'LOC'));
            $prefix = strtoupper(substr($name !== '' ? $name : 'LOC', 0, 3));
        }

        $n = 1;
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => self::getTable(),
            'WHERE' => ['locations_id' => $locations_id],
        ]) as $row) {
            $n = (int) ($row['cpt'] ?? 0) + 1;
        }

        $code = sprintf('%s-%s', $prefix, $n);
        while ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['code' => $code],
            'LIMIT' => 1,
        ])->count() > 0) {
            $n++;
            $code = sprintf('%s-%s', $prefix, $n);
        }

        return $code;
    }

    /**
     * Options for a static (non-AJAX) container select.
     * Includes inactive shelves so assignment still works; excludes deleted.
     *
     * @return array<int, string>
     */
    public static function dropdownOptionsForLocation(int $locations_id, int $keep_id = 0): array
    {
        $options = [0 => Dropdown::EMPTY_VALUE];
        if ($locations_id <= 0) {
            if ($keep_id > 0) {
                $tmp = new self();
                if ($tmp->getFromDB($keep_id)) {
                    $options[$keep_id] = self::formatOptionLabel($tmp->fields);
                }
            }
            return $options;
        }

        foreach (self::listForLocation($locations_id) as $row) {
            if ((int) ($row['is_deleted'] ?? 0) === 1) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $options[$id] = self::formatOptionLabel($row);
        }

        if ($keep_id > 0 && !isset($options[$keep_id])) {
            $tmp = new self();
            if ($tmp->getFromDB($keep_id)) {
                $options[$keep_id] = self::formatOptionLabel($tmp->fields);
            }
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function formatOptionLabel(array $row): string
    {
        $label = (string) ($row['name'] ?? '');
        $code = trim((string) ($row['code'] ?? ''));
        if ($code !== '') {
            $label .= ' (' . $code . ')';
        }
        if (!(int) ($row['is_active'] ?? 1)) {
            $label .= ' [' . __('Inactive') . ']';
        }
        return $label;
    }

    /**
     * Entity-assignable so form header shows Entity + Child entities (is_recursive).
     * Location still drives stock shelf filtering separately.
     */
    public function isEntityAssign(): bool
    {
        return true;
    }

    /**
     * Native GLPI CommonDropdown (same attached i/+ btn-group as Location).
     *
     * @param array<string, mixed> $options
     */
    public static function dropdownWithActions(array $options = []): void
    {
        $sync_location = !empty($options['sync_location']);
        $plain = !empty($options['plain']); // AJAX fragment: no sync scripts
        unset($options['sync_location'], $options['plain']);

        $options['rand'] = (int) ($options['rand'] ?? mt_rand());
        $options['comments'] = $options['comments'] ?? true;
        $options['addicon'] = $options['addicon'] ?? true;
        $options['name'] = $options['name'] ?? 'plugin_auchanassettracker_containers_id';
        $options['width'] = $options['width'] ?? '220px';

        if (!isset($options['condition']) || !is_array($options['condition'])) {
            $options['condition'] = [];
        }
        // Native AJAX dropdown: never pass locations_id = -1.
        if (isset($options['condition']['locations_id'])
            && (int) $options['condition']['locations_id'] < 0) {
            $options['condition']['locations_id'] = 0;
            $options['condition']['id'] = -1;
        }
        if (!isset($options['condition']['is_deleted'])) {
            $options['condition']['is_deleted'] = 0;
        }

        // Exact same renderer as Location / Manufacturer (btn-group + i + +).
        echo "<span class='aat-container-dropdown'>";
        self::dropdown($options);
        echo '</span>';

        if ($plain) {
            return;
        }

        self::scriptSyncLocationContainers($sync_location);
    }

    /**
     * Reload a fresh native CommonDropdown when Location changes / after + add.
     */
    public static function scriptSyncLocationContainers(bool $bind_location = true): void
    {
        static $done = false;
        if ($done) {
            if ($bind_location) {
                echo Html::scriptBlock(<<<'JS'
$(function () {
  if (window.aatPluginContainerLocBound) { return; }
  window.aatPluginContainerLocBound = true;
  // Only bind change (not select2:select too) — Select2 fires both and caused duplicate menus.
  $(document)
    .off('change.aatLoc select2:select.aatLoc select2:clear.aatLoc')
    .on('change.aatLoc', 'select[name="locations_id"]', function () {
      if (typeof window.aatRefreshContainerDropdown !== 'function') { return; }
      var loc = $(this).val();
      clearTimeout(window.aatContainerLocTimer);
      window.aatContainerLocTimer = setTimeout(function () {
        window.aatRefreshContainerDropdown(loc, false);
      }, 120);
    });
});
JS);
            }
            return;
        }
        $done = true;

        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/containers.php',
            JSON_UNESCAPED_SLASHES
        );
        $bind_js = $bind_location ? 'true' : 'false';

        $js = str_replace(
            ['__AJAX__', '__BIND__'],
            [$ajax, $bind_js],
            <<<'JS'
$(function () {
  function aatContainerField() {
    var $f = $('.aat-native-container-field .aat-container-field').first();
    if (!$f.length) {
      $f = $('.aat-container-field').first();
    }
    return $f;
  }

  function aatDestroyContainerSelect2($root) {
    if (!$root || !$root.length) {
      return;
    }
    // Only tear down Select2 inside the container field — never wipe body menus
    // (that was freezing the Location dropdown on the previous value).
    $root.find('select').each(function () {
      var $s = $(this);
      if ($s.hasClass('select2-hidden-accessible')) {
        try { $s.select2('close'); } catch (e) {}
        try { $s.select2('destroy'); } catch (e2) {}
      }
    });
    $root.find('.select2-container').remove();
  }

  function aatRunScripts($root) {
    $root.find('script').each(function () {
      var code = this.text || this.textContent || '';
      if (code) {
        $.globalEval(code);
      }
    });
  }

  function aatMarkContainerReady($field) {
    if (!$field || !$field.length) {
      return;
    }
    // Drop pending once Select2 has replaced the native <select>.
    if ($field.find('select.select2-hidden-accessible').length
        || !$field.find('select').length) {
      $field.removeClass('aat-container-pending');
      return;
    }
    // GLPI init can be a tick later after globalEval.
    setTimeout(function () {
      $field.removeClass('aat-container-pending');
    }, 50);
  }

  window.aatRefreshContainerDropdown = function (locId, keepValue, preferId) {
    var $field = aatContainerField();
    if (!$field.length) {
      return;
    }
    locId = parseInt(locId, 10) || 0;
    var current = 0;
    if (preferId && parseInt(preferId, 10) > 0) {
      current = parseInt(preferId, 10);
    } else if (keepValue) {
      current = parseInt(
        $field.find('select[name="plugin_auchanassettracker_containers_id"]').val(),
        10
      ) || 0;
    }
    var width = $field.attr('data-aat-width') || '220px';
    window.aatContainerRefreshSeq = (window.aatContainerRefreshSeq || 0) + 1;
    var seq = window.aatContainerRefreshSeq;

    // Hide native ----- menu while Select2 is torn down / rebuilt.
    $field.addClass('aat-container-pending');

    // Tear down current Select2 before replacing HTML (prevents duplicate / top-left menus).
    aatDestroyContainerSelect2($field);

    $.ajax({
      url: __AJAX__,
      data: {
        display: 'dropdown',
        locations_id: locId,
        value: current,
        width: width
      },
      dataType: 'html'
    }).done(function (html) {
      if (seq !== window.aatContainerRefreshSeq) {
        return; // stale response — a newer refresh already started
      }
      aatDestroyContainerSelect2($field);
      var $tmp = $('<div/>').append($.parseHTML(html, document, true));
      $field.empty().append($tmp.contents());
      aatRunScripts($field);
      aatMarkContainerReady($field);
    }).fail(function () {
      if (seq === window.aatContainerRefreshSeq) {
        $field.removeClass('aat-container-pending');
      }
    });
  };

  window.aatRefreshContainerDropdownAfterAdd = function (newId) {
    var loc = $('select[name="locations_id"]').filter(':visible').last().val()
      || $('input[name="locations_id"]').last().val()
      || 0;
    window.aatRefreshContainerDropdown(loc, false, newId);
  };

  // After native + modal closes, refresh list (new shelf appears).
  $(document)
    .off('hidden.bs.modal.aatContainerAdd')
    .on(
      'hidden.bs.modal.aatContainerAdd',
      '[id^="add_dropdown_plugin_auchanassettracker_containers_id"]',
      function () {
        var loc = $('select[name="locations_id"]').filter(':visible').last().val()
          || $('input[name="locations_id"]').last().val()
          || 0;
        window.aatRefreshContainerDropdown(loc, true);
      }
    );

  if (__BIND__) {
    if (!window.aatPluginContainerLocBound) {
      window.aatPluginContainerLocBound = true;
      // Debounce so rapid Location re-picks don't race / stick on the previous value.
      $(document)
        .off('change.aatLoc select2:select.aatLoc select2:clear.aatLoc')
        .on('change.aatLoc', 'select[name="locations_id"]', function () {
          var loc = $(this).val();
          clearTimeout(window.aatContainerLocTimer);
          window.aatContainerLocTimer = setTimeout(function () {
            window.aatRefreshContainerDropdown(loc, false);
          }, 120);
        });
    }
  }
});
JS
        );
        echo Html::scriptBlock($js);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForLocation(int $locations_id): array
    {
        global $DB;
        $rows = [];
        if ($locations_id <= 0 || !$DB->tableExists(self::getTable())) {
            return $rows;
        }
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'locations_id' => $locations_id,
                'is_deleted'   => 0,
            ],
            'ORDER' => 'name ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
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
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::canAllocate()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public function canUpdateItem(): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return false;
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canPurgeItem(): bool
    {
        return $this->canUpdateItem();
    }

    public static function canPurge(): bool
    {
        return self::canUpdate();
    }

    public function canViewItem(): bool
    {
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    /**
     * Soft-delete by default; hard-delete when $force (purge / delete permanently).
     */
    public function delete(array $input, $force = 0, $history = 1)
    {
        if ($force) {
            return parent::delete($input, $force, $history);
        }

        $input['is_deleted'] = 1;
        $input['is_active'] = 0;
        return $this->update($input);
    }
}
