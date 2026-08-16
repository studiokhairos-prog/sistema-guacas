<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();
$backups=backup_inventory();$last=$backups[0]??null;
$latestAge=$last?time()-$last['mtime']:null;
$pendingPublic=(int)$pdo->query("SELECT COUNT(*) FROM occurrences WHERE source='PUBLICO' AND status<>'ENCERRADA' AND central_acknowledged_at IS NULL")->fetchColumn();
$activeDevices=(int)$pdo->query("SELECT COUNT(*) FROM device_sessions WHERE revoked_at IS NULL")->fetchColumn();
$adminsNo2fa=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1 AND deleted_at IS NULL AND COALESCE(two_factor_enabled,0)=0")->fetchColumn();
$checks=[
 ['Banco SQLite','OK',is_file(DB_PATH)?'Banco encontrado.':'Banco será criado no primeiro uso.'],
 ['Integridade SQLite','OK',(string)$pdo->query("PRAGMA integrity_check")->fetchColumn()],
 ['Pasta de dados',is_writable(dirname(DB_PATH))?'OK':'ERRO',is_writable(dirname(DB_PATH))?'Gravável':'Sem permissão de escrita'],
 ['Pasta de backup',is_writable(BACKUP_DIR)?'OK':'ERRO',is_writable(BACKUP_DIR)?'Gravável':'Sem permissão de escrita'],
 ['Backup recente',($latestAge!==null&&$latestAge<36*3600)?'OK':'ATENÇÃO',$last?'Último: '.date('d/m/Y H:i',$last['mtime']):'Nenhum backup disponível'],
 ['OpenSSL',function_exists('openssl_encrypt')?'OK':'ERRO',function_exists('openssl_encrypt')?'Disponível para proteger segredo 2FA':'Extensão não encontrada'],
 ['Pasta de nuvem GUACAS',sync_cloud_ready($pdo)?'OK':'ATENÇÃO',sync_cloud_ready($pdo)?'Pasta interna pronta: '.simple_cloud_relative_display():'Ative Admin → Nuvem'],
 ['HTTPS',((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')?'OK':'ATENÇÃO','Em localhost o HTTP pode ser usado para testes; produção deve usar HTTPS.'],
 ['2FA dos Admins',$adminsNo2fa===0?'OK':'ATENÇÃO',$adminsNo2fa.' Admin(s) sem 2FA'],
 ['Solicitações públicas pendentes',$pendingPublic===0?'OK':'ATENÇÃO',$pendingPublic.' aguardando ciência da Central'],
 ['Auditoria segurança',verify_audit_chain($pdo,'security_audit')?'OK':'ATENÇÃO','Verificação do encadeamento dos eventos'],
 ['Auditoria administrativa',verify_audit_chain($pdo,'admin_audit')?'OK':'ATENÇÃO','Verificação do encadeamento dos novos eventos'],
];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saúde do Sistema - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Saúde do Sistema</span></div></div><div class="right"><a href="backups.php">Backups</a><a href="seguranca.php">Segurança</a><a href="homologacao.php">Homologação</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><section class="kpi-grid"><article class="kpi"><span>PHP</span><strong style="font-size:18px"><?=e(PHP_VERSION)?></strong></article><article class="kpi"><span>SQLite</span><strong style="font-size:18px"><?=e((string)$pdo->query('SELECT sqlite_version()')->fetchColumn())?></strong></article><article class="kpi"><span>Dispositivos ativos</span><strong><?=$activeDevices?></strong></article><article class="kpi"><span>Espaço livre</span><strong style="font-size:18px"><?=e(number_format((float)disk_free_space(dirname(DB_PATH))/1024/1024/1024,1,',','.'))?> GB</strong></article></section>
<section class="card"><h2>Diagnóstico</h2><div class="health-grid"><?php foreach($checks as [$name,$status,$detail]):?><article class="health-item health-<?=strtolower($status)?>"><span><?=$status==='OK'?'✅':($status==='ERRO'?'❌':'⚠️')?></span><div><strong><?=e($name)?></strong><p><?=e($detail)?></p></div><b><?=e($status)?></b></article><?php endforeach;?></div></section>
<section class="card"><h2>Uso operacional</h2><p>Este painel é uma verificação técnica básica. Antes de uso real, execute também a página de <a href="homologacao.php">Homologação</a> e simule o fluxo completo com a equipe.</p></section>
</main><script src="assets/security.js"></script></body></html>
