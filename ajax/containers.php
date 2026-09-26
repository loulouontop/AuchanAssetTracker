<?php

/**
 * Containers for a location — JSON list or native GLPI dropdown HTML.
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!Session::getLoginUserID()) {
    http_response_code(403);
    exit;
}

$locations_id = (int) ($_GET['locations_id'] ?? 0);
$display = (string) ($_GET['display'] ?? 'json');
$value = (int) ($_GET['value'] ?? 0);

// Scoped users are locked to their profile location.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if ($scope !== null) {
    $locations_id = $scope;
}

$condition = [
    'is_deleted' => 0,
];

if ($locations_id > 0 && PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
    $condition['locations_id'] = $locations_id;
    if ($value > 0) {
        $tmp = new PluginAuchanassettrackerContainer();
        if (!$tmp->getFromDB($value)
            || (int) ($tmp->fields['locations_id'] ?? 0) !== $locations_id) {
            $value = 0;
        }
    }
} else {
    // No location (or not allowed) → empty container menu.
    $condition['locations_id'] = -1;
    $value = 0;
}

if ($display === 'dropdown') {
    header('Content-Type: text/html; charset=UTF-8');
    PluginAuchanassettrackerContainer::dropdownWithActions([
        'name'          => 'plugin_auchanassettracker_containers_id',
        'value'         => $value,
        'condition'     => $condition,
        'width'         => '100%',
        'sync_location' => false,
        'plain'         => true,
    ]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$choices = PluginAuchanassettrackerContainer::dropdownOptionsForLocation(
    ($condition['locations_id'] ?? 0) > 0 ? (int) $condition['locations_id'] : 0,
    $value
);
$results = [];
foreach ($choices as $id => $text) {
    $results[] = [
        'id'   => (int) $id,
        'text' => (string) $text,
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
