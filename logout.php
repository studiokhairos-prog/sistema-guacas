<?php
require __DIR__ . '/config.php';
$u=current_user();
if($u){
    security_audit(db(),(int)$u['id'],isset($_GET['idle'])?'LOGOUT_IDLE':'LOGOUT','SESSION',session_id(),true,isset($_GET['idle'])?'Sessão encerrada por inatividade local.':'Logout solicitado pelo usuário.');
}
clear_auth_session();
$_SESSION=[];
if(ini_get('session.use_cookies')){
    $params=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
}
session_destroy();
header('Location: login.php'.(isset($_GET['idle'])?'?expired=1':''));
exit;
