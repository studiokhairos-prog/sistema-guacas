<?php
require __DIR__ . '/config.php';
$u=require_user();
$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT s.*,a.occurrence_id FROM aph_signatures s JOIN aph_records a ON a.id=s.aph_id WHERE s.id=?");
$st->execute([$id]);
$s=$st->fetch();
if(!$s){http_response_code(404);exit;}
$r=load_aph((int)$s['aph_id']);
if(!$r || !aph_can_access($u,$r)){http_response_code(403);exit;}
$file=__DIR__ . '/' . ltrim((string)$s['signature_path'],'/');
$real=realpath($file);
$base=realpath(SIGNATURE_DIR);
if(!$real || !$base || !str_starts_with($real,$base) || !is_file($real)){http_response_code(404);exit;}
header('Content-Type: image/png');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($real);
