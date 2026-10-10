<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

// Bring native GLPI assets (Global / All assets) into this list, then search.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if (PluginAuchanassettrackerRighthelper::canManageStock()
    || PluginAuchanassettrackerRighthelper::canAllocate()
    || PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    PluginAuchanassettrackerEquipment::syncVisibleGlpiAssets($scope);
}

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

Html::footer();
