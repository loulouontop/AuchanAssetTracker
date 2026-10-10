<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerContainer();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newID = $item->add($_POST)) {
        Html::redirect($item->getFormURL() . '?id=' . $newID);
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['delete']) || isset($_POST['purge'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
$item->display(['id' => $id]);
