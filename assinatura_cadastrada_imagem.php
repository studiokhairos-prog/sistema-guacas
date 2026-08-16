<?php
require __DIR__ . '/config.php';$viewer=require_user();$id=(int)($_GET['id']??0);
if($id!==(int)$viewer['id']&&!is_admin_general($viewer)){http_response_code(403);exit;}
$st=db()->prepare("SELECT registered_signature_path FROM users WHERE id=? AND deleted_at IS NULL");$st->execute([$id]);$path=$st->fetchColumn();$full=registered_signature_absolute_path(is_string($path)?$path:null);if(!$full){http_response_code(404);exit;}
header('Content-Type: image/png');header('Cache-Control: private,no-store');header('X-Content-Type-Options: nosniff');readfile($full);