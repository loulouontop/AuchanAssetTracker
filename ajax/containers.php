<?php

/**
 * Containers for a location — JSON list for Select2 option refresh.
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!Session::getLoginUserID()) {
    http_response_code(403);
    exit;
}

$locations_id = (int) ($_GET['locations_id'] ?? 0);
$value = (int) ($_GET['value'] ?? 0);

// Scoped users are locked to their profile location.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if ($scope !== null) {
    $locations_id = $scope;
}

if ($locations_id > 0 && !PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
    $locations_id = 0;
    $value = 0;
}

header('Content-Type: application/json; charset=UTF-8');

$choices = PluginAuchanassettrackerContainer::dropdownOptionsForLocation($locations_id, $value);
$results = [];
foreach ($choices as $id => $text) {
    $results[] = [
        'id'   => (int) $id,
        'text' => (string) $text,
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
