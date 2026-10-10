<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canTransfer()) {
    Html::header(
        __('Transfer', 'auchanassettracker'),
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_TRANSFER
    );
    Html::displayRightError();
    exit;
}

Html::header(
    __('Transfer', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_TRANSFER
);

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

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

echo "<div class='aat-workspace'>";

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

    PluginAuchanassettrackerMenu::beginNativeFormCard(
        sprintf(__('Transfer #%d', 'auchanassettracker'), $id),
        PluginAuchanassettrackerTransfer::getIcon()
    );

    echo "<p><strong>" . __('From') . ":</strong> "
        . PluginAuchanassettrackerAllocation::locationNameLink((int) $tr->fields['locations_id_source'])
        . " → <strong>" . __('To') . ":</strong> "
        . PluginAuchanassettrackerAllocation::locationNameLink((int) $tr->fields['locations_id_dest'])
        . "</p>";
    echo "<p><strong>" . __('Status') . ":</strong> "
        . Html::entities_deep($status_labels[$st] ?? $st) . "</p>";

    $items = PluginAuchanassettrackerTransfer::getItems($id);
    $dest = (int) $tr->fields['locations_id_dest'];
    $can_validate = ($tr->fields['transfer_status'] === PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT)
        && (PluginAuchanassettrackerRighthelper::canAccessLocation($dest)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin());

    if ($can_validate && PluginAuchanassettrackerContainer::countAtLocation($dest) <= 0) {
        echo "<div class='alert alert-warning'>"
            . __('No container at destination. Create one first, then come back to validate.', 'auchanassettracker')
            . " <a class='btn btn-sm btn-primary' href='"
            . Html::entities_deep($base . '/front/container.form.php') . "'>"
            . __('Create container', 'auchanassettracker') . "</a></div>";
    }

    if ($can_validate && PluginAuchanassettrackerContainer::countAtLocation($dest) > 0) {
        echo "<form method='post' action=''>";
        echo Html::hidden('id', ['value' => $id]);
        echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
        echo "<thead><tr><th>" . __('Equipment') . "</th><th>"
            . __('Serial number') . "</th><th>" . __('Container', 'auchanassettracker') . " *</th></tr></thead><tbody>";
        foreach ($items as $it) {
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->getFromDB((int) $it['plugin_auchanassettracker_equipments_id']);
            $eid = (int) $eq->getID();
            echo "<tr><td>" . Html::entities_deep($eq->fields['name'] ?? '')
                . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '')
                . "</td><td>";
            echo "<span class='aat-container-field' data-aat-width='220px'>";
            PluginAuchanassettrackerContainer::dropdownWithActions([
                'name'      => "container[$eid]",
                'condition' => [
                    'locations_id' => $dest,
                    'is_active'    => 1,
                    'is_deleted'   => 0,
                ],
                'width' => '220px',
            ]);
            echo "</span></td></tr>";
        }
        echo "</tbody></table></div>";
        echo "<div class='text-center mt-3'>";
        echo Html::submit(__('Validate transfer', 'auchanassettracker'), [
            'name'  => 'validate_transfer',
            'class' => 'btn btn-primary',
        ]);
        echo "</div>";
        Html::closeForm();
    } else {
        echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
        echo "<thead><tr><th>" . __('Equipment') . "</th><th>"
            . __('Serial number') . "</th></tr></thead><tbody>";
        foreach ($items as $it) {
            $eq = new PluginAuchanassettrackerEquipment();
            $eq->getFromDB((int) $it['plugin_auchanassettracker_equipments_id']);
            echo "<tr><td>" . Html::entities_deep($eq->fields['name'] ?? '')
                . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '') . "</td></tr>";
        }
        echo "</tbody></table></div>";
    }

    PluginAuchanassettrackerMenu::endNativeFormCard();
} else {
    $available = PluginAuchanassettrackerEquipment::findByStatus(
        PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
        $scope
    );

    PluginAuchanassettrackerMenu::beginNativeFormCard(
        __('New transfer', 'auchanassettracker'),
        PluginAuchanassettrackerTransfer::getIcon()
    );

    echo "<form method='post' action=''>";
    echo "<div class='mb-3'><label class='form-label'>"
        . __('Destination location', 'auchanassettracker') . " *</label>";
    Location::dropdown(['name' => 'locations_id_dest']);
    echo "</div>";
    echo "<div class='mb-3'><label class='form-label'>" . __('Notes') . "</label>";
    echo "<textarea name='notes' class='form-control' rows='2'></textarea></div>";

    echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
    echo "<thead><tr><th></th><th>" . __('Name') . "</th><th>" . __('Serial number') . "</th><th>"
        . __('Container', 'auchanassettracker') . "</th></tr></thead><tbody>";
    $shown = 0;
    foreach ($available as $e) {
        if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
            continue;
        }
        $shown++;
        echo "<tr><td><input type='checkbox' name='equipment_ids[]' value='"
            . (int) $e['id'] . "'></td><td>"
            . Html::entities_deep($e['name'] ?? '') . "</td><td>"
            . Html::entities_deep($e['serial'] ?? '') . "</td><td>"
            . Html::entities_deep(Dropdown::getDropdownName(
                PluginAuchanassettrackerContainer::getTable(),
                (int) $e['plugin_auchanassettracker_containers_id']
            )) . "</td></tr>";
    }
    if ($shown === 0) {
        echo "<tr><td colspan='4' class='text-muted'>" . __('None.', 'auchanassettracker') . "</td></tr>";
    }
    echo "</tbody></table></div>";
    echo "<div class='text-center mt-3'>";
    echo Html::submit(__('Start transfer', 'auchanassettracker'), [
        'name'  => 'create_transfer',
        'class' => 'btn btn-primary',
    ]);
    echo "</div>";
    Html::closeForm();

    PluginAuchanassettrackerMenu::endNativeFormCard();
}

echo "</div>";
Html::footer();
