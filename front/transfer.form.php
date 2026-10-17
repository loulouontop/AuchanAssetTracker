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
$edit_mode = isset($_GET['edit']) || isset($_POST['update_transfer']);
$req = " <span class='aat-required'>*</span>";

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

if ($id > 0 && isset($_POST['reject_transfer'])) {
    $comment = (string) ($_POST['reject_comment'] ?? '');
    if (PluginAuchanassettrackerTransfer::rejectByRecipient($id, $comment)) {
        Session::addMessageAfterRedirect(
            __('Transfer rejected. Equipment restored at source.', 'auchanassettracker'),
            true,
            WARNING
        );
        Html::redirect($base . '/front/transfer.php');
        exit;
    }
}

if ($id > 0 && isset($_POST['update_transfer'])) {
    $dest = (int) ($_POST['locations_id_dest'] ?? 0);
    $notes = (string) ($_POST['notes'] ?? '');
    $eids = array_map('intval', (array) ($_POST['equipment_ids'] ?? []));
    if (PluginAuchanassettrackerTransfer::updateInTransit($id, $dest, $notes, $eids)) {
        Session::addMessageAfterRedirect(__('Item successfully updated'), true, INFO);
        Html::redirect($base . '/front/transfer.form.php?id=' . $id);
        exit;
    }
    $edit_mode = true;
}

if ($id > 0 && isset($_POST['delete_transfer'])) {
    if (PluginAuchanassettrackerTransfer::cancelBySender($id)) {
        Session::addMessageAfterRedirect(
            __('Transfer cancelled. Equipment restored at source.', 'auchanassettracker'),
            true,
            INFO
        );
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
    $reject_comment = trim((string) ($tr->fields['reject_comment'] ?? ''));
    $is_rejected = $st === PluginAuchanassettrackerTransfer::STATUS_CANCELLED && $reject_comment !== '';
    if ($is_rejected) {
        $status_labels[PluginAuchanassettrackerTransfer::STATUS_CANCELLED] = __(
            'Rejected',
            'auchanassettracker'
        );
    }
    $is_in_transit = $st === PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT;
    $can_sender = $is_in_transit && PluginAuchanassettrackerTransfer::isSenderForTransfer($tr);

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
    echo "<p><strong>" . __('Transferred by', 'auchanassettracker') . ":</strong> "
        . PluginAuchanassettrackerAllocation::userNameLink((int) ($tr->fields['users_id_initiator'] ?? 0))
        . "</p>";
    $validator_id = (int) ($tr->fields['users_id_validator'] ?? 0);
    $rejector_id = (int) ($tr->fields['users_id_rejector'] ?? 0);
    if ($rejector_id <= 0 && $is_rejected) {
        $rejector_id = $validator_id;
    }
    if ($st === PluginAuchanassettrackerTransfer::STATUS_COMPLETED || ($validator_id > 0 && !$is_rejected)) {
        echo "<p><strong>" . __('Approved by', 'auchanassettracker') . ":</strong> "
            . PluginAuchanassettrackerAllocation::userNameLink($validator_id)
            . "</p>";
    }
    if ($is_rejected) {
        echo "<p><strong>" . __('Rejected by', 'auchanassettracker') . ":</strong> "
            . PluginAuchanassettrackerAllocation::userNameLink($rejector_id)
            . "</p>";
    }

    if (trim((string) ($tr->fields['notes'] ?? '')) !== '') {
        echo "<p><strong>" . __('Notes') . ":</strong> "
            . nl2br(Html::entities_deep((string) $tr->fields['notes'])) . "</p>";
    }
    if ($reject_comment !== '') {
        echo "<p><strong>" . __('Reject comment', 'auchanassettracker') . ":</strong> "
            . nl2br(Html::entities_deep($reject_comment)) . "</p>";
    }

    // Sender edit while in transit (destination, notes, equipment).
    if ($can_sender && $edit_mode) {
        $source = (int) $tr->fields['locations_id_source'];
        $edit_rows = PluginAuchanassettrackerTransfer::getEditableEquipmentRows($id, $source);

        echo "<form method='post' action='' class='mb-4'>";
        echo Html::hidden('id', ['value' => $id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='mb-3'><label class='form-label'>"
            . __('Destination location', 'auchanassettracker') . $req . "</label>";
        $dest_opts = [
            'name'                => 'locations_id_dest',
            'value'               => (int) $tr->fields['locations_id_dest'],
            'display_emptychoice' => false,
            'width'               => '100%',
        ];
        if ($source > 0) {
            $dest_opts['used'] = [$source];
        }
        PluginAuchanassettrackerTransfer::dropdownDestinationLocation($dest_opts);
        echo "</div>";
        echo "<div class='mb-3'><label class='form-label'>" . __('Notes') . "</label>";
        echo "<textarea name='notes' class='form-control' rows='2'>"
            . Html::entities_deep((string) ($tr->fields['notes'] ?? ''))
            . "</textarea></div>";

        echo "<div class='mb-3'><label class='form-label'>"
            . __('Equipment') . $req . "</label>";
        echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
        echo "<thead><tr><th></th><th>" . __('Name') . "</th><th>" . __('Serial number') . "</th><th>"
            . __('Container', 'auchanassettracker') . "</th></tr></thead><tbody>";
        $shown = 0;
        foreach ($edit_rows as $e) {
            $shown++;
            $eid = (int) ($e['id'] ?? 0);
            $on = !empty($e['_on_transfer']);
            $cid = $on
                ? (int) ($e['_prev_container'] ?? 0)
                : (int) ($e['plugin_auchanassettracker_containers_id'] ?? 0);
            $cname = $cid > 0
                ? Dropdown::getDropdownName(PluginAuchanassettrackerContainer::getTable(), $cid)
                : ($on ? __('In transit', 'auchanassettracker') : '');
            echo "<tr><td><input type='checkbox' name='equipment_ids[]' value='"
                . $eid . "'" . ($on ? " checked" : '') . "></td><td>"
                . Html::entities_deep((string) ($e['name'] ?? '')) . "</td><td>"
                . Html::entities_deep((string) ($e['serial'] ?? '')) . "</td><td>"
                . Html::entities_deep((string) $cname) . "</td></tr>";
        }
        if ($shown === 0) {
            echo "<tr><td colspan='4' class='text-muted'>" . __('None.', 'auchanassettracker') . "</td></tr>";
        }
        echo "</tbody></table></div></div>";

        echo "<div class='d-flex gap-2'>";
        echo Html::submit(_x('button', 'Save'), [
            'name'  => 'update_transfer',
            'class' => 'btn btn-primary',
        ]);
        echo "<a class='btn btn-outline-secondary' href='"
            . Html::entities_deep($base . '/front/transfer.form.php?id=' . $id) . "'>"
            . Html::entities_deep(__('Cancel')) . "</a>";
        echo "</div>";
        Html::closeForm();
        PluginAuchanassettrackerMenu::endNativeFormCard();
        echo "</div>";
        Html::footer();
        exit;
    } elseif ($can_sender) {
        echo "<div class='mb-3 d-flex gap-2 flex-wrap'>";
        echo "<a class='btn btn-sm btn-outline-secondary' href='"
            . Html::entities_deep($base . '/front/transfer.form.php?id=' . $id . '&edit=1') . "'>"
            . "<i class='ti ti-edit'></i> " . Html::entities_deep(_x('button', 'Edit')) . "</a>";
        $confirm_js = json_encode(
            __('Cancel this transfer and restore equipment at source?', 'auchanassettracker'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        echo "<form method='post' action='' class='d-inline' onsubmit='return confirm({$confirm_js});'>";
        echo Html::hidden('id', ['value' => $id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<button type='submit' name='delete_transfer' value='1' class='btn btn-sm btn-outline-danger'>"
            . "<i class='ti ti-trash'></i> " . Html::entities_deep(_x('button', 'Delete')) . "</button>";
        Html::closeForm();
        echo "</div>";
    }

    $items = PluginAuchanassettrackerTransfer::getItems($id);
    $dest = (int) $tr->fields['locations_id_dest'];
    $can_validate = $is_in_transit
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
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
        echo "<thead><tr><th>" . __('Equipment') . "</th><th>"
            . __('Serial number') . "</th><th>"
            . __('Container', 'auchanassettracker') . $req . "</th></tr></thead><tbody>";
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

        // Recipient reject with mandatory comment (separate form).
        echo "<hr class='my-4'>";
        echo "<form method='post' action='' class='aat-reject-form'>";
        echo Html::hidden('id', ['value' => $id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<div class='mb-2'><label class='form-label'>"
            . Html::entities_deep(__('Comment')) . $req . "</label>";
        echo "<textarea name='reject_comment' class='form-control' rows='2' required"
            . " placeholder='"
            . Html::entities_deep(__('Required when rejecting a transfer', 'auchanassettracker'))
            . "'></textarea></div>";
        echo Html::submit(__('Reject transfer', 'auchanassettracker'), [
            'name'  => 'reject_transfer',
            'class' => 'btn btn-outline-danger',
        ]);
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

        // Destination with no containers yet can still reject.
        if ($can_validate) {
            echo "<hr class='my-4'>";
            echo "<form method='post' action='' class='aat-reject-form'>";
            echo Html::hidden('id', ['value' => $id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<div class='mb-2'><label class='form-label'>"
                . Html::entities_deep(__('Comment')) . $req . "</label>";
            echo "<textarea name='reject_comment' class='form-control' rows='2' required"
                . " placeholder='"
                . Html::entities_deep(__('Required when rejecting a transfer', 'auchanassettracker'))
                . "'></textarea></div>";
            echo Html::submit(__('Reject transfer', 'auchanassettracker'), [
                'name'  => 'reject_transfer',
                'class' => 'btn btn-outline-danger',
            ]);
            Html::closeForm();
        }
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
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<div class='mb-3'><label class='form-label'>"
        . __('Destination location', 'auchanassettracker') . $req . "</label>";
    // Own / scoped location excluded inside dropdownDestinationLocation().
    PluginAuchanassettrackerTransfer::dropdownDestinationLocation([
        'name'                => 'locations_id_dest',
        'display_emptychoice' => true,
        'width'               => '100%',
    ]);
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
