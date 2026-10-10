#!/usr/bin/env php
<?php
/**
 * Offline structure checks (no GLPI required).
 */
$root = dirname(__DIR__);
$errors = 0;

function fail(string $msg): void
{
    global $errors;
    echo "FAIL: $msg\n";
    $errors++;
}

function ok(string $msg): void
{
    echo "OK: $msg\n";
}

foreach ([
    'setup.php', 'hook.php', 'install/install.sql',
    'inc/equipment.class.php', 'inc/container.class.php', 'inc/allocation.class.php',
    'inc/transfer.class.php', 'inc/transferitem.class.php', 'inc/tickethook.class.php',
    'inc/config.class.php', 'public/css/assettracker.css',
    'front/transfer.php', 'front/transfer.form.php', 'front/equipment.form.php',
    'locales/en_GB.php', 'locales/ro_RO.php',
] as $rel) {
    if (!is_readable("$root/$rel")) {
        fail("missing $rel");
    } else {
        ok($rel);
    }
}

$sql = file_get_contents("$root/install/install.sql");
foreach ([
    'glpi_plugin_auchanassettracker_equipments',
    'glpi_plugin_auchanassettracker_containers',
    'glpi_plugin_auchanassettracker_allocations',
    'glpi_plugin_auchanassettracker_transfers',
    'glpi_plugin_auchanassettracker_transferitems',
    'glpi_plugin_auchanassettracker_auditlogs',
    'glpi_plugin_auchanassettracker_profiles',
] as $table) {
    if (!str_contains($sql, $table)) {
        fail("SQL missing $table");
    } else {
        ok("SQL has $table");
    }
}

if (str_contains($sql, 'qr_token')) {
    fail('SQL must not include qr_token (Sprint 4)');
} else {
    ok('no qr_token in SQL');
}

$setup = file_get_contents("$root/setup.php");
if (!str_contains($setup, "plugin_init_auchanassettracker")) {
    fail('setup missing init');
} else {
    ok('plugin init present');
}

if (!str_contains($setup, 'Auchan Asset Tracker')) {
    fail('plugin name missing');
} else {
    ok('plugin name present');
}

foreach (['dashboard', 'report', 'qrhelper'] as $banned) {
    if (str_contains($setup, "'$banned'")) {
        fail("setup still bootstraps $banned");
    } else {
        ok("setup omits $banned");
    }
}

// Ensure first-plugin tables are NOT touched
if (str_contains($sql, 'auchanequipment')) {
    fail('SQL must not reference auchanequipment');
} else {
    ok('no coupling to plugin 1 tables');
}

$forbidden = [
    'front/dashboard.php',
    'front/report.php',
    'front/container.qr.php',
    'public/qr.php',
    'public/qrimg.php',
    'inc/dashboard.class.php',
    'inc/report.class.php',
    'inc/qrhelper.class.php',
    'docs/README.md',
];
foreach ($forbidden as $rel) {
    if (is_readable("$root/$rel")) {
        fail("Sprint 4+ file present: $rel");
    } else {
        ok("absent $rel");
    }
}

echo $errors === 0 ? "\nAll structure checks passed.\n" : "\n$errors failure(s).\n";
exit($errors === 0 ? 0 : 1);
