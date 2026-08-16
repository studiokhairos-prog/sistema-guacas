<?php
require dirname(__DIR__) . '/config.php';
$u=require_user(['ADMIN','BASE','STAFF']);
$pdo=db();

$where='';$params=[];
if($u['role']==='CAMPO'){
    $where=" WHERE o.team=? OR o.team IS NULL OR o.team='' ";
    $params[]=$u['team'] ?? '';
}

$sql="
SELECT o.*,v.prefix AS vehicle_prefix,v.description AS vehicle_description,
       (SELECT COUNT(*) FROM aph_records a WHERE a.occurrence_id=o.id AND a.deleted_at IS NULL) AS patient_count,
       (SELECT COUNT(*) FROM occurrence_messages m WHERE m.occurrence_id=o.id) AS message_count
  FROM occurrences o
  LEFT JOIN vehicles v ON v.id=o.vehicle_id
 $where
 ORDER BY CASE o.status WHEN 'ENCERRADA' THEN 1 ELSE 0 END,
          CASE o.priority WHEN 'CRITICA' THEN 0 WHEN 'ALTA' THEN 1 WHEN 'MEDIA' THEN 2 ELSE 3 END,
          o.id DESC
 LIMIT 150
";
$st=$pdo->prepare($sql);$st->execute($params);$items=$st->fetchAll();

$today=date('Y-m-d');
$stats=[
    'open'=>(int)$pdo->query("SELECT COUNT(*) FROM occurrences WHERE status<>'ENCERRADA'")->fetchColumn(),
    'critical'=>(int)$pdo->query("SELECT COUNT(*) FROM occurrences WHERE status<>'ENCERRADA' AND priority='CRITICA'")->fetchColumn(),
    'today'=>(int)$pdo->query("SELECT COUNT(*) FROM occurrences WHERE substr(created_at,1,10)='$today'")->fetchColumn(),
    'patients_today'=>(int)$pdo->query("SELECT COUNT(*) FROM aph_records WHERE deleted_at IS NULL AND substr(created_at,1,10)='$today'")->fetchColumn(),
    'teams_available'=>(int)$pdo->query("SELECT COUNT(*) FROM teams t WHERE t.active=1 AND NOT EXISTS(SELECT 1 FROM occurrences o WHERE o.team=t.name AND o.status<>'ENCERRADA')")->fetchColumn(),
    'teams_engaged'=>(int)$pdo->query("SELECT COUNT(*) FROM teams t WHERE t.active=1 AND EXISTS(SELECT 1 FROM occurrences o WHERE o.team=t.name AND o.status<>'ENCERRADA')")->fetchColumn(),
];

json_response([
    'ok'=>true,
    'items'=>$items,
    'stats'=>$stats,
    'teams'=>team_operational_rows($pdo),
    'vehicles'=>active_vehicles($pdo),
    'server_time'=>now_iso(),
]);
