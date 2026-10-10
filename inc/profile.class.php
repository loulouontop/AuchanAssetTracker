<?php

/**
 * GLPI-native rights matrix for Auchan Asset Tracker tabs + optional location scope.
 */
class PluginAuchanassettrackerProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    /** Right names registered in glpi_profilerights. */
    public const RIGHT_EQUIPMENT  = 'plugin_auchanassettracker_equipment';
    public const RIGHT_CONTAINER  = 'plugin_auchanassettracker_container';
    public const RIGHT_BULK       = 'plugin_auchanassettracker_bulk';
    public const RIGHT_ALLOCATION = 'plugin_auchanassettracker_allocation';
    public const RIGHT_TRANSFER   = 'plugin_auchanassettracker_transfer';
    public const RIGHT_CONFIRM    = 'plugin_auchanassettracker_confirm';
    public const RIGHT_CONFIG     = 'plugin_auchanassettracker_config';

    /** @deprecated kept for migration from role dropdown */
    public const LEGACY_RIGHT = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return __('Auchan Asset Tracker', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_profiles';
    }

    public static function getIcon(): string
    {
        return 'ti ti-packages';
    }

    /**
     * Standard GLPI asset-style columns (View all / Update all / Create / … / owned).
     *
     * @return array<int, string|array{short: string, long: string}>
     */
    public static function getStandardRightsSet(): array
    {
        return [
            READ    => __('View all'),
            UPDATE  => __('Update all'),
            CREATE  => __('Create'),
            DELETE  => [
                'short' => __('Delete'),
                'long'  => _x('button', 'Put in trashbin'),
            ],
            PURGE   => [
                'short' => __('Purge'),
                'long'  => _x('button', 'Delete permanently'),
            ],
            READNOTE => [
                'short' => __('Read notes'),
                'long'  => __("Read the item's notes"),
            ],
            UPDATENOTE => [
                'short' => __('Update notes'),
                'long'  => __("Update the item's notes"),
            ],
            READ_ASSIGNED => __('View assigned'),
            UPDATE_ASSIGNED => __('Update assigned'),
            READ_OWNED => __('View owned'),
            UPDATE_OWNED => __('Update owned'),
        ];
    }

    /**
     * Rows for Profile::displayRightsChoiceMatrix (tab name → right field).
     *
     * @return list<array{itemtype?: string, label: string, field: string, rights: array, scope?: string}>
     */
    public static function getAllRights(bool $all = false): array
    {
        $std = self::getStandardRightsSet();

        return [
            [
                'itemtype' => 'PluginAuchanassettrackerEquipment',
                'label'    => __('Equipment', 'auchanassettracker'),
                'field'    => self::RIGHT_EQUIPMENT,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerContainer',
                'label'    => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'field'    => self::RIGHT_CONTAINER,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerBulk',
                'label'    => __('Bulk add accessories', 'auchanassettracker'),
                'field'    => self::RIGHT_BULK,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerAllocation',
                'label'    => __('New allocation', 'auchanassettracker'),
                'field'    => self::RIGHT_ALLOCATION,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerTransfer',
                'label'    => __('Transfers', 'auchanassettracker'),
                'field'    => self::RIGHT_TRANSFER,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerConfirm',
                'label'    => __('Confirm receipt', 'auchanassettracker'),
                'field'    => self::RIGHT_CONFIRM,
                'rights'   => $std,
            ],
            [
                'itemtype' => 'PluginAuchanassettrackerConfig',
                'label'    => __('Configuration', 'auchanassettracker'),
                'field'    => self::RIGHT_CONFIG,
                'rights'   => $std,
                'scope'    => 'global',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function getRightNames(): array
    {
        return array_column(self::getAllRights(true), 'field');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile
            && (int) $item->getID() > 0
            && ($item->fields['interface'] ?? '') !== 'helpdesk'
            && Session::haveRight('profile', READ)
        ) {
            return self::createTabEntry(
                __('Auchan Asset Tracker', 'auchanassettracker'),
                0,
                $item->getType(),
                self::getIcon()
            );
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            self::ensureRightsRowsForProfile((int) $item->getID());
            $prof = new self();
            $prof->showForm((int) $item->getID());
            return true;
        }
        return false;
    }

    /**
     * Ensure profilerights rows exist for one profile (value 0 if new).
     */
    public static function ensureRightsRowsForProfile(int $profiles_id, ?array $defaults = null): void
    {
        if ($profiles_id <= 0) {
            return;
        }
        $defaults ??= array_fill_keys(self::getRightNames(), 0);
        self::addDefaultProfileInfos($profiles_id, $defaults, false);
    }

    /**
     * @param array<string, int> $rights
     */
    public static function addDefaultProfileInfos(
        int $profiles_id,
        array $rights,
        bool $drop_existing = false
    ): void {
        global $DB;

        if (!$DB->tableExists('glpi_profilerights') || $profiles_id <= 0) {
            return;
        }

        foreach ($rights as $right => $value) {
            $exists = false;
            foreach ($DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                ],
                'LIMIT' => 1,
            ]) as $_) {
                $exists = true;
            }

            if ($exists && $drop_existing) {
                $DB->delete('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                ]);
                $exists = false;
            }

            if (!$exists) {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => (int) $value,
                ]);
                if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                    $_SESSION['glpiactiveprofile'][$right] = (int) $value;
                }
            }
        }
    }

    /**
     * Full bitmask for Super-Admin style access.
     */
    public static function getFullRightsMask(): int
    {
        return ALLSTANDARDRIGHT | READNOTE | UPDATENOTE
            | READ_ASSIGNED | UPDATE_ASSIGNED | READ_OWNED | UPDATE_OWNED;
    }

    /**
     * Map legacy role string → per-right bitmasks.
     *
     * @return array<string, int>
     */
    public static function rightsFromLegacyRole(string $role): array
    {
        $full = self::getFullRightsMask();
        $names = self::getRightNames();
        $zero = array_fill_keys($names, 0);

        return match ($role) {
            'central_admin' => array_fill_keys($names, $full),
            'location_manager' => [
                self::RIGHT_EQUIPMENT  => $full,
                self::RIGHT_CONTAINER  => $full,
                self::RIGHT_BULK       => CREATE | READ | UPDATE,
                self::RIGHT_ALLOCATION => READ | CREATE | UPDATE,
                self::RIGHT_TRANSFER   => READ | CREATE | UPDATE,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            'support_tech' => [
                self::RIGHT_EQUIPMENT  => READ | UPDATE | READ_ASSIGNED | UPDATE_ASSIGNED,
                self::RIGHT_CONTAINER  => READ,
                self::RIGHT_BULK       => 0,
                self::RIGHT_ALLOCATION => READ | CREATE | UPDATE,
                self::RIGHT_TRANSFER   => READ | CREATE | UPDATE,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            default => [ // end user
                self::RIGHT_EQUIPMENT  => READ_OWNED,
                self::RIGHT_CONTAINER  => 0,
                self::RIGHT_BULK       => 0,
                self::RIGHT_ALLOCATION => 0,
                self::RIGHT_TRANSFER   => 0,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
        };
    }

    public static function initProfile(): void
    {
        global $DB, $GLPI_CACHE;

        if (!$DB->tableExists('glpi_profilerights') || !$DB->tableExists('glpi_profiles')) {
            return;
        }

        if (isset($GLPI_CACHE) && is_object($GLPI_CACHE) && method_exists($GLPI_CACHE, 'set')) {
            $GLPI_CACHE->set('all_possible_rights', []);
        }

        $full = self::getFullRightsMask();
        $rightNames = self::getRightNames();

        $superAdminIds = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['name' => 'Super-Admin'],
        ]) as $prof) {
            $superAdminIds[(int) $prof['id']] = true;
        }

        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles',
        ]) as $prof) {
            $profiles_id = (int) $prof['id'];
            $isSuper = isset($superAdminIds[$profiles_id]);

            $defaults = array_fill_keys($rightNames, 0);
            if ($isSuper) {
                $defaults = array_fill_keys($rightNames, $full);
            } else {
                $mapping = self::getForProfileId($profiles_id);
                if ($mapping !== null) {
                    $role = (string) ($mapping['role'] ?? 'user');
                    $defaults = self::rightsFromLegacyRole($role);
                }
            }

            // Seed missing rows only (do not overwrite admin edits).
            self::addDefaultProfileInfos($profiles_id, $defaults, false);

            // Super-Admin: keep full rights on every load.
            if ($isSuper) {
                foreach ($rightNames as $name) {
                    $DB->update('glpi_profilerights', [
                        'rights' => $full,
                    ], [
                        'profiles_id' => $profiles_id,
                        'name'        => $name,
                    ]);
                    if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                        $_SESSION['glpiactiveprofile'][$name] = $full;
                    }
                }
            }

            // Location mapping row for Super-Admin if missing.
            if ($isSuper && self::getForProfileId($profiles_id) === null) {
                self::saveLocationFromPost([
                    'profiles_id'  => $profiles_id,
                    'locations_id' => 0,
                    'role'         => 'central_admin',
                ]);
            }

            // Keep legacy single right in sync for older CommonDBTM checks during transition.
            self::addDefaultProfileInfos($profiles_id, [
                self::LEGACY_RIGHT => $isSuper ? $full : (int) ($defaults[self::RIGHT_EQUIPMENT] ?? 0),
            ], false);
            if ($isSuper) {
                $DB->update('glpi_profilerights', [
                    'rights' => $full,
                ], [
                    'profiles_id' => $profiles_id,
                    'name'        => self::LEGACY_RIGHT,
                ]);
            }
        }
    }

    public static function getForCurrentProfile(): ?array
    {
        $profiles_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        return self::getForProfileId($profiles_id);
    }

    public static function getForProfileId(int $profiles_id): ?array
    {
        global $DB;

        if (!$DB->tableExists(self::getTable()) || $profiles_id <= 0) {
            return null;
        }

        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['profiles_id' => $profiles_id],
            'LIMIT' => 1,
        ]) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Rights matrix + location scope in one GLPI table form (single Save).
     */
    public function showForm($ID, array $options = []): bool
    {
        $profiles_id = (int) $ID;
        $canedit = Session::haveRight('profile', UPDATE);

        $profile = new Profile();
        if (!$profile->getFromDB($profiles_id)) {
            return false;
        }

        $current = self::getForProfileId($profiles_id) ?? [
            'locations_id' => 0,
        ];
        $locations_id = (int) ($current['locations_id'] ?? 0);
        $action = plugin_auchanassettracker_web_dir() . '/front/profile.form.php';

        echo "<div class='aat-profile-wrap firstbloc'>";

        if ($canedit) {
            echo "<form method='post' action='" . Html::entities_deep($action) . "'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
        }

        if (($profile->fields['interface'] ?? '') === 'central') {
            $profile->displayRightsChoiceMatrix(self::getAllRights(), [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('Auchan Asset Tracker', 'auchanassettracker'),
            ]);
        }

        // Location scope — same tab_cadre_fixe table UI as the rights matrix.
        echo "<table class='tab_cadre_fixe aat-profile-location-table'>";
        echo "<tr><th colspan='2'>"
            . Html::entities_deep(__('Location scope', 'auchanassettracker'))
            . "</th></tr>";
        echo "<tr class='tab_bg_2'>";
        echo "<td class='aat-profile-location-label'>"
            . Html::entities_deep(__('Location'))
            . "</td>";
        echo "<td class='aat-profile-location-value'>";
        if ($canedit) {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => $locations_id,
                'width' => '100%',
            ]);
        } elseif ($locations_id > 0) {
            echo Html::entities_deep(Dropdown::getDropdownName('glpi_locations', $locations_id));
        } else {
            echo Html::entities_deep(__('All locations', 'auchanassettracker'));
        }
        echo "<div class='form-text mt-1'>"
            . Html::entities_deep(__(
                'Optional. When set, Equipment / containers / stock actions are limited to this location. Leave empty to allow all locations for this profile.',
                'auchanassettracker'
            ))
            . "</div>";
        echo "</td></tr>";
        echo "</table>";

        if ($canedit) {
            echo "<div class='center my-2'>";
            echo Html::submit(_sx('button', 'Save'), [
                'name'  => 'update',
                'class' => 'btn btn-primary',
            ]);
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>";
        return true;
    }

    /**
     * Apply rights matrix POST fields (same shape as Profile::prepareInputForUpdate).
     *
     * @return bool true if any right value was processed
     */
    public static function saveRightsFromPost(array $post): bool
    {
        global $DB;

        $profiles_id = (int) ($post['profiles_id'] ?? $post['id'] ?? 0);
        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profilerights')) {
            return false;
        }

        $changed = [];
        foreach (self::getRightNames() as $right) {
            $key = '_' . $right;
            if (!isset($post[$key])) {
                continue;
            }
            $raw = $post[$key];
            if (!is_array($raw)) {
                $raw = ['1' => $raw];
            }
            $newvalue = 0;
            foreach ($raw as $value => $valid) {
                if (!$valid) {
                    continue;
                }
                if (($underscore_pos = strpos((string) $value, '_')) !== false) {
                    $value = substr((string) $value, 0, $underscore_pos);
                }
                $newvalue += (int) $value;
            }
            $changed[$right] = $newvalue;
            if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                $_SESSION['glpiactiveprofile'][$right] = $newvalue;
            }
        }

        if ($changed === []) {
            return false;
        }

        ProfileRight::updateProfileRights($profiles_id, $changed);

        // Keep legacy aggregate right roughly in sync with equipment.
        if (isset($changed[self::RIGHT_EQUIPMENT])) {
            ProfileRight::updateProfileRights($profiles_id, [
                self::LEGACY_RIGHT => (int) $changed[self::RIGHT_EQUIPMENT],
            ]);
            if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                $_SESSION['glpiactiveprofile'][self::LEGACY_RIGHT] = (int) $changed[self::RIGHT_EQUIPMENT];
            }
        }

        return true;
    }

    /**
     * Upsert location scope (and keep role column for history/migration).
     *
     * @return 'created'|'updated'|'unchanged'|'error'
     */
    public static function saveLocationFromPost(array $post): string
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return 'error';
        }

        $profiles_id = (int) ($post['profiles_id'] ?? 0);
        if ($profiles_id <= 0) {
            return 'error';
        }

        $locations_id = (int) ($post['locations_id'] ?? 0);
        $role = (string) ($post['role'] ?? '');
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $existing = self::getForProfileId($profiles_id);

        if ($existing !== null) {
            $sameLoc = (int) ($existing['locations_id'] ?? 0) === $locations_id;
            $update = [
                'locations_id' => $locations_id,
                'date_mod'     => $now,
            ];
            // Only touch role when explicitly provided (migration / Super-Admin seed).
            if ($role !== '' && $role !== (string) ($existing['role'] ?? '')) {
                $update['role'] = $role;
                $sameLoc = false;
            } elseif ($sameLoc) {
                return 'unchanged';
            }

            $ok = $DB->update(self::getTable(), $update, ['id' => (int) $existing['id']]);
            return $ok !== false ? 'updated' : 'error';
        }

        $ok = $DB->insert(self::getTable(), [
            'profiles_id'   => $profiles_id,
            'role'          => $role !== '' ? $role : 'user',
            'locations_id'  => $locations_id,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);

        return $ok ? 'created' : 'error';
    }

    /** @deprecated use saveLocationFromPost */
    public static function saveFromPost(array $post): string
    {
        return self::saveLocationFromPost($post);
    }

    /**
     * Profile IDs that currently hold at least one of the given rights bits.
     *
     * @param list<string> $right_names
     * @return list<int>
     */
    public static function getProfileIdsWithRights(array $right_names, int $bits = READ): array
    {
        global $DB;

        if ($right_names === [] || !$DB->tableExists('glpi_profilerights')) {
            return [];
        }

        $ids = [];
        foreach ($DB->request([
            'SELECT' => ['profiles_id', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => $right_names],
        ]) as $row) {
            if (((int) ($row['rights'] ?? 0) & $bits) === $bits) {
                $ids[(int) $row['profiles_id']] = true;
            }
        }
        return array_map('intval', array_keys($ids));
    }
}
