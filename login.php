<?php
require __DIR__ . '/config.php';
if ((int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) { header('Location: setup.php'); exit; }
if (current_user()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo=db();
    if(login_rate_limited($pdo)){
        $error='Muitas tentativas inválidas. Aguarde alguns minutos antes de tentar novamente.';
    }else{
        $stmt=$pdo->prepare("SELECT * FROM users WHERE username=? AND active=1 AND deleted_at IS NULL");
        $stmt->execute([upper_text($_POST['username'] ?? '')]);
        $u=$stmt->fetch();
        if($u && password_verify($_POST['password'] ?? '',$u['password_hash'])){
            if(($u['role']??'')==='ADMIN' && !empty($u['two_factor_enabled'])){
                session_regenerate_id(true);
                $_SESSION['pre_2fa_uid']=(int)$u['id'];
                $_SESSION['pre_2fa_expires']=time()+300;
                $_SESSION['pre_2fa_attempts']=0;
                header('Location: login_2fa.php');exit;
            }
            complete_login($pdo,$u);
            header('Location: index.php');exit;
        }
        security_audit($pdo,$u?(int)$u['id']:null,'LOGIN_FAILURE','SESSION',null,false,'Usuário ou senha inválidos.');
        $error='Usuário ou senha inválidos.';
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24"><title><?=e(app_display_name())?> - Login</title><link rel="manifest" href="manifest_bombeiros.php"><link rel="apple-touch-icon" href="assets/icons/guacas-bombeiros-180.png">
<link rel="stylesheet" href="assets/app.css"></head><body class="center">
<main class="card auth"><div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" alt="Logo oficial" class="logo-auth"></div>
<h1><?=e(app_display_name())?></h1><p><strong><?=e(ORG_NAME)?></strong></p><p>Sistema Integrado de Comunicação e Operações de Bombeiros Civis</p>
<?php if(isset($_GET['setup'])): ?><div class="alert ok">Administrador Geral criado. Faça o login.</div><?php endif; ?>
<?php if(isset($_GET['expired'])): ?><div class="alert error">Sua sessão foi encerrada por inatividade, revogação do dispositivo ou expiração. Entre novamente.</div><?php endif; ?>
<?php if($error): ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post">
<label>Usuário<input name="username" required autocomplete="username"></label>
<label>Senha<input type="password" name="password" required autocomplete="current-password"></label>
<button class="primary">Entrar</button>
</form>
<p></p><p><a class="forgot-password-link" href="esqueci_senha.php">🔐 Esqueci minha senha</a></p>
<p><a class="public-request-button" href="solicitar_ocorrencia.php">🚨 Solicitar / abrir ocorrência</a></p>
<p><a href="privacidade.php">🔒 Privacidade e proteção de dados</a></p>
<p><a class="whatsapp-button compact" href="contato.php">📲 Ocorrências e denúncias via WhatsApp</a></p><p class="muted">Acesso restrito às equipes autorizadas.</p></main>

</body></html>
