<?php
require dirname(__DIR__) . '/config.php';
$u=require_user();
$pdo=db();

$id=(int)($_GET['occurrence_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $d=json_input();
    $id=(int)($d['occurrence_id']??0);
    $message=trim((string)($d['message']??''));
    if(!$id||$message==='')json_response(['ok'=>false,'error'=>'Mensagem inválida'],422);
    if(mb_strlen($message)>1200)json_response(['ok'=>false,'error'=>'Mensagem muito longa'],422);

    $o=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");$o->execute([$id]);$occ=$o->fetch();
    if(!$occ)json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],404);
    if(!occurrence_access_allowed($u,$occ))json_response(['ok'=>false,'error'=>'Sem acesso a esta ocorrência'],403);

    $now=now_iso();
    $st=$pdo->prepare("INSERT INTO occurrence_messages(occurrence_id,user_id,message,created_at) VALUES(?,?,?,?)");
    $st->execute([$id,$u['id'],$message,$now]);

    $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?,?)");
    $ev->execute([$id,'MENSAGEM',$occ['status'],$occ['status'],$message,$u['id'],$now]);

    json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'created_at'=>$now],201);
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    if(!$id)json_response(['ok'=>false,'error'=>'Ocorrência inválida'],422);
    $o=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");$o->execute([$id]);$occ=$o->fetch();
    if(!$occ)json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],404);
    if(!occurrence_access_allowed($u,$occ))json_response(['ok'=>false,'error'=>'Sem acesso a esta ocorrência'],403);

    $st=$pdo->prepare("
        SELECT m.id,m.message,m.created_at,u.bc_name,u.name,u.role
          FROM occurrence_messages m
          JOIN users u ON u.id=m.user_id
         WHERE m.occurrence_id=?
         ORDER BY m.id DESC LIMIT 100
    ");
    $st->execute([$id]);
    json_response(['ok'=>true,'items'=>array_reverse($st->fetchAll())]);
}

json_response(['ok'=>false,'error'=>'Método não permitido'],405);
