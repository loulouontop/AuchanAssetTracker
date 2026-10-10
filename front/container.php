<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_CONTAINER
);

// Use Search facade (has proper use Glpi\Search\SearchEngine) — never bare SearchEngine::.
Search::show(PluginAuchanassettrackerContainer::class);

Html::footer();
