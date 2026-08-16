<?php
require dirname(__DIR__) . '/config.php';
$u = require_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = '';$params = [];
    if ($u['role'] === 'CAMPO') {$where = ' WHERE team = ? OR team IS NULL OR team = "" ';$params[] = trim((string)($u['team'] ?? ''));}
    $sql="SELECT o.*,v.prefix AS vehicle_prefix,
        (SELECT COUNT(*) FROM aph_records a WHERE a.occurrence_id=o.id AND a.deleted_at IS NULL) AS patient_count,
        (SELECT COUNT(*) FROM occurrence_messages m WHERE m.occurrence_id=o.id) AS message_count
        FROM occurrences o LEFT JOIN vehicles v ON v.id=o.vehicle_id $where
        ORDER BY CASE o.status WHEN 'ENCERRADA' THEN 1 ELSE 0 END,
                 CASE o.priority WHEN 'CRITICA' THEN 0 WHEN 'ALTA' THEN 1 WHEN 'MEDIA' THEN 2 ELSE 3 END,
                 o.id DESC LIMIT 120";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    json_response(['ok'=>true,'items'=>$st->fetchAll(),'server_time'=>now_iso()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!in_array($u['role'], ['ADMIN','BASE','STAFF'], true)) json_response(['ok'=>false,'error'=>'Sem permissão'],403);
    $d = json_input();
    $nature=upper_text($d['nature']??'');
    $type=upper_text($d['type']??'');
    if($nature==='OUTRA') $nature=upper_text($d['nature_other']??'');
    if($type==='OUTRO') $type=upper_text($d['type_other']??'');
    $address = upper_text($d['address'] ?? '');
    if ($nature === '' || $type === '' || $address === '') json_response(['ok'=>false,'error'=>'Natureza, tipo e endereço são obrigatórios'],422);
    $level=upper_text($d['occurrence_level']??'NAO_CLASSIFICADO');if(!in_array($level,occurrence_level_options(),true))$level='NAO_CLASSIFICADO';
    $priority = in_array(($d['priority']??''),['BAIXA','MEDIA','ALTA','CRITICA'],true) ? $d['priority'] : occurrence_level_priority($level);
    $team=trim($d['team']??'');
    if($team!==''){
        $q=$pdo->prepare("SELECT COUNT(*) FROM teams WHERE name=? AND active=1");$q->execute([$team]);
        if(!(int)$q->fetchColumn()) json_response(['ok'=>false,'error'=>'Equipe inválida ou inativa'],422);
    }
    $protocol = protocol_new();$now = now_iso();
    $st = $pdo->prepare("INSERT INTO occurrences(protocol,nature,type,address,priority,occurrence_level,team,status,details,created_by,requested_at,created_at,updated_at,source) VALUES(?,?,?,?,?,?,?,'ABERTA',?,?,?,?,?,'INTERNA')");
    $st->execute([$protocol,$nature,$type,$address,$priority,$level,$team?:null,upper_text($d['details']??'')?:null,$u['id'],$now,$now,$now]);
    $id = (int)$pdo->lastInsertId();
    $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,new_status,note,user_id,created_at) VALUES(?,?,?,?,?,?)");
    $ev->execute([$id,'CRIADA','ABERTA',$nature.' — '.$type,$u['id'],$now]);
    json_response(['ok'=>true,'id'=>$id,'protocol'=>$protocol],201);
}
json_response(['ok'=>false,'error'=>'Método não permitido'],405);
