<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canConfirm()) {
    Html::displayRightError();
    exit;
}

$item = new PluginAuchanassettrackerConfirm();
$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['allocation_id'] ?? 0);
    if (isset($_POST['confirm'])) {
        PluginAuchanassettrackerAllocation::confirm($aid);
        Session::addMessageAfterRedirect(__('Receipt confirmed.', 'auchanassettracker'), true, INFO);
    } elseif (isset($_POST['reject'])) {
        $comment = (string) ($_POST['reject_comment'] ?? '');
        if (PluginAuchanassettrackerAllocation::reject($aid, 0, $comment)) {
            Session::addMessageAfterRedirect(
                __('Marked as not received. Item returned to its previous container.', 'auchanassettracker'),
                true,
                WARNING
            );
        }
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

// Do not use check(-1, READ): CommonDBTM requires Session::haveRight bits that
// helpdesk End users may not hold until session refresh; canView()/canConfirm() already ran.
if (!PluginAuchanassettrackerConfirm::canView()) {
    Html::displayRightError();
    exit;
}
$item->showForm(0);

Html::footer();
