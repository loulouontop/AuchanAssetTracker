<?php

/**
 * Render the allocation email template as final HTML UI (config preview).
 */
include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canConfigure()) {
    Html::displayRightError();
    exit;
}

// Full HTML document for iframe / new-tab preview (not GLPI chrome).
header('Content-Type: text/html; charset=UTF-8');
echo PluginAuchanassettrackerMailhelper::renderPreviewHtml();
exit;
