<?php
require __DIR__ . '/config.php';
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#75080e">
<title><?=e(app_display_name())?> — Portal Web</title>
<link rel="stylesheet" href="assets/app.css">
</head><body class="apps-landing">
<main class="apps-page">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo"></div>
<h1><?=e(app_display_name())?> — Portal Web</h1>
<p>Acesse pelo navegador do celular, tablet ou computador. Se preferir, instale um atalho com aparência de aplicativo.</p><div class="alert ok"><strong>✅ MODO WEB ATIVO</strong> — funciona sem instalar e também pode ser adicionado à tela inicial.</div>

<div class="app-choice-grid">
<a class="app-choice-card public" href="app_publico.php?from=portal">
<img src="assets/icons/guacas-publico-192.png" alt="">
<div><strong>PORTAL PÚBLICO</strong><span>Solicitar uma ocorrência pela internet</span></div>
</a>

<a class="app-choice-card firefighters" href="app_bombeiros.php?from=portal">
<img src="assets/icons/guacas-bombeiros-192.png" alt="">
<div><strong>ACESSO BOMBEIROS</strong><span>Entrar na Central / Campo / APH</span></div>
</a>
</div>

<div class="notice" style="margin-top:18px">
<strong>Modo Web:</strong> você pode usar normalmente no navegador. Nos aparelhos compatíveis, cada área oferece o botão de instalação.
</div>
</main>
</body></html>
