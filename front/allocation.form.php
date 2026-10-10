<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('New allocation', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$base = Plugin::getWebDir(plugin_auchanassettracker_dir());
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $uid = (int) ($_POST['users_id'] ?? 0);
    $eids = array_map('intval', (array) ($_POST['equipment_ids'] ?? []));
    $ok = 0;
    foreach ($eids as $eid) {
        if ($eid > 0 && PluginAuchanassettrackerAllocation::initiate($eid, $uid)) {
            $ok++;
        }
    }
    Session::addMessageAfterRedirect(
        sprintf(__('%d allocation(s) created.', 'auchanassettracker'), $ok),
        true,
        INFO
    );
    Html::redirect($base . '/front/allocation.form.php');
    exit;
}

$preview_user = (int) ($_GET['users_id'] ?? $_POST['users_id'] ?? 0);

echo "<form method='get' action='' class='mb-3'>";
echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>" . __('New allocation', 'auchanassettracker') . "</th></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Recipient user', 'auchanassettracker') . "</td><td>";
User::dropdown(['name' => 'users_id', 'value' => $preview_user, 'right' => 'all']);
echo " " . Html::submit(__('Show current gear', 'auchanassettracker'), ['class' => 'btn btn-secondary']);
echo "</td></tr></table>";
Html::closeForm();

if ($preview_user > 0) {
    $current = PluginAuchanassettrackerAllocation::getCurrentGearForUser($preview_user);
    echo "<h3>" . __('Equipment already with this user', 'auchanassettracker') . "</h3>";
    if ($current === []) {
        echo "<p>" . __('None.', 'auchanassettracker') . "</p>";
    } else {
        echo "<ul>";
        foreach ($current as $e) {
            echo "<li>" . Html::entities_deep(($e['name'] ?? '') . ' [' . ($e['serial'] ?? '') . '] — '
                . PluginAuchanassettrackerEquipment::getStatusLabel((string) $e['status'])) . "</li>";
        }
        echo "</ul>";
    }

    $available = PluginAuchanassettrackerEquipment::findByStatus(
        PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
        $scope
    );

    echo "<form method='post' action=''>";
    echo Html::hidden('users_id', ['value' => $preview_user]);
    echo "<table class='tab_cadre_fixe'><tr><th colspan='4'>"
        . __('Select equipment from stock', 'auchanassettracker') . "</th></tr>";
    echo "<tr><th></th><th>" . __('Name') . "</th><th>" . __('Serial number') . "</th><th>"
        . __('Container', 'auchanassettracker') . "</th></tr>";
    foreach ($available as $e) {
        // Skip items without container (incomplete stock)
        if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
            continue;
        }
        echo "<tr class='tab_bg_1'><td>"
            . "<input type='checkbox' name='equipment_ids[]' value='" . (int) $e['id'] . "'></td><td>"
            . Html::entities_deep($e['name'] ?? '') . "</td><td>"
            . Html::entities_deep($e['serial'] ?? '') . "</td><td>"
            . Html::entities_deep(Dropdown::getDropdownName(
                PluginAuchanassettrackerContainer::getTable(),
                (int) $e['plugin_auchanassettracker_containers_id']
            )) . "</td></tr>";
    }
    echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
    echo Html::submit(__('Allocate', 'auchanassettracker'), ['name' => 'allocate', 'class' => 'btn btn-primary']);
    echo "</td></tr></table>";
    Html::closeForm();
}

Html::footer();
