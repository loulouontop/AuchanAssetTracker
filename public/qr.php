<?php
/**
 * Public container QR view — no GLPI login (internal network / hosted with GLPI).
 * Shows Available equipment in a container, grouped by type (read-only).
 */

$glpi_root = dirname(__DIR__, 3);
if (!is_readable($glpi_root . '/inc/includes.php')) {
    http_response_code(500);
    echo 'GLPI not found.';
    exit;
}

define('DO_NOT_CHECK_LOGIN', 1);

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

$e = static function (?string $value): string {
    return Html::entities_deep((string) $value);
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
$grouped = [];
foreach ($items as $it) {
    $tid = (int) ($it['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
    $grouped[$tid][] = $it;
}

$loc = Dropdown::getDropdownName('glpi_locations', (int) ($container['locations_id'] ?? 0));
if ($loc === '' || $loc === '&nbsp;' || $loc === '-1') {
    $loc = '—';
}

$code = (string) ($container['code'] ?? '');
$name = (string) ($container['name'] ?? '');
$title = trim($code !== '' ? $code : $name);
if ($title === '') {
    $title = __('Physical container', 'auchanassettracker');
}

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . $e($title) . '</title>'
    . '<link rel="stylesheet" href="css/qrview.css">'
    . '</head><body class="aat-qrview"><div class="aat-qrview-shell">';

echo '<div class="card aat-qrview-header-card">'
    . '<div class="card-header"><h1 class="card-title">'
    . $e(__('Physical container', 'auchanassettracker'))
    . '</h1></div>'
    . '<div class="card-body">'
    . '<div class="aat-qrview-meta">'
    . '<div class="aat-qrview-field"><span class="aat-qrview-label">'
    . $e(__('Location'))
    . '</span><span class="aat-qrview-value">' . $e($loc) . '</span></div>'
    . '<div class="aat-qrview-field"><span class="aat-qrview-label">'
    . $e(__('Container code', 'auchanassettracker'))
    . '</span><span class="aat-qrview-value aat-qrview-code">' . $e($code !== '' ? $code : '—') . '</span></div>'
    . '<div class="aat-qrview-field"><span class="aat-qrview-label">'
    . $e(__('Container name', 'auchanassettracker'))
    . '</span><span class="aat-qrview-value">' . $e($name !== '' ? $name : '—') . '</span></div>'
    . '</div></div></div>';

echo '<div class="card aat-qrview-stock-card">'
    . '<div class="card-header"><h2 class="card-title">'
    . $e(__('Available stock', 'auchanassettracker'))
    . ' <span class="badge">' . count($items) . '</span>'
    . '</h2></div><div class="card-body p-0">';

if ($grouped === []) {
    echo '<div class="aat-qrview-empty">'
        . $e(__('No available equipment in this container.', 'auchanassettracker'))
        . '</div>';
} else {
    foreach ($grouped as $tid => $list) {
        $typeName = '';
        if ($tid > 0) {
            $typeName = Dropdown::getDropdownName(
                PluginAuchanassettrackerEquipmenttype::getTable(),
                $tid
            );
        }
        if ($typeName === '' || $typeName === '&nbsp;' || $typeName === '-1') {
            $typeName = __('Equipment type', 'auchanassettracker');
        }

        echo '<div class="aat-qrview-group">'
            . '<div class="aat-qrview-group-title">'
            . $e($typeName) . ' <span class="badge">' . count($list) . '</span>'
            . '</div>'
            . '<div class="table-responsive"><table class="table table-striped table-hover aat-qrview-table">'
            . '<thead><tr>'
            . '<th>' . $e(__('Name')) . '</th>'
            . '<th>' . $e(__('Serial number')) . '</th>'
            . '</tr></thead><tbody>';

        foreach ($list as $it) {
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

            echo '<tr>'
                . '<td data-label="' . $e(__('Name')) . '">'
                . '<span class="aat-qrview-item-name">' . $e($item_name) . '</span>'
                . '</td>'
                . '<td data-label="' . $e(__('Serial number')) . '">'
                . '<span class="aat-qrview-item-serial">' . $e($serial) . '</span>'
                . '</td>'
                . '</tr>';
        }

        echo '</tbody></table></div></div>';
    }
}

echo '</div></div>';

echo '<p class="aat-qrview-footer">'
    . $e(__('View only — to manage, open GLPI.', 'auchanassettracker'))
    . '</p>';

echo '</div></body></html>';
