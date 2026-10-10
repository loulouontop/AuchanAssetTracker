<?php

/**
 * Legacy URL (old Equipment type dropdown). Always redirect — never call
 * SearchEngine here (GLPI 11 class is Glpi\Search\SearchEngine).
 */
include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::redirect(plugin_auchanassettracker_web_dir() . '/front/equipment.php');
