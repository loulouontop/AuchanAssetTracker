<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

// CSRF is already validated by GLPI 11 CheckCsrfListener before this
// legacy front script runs.

$profiles_id = (int) ($_POST['profiles_id'] ?? $_POST['id'] ?? 0);

if (isset($_POST['update']) && $profiles_id > 0) {
    PluginAuchanassettrackerProfile::ensureRightsRowsForProfile($profiles_id);
    PluginAuchanassettrackerProfile::saveRightsFromPost($_POST);
    $loc = PluginAuchanassettrackerProfile::saveLocationFromPost($_POST);

    if ($loc === 'error') {
        Session::addMessageAfterRedirect(
            __('Unable to save location scope.', 'auchanassettracker'),
            false,
            ERROR
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Item successfully updated'),
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
