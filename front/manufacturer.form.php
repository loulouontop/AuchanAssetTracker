<?php
include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();
Session::addMessageAfterRedirect(
    __('Use GLPI Setup → Dropdowns → Manufacturer.', 'auchanassettracker'),
    true,
    INFO
);
Html::redirect(plugin_auchanassettracker_web_dir() . '/front/equipment.php');
