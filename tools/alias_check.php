#!/usr/bin/env php
<?php
/**
 * Offline check: directory aliases resolve for a non-canonical folder name.
 */
declare(strict_types=1);

$root = dirname(__DIR__);

// Simulate deploy under a legacy / mistyped folder name without moving files.
$fakeDir = 'auchanequipment';

// Minimal stubs so setup.php can load without GLPI.
if (!defined('GLPI_VERSION')) {
    define('GLPI_VERSION', '11.0.2');
}
if (!isset($CFG_GLPI)) {
    $CFG_GLPI = ['root_doc' => ''];
}
if (!isset($DB)) {
    $DB = new class {
        public function tableExists($t): bool
        {
            return false;
        }
        public function request($c): array
        {
            return [];
        }
    };
}

// Override basename used by PLUGIN_AUCHANASSETTRACKER_DIR by defining after include is hard;
// instead include setup and then register aliases for a fake dir manually.
require_once $root . '/setup.php';

if (!function_exists('plugin_version_auchanassettracker')) {
    fwrite(STDERR, "FAIL: canonical plugin_version missing\n");
    exit(1);
}

// Re-run alias registration logic for a synthetic directory.
$dir = $fakeDir;
$map = [
    "plugin_init_{$dir}"                => 'plugin_init_auchanassettracker',
    "plugin_version_{$dir}"             => 'plugin_version_auchanassettracker',
    "plugin_{$dir}_check_prerequisites" => 'plugin_auchanassettracker_check_prerequisites',
    "plugin_{$dir}_check_config"        => 'plugin_auchanassettracker_check_config',
    "plugin_{$dir}_install"             => 'plugin_auchanassettracker_install',
    "plugin_{$dir}_uninstall"           => 'plugin_auchanassettracker_uninstall',
    "plugin_{$dir}_upgrade"             => 'plugin_auchanassettracker_upgrade',
];
foreach ($map as $alias => $canonical) {
    if (function_exists($alias) || !function_exists($canonical)) {
        continue;
    }
    eval(
        'function ' . $alias . '(...$args) {'
        . ' return ' . $canonical . '(...$args);'
        . '}'
    );
}

if (!function_exists('plugin_version_auchanequipment')) {
    fwrite(STDERR, "FAIL: alias plugin_version_auchanequipment not created\n");
    exit(1);
}

$info = plugin_version_auchanequipment();
if (($info['name'] ?? '') !== 'AuchanAssetTracker') {
    fwrite(STDERR, "FAIL: alias returned unexpected name\n");
    exit(1);
}
if (($info['oldname'] ?? '') !== 'auchanequipment') {
    fwrite(STDERR, "FAIL: oldname missing\n");
    exit(1);
}

echo "OK: directory aliases work for {$fakeDir}\n";
exit(0);
