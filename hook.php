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
 * Keep a single glpi_plugins row and force directory = real folder name.
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

    $canonical = plugin_auchanassettracker_dir();

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
        'name'      => 'Auchan Asset Tracker',
    ], ['id' => $keepId]);

    return [
        'id'      => $keepId,
        'version' => (string) ($keep['version'] ?? ''),
        'state'   => (int) ($keep['state'] ?? 2),
    ];
}

/**
 * Heal directory/version drift as soon as setup.php is loaded.
 *
 * GLPI compares folder name vs glpi_plugins.directory with PHP (case-sensitive).
 * If they differ (or the file version differs), it logs "version changed", sets
 * NOTUPDATED, and Event::log() then warns about $_SESSION during early boot.
 * Running this during setup include fixes the row before that comparison.
 */
function plugin_auchanassettracker_self_heal_on_load(): void
{
    global $DB;

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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
    // GLPI Plugin::NOTUPDATED = 6
    $wasPendingUpdate = ($info['state'] === 6);

    if (!$needsVersion && !$wasPendingUpdate) {
        return;
    }

    static $upgrading = false;
    if ($upgrading) {
        return;
    }
    $upgrading = true;

    try {
        if ($needsVersion && function_exists('plugin_auchanassettracker_upgrade')) {
            plugin_auchanassettracker_upgrade($info['version']);
        }

        // After a successful heal, leave the plugin enabled if it was active
        // or only marked "to update"; otherwise keep NOTACTIVATED.
        $newState = $info['state'];
        if ($wasPendingUpdate || $info['state'] === 1) {
            $newState = 1; // ACTIVATED
        }

        $DB->update('glpi_plugins', [
            'directory' => $canonical,
            'version'   => PLUGIN_AUCHANASSETTRACKER_VERSION,
            'name'      => 'Auchan Asset Tracker',
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

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerConfig', false)) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }
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

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerConfig', false)) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }
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
    // Keep tables so reinstall preserves data (same approach as first plugin).
    plugin_auchanassettracker_clear_translation_cache();
    return true;
}

function plugin_auchanassettracker_getDatabaseRelations(): array
{
    return [];
}

// When GLPI includes setup.php during plugin state checks, heal DB drift first.
plugin_auchanassettracker_self_heal_on_load();
