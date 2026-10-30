<?php
/**
 * AuchanAssetTracker — GLPI 11 plugin (Sprint 5).
 *
 * Stock, containers, allocation, transfers, service, write-off, QR labels,
 * role dashboards, reports. RO + EN.
 *
 * @author    Lokmane Benaziza
 * @copyright 2026 Auchan Romania
 */

/** Plugin version shown in Setup → Plugins. */
define('PLUGIN_AUCHANASSETTRACKER_VERSION', '0.5.1');
/**
 * Asset cache-buster for CSS/JS (independent of PLUGIN version).
 * Bump this when UX CSS/JS must invalidate browser caches.
 */
define('PLUGIN_AUCHANASSETTRACKER_ASSET_REV', '20261030b');
define('PLUGIN_AUCHANASSETTRACKER_MIN_GLPI', '11.0.0');
define('PLUGIN_AUCHANASSETTRACKER_MAX_GLPI', '11.9.99');
/**
 * Exact plugins/ folder name on disk (case-sensitive on Linux).
 * Must match glpi_plugins.directory — never hardcode a different casing.
 */
define('PLUGIN_AUCHANASSETTRACKER_DIR', basename(__DIR__));

/**
 * Single plugin directory key for hooks, menus, DB row, and every URL.
 */
function plugin_auchanassettracker_dir(): string
{
    return PLUGIN_AUCHANASSETTRACKER_DIR;
}

/**
 * Web base path for this plugin.
 * Avoid Plugin::getWebDir() (deprecated in GLPI 11; can return false and break menus).
 */
function plugin_auchanassettracker_web_dir(bool $full = true): string
{
    global $CFG_GLPI;

    $path = 'plugins/' . plugin_auchanassettracker_dir();
    if (!$full) {
        return $path;
    }

    $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    return ($root !== '' ? $root : '') . '/' . $path;
}

function plugin_auchanassettracker_bootstrap(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    foreach ([
        'pluginlog',
        'config',
        'auditlog',
        'profile',
        'righthelper',
        'equipmenttype',
        'manufacturer',
        'container',
        'equipment',
        'assetform',
        'locationtab',
        'bulk',
        'allocation',
        'confirm',
        'notice',
        'transfer',
        'transferitem',
        'tickethook',
        'qrhelper',
        'dashboard',
        'report',
        'stockbylocation',
        'menu',
        'mailhelper',
    ] as $file) {
        $path = __DIR__ . '/inc/' . $file . '.class.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }

    $loaded = true;
}

function plugin_auchanassettracker_load_translations(): void
{
    global $TRANSLATE, $CFG_GLPI;

    $lang = trim((string) ($_SESSION['glpilanguage'] ?? ''));
    if ($lang === '') {
        $lang = trim((string) ($CFG_GLPI['language'] ?? 'en_GB'));
    }
    if ($lang === '') {
        $lang = 'en_GB';
    }

    if (isset($TRANSLATE) && !str_starts_with($lang, 'en')) {
        foreach (array_unique([$lang, substr($lang, 0, 2)]) as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $phpfile = __DIR__ . '/locales/' . $candidate . '.php';
            if (is_readable($phpfile)) {
                // Plugin domain (menu, forms, messages).
                $TRANSLATE->addTranslationFile('phparray', $phpfile, 'auchanassettracker', $lang);
                // Also cover GLPI core UI chrome that appears on plugin pages
                // (search bar: Filter, Sort, Export, rows/page, Document upload, …).
                $TRANSLATE->addTranslationFile('phparray', $phpfile, 'glpi', $lang);
                break;
            }
        }
    }
}

function plugin_auchanassettracker_post_init(): void
{
    plugin_auchanassettracker_load_translations();
}

/**
 * Emit cache-busted CSS/JS (ASSET_REV invalidates browsers).
 * Safe to call once per front page after Html::header().
 */
function plugin_auchanassettracker_emit_assets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $rev = defined('PLUGIN_AUCHANASSETTRACKER_ASSET_REV')
        ? PLUGIN_AUCHANASSETTRACKER_ASSET_REV
        : '1';
    $base = plugin_auchanassettracker_web_dir();
    // GLPI 11 serves plugin public/ assets at /plugins/<dir>/css|js/...
    echo '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($base . '/css/assettracker.css?aat=' . $rev, ENT_QUOTES, 'UTF-8')
        . '">' . "\n";
    echo '<script src="'
        . htmlspecialchars($base . '/js/aat-forms.js?aat=' . $rev, ENT_QUOTES, 'UTF-8')
        . '"></script>' . "\n";
}

/**
 * Early boot (GLPI 11): register public QR before session/firewall decisions.
 */
function plugin_auchanassettracker_boot(): void
{
    plugin_auchanassettracker_register_public_qr_access();
}

function plugin_auchanassettracker_register_public_qr_access(): void
{
    // GLPI 11: every plugin PHP script defaults to authenticated-only.
    // DO_NOT_CHECK_LOGIN alone is ignored by the Kernel firewall — we must
    // declare STRATEGY_NO_CHECK (+ optional stateless session) explicitly.
    // Patterns are relative to /plugins/<dir>/ (no /public/ prefix in URL).
    $keys = array_unique([
        plugin_auchanassettracker_dir(),
        strtolower(plugin_auchanassettracker_dir()),
    ]);
    $pattern = '#^/qr\\.php$#';

    foreach ($keys as $key) {
        if ($key === '') {
            continue;
        }
        if (class_exists(\Glpi\Http\Firewall::class)) {
            \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
                $key,
                $pattern,
                \Glpi\Http\Firewall::STRATEGY_NO_CHECK
            );
        }
        if (class_exists(\Glpi\Http\SessionManager::class)) {
            // Avoid “no session → redirect to login” for phone scans.
            \Glpi\Http\SessionManager::registerPluginStatelessPath($key, $pattern);
        }
    }
}

function plugin_init_auchanassettracker(): void
{
    global $PLUGIN_HOOKS, $DB, $CFG_GLPI;

    $plug = plugin_auchanassettracker_dir();

    $PLUGIN_HOOKS['csrf_compliant'][$plug] = true;
    $PLUGIN_HOOKS['post_init'][$plug] = 'plugin_auchanassettracker_post_init';

    // Must run for anonymous requests too (before the login early-return).
    plugin_auchanassettracker_register_public_qr_access();

    plugin_auchanassettracker_bootstrap();

    if ($DB->tableExists('glpi_plugin_auchanassettracker_equipments')
        || $DB->tableExists('glpi_plugin_auchanassettracker_containers')) {
        plugin_auchanassettracker_ensure_schema();
    }

    if ($DB->tableExists('glpi_plugin_auchanassettracker_configs')) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }

    // Translations before profile seeding (localized profile names/comments).
    plugin_auchanassettracker_load_translations();

    // Register / migrate GLPI ProfileRight rows (idempotent; missing rows only).
    if ($DB->tableExists('glpi_profilerights')) {
        PluginAuchanassettrackerProfile::initProfile();
    }

    // Top-level “Auchan Asset Tracker” menu (not under Assets).
    $PLUGIN_HOOKS['redefine_menus'][$plug] = 'plugin_auchanassettracker_redefine_menus';
    $PLUGIN_HOOKS['add_css'][$plug][] = 'css/assettracker.css';
    $PLUGIN_HOOKS['add_javascript'][$plug][] = 'js/aat-forms.js';

    if (!Session::getLoginUserID()) {
        return;
    }

    // Helpdesk: Confirm receipt only via redefine_menus (Auchan Asset Tracker sector).
    // Do not register helpdesk_menu_entry — that adds a second “Plugins” menu entry.

    Plugin::registerClass('PluginAuchanassettrackerContainer');
    Plugin::registerClass('PluginAuchanassettrackerEquipment');
    Plugin::registerClass('PluginAuchanassettrackerBulk');
    Plugin::registerClass('PluginAuchanassettrackerAllocation');
    Plugin::registerClass('PluginAuchanassettrackerConfirm');
    Plugin::registerClass('PluginAuchanassettrackerTransfer');
    Plugin::registerClass('PluginAuchanassettrackerAuditlog');
    Plugin::registerClass('PluginAuchanassettrackerStockbylocation');
    Plugin::registerClass('PluginAuchanassettrackerProfile', [
        'addtabon' => ['Profile'],
    ]);
    Plugin::registerClass('PluginAuchanassettrackerLocationtab', [
        'addtabon' => ['Location'],
    ]);

    // Allow Documents tab / uploads on plugin equipment & containers.
    foreach ([
        PluginAuchanassettrackerEquipment::class,
        PluginAuchanassettrackerContainer::class,
    ] as $doc_type) {
        if (!in_array($doc_type, $CFG_GLPI['document_types'] ?? [], true)) {
            $CFG_GLPI['document_types'][] = $doc_type;
        }
    }

    // Physical container on native GLPI asset forms + location-scoped search.
    $PLUGIN_HOOKS['post_item_form'][$plug] = 'plugin_auchanassettracker_post_item_form';

    foreach (PluginAuchanassettrackerEquipment::getAllowedAssetTypes() as $asset_type) {
        $PLUGIN_HOOKS['item_add'][$plug][$asset_type] = 'plugin_auchanassettracker_item_add_asset';
        $PLUGIN_HOOKS['pre_item_update'][$plug][$asset_type] = 'plugin_auchanassettracker_pre_item_update_asset';
        $PLUGIN_HOOKS['item_update'][$plug][$asset_type] = 'plugin_auchanassettracker_item_update_asset';
    }

    // Service: ticket opened on tracked gear → In service; solved/closed → Allocated.
    $PLUGIN_HOOKS['item_add'][$plug]['Ticket'] = [
        'PluginAuchanassettrackerTickethook',
        'postTicketAdd',
    ];
    $PLUGIN_HOOKS['item_add'][$plug]['Item_Ticket'] = [
        'PluginAuchanassettrackerTickethook',
        'postItemTicketAdd',
    ];
    $PLUGIN_HOOKS['item_update'][$plug]['Ticket'] = [
        'PluginAuchanassettrackerTickethook',
        'postTicketUpdate',
    ];

    if (PluginAuchanassettrackerRighthelper::canConfigure()) {
        $PLUGIN_HOOKS['config_page'][$plug] = 'front/config.form.php';
    }
}

/**
 * @param array{item?: CommonDBTM} $params
 */
function plugin_auchanassettracker_post_item_form(array $params): void
{
    PluginAuchanassettrackerAssetform::postItemForm($params);
}

function plugin_auchanassettracker_item_add_asset(CommonDBTM $item): void
{
    PluginAuchanassettrackerAssetform::onItemAdd($item);
}

function plugin_auchanassettracker_pre_item_update_asset(CommonDBTM $item): bool
{
    return PluginAuchanassettrackerAssetform::preItemUpdate($item);
}

function plugin_auchanassettracker_item_update_asset(CommonDBTM $item): void
{
    PluginAuchanassettrackerAssetform::onItemUpdate($item);
}

/**
 * Restrict Equipment / Container search lists to the user’s location scope.
 * (GLPI naming-convention hook.)
 */
function plugin_auchanassettracker_addDefaultWhere($itemtype): string
{
    $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
    if ($scope === null) {
        return '';
    }

    if ($itemtype === PluginAuchanassettrackerEquipment::class
        || $itemtype === 'PluginAuchanassettrackerEquipment') {
        $table = PluginAuchanassettrackerEquipment::getTable();
        if ($scope <= 0) {
            return "`$table`.`id` = 0";
        }
        return "`$table`.`locations_id` = " . (int) $scope;
    }

    if ($itemtype === PluginAuchanassettrackerContainer::class
        || $itemtype === 'PluginAuchanassettrackerContainer') {
        $table = PluginAuchanassettrackerContainer::getTable();
        if ($scope <= 0) {
            return "`$table`.`id` = 0";
        }
        return "`$table`.`locations_id` = " . (int) $scope;
    }

    return '';
}

function plugin_version_auchanassettracker(): array
{
    return [
        'name'           => 'AuchanAssetTracker',
        'version'        => PLUGIN_AUCHANASSETTRACKER_VERSION,
        'author'         => 'Lokmane BENAZIZA',
        'license'        => 'Auchan RO',
        'homepage'       => '',
        'minGlpiVersion' => PLUGIN_AUCHANASSETTRACKER_MIN_GLPI,
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_AUCHANASSETTRACKER_MIN_GLPI,
                'max' => PLUGIN_AUCHANASSETTRACKER_MAX_GLPI,
            ],
            'php' => ['min' => '8.1'],
        ],
    ];
}

function plugin_auchanassettracker_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_AUCHANASSETTRACKER_MIN_GLPI, 'lt')) {
        echo 'GLPI ' . PLUGIN_AUCHANASSETTRACKER_MIN_GLPI . ' or higher is required.';
        return false;
    }
    return true;
}

function plugin_auchanassettracker_check_config(bool $verbose = false): bool
{
    return true;
}

if (is_readable(__DIR__ . '/hook.php')) {
    require_once __DIR__ . '/hook.php';
}
