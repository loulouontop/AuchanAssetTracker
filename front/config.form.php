<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerConfig();

if (!PluginAuchanassettrackerRighthelper::canConfigure()) {
    Html::displayRightError();
    exit;
}

$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_thresholds'])) {
    PluginAuchanassettrackerConfig::saveThresholds(
        (int) ($_POST['allocation_confirm_days'] ?? 5),
        (int) ($_POST['transfer_validate_days'] ?? 7),
        (int) ($_POST['service_max_days'] ?? 30)
    );
    Session::addMessageAfterRedirect(__('Thresholds saved.', 'auchanassettracker'), true, INFO);
    Html::redirect($base . '/front/config.form.php');
    exit;
}

Html::header(
    __('Auchan Asset Tracker - configuration', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_CONFIG
);

$item->check(-1, READ);
$item->showForm(0);

Html::footer();
