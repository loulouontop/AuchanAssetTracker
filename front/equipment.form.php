<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerEquipment();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newID = $item->add($_POST)) {
        if ($_SESSION['glpibackcreated'] ?? false) {
            Html::redirect($item->getFormURL() . '?id=' . $newID);
        }
        Html::back();
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['mark_final'])) {
    $item->check((int) $_POST['id'], UPDATE);
    if ($item->getFromDB((int) $_POST['id'])) {
        $item->markFinal(
            (string) ($_POST['final_status'] ?? ''),
            (string) ($_POST['final_reason'] ?? ''),
            (string) ($_POST['final_document'] ?? '')
        );
    }
    Html::back();
} elseif (isset($_POST['reintroduce'])) {
    $item->check((int) $_POST['id'], UPDATE);
    if ($item->getFromDB((int) $_POST['id'])) {
        $item->reintroduceToStock((int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0));
    }
    Html::back();
} elseif (isset($_POST['delete']) || isset($_POST['purge'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
$item->display(['id' => $id]);
