<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerStockbylocation::canView()) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginAuchanassettrackerStockbylocation::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_DASHBOARD
);

PluginAuchanassettrackerStockbylocation::rebuild();
PluginAuchanassettrackerStockbylocation::ensureDisplayPreferences();
Search::show(PluginAuchanassettrackerStockbylocation::class);

Html::footer();
