<?php
require __DIR__ . '/config.php';
$pdo=db();
$error='';

$uid=(int)($_SESSION['pre_2fa_uid']??0);
$expires=(int)($_SESSION['pre_2fa_expires']??0);
if(!$uid || $expires<time()){
    unset($_SESSION['pre_2fa_uid'],$_SESSION['pre_2fa_expires'],$_SESSION['pre_2fa_attempts']);
    header('Location: login.php');exit;
}

$st=$pdo->prepare("SELECT * FROM users WHERE id=? AND active=1 AND deleted_at IS NULL");
$st->execute([$uid]);$u=$st->fetch();
if(!$u){header('Location: login.php');exit;}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')){
        $error='Sessão inválida.';
    }else{
        $code=strtoupper(trim($_POST['code']??''));
        if(verify_two_factor_input($pdo,$u,$code,true)){
            security_audit($pdo,$uid,'TWO_FACTOR_SUCCESS','USER',(string)$uid,true,'Segundo fator confirmado.');
            complete_login($pdo,$u);
            header('Location: index.php');exit;
        }
        $_SESSION['pre_2fa_attempts']=(int)($_SESSION['pre_2fa_attempts']??0)+1;
        security_audit($pdo,$uid,'TWO_FACTOR_FAILURE','USER',(string)$uid,false,'Código 2FA inválido.');
        if((int)$_SESSION['pre_2fa_attempts']>=5){
            unset($_SESSION['pre_2fa_uid'],$_SESSION['pre_2fa_expires'],$_SESSION['pre_2fa_attempts']);
            header('Location: login.php?expired=1');exit;
        }
        $error='Código inválido. Use o código de 6 dígitos do autenticador ou um código de recuperação.';
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificação em duas etapas - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head>
<body class="center"><main class="card auth recovery-card">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo"></div>
<h1>🔐 Verificação em duas etapas</h1>
<p><strong><?=e($u['bc_name']?:$u['name'])?></strong></p>
<p class="muted">Digite o código atual do seu aplicativo autenticador.</p>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label>Código 2FA ou código de recuperação<input name="code" required autofocus autocomplete="one-time-code" maxlength="16" placeholder="000000"></label>
<button class="primary">Confirmar e entrar</button>
</form>
<p><a href="login.php">Cancelar</a></p>
</main></body></html>
