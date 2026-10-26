#!/usr/bin/env php
<?php
/**
 * Offline structure checks (no GLPI required) — Sprint 5 delivery.
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
    'inc/dashboard.class.php', 'inc/report.class.php', 'inc/stockbylocation.class.php',
    'front/allocation.form.php', 'front/confirm.php', 'front/config.form.php',
    'front/transfer.php', 'front/transfer.form.php', 'front/equipment.form.php',
    'front/container.qr.php', 'front/dashboard.php', 'front/report.php',
    'front/auditlog.php', 'front/stockbylocation.php',
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
    'front/container.qrview.php',
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
    'glpi_plugin_auchanassettracker_stockbylocations',
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

if (!str_contains($setup, "'0.5.0'")) {
    fail('expected version 0.5.0 (do not bump for UX patches)');
} else {
    ok('version 0.5.0');
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
if (!is_readable("$root/public/js/aat-forms.js")) {
    fail('public/js/aat-forms.js missing (GLPI 11 serves plugin assets from public/)');
} else {
    ok('public/js/aat-forms.js present');
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

foreach (['transfer', 'transferitem', 'tickethook', 'qrhelper', 'dashboard', 'report', 'stockbylocation'] as $need) {
    if (!preg_match("/['\"]" . preg_quote($need, '/') . "['\"]/", $setup)) {
        fail("setup missing bootstrap $need");
    } else {
        ok("setup bootstraps $need");
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
if (!str_contains($menu, 'MENU_DASHBOARD') || !str_contains($menu, 'MENU_REPORT')) {
    fail('menu missing Dashboard / Reports');
} else {
    ok('menu has Dashboard + Reports');
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
if (!preg_match(
    "/ROLE_USER\\s*=>\\s*\\[[^\\]]*?'is_default'\\s*=>\\s*false/s",
    $profile
) && !preg_match(
    "/'End user'[\\s\\S]*?'is_default'\\s*=>\\s*false/",
    $profile
)) {
    fail('End user must not be GLPI default profile for new users');
} else {
    ok('End user is not GLPI default');
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
if (!str_contains($mail, 'sendViaGlpiMailer')
    || !str_contains($mail, "class_exists('GLPIMailer')")
    || str_contains($mail, "class_exists('GLPIMailer', false)")
) {
    fail('mailhelper must autoload GLPIMailer (not class_exists false)');
} else {
    ok('mailhelper GLPIMailer autoload');
}
if (!str_contains($mail, 'resolveMailFrom')
    || !str_contains($mail, 'getEmailSender')
) {
    fail('mailhelper must set From via Config::getEmailSender');
} else {
    ok('mailhelper From/Sender from GLPI config');
}
if (!str_contains($mail, 'absolutePluginUrl')
    || !str_contains($mail, 'ensureAbsoluteUrl')
    || !str_contains($mail, 'getGlpiBaseUrl')
) {
    fail('mailhelper must build absolute GLPI-root email links');
} else {
    ok('mailhelper absolute GLPI-root links');
}
if (!str_contains($mail, 'Hello %s,')
    || !str_contains($mail, 'has assigned equipment')
) {
    fail('mailhelper missing allocation email copy');
} else {
    ok('mailhelper allocation copy');
}
if (preg_match("/\\?:\\s*'—'/", $mail) || preg_match("/\\?\\s*'—'\\s*:/", $mail)) {
    fail('mailhelper must not use em-dash placeholders for empty values');
} else {
    ok('mailhelper blank empty table cells');
}
if (file_exists("$root/front/mail.preview.php")
    || str_contains(file_get_contents("$root/inc/config.class.php"), 'Preview email template')
    || str_contains(file_get_contents("$root/front/config.form.php"), 'preview_mail')
) {
    fail('email template preview must be removed');
} else {
    ok('email template preview removed');
}
$ro = file_get_contents("$root/locales/ro_RO.php");
foreach ([
    'Hello %s,',
    'has assigned equipment to you',
    'Confirm or decline',
] as $msgid) {
    if (!str_contains($ro, $msgid)) {
        fail("ro_RO.php missing mail msgid: $msgid");
    } else {
        ok("ro_RO mail: $msgid");
    }
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
if (!str_contains($rights, 'isCentralAdmin')
    || !str_contains($rights, "haveRight('config', UPDATE)")
) {
    fail('canConfigure should allow Central admin or Super-Admin');
} else {
    ok('canConfigure Central admin / Super-Admin gate');
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
    || !str_contains($xfer, 'Location::dropdown')
    || !str_contains($xfer, 'bootNativeLocationDropdownHtml')
    || !str_contains($xfer, "'comments'")
    || !str_contains($xfer, "'addicon'")
    || !str_contains($xfer, 'destinationLocationsToExclude')
) {
    fail('transfer missing native Location::dropdown boot / exclude own site');
} else {
    ok('transfer native Location dropdown boot + exclude');
}
$css = file_get_contents("$root/css/assettracker.css");
$pcss = file_get_contents("$root/public/css/assettracker.css");
if (!str_contains($css, 'aat-location-dropdown')
    || !str_contains($css, '--bs-gutter-x: 2.5rem')
) {
    fail('css missing location wrapper / alert column gap');
} else {
    ok('css location wrapper + alert gap');
}
if ($css !== $pcss) {
    fail('css/assettracker.css and public/css/assettracker.css must stay identical');
} else {
    ok('css and public/css assettracker.css in sync');
}
// Sprint 5 UX lock — colorful cards + red alerts (never yellow warning look).
foreach ([
    'aat-card--total', 'aat-card--available', 'aat-card--allocated',
    'aat-card--in_service', 'aat-card--in_transit', 'aat-card--written_off',
    '#d63939', '#fde8e8', '#e8f1ff',
] as $needle) {
    if (!str_contains($css, $needle)) {
        fail("css missing sprint-5 UX marker: $needle");
    } else {
        ok("css UX marker $needle");
    }
}
if (str_contains($css, '#fff8e6') || str_contains($css, '#e6a700')) {
    fail('css must not use yellow alert colors (#fff8e6 / #e6a700)');
} else {
    ok('css has no yellow alert leftovers');
}
$dash = file_get_contents("$root/front/dashboard.php");
if (str_contains($dash, 'Signed in as')) {
    fail('dashboard must not show Signed in as');
} else {
    ok('dashboard omits Signed in as');
}
foreach ([
    'aat-card--', 'aat-alert-heading', 'Active alerts', 'To resolve today',
    'Search::showList', 'forcetoview', 'aat-pending-row', 'aat-pending-equipment',
    'In movement', 'aat-dashboard-critical-css',
] as $needle) {
    if (!str_contains($dash, $needle)) {
        fail("dashboard missing UX: $needle");
    } else {
        ok("dashboard has $needle");
    }
}
if (str_contains($dash, 'item(s)')) {
    fail('dashboard pending must not say item(s)');
} else {
    ok('dashboard pending omits item(s)');
}
$dashc = file_get_contents("$root/inc/dashboard.class.php");
if (!str_contains($dashc, 'syncInServiceFromGlpiAssets')) {
    fail('dashboard class missing syncInServiceFromGlpiAssets');
} else {
    ok('dashboard syncInServiceFromGlpiAssets');
}
$stock = file_get_contents("$root/inc/stockbylocation.class.php");
if (!str_contains($stock, 'aat_stockbylocation_rebuilt_at')
    || !str_contains($stock, 'GROUP BY')
) {
    fail('stockbylocation rebuild must be throttled + aggregated');
} else {
    ok('stockbylocation throttled rebuild');
}
if (!str_contains($rights, "haveRight('config', UPDATE)")
    || !preg_match('/function getCurrentRole.*?haveRight\(\'config\',\s*UPDATE\)/s', $rights)
) {
    fail('Super-Admin (config UPDATE) must map to Central admin role');
} else {
    ok('Super-Admin maps to Central admin');
}
if (!str_contains($dash, '_n(')
    || !str_contains($dash, 'You have %d equipments awaiting confirmation.')
) {
    fail('dashboard pending must pluralize equipment/equipments');
} else {
    ok('dashboard pending pluralization');
}
$audit = file_get_contents("$root/inc/auditlog.class.php");
foreach (['formatActionLabel', 'formatDetailsHtml'] as $needle) {
    if (!str_contains($audit, $needle)) {
        fail("auditlog missing $needle");
    } else {
        ok("auditlog has $needle");
    }
}
if (!str_contains($audit, 'isEmptyDetailValue')
    || !str_contains($audit, 'formatUpdatedFieldsPart')
    || !str_contains($audit, 'array_keys($json) === range')
) {
    fail('audit details must hide empty FKs and fix list updates');
} else {
    ok('audit details empty/list handling');
}
if (!str_contains($xfer_front, 'bulk_container_id')
    || !str_contains($xfer_front, 'aat-transfer-bulk-panel')
    || !str_contains($xfer_front, 'aat-transfer-validate-actions')
    || !str_contains($xfer_front, 'container[$eid]')
    || !str_contains($xfer_front, 'Container for selected items')
) {
    fail('transfer validate missing mixed per-row + bulk containers');
} else {
    ok('transfer mixed per-row + bulk containers');
}
if (!str_contains($xfer_front, 'aat-transfer-create-form')
    || !str_contains($xfer_front, 'aat-check-all')
) {
    fail('new transfer missing select-all');
} else {
    ok('new transfer has select-all');
}
$js = file_get_contents("$root/js/aat-forms.js");
$pjs = file_get_contents("$root/public/js/aat-forms.js");
if ($js !== $pjs) {
    fail('js/aat-forms.js and public/js/aat-forms.js must stay identical');
} else {
    ok('js and public/js aat-forms.js in sync');
}
if (!str_contains($js, 'aatSyncTransferBulkPanel')
    || !str_contains($js, 'bulk_container_id')
) {
    fail('aat-forms.js missing transfer bulk panel sync');
} else {
    ok('aat-forms.js transfer bulk panel sync');
}
if (!str_contains($js, 'aatHideDashboardSearchChrome')) {
    fail('aat-forms.js must hide dashboard Search chrome extras');
} else {
    ok('aat-forms.js hides dashboard Search chrome extras');
}
if (str_contains($js, "form[name=\"search\"]")
    || str_contains($js, '.saved_searches')
) {
    fail('aat-forms.js must not hide search-by / saved searches');
} else {
    ok('aat-forms.js keeps search-by + saved searches');
}
if (!str_contains($css, 'font-weight: 700 !important')
    || !str_contains($css, 'color: #000 !important')
) {
    fail('css missing bold alert titles / black alert text');
} else {
    ok('css alert titles + black text');
}
if (str_contains($css, 'form[name="search"]')
    || str_contains($css, '.saved_searches')
) {
    fail('css must not hide search-by / saved searches');
} else {
    ok('css keeps search-by + saved searches');
}
if (str_contains($css, '.search-controls .primary-controls')
    && preg_match('/\.aat-dashboard-search \.search-controls \.primary-controls[\s\S]{0,80}display:\s*none/', $css)
) {
    fail('css must not hide primary-controls (breaks sort-by)');
} else {
    ok('css keeps sort-by primary-controls');
}
if (!str_contains($dashc, 'resolveServiceTicketId')) {
    fail('dashboard missing resolveServiceTicketId for In movement tickets');
} else {
    ok('dashboard resolveServiceTicketId');
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
