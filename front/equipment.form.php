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
    $id = (int) ($_POST['id'] ?? 0);
    $item->check($id, UPDATE);
    if ($item->getFromDB($id)) {
        $doc_label = '';
        // Prefer first uploaded filename for the summary field.
        if (!empty($_POST['_filename'][0])) {
            $doc_label = (string) $_POST['_filename'][0];
            // Strip upload prefix if present.
            if (!empty($_POST['_prefix_filename'][0])) {
                $doc_label = str_replace((string) $_POST['_prefix_filename'][0], '', $doc_label);
            }
        }
        $ok = $item->markFinal(
            (string) ($_POST['final_status'] ?? ''),
            (string) ($_POST['final_reason'] ?? ''),
            $doc_label
        );
        if ($ok) {
            try {
                $names = $item->attachUploadedDocuments($_POST);
                if ($names !== []) {
                    $item->update([
                        'id'             => $id,
                        'final_document' => implode(', ', array_slice($names, 0, 3)),
                    ]);
                }
            } catch (Throwable $e) {
                PluginAuchanassettrackerPluginlog::exception($e, 'mark_final_attach_documents');
            }
        }
    }
    // Stay on this equipment form (avoid Html::back() landing on legacy fronts).
    Html::redirect(
        $item->getFormURL() . '?id=' . $id
        . '&forcetab=PluginAuchanassettrackerEquipment$1'
    );
} elseif (isset($_POST['reintroduce'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $item->check($id, UPDATE);
    if ($item->getFromDB($id)) {
        $item->reintroduceToStock((int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0));
    }
    Html::redirect(
        $item->getFormURL() . '?id=' . $id
        . '&forcetab=PluginAuchanassettrackerEquipment$1'
    );
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    $item->redirectToList();
} elseif (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST, 0);
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $item->check($id, READ);
} else {
    $item->check(-1, CREATE);
}

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

$item->display(['id' => $id]);

Html::footer();
