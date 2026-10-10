<?php

/**
 * Recipient users for allocation — location-scoped Select2 results.
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

header('Content-Type: application/json; charset=UTF-8');

if (!Session::getLoginUserID()) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

$search = trim((string) ($_GET['term'] ?? $_GET['searchText'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$page_limit = 30;

$results = PluginAuchanassettrackerRighthelper::searchRecipientUsers($search, $page, $page_limit);

echo json_encode([
    'results'    => $results['results'],
    'pagination' => ['more' => $results['more']],
], JSON_UNESCAPED_UNICODE);
