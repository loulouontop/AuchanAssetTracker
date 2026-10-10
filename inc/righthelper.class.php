<?php

/**
 * Rights + location scope helpers.
 *
 * Capabilities come from GLPI ProfileRight bits (Session::haveRight).
 * Optional location scope still lives in glpi_plugin_auchanassettracker_profiles.
 */
class PluginAuchanassettrackerRighthelper
{
    /** @deprecated kept for locale / migration labels only */
    public const ROLE_CENTRAL_ADMIN     = 'central_admin';
    public const ROLE_LOCATION_MANAGER  = 'location_manager';
    public const ROLE_SUPPORT_TECH      = 'support_tech';
    public const ROLE_USER              = 'user';

    public static function getRoles(): array
    {
        return [
            self::ROLE_CENTRAL_ADMIN    => __('Central admin', 'auchanassettracker'),
            self::ROLE_LOCATION_MANAGER => __('Location manager', 'auchanassettracker'),
            self::ROLE_SUPPORT_TECH     => __('Support technician', 'auchanassettracker'),
            self::ROLE_USER             => __('End user', 'auchanassettracker'),
        ];
    }

    /**
     * Any of the given rights bits on a module.
     *
     * @param list<int> $bits
     */
    public static function haveAnyRight(string $module, array $bits): bool
    {
        foreach ($bits as $bit) {
            if (Session::haveRight($module, $bit)) {
                return true;
            }
        }
        return false;
    }

    public static function canSeeEquipment(): bool
    {
        return self::haveAnyRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, [
            READ, READ_ASSIGNED, READ_OWNED,
        ]);
    }

    public static function canSeeContainers(): bool
    {
        return self::haveAnyRight(PluginAuchanassettrackerProfile::RIGHT_CONTAINER, [
            READ, READ_ASSIGNED, READ_OWNED,
        ]);
    }

    public static function canManageStock(): bool
    {
        return Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, CREATE)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_BULK, CREATE);
    }

    public static function canAllocate(): bool
    {
        return self::haveAnyRight(PluginAuchanassettrackerProfile::RIGHT_ALLOCATION, [
            READ, CREATE, UPDATE,
        ]);
    }

    public static function canTransfer(): bool
    {
        return self::haveAnyRight(PluginAuchanassettrackerProfile::RIGHT_TRANSFER, [
            READ, CREATE, UPDATE,
        ]);
    }

    public static function canConfirm(): bool
    {
        return self::haveAnyRight(PluginAuchanassettrackerProfile::RIGHT_CONFIRM, [
            READ, UPDATE, READ_OWNED, UPDATE_OWNED,
        ]);
    }

    public static function canWriteOff(): bool
    {
        return Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE_OWNED)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE_ASSIGNED);
    }

    public static function canChangeFinalStatus(): bool
    {
        // Reintroduce: require Update all (not only owned/assigned).
        return Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE);
    }

    public static function canConfigure(): bool
    {
        return Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_CONFIG, READ)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_CONFIG, UPDATE);
    }

    /**
     * @deprecated use Session::haveRight on specific modules
     */
    public static function isCentralAdmin(): bool
    {
        return self::canConfigure()
            || Session::haveRight('config', UPDATE);
    }

    /**
     * @deprecated
     */
    public static function isLocationManager(): bool
    {
        return self::canManageStock() || self::canWriteOff();
    }

    /**
     * @deprecated
     */
    public static function isSupportTech(): bool
    {
        return self::canAllocate() || self::canTransfer() || self::canManageStock();
    }

    /**
     * @deprecated
     */
    public static function getCurrentRole(): string
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $role = (string) ($mapping['role'] ?? '');
            if ($role !== '' && isset(self::getRoles()[$role])) {
                return $role;
            }
        }
        if (self::canConfigure()) {
            return self::ROLE_CENTRAL_ADMIN;
        }
        if (self::canManageStock()) {
            return self::ROLE_LOCATION_MANAGER;
        }
        if (self::canAllocate() || self::canTransfer()) {
            return self::ROLE_SUPPORT_TECH;
        }
        return self::ROLE_USER;
    }

    public static function hasPluginProfileMap(): bool
    {
        return PluginAuchanassettrackerProfile::getForCurrentProfile() !== null;
    }

    /**
     * Location scope for current user.
     * null = all locations.
     * int  = only that location (0 = none / blocked).
     */
    public static function getScopedLocationId(): ?int
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $loc = (int) ($mapping['locations_id'] ?? 0);
            if ($loc > 0) {
                return $loc;
            }
            // Mapped with empty location → all locations.
            return null;
        }

        // No mapping: unrestricted if they have any plugin "view all" / config right.
        if (Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, READ)
            || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_CONFIG, UPDATE)
            || Session::haveRight('config', UPDATE)
        ) {
            return null;
        }

        // Owned-only / confirm-only users: no location filter needed.
        if (self::canSeeEquipment() || self::canConfirm()) {
            return null;
        }

        return 0;
    }

    public static function canAccessLocation(int $locations_id): bool
    {
        $scope = self::getScopedLocationId();
        if ($scope === null) {
            return true;
        }
        return $scope === $locations_id;
    }

    /**
     * @return list<int>
     */
    public static function getUserIdsWithPluginLocation(int $locations_id): array
    {
        global $DB;

        if ($locations_id <= 0
            || !$DB->tableExists(PluginAuchanassettrackerProfile::getTable())
            || !$DB->tableExists('glpi_profiles_users')) {
            return [];
        }

        $profile_ids = [];
        foreach ($DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => PluginAuchanassettrackerProfile::getTable(),
            'WHERE'  => ['locations_id' => $locations_id],
        ]) as $row) {
            $pid = (int) ($row['profiles_id'] ?? 0);
            if ($pid > 0) {
                $profile_ids[$pid] = true;
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
        return array_map('intval', array_keys($uids));
    }

    public static function userMatchesRecipientLocation(int $users_id, int $locations_id): bool
    {
        if ($users_id <= 0 || $locations_id <= 0) {
            return false;
        }

        $user = new User();
        if ($user->getFromDB($users_id)
            && (int) ($user->fields['locations_id'] ?? 0) === $locations_id) {
            return true;
        }

        return in_array($users_id, self::getUserIdsWithPluginLocation($locations_id), true);
    }

    public static function canAccessRecipientUser(int $users_id): bool
    {
        if ($users_id <= 0) {
            return false;
        }
        $scope = self::getScopedLocationId();
        if ($scope === null) {
            return true;
        }
        if ($scope <= 0) {
            return false;
        }
        return self::userMatchesRecipientLocation($users_id, $scope);
    }

    /**
     * Select2 search for allocation recipients (location-scoped when applicable).
     *
     * @return array{results: list<array{id: int, text: string}>, more: bool}
     */
    public static function searchRecipientUsers(string $term, int $page = 1, int $page_limit = 30): array
    {
        global $DB;

        $page = max(1, $page);
        $page_limit = max(1, min(100, $page_limit));
        $scope = self::getScopedLocationId();

        if ($scope !== null && $scope <= 0) {
            return ['results' => [], 'more' => false];
        }

        $where = [
            'is_active'  => 1,
            'is_deleted' => 0,
        ];
        if ($scope !== null) {
            // Match GLPI native location OR plugin profile location mapping.
            $plugin_uids = self::getUserIdsWithPluginLocation($scope);
            $loc_or = [
                ['locations_id' => $scope],
            ];
            if ($plugin_uids !== []) {
                $loc_or[] = ['id' => $plugin_uids];
            }
            $where[] = ['OR' => $loc_or];
        }

        $term = trim($term);
        if ($term !== '') {
            $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $like = '%' . $esc . '%';
            $where[] = [
                'OR' => [
                    ['name'      => ['LIKE', $like]],
                    ['realname'  => ['LIKE', $like]],
                    ['firstname' => ['LIKE', $like]],
                ],
            ];
        }

        $start = ($page - 1) * $page_limit;
        $rows = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'realname', 'firstname'],
            'FROM'   => User::getTable(),
            'WHERE'  => $where,
            'ORDER'  => 'realname ASC, firstname ASC, name ASC',
            'START'  => $start,
            'LIMIT'  => $page_limit + 1,
        ]) as $row) {
            $rows[] = $row;
        }

        $more = count($rows) > $page_limit;
        if ($more) {
            array_pop($rows);
        }

        $results = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            // Build label from already-fetched columns (avoid N+1 getUserName).
            $label = self::formatRecipientLabel($row);
            $results[] = [
                'id'   => $id,
                'text' => $label,
            ];
        }

        return ['results' => $results, 'more' => $more];
    }

    /**
     * GLPI-style user label from a users row (no extra DB hit).
     *
     * @param array<string, mixed> $row
     */
    public static function formatRecipientLabel(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $login = trim((string) ($row['name'] ?? ''));
        $realname = trim((string) ($row['realname'] ?? ''));
        $firstname = trim((string) ($row['firstname'] ?? ''));

        // Prefer GLPI login name; append real name when useful.
        if ($login !== '') {
            $parts = array_filter([$firstname, $realname], static fn ($p) => $p !== '');
            if ($parts !== []) {
                return $login . ' — ' . implode(' ', $parts);
            }
            return $login;
        }

        if (function_exists('formatUserName')) {
            $label = trim(strip_tags((string) formatUserName($id, $login, $realname, $firstname)));
            if ($label !== '') {
                return $label;
            }
        }

        return $id > 0 ? (string) $id : Dropdown::EMPTY_VALUE;
    }

    /**
     * Searchable recipient User dropdown (Select2 AJAX), location-filtered for scoped roles.
     * Matches GLPI users dropdown UX: empty "-----" choice, no clear (X), fast typeahead.
     */
    public static function dropdownRecipientUser(array $options = []): void
    {
        $name  = (string) ($options['name'] ?? 'users_id');
        $value = (int) ($options['value'] ?? 0);
        $width = (string) ($options['width'] ?? '100%');
        $rand  = (int) ($options['rand'] ?? mt_rand());

        if ($value > 0 && !self::canAccessRecipientUser($value)) {
            $value = 0;
        }

        $field_id = Html::cleanId('dropdown_' . $name . $rand);
        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/users.php',
            JSON_UNESCAPED_SLASHES
        );
        $width_js = json_encode($width, JSON_UNESCAPED_SLASHES);
        $field_js = json_encode($field_id, JSON_UNESCAPED_SLASHES);

        // Hidden until Select2 binds — avoids native browser menu with only "-----".
        echo "<select name='" . Html::entities_deep($name) . "' id='"
            . Html::entities_deep($field_id) . "' class='form-select aat-recipient-user aat-recipient-pending'"
            . " data-glpicontainername='" . Html::entities_deep($name) . "'"
            . " style='width:" . Html::entities_deep($width) . "'>";
        echo "<option value='0'>" . Html::entities_deep(Dropdown::EMPTY_VALUE) . "</option>";
        if ($value > 0) {
            $u = new User();
            $label = '#' . $value;
            if ($u->getFromDB($value)) {
                $label = self::formatRecipientLabel([
                    'id'        => $value,
                    'name'      => $u->fields['name'] ?? '',
                    'realname'  => $u->fields['realname'] ?? '',
                    'firstname' => $u->fields['firstname'] ?? '',
                ]);
            }
            echo "<option value='" . $value . "' selected>"
                . Html::entities_deep($label) . "</option>";
        }
        echo '</select>';

        // Init immediately (and again on DOM ready) so refresh/switch never shows the native menu.
        echo Html::scriptBlock(<<<JS
(function () {
  function aatInitRecipientUser() {
    var \$sel = $('#' + {$field_js});
    if (!\$sel.length || \$sel.data('aatSelect2Ready')) { return; }
    if (\$sel.hasClass('select2-hidden-accessible')) {
      try { \$sel.select2('destroy'); } catch (e) {}
    }
    \$sel.select2({
      width: {$width_js},
      allowClear: false,
      minimumInputLength: 0,
      minimumResultsForSearch: 0,
      ajax: {
        url: {$ajax},
        dataType: 'json',
        delay: 100,
        data: function (params) {
          return {
            term: params.term || '',
            page: params.page || 1
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;
          return {
            results: (data && data.results) ? data.results : [],
            pagination: {
              more: !!(data && data.pagination && data.pagination.more)
            }
          };
        },
        cache: true
      }
    });
    \$sel.removeClass('aat-recipient-pending').data('aatSelect2Ready', true);
  }
  if (window.jQuery) {
    aatInitRecipientUser();
    $(aatInitRecipientUser);
  }
})();
JS);
    }

    public static function requireCanAccessLocation(int $locations_id): void
    {
        if (!self::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot access data from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            Html::displayRightError();
            exit;
        }
    }
}
