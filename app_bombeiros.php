<?php
require __DIR__ . '/config.php';
$count=(int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$u=$count>0?current_user():null;
if(isset($_GET['launch'])&&$u){
    header('Location: index.php');exit;
}
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#75080e">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?=e(app_display_name())?> Bombeiros</title>
<link rel="manifest" href="manifest_bombeiros.php">
<link rel="apple-touch-icon" href="assets/icons/guacas-bombeiros-180.png">
<link rel="stylesheet" href="assets/app.css">
</head><body class="firefighter-app-home">
<main class="firefighter-app-shell">
<img src="assets/icons/guacas-bombeiros-512.png" class="app-home-icon" alt="GUACAS Bombeiros">
<h1><?=e(app_display_name())?> Bombeiros</h1>
<p>Acesso operacional pelo navegador ou como aplicativo para integrantes autorizados.</p>
<?php if($count===0):?><a class="button-link primary" href="setup.php">Configurar primeiro Administrador</a>
<?php elseif($u):?><div class="alert ok">Conectado como <strong><?=e($u['bc_name']?:$u['name'])?></strong></div><a class="public-app-emergency-button firefighter-open" href="index.php">🚒 ABRIR PAINEL OPERACIONAL</a>
<?php else:?><a class="public-app-emergency-button firefighter-open" href="login.php?from=app">🔐 ENTRAR NO APP</a><?php endif;?>
<button type="button" class="install-pwa-button dark" data-install-pwa>📲 INSTALAR GUACAS BOMBEIROS</button>
<div class="app-home-links"><a href="portal.php">🌐 Portal</a><a href="app_publico.php">🚨 GUACAS Público</a></div>
<div class="notice"><strong>Operação:</strong> o aplicativo auxilia o registro e a comunicação, mas rádio, telefone e demais meios redundantes devem continuar disponíveis.</div>
</main>
<script>window.GUACAS_PWA_TYPE='BOMBEIROS';</script><script src="assets/pwa_install.js"></script>
</body></html>
