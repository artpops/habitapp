<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'collectibles' => list_collectibles($user['id']),
        'available' => scan_award_files(),
    ]);
}

json_response(['error' => 'Unsupported method'], 405);
?>
