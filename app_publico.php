<?php
require __DIR__ . '/config.php';
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#b10f18">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?=e(app_display_name())?> Público</title>
<link rel="manifest" href="manifest_publico.php">
<link rel="apple-touch-icon" href="assets/icons/guacas-publico-180.png">
<link rel="stylesheet" href="assets/app.css">
</head><body class="public-app-home">
<main class="public-app-shell">
<img src="assets/icons/guacas-publico-512.png" class="app-home-icon" alt="GUACAS Público">
<h1><?=e(app_display_name())?> Público</h1>
<p>Solicitação rápida de ocorrência pelo navegador ou como aplicativo.</p>
<a class="public-app-emergency-button" href="solicitar_ocorrencia.php?from=app">🚨 SOLICITAR OCORRÊNCIA</a>
<button type="button" class="install-pwa-button" data-install-pwa>📲 INSTALAR GUACAS PÚBLICO</button>
<div class="app-home-links"><a href="portal.php">🌐 Portal</a><a href="privacidade.php">🔒 Privacidade</a><a href="contato.php">📲 WhatsApp</a><a href="app_bombeiros.php">🚒 Acesso Bombeiros</a></div>
<div class="notice">Se houver risco imediato, use também os canais públicos de emergência e os meios locais disponíveis.</div>
</main>
<script>window.GUACAS_PWA_TYPE='PUBLICO';</script><script src="assets/pwa_install.js"></script>
</body></html>
