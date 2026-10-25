<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$role = PluginAuchanassettrackerRighthelper::getCurrentRole();
$base = plugin_auchanassettracker_web_dir();
$title = __('Dashboard', 'auchanassettracker');
$is_helpdesk = Session::getCurrentInterface() === 'helpdesk';

if ($is_helpdesk) {
    Html::helpHeader($title, PluginAuchanassettrackerMenu::SECTOR);
} else {
    Html::header(
        $title,
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_DASHBOARD
    );
}

echo "<div class='aat-dashboard-page assettracker-dashboard'>";
PluginAuchanassettrackerMenu::beginNativeFormCard($title, 'ti ti-dashboard');

if ($role === PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN) {
    $data = PluginAuchanassettrackerDashboard::getAdminData();
    $ind = $data['indicators'];

    echo "<div class='aat-indicators'>";
    $cards = [
        [
            'total',
            __('Total equipment', 'auchanassettracker'),
            $ind['total'],
            $base . '/front/equipment.php',
        ],
        [
            'available',
            __('Available', 'auchanassettracker'),
            $ind['available'],
            $base . '/front/equipment.php?criteria[0][field]=4&criteria[0][searchtype]=equals'
                . '&criteria[0][value]=' . urlencode(PluginAuchanassettrackerEquipment::STATUS_AVAILABLE),
        ],
        [
            'allocated',
            __('Allocated', 'auchanassettracker'),
            $ind['allocated'],
            $base . '/front/equipment.php?criteria[0][field]=4&criteria[0][searchtype]=equals'
                . '&criteria[0][value]=' . urlencode(PluginAuchanassettrackerEquipment::STATUS_ALLOCATED),
        ],
        [
            'in_service',
            __('In service', 'auchanassettracker'),
            $ind['in_service'],
            $base . '/front/equipment.php?criteria[0][field]=4&criteria[0][searchtype]=equals'
                . '&criteria[0][value]=' . urlencode(PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE),
        ],
        [
            'in_transit',
            __('In transit', 'auchanassettracker'),
            $ind['in_transit'],
            $base . '/front/equipment.php?criteria[0][field]=4&criteria[0][searchtype]=equals'
                . '&criteria[0][value]=' . urlencode(PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT),
        ],
        [
            'written_off',
            __('Written off (30 days)', 'auchanassettracker'),
            $ind['written_off_30'],
            $base . '/front/equipment.php',
        ],
    ];
    foreach ($cards as [$mod, $label, $val, $href]) {
        echo "<a class='aat-card aat-card--" . Html::entities_deep($mod) . "' href='"
            . Html::entities_deep($href) . "'>"
            . "<div class='aat-card-value'>" . (int) $val . '</div>'
            . "<div class='aat-card-label'>" . Html::entities_deep($label) . '</div></a>';
    }
    echo '</div>';

    echo "<div class='aat-alerts aat-alert-section-block'>";
    echo '<h2 class="aat-alert-heading">' . __('Active alerts', 'auchanassettracker') . '</h2>';
    foreach ($data['alerts']['service'] as $r) {
        $eq_id = (int) ($r['id'] ?? 0);
        $link = $eq_id > 0 ? $base . '/front/equipment.form.php?id=' . $eq_id : '#';
        echo "<div class='aat-alert'><a href='" . Html::entities_deep($link) . "'>" . sprintf(
            __('In service overtime: %s (%s) — %d days', 'auchanassettracker'),
            Html::entities_deep($r['name'] ?? ''),
            Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) ($r['locations_id'] ?? 0))),
            PluginAuchanassettrackerDashboard::daysSince($r['service_since'] ?? null)
        ) . '</a></div>';
    }
    foreach ($data['alerts']['allocations'] as $r) {
        $user = trim((string) ($r['recipient_name'] ?? ''));
        if ($user === '') {
            $user = trim(strip_tags((string) getUserName((int) ($r['users_id_recipient'] ?? 0))));
        }
        echo "<div class='aat-alert'>" . sprintf(
            __('Unconfirmed allocation: %s — %s — %d days', 'auchanassettracker'),
            Html::entities_deep($r['equipment_name'] ?? ''),
            Html::entities_deep($user),
            PluginAuchanassettrackerDashboard::daysSince($r['allocation_date'] ?? null)
        ) . '</div>';
    }
    foreach ($data['alerts']['transfers'] as $r) {
        $tid = (int) ($r['id'] ?? 0);
        echo "<div class='aat-alert'><a href='"
            . Html::entities_deep($base . '/front/transfer.form.php?id=' . $tid) . "'>" . sprintf(
                __('Unvalidated transfer #%d to %s — %d days', 'auchanassettracker'),
                $tid,
                Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) ($r['locations_id_dest'] ?? 0))),
                PluginAuchanassettrackerDashboard::daysSince($r['date_initiated'] ?? null)
            ) . '</a></div>';
    }
    if ($data['alerts']['service'] === []
        && $data['alerts']['allocations'] === []
        && $data['alerts']['transfers'] === []
    ) {
        echo '<p>' . __('No active alerts.', 'auchanassettracker') . '</p>';
    }
    echo '</div>';

    // Native GLPI Search: filter / sort / saved searches / rows per page / pagination.
    echo '<h2 class="mt-4">' . __('Stock by location', 'auchanassettracker') . '</h2>';
    echo "<div class='aat-dashboard-search'>";
    PluginAuchanassettrackerStockbylocation::rebuild();
    PluginAuchanassettrackerStockbylocation::ensureDisplayPreferences();
    Search::showList(PluginAuchanassettrackerStockbylocation::class, [
        'showmassiveactions' => false,
        'sort'               => 2,
        'order'              => 'ASC',
    ]);
    echo '</div>';

    echo '<h2 class="mt-4">' . __('Recent activity', 'auchanassettracker') . '</h2>';
    echo "<div class='aat-dashboard-search'>";
    PluginAuchanassettrackerAuditlog::ensureDisplayPreferences();
    Search::showList(PluginAuchanassettrackerAuditlog::class, [
        'showmassiveactions' => false,
        'sort'               => 2,
        'order'              => 'DESC',
    ]);
    echo '</div>';

} elseif (in_array($role, [
    PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER,
    PluginAuchanassettrackerRighthelper::ROLE_SUPPORT_TECH,
], true)) {
    $loc = (int) (PluginAuchanassettrackerRighthelper::getScopedLocationId() ?? 0);
    $data = PluginAuchanassettrackerDashboard::getManagerData($loc);

    echo "<div class='aat-manager-grid'>";
    echo "<div class='aat-col'><h2>" . __('Technical room stock', 'auchanassettracker') . '</h2>';
    echo "<div class='table-responsive'><table class='table table-hover table-striped'><thead><tr>"
        . '<th>' . __('Container', 'auchanassettracker') . '</th>'
        . '<th>' . __('Available', 'auchanassettracker') . '</th>'
        . '</tr></thead><tbody>';
    foreach ($data['containers'] as $c) {
        echo "<tr><td><a href='" . Html::entities_deep($base . '/front/container.form.php?id=' . (int) $c['id']) . "'>"
            . Html::entities_deep(($c['code'] ?? '') . ' — ' . ($c['name'] ?? ''))
            . '</a></td><td>' . (int) ($c['available_count'] ?? 0) . '</td></tr>';
    }
    if ($data['containers'] === []) {
        echo "<tr><td colspan='2'>"
            . __('No containers yet. Create one before receiving equipment.', 'auchanassettracker')
            . '</td></tr>';
    }
    echo '</tbody></table></div></div>';

    echo "<div class='aat-col aat-alerts aat-alert-section-block'>";
    echo '<h2 class="aat-alert-heading">' . __('To resolve today', 'auchanassettracker') . '</h2>';
    foreach ($data['to_resolve']['transfers'] as $t) {
        echo "<div class='aat-alert'><a href='"
            . Html::entities_deep($base . '/front/transfer.form.php?id=' . (int) $t['id']) . "'>"
            . sprintf(__('Transfer #%d awaiting validation', 'auchanassettracker'), (int) $t['id'])
            . '</a></div>';
    }
    foreach ($data['to_resolve']['allocations'] as $a) {
        $user = trim((string) ($a['recipient_name'] ?? ''));
        if ($user === '') {
            $user = trim(strip_tags((string) getUserName((int) ($a['users_id_recipient'] ?? 0))));
        }
        echo "<div class='aat-alert'>" . sprintf(
            __('Unconfirmed: %s — %s (%d days)', 'auchanassettracker'),
            Html::entities_deep($a['equipment_name'] ?? ''),
            Html::entities_deep($user),
            PluginAuchanassettrackerDashboard::daysSince($a['allocation_date'] ?? null)
        ) . '</div>';
    }
    foreach ($data['to_resolve']['needs_container'] as $e) {
        echo "<div class='aat-alert'><a href='"
            . Html::entities_deep($base . '/front/equipment.form.php?id=' . (int) $e['id']) . "'>"
            . sprintf(__('Needs container: %s', 'auchanassettracker'), Html::entities_deep($e['name'] ?? ''))
            . '</a></div>';
    }
    if ($data['to_resolve']['transfers'] === []
        && $data['to_resolve']['allocations'] === []
        && $data['to_resolve']['needs_container'] === []
    ) {
        echo '<p>' . __('No active alerts.', 'auchanassettracker') . '</p>';
    }

    echo '<h2 class="mt-3 aat-movement-heading">' . __('In movement', 'auchanassettracker') . '</h2>';
    echo "<div class='aat-in-movement'>";
    foreach ($data['in_movement']['in_service'] as $e) {
        $since = PluginAuchanassettrackerDashboard::formatSinceDate($e['since'] ?? null);
        $days = PluginAuchanassettrackerDashboard::daysSince($e['since'] ?? null);
        $eq_id = (int) ($e['id'] ?? 0);
        $href = $eq_id > 0 ? $base . '/front/equipment.form.php?id=' . $eq_id : '#';
        echo "<div class='aat-movement-row'><a href='" . Html::entities_deep($href) . "'>"
            . Html::entities_deep($e['name'] ?? '') . '</a>'
            . ' — ' . __('In service', 'auchanassettracker');
        if ($since !== '') {
            echo ' — ' . sprintf(__('since %s (%dd)', 'auchanassettracker'), Html::entities_deep($since), $days);
        }
        echo ' — ' . Html::entities_deep((string) ($e['service_label'] ?? __('Service', 'auchanassettracker')));
        echo '</div>';
    }
    foreach ($data['in_movement']['in_transit_out'] as $e) {
        $since = PluginAuchanassettrackerDashboard::formatSinceDate($e['since'] ?? null);
        $days = PluginAuchanassettrackerDashboard::daysSince($e['since'] ?? null);
        $eq_id = (int) ($e['id'] ?? 0);
        $href = $eq_id > 0 ? $base . '/front/equipment.form.php?id=' . $eq_id : '#';
        $dest = trim(strip_tags((string) ($e['destination_label'] ?? '')));
        echo "<div class='aat-movement-row'><a href='" . Html::entities_deep($href) . "'>"
            . Html::entities_deep($e['name'] ?? '') . '</a>'
            . ' — ' . __('In transit', 'auchanassettracker');
        if ($since !== '') {
            echo ' — ' . sprintf(__('since %s (%dd)', 'auchanassettracker'), Html::entities_deep($since), $days);
        }
        echo ' — ' . __('Destination', 'auchanassettracker') . ': ' . Html::entities_deep($dest);
        echo '</div>';
    }
    if ($data['in_movement']['in_service'] === [] && $data['in_movement']['in_transit_out'] === []) {
        echo '<p class="text-muted">' . __('No data.', 'auchanassettracker') . '</p>';
    }
    echo '</div></div></div>';

} else {
    $data = PluginAuchanassettrackerDashboard::getUserData((int) Session::getLoginUserID());

    if ($data['pending'] !== []) {
        echo "<div class='aat-pending mb-3'>";
        echo "<div class='aat-pending-row'>";
        echo "<div class='aat-pending-main'>";
        echo '<h2>' . __('Pending actions', 'auchanassettracker') . '</h2>';
        echo '<p class="mb-2">' . sprintf(
            __('You have %d equipment awaiting confirmation.', 'auchanassettracker'),
            count($data['pending'])
        ) . '</p>';
        echo "<ul class='aat-pending-equipment mb-0'>";
        foreach ($data['pending'] as $p) {
            $ename = trim((string) ($p['equipment_name'] ?? ''));
            if ($ename === '') {
                $ename = '#' . (int) ($p['plugin_auchanassettracker_equipments_id'] ?? 0);
            }
            echo '<li>' . Html::entities_deep($ename) . '</li>';
        }
        echo '</ul></div>';
        echo "<div class='aat-pending-actions'>"
            . "<a class='btn btn-primary' href='" . Html::entities_deep($base . '/front/confirm.php') . "'>"
            . __('Confirm receipt', 'auchanassettracker') . '</a></div>';
        echo '</div></div>';
    }

    echo '<h2>' . __('My equipment', 'auchanassettracker') . '</h2>';
    echo "<div class='table-responsive'><table class='table table-hover table-striped'><thead><tr>"
        . '<th>' . __('Type') . '</th>'
        . '<th>' . __('Model') . '</th>'
        . '<th>' . __('Serial number') . '</th>'
        . '<th>' . __('Status') . '</th>'
        . '</tr></thead><tbody>';
    $shown = 0;
    foreach ($data['my_equipment'] as $e) {
        if (($e['status'] ?? '') === PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION) {
            continue;
        }
        $shown++;
        $type_label = '';
        $type_id = (int) ($e['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
        if ($type_id > 0) {
            $type_label = Dropdown::getDropdownName(
                PluginAuchanassettrackerEquipmenttype::getTable(),
                $type_id
            );
        } elseif (!empty($e['itemtype'])) {
            $type_label = (string) $e['itemtype'];
        }
        $status = (string) ($e['status'] ?? PluginAuchanassettrackerEquipment::STATUS_ALLOCATED);
        $status_label = PluginAuchanassettrackerEquipment::getStatusLabel($status);
        echo '<tr><td>' . Html::entities_deep($type_label)
            . '</td><td>' . Html::entities_deep((string) ($e['model'] ?? ''))
            . '</td><td>' . Html::entities_deep((string) ($e['serial'] ?? ''))
            . '</td><td>' . Html::entities_deep($status_label) . '</td></tr>';
    }
    if ($shown === 0) {
        echo "<tr><td colspan='4'>" . __('No data.', 'auchanassettracker') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

PluginAuchanassettrackerMenu::endNativeFormCard();
echo '</div>';

if ($is_helpdesk) {
    Html::helpFooter();
} else {
    Html::footer();
}
