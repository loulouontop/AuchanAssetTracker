<?php
/**
 * Shared bootstrap for front controllers.
 */
function plugin_auchanassettracker_front_bootstrap(): void
{
    // GLPI 11 loads plugin fronts via LegacyFileLoadController — core is
    // already bootstrapped. Re-including includes.php can break the session.
    if (!defined('GLPI_ROOT')) {
        include_once dirname(__DIR__, 3) . '/inc/includes.php';
    }

    plugin_auchanassettracker_bootstrap();
    if (function_exists('plugin_auchanassettracker_load_translations')) {
        plugin_auchanassettracker_load_translations();
    }
    Session::checkLoginUser();
}
