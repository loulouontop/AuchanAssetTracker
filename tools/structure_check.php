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
    'front/dashboard.php', 'front/report.php', 'front/container.qrview.php',
    'docs/README.md',
] as $rel) {
    if (file_exists("$root/$rel")) {
        fail("must not be present: $rel");
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

if (!str_contains($setup, "'0.4.1'")) {
    fail('expected version 0.4.1');
} else {
    ok('version 0.4.1');
}
if (str_contains($setup, 'helpdesk_menu_entry')) {
    fail('setup must not register helpdesk_menu_entry (duplicate Plugins menu)');
} else {
    ok('setup omits helpdesk_menu_entry');
}
if (!str_contains($setup, 'aat-forms.js')) {
    fail('setup missing aat-forms.js');
} else {
    ok('setup has aat-forms.js');
}
if (!is_readable("$root/js/aat-forms.js")) {
    fail('js/aat-forms.js missing');
} else {
    ok('js/aat-forms.js present');
}

foreach ([
    'addPluginStrategyForLegacyScripts',
    'STRATEGY_NO_CHECK',
    'registerPluginStatelessPath',
    'plugin_auchanassettracker_boot',
    'plugin_auchanassettracker_register_public_qr_access',
] as $needle) {
    if (!str_contains($setup, $needle)) {
        fail("setup missing public QR access: $needle");
    } else {
        ok("setup has $needle");
    }
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
foreach ([
    'findByToken', 'qr_token', 'Download PDF label', 'QR code', 'QR URL',
    'aat-qr-preview', 'aat-qr-pdf-btn', 'ti ti-qrcode', 'getQrPngDataUri',
    "addbuttons", 'aat_download_pdf_label',
    'Scan the label on a phone using the internal network for a read only list of Available stock in this container.',
] as $needle) {
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

$profile = file_get_contents("$root/inc/profile.class.php");
foreach ([
    'ensureBundledProfiles', 'getBundledProfiles', 'Location manager', 'End user',
    'findOrCreateProfileByName', 'syncProfileLocalizedLabels', 'getProfileNameVariants',
    'cloneCoreRightsFromTemplate', 'helpdesk', 'function showForm', 'getTabNameForItem',
    'displayRightsChoiceMatrix', 'saveRightsFromPost',
] as $needle) {
    if (!str_contains($profile, $needle)) {
        fail("profile missing $needle");
    } else {
        ok("profile has $needle");
    }
}
if (!is_readable("$root/front/profile.form.php")) {
    fail('front/profile.form.php missing');
} else {
    ok('front/profile.form.php present');
}
if (!preg_match("/registerClass\\('PluginAuchanassettrackerProfile'\\s*,\\s*\\[[^\\]]*addtabon/", $setup)) {
    fail('Profile addtabon must be registered');
} else {
    ok('Profile addtabon registered');
}
// End user: no equipment; technician: no transfer.
if (!preg_match("/'support_tech'\\s*=>\\s*\\[[^\\]]*RIGHT_TRANSFER\\s*=>\\s*0/s", $profile)) {
    fail('support_tech must have TRANSFER => 0');
} else {
    ok('support_tech has no transfer');
}
if (!preg_match("/default\\s*=>\\s*\\[[^\\]]*RIGHT_EQUIPMENT\\s*=>\\s*0/s", $profile)) {
    fail('end user must have EQUIPMENT => 0');
} else {
    ok('end user has no equipment');
}
$confirm_front = file_get_contents("$root/front/confirm.php");
$confirm_class = file_get_contents("$root/inc/confirm.class.php");
if (!str_contains($confirm_front, 'helpHeader') || !str_contains($confirm_front, 'helpFooter')) {
    fail('confirm.php must use helpHeader/helpFooter for simplified interface');
} else {
    ok('confirm.php helpdesk header');
}
if (str_contains($confirm_class, 'initForm(')) {
    fail('confirm.class.php must not call initForm (CREATE on ID -1)');
} else {
    ok('confirm skips initForm');
}
if (str_contains($profile, "Dropdown::showFromArray('role'")
    || str_contains($profile, "__('Role', 'auchanassettracker')")
) {
    fail('Profiles tab must not show Role dropdown');
} else {
    ok('Profiles tab has no Role field');
}
$mail = file_get_contents("$root/inc/mailhelper.class.php");
if (!str_contains($mail, 'renderHtmlEmail') || !str_contains($mail, '#E30613')) {
    fail('mailhelper missing Auchan HTML email template');
} else {
    ok('mailhelper HTML template');
}

$rights = file_get_contents("$root/inc/righthelper.class.php");
if (!str_contains($rights, 'getMappedRole')
    || !str_contains($rights, 'ROLE_SUPPORT_TECH')
    || !str_contains($rights, 'canBulk')
    || !str_contains($rights, "return '';")
) {
    fail('righthelper missing role gates / no default end user');
} else {
    ok('righthelper role gates');
}
if (!str_contains($rights, "haveRight('config', UPDATE)")) {
    fail('canConfigure should prefer Super-Admin config UPDATE');
} else {
    ok('canConfigure Super-Admin gate');
}
$alloc = file_get_contents("$root/inc/allocation.class.php");
if (!str_contains($alloc, 'aat-alert-columns') || !str_contains($alloc, 'View all')
    || !str_contains($alloc, 'iconForItemtype')
    || !str_contains($alloc, 'row-cols-lg-4')
) {
    fail('allocation alerts missing columns / View all / icons');
} else {
    ok('allocation alert columns');
}
if (!str_contains($alloc, 'recipient_loc')
    || !str_contains($alloc, 'same location as the recipient')
) {
    fail('allocation stock must filter by recipient location');
} else {
    ok('allocation stock by recipient location');
}
$xfer_front = file_get_contents("$root/front/transfer.form.php");
if (!str_contains($xfer_front, 'Transferred by')
    || !str_contains($xfer_front, 'Approved by')
    || !str_contains($xfer_front, 'Rejected by')
    || !str_contains($xfer_front, 'dropdownDestinationLocation')
) {
    fail('transfer form missing actors / destination dropdown helper');
} else {
    ok('transfer actors + location dropdown');
}
$xfer = file_get_contents("$root/inc/transfer.class.php");
if (!str_contains($xfer, 'function dropdownDestinationLocation')
    || !str_contains($xfer, 'users_id_rejector')
) {
    fail('transfer missing destination Select2 helper / rejector');
} else {
    ok('transfer destination Select2 + rejector');
}
$css = file_get_contents("$root/css/assettracker.css");
if (!str_contains($css, 'aat-location-pending')
    || !str_contains($css, '--bs-gutter-x: 2.5rem')
) {
    fail('css missing location pending / alert column gap');
} else {
    ok('css location pending + alert gap');
}
$ro = file_get_contents("$root/locales/ro_RO.php");
foreach (['Destination', 'Sort', 'Filter', 'Export', 'Choose files', 'No file chosen', 'Rows / page', 'Show saved search', 'Filtered by'] as $msgid) {
    if (!str_contains($ro, "'$msgid'")) {
        fail("ro_RO missing msgid $msgid");
    } else {
        ok("ro_RO has $msgid");
    }
}

if (str_contains($sql, 'auchanequipment')) {
    fail('SQL must not reference auchanequipment');
} else {
    ok('no coupling to plugin 1 tables');
}

if (file_exists("$root/public/qrimg.php")) {
    fail('public/qrimg.php must not remain (embedded QR preferred)');
} else {
    ok('absent public/qrimg.php');
}

$view = file_get_contents("$root/public/qr.php");
if (!str_contains($view, 'DO_NOT_CHECK_LOGIN')) {
    fail('public/qr.php must allow unauthenticated access');
} else {
    ok('public/qr.php unauthenticated');
}
foreach ([
    'listInContainer', 'Physical container', 'Serial number',
    'Equipment type', 'Added on', 'aat-qrview-hero-desc',
    'aat-qrview-item-notes', 'applyLanguage', 'show_notes',
    'View only — to manage, open GLPI.',
] as $needle) {
    if (!str_contains($view, $needle)) {
        fail("public/qr.php missing $needle");
    } else {
        ok("public/qr.php has $needle");
    }
}

$helper = file_get_contents("$root/inc/qrhelper.class.php");
foreach ([
    'getGlpiBaseUrl', 'getScanUrl', 'getQrPngDataUri', 'HTTP_HOST', '/qr.php?t=',
    'getPreferredLanguage', 'applyLanguage', 'normalizeLanguage', '&lang=',
] as $needle) {
    if (!str_contains($helper, $needle)) {
        fail("qrhelper missing $needle");
    } else {
        ok("qrhelper has $needle");
    }
}
if (str_contains($helper, '/public/qr.php')) {
    fail('scan URL must not include /public/ (GLPI login route)');
} else {
    ok('scan URL omits /public/');
}
if (!str_contains($helper, 'tempnam')) {
    fail('QR PNG must use temp file (avoid image/png header leak)');
} else {
    ok('QR PNG uses temp file');
}

echo $errors === 0 ? "\nAll structure checks passed.\n" : "\n$errors failure(s).\n";
exit($errors === 0 ? 0 : 1);
