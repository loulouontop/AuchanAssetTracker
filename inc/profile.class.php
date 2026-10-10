<?php

/**
 * Maps a GLPI profile to an Asset Tracker role (+ optional location).
 */
class PluginAuchanassettrackerProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0): string
    {
        return __('AuchanAssetTracker roles', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_profiles';
    }

    public static function getIcon(): string
    {
        return 'ti ti-packages';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && Session::haveRight('profile', READ)) {
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
            self::showForProfile($item);
            return true;
        }
        return false;
    }

    public static function initProfile(): void
    {
        global $DB, $GLPI_CACHE;

        // Register plugin right so CommonDBTM checks pass; fine-grained roles live in our mapping table.
        // Do not use ProfileRight::addProfileRights() — it always INSERT and fails on reinstall.
        if (!$DB->tableExists('glpi_profilerights') || !$DB->tableExists('glpi_profiles')) {
            return;
        }

        if (isset($GLPI_CACHE) && is_object($GLPI_CACHE) && method_exists($GLPI_CACHE, 'set')) {
            $GLPI_CACHE->set('all_possible_rights', []);
        }

        $rightName = 'plugin_auchanassettracker';
        $fullRights = ALLSTANDARDRIGHT | READNOTE | UPDATENOTE;

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
            $exists = false;

            foreach ($DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => [
                    'profiles_id' => $profiles_id,
                    'name'        => $rightName,
                ],
                'LIMIT' => 1,
            ]) as $_) {
                $exists = true;
            }

            if (!$exists) {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $rightName,
                    'rights'      => $isSuper ? $fullRights : 0,
                ]);
            } elseif ($isSuper) {
                $DB->update('glpi_profilerights', [
                    'rights' => $fullRights,
                ], [
                    'profiles_id' => $profiles_id,
                    'name'        => $rightName,
                ]);
            }

            // Default Super-Admin → central_admin only when no mapping exists yet
            // (do not overwrite a manual End user / other role mapping).
            if ($isSuper && self::getForProfileId($profiles_id) === null) {
                self::saveFromPost([
                    'profiles_id'  => $profiles_id,
                    'role'         => PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN,
                    'locations_id' => 0,
                ]);
            }
        }
    }

    public static function getForCurrentProfile(): ?array
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return null;
        }

        $profiles_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($profiles_id <= 0) {
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

    public static function showForProfile(Profile $profile): void
    {
        $profiles_id = (int) $profile->getID();
        $canedit = Session::haveRight('profile', UPDATE);
        $current = self::getForProfileId($profiles_id) ?? [
            'role'         => PluginAuchanassettrackerRighthelper::ROLE_USER,
            'locations_id' => 0,
        ];

        $action = plugin_auchanassettracker_web_dir() . '/front/profile.form.php';
        $role = (string) ($current['role'] ?? PluginAuchanassettrackerRighthelper::ROLE_USER);
        $locations_id = (int) ($current['locations_id'] ?? 0);

        echo "<div class='aat-profile-wrap'>";
        echo "<div class='aat-profile-form mx-auto'>";

        // Own form + explicit CSRF. Do not use Html::closeForm() here: Profile
        // pages already open a GLPI form stack, and closeForm() then emits the
        // wrong token (AccessDeniedHttpException on save).
        if ($canedit) {
            echo "<form method='post' action='" . Html::entities_deep($action) . "'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
        }

        echo "<div class='card'>";
        echo "<div class='card-header aat-profile-card-header'>"
            . Html::entities_deep(__('Auchan Asset Tracker', 'auchanassettracker'))
            . "</div>";
        echo "<div class='card-body aat-profile-card-body'>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label'>"
            . Html::entities_deep(__('Role', 'auchanassettracker'))
            . "</label>";
        echo "<div class='aat-field-control aat-role-control'>";
        Dropdown::showFromArray('role', PluginAuchanassettrackerRighthelper::getRoles(), [
            'value' => $role,
            'width' => '320px',
        ]);
        echo "</div>";
        echo "</div>";

        echo "<div class='mb-3'>";
        echo "<label class='form-label'>"
            . Html::entities_deep(__('Location'))
            . "</label>";
        echo "<div class='aat-field-control aat-location-control'>";
        Location::dropdown([
            'name'  => 'locations_id',
            'value' => $locations_id,
            'width' => '320px',
        ]);
        echo "</div>";
        echo "<div class='form-text'>"
            . Html::entities_deep(__(
                'Set a location to limit Equipment and containers to that site (including Central admin). Leave empty for Central admin to see all locations.',
                'auchanassettracker'
            ))
            . "</div>";
        echo "</div>";

        echo "</div>"; // card-body

        if ($canedit) {
            // GLPI-style footer: primary action on the right
            echo "<div class='card-footer mx-n2 mb-n2 d-flex flex-row-reverse align-items-center flex-wrap gap-2'>";
            echo Html::submit(_sx('button', 'Save'), [
                'name'  => 'update_aat_profile',
                'class' => 'btn btn-primary',
            ]);
            echo "</div>";
        }

        echo "</div>"; // card

        if ($canedit) {
            echo "</form>";
        }

        echo "</div>"; // aat-profile-form
        echo "</div>"; // aat-profile-wrap
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInputForAdd($input);
    }

    public function prepareInputForAdd($input)
    {
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_mod'] = $now;
        if (!isset($input['date_creation'])) {
            $input['date_creation'] = $now;
        }
        return $input;
    }

    /**
     * Upsert from profile tab form.
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

        $role = (string) ($post['role'] ?? PluginAuchanassettrackerRighthelper::ROLE_USER);
        $roles = array_keys(PluginAuchanassettrackerRighthelper::getRoles());
        if (!in_array($role, $roles, true)) {
            $role = PluginAuchanassettrackerRighthelper::ROLE_USER;
        }

        $locations_id = (int) ($post['locations_id'] ?? 0);
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $existing = self::getForProfileId($profiles_id);

        if ($existing !== null) {
            $sameRole = (string) ($existing['role'] ?? '') === $role;
            $sameLoc  = (int) ($existing['locations_id'] ?? 0) === $locations_id;
            if ($sameRole && $sameLoc) {
                return 'unchanged';
            }

            $ok = $DB->update(self::getTable(), [
                'role'         => $role,
                'locations_id' => $locations_id,
                'date_mod'     => $now,
            ], ['id' => (int) $existing['id']]);

            return $ok !== false ? 'updated' : 'error';
        }

        $ok = $DB->insert(self::getTable(), [
            'profiles_id'   => $profiles_id,
            'role'          => $role,
            'locations_id'  => $locations_id,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);

        return $ok ? 'created' : 'error';
    }
}
