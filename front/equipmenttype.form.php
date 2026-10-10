<?php
include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();
Session::addMessageAfterRedirect(
    __('Use GLPI native asset types (Computer, Monitor, …) on the equipment form.', 'auchanassettracker'),
    true,
    INFO
);
Html::redirect(plugin_auchanassettracker_web_dir() . '/front/equipment.php');
