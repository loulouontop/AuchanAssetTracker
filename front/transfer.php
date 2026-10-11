<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canTransfer()) {
    Html::header(
        __('Transfers', 'auchanassettracker'),
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_TRANSFER
    );
    Html::displayRightError();
    exit;
}

$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transfer'])) {
    $tid = (int) ($_POST['id'] ?? 0);
    if ($tid > 0 && PluginAuchanassettrackerTransfer::cancelBySender($tid)) {
        Session::addMessageAfterRedirect(
            __('Transfer cancelled. Equipment restored at source.', 'auchanassettracker'),
            true,
            INFO
        );
    }
    Html::redirect($base . '/front/transfer.php');
    exit;
}

Html::header(
    __('Transfers', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_TRANSFER
);

$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

echo "<div class='aat-workspace'>";

// Location managers see full history (all statuses) for their location — not only in-transit.
if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    $list = PluginAuchanassettrackerTransfer::getAll();
} else {
    $list = PluginAuchanassettrackerTransfer::getForLocationScope($scope);
}

$status_labels = [
    PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT => __('In transit', 'auchanassettracker'),
    PluginAuchanassettrackerTransfer::STATUS_COMPLETED  => __('Completed'),
    PluginAuchanassettrackerTransfer::STATUS_CANCELLED  => __('Cancelled'),
];

PluginAuchanassettrackerMenu::beginNativeFormCard(
    __('Transfers', 'auchanassettracker'),
    PluginAuchanassettrackerTransfer::getIcon()
);

echo "<div class='table-responsive'><table class='table table-sm table-hover card-table'>";
echo "<thead><tr><th>" . __('ID') . "</th><th>"
    . __('Source') . "</th><th>" . __('Destination') . "</th><th>"
    . __('Status') . "</th><th>" . __('Date') . "</th><th>"
    . __('Actions') . "</th></tr></thead><tbody>";

foreach ($list as $t) {
    $tid = (int) ($t['id'] ?? 0);
    $st = (string) ($t['transfer_status'] ?? '');
    $tr = new PluginAuchanassettrackerTransfer();
    $tr->fields = $t;
    $is_in_transit = $st === PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT;
    $can_sender = $is_in_transit && PluginAuchanassettrackerTransfer::isSenderForTransfer($tr);

    echo "<tr><td>" . $tid
        . "</td><td>" . PluginAuchanassettrackerAllocation::locationNameLink((int) $t['locations_id_source'])
        . "</td><td>" . PluginAuchanassettrackerAllocation::locationNameLink((int) $t['locations_id_dest'])
        . "</td><td>" . Html::entities_deep($status_labels[$st] ?? $st)
        . "</td><td>" . Html::entities_deep($t['date_initiated'] ?? '')
        . "</td><td class='aat-transfer-actions text-nowrap'>";

    // GLPI-style open / edit / delete (edit+delete only for sender while in transit).
    echo "<a class='btn btn-sm btn-icon btn-ghost-secondary' title='"
        . Html::entities_deep(__('Open')) . "' href='"
        . Html::entities_deep($base . '/front/transfer.form.php?id=' . $tid) . "'>"
        . "<i class='ti ti-eye'></i></a>";

    if ($can_sender) {
        echo " <a class='btn btn-sm btn-icon btn-ghost-secondary' title='"
            . Html::entities_deep(_x('button', 'Edit')) . "' href='"
            . Html::entities_deep($base . '/front/transfer.form.php?id=' . $tid . '&edit=1') . "'>"
            . "<i class='ti ti-edit'></i></a>";

        $confirm_js = json_encode(
            __('Cancel this transfer and restore equipment at source?', 'auchanassettracker'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        echo " <form method='post' action='' class='d-inline' onsubmit='return confirm({$confirm_js});'>";
        echo Html::hidden('id', ['value' => $tid]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<button type='submit' name='delete_transfer' value='1'"
            . " class='btn btn-sm btn-icon btn-ghost-danger' title='"
            . Html::entities_deep(_x('button', 'Delete')) . "'>"
            . "<i class='ti ti-trash'></i></button>";
        Html::closeForm();
    }

    echo "</td></tr>";
}
if ($list === []) {
    echo "<tr><td colspan='6' class='text-muted'>" . __('None.', 'auchanassettracker') . "</td></tr>";
}
echo "</tbody></table></div>";

PluginAuchanassettrackerMenu::endNativeFormCard();
echo "</div>";

Html::footer();
