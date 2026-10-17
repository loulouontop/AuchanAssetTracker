<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

// CSRF is already validated by GLPI 11 CheckCsrfListener before this
// legacy front script runs.

$profiles_id = (int) ($_POST['profiles_id'] ?? $_POST['id'] ?? 0);

if (isset($_POST['update']) && $profiles_id > 0) {
    PluginAuchanassettrackerProfile::ensureRightsRowsForProfile($profiles_id);

    // Save the editable GLPI-style rights matrix only (no Role field on the tab).
    $saved = PluginAuchanassettrackerProfile::saveRightsFromPost($_POST);

    if ($saved) {
        Session::addMessageAfterRedirect(
            __('Item successfully updated'),
            true,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            __('No changes to save.', 'auchanassettracker'),
            true,
            INFO
        );
    }

    global $CFG_GLPI;
    Html::redirect(
        $CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id
        . '&forcetab=PluginAuchanassettrackerProfile$1'
    );
}

Html::back();
