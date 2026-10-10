<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

// CSRF is already validated by GLPI 11 CheckCsrfListener before this
// legacy front script runs.

if (isset($_POST['update_aat_location'])
    || isset($_POST['update_aat_profile'])
    || isset($_POST['update'])
) {
    $result = PluginAuchanassettrackerProfile::saveLocationFromPost($_POST);

    if ($result === 'unchanged') {
        Session::addMessageAfterRedirect(
            __('No changes to save.', 'auchanassettracker'),
            true,
            INFO
        );
    } elseif ($result === 'updated' || $result === 'created') {
        Session::addMessageAfterRedirect(
            __('Location scope saved.', 'auchanassettracker'),
            true,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Unable to save location scope.', 'auchanassettracker'),
            false,
            ERROR
        );
    }

    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    if ($profiles_id > 0) {
        global $CFG_GLPI;
        Html::redirect(
            $CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id
            . '&forcetab=PluginAuchanassettrackerProfile$1'
        );
    }
}

Html::back();
