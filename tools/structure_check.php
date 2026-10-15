#!/usr/bin/env php
<?php
/**
 * Offline structure checks (no GLPI required) — Sprint 4 delivery.
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
    'inc/assetform.class.php', 'inc/bulk.class.php', 'inc/confirm.class.php', 'inc/notice.class.php',
    'inc/transfer.class.php', 'inc/transferitem.class.php', 'inc/tickethook.class.php',
    'inc/mailhelper.class.php', 'inc/config.class.php', 'inc/menu.class.php',
    'inc/qrhelper.class.php', 'inc/phpqrcode/qrlib.php',
    'front/allocation.form.php', 'front/confirm.php', 'front/config.form.php',
    'front/transfer.php', 'front/transfer.form.php', 'front/equipment.form.php',
    'front/container.qr.php',
    'public/qr.php', 'public/css/qrview.css',
    'ajax/containers.php', 'ajax/models.php', 'ajax/users.php',
    'css/assettracker.css', 'public/css/assettracker.css',
    'locales/en_GB.php', 'locales/ro_RO.php',
] as $rel) {
    if (!is_readable("$root/$rel")) {
        fail("missing $rel");
    } else {
        ok($rel);
    }
}

foreach ([
    'inc/dashboard.class.php', 'inc/report.class.php',
    'front/dashboard.php', 'front/report.php', 'docs/README.md',
] as $rel) {
    if (file_exists("$root/$rel")) {
        fail("Sprint 5+ file still present: $rel");
    } else {
        ok("absent $rel");
    }
}

$sql = file_get_contents("$root/install/install.sql");
foreach ([
    'glpi_plugin_auchanassettracker_equipments',
    'glpi_plugin_auchanassettracker_containers',
    'glpi_plugin_auchanassettracker_allocations',
    'glpi_plugin_auchanassettracker_transfers',
    'glpi_plugin_auchanassettracker_transferitems',
    'glpi_plugin_auchanassettracker_notices',
    'glpi_plugin_auchanassettracker_auditlogs',
    'glpi_plugin_auchanassettracker_profiles',
    'glpi_plugin_auchanassettracker_configs',
    'final_reason',
    'service_tickets_id',
    'qr_token',
] as $needle) {
    if (!str_contains($sql, $needle)) {
        fail("SQL missing $needle");
    } else {
        ok("SQL has $needle");
    }
}

$setup = file_get_contents("$root/setup.php");
if (!str_contains($setup, "plugin_init_auchanassettracker")) {
    fail('setup missing init');
} else {
    ok('plugin init present');
}

if (!str_contains($setup, 'AuchanAssetTracker') && !str_contains($setup, 'Auchan Asset Tracker')) {
    fail('plugin name missing');
} else {
    ok('plugin name present');
}

if (!str_contains($setup, "'0.4.0'")) {
    fail('expected version 0.4.0');
} else {
    ok('version 0.4.0');
}

foreach (['transfer', 'transferitem', 'tickethook', 'qrhelper'] as $need) {
    if (!preg_match("/['\"]" . preg_quote($need, '/') . "['\"]/", $setup)) {
        fail("setup missing bootstrap $need");
    } else {
        ok("setup bootstraps $need");
    }
}

foreach (['dashboard', 'report'] as $bad) {
    if (preg_match("/['\"]" . preg_quote($bad, '/') . "['\"]/", $setup)) {
        fail("setup still bootstraps $bad");
    } else {
        ok("setup omits $bad");
    }
}

$container = file_get_contents("$root/inc/container.class.php");
foreach (['findByToken', 'qr_token', 'Download PDF label', 'QR code', 'aat-qr-preview', 'getQrPngDataUri'] as $needle) {
    if (!str_contains($container, $needle)) {
        fail("container missing $needle");
    } else {
        ok("container has $needle");
    }
}

$equip = file_get_contents("$root/inc/equipment.class.php");
if (!str_contains($equip, 'function listInContainer')) {
    fail('equipment missing listInContainer');
} else {
    ok('equipment listInContainer');
}

$menu = file_get_contents("$root/inc/menu.class.php");
if (!str_contains($menu, 'SECTOR')) {
    fail('menu missing SECTOR (top-level GLPI menu)');
} else {
    ok('menu has SECTOR');
}
if (!str_contains($menu, 'MENU_TRANSFER')) {
    fail('menu missing Transfers');
} else {
    ok('menu has Transfers');
}

if (str_contains($sql, 'auchanequipment')) {
    fail('SQL must not reference auchanequipment');
} else {
    ok('no coupling to plugin 1 tables');
}

foreach (['public/qr.php', 'public/qrimg.php'] as $gone) {
    if (file_exists("$root/$gone")) {
        fail("unauthenticated $gone must not remain");
    } else {
        ok("absent $gone");
    }
}

$view = file_get_contents("$root/front/container.qrview.php");
if (!str_contains($view, 'listInContainer')) {
    fail('qrview must list container stock');
} else {
    ok('qrview lists stock');
}
if (str_contains($view, 'DO_NOT_CHECK_LOGIN')) {
    fail('qrview must require GLPI login');
} else {
    ok('qrview requires login');
}

$helper = file_get_contents("$root/inc/qrhelper.class.php");
foreach (['getGlpiBaseUrl', 'getScanUrl', 'getQrPngDataUri', 'HTTP_HOST'] as $needle) {
    if (!str_contains($helper, $needle)) {
        fail("qrhelper missing $needle");
    } else {
        ok("qrhelper has $needle");
    }
}

echo $errors === 0 ? "\nAll structure checks passed.\n" : "\n$errors failure(s).\n";
exit($errors === 0 ? 0 : 1);
