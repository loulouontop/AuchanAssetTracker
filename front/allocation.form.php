<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerAllocation();

if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
    Html::header(
        __('New allocation', 'auchanassettracker'),
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_ALLOCATION
    );
    Html::displayRightError();
    exit;
}

$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_gear'])) {
    $uid = (int) ($_POST['users_id'] ?? 0);
    Html::redirect($base . '/front/allocation.form.php' . ($uid > 0 ? '?users_id=' . $uid : ''));
    exit;
}

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
    Html::redirect($base . '/front/allocation.form.php' . ($uid > 0 ? '?users_id=' . $uid : ''));
    exit;
}

Html::header(
    __('New allocation', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_ALLOCATION
);

// Call showForm directly (not display) so GLPI tabs do not wrap a parent <form>
// that would swallow the gear / allocate forms.
$item->check(-1, CREATE);
$item->showForm(0);

Html::footer();
