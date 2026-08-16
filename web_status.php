<?php
require __DIR__ . '/config.php';
$u=require_user(['ADMIN','BASE','STAFF']);
$urlFile=__DIR__.'/../LINK_GUACAS_WEB_ATUAL.txt';
$linkText=is_file($urlFile)?(string)file_get_contents($urlFile):'';
preg_match('#https://[A-Za-z0-9-]+\.trycloudflare\.com/[^\s]+/portal\.php#',$linkText,$m);
$portal=$m[0]??'';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Modo Web - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.length>1?history.back():location.href='base.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Modo Web</span></div></div><div class="right"><a href="portal.php">Portal</a><a href="base.php">Central</a></div></header>
<main class="layout">
<section class="card production-hero production-on"><div><h1>🌐 GUACAS pelo navegador</h1><p>O fluxo principal está configurado para uso web.</p></div><div class="production-badge">✅ WEB</div></section>
<section class="card"><h2>Acesso local</h2><code><?=e(app_absolute_url('portal.php'))?></code></section>
<section class="card"><h2>Acesso externo temporário</h2>
<?php if($portal):?><div class="alert ok"><strong>Último link gerado:</strong><br><code><?=e($portal)?></code></div>
<?php else:?><p>Execute <code>INICIAR_GUACAS_WEB_1_CLIQUE.bat</code> na pasta principal da GUACAS para gerar o link HTTPS.</p><?php endif;?>
</section>
<div class="notice">O link trycloudflare.com é para uso temporário/testes. Para um endereço fixo 24/7, mantenha a opção de Produção preparada para quando decidir configurar domínio e Tunnel permanente.</div>
</main><script src="assets/security.js"></script></body></html>
