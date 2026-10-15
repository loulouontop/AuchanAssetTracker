<?php
/**
 * Container QR scan view — GLPI login required (internal network).
 * Shows Available equipment in a container, grouped by type (read-only).
 */

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$token = trim((string) ($_GET['t'] ?? ''));
$container = PluginAuchanassettrackerContainer::findByToken($token);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');

if ($container === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . Html::entities_deep(__('Not found', 'auchanassettracker')) . '</title></head>'
        . '<body style="font-family:sans-serif;padding:24px">'
        . '<h1>' . Html::entities_deep(__('Container not found', 'auchanassettracker')) . '</h1>'
        . '<p>' . Html::entities_deep(__('Invalid or inactive QR code.', 'auchanassettracker')) . '</p>'
        . '</body></html>';
    exit;
}

$locations_id = (int) ($container['locations_id'] ?? 0);
if ($locations_id > 0 && !PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
    Html::displayRightError();
    exit;
}

$items = PluginAuchanassettrackerEquipment::listInContainer((int) $container['id']);
$grouped = [];
foreach ($items as $it) {
    $tid = (int) $it['plugin_auchanassettracker_equipmenttypes_id'];
    $grouped[$tid][] = $it;
}

$loc = Dropdown::getDropdownName('glpi_locations', $locations_id);

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . Html::entities_deep($container['code'] ?? '') . '</title>'
    . '<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f6f8;color:#1a1a1a}
header{background:#0b3d2e;color:#fff;padding:16px 20px}
h1{margin:0;font-size:1.25rem}
.meta{opacity:.85;font-size:.9rem;margin-top:4px}
main{padding:16px 20px;max-width:720px;margin:0 auto}
.group{background:#fff;border-radius:8px;padding:12px 16px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.group h2{margin:0 0 8px;font-size:1rem;color:#0b3d2e}
.item{padding:8px 0;border-top:1px solid #eee;font-size:.95rem}
.item:first-of-type{border-top:0}
.serial{color:#555;font-size:.85rem}
.empty{padding:24px;text-align:center;color:#666}
.note{font-size:.8rem;color:#666;margin-top:16px;text-align:center}
</style></head><body>';

echo '<header><h1>' . Html::entities_deep(($container['code'] ?? '') . ' — ' . ($container['name'] ?? '')) . '</h1>'
    . '<div class="meta">' . Html::entities_deep($loc) . '</div></header><main>';

if ($grouped === []) {
    echo '<div class="empty">'
        . Html::entities_deep(__('No available equipment in this container.', 'auchanassettracker'))
        . '</div>';
} else {
    foreach ($grouped as $tid => $list) {
        $typeName = Dropdown::getDropdownName(PluginAuchanassettrackerEquipmenttype::getTable(), $tid);
        echo '<div class="group"><h2>' . Html::entities_deep($typeName) . ' (' . count($list) . ')</h2>';
        foreach ($list as $it) {
            echo '<div class="item"><strong>'
                . Html::entities_deep($it['model'] ?? $it['name'] ?? '')
                . '</strong>';
            if (!empty($it['serial'])) {
                echo '<div class="serial">SN: ' . Html::entities_deep($it['serial']) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

echo '<p class="note">'
    . Html::entities_deep(__('View only — manage stock from the GLPI desktop app.', 'auchanassettracker'))
    . '</p>';
echo '</main></body></html>';
