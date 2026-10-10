<?php

/**
 * Physical container (shelf / box).
 */
class PluginAuchanassettrackerContainer extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return _n('Physical container', 'Physical containers', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_containers';
    }

    public static function getIcon(): string
    {
        return 'ti ti-box';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];

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
            'id'       => 3,
            'table'    => 'glpi_locations',
            'field'    => 'completename',
            'name'     => __('Location'),
            'datatype' => 'dropdown',
            'linkfield'=> 'locations_id',
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

        $input['is_active'] = isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

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
        $this->showFormHeader($options);

        $canedit = $this->canUpdateItem();
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

        echo "<tr class='tab_bg_1'><td>" . __('Name') . " *</td><td>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'required' => true]);
        echo "</td><td>" . __('Container code', 'auchanassettracker') . "</td><td>";
        echo Html::input('code', [
            'value'    => $this->fields['code'] ?? '',
            'readonly' => $ID > 0,
            'placeholder' => __('Auto-generated if empty', 'auchanassettracker'),
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . " *</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
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

        $code = sprintf('%s-%s%d', $prefix, chr(64 + min(26, $n)), $n);
        // Ensure uniqueness
        $try = 0;
        while (self::codeExists($code) && $try < 50) {
            $n++;
            $code = sprintf('%s-%s%d', $prefix, chr(64 + min(26, (($n - 1) % 26) + 1)), $n);
            $try++;
        }

        return $code;
    }

    public static function codeExists(string $code): bool
    {
        global $DB;
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['code' => $code],
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    public static function countAtLocation(int $locations_id): int
    {
        global $DB;
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => self::getTable(),
            'WHERE' => [
                'locations_id' => $locations_id,
                'is_deleted'   => 0,
                'is_active'    => 1,
            ],
        ]) as $row) {
            return (int) ($row['cpt'] ?? 0);
        }
        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForLocation(int $locations_id): array
    {
        global $DB;
        $rows = [];
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
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, CREATE));
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, UPDATE));
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

    public function canViewItem(): bool
    {
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    /**
     * Soft-deactivate instead of hard delete.
     */
    public function delete(array $input, $force = 0, $history = 1)
    {
        $input['is_deleted'] = 1;
        $input['is_active'] = 0;
        return $this->update($input);
    }
}
