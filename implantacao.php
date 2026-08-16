<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
 $url=rtrim(trim($_POST['system_public_url']??''),'/');
 $just=trim($_POST['justification']??'');
 if($url!==''&&!preg_match('#^https?://[A-Za-z0-9.\-:\[\]]+(?:/[^ ]*)?$#',$url))$err='Informe uma URL completa começando com http:// ou https://.';
 elseif(mb_strlen($just)<5)$err='Informe uma justificativa.';
 else{
   update_system_setting('system_public_url',$url,(int)$admin['id']);
   admin_audit($pdo,(int)$admin['id'],'UPDATE','PUBLIC_URL','GLOBAL',$just,'URL pública definida como '.$url);
   $msg='Endereço público atualizado.';
 }
}
$base=current_app_base_url();
$https=str_starts_with(strtolower($base),'https://');
$checks=[
 ['PHP 8+',version_compare(PHP_VERSION,'8.0','>=')],
 ['PDO SQLite',extension_loaded('pdo_sqlite')],
 ['OpenSSL',extension_loaded('openssl')],
 ['Pasta data gravável',is_writable(dirname(DB_PATH))],
 ['HTTPS (obrigatório na internet)', $https || str_contains($base,'localhost')],
 ['Pasta de backup GUACAS',sync_cloud_ready($pdo)],
 ['Backup automático local',system_setting('backup_enabled','1')==='1'],
];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Apps e Implantação - <?=e(app_display_name())?></title><link rel="manifest" href="manifest_bombeiros.php"><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Aplicativos e Implantação Online</span></div></div><div class="right"><a href="producao.php">🚒 Produção</a><a href="internet_teste.php">🌐 Internet teste</a><a href="nuvem.php">☁️ Nuvem</a><a href="seguranca.php">Segurança</a><a href="base.php">Central</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card"><h1>📲 Dois aplicativos, um único sistema GUACAS</h1><div class="app-admin-grid">
<a class="app-admin-card public" target="_blank" href="app_publico.php"><img src="assets/icons/guacas-publico-192.png"><div><strong>GUACAS PÚBLICO</strong><span><?=e(app_absolute_url('app_publico.php'))?></span></div></a>
<a class="app-admin-card firefighters" target="_blank" href="app_bombeiros.php"><img src="assets/icons/guacas-bombeiros-192.png"><div><strong>GUACAS BOMBEIROS</strong><span><?=e(app_absolute_url('app_bombeiros.php'))?></span></div></a>
</div></section>

<section class="card"><h2>Endereço público da instalação</h2><p>Quando colocar a GUACAS em um servidor, informe aqui o endereço HTTPS definitivo. Isso também define a URL de retorno usada pelo Google Drive.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>URL da GUACAS<input name="system_public_url" data-preserve-case="1" value="<?=e(system_setting('system_public_url',''))?>" placeholder="https://app.exemplo.com/guacas"></label><label>Justificativa administrativa<textarea name="justification" required minlength="5"></textarea></label><button class="primary">Salvar endereço</button></form>
<p class="muted">Endereço atualmente calculado: <code><?=e($base)?></code></p></section>

<section class="card"><h2>Prontidão para colocar online</h2><div class="health-grid"><?php foreach($checks as [$name,$ok]):?><article class="health-item <?=$ok?'health-ok':'health-atenção'?>"><span><?=$ok?'✅':'⚠️'?></span><div><strong><?=e($name)?></strong></div><b><?=$ok?'OK':'VERIFICAR'?></b></article><?php endforeach;?></div></section>

<section class="card"><h2>Links que você poderá divulgar</h2>
<div class="deployment-links">
<div><strong>Portal Web</strong><code><?=e(app_absolute_url('apps.php'))?></code></div>
<div><strong>App Público</strong><code><?=e(app_absolute_url('app_publico.php'))?></code></div>
<div><strong>Solicitação pública direta</strong><code><?=e(app_absolute_url('solicitar_ocorrencia.php'))?></code></div>
<div><strong>App Bombeiros</strong><code><?=e(app_absolute_url('app_bombeiros.php'))?></code></div>
<div><strong>Nuvem / Pasta sincronizada</strong><code><?=e(app_absolute_url('nuvem.php'))?></code></div>
</div></section>

<div class="notice"><strong>Para uso pela internet:</strong> utilize hospedagem com PHP, HTTPS, backup do servidor e proteção da pasta <code>data</code>. Google Drive é a segunda cópia de segurança; ele não substitui o servidor que atende os aplicativos.</div>
</main><script src="assets/app.js"></script><script src="assets/security.js"></script></body></html>
