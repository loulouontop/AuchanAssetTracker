<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canTransfer()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Transfer', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$base = Plugin::getWebDir(plugin_auchanassettracker_dir());
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// Validate existing transfer
if ($id > 0 && isset($_POST['validate_transfer'])) {
    $map = [];
    foreach ((array) ($_POST['container'] ?? []) as $eid => $cid) {
        $map[(int) $eid] = (int) $cid;
    }
    if (PluginAuchanassettrackerTransfer::validate($id, $map)) {
        Session::addMessageAfterRedirect(__('Transfer validated.', 'auchanassettracker'), true, INFO);
        Html::redirect($base . '/front/transfer.php');
        exit;
    }
}

// Create new transfer
if ($id <= 0 && isset($_POST['create_transfer'])) {
    $dest = (int) ($_POST['locations_id_dest'] ?? 0);
    $eids = array_map('intval', (array) ($_POST['equipment_ids'] ?? []));
    $tid = PluginAuchanassettrackerTransfer::initiate($dest, $eids, (string) ($_POST['notes'] ?? ''));
    if ($tid) {
        Session::addMessageAfterRedirect(__('Transfer initiated.', 'auchanassettracker'), true, INFO);
        Html::redirect($base . '/front/transfer.form.php?id=' . $tid);
        exit;
    }
}

if ($id > 0) {
    $tr = new PluginAuchanassettrackerTransfer();
    if (!$tr->getFromDB($id)) {
        Html::displayNotFoundError();
        exit;
    }

    $status_labels = [
        PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT => __('In transit', 'auchanassettracker'),
        PluginAuchanassettrackerTransfer::STATUS_COMPLETED  => __('Completed'),
        PluginAuchanassettrackerTransfer::STATUS_CANCELLED  => __('Cancelled'),
    ];
    $st = (string) ($tr->fields['transfer_status'] ?? '');

    echo "<div class='card mb-3'><div class='card-header'>";
    echo "<h3 class='card-title'>" . sprintf(__('Transfer #%d', 'auchanassettracker'), $id) . "</h3>";
    echo "</div><div class='card-body'>";
    echo "<p><strong>" . __('From') . ":</strong> "
        . Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $tr->fields['locations_id_source']))
        . " → <strong>" . __('To') . ":</strong> "
        . Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $tr->fields['locations_id_dest']))
        . "</p>";
    echo "<p><strong>" . __('Status') . ":</strong> "
        . Html::entities_deep($status_labels[$st] ?? $st) . "</p>";
    echo "</div></div>";

    $items = PluginAuchanassettrackerTransfer::getItems($id);
    $dest = (int) $tr->fields['locations_id_dest'];
    $can_validate = ($tr->fields['transfer_status'] === PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT)
        && (PluginAuchanassettrackerRighthelper::canAccessLocation($dest)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin());

    if ($can_validate && PluginAuchanassettrackerContainer::countAtLocation($dest) <= 0) {
        echo "<div class='alert alert-warning'>"
            . __('No container at destination. Create one first, then come back to validate.', 'auchanassettracker')
            . " <a class='btn btn-sm btn-primary' href='" . $base . "/front/container.form.php'>"
            . __('Create container', 'auchanassettracker') . "</a></div>";
    }

    if ($can_validate && PluginAuchanassettrackerContainer::countAtLocation($dest) > 0) {
        echo "<form method='post' action=''>";
        echo Html::hidden('id', ['value' => $id]);
        echo "<div class='card'><div class='card-body'>";
        echo "<table class='tab_cadre_fixe'><tr><th>" . __('Equipment') . "</th><th>"
            . __('Serial number') . "</th><th>" . __('Container', 'auchanassettracker') . " *</th></tr>";
        foreach ($items as $it) {
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->getFromDB((int) $it['plugin_auchanassettracker_equipments_id']);
            $eid = (int) $eq->getID();
            echo "<tr class='tab_bg_1'><td>" . Html::entities_deep($eq->fields['name'] ?? '')
                . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '')
                . "</td><td>";
            PluginAuchanassettrackerContainer::dropdown([
                'name' => "container[$eid]",
                'condition' => [
                    'locations_id' => $dest,
                    'is_active' => 1,
                    'is_deleted' => 0,
                ],
            ]);
            echo "</td></tr>";
        }
        echo "<tr class='tab_bg_2'><td colspan='3' class='center'>";
        echo Html::submit(__('Validate transfer', 'auchanassettracker'), [
            'name' => 'validate_transfer',
            'class' => 'btn btn-primary',
        ]);
        echo "</td></tr></table></div></div>";
        Html::closeForm();
    } else {
        echo "<div class='card'><div class='card-body'>";
        echo "<table class='table table-hover card-table'><thead><tr><th>" . __('Equipment') . "</th><th>"
            . __('Serial number') . "</th></tr></thead><tbody>";
        foreach ($items as $it) {
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->getFromDB((int) $it['plugin_auchanassettracker_equipments_id']);
            echo "<tr><td>" . Html::entities_deep($eq->fields['name'] ?? '')
                . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '') . "</td></tr>";
        }
        echo "</tbody></table></div></div>";
    }
} else {
    // New transfer form
    $available = PluginAuchanassettrackerEquipment::findByStatus(
        PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
        $scope
    );

    echo "<form method='post' action=''>";
    echo "<div class='card'><div class='card-body'>";
    echo "<table class='tab_cadre_fixe'><tr><th colspan='4'>"
        . __('New transfer', 'auchanassettracker') . "</th></tr>";
    echo "<tr class='tab_bg_1'><td>" . __('Destination location', 'auchanassettracker') . " *</td><td colspan='3'>";
    Location::dropdown(['name' => 'locations_id_dest']);
    echo "</td></tr>";
    echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
    echo "<textarea name='notes' class='form-control' rows='2'></textarea></td></tr>";
    echo "<tr><th></th><th>" . __('Name') . "</th><th>" . __('Serial number') . "</th><th>"
        . __('Container', 'auchanassettracker') . "</th></tr>";
    foreach ($available as $e) {
        if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
            continue;
        }
        echo "<tr class='tab_bg_1'><td><input type='checkbox' name='equipment_ids[]' value='"
            . (int) $e['id'] . "'></td><td>"
            . Html::entities_deep($e['name'] ?? '') . "</td><td>"
            . Html::entities_deep($e['serial'] ?? '') . "</td><td>"
            . Html::entities_deep(Dropdown::getDropdownName(
                PluginAuchanassettrackerContainer::getTable(),
                (int) $e['plugin_auchanassettracker_containers_id']
            )) . "</td></tr>";
    }
    echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
    echo Html::submit(__('Start transfer', 'auchanassettracker'), [
        'name' => 'create_transfer',
        'class' => 'btn btn-primary',
    ]);
    echo "</td></tr></table></div></div>";
    Html::closeForm();
}

Html::footer();
