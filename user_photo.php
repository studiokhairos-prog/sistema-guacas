<?php
require __DIR__ . '/config.php';
$viewer = require_user();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

if ($id !== (int)$viewer['id'] && !is_admin_general($viewer)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$st = $pdo->prepare("SELECT photo_path FROM users WHERE id=? AND deleted_at IS NULL");
$st->execute([$id]);
$photo = $st->fetchColumn();
$full = user_photo_absolute_path(is_string($photo) ? $photo : null);
if (!$full) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . user_photo_mime((string)$photo));
header('Content-Length: ' . filesize($full));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($full);
