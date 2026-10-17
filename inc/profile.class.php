<?php

/**
 * Plugin profile rights + bundled GLPI profiles for Auchan Asset Tracker.
 * Roles are bound to the four auto-created profiles (no Profile tab UI).
 * Location scope uses native GLPI User.locations_id — not stored here.
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
     * Bundled GLPI profiles created by the plugin (name → role).
     * End user is the default profile for new GLPI users.
     *
     * @return array<string, array{name: string, comment: string, is_default: bool}>
     */
    public static function getBundledProfiles(): array
    {
        return [
            PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN => [
                'name'       => 'Central admin',
                'comment'    => 'Auchan Asset Tracker — global operations (all locations). Plugin config remains Super-Admin only.',
                'is_default' => false,
            ],
            PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER => [
                'name'       => 'Location manager',
                'comment'    => 'Auchan Asset Tracker — full operations on own location (receipt, stock, allocation, transfer, write-off).',
                'is_default' => false,
            ],
            PluginAuchanassettrackerRighthelper::ROLE_SUPPORT_TECH => [
                'name'       => 'Support technician',
                'comment'    => 'Auchan Asset Tracker — allocation, transfers and service on assigned location.',
                'is_default' => false,
            ],
            PluginAuchanassettrackerRighthelper::ROLE_USER => [
                'name'       => 'End user',
                'comment'    => 'Auchan Asset Tracker — confirm receipt and view own equipment only. Default profile for new users.',
                'is_default' => true,
            ],
        ];
    }

    /**
     * Map legacy role string → per-right bitmasks.
     *
     * Config (RIGHT_CONFIG) is never granted via roles — Super-Admin only.
     *
     * @return array<string, int>
     */
    public static function rightsFromLegacyRole(string $role): array
    {
        $full = self::getFullRightsMask();
        $names = self::getRightNames();
        $zero = array_fill_keys($names, 0);

        // Config (RIGHT_CONFIG) is applied only for Super-Admin in initProfile.

        return match ($role) {
            // Global ops (all locations); configuration stays Super-Admin-only.
            'central_admin' => [
                self::RIGHT_EQUIPMENT  => $full,
                self::RIGHT_CONTAINER  => $full,
                self::RIGHT_BULK       => $full,
                self::RIGHT_ALLOCATION => $full,
                self::RIGHT_TRANSFER   => $full,
                self::RIGHT_CONFIRM    => $full,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            // Own location: receipt, stock, containers, allocation, transfer, write-off.
            'location_manager' => [
                self::RIGHT_EQUIPMENT  => $full,
                self::RIGHT_CONTAINER  => $full,
                self::RIGHT_BULK       => CREATE | READ | UPDATE,
                self::RIGHT_ALLOCATION => READ | CREATE | UPDATE,
                self::RIGHT_TRANSFER   => READ | CREATE | UPDATE,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            // Own location: new allocation + transfers / service (no stock receipt / bulk / config).
            'support_tech' => [
                self::RIGHT_EQUIPMENT  => READ | UPDATE | READ_ASSIGNED | UPDATE_ASSIGNED,
                self::RIGHT_CONTAINER  => READ,
                self::RIGHT_BULK       => 0,
                self::RIGHT_ALLOCATION => READ | CREATE | UPDATE,
                self::RIGHT_TRANSFER   => 0,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            default => [ // end user — confirm receipt + view own equipment
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

    /**
     * Find a GLPI profile by exact name, or create it (central interface).
     */
    public static function findOrCreateProfileByName(
        string $name,
        string $comment = '',
        bool $is_default = false
    ): int {
        global $DB;

        if ($name === '' || !$DB->tableExists('glpi_profiles')) {
            return 0;
        }

        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1,
        ]) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && $is_default && $DB->fieldExists('glpi_profiles', 'is_default')) {
                // Keep End user as the default profile for new GLPI users.
                $DB->update('glpi_profiles', ['is_default' => 0], [
                    'is_default' => 1,
                    ['NOT' => ['id' => $id]],
                ]);
                $DB->update('glpi_profiles', ['is_default' => 1], ['id' => $id]);
            }
            return $id;
        }

        $input = [
            'name'      => $name,
            'interface' => 'central',
            'comment'   => $comment,
        ];
        if ($DB->fieldExists('glpi_profiles', 'is_default')) {
            $input['is_default'] = $is_default ? 1 : 0;
        }

        $profile = new Profile();
        $new_id = (int) $profile->add($input);
        if ($new_id > 0) {
            if ($is_default && $DB->fieldExists('glpi_profiles', 'is_default')) {
                $DB->update('glpi_profiles', ['is_default' => 0], [
                    'is_default' => 1,
                    ['NOT' => ['id' => $new_id]],
                ]);
                $DB->update('glpi_profiles', ['is_default' => 1], ['id' => $new_id]);
            }
            return $new_id;
        }

        // Fallback if Profile::add is unavailable / blocked in this context.
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $row = [
            'name'      => $name,
            'interface' => 'central',
        ];
        if ($DB->fieldExists('glpi_profiles', 'comment')) {
            $row['comment'] = $comment;
        }
        if ($DB->fieldExists('glpi_profiles', 'is_default')) {
            $row['is_default'] = $is_default ? 1 : 0;
        }
        if ($DB->fieldExists('glpi_profiles', 'date_creation')) {
            $row['date_creation'] = $now;
        }
        if ($DB->fieldExists('glpi_profiles', 'date_mod')) {
            $row['date_mod'] = $now;
        }
        if ($DB->insert('glpi_profiles', $row)) {
            $id = (int) $DB->insertId();
            if ($id > 0 && $is_default && $DB->fieldExists('glpi_profiles', 'is_default')) {
                $DB->update('glpi_profiles', ['is_default' => 0], [
                    'is_default' => 1,
                    ['NOT' => ['id' => $id]],
                ]);
            }
            return $id;
        }

        return 0;
    }

    /**
     * Create the four Auchan Asset Tracker profiles if missing and bind roles + rights.
     *
     * @param bool $force_rights When true (install/upgrade), re-apply role right presets.
     */
    public static function ensureBundledProfiles(bool $force_rights = false): void
    {
        global $DB;

        if (!$DB->tableExists('glpi_profiles') || !$DB->tableExists(self::getTable())) {
            return;
        }

        foreach (self::getBundledProfiles() as $role => $meta) {
            $before = 0;
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_profiles',
                'WHERE'  => ['name' => (string) $meta['name']],
                'LIMIT'  => 1,
            ]) as $row) {
                $before = (int) ($row['id'] ?? 0);
            }

            $profiles_id = self::findOrCreateProfileByName(
                (string) $meta['name'],
                (string) ($meta['comment'] ?? ''),
                !empty($meta['is_default'])
            );
            if ($profiles_id <= 0) {
                continue;
            }

            $created = $before <= 0;
            $existing = self::getForProfileId($profiles_id);
            $role_mismatch = $existing === null
                || (string) ($existing['role'] ?? '') !== $role;

            if ($created || $role_mismatch || $force_rights) {
                self::saveFromPost([
                    'profiles_id' => $profiles_id,
                    'role'        => $role,
                ]);
                self::applyRightsForRole($profiles_id, $role);
            } else {
                self::ensureRightsRowsForProfile(
                    $profiles_id,
                    self::rightsFromLegacyRole($role)
                );
            }
        }
    }

    /**
     * @param bool $force_bundled_rights Re-apply bundled profile right presets (install/upgrade).
     */
    public static function initProfile(bool $force_bundled_rights = false): void
    {
        global $DB, $GLPI_CACHE;

        if (!$DB->tableExists('glpi_profilerights') || !$DB->tableExists('glpi_profiles')) {
            return;
        }

        if (isset($GLPI_CACHE) && is_object($GLPI_CACHE) && method_exists($GLPI_CACHE, 'set')) {
            $GLPI_CACHE->set('all_possible_rights', []);
        }

        // Create Central admin / Location manager / Support technician / End user.
        self::ensureBundledProfiles($force_bundled_rights);

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
                // Super-Admin: full plugin rights including Configuration.
                $defaults = array_fill_keys($rightNames, $full);
            } else {
                $mapping = self::getForProfileId($profiles_id);
                if ($mapping !== null) {
                    $role = (string) ($mapping['role'] ?? 'user');
                    $defaults = self::rightsFromLegacyRole($role);
                } else {
                    // Unmapped profiles default to End user (confirm receipt only).
                    $defaults = self::rightsFromLegacyRole(
                        PluginAuchanassettrackerRighthelper::ROLE_USER
                    );
                }
            }

            // Seed missing rows only (do not overwrite admin edits).
            self::addDefaultProfileInfos($profiles_id, $defaults, false);

            // Super-Admin: keep full rights on every load (only profile with Config).
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

            // Role mapping row for Super-Admin if missing.
            if ($isSuper && self::getForProfileId($profiles_id) === null) {
                self::saveFromPost([
                    'profiles_id' => $profiles_id,
                    'role'        => 'central_admin',
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
     * Role only (rights come from the role). Location scope uses GLPI User.locations_id.
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
            'role' => PluginAuchanassettrackerRighthelper::ROLE_USER,
        ];
        $role = (string) ($current['role'] ?? PluginAuchanassettrackerRighthelper::ROLE_USER);
        if (!isset(PluginAuchanassettrackerRighthelper::getRoles()[$role])) {
            $role = PluginAuchanassettrackerRighthelper::ROLE_USER;
        }
        $action = plugin_auchanassettracker_web_dir() . '/front/profile.form.php';

        echo "<div class='aat-profile-wrap firstbloc'>";

        if ($canedit) {
            echo "<form method='post' action='" . Html::entities_deep($action) . "'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
        }

        // Same wrapper/title markup as components/checkbox_matrix.html.twig.
        echo "<div class='mb-4 table-responsive aat-profile-meta'>";
        echo "<table class='table table-hover card-table'>";
        echo "<thead><tr class='border-top'><th colspan='1' class=''>"
            . "<span class='fs-4'>"
            . Html::entities_deep(__('Role', 'auchanassettracker'))
            . "</span></th></tr></thead>";
        echo "<tbody><tr><td class='aat-profile-meta-cell'>";
        echo "<div class='aat-profile-field'>";
        if ($canedit) {
            Dropdown::showFromArray('role', PluginAuchanassettrackerRighthelper::getRoles(), [
                'value' => $role,
                'width' => '320px',
            ]);
        } else {
            $roles = PluginAuchanassettrackerRighthelper::getRoles();
            echo Html::entities_deep($roles[$role] ?? $role);
        }
        echo "</div>";
        echo "<div class='form-text mt-1'>"
            . Html::entities_deep(__(
                'The plugin creates four profiles: Central admin, Location manager, Support technician, and End user (default for new users). Location managers and support technicians are limited to the Location set on each user’s GLPI form (Administration → Users). Central admin has no location limit. Plugin Configuration is available to Super-Admin only.',
                'auchanassettracker'
            ))
            . "</div>";
        echo "</td></tr></tbody>";
        echo "</table></div>";

        if ($canedit) {
            // Same footer as GLPI components/form/buttons.html.twig (Save on the right).
            echo "<div class='form-button-separator card-body mx-n2 mb-n2 border-top"
                . " d-flex flex-row-reverse align-items-center flex-wrap gap-2'>";
            echo "<button class='btn btn-primary me-2' type='submit' name='update' value='1'>";
            echo "<i class='ti ti-device-floppy'></i> ";
            echo "<span>" . Html::entities_deep(_sx('button', 'Save')) . "</span>";
            echo "</button>";
            echo "</div>";
            // Do not Html::closeForm() on Profile tabs — wrong CSRF token on nested forms.
            echo "</form>";
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
     * Upsert role from the profile tab (location is no longer stored here).
     *
     * @return 'created'|'updated'|'unchanged'|'error'
     */
    public static function saveLocationFromPost(array $post): string
    {
        return self::saveFromPost($post);
    }

    /**
     * Upsert role from the profile tab.
     * locations_id column is kept for DB compatibility but always stored as 0.
     *
     * @return 'created'|'updated'|'unchanged'|'error'
     */
    public static function saveFromPost(array $post): string
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return 'error';
        }

        $profiles_id = (int) ($post['profiles_id'] ?? 0);
        if ($profiles_id <= 0) {
            return 'error';
        }

        $roles = array_keys(PluginAuchanassettrackerRighthelper::getRoles());
        $role = (string) ($post['role'] ?? PluginAuchanassettrackerRighthelper::ROLE_USER);
        if (!in_array($role, $roles, true)) {
            $role = PluginAuchanassettrackerRighthelper::ROLE_USER;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $existing = self::getForProfileId($profiles_id);

        if ($existing !== null) {
            $sameRole = (string) ($existing['role'] ?? '') === $role;
            $sameLoc  = (int) ($existing['locations_id'] ?? 0) === 0;
            if ($sameRole && $sameLoc) {
                return 'unchanged';
            }

            $ok = $DB->update(self::getTable(), [
                'role'         => $role,
                'locations_id' => 0,
                'date_mod'     => $now,
            ], ['id' => (int) $existing['id']]);

            return $ok !== false ? 'updated' : 'error';
        }

        $ok = $DB->insert(self::getTable(), [
            'profiles_id'   => $profiles_id,
            'role'          => $role,
            'locations_id'  => 0,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);

        return $ok ? 'created' : 'error';
    }

    /**
     * Apply rights matrix presets for a role (bundled profiles / install).
     */
    public static function applyRightsForRole(int $profiles_id, string $role): void
    {
        global $DB;

        if ($profiles_id <= 0) {
            return;
        }
        $rights = self::rightsFromLegacyRole($role);

        // Super-Admin always keeps plugin Configuration (never stripped by role presets).
        $is_super = false;
        if ($DB->tableExists('glpi_profiles')) {
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_profiles',
                'WHERE'  => [
                    'id'   => $profiles_id,
                    'name' => 'Super-Admin',
                ],
                'LIMIT'  => 1,
            ]) as $_) {
                $is_super = true;
            }
        }
        if ($is_super) {
            $full = self::getFullRightsMask();
            foreach (self::getRightNames() as $name) {
                $rights[$name] = $full;
            }
        }

        self::addDefaultProfileInfos($profiles_id, $rights, true);
        ProfileRight::updateProfileRights($profiles_id, $rights);
        if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
            foreach ($rights as $name => $value) {
                $_SESSION['glpiactiveprofile'][$name] = (int) $value;
            }
        }
        // Legacy aggregate right.
        $legacy = (int) ($rights[self::RIGHT_EQUIPMENT] ?? 0);
        ProfileRight::updateProfileRights($profiles_id, [self::LEGACY_RIGHT => $legacy]);
        if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
            $_SESSION['glpiactiveprofile'][self::LEGACY_RIGHT] = $legacy;
        }
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
