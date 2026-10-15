<?php
/**
 * Public container QR view — no GLPI login.
 * Served by GLPI as /plugins/<PluginDir>/qr.php (file lives in public/).
 *
 * Anonymous access also requires Firewall STRATEGY_NO_CHECK + stateless path
 * registration in setup.php (GLPI 11 Kernel). DO_NOT_CHECK_LOGIN alone is not enough.
 */

// Must be defined before GLPI bootstrap (legacy + Kernel).
if (!defined('DO_NOT_CHECK_LOGIN')) {
    define('DO_NOT_CHECK_LOGIN', 1);
}

$glpi_root = dirname(__DIR__, 3);
if (!is_readable($glpi_root . '/inc/includes.php')) {
    http_response_code(500);
    echo 'GLPI not found.';
    exit;
}

include_once $glpi_root . '/inc/includes.php';

if (!function_exists('plugin_auchanassettracker_bootstrap')) {
    require_once dirname(__DIR__) . '/setup.php';
}
plugin_auchanassettracker_bootstrap();

// Public/stateless page: use GLPI default language (e.g. ro_RO) when no session lang.
global $CFG_GLPI;
$lang = trim((string) ($_SESSION['glpilanguage'] ?? ''));
if ($lang === '') {
    $lang = trim((string) ($CFG_GLPI['language'] ?? 'en_GB'));
}
if ($lang === '') {
    $lang = 'en_GB';
}
$_SESSION['glpilanguage'] = $lang;
if (class_exists('Session', false) && method_exists('Session', 'loadLanguage')) {
    Session::loadLanguage($lang);
}
if (function_exists('plugin_auchanassettracker_load_translations')) {
    plugin_auchanassettracker_load_translations();
}
$html_lang = strtolower(str_replace('_', '-', explode('.', $lang)[0]));
if ($html_lang === '') {
    $html_lang = 'en';
}

$token = trim((string) ($_GET['t'] ?? ''));
$container = PluginAuchanassettrackerContainer::findByToken($token);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$e = static function (?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

if ($container === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="' . $e($html_lang) . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $e(__('Not found', 'auchanassettracker')) . '</title>'
        . '<link rel="stylesheet" href="css/qrview.css">'
        . '</head><body class="aat-qrview">'
        . '<div class="aat-qrview-shell">'
        . '<div class="card"><div class="card-body">'
        . '<h1 class="card-title">' . $e(__('Container not found', 'auchanassettracker')) . '</h1>'
        . '<p class="text-secondary mb-0">'
        . $e(__('Invalid or inactive QR code.', 'auchanassettracker'))
        . '</p></div></div></div></body></html>';
    exit;
}

$items = PluginAuchanassettrackerEquipment::listInContainer((int) $container['id']);

$loc = Dropdown::getDropdownName('glpi_locations', (int) ($container['locations_id'] ?? 0));
if ($loc === '&nbsp;' || $loc === '-1') {
    $loc = '';
}
$loc = trim(html_entity_decode(strip_tags((string) $loc), ENT_QUOTES, 'UTF-8'));

$code = trim((string) ($container['code'] ?? ''));
$name = trim((string) ($container['name'] ?? ''));
$description = trim((string) ($container['description'] ?? ''));
if ($name === '') {
    $name = __('Physical container', 'auchanassettracker');
}

echo '<!DOCTYPE html><html lang="' . $e($html_lang) . '"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . $e($name) . '</title>'
    . '<link rel="stylesheet" href="css/qrview.css">'
    . '</head><body class="aat-qrview"><div class="aat-qrview-shell">';

echo '<header class="aat-qrview-hero">'
    . '<div class="aat-qrview-hero-title">'
    . $e(__('Physical container', 'auchanassettracker'))
    . ' — ' . $e($name)
    . '</div>';
if ($code !== '') {
    echo '<div class="aat-qrview-hero-code">'
        . '<span class="aat-qrview-hero-label">' . $e(__('Code', 'auchanassettracker')) . ':</span> '
        . $e($code)
        . '</div>';
}
if ($loc !== '') {
    echo '<div class="aat-qrview-hero-loc">'
        . '<span class="aat-qrview-hero-label">' . $e(__('Location')) . ':</span> '
        . $e($loc)
        . '</div>';
}
if ($description !== '') {
    echo '<div class="aat-qrview-hero-desc">'
        . '<span class="aat-qrview-hero-label">' . $e(__('Description')) . ':</span> '
        . $e($description)
        . '</div>';
}
echo '</header>';

echo '<div class="card aat-qrview-stock-card">'
    . '<div class="card-header"><h2 class="card-title">'
    . $e(__('Equipment', 'auchanassettracker'))
    . ' <span class="badge">' . count($items) . '</span>'
    . '</h2></div><div class="card-body p-0">';

if ($items === []) {
    echo '<div class="aat-qrview-empty">'
        . $e(__('No available equipment in this container.', 'auchanassettracker'))
        . '</div>';
} else {
    echo '<div class="table-responsive"><table class="table table-striped table-hover aat-qrview-table">'
        . '<thead><tr>'
        . '<th>' . $e(__('Name')) . '</th>'
        . '<th>' . $e(__('Serial number')) . '</th>'
        . '<th>' . $e(__('Equipment type', 'auchanassettracker')) . '</th>'
        . '<th>' . $e(__('Added on', 'auchanassettracker')) . '</th>'
        . '</tr></thead><tbody>';

    foreach ($items as $it) {
        $item_name = trim((string) ($it['name'] ?? ''));
        if ($item_name === '') {
            $item_name = trim((string) ($it['model'] ?? ''));
        }

        $serial = trim((string) ($it['serial'] ?? ''));

        // Sprint UI stores GLPI asset class in itemtype (Computer, Monitor, …).
        $typeName = '';
        $glpi_type = trim((string) ($it['itemtype'] ?? ''));
        if ($glpi_type !== '' && class_exists($glpi_type) && method_exists($glpi_type, 'getTypeName')) {
            $typeName = trim((string) $glpi_type::getTypeName(1));
        }
        if ($typeName === '') {
            $tid = (int) ($it['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
            if ($tid > 0) {
                $typeName = Dropdown::getDropdownName(
                    PluginAuchanassettrackerEquipmenttype::getTable(),
                    $tid
                );
                if ($typeName === '&nbsp;' || $typeName === '-1') {
                    $typeName = '';
                }
                $typeName = trim(html_entity_decode(strip_tags((string) $typeName), ENT_QUOTES, 'UTF-8'));
            }
        }

        $notes = trim((string) ($it['notes'] ?? ''));

        $added = trim((string) ($it['date_creation'] ?? ''));
        if ($added !== '' && class_exists('Html', false) && method_exists('Html', 'convDateTime')) {
            $added = (string) Html::convDateTime($added);
        }

        echo '<tr>'
            . '<td data-label="' . $e(__('Name')) . '">'
            . '<span class="aat-qrview-item-name">' . $e($item_name) . '</span>';
        // Notes line only on rows that have notes (no empty Notes column).
        if ($notes !== '') {
            echo '<div class="aat-qrview-item-notes">'
                . '<span class="aat-qrview-hero-label">' . $e(__('Notes')) . ':</span> '
                . $e($notes)
                . '</div>';
        }
        echo '</td>'
            . '<td data-label="' . $e(__('Serial number')) . '">'
            . '<span class="aat-qrview-item-serial">' . $e($serial) . '</span></td>'
            . '<td data-label="' . $e(__('Equipment type', 'auchanassettracker')) . '">'
            . $e($typeName) . '</td>'
            . '<td data-label="' . $e(__('Added on', 'auchanassettracker')) . '">'
            . $e($added) . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div>';
}

echo '</div></div>';

echo '<p class="aat-qrview-footer">'
    . $e(__('View only — to manage, open GLPI.', 'auchanassettracker'))
    . '</p>';

echo '</div></body></html>';
