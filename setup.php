<?php
/**
 * AuchanAssetTracker — GLPI 11 plugin (Sprint 2).
 *
 * Stock, containers, allocation with user confirm/reject, alerts, audit. RO + EN.
 *
 * @author    Lokmane Benaziza
 * @copyright 2026 Auchan Romania
 */

define('PLUGIN_AUCHANASSETTRACKER_VERSION', '0.3.5');
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
        'bulk',
        'allocation',
        'confirm',
        'notice',
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
    global $TRANSLATE;

    $lang = (string) ($_SESSION['glpilanguage'] ?? 'en_GB');
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
                $TRANSLATE->addTranslationFile('phparray', $phpfile, 'auchanassettracker', $lang);
                break;
            }
        }
    }
}

function plugin_auchanassettracker_post_init(): void
{
    plugin_auchanassettracker_load_translations();
}

function plugin_init_auchanassettracker(): void
{
    global $PLUGIN_HOOKS, $DB;

    $plug = plugin_auchanassettracker_dir();

    $PLUGIN_HOOKS['csrf_compliant'][$plug] = true;
    $PLUGIN_HOOKS['post_init'][$plug] = 'plugin_auchanassettracker_post_init';

    plugin_auchanassettracker_bootstrap();

    if ($DB->tableExists('glpi_plugin_auchanassettracker_equipments')
        || $DB->tableExists('glpi_plugin_auchanassettracker_containers')) {
        plugin_auchanassettracker_ensure_schema();
    }

    if ($DB->tableExists('glpi_plugin_auchanassettracker_configs')) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }

    // Top-level “Auchan Asset Tracker” menu (not under Assets).
    $PLUGIN_HOOKS['redefine_menus'][$plug] = 'plugin_auchanassettracker_redefine_menus';
    $PLUGIN_HOOKS['add_css'][$plug][] = 'css/assettracker.css';

    if (!Session::getLoginUserID()) {
        return;
    }

    Plugin::registerClass('PluginAuchanassettrackerContainer');
    Plugin::registerClass('PluginAuchanassettrackerEquipment');
    Plugin::registerClass('PluginAuchanassettrackerBulk');
    Plugin::registerClass('PluginAuchanassettrackerAllocation');
    Plugin::registerClass('PluginAuchanassettrackerConfirm');
    Plugin::registerClass('PluginAuchanassettrackerProfile', [
        'addtabon' => ['Profile'],
    ]);

    // Physical container on native GLPI asset forms + location-scoped search.
    $PLUGIN_HOOKS['post_item_form'][$plug] = 'plugin_auchanassettracker_post_item_form';

    foreach (PluginAuchanassettrackerEquipment::getAllowedAssetTypes() as $asset_type) {
        $PLUGIN_HOOKS['item_add'][$plug][$asset_type] = 'plugin_auchanassettracker_item_add_asset';
        $PLUGIN_HOOKS['item_update'][$plug][$asset_type] = 'plugin_auchanassettracker_item_update_asset';
    }

    if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
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
