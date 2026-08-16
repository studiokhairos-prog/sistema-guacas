<?php
require __DIR__ . '/config.php';
$pdo=db();
$error='';$success=false;

if(current_user()){header('Location: index.php');exit;}

$blockedUntil=(int)($_SESSION['recovery_blocked_until']??0);
$attempts=(int)($_SESSION['recovery_attempts']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')){
        $error='Sessão inválida. Atualize a página.';
    }elseif(password_recovery_rate_limited($pdo)){
        $error='Muitas tentativas inválidas neste acesso. Aguarde 15 minutos antes de tentar novamente.';
    }elseif($blockedUntil>time()){
        $minutes=max(1,(int)ceil(($blockedUntil-time())/60));
        $error='Muitas tentativas. Aguarde aproximadamente '.$minutes.' minuto(s) para tentar novamente.';
    }else{
        $registration=upper_text($_POST['registration_number']??'');
        $cpf=normalize_cpf($_POST['cpf']??'');
        $birthDate=trim($_POST['birth_date']??'');
        $newPassword=$_POST['new_password']??'';
        $confirm=$_POST['confirm_password']??'';

        $st=$pdo->prepare("SELECT id,registration_number,cpf_hash,birth_date,active,deleted_at FROM users WHERE registration_number=? LIMIT 1");
        $st->execute([$registration]);
        $user=$st->fetch();

        $identityOk=$user
            && (int)$user['active']===1
            && empty($user['deleted_at'])
            && !empty($user['cpf_hash'])
            && !empty($user['birth_date'])
            && valid_cpf($cpf)
            && hash_equals((string)$user['birth_date'],$birthDate)
            && password_verify($cpf,(string)$user['cpf_hash']);

        if(!$identityOk || strlen($newPassword)<10 || $newPassword!==$confirm){
            $attempts++;
            $_SESSION['recovery_attempts']=$attempts;

            if($attempts>=5){
                $_SESSION['recovery_blocked_until']=time()+900;
                $_SESSION['recovery_attempts']=0;
            }

            $pdo->prepare("INSERT INTO password_recovery_events(user_id,registration_number,success,ip_hash,user_agent,created_at) VALUES(?,?,0,?,?,?)")
                ->execute([$user['id']??null,$registration?:null,privacy_hash(client_ip()),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),now_iso()]);

            $error='Não foi possível confirmar os dados ou a nova senha. Confira Nº Cadastro GUACAS, CPF, data de nascimento e use uma senha com pelo menos 10 caracteres.';
        }else{
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($newPassword,PASSWORD_DEFAULT),(int)$user['id']]);

            $pdo->prepare("INSERT INTO password_recovery_events(user_id,registration_number,success,ip_hash,user_agent,created_at) VALUES(?,?,1,?,?,?)")
                ->execute([(int)$user['id'],$registration,privacy_hash(client_ip()),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),now_iso()]);

            $_SESSION['recovery_attempts']=0;
            unset($_SESSION['recovery_blocked_until']);
            $success=true;
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24"><title>Recuperar senha - <?=e(app_display_name())?></title>
<link rel="stylesheet" href="assets/app.css"></head>
<body class="center">
<main class="card auth recovery-card">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" alt="Logo oficial" class="logo-auth"></div>
<h1>Recuperar senha</h1>
<p><strong><?=e(app_display_name())?></strong></p>
<?php if($success):?>
<div class="alert ok"><strong>Senha alterada com sucesso.</strong><br>Você já pode entrar usando a nova senha.</div>
<p><a class="button-link" href="login.php">Ir para o login</a></p>
<?php else:?>
<p class="muted">A confirmação usa o Nº Cadastro GUACAS, o CPF e a data de nascimento previamente cadastrados.</p>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label>Nº Cadastro GUACAS<input name="registration_number" required placeholder="Ex.: GUA-2026-000001"></label>
<label>CPF<input name="cpf" required inputmode="numeric" autocomplete="off" placeholder="CPF cadastrado"></label>
<label>Data de nascimento<input type="date" name="birth_date" required></label>
<label>Nova senha<input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
<label>Confirmar nova senha<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
<button class="primary">🔐 Confirmar dados e trocar senha</button>
</form>
<div class="notice"><strong>Não consegue recuperar?</strong> Se o CPF ou a data de nascimento ainda não tiverem sido cadastrados, procure um Administrador Geral para atualizar seu cadastro.</div>
<p><a href="login.php">← Voltar ao login</a></p>
<?php endif;?>
</main>
</body></html>
