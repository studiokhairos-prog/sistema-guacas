<?php
require dirname(__DIR__) . '/config.php';
$u=require_user(['ADMIN','BASE','STAFF']);
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['ok'=>false,'error'=>'Método não permitido'],405);
require_csrf();

$d=json_input();
$id=(int)($d['id']??0);
$action=(string)($d['action']??'');
$note=trim((string)($d['note']??''));
if(!$id) json_response(['ok'=>false,'error'=>'Ocorrência inválida'],422);

$pdo=db();
$st=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");$st->execute([$id]);$occ=$st->fetch();
if(!$occ) json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],404);

try{
    $pdo->beginTransaction();
    $now=now_iso();

    if($action==='dispatch'){
        $team=trim((string)($d['team']??''));
        $vehicleId=(int)($d['vehicle_id']??0);
        $priority=(string)($d['priority']??$occ['priority']);
        $level=upper_text($d['occurrence_level']??($occ['occurrence_level']??'NAO_CLASSIFICADO'));if(!in_array($level,occurrence_level_options(),true))$level=$occ['occurrence_level']??'NAO_CLASSIFICADO';

        if($team==='') throw new RuntimeException('Escolha uma equipe para o despacho.');
        $q=$pdo->prepare("SELECT id FROM teams WHERE name=? AND active=1");$q->execute([$team]);
        if(!$q->fetchColumn()) throw new RuntimeException('Equipe inválida ou inativa.');

        if(!in_array($priority,['BAIXA','MEDIA','ALTA','CRITICA'],true)) $priority=$occ['priority'];

        if($vehicleId){
            $v=$pdo->prepare("SELECT * FROM vehicles WHERE id=? AND active=1");$v->execute([$vehicleId]);$vehicle=$v->fetch();
            if(!$vehicle) throw new RuntimeException('Viatura inválida.');
            if($vehicle['status']==='MANUTENCAO'||$vehicle['status']==='INATIVA') throw new RuntimeException('Viatura indisponível para despacho.');
        }

        $oldVehicle=(int)($occ['vehicle_id']??0);
        $up=$pdo->prepare("UPDATE occurrences SET team=?,vehicle_id=?,priority=?,occurrence_level=?,status='DESPACHADA',assigned_at=COALESCE(assigned_at,?),dispatched_at=COALESCE(dispatched_at,?),updated_at=? WHERE id=?");
        $up->execute([$team,$vehicleId?:null,$priority,$level,$now,$now,$now,$id]);

        if($oldVehicle && $oldVehicle!==$vehicleId) release_vehicle_if_unused($pdo,$oldVehicle,$id);
        if($vehicleId) set_vehicle_status_if_available($pdo,$vehicleId,'EM_USO');

        $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?,?)");
        $ev->execute([$id,'DESPACHO',$occ['status'],'DESPACHADA','DESPACHO PARA '.$team.' · '.occurrence_level_label($level).($note!==''?' — '.$note:''),$u['id'],$now]);

    }elseif($action==='status'){
        $status=(string)($d['status']??'');
        $allowed=['ABERTA','DESPACHADA','A_CAMINHO','NO_LOCAL','EM_ATENDIMENTO','RETORNANDO','ENCERRADA'];
        if(!in_array($status,$allowed,true)) throw new RuntimeException('Status inválido.');

        $col=status_timestamp_column($status);
        $sql="UPDATE occurrences SET status=?,updated_at=?";
        $args=[$status,$now];
        if($col){$sql.=", $col=COALESCE($col,?)";$args[]=$now;}
        $sql.=" WHERE id=?";$args[]=$id;
        $pdo->prepare($sql)->execute($args);

        $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?,?)");
        $ev->execute([$id,'STATUS',$occ['status'],$status,$note?:null,$u['id'],$now]);

        if($status==='ENCERRADA') release_vehicle_if_unused($pdo,(int)($occ['vehicle_id']??0),$id);

    }elseif($action==='priority'){
        $priority=(string)($d['priority']??'');
        if(!in_array($priority,['BAIXA','MEDIA','ALTA','CRITICA'],true)) throw new RuntimeException('Prioridade inválida.');
        $pdo->prepare("UPDATE occurrences SET priority=?,updated_at=? WHERE id=?")->execute([$priority,$now,$id]);
        $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?,?)");
        $ev->execute([$id,'PRIORIDADE',$occ['status'],$occ['status'],'Prioridade alterada para '.$priority.($note?' — '.$note:''),$u['id'],$now]);
    }else{
        throw new RuntimeException('Ação inválida.');
    }

    $pdo->commit();
    json_response(['ok'=>true,'server_time'=>$now]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    json_response(['ok'=>false,'error'=>$e->getMessage()],422);
}
