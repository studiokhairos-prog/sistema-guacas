<?php
require __DIR__ . '/config.php';
$u=require_user(['ADMIN','CAMPO']);$csrf=csrf_token();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24"><title><?=e(app_display_name())?> - Operação de Campo</title><link rel="manifest" href="manifest_bombeiros.php"><link rel="apple-touch-icon" href="assets/icons/guacas-bombeiros-180.png"><link rel="stylesheet" href="assets/app.css"></head>
<body class="field-body">
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Operação de Campo</span></div></div>
<div class="right"><span id="net" class="pill">...</span><span id="pending" class="pill">0 pendentes</span><button id="gpsToggle" class="top-action">📍 Ativar GPS</button><?php if($u['role']==='ADMIN'):?><a href="base.php">Central</a><?php endif;?><a href="viaturas.php">Viaturas</a><a href="materiais.php">Materiais</a><a href="carteirinha.php" target="_blank">Carteirinha</a><a href="logout.php">Sair</a></div></header>
<main class="field-layout">
<section class="field-identity card"><div><strong><?=e($u['bc_name']?:$u['name'])?></strong><span><?=e($u['name'])?></span></div><div><b>Equipe</b><span><?=e($u['team']?:'SEM EQUIPE')?></span></div><div><b>Conexão</b><span id="gpsState">GPS desligado</span></div><button id="syncNow" class="primary">Sincronizar agora</button></section>
<section><div class="section-head"><h2>Minhas ocorrências</h2><button id="refresh">Atualizar</button></div><div id="occurrences" class="field-board"></div></section>
</main>
<script>window.SICOBC={csrf:<?=json_encode($csrf)?>,role:<?=json_encode($u['role'])?>,team:<?=json_encode($u['team'])?>};</script>
<script src="assets/app.js"></script><script src="assets/campo.js"></script>
<script src="assets/security.js"></script></body></html>
