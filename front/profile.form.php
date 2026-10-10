<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

$item = new PluginAuchanassettrackerProfile();

if (isset($_POST['update'])) {
    PluginAuchanassettrackerProfile::saveFromPost($_POST);
    Session::addMessageAfterRedirect(__('Role mapping saved.', 'auchanassettracker'), true, INFO);
}

Html::back();
