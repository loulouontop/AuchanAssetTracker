<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Bulk add accessories', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

if (!PluginAuchanassettrackerRighthelper::canManageStock()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

$base = Plugin::getWebDir(plugin_auchanassettracker_dir());
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    $qty = (int) ($_POST['quantity'] ?? 0);
    $input = [
        'plugin_auchanassettracker_equipmenttypes_id' => (int) ($_POST['plugin_auchanassettracker_equipmenttypes_id'] ?? 0),
        'plugin_auchanassettracker_manufacturers_id'  => (int) ($_POST['plugin_auchanassettracker_manufacturers_id'] ?? 0),
        'plugin_auchanassettracker_containers_id'     => (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0),
        'model' => (string) ($_POST['model'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
        'locations_id' => $scope ?? (int) ($_POST['locations_id'] ?? 0),
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

echo "<form method='post' action=''>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Bulk add accessories', 'auchanassettracker') . "</th></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . " *</td><td>";
PluginAuchanassettrackerEquipmenttype::dropdown([
    'name' => 'plugin_auchanassettracker_equipmenttypes_id',
    'condition' => ['category' => 'B', 'is_active' => 1],
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Manufacturer', 'auchanassettracker') . " *</td><td>";
PluginAuchanassettrackerManufacturer::dropdown(['name' => 'plugin_auchanassettracker_manufacturers_id']);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Model') . " *</td><td>";
echo Html::input('model', ['required' => true]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Quantity', 'auchanassettracker') . " *</td><td>";
echo Html::input('quantity', ['type' => 'number', 'min' => 1, 'max' => 500, 'value' => 1, 'required' => true]);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Location') . "</td><td>";
if ($scope !== null) {
    echo Dropdown::getDropdownName('glpi_locations', $scope);
    echo Html::hidden('locations_id', ['value' => $scope]);
    $loc = $scope;
} else {
    Location::dropdown(['name' => 'locations_id']);
    $loc = 0;
}
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker') . " *</td><td>";
$cond = ['is_active' => 1, 'is_deleted' => 0];
if ($loc > 0) {
    $cond['locations_id'] = $loc;
}
PluginAuchanassettrackerContainer::dropdown([
    'name' => 'plugin_auchanassettracker_containers_id',
    'condition' => $cond,
]);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td>";
echo "<textarea name='notes' class='form-control' rows='2'></textarea></td></tr>";

echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo Html::submit(__('Create', 'auchanassettracker'), ['name' => 'bulk_add', 'class' => 'btn btn-primary']);
echo " <a class='btn btn-secondary' href='" . $base . "/front/equipment.form.php'>" . __('Single item', 'auchanassettracker') . "</a>";
echo "</td></tr></table>";
Html::closeForm();

Html::footer();
