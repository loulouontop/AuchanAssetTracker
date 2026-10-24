<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canViewReports()) {
    Html::displayRightError();
    exit;
}

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? 'inventory_location');
$types = PluginAuchanassettrackerReport::getTypes();
if (!isset($types[$type])) {
    $type = 'inventory_location';
}

$filters = [
    'locations_id'  => (int) ($_REQUEST['locations_id'] ?? 0),
    'users_id'      => (int) ($_REQUEST['users_id'] ?? 0),
    'containers_id' => (int) ($_REQUEST['containers_id'] ?? 0),
    'date_from'     => (string) ($_REQUEST['date_from'] ?? date('Y-m-d', strtotime('-30 days'))),
    'date_to'       => (string) ($_REQUEST['date_to'] ?? date('Y-m-d')),
];

$export = (string) ($_GET['export'] ?? '');
if ($export === 'csv' || $export === 'excel') {
    $report = PluginAuchanassettrackerReport::run($type, $filters);
    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="aat-report-' . $type . '.xls"');
        echo PluginAuchanassettrackerReport::toExcelXml($report);
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="aat-report-' . $type . '.csv"');
        echo PluginAuchanassettrackerReport::toCsv($report);
    }
    exit;
}

Html::header(
    __('Reports', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_REPORT
);

$base = plugin_auchanassettracker_web_dir();
$qs = http_build_query([
    'type'          => $type,
    'locations_id'  => $filters['locations_id'],
    'users_id'      => $filters['users_id'],
    'containers_id' => $filters['containers_id'],
    'date_from'     => $filters['date_from'],
    'date_to'       => $filters['date_to'],
]);

echo "<div class='aat-page'>";
PluginAuchanassettrackerMenu::beginNativeFormCard(
    __('Reports', 'auchanassettracker'),
    'ti ti-report'
);

echo "<form method='get' action='" . Html::entities_deep($base . '/front/report.php') . "' class='mb-3'>";
echo "<div class='row g-3'>";

echo "<div class='col-md-6'><label class='form-label'>"
    . __('Report type', 'auchanassettracker') . '</label>';
Dropdown::showFromArray('type', $types, [
    'value' => $type,
    'width' => '100%',
]);
echo '</div>';

$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
echo "<div class='col-md-6'><label class='form-label'>" . __('Location') . '</label>';
if ($scope !== null) {
    $loc_name = $scope > 0
        ? Dropdown::getDropdownName('glpi_locations', $scope)
        : __('None');
    echo '<div class="form-control-plaintext">' . Html::entities_deep($loc_name) . '</div>';
    echo Html::hidden('locations_id', ['value' => (int) $scope]);
} else {
    Location::dropdown([
        'name'                => 'locations_id',
        'value'               => $filters['locations_id'],
        'display_emptychoice' => true,
        'width'               => '100%',
    ]);
}
echo '</div>';

echo "<div class='col-md-6'><label class='form-label'>" . __('User') . '</label>';
User::dropdown([
    'name'                => 'users_id',
    'value'               => $filters['users_id'],
    'right'               => 'all',
    'display_emptychoice' => true,
    'width'               => '100%',
]);
echo '</div>';

echo "<div class='col-md-6'><label class='form-label'>"
    . __('Container', 'auchanassettracker') . '</label>';
PluginAuchanassettrackerContainer::dropdown([
    'name'                => 'containers_id',
    'value'               => $filters['containers_id'],
    'display_emptychoice' => true,
    'width'               => '100%',
]);
echo '</div>';

echo "<div class='col-md-6'><label class='form-label'>" . __('Period') . '</label>';
echo "<div class='d-flex align-items-center gap-2 flex-wrap'>";
echo Html::input('date_from', [
    'type'  => 'date',
    'value' => $filters['date_from'],
    'class' => 'form-control',
]);
echo '<span>→</span>';
echo Html::input('date_to', [
    'type'  => 'date',
    'value' => $filters['date_to'],
    'class' => 'form-control',
]);
echo '</div></div>';

echo "<div class='col-12 d-flex flex-wrap gap-2'>";
echo Html::submit(__('Run', 'auchanassettracker'), ['class' => 'btn btn-primary']);
echo " <a class='btn btn-outline-secondary' href='?" . Html::entities_deep($qs) . "&export=csv'>"
    . __('Export CSV', 'auchanassettracker') . '</a>';
echo " <a class='btn btn-outline-secondary' href='?" . Html::entities_deep($qs) . "&export=excel'>"
    . __('Export Excel', 'auchanassettracker') . '</a>';
echo '</div></div>';
Html::closeForm();

$report = PluginAuchanassettrackerReport::run($type, $filters);
echo "<div class='table-responsive'><table class='table table-hover table-striped'><thead><tr>";
foreach ($report['headers'] as $h) {
    echo '<th>' . Html::entities_deep($h) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($report['rows'] as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . Html::entities_deep((string) $cell) . '</td>';
    }
    echo '</tr>';
}
if ($report['rows'] === []) {
    echo "<tr><td colspan='" . max(1, count($report['headers'])) . "'>"
        . __('No data.', 'auchanassettracker') . '</td></tr>';
}
echo '</tbody></table></div>';

PluginAuchanassettrackerMenu::endNativeFormCard();
echo '</div>';

Html::footer();
