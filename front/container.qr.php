<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$id = (int) ($_GET['id'] ?? 0);
$c = new PluginAuchanassettrackerContainer();
if ($id <= 0 || !$c->getFromDB($id) || !$c->canViewItem()) {
    Html::displayRightError();
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
echo PluginAuchanassettrackerQrhelper::renderLabelHtml($c->fields);
exit;
