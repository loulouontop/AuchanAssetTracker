<?php
/**
 * Shared bootstrap for front controllers.
 */
function plugin_auchanassettracker_front_bootstrap(): void
{
    include_once dirname(__DIR__, 3) . '/inc/includes.php';
    plugin_auchanassettracker_bootstrap();
    Session::checkLoginUser();
}
