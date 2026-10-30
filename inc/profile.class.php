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

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile
            && (int) $item->getID() > 0
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
     * Ensure profilerights rows exist (insert missing; optionally refresh values).
     * Idempotent: never DELETE+INSERT (avoids unicity 1062 on plugin init).
     *
     * @param array<string, int> $rights
     * @param bool               $drop_existing When true, UPDATE existing rows to $value.
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
            $value = (int) $value;
            $exists = false;
            if (function_exists('countElementsInTable')) {
                $exists = countElementsInTable('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                ]) > 0;
            } else {
                foreach ($DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_profilerights',
                    'WHERE'  => [
                        'profiles_id' => $profiles_id,
                        'name'        => $right,
                    ],
                    'LIMIT'  => 1,
                ]) as $_) {
                    $exists = true;
                }
            }

            if ($exists) {
                if ($drop_existing) {
                    $DB->update('glpi_profilerights', [
                        'rights' => $value,
                    ], [
                        'profiles_id' => $profiles_id,
                        'name'        => $right,
                    ]);
                    if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                        $_SESSION['glpiactiveprofile'][$right] = $value;
                    }
                }
                continue;
            }

            // Insert missing row; ignore duplicate-key races so plugin init cannot abort.
            $applied = false;
            try {
                $ok = $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value,
                ]);
                if ($ok !== false) {
                    $applied = true;
                } elseif ($drop_existing) {
                    $DB->update('glpi_profilerights', [
                        'rights' => $value,
                    ], [
                        'profiles_id' => $profiles_id,
                        'name'        => $right,
                    ]);
                    $applied = true;
                }
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $is_duplicate = str_contains($msg, '1062')
                    || stripos($msg, 'Duplicate') !== false;
                if (!$is_duplicate) {
                    throw $e;
                }
                if ($drop_existing) {
                    $DB->update('glpi_profilerights', [
                        'rights' => $value,
                    ], [
                        'profiles_id' => $profiles_id,
                        'name'        => $right,
                    ]);
                    $applied = true;
                }
            }

            if ($applied
                && (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id
            ) {
                $_SESSION['glpiactiveprofile'][$right] = $value;
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
     * Bundled GLPI profiles created by the plugin (English msgid → role).
     * Display name/comment use __() so they follow the active GLPI language.
     * End user uses helpdesk interface (confirm receipt only).
     * Other roles use central interface and clone core rights from Admin / Technician.
     * None of the bundled profiles is marked as GLPI default for new users.
     *
     * @return array<string, array{
     *   name: string,
     *   comment: string,
     *   is_default: bool,
     *   interface: string,
     *   clone_from: list<string>
     * }>
     */
    public static function getBundledProfiles(): array
    {
        return [
            PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN => [
                'name'       => 'Central admin',
                'comment'    => 'Auchan Asset Tracker — global operations (all locations). Plugin config remains Super-Admin only.',
                'is_default' => false,
                'interface'  => 'central',
                // Same native GLPI rights as Super-Admin (plugin Configuration still gated separately).
                'clone_from' => ['Super-Admin'],
            ],
            PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER => [
                'name'       => 'Location manager',
                'comment'    => 'Auchan Asset Tracker — full operations on own location (receipt, stock, allocation, transfer, write-off).',
                'is_default' => false,
                'interface'  => 'central',
                'clone_from' => ['Admin', 'Administrator', 'Administrateur'],
            ],
            PluginAuchanassettrackerRighthelper::ROLE_SUPPORT_TECH => [
                'name'       => 'Support technician',
                'comment'    => 'Auchan Asset Tracker — new allocation and service on assigned location.',
                'is_default' => false,
                'interface'  => 'central',
                'clone_from' => ['Technician', 'Technicien', 'Tech'],
            ],
            PluginAuchanassettrackerRighthelper::ROLE_USER => [
                'name'       => 'End user',
                'comment'    => 'Auchan Asset Tracker — confirm receipt only.',
                'is_default' => false,
                'interface'  => 'helpdesk',
                'clone_from' => ['Self-Service', 'Self-service', 'Hotliner'],
            ],
        ];
    }

    /**
     * Known name variants (EN + RO + current translation) for a profile msgid.
     *
     * @return list<string>
     */
    public static function getProfileNameVariants(string $name_msgid): array
    {
        $variants = [$name_msgid];
        $translated = __($name_msgid, 'auchanassettracker');
        if ($translated !== '' && $translated !== $name_msgid) {
            $variants[] = $translated;
        }
        // Hardcoded RO so find still works before/without translator.
        static $ro = [
            'Central admin'       => 'Admin central',
            'Location manager'    => 'Manager locație',
            'Support technician'  => 'Tehnician suport',
            'End user'            => 'Utilizator final',
        ];
        if (isset($ro[$name_msgid])) {
            $variants[] = $ro[$name_msgid];
        }
        return array_values(array_unique(array_filter($variants, static fn ($n) => $n !== '')));
    }

    public static function translateProfileLabel(string $msgid): string
    {
        $out = __($msgid, 'auchanassettracker');
        return $out !== '' ? $out : $msgid;
    }

    /**
     * Map legacy role string → per-right bitmasks.
     *
     * Config (RIGHT_CONFIG): Central admin (+ Super-Admin via initProfile).
     *
     * @return array<string, int>
     */
    public static function rightsFromLegacyRole(string $role): array
    {
        $full = self::getFullRightsMask();
        $names = self::getRightNames();
        $zero = array_fill_keys($names, 0);

        return match ($role) {
            // Global ops (all locations) including plugin Configuration.
            'central_admin' => [
                self::RIGHT_EQUIPMENT  => $full,
                self::RIGHT_CONTAINER  => $full,
                self::RIGHT_BULK       => $full,
                self::RIGHT_ALLOCATION => $full,
                self::RIGHT_TRANSFER   => $full,
                self::RIGHT_CONFIRM    => $full,
                self::RIGHT_CONFIG     => READ | UPDATE | CREATE,
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
            // Own location: allocation + view/edit equipment & containers (no create/delete/transfer/bulk/config).
            'support_tech' => [
                self::RIGHT_EQUIPMENT  => READ | UPDATE | READ_ASSIGNED | UPDATE_ASSIGNED,
                self::RIGHT_CONTAINER  => READ | UPDATE,
                self::RIGHT_BULK       => 0,
                self::RIGHT_ALLOCATION => READ | CREATE | UPDATE,
                self::RIGHT_TRANSFER   => 0,
                self::RIGHT_CONFIRM    => READ | UPDATE,
                self::RIGHT_CONFIG     => 0,
            ] + $zero,
            default => [ // end user — confirm receipt only (no equipment list)
                self::RIGHT_EQUIPMENT  => 0,
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
     * Find bundled profile id by known name variants, then role mapping
     * (skipping Super-Admin, which also maps to central_admin).
     */
    public static function findBundledProfileId(string $role, string $name_msgid): int
    {
        global $DB;

        if (!$DB->tableExists('glpi_profiles')) {
            return 0;
        }

        $names = self::getProfileNameVariants($name_msgid);
        if ($names !== []) {
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_profiles',
                'WHERE'  => ['name' => $names],
                'LIMIT'  => 1,
            ]) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }

        if ($role === '' || !$DB->tableExists(self::getTable())) {
            return 0;
        }

        foreach ($DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['role' => $role],
        ]) as $row) {
            $id = (int) ($row['profiles_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $is_super = false;
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_profiles',
                'WHERE'  => [
                    'id'   => $id,
                    'name' => 'Super-Admin',
                ],
                'LIMIT'  => 1,
            ]) as $_) {
                $is_super = true;
            }
            if (!$is_super) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * Sync GLPI is_default on a profile.
     * When true: mark this profile as the exclusive default for new users.
     * When false: clear is_default on this profile only (does not choose another default).
     */
    public static function setProfileAsDefault(int $profiles_id, bool $is_default): void
    {
        global $DB;

        if ($profiles_id <= 0
            || !$DB->tableExists('glpi_profiles')
            || !$DB->fieldExists('glpi_profiles', 'is_default')
        ) {
            return;
        }

        if (!$is_default) {
            $DB->update('glpi_profiles', ['is_default' => 0], ['id' => $profiles_id]);
            return;
        }

        $DB->update('glpi_profiles', ['is_default' => 0], [
            'is_default' => 1,
            ['NOT' => ['id' => $profiles_id]],
        ]);
        $DB->update('glpi_profiles', ['is_default' => 1], ['id' => $profiles_id]);
    }

    /**
     * Sync profile name/comment to the active language translation.
     */
    public static function syncProfileLocalizedLabels(
        int $profiles_id,
        string $name_msgid,
        string $comment_msgid
    ): void {
        global $DB;

        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profiles')) {
            return;
        }

        $name = self::translateProfileLabel($name_msgid);
        $comment = self::translateProfileLabel($comment_msgid);
        $fields = ['name' => $name];
        if ($DB->fieldExists('glpi_profiles', 'comment')) {
            $fields['comment'] = $comment;
        }

        $current_name = '';
        $current_comment = '';
        foreach ($DB->request([
            'SELECT' => $DB->fieldExists('glpi_profiles', 'comment')
                ? ['name', 'comment']
                : ['name'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['id' => $profiles_id],
            'LIMIT'  => 1,
        ]) as $row) {
            $current_name = (string) ($row['name'] ?? '');
            $current_comment = (string) ($row['comment'] ?? '');
        }

        if ($current_name === $name
            && (!$DB->fieldExists('glpi_profiles', 'comment') || $current_comment === $comment)
        ) {
            return;
        }

        $DB->update('glpi_profiles', $fields, ['id' => $profiles_id]);
    }

    /**
     * Find a stock GLPI profile id by one of the given names (Admin, Technician, Super-Admin…).
     * Super-Admin is used only when explicitly listed (Central admin seed).
     *
     * @param list<string> $names
     */
    public static function findTemplateProfileId(array $names): int
    {
        global $DB;

        if ($names === [] || !$DB->tableExists('glpi_profiles')) {
            return 0;
        }

        $allow_super = false;
        foreach ($names as $n) {
            if (strcasecmp((string) $n, 'Super-Admin') === 0) {
                $allow_super = true;
                break;
            }
        }

        $where = ['name' => $names];
        if (!$allow_super) {
            // Skip Super-Admin for Admin/Technician/Self-Service templates.
            $where[] = ['NOT' => ['name' => 'Super-Admin']];
        }

        // Prefer exact name match.
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => $where,
            'LIMIT'  => 1,
        ]) as $row) {
            return (int) ($row['id'] ?? 0);
        }

        // Fallback: case-insensitive match on known templates.
        $fallback = [
            'SELECT' => ['id', 'name', 'interface'],
            'FROM'   => 'glpi_profiles',
        ];
        if (!$allow_super) {
            $fallback['WHERE'] = [['NOT' => ['name' => 'Super-Admin']]];
        }
        foreach ($DB->request($fallback) as $row) {
            $pname = trim((string) ($row['name'] ?? ''));
            if ($pname === '') {
                continue;
            }
            foreach ($names as $want) {
                if (strcasecmp($pname, $want) === 0) {
                    return (int) $row['id'];
                }
            }
        }

        return 0;
    }

    /**
     * How many non-plugin ProfileRight rows a profile currently has.
     */
    public static function countCoreRights(int $profiles_id): int
    {
        global $DB;

        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profilerights')) {
            return 0;
        }

        $plugin_names = self::getRightNames();
        $plugin_names[] = self::LEGACY_RIGHT;
        $n = 0;
        foreach ($DB->request([
            'SELECT' => ['name'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $profiles_id],
        ]) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || in_array($name, $plugin_names, true)) {
                continue;
            }
            if (str_starts_with($name, 'plugin_auchanassettracker')) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /** Config flag: seed of Super-Admin/Admin/Technician/Self-Service rights onto bundled profiles. */
    public const CFG_CORE_RIGHTS_SEEDED = 'core_rights_seeded_v5';

    /**
     * Copy non-plugin ProfileRight rows from a template profile (create only).
     * Client can edit GLPI rights afterwards; we do not overwrite on later loads.
     *
     * @param list<string> $template_names
     */
    public static function cloneCoreRightsFromTemplate(int $profiles_id, array $template_names): void
    {
        global $DB;

        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profilerights')) {
            return;
        }

        $source_id = self::findTemplateProfileId($template_names);
        if ($source_id <= 0 || $source_id === $profiles_id) {
            return;
        }

        $plugin_names = self::getRightNames();
        $plugin_names[] = self::LEGACY_RIGHT;

        foreach ($DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => $source_id],
        ]) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || in_array($name, $plugin_names, true)) {
                continue;
            }
            if (str_starts_with($name, 'plugin_auchanassettracker')) {
                continue;
            }

            $exists = false;
            foreach ($DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => [
                    'profiles_id' => $profiles_id,
                    'name'        => $name,
                ],
                'LIMIT' => 1,
            ]) as $_) {
                $exists = true;
            }

            $rights = (int) ($row['rights'] ?? 0);
            if ($exists) {
                $DB->update('glpi_profilerights', [
                    'rights' => $rights,
                ], [
                    'profiles_id' => $profiles_id,
                    'name'        => $name,
                ]);
            } else {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $name,
                    'rights'      => $rights,
                ]);
            }
        }
    }

    /**
     * Set interface (central / helpdesk) on a profile.
     */
    public static function setProfileInterface(int $profiles_id, string $interface): void
    {
        global $DB;

        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profiles')) {
            return;
        }
        if (!in_array($interface, ['central', 'helpdesk'], true)) {
            return;
        }
        if (!$DB->fieldExists('glpi_profiles', 'interface')) {
            return;
        }

        $DB->update('glpi_profiles', [
            'interface' => $interface,
        ], ['id' => $profiles_id]);
    }

    /**
     * Find a GLPI profile by msgid (any language variant), or create it.
     */
    public static function findOrCreateProfileByName(
        string $name_msgid,
        string $comment_msgid = '',
        bool $is_default = false,
        string $role = '',
        string $interface = 'central'
    ): int {
        global $DB;

        if ($name_msgid === '' || !$DB->tableExists('glpi_profiles')) {
            return 0;
        }

        if (!in_array($interface, ['central', 'helpdesk'], true)) {
            $interface = 'central';
        }

        $id = self::findBundledProfileId($role, $name_msgid);
        if ($id > 0) {
            self::syncProfileLocalizedLabels($id, $name_msgid, $comment_msgid);
            self::setProfileAsDefault($id, $is_default);
            return $id;
        }

        $name = self::translateProfileLabel($name_msgid);
        $comment = self::translateProfileLabel($comment_msgid);

        $input = [
            'name'      => $name,
            'interface' => $interface,
            'comment'   => $comment,
        ];
        if ($DB->fieldExists('glpi_profiles', 'is_default')) {
            $input['is_default'] = $is_default ? 1 : 0;
        }

        $profile = new Profile();
        $new_id = (int) $profile->add($input);
        if ($new_id > 0) {
            self::setProfileAsDefault($new_id, $is_default);
            return $new_id;
        }

        // Fallback if Profile::add is unavailable / blocked in this context.
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $row = [
            'name'      => $name,
            'interface' => $interface,
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
            if ($id > 0) {
                self::setProfileAsDefault($id, $is_default);
            }
            return $id;
        }

        return 0;
    }

    /**
     * Create the four Auchan Asset Tracker profiles if missing and bind roles + rights.
     *
     * @param bool $force_rights When true (install/upgrade), re-apply plugin role rights + interface defaults.
     */
    public static function ensureBundledProfiles(bool $force_rights = false): void
    {
        global $DB;

        if (!$DB->tableExists('glpi_profiles') || !$DB->tableExists(self::getTable())) {
            return;
        }

        foreach (self::getBundledProfiles() as $role => $meta) {
            $before = self::findBundledProfileId($role, (string) $meta['name']);
            $interface = (string) ($meta['interface'] ?? 'central');
            /** @var list<string> $clone_from */
            $clone_from = $meta['clone_from'] ?? [];

            $profiles_id = self::findOrCreateProfileByName(
                (string) $meta['name'],
                (string) ($meta['comment'] ?? ''),
                !empty($meta['is_default']),
                $role,
                $interface
            );
            if ($profiles_id <= 0) {
                continue;
            }

            $created = $before <= 0;
            $existing = self::getForProfileId($profiles_id);
            $role_mismatch = $existing === null
                || (string) ($existing['role'] ?? '') !== $role;

            // GLPI native rights: seed from Admin / Technician / Self-Service (changeable after).
            // Re-seed once per upgrade flag so existing empty profiles pick up Admin/Technician
            // without uninstalling.
            $seed_core = $created;
            if (!$seed_core && $force_rights) {
                $already = class_exists('PluginAuchanassettrackerConfig', false)
                    ? PluginAuchanassettrackerConfig::get(self::CFG_CORE_RIGHTS_SEEDED)
                    : null;
                if ($already !== '1') {
                    $seed_core = true;
                }
            }

            if ($seed_core) {
                self::cloneCoreRightsFromTemplate($profiles_id, $clone_from);
            }

            if ($created || $force_rights) {
                self::setProfileInterface($profiles_id, $interface);
            }

            self::syncProfileLocalizedLabels(
                $profiles_id,
                (string) $meta['name'],
                (string) ($meta['comment'] ?? '')
            );

            if ($created || $role_mismatch || $force_rights) {
                self::saveFromPost([
                    'profiles_id' => $profiles_id,
                    'role'        => $role,
                ]);
                // Plugin module ProfileRight bits only for CommonDBTM/Session helpers;
                // tab visibility is role-based in Righthelper.
                self::applyRightsForRole($profiles_id, $role);
            } else {
                self::ensureRightsRowsForProfile(
                    $profiles_id,
                    self::rightsFromLegacyRole($role)
                );
            }
        }

        if ($force_rights && class_exists('PluginAuchanassettrackerConfig', false)) {
            PluginAuchanassettrackerConfig::set(self::CFG_CORE_RIGHTS_SEEDED, '1');
        }
    }

    /**
     * Whether glpi_profilerights for plugin modules match the role preset exactly.
     */
    public static function pluginRightsMatchRole(int $profiles_id, string $role): bool
    {
        global $DB;

        if ($profiles_id <= 0 || !$DB->tableExists('glpi_profilerights')) {
            return false;
        }

        $expected = self::rightsFromLegacyRole($role);
        $found = [];
        foreach ($DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profiles_id,
                'name'        => array_keys($expected),
            ],
        ]) as $row) {
            $found[(string) $row['name']] = (int) ($row['rights'] ?? 0);
        }

        foreach ($expected as $name => $rights) {
            if (!array_key_exists($name, $found) || $found[$name] !== (int) $rights) {
                return false;
            }
        }

        return true;
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
                }
                // Unmapped profiles: leave plugin rights at 0 (do not force End user).
            }

            // Seed missing plugin right rows only (value 0 for unmapped / new rows).
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

            // Central admin: ensure Configuration rights (menu + save thresholds).
            // Row is seeded above; only UPDATE (never re-INSERT — unicity 1062 disables plugin).
            $mapping = self::getForProfileId($profiles_id);
            if (!$isSuper
                && $mapping !== null
                && (string) ($mapping['role'] ?? '') === 'central_admin'
            ) {
                $cfg_rights = READ | UPDATE | CREATE;
                $DB->update('glpi_profilerights', [
                    'rights' => $cfg_rights,
                ], [
                    'profiles_id' => $profiles_id,
                    'name'        => self::RIGHT_CONFIG,
                ]);
                if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === $profiles_id) {
                    $_SESSION['glpiactiveprofile'][self::RIGHT_CONFIG] = $cfg_rights;
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
     * GLPI-style rights matrix only (editable per plugin module).
     * Internal role mapping stays for bundled profiles / location scope — not shown here.
     */
    public function showForm($ID, array $options = []): bool
    {
        $profiles_id = (int) $ID;
        $canedit = Session::haveRight('profile', UPDATE);

        $profile = new Profile();
        if (!$profile->getFromDB($profiles_id)) {
            return false;
        }
        // Reload so matrix checkboxes see current profilerights.
        $profile->getFromDB($profiles_id);

        self::ensureRightsRowsForProfile($profiles_id);

        $action = plugin_auchanassettracker_web_dir() . '/front/profile.form.php';

        echo "<div class='aat-profile-wrap firstbloc'>";

        if ($canedit) {
            echo "<form method='post' action='" . Html::entities_deep($action) . "'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
        }

        // Native GLPI checkbox matrix (Read / Update / Create / Delete / …).
        $profile->displayRightsChoiceMatrix(self::getAllRights(true), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Auchan Asset Tracker', 'auchanassettracker'),
        ]);

        if ($canedit) {
            echo "<div class='form-button-separator card-body mx-n2 mb-n2 border-top"
                . " d-flex flex-row-reverse align-items-center flex-wrap gap-2'>";
            echo "<button class='btn btn-primary me-2' type='submit' name='update' value='1'>";
            echo "<i class='ti ti-device-floppy'></i> ";
            echo "<span>" . Html::entities_deep(_sx('button', 'Save')) . "</span>";
            echo "</button>";
            echo "</div>";
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
     * Whether the posted role differs from the stored mapping.
     */
    public static function roleChangedInPost(array $post): bool
    {
        $profiles_id = (int) ($post['profiles_id'] ?? $post['id'] ?? 0);
        if ($profiles_id <= 0 || !isset($post['role'])) {
            return false;
        }
        $roles = array_keys(PluginAuchanassettrackerRighthelper::getRoles());
        $role = (string) $post['role'];
        if (!in_array($role, $roles, true)) {
            $role = PluginAuchanassettrackerRighthelper::ROLE_USER;
        }
        $existing = self::getForProfileId($profiles_id);
        if ($existing === null) {
            return true;
        }
        return (string) ($existing['role'] ?? '') !== $role;
    }

    /**
     * Upsert role mapping for a profile.
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
