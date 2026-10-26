<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canViewReports()) {
    Html::displayRightError();
    exit;
}

$types = PluginAuchanassettrackerReport::getTypes();
$type  = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
if ($type !== '' && !isset($types[$type])) {
    $type = '';
}

$filters = [
    'locations_id'  => (int) ($_REQUEST['locations_id'] ?? 0),
    'users_id'      => (int) ($_REQUEST['users_id'] ?? 0),
    'containers_id' => (int) ($_REQUEST['containers_id'] ?? 0),
    'date_from'     => (string) ($_REQUEST['date_from'] ?? ''),
    'date_to'       => (string) ($_REQUEST['date_to'] ?? ''),
];

// Scoped roles always use their location.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if ($scope !== null) {
    $filters['locations_id'] = (int) $scope;
}

$export = (string) ($_REQUEST['export'] ?? '');
$submitted = $type !== ''
    || isset($_REQUEST['run'])
    || ($export === 'csv' || $export === 'excel');

$errors = [];
$report = null;
if ($submitted) {
    $errors = PluginAuchanassettrackerReport::validate($type, $filters);
    if ($errors === []) {
        // Default period only when movements needs it and dates were left blank after validation passed.
        if ($type === 'movements') {
            if ($filters['date_from'] === '') {
                $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
            }
            if ($filters['date_to'] === '') {
                $filters['date_to'] = date('Y-m-d');
            }
        }
        $report = PluginAuchanassettrackerReport::run($type, $filters);
        if ($export === 'csv' || $export === 'excel') {
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
    }
}

Html::header(
    __('Reports', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_REPORT
);

$base = plugin_auchanassettracker_web_dir();
$req = " <span class='aat-required'>*</span>";

if (function_exists('plugin_auchanassettracker_emit_assets')) {
    plugin_auchanassettracker_emit_assets();
}

echo "<div class='aat-page aat-report-page'>";
PluginAuchanassettrackerMenu::beginNativeFormCard(
    __('Reports', 'auchanassettracker'),
    'ti ti-report'
);

$required_map = [];
foreach (array_keys($types) as $t) {
    $required_map[$t] = PluginAuchanassettrackerReport::getRequiredFilters($t);
}

echo "<form method='get' action='" . Html::entities_deep($base . '/front/report.php')
    . "' class='mb-3 aat-report-form' id='aat-report-form'"
    . " data-aat-required='" . Html::entities_deep(json_encode($required_map, JSON_UNESCAPED_UNICODE)) . "'"
    . " data-aat-msg-type='" . Html::entities_deep(__('Please select a report type.', 'auchanassettracker')) . "'"
    . " data-aat-msg-user='" . Html::entities_deep(__('User is required for this report.', 'auchanassettracker')) . "'"
    . " data-aat-msg-container='" . Html::entities_deep(__('Container is required for this report.', 'auchanassettracker')) . "'"
    . " data-aat-msg-location='" . Html::entities_deep(__('Location is required for this report.', 'auchanassettracker')) . "'"
    . " data-aat-msg-period='" . Html::entities_deep(__('Period is required for this report.', 'auchanassettracker')) . "'"
    . ">";

echo "<div class='aat-report-grid'>";

// Report type — always mandatory.
echo "<div class='aat-report-field' data-aat-filter='type'>";
echo "<label class='form-label'>"
    . __('Report type', 'auchanassettracker') . $req . '</label>';
$type_choices = ['' => Dropdown::EMPTY_VALUE] + $types;
Dropdown::showFromArray('type', $type_choices, [
    'value' => $type,
    'width' => '100%',
]);
echo '</div>';

// Location — required for inventory_container (when not scoped).
echo "<div class='aat-report-field' data-aat-filter='locations_id'>";
echo "<label class='form-label'>" . __('Location')
    . " <span class='aat-required aat-req-mark' hidden>*</span></label>";
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

// User — required for equipment_user.
echo "<div class='aat-report-field' data-aat-filter='users_id'>";
echo "<label class='form-label'>" . __('User')
    . " <span class='aat-required aat-req-mark' hidden>*</span></label>";
User::dropdown([
    'name'                => 'users_id',
    'value'               => $filters['users_id'],
    'right'               => 'all',
    'display_emptychoice' => true,
    'width'               => '100%',
]);
echo '</div>';

// Container — required for inventory_container.
echo "<div class='aat-report-field' data-aat-filter='containers_id'>";
echo "<label class='form-label'>" . __('Container', 'auchanassettracker')
    . " <span class='aat-required aat-req-mark' hidden>*</span></label>";
PluginAuchanassettrackerContainer::dropdown([
    'name'                => 'containers_id',
    'value'               => $filters['containers_id'],
    'display_emptychoice' => true,
    'width'               => '100%',
]);
echo '</div>';

// Period: from on the left, to on the right (compact).
echo "<div class='aat-report-period' data-aat-filter='period'>";
echo "<div class='aat-report-period-field aat-report-period-from'>";
echo "<label class='form-label'>" . __('From')
    . " <span class='aat-required aat-req-mark' hidden>*</span></label>";
echo Html::input('date_from', [
    'type'  => 'date',
    'value' => $filters['date_from'] !== ''
        ? $filters['date_from']
        : date('Y-m-d', strtotime('-30 days')),
    'class' => 'form-control form-control-sm aat-report-date',
]);
echo '</div>';
echo "<div class='aat-report-period-field aat-report-period-to'>";
echo "<label class='form-label'>" . __('To')
    . " <span class='aat-required aat-req-mark' hidden>*</span></label>";
echo Html::input('date_to', [
    'type'  => 'date',
    'value' => $filters['date_to'] !== '' ? $filters['date_to'] : date('Y-m-d'),
    'class' => 'form-control form-control-sm aat-report-date',
]);
echo '</div>';
echo '</div>';

echo "<div class='aat-report-actions'>";
echo Html::submit(__('Run', 'auchanassettracker'), [
    'name'  => 'run',
    'class' => 'btn btn-primary',
    'value' => '1',
]);
echo Html::submit(__('Export CSV', 'auchanassettracker'), [
    'name'  => 'export',
    'class' => 'btn btn-outline-secondary',
    'value' => 'csv',
]);
echo Html::submit(__('Export Excel', 'auchanassettracker'), [
    'name'  => 'export',
    'class' => 'btn btn-outline-secondary',
    'value' => 'excel',
]);
echo '</div>';

echo '</div>'; // grid
Html::closeForm();

if ($errors !== []) {
    echo "<div class='alert alert-danger'>";
    foreach ($errors as $err) {
        echo '<div>' . Html::entities_deep($err) . '</div>';
    }
    echo '</div>';
}

if ($report !== null) {
    echo "<div class='table-responsive'><table class='table table-hover table-striped aat-report-table'><thead><tr>";
    foreach ($report['headers'] as $h) {
        echo '<th>' . Html::entities_deep($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($report['rows'] as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            // HTML cells (links / audit details) — same clickable style as Recent activity.
            echo '<td>' . PluginAuchanassettrackerReport::cellHtml($cell) . '</td>';
        }
        echo '</tr>';
    }
    if ($report['rows'] === []) {
        echo "<tr><td colspan='" . max(1, count($report['headers'])) . "'>"
            . __('No data.', 'auchanassettracker') . '</td></tr>';
    }
    echo '</tbody></table></div>';
} elseif ($errors === []) {
    echo "<p class='text-muted mb-0'>"
        . Html::entities_deep(__('Select a report type and run to see results.', 'auchanassettracker'))
        . '</p>';
}

PluginAuchanassettrackerMenu::endNativeFormCard();
echo '</div>';

Html::footer();
