<?php
require dirname(__DIR__) . '/config.php';
$u=require_user();
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['ok'=>false],405);
$_SESSION['last_human_activity']=time();
if(!empty($_SESSION['device_session_id'])){
    db()->prepare("UPDATE device_sessions SET last_seen=? WHERE id=? AND user_id=?")->execute([now_iso(),(int)$_SESSION['device_session_id'],(int)$u['id']]);
}
json_response(['ok'=>true,'idle_minutes'=>max(5,min(240,(int)system_setting('session_idle_minutes','20')))]);
