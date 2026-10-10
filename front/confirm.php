<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Confirm receipt', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$uid = (int) Session::getLoginUserID();
$base = Plugin::getWebDir(plugin_auchanassettracker_dir());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['allocation_id'] ?? 0);
    if (isset($_POST['confirm'])) {
        PluginAuchanassettrackerAllocation::confirm($aid);
        Session::addMessageAfterRedirect(__('Receipt confirmed.', 'auchanassettracker'), true, INFO);
    } elseif (isset($_POST['reject'])) {
        PluginAuchanassettrackerAllocation::reject($aid);
        Session::addMessageAfterRedirect(
            __('Marked as not received. The manager will put it back in a container.', 'auchanassettracker'),
            true,
            WARNING
        );
    }
    Html::redirect($base . '/front/confirm.php');
    exit;
}

$pending = PluginAuchanassettrackerAllocation::getPendingForUser($uid);

echo "<h1>" . __('Equipment awaiting your confirmation', 'auchanassettracker') . "</h1>";

if ($pending === []) {
    echo "<p>" . __('Nothing to confirm.', 'auchanassettracker') . "</p>";
} else {
    echo "<table class='tab_cadre_fixe'><tr><th>" . __('Equipment') . "</th><th>"
        . __('Serial number') . "</th><th>" . __('Allocated on', 'auchanassettracker')
        . "</th><th>" . __('Actions') . "</th></tr>";
    foreach ($pending as $a) {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB((int) $a['plugin_auchanassettracker_equipments_id']);
        echo "<tr class='tab_bg_1'><td>" . Html::entities_deep($eq->fields['name'] ?? '')
            . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '')
            . "</td><td>" . Html::entities_deep($a['allocation_date'] ?? '')
            . "</td><td><form method='post' action='' style='display:inline'>";
        echo Html::hidden('allocation_id', ['value' => (int) $a['id']]);
        echo Html::submit(__('Confirm receipt', 'auchanassettracker'), ['name' => 'confirm', 'class' => 'btn btn-success btn-sm']);
        echo " ";
        echo Html::submit(__('Did not receive', 'auchanassettracker'), ['name' => 'reject', 'class' => 'btn btn-danger btn-sm']);
        Html::closeForm();
        echo "</td></tr>";
    }
    echo "</table>";
}

Html::footer();
