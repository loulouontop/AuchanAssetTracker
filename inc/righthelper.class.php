<?php

/**
 * Role helpers: central_admin | location_manager | support_tech | user
 *
 * When a GLPI profile is mapped in the plugin tab, that role wins —
 * including for Super-Admin mapped as End user.
 */
class PluginAuchanassettrackerRighthelper
{
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

    public static function getCurrentRole(): string
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $role = (string) ($mapping['role'] ?? self::ROLE_USER);
            if (isset(self::getRoles()[$role])) {
                return $role;
            }
            return self::ROLE_USER;
        }

        // No mapping: Super-Admin / config editors act as central admin.
        if (Session::haveRight('config', UPDATE)) {
            return self::ROLE_CENTRAL_ADMIN;
        }

        return self::ROLE_USER;
    }

    public static function hasPluginProfileRow(): bool
    {
        return PluginAuchanassettrackerProfile::getForCurrentProfile() !== null;
    }

    public static function isCentralAdmin(): bool
    {
        return self::getCurrentRole() === self::ROLE_CENTRAL_ADMIN;
    }

    public static function isLocationManager(): bool
    {
        $role = self::getCurrentRole();
        return in_array($role, [self::ROLE_LOCATION_MANAGER, self::ROLE_CENTRAL_ADMIN], true);
    }

    public static function isSupportTech(): bool
    {
        $role = self::getCurrentRole();
        return in_array($role, [
            self::ROLE_SUPPORT_TECH,
            self::ROLE_LOCATION_MANAGER,
            self::ROLE_CENTRAL_ADMIN,
        ], true);
    }

    public static function canManageStock(): bool
    {
        return self::isLocationManager() || self::isCentralAdmin();
    }

    public static function canAllocate(): bool
    {
        return self::isSupportTech();
    }

    /**
     * Location scope for current user.
     * null = all locations (central admin with no location mapped).
     * int  = only that location — including central admin when a location is set.
     */
    public static function getScopedLocationId(): ?int
    {
        $mapping = PluginAuchanassettrackerProfile::getForCurrentProfile();
        if ($mapping !== null) {
            $loc = (int) ($mapping['locations_id'] ?? 0);
            if ($loc > 0) {
                return $loc;
            }
            // Mapped role with empty location: central admin sees all; others see nothing.
            if (self::isCentralAdmin()) {
                return null;
            }
            return 0;
        }

        // No mapping: Super-Admin / config editors act as central admin (all locations).
        if (self::isCentralAdmin()) {
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
     * User IDs assigned to a GLPI profile that has this plugin Location mapping.
     *
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

    /**
     * Whether a user belongs to a location via GLPI default location
     * and/or the Auchan Asset Tracker location on one of their GLPI profiles.
     */
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

    /**
     * Whether the current role may pick this GLPI user as allocation recipient.
     * Scoped roles: GLPI user location OR plugin profile location must match.
     */
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
            $label = trim(strip_tags((string) getUserName($id)));
            if ($label === '') {
                $login = trim((string) ($row['name'] ?? ''));
                $label = $login !== '' ? $login : ('#' . $id);
            }
            $results[] = [
                'id'   => $id,
                'text' => $label,
            ];
        }

        return ['results' => $results, 'more' => $more];
    }

    /**
     * Searchable recipient User dropdown (Select2 AJAX), location-filtered for scoped roles.
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
        $placeholder = json_encode(Dropdown::EMPTY_VALUE, JSON_UNESCAPED_UNICODE);

        echo "<select name='" . Html::entities_deep($name) . "' id='"
            . Html::entities_deep($field_id) . "' class='form-select aat-recipient-user'"
            . " style='width:" . Html::entities_deep($width) . "'>";
        echo "<option value='0'>" . Html::entities_deep(Dropdown::EMPTY_VALUE) . "</option>";
        if ($value > 0) {
            $label = trim(strip_tags((string) getUserName($value)));
            if ($label === '') {
                $label = '#' . $value;
            }
            echo "<option value='" . $value . "' selected>"
                . Html::entities_deep($label) . "</option>";
        }
        echo '</select>';

        echo Html::scriptBlock(<<<JS
$(function () {
  var \$sel = $('#' + {$field_js});
  if (!\$sel.length) { return; }
  if (\$sel.hasClass('select2-hidden-accessible')) {
    \$sel.select2('destroy');
  }
  \$sel.select2({
    width: {$width_js},
    allowClear: true,
    placeholder: {$placeholder},
    minimumInputLength: 0,
    minimumResultsForSearch: 0,
    ajax: {
      url: {$ajax},
      dataType: 'json',
      delay: 250,
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
});
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
