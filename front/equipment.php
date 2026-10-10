<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canSeeEquipment()) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

// Bring native GLPI assets (Global / All assets) into this list, then search.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if (Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, READ)
    || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, CREATE)
    || Session::haveRight(PluginAuchanassettrackerProfile::RIGHT_EQUIPMENT, UPDATE)
) {
    PluginAuchanassettrackerEquipment::syncVisibleGlpiAssets($scope);
}

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

Html::footer();
