<?php

/**
 * Maps a GLPI profile to an Asset Tracker role (+ optional location).
 */
class PluginAuchanassettrackerProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0): string
    {
        return __('Auchan Asset Tracker roles', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_profiles';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            return self::createTabEntry(__('Auchan Asset Tracker', 'auchanassettracker'));
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

            // Map Super-Admin → central_admin role.
            if ($isSuper) {
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

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
        }

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . __('Auchan Asset Tracker role', 'auchanassettracker') . "</th></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Role', 'auchanassettracker') . "</td><td>";
        Dropdown::showFromArray('role', PluginAuchanassettrackerRighthelper::getRoles(), [
            'value'  => $current['role'] ?? 'user',
            'width'  => '100%',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location (for managers / technicians)', 'auchanassettracker') . "</td><td>";
        Location::dropdown([
            'name'   => 'locations_id',
            'value'  => (int) ($current['locations_id'] ?? 0),
            'width'  => '100%',
        ]);
        echo "</td></tr>";

        if ($canedit) {
            echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</td></tr>";
        }

        echo "</table>";
        if ($canedit) {
            Html::closeForm();
        }
        echo "</div>";
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
     */
    public static function saveFromPost(array $post): bool
    {
        global $DB;

        $profiles_id = (int) ($post['profiles_id'] ?? 0);
        if ($profiles_id <= 0) {
            return false;
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
            return (bool) $DB->update(self::getTable(), [
                'role'         => $role,
                'locations_id' => $locations_id,
                'date_mod'     => $now,
            ], ['id' => (int) $existing['id']]);
        }

        return (bool) $DB->insert(self::getTable(), [
            'profiles_id'   => $profiles_id,
            'role'          => $role,
            'locations_id'  => $locations_id,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }
}
