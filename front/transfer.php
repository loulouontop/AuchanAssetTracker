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

Html::header(
    __('Transfers', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_TRANSFER
);

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

echo "<div class='aat-workspace'>";
echo "<p><a class='btn btn-primary' href='" . Html::entities_deep($base . '/front/transfer.form.php') . "'>"
    . __('New transfer', 'auchanassettracker') . "</a></p>";

if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    $list = PluginAuchanassettrackerTransfer::getAll();
} else {
    $list = array_merge(
        PluginAuchanassettrackerTransfer::getPendingForLocation((int) $scope),
        array_filter(
            PluginAuchanassettrackerTransfer::getAll(PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT),
            static fn($t) => (int) $t['locations_id_source'] === (int) $scope
                || (int) $t['locations_id_dest'] === (int) $scope
        )
    );
    $uniq = [];
    foreach ($list as $t) {
        $uniq[(int) $t['id']] = $t;
    }
    $list = array_values($uniq);
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
    . __('Status') . "</th><th>" . __('Date') . "</th><th></th></tr></thead><tbody>";

foreach ($list as $t) {
    $st = (string) ($t['transfer_status'] ?? '');
    echo "<tr><td>" . (int) $t['id']
        . "</td><td>" . PluginAuchanassettrackerAllocation::locationNameLink((int) $t['locations_id_source'])
        . "</td><td>" . PluginAuchanassettrackerAllocation::locationNameLink((int) $t['locations_id_dest'])
        . "</td><td>" . Html::entities_deep($status_labels[$st] ?? $st)
        . "</td><td>" . Html::entities_deep($t['date_initiated'] ?? '')
        . "</td><td><a class='btn btn-sm btn-secondary' href='"
        . Html::entities_deep($base . '/front/transfer.form.php?id=' . (int) $t['id']) . "'>"
        . __('Open') . "</a></td></tr>";
}
if ($list === []) {
    echo "<tr><td colspan='6' class='text-muted'>" . __('None.', 'auchanassettracker') . "</td></tr>";
}
echo "</tbody></table></div>";

PluginAuchanassettrackerMenu::endNativeFormCard();
echo "</div>";

Html::footer();
