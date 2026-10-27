#!/usr/bin/env php
<?php
/**
 * Offline check: mistyped plugins/ folder still exposes plugin_version_<dir>.
 */
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/aat_alias_' . bin2hex(random_bytes(4));
$typo = 'auchanassettraacker';
$dir = $tmp . '/' . $typo;

mkdir($dir, 0777, true);

foreach (['setup.php', 'hook.php'] as $file) {
    if (!copy("$root/$file", "$dir/$file")) {
        fwrite(STDERR, "FAIL: copy $file\n");
        exit(1);
    }
}
mkdir("$dir/install", 0777, true);
mkdir("$dir/inc", 0777, true);

// Minimal GLPI stubs so setup/hook can load without a full stack.
if (!defined('GLPI_VERSION')) {
    define('GLPI_VERSION', '11.0.2');
}

class Session
{
    public static function getLoginUserID()
    {
        return false;
    }
}

class Plugin
{
    public static function registerClass($class, $options = []): void
    {
    }
}

// Avoid DB self-heal during this offline check.
$GLOBALS['plugin_auchanassettracker_maintenance'] = true;

require $dir . '/setup.php';

$needed = [
    "plugin_version_{$typo}",
    "plugin_init_{$typo}",
    "plugin_{$typo}_check_prerequisites",
    "plugin_{$typo}_check_config",
    "plugin_{$typo}_boot",
    "plugin_{$typo}_install",
    "plugin_{$typo}_uninstall",
    "plugin_{$typo}_upgrade",
];

$errors = 0;
foreach ($needed as $fn) {
    if (!function_exists($fn)) {
        echo "FAIL: missing alias $fn\n";
        $errors++;
    } else {
        echo "OK: $fn\n";
    }
}

if (plugin_auchanassettracker_dir() !== $typo) {
    echo 'FAIL: dir key expected ' . $typo . ' got ' . plugin_auchanassettracker_dir() . "\n";
    $errors++;
} else {
    echo "OK: PLUGIN dir is typo folder name\n";
}

$info = plugin_version_auchanassettraacker();
if (($info['name'] ?? '') !== 'AuchanAssetTracker') {
    echo "FAIL: version alias did not return plugin info\n";
    $errors++;
} else {
    echo "OK: plugin_version_auchanassettraacker() returns info\n";
}

if (!plugin_auchanassettracker_is_our_plugin_row([
    'directory' => $typo,
    'name'      => 'AuchanAssetTracker',
])) {
    echo "FAIL: is_our_plugin_row should match typo directory\n";
    $errors++;
} else {
    echo "OK: is_our_plugin_row matches typo directory\n";
}

// Cleanup
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
@rmdir($tmp);

echo $errors === 0 ? "\nAlias checks passed.\n" : "\n$errors failure(s).\n";
exit($errors === 0 ? 0 : 1);
