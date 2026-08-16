<?php
require dirname(__DIR__) . '/config.php';
$u=require_user(['CAMPO','ADMIN']);
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['ok'=>false,'error'=>'Método não permitido'],405);
require_csrf();
if(trim((string)($u['team']??''))==='') json_response(['ok'=>false,'error'=>'Seu cadastro não está vinculado a uma equipe ativa'],422);
$d=json_input();$id=(int)($d['id']??0);if(!$id)json_response(['ok'=>false,'error'=>'Ocorrência inválida'],422);
$pdo=db();$st=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");$st->execute([$id]);$o=$st->fetch();if(!$o)json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],404);
if(!empty($o['team'])){
    if($o['team']===$u['team']) json_response(['ok'=>true,'already'=>true,'team'=>$u['team']]);
    json_response(['ok'=>false,'error'=>'Esta ocorrência já foi assumida por outra equipe'],409);
}
$pdo->beginTransaction();
try{
    $now=now_iso();
    $up=$pdo->prepare("UPDATE occurrences SET team=?,status='DESPACHADA',assigned_at=COALESCE(assigned_at,?),dispatched_at=COALESCE(dispatched_at,?),updated_at=? WHERE id=? AND (team IS NULL OR team='')");
    $up->execute([$u['team'],$now,$now,$now,$id]);
    if($up->rowCount()!==1) throw new RuntimeException('A ocorrência foi assumida por outra equipe.');
    $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?,?)");
    $ev->execute([$id,'ASSUMIDA',$o['status'],'DESPACHADA','Ocorrência assumida pela equipe '.$u['team'],$u['id'],$now]);
    $pdo->commit();json_response(['ok'=>true,'team'=>$u['team'],'status'=>'DESPACHADA']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['ok'=>false,'error'=>$e->getMessage()],409);}
