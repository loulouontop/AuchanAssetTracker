<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerContainer();
$in_modal = !empty($_REQUEST['_in_modal']);

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newID = $item->add($_POST)) {
        // Popup add: close modal and refresh parent dropdown (no iframe redirect glitch).
        if ($in_modal || !empty($_POST['_in_modal'])) {
            $new_id_js = (int) $newID;
            Html::popHeader(
                PluginAuchanassettrackerContainer::getTypeName(1),
                $_SERVER['PHP_SELF']
            );
            $js = str_replace(
                '__NEW_ID__',
                (string) $new_id_js,
                <<<'JS'
(function () {
  try {
    var p = window.parent;
    if (p && p !== window) {
      if (typeof p.aatRefreshContainerDropdownAfterAdd === 'function') {
        p.aatRefreshContainerDropdownAfterAdd(__NEW_ID__);
      }
      var $m = p.$('.modal.show');
      if ($m.length && typeof $m.modal === 'function') {
        $m.modal('hide');
      }
    }
  } catch (e) {}
})();
JS
            );
            echo Html::scriptBlock($js);
            Html::popFooter();
            exit;
        }
        Html::redirect($item->getFormURL() . '?id=' . $newID);
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    if ($in_modal || !empty($_POST['_in_modal'])) {
        echo Html::scriptBlock('window.top.location.reload();');
        Html::popFooter();
        exit;
    }
    $item->redirectToList();
} elseif (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST, 0);
    if ($in_modal || !empty($_POST['_in_modal'])) {
        echo Html::scriptBlock('window.top.location.reload();');
        Html::popFooter();
        exit;
    }
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $item->check($id, READ);
} else {
    $item->check(-1, CREATE);
    // Prefill location when opened from Equipment / asset dropdown +.
    if (empty($item->fields['locations_id']) && !empty($_GET['locations_id'])) {
        $item->fields['locations_id'] = (int) $_GET['locations_id'];
    }
}

// Popup/iframe must NOT render full GLPI chrome (sidebar) — form only.
if ($in_modal) {
    Html::popHeader(
        PluginAuchanassettrackerContainer::getTypeName(1),
        $_SERVER['PHP_SELF']
    );
    // Let the bookmark ribbon stick above the header inside the iframe.
    echo '<style>
      html, body { overflow: visible !important; background: #fff !important; }
    </style>';
    echo '<div class="aat-modal-shell">';
    $item->showForm($id, ['in_modal' => true]);
    echo '</div>';
    Html::popFooter();
    exit;
}

Html::header(
    PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_CONTAINER
);

$item->display(['id' => $id]);

Html::footer();
