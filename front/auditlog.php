<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerAuditlog::canView()) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginAuchanassettrackerAuditlog::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_DASHBOARD
);

PluginAuchanassettrackerAuditlog::ensureDisplayPreferences();
Search::show(PluginAuchanassettrackerAuditlog::class);

Html::footer();
