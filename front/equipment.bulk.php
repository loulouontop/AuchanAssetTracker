<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerBulk();

if (!PluginAuchanassettrackerRighthelper::canManageStock()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::header(
        PluginAuchanassettrackerBulk::getTypeName(1),
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_BULK
    );
    Html::displayRightError();
    exit;
}

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['bulk_add']) || isset($_POST['add']))) {
    $item->check(-1, CREATE, $_POST);
    $qty = (int) ($_POST['quantity'] ?? 0);
    $input = [
        'itemtype'                                => (string) ($_POST['itemtype'] ?? 'Peripheral'),
        'manufacturers_id'                        => (int) ($_POST['manufacturers_id'] ?? 0),
        'models_id'                               => (int) ($_POST['models_id'] ?? 0),
        'plugin_auchanassettracker_containers_id' => (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0),
        'name'                                    => (string) ($_POST['name'] ?? ''),
        'notes'                                   => (string) ($_POST['notes'] ?? ''),
        'locations_id'                            => $scope ?? (int) ($_POST['locations_id'] ?? 0),
    ];
    $created = PluginAuchanassettrackerEquipment::bulkAddAccessories($input, $qty);
    Session::addMessageAfterRedirect(
        sprintf(__('%d accessory record(s) created.', 'auchanassettracker'), $created),
        true,
        INFO
    );
    Html::redirect($base . '/front/equipment.php');
    exit;
}

Html::header(
    PluginAuchanassettrackerBulk::getTypeName(1),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_BULK
);

$item->check(-1, CREATE);
// Same page chrome as container/equipment: display() → tabs → showForm (Twig).
echo "<div class='aat-bulk-page'>";
$item->display(['id' => 0]);
echo "</div>";

Html::footer();
