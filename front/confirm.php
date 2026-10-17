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

// Do not use check(-1, READ) / initForm(-1): those require CREATE on ID -1 and
// force central chrome. Helpdesk End users must stay on simplified interface.
if (!PluginAuchanassettrackerConfirm::canView()) {
    Html::displayRightError();
    exit;
}

$title = __('Confirm receipt', 'auchanassettracker');
$is_helpdesk = Session::getCurrentInterface() === 'helpdesk';
if ($is_helpdesk) {
    Html::helpHeader($title, PluginAuchanassettrackerMenu::SECTOR);
} else {
    Html::header(
        $title,
        $_SERVER['PHP_SELF'],
        PluginAuchanassettrackerMenu::SECTOR,
        PluginAuchanassettrackerMenu::MENU_CONFIRM
    );
}

$item->showForm(0);

if ($is_helpdesk) {
    Html::helpFooter();
} else {
    Html::footer();
}
