<?php
require dirname(__DIR__) . '/config.php';
$u=require_user(['CAMPO','ADMIN']);
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'Método não permitido'],405);
require_csrf();
if(system_setting('gps_enabled','1')!=='1')json_response(['ok'=>false,'error'=>'GPS desativado pela administração'],403);

$d=json_input();
$lat=isset($d['lat'])?(float)$d['lat']:null;
$lng=isset($d['lng'])?(float)$d['lng']:null;
$accuracy=isset($d['accuracy'])?(float)$d['accuracy']:null;
if($lat===null||$lng===null||$lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)json_response(['ok'=>false,'error'=>'Coordenadas inválidas'],422);

$now=now_iso();$pdo=db();
$st=$pdo->prepare("
INSERT INTO team_presence(user_id,team,lat,lng,accuracy,last_seen)
VALUES(?,?,?,?,?,?)
ON CONFLICT(user_id) DO UPDATE SET team=excluded.team,lat=excluded.lat,lng=excluded.lng,accuracy=excluded.accuracy,last_seen=excluded.last_seen
");
$st->execute([$u['id'],$u['team']?:null,$lat,$lng,$accuracy,$now]);
json_response(['ok'=>true,'last_seen'=>$now]);
