<?php

/**
 * Find glpi_plugins rows that belong to this plugin (any directory casing).
 *
 * @return list<array<string, mixed>>
 */
function plugin_auchanassettracker_find_plugin_rows(): array
{
    global $DB;

    $rows = [];
    if (!$DB->tableExists('glpi_plugins')) {
        return $rows;
    }

    foreach ($DB->request(['FROM' => 'glpi_plugins']) as $row) {
        if (strcasecmp((string) ($row['directory'] ?? ''), 'auchanassettracker') === 0) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Keep a single glpi_plugins row and force directory = exact disk folder name.
 *
 * @return array{id: int, version: string, state: int}|null
 */
function plugin_auchanassettracker_sync_plugin_directory(): ?array
{
    global $DB;

    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows === []) {
        return null;
    }

    // Exact on-disk folder (e.g. AuchanAssetTracker). Never invent another casing.
    $canonical = plugin_auchanassettracker_dir();

    // Prefer a row that already matches the disk name; otherwise rewrite the first.
    $keep = null;
    foreach ($rows as $row) {
        if ((string) $row['directory'] === $canonical) {
            $keep = $row;
            break;
        }
    }
    if ($keep === null) {
        $keep = $rows[0];
    }

    $keepId = (int) $keep['id'];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if ($id !== $keepId) {
            $DB->delete('glpi_plugins', ['id' => $id]);
        }
    }

    $DB->update('glpi_plugins', [
        'directory' => $canonical,
        'name'      => 'AuchanAssetTracker',
    ], ['id' => $keepId]);

    // Re-read state/version after update in case another heal changed them.
    $version = (string) ($keep['version'] ?? '');
    $state = (int) ($keep['state'] ?? 2);
    foreach ($DB->request([
        'FROM'  => 'glpi_plugins',
        'WHERE' => ['id' => $keepId],
        'LIMIT' => 1,
    ]) as $fresh) {
        $version = (string) ($fresh['version'] ?? $version);
        $state = (int) ($fresh['state'] ?? $state);
    }

    return [
        'id'      => $keepId,
        'version' => $version,
        'state'   => $state,
    ];
}

/**
 * Heal directory/version drift as soon as setup.php is loaded.
 *
 * GLPI compares folder name vs glpi_plugins.directory with PHP (case-sensitive).
 * A wrong casing creates a ghost DB row ("unable to load") plus a real folder
 * marked "version changed". This heal collapses rows to the disk name and
 * finishes the upgrade so the plugin stays usable.
 */
function plugin_auchanassettracker_self_heal_on_load(): void
{
    global $DB;

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // Never interfere while GLPI is uninstalling/disabling this plugin.
    if (!empty($GLOBALS['plugin_auchanassettracker_maintenance'])) {
        return;
    }

    if (!isset($DB) || !is_object($DB) || !method_exists($DB, 'tableExists')) {
        return;
    }

    try {
        if (!$DB->tableExists('glpi_plugins')) {
            return;
        }
    } catch (Throwable) {
        return;
    }

    $info = plugin_auchanassettracker_sync_plugin_directory();
    if ($info === null) {
        return;
    }

    $canonical = plugin_auchanassettracker_dir();
    $needsVersion = $info['version'] !== PLUGIN_AUCHANASSETTRACKER_VERSION;
    // GLPI: ACTIVATED=1, NOTACTIVATED=2, TOBECONFIGURED=3, NOTINSTALLED=4, NOTUPDATED=6
    $state = (int) $info['state'];

    // Respect explicit uninstall / disable — only sync the directory name.
    if ($state === 4 || $state === 2) {
        return;
    }

    $wasPendingUpdate = ($state === 6);
    $wasActive = ($state === 1);

    if (!$needsVersion && !$wasPendingUpdate) {
        return;
    }

    static $upgrading = false;
    if ($upgrading) {
        return;
    }
    $upgrading = true;

    try {
        if (($needsVersion || $wasPendingUpdate) && function_exists('plugin_auchanassettracker_upgrade')) {
            plugin_auchanassettracker_upgrade($info['version']);
        }

        // Only auto-activate when updating from ACTIVATED or NOTUPDATED.
        $newState = ($wasActive || $wasPendingUpdate) ? 1 : $state;

        $DB->update('glpi_plugins', [
            'directory' => $canonical,
            'version'   => PLUGIN_AUCHANASSETTRACKER_VERSION,
            'name'      => 'AuchanAssetTracker',
            'state'     => $newState,
        ], ['id' => $info['id']]);
    } catch (Throwable) {
        // Never break GLPI boot because of plugin self-heal.
    } finally {
        $upgrading = false;
    }
}

function plugin_auchanassettracker_install(array $params = []): bool
{
    global $DB;

    $sqlFile = __DIR__ . '/install/install.sql';
    if (is_readable($sqlFile)) {
        foreach (explode(';', (string) file_get_contents($sqlFile)) as $query) {
            $query = trim($query);
            if ($query !== '') {
                $DB->doQuery($query);
            }
        }
    }

    plugin_auchanassettracker_ensure_schema();

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerEquipmenttype', false)) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerManufacturer', false)) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerProfile', false)) {
        PluginAuchanassettrackerProfile::initProfile();
    }

    plugin_auchanassettracker_sync_plugin_directory();
    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows !== []) {
        $DB->update('glpi_plugins', [
            'version' => PLUGIN_AUCHANASSETTRACKER_VERSION,
        ], ['id' => (int) $rows[0]['id']]);
    }

    plugin_auchanassettracker_clear_translation_cache();

    return true;
}

function plugin_auchanassettracker_upgrade($version): bool
{
    global $DB;

    if (is_readable(__DIR__ . '/install/install.sql')) {
        foreach (explode(';', (string) file_get_contents(__DIR__ . '/install/install.sql')) as $query) {
            $query = trim($query);
            if ($query !== '') {
                $DB->doQuery($query);
            }
        }
    }

    plugin_auchanassettracker_ensure_schema();

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerEquipmenttype', false)) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerManufacturer', false)) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerProfile', false)) {
        PluginAuchanassettrackerProfile::initProfile();
    }

    plugin_auchanassettracker_sync_plugin_directory();
    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows !== []) {
        $DB->update('glpi_plugins', [
            'version' => PLUGIN_AUCHANASSETTRACKER_VERSION,
        ], ['id' => (int) $rows[0]['id']]);
    }

    plugin_auchanassettracker_clear_translation_cache();

    return true;
}

function plugin_auchanassettracker_clear_translation_cache(): void
{
    if (!class_exists(\Glpi\Cache\CacheManager::class, false)) {
        return;
    }

    try {
        (new \Glpi\Cache\CacheManager())->getTranslationsCacheInstance()->clear();
    } catch (Throwable) {
        // Ignore cache backend issues during upgrade.
    }
}

function plugin_auchanassettracker_uninstall(): bool
{
    // Soft uninstall: keep tables/data so a later install can reuse them.
    // Flag blocks self-heal from fighting GLPI's uninstall state change.
    $GLOBALS['plugin_auchanassettracker_maintenance'] = true;

    try {
        plugin_auchanassettracker_clear_translation_cache();
    } catch (Throwable) {
        // Still allow GLPI to mark the plugin uninstalled.
    }

    return true;
}

function plugin_auchanassettracker_getDatabaseRelations(): array
{
    return [];
}

/**
 * Add columns introduced after first install (CREATE TABLE IF NOT EXISTS won't alter).
 * Also heal leftover UNIQUE qr_token from older installs (empty '' collides).
 */
function plugin_auchanassettracker_ensure_schema(): void
{
    global $DB;

    $table = 'glpi_plugin_auchanassettracker_equipments';
    if ($DB->tableExists($table)) {
        $columns = [
            'itemtype'         => "VARCHAR(100) NOT NULL DEFAULT 'Computer'",
            'items_id'         => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'manufacturers_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'models_id'        => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'users_id'         => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'is_recursive'     => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $name => $definition) {
            if ($DB->fieldExists($table, $name)) {
                continue;
            }
            try {
                $DB->doQuery("ALTER TABLE `$table` ADD `$name` $definition");
            } catch (Throwable $e) {
                // Race / stale field cache: column already exists (MySQL 1060).
                $msg = $e->getMessage();
                if (!str_contains($msg, '1060') && !str_contains($msg, 'Duplicate column')) {
                    throw $e;
                }
            }
        }
    }

    $containers = 'glpi_plugin_auchanassettracker_containers';
    if ($DB->tableExists($containers) && !$DB->fieldExists($containers, 'is_recursive')) {
        try {
            $DB->doQuery(
                "ALTER TABLE `$containers` ADD `is_recursive` TINYINT(1) NOT NULL DEFAULT 0"
            );
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (!str_contains($msg, '1060') && !str_contains($msg, 'Duplicate column')) {
                throw $e;
            }
        }
    }

    $alloc = 'glpi_plugin_auchanassettracker_allocations';
    if ($DB->tableExists($alloc)
        && !$DB->fieldExists($alloc, 'plugin_auchanassettracker_containers_id_previous')) {
        try {
            $DB->doQuery(
                "ALTER TABLE `$alloc`
                 ADD `plugin_auchanassettracker_containers_id_previous` INT UNSIGNED NOT NULL DEFAULT 0"
            );
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (!str_contains($msg, '1060') && !str_contains($msg, 'Duplicate column')) {
                throw $e;
            }
        }
    }

    // Sprint 2 tables (CREATE IF NOT EXISTS is safe on every load).
    if (is_readable(__DIR__ . '/install/install.sql')) {
        foreach (explode(';', (string) file_get_contents(__DIR__ . '/install/install.sql')) as $query) {
            $query = trim($query);
            if ($query !== '' && (
                str_contains($query, 'glpi_plugin_auchanassettracker_configs')
                || str_contains($query, 'glpi_plugin_auchanassettracker_allocations')
                || str_contains($query, 'glpi_plugin_auchanassettracker_notices')
            )) {
                $DB->doQuery($query);
            }
        }
    }

    // Older full-plugin installs kept UNIQUE qr_token DEFAULT ''.
    // Backfill empties so new inserts no longer hit duplicate-key 1062.
    $containers = 'glpi_plugin_auchanassettracker_containers';
    if ($DB->tableExists($containers) && $DB->fieldExists($containers, 'qr_token')) {
        foreach ($DB->request([
            'SELECT' => ['id', 'qr_token'],
            'FROM'   => $containers,
        ]) as $row) {
            $token = trim((string) ($row['qr_token'] ?? ''));
            if ($token !== '') {
                continue;
            }
            $DB->update($containers, [
                'qr_token' => bin2hex(random_bytes(16)),
            ], ['id' => (int) $row['id']]);
        }
    }

    plugin_auchanassettracker_ensure_equipment_displayprefs();
}

/**
 * Seed default Equipment search columns once (users_id = 0 only).
 * Never re-add columns after the user removes them via “Select items to show”.
 */
function plugin_auchanassettracker_ensure_equipment_displayprefs(): void
{
    global $DB;

    if (!$DB->tableExists('glpi_displaypreferences')) {
        return;
    }

    $itemtype = 'PluginAuchanassettrackerEquipment';
    $existing = 0;
    foreach ($DB->request([
        'COUNT' => 'cpt',
        'FROM'  => 'glpi_displaypreferences',
        'WHERE' => [
            'itemtype' => $itemtype,
            'users_id' => 0,
        ],
    ]) as $row) {
        $existing = (int) ($row['cpt'] ?? 0);
    }
    if ($existing > 0) {
        return;
    }

    $default_nums = [1, 8, 2, 4, 5, 7, 11, 12, 13];
    $rank = 1;
    foreach ($default_nums as $num) {
        try {
            $DB->insert('glpi_displaypreferences', [
                'itemtype' => $itemtype,
                'num'      => $num,
                'rank'     => $rank,
                'users_id' => 0,
            ]);
            $rank++;
        } catch (Throwable $e) {
            // Duplicate / race — ignore.
        }
    }
}

/**
 * Register Auchan Asset Tracker as its own top-level menu sector.
 *
 * @param array<string, mixed> $menu
 * @return array<string, mixed>
 */
function plugin_auchanassettracker_redefine_menus(array $menu): array
{
    // Remove any leftover entries under Assets from older versions.
    if (isset($menu['assets']['content']) && is_array($menu['assets']['content'])) {
        foreach ($menu['assets']['content'] as $key => $_) {
            $key_s = (string) $key;
            if (stripos($key_s, 'auchanassettracker') !== false || str_starts_with($key_s, 'aat_')) {
                unset($menu['assets']['content'][$key]);
            }
        }
    }

    $ours = PluginAuchanassettrackerMenu::getMenuContent();
    if ($ours === false) {
        unset($menu[PluginAuchanassettrackerMenu::SECTOR]);
        return $menu;
    }

    $menu[PluginAuchanassettrackerMenu::SECTOR] = [
        'title'   => $ours['title'],
        'default' => $ours['page'],
        'icon'    => $ours['icon'],
        'content' => $ours['content'],
    ];

    return $menu;
}

// When GLPI includes setup.php during plugin state checks, heal DB drift first.
plugin_auchanassettracker_self_heal_on_load();
