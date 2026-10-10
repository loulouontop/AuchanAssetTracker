<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::isCentralAdmin()
    && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Auchan Asset Tracker - configuration', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$base = Plugin::getWebDir(plugin_auchanassettracker_dir());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_thresholds'])) {
    PluginAuchanassettrackerConfig::saveThresholds(
        (int) ($_POST['allocation_confirm_days'] ?? 5),
        (int) ($_POST['transfer_validate_days'] ?? 7),
        (int) ($_POST['service_max_days'] ?? 30)
    );
    Session::addMessageAfterRedirect(__('Thresholds saved.', 'auchanassettracker'), true, INFO);
    Html::redirect($base . '/front/config.form.php');
    exit;
}

echo "<form method='post' action=''>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Alert thresholds', 'auchanassettracker') . "</th></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Allocation confirmation (working days)', 'auchanassettracker') . "</td><td>";
echo Html::input('allocation_confirm_days', [
    'type' => 'number',
    'min' => 1,
    'value' => PluginAuchanassettrackerConfig::getAllocationConfirmDays(),
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Transfer validation (days)', 'auchanassettracker') . "</td><td>";
echo Html::input('transfer_validate_days', [
    'type' => 'number',
    'min' => 1,
    'value' => PluginAuchanassettrackerConfig::getTransferValidateDays(),
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Service max period (days)', 'auchanassettracker') . "</td><td>";
echo Html::input('service_max_days', [
    'type' => 'number',
    'min' => 1,
    'value' => PluginAuchanassettrackerConfig::getServiceMaxDays(),
]);
echo "</td></tr>";
echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo Html::submit(_sx('button', 'Save'), ['name' => 'save_thresholds', 'class' => 'btn btn-primary']);
echo "</td></tr></table>";
Html::closeForm();

echo "<p class='mt-3'>"
    . __('Map GLPI profiles to Asset Tracker roles under Administration → Profiles → Auchan Asset Tracker tab.', 'auchanassettracker')
    . "</p>";

echo "<p><a href='" . $base . "/front/equipmenttype.php'>" . __('Equipment types', 'auchanassettracker') . "</a> · ";
echo "<a href='" . $base . "/front/manufacturer.php'>" . __('Manufacturers', 'auchanassettracker') . "</a> · ";
echo "<a href='" . $base . "/front/equipment.bulk.php'>" . __('Bulk add accessories', 'auchanassettracker') . "</a></p>";

Html::footer();
