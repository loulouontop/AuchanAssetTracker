<?php
/**
 * Public container QR view — no GLPI login.
 * Served by GLPI as /plugins/<PluginDir>/qr.php (file lives in public/).
 */

// Must be defined before GLPI bootstrap.
define('DO_NOT_CHECK_LOGIN', 1);

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
if (function_exists('plugin_auchanassettracker_load_translations')) {
    plugin_auchanassettracker_load_translations();
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
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
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
if ($loc === '' || $loc === '&nbsp;' || $loc === '-1') {
    $loc = '—';
}

$code = trim((string) ($container['code'] ?? ''));
$name = trim((string) ($container['name'] ?? ''));
if ($name === '') {
    $name = __('Physical container', 'auchanassettracker');
}

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . $e($name) . '</title>'
    . '<link rel="stylesheet" href="css/qrview.css">'
    . '</head><body class="aat-qrview"><div class="aat-qrview-shell">';

echo '<header class="aat-qrview-hero">'
    . '<div class="aat-qrview-hero-title">'
    . $e(__('Physical container', 'auchanassettracker'))
    . ' — ' . $e($name)
    . '</div>'
    . '<div class="aat-qrview-hero-code">' . $e($code !== '' ? $code : '—') . '</div>'
    . '<div class="aat-qrview-hero-loc">' . $e($loc) . '</div>'
    . '</header>';

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
        if ($item_name === '') {
            $item_name = '#' . (int) ($it['id'] ?? 0);
        }

        $serial = trim((string) ($it['serial'] ?? ''));
        if ($serial === '') {
            $serial = '—';
        }

        $tid = (int) ($it['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
        $typeName = '';
        if ($tid > 0) {
            $typeName = Dropdown::getDropdownName(
                PluginAuchanassettrackerEquipmenttype::getTable(),
                $tid
            );
        }
        if ($typeName === '' || $typeName === '&nbsp;' || $typeName === '-1') {
            $typeName = '—';
        }

        $added = trim((string) ($it['date_creation'] ?? ''));
        if ($added !== '' && class_exists('Html', false) && method_exists('Html', 'convDateTime')) {
            $added = Html::convDateTime($added);
        } elseif ($added === '') {
            $added = '—';
        }

        echo '<tr>'
            . '<td data-label="' . $e(__('Name')) . '">'
            . '<span class="aat-qrview-item-name">' . $e($item_name) . '</span></td>'
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
