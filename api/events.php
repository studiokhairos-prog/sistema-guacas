<?php
require dirname(__DIR__) . '/config.php';
$u=require_user();
$id=(int)($_GET['occurrence_id']??0);
if(!$id) json_response(['ok'=>false,'error'=>'ID inválido'],422);
$pdo=db();
$occStmt=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");
$occStmt->execute([$id]);
$occ=$occStmt->fetch();
if(!$occ) json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],404);
if(!occurrence_access_allowed($u,$occ)) json_response(['ok'=>false,'error'=>'Sem acesso a esta ocorrência'],403);
$st=$pdo->prepare("SELECT e.*,u.name AS user_name,u.bc_name FROM occurrence_events e LEFT JOIN users u ON u.id=e.user_id WHERE occurrence_id=? ORDER BY id DESC LIMIT 100");
$st->execute([$id]);
json_response(['ok'=>true,'items'=>$st->fetchAll()]);
