<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerConfirm();
$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['allocation_id'] ?? 0);
    if (isset($_POST['confirm'])) {
        PluginAuchanassettrackerAllocation::confirm($aid);
        Session::addMessageAfterRedirect(__('Receipt confirmed.', 'auchanassettracker'), true, INFO);
    } elseif (isset($_POST['reject'])) {
        PluginAuchanassettrackerAllocation::reject($aid);
        Session::addMessageAfterRedirect(
            __('Marked as not received. Item returned to its previous container.', 'auchanassettracker'),
            true,
            WARNING
        );
    }
    Html::redirect($base . '/front/confirm.php');
    exit;
}

Html::header(
    __('Confirm receipt', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_CONFIRM
);

$item->check(-1, READ);
$item->showForm(0);

Html::footer();
