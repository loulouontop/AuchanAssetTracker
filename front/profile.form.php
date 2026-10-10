<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

// CSRF is already validated by GLPI 11 CheckCsrfListener before this
// legacy front script runs.

$profiles_id = (int) ($_POST['profiles_id'] ?? $_POST['id'] ?? 0);

if (isset($_POST['update']) && $profiles_id > 0) {
    PluginAuchanassettrackerProfile::ensureRightsRowsForProfile($profiles_id);

    $role_changed = PluginAuchanassettrackerProfile::roleChangedInPost($_POST);
    $result = PluginAuchanassettrackerProfile::saveFromPost($_POST);

    if ($role_changed) {
        $role = (string) ($_POST['role'] ?? PluginAuchanassettrackerRighthelper::ROLE_USER);
        PluginAuchanassettrackerProfile::applyRightsForRole($profiles_id, $role);
    } else {
        PluginAuchanassettrackerProfile::saveRightsFromPost($_POST);
    }

    if ($result === 'error') {
        Session::addMessageAfterRedirect(
            __('Unable to save role mapping.', 'auchanassettracker'),
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
