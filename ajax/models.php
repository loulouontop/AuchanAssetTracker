<?php

/**
 * GLPI native model dropdown for a selected asset itemtype (Select2 refresh).
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!Session::getLoginUserID()) {
    http_response_code(403);
    exit;
}

$itemtype = (string) ($_GET['itemtype'] ?? 'Computer');
$value = (int) ($_GET['value'] ?? 0);
$rand = (int) ($_GET['rand'] ?? 0);

header('Content-Type: text/html; charset=UTF-8');
PluginAuchanassettrackerEquipment::dropdownModel([
    'itemtype' => $itemtype,
    'value'    => $value,
    'rand'     => $rand > 0 ? $rand : mt_rand(),
    'width'    => '220px',
]);
