<?php
/**
 * PNG QR image for a container token (public).
 */

$glpi_root = dirname(__DIR__, 3);
if (!is_readable($glpi_root . '/inc/includes.php')) {
    http_response_code(500);
    exit;
}

define('DO_NOT_CHECK_LOGIN', 1);
include_once $glpi_root . '/inc/includes.php';

if (!function_exists('plugin_auchanassettracker_bootstrap')) {
    require_once dirname(__DIR__) . '/setup.php';
}
plugin_auchanassettracker_bootstrap();

$token = trim((string) ($_GET['t'] ?? ''));
if ($token === '' || PluginAuchanassettrackerContainer::findByToken($token) === null) {
    http_response_code(404);
    exit;
}

$url = PluginAuchanassettrackerQrhelper::getPublicUrl($token);

require_once dirname(__DIR__) . '/inc/phpqrcode/qrlib.php';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
QRcode::png($url, false, QR_ECLEVEL_M, 6, 2);
exit;
