<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$dropdown = new PluginAuchanassettrackerManufacturer();
include GLPI_ROOT . '/front/dropdown.common.form.php';
