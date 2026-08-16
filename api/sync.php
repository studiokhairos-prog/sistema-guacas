<?php
require dirname(__DIR__) . '/config.php';
$u = require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido'],405);
require_csrf();

$d = json_input();
$ops = $d['operations'] ?? [];
if (!is_array($ops)) json_response(['ok'=>false,'error'=>'Formato inválido'],422);

$pdo = db();
$results = [];
$allowedStatus = ['ABERTA','DESPACHADA','A_CAMINHO','NO_LOCAL','EM_ATENDIMENTO','RETORNANDO','ENCERRADA'];

foreach ($ops as $op) {
    $uuid = trim((string)($op['uuid'] ?? ''));
    $occId = (int)($op['occurrence_id'] ?? 0);
    $status = (string)($op['status'] ?? '');
    $note = trim((string)($op['note'] ?? ''));
    $device = trim((string)($op['device_id'] ?? ''));
    if (!$uuid || !$occId || !in_array($status,$allowedStatus,true)) { $results[]=['uuid'=>$uuid,'ok'=>false,'error'=>'Operação inválida']; continue; }

    $dup=$pdo->prepare("SELECT id FROM occurrence_events WHERE op_uuid=?");
    $dup->execute([$uuid]);
    if ($dup->fetchColumn()) { $results[]=['uuid'=>$uuid,'ok'=>true,'duplicate'=>true]; continue; }

    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");
        $st->execute([$occId]);
        $occ=$st->fetch();
        if (!$occ) throw new RuntimeException('Ocorrência não encontrada');

        if (!occurrence_mutation_allowed($u,$occ)) {
            throw new RuntimeException('Sua equipe não pode alterar esta ocorrência');
        }

        $now=now_iso();
        $col=status_timestamp_column($status);
        if($col){
            $up=$pdo->prepare("UPDATE occurrences SET status=?,updated_at=?,{$col}=COALESCE({$col},?) WHERE id=?");
            $up->execute([$status,$now,$now,$occId]);
        }else{
            $up=$pdo->prepare("UPDATE occurrences SET status=?,updated_at=? WHERE id=?");
            $up->execute([$status,$now,$occId]);
        }
        if($status==='ENCERRADA') release_vehicle_if_unused($pdo,(int)($occ['vehicle_id']??0),$occId);

        $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,op_uuid,event_type,old_status,new_status,note,user_id,device_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)");
        $ev->execute([$occId,$uuid,'STATUS',$occ['status'],$status,$note?:null,$u['id'],$device?:null,$now]);

        $pdo->commit();
        $results[]=['uuid'=>$uuid,'ok'=>true,'server_time'=>$now];
    } catch(Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $results[]=['uuid'=>$uuid,'ok'=>false,'error'=>$e->getMessage()];
    }
}
json_response(['ok'=>true,'results'=>$results,'server_time'=>now_iso()]);
