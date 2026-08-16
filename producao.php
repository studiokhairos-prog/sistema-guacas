<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
    $action=$_POST['action']??'';

    if($action==='save_url'){
        $url=rtrim(trim((string)($_POST['system_public_url']??'')),'/');
        $host=trim((string)($_POST['production_hostname']??''));
        $just=trim((string)($_POST['justification']??''));
        if($url==='' || !preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?(?:/[^ ]*)?$#',$url)){
            $err='A URL definitiva precisa começar com https:// e ser um endereço válido.';
        }elseif(mb_strlen($just)<5){
            $err='Informe uma justificativa administrativa.';
        }else{
            update_system_setting('system_public_url',$url,(int)$admin['id']);
            update_system_setting('production_hostname',$host,(int)$admin['id']);
            admin_audit($pdo,(int)$admin['id'],'UPDATE','PRODUCTION_URL','GLOBAL',$just,'URL de produção: '.$url.' · hostname: '.$host);
            security_audit($pdo,(int)$admin['id'],'PRODUCTION_URL_CONFIGURED','SYSTEM',$host?:$url,true,'Endereço HTTPS definitivo configurado.');
            $msg='✅ Endereço definitivo salvo.';
        }
    }elseif($action==='activate'){
        if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
            $err='Confirmação de segurança inválida.';
        }else{
            $url=trim(system_setting('system_public_url',''));
            if($url==='' || !str_starts_with(strtolower($url),'https://')){
                $err='Configure primeiro a URL HTTPS definitiva.';
            }else{
                update_system_setting('production_mode','1',(int)$admin['id']);
                update_system_setting('production_activated_at',now_iso(),(int)$admin['id']);
                admin_audit($pdo,(int)$admin['id'],'ACTIVATE','PRODUCTION_MODE','GLOBAL','Ativação da produção','Modo de produção ativado pelo Admin.');
                security_audit($pdo,(int)$admin['id'],'PRODUCTION_MODE_ACTIVATED','SYSTEM','GLOBAL',true,'Modo de produção ativado.');
                $msg='🚒 Modo de produção ativado na GUACAS.';
            }
        }
    }elseif($action==='deactivate'){
        if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
            $err='Confirmação de segurança inválida.';
        }else{
            update_system_setting('production_mode','0',(int)$admin['id']);
            admin_audit($pdo,(int)$admin['id'],'DEACTIVATE','PRODUCTION_MODE','GLOBAL','Retorno para homologação','Modo de produção desativado.');
            $msg='Modo de produção desativado.';
        }
    }
}

$url=system_setting('system_public_url','');
$mode=system_setting('production_mode','0')==='1';
$host=system_setting('production_hostname','');
$activated=system_setting('production_activated_at','');
$https=$url!==''&&str_starts_with(strtolower($url),'https://');
$backupOk=system_setting('backup_enabled','1')==='1';
$cloudOk=sync_cloud_ready($pdo);
$twoMissing=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1 AND two_factor_enabled=0")->fetchColumn();
$auditOk=verify_audit_chain($pdo,'security_audit')&&verify_audit_chain($pdo,'admin_audit');
$checks=[
 ['URL HTTPS definitiva',$https,$url?:'NÃO CONFIGURADA'],
 ['Backup local automático',$backupOk,$backupOk?'ATIVO':'DESATIVADO'],
 ['Backup em pasta sincronizada',$cloudOk,$cloudOk?'ATIVO':'VERIFICAR'],
 ['Auditoria íntegra',$auditOk,$auditOk?'CADEIAS ÍNTEGRAS':'VERIFICAR'],
 ['Admins com 2FA',$twoMissing===0,$twoMissing===0?'TODOS COM 2FA':$twoMissing.' ADMIN(S) SEM 2FA'],
 ['OpenSSL',function_exists('openssl_encrypt'),function_exists('openssl_encrypt')?'ATIVO':'AUSENTE'],
 ['PDO SQLite',extension_loaded('pdo_sqlite'),extension_loaded('pdo_sqlite')?'ATIVO':'AUSENTE'],
];
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Produção - <?=e(app_display_name())?></title>
<link rel="manifest" href="manifest_bombeiros.php"><link rel="stylesheet" href="assets/app.css">
</head><body>
<button class="back-floating" onclick="history.length>1?history.back():location.href='base.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Implantação Definitiva</span></div></div>
<div class="right"><a href="saude_sistema.php">Saúde</a><a href="backups.php">Backups</a><a href="nuvem.php">☁️ Nuvem</a><a href="seguranca.php">Segurança</a><a href="base.php">Central</a></div></header>
<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="card production-hero <?=$mode?'production-on':'production-off'?>">
<div><h1>🚒 GUACAS — Produção Web</h1>
<p>Configuração final para um endereço HTTPS estável em hospedagem web.</p></div>
<div class="production-badge"><?=$mode?'✅ PRODUÇÃO ATIVADA':'🟡 AGUARDANDO ENDEREÇO FIXO'?></div>
</section>

<section class="card">
<h2>1. Endereço definitivo</h2>
<p>Depois de publicar na hospedagem, informe aqui a URL HTTPS completa da GUACAS.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_url">
<label>URL HTTPS definitiva<input name="system_public_url" data-preserve-case="1" value="<?=e($url)?>" placeholder="https://app.seudominio.com/GUACAS_V4_0_PRODUCAO_PREPARADA" required></label>
<label>Hostname <span class="small">(opcional)</span><input name="production_hostname" data-preserve-case="1" value="<?=e($host)?>" placeholder="app.seudominio.com"></label>
<label>Justificativa administrativa<textarea name="justification" minlength="5" required placeholder="ATIVAÇÃO DO ENDEREÇO DEFINITIVO DA GUACAS"></textarea></label>
<button class="primary">SALVAR ENDEREÇO DEFINITIVO</button></form>
</section>

<section class="card"><h2>2. Verificações finais</h2>
<div class="health-grid"><?php foreach($checks as [$name,$ok,$detail]):?><article class="health-item <?=$ok?'health-ok':'health-atenção'?>"><span><?=$ok?'✅':'⚠️'?></span><div><strong><?=e($name)?></strong><small><?=e($detail)?></small></div><b><?=$ok?'OK':'VERIFICAR'?></b></article><?php endforeach;?></div>
</section>

<section class="card"><h2>3. Ativar produção</h2>
<?php if(!$mode):?>
<p>Faça isso somente depois que a URL acima abrir corretamente fora da sua rede e o diagnóstico não apresentar erro.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="activate">
<label>Senha do Admin<input type="password" name="password" required></label>
<?php if($admin['two_factor_enabled']):?><label>Código 2FA<input name="two_factor" required></label><?php endif;?>
<button class="primary">🚒 ATIVAR MODO DE PRODUÇÃO</button></form>
<?php else:?>
<div class="alert ok"><strong>Produção ativada em:</strong> <?=e($activated?:'-')?></div>
<div class="deployment-links">
<div><strong>Apps</strong><code><?=e(app_absolute_url('apps.php'))?></code></div>
<div><strong>Portal Público</strong><code><?=e(app_absolute_url('app_publico.php'))?></code></div>
<div><strong>Solicitar ocorrência</strong><code><?=e(app_absolute_url('solicitar_ocorrencia.php'))?></code></div>
<div><strong>Acesso Bombeiros</strong><code><?=e(app_absolute_url('app_bombeiros.php'))?></code></div>
</div>
<details><summary>Desativar modo de produção</summary><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="deactivate"><label>Senha Admin<input type="password" name="password" required></label><?php if($admin['two_factor_enabled']):?><label>2FA<input name="two_factor" required></label><?php endif;?><button>Voltar para homologação</button></form></details>
<?php endif;?>
</section>

<div class="notice"><strong>Importante:</strong> “produção” aqui significa que a aplicação está configurada para o endereço definitivo. A disponibilidade e a segurança continuam dependendo da hospedagem, HTTPS, limites de armazenamento, monitoramento e rotinas externas de backup.</div>
</main><script src="assets/security.js"></script></body></html>
