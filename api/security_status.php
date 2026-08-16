<?php
require dirname(__DIR__) . '/config.php';
$u=require_user();
json_response([
 'ok'=>true,
 'idle_minutes'=>max(5,min(240,(int)system_setting('session_idle_minutes','20'))),
 'two_factor_enabled'=>(bool)($u['two_factor_enabled']??false),
 'role'=>$u['role']
]);
