<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';
if(isset($_GET['restored']))$msg='Restauração aplicada. Verifique imediatamente a Saúde do Sistema e execute a homologação dos fluxos críticos.';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
    $action=$_POST['action']??'';
    $password=$_POST['password']??'';$twoFactor=$_POST['two_factor']??'';

    if(!sensitive_admin_auth($pdo,$admin,$password,$twoFactor)){
        $err='Confirmação de segurança inválida.';
        security_audit($pdo,(int)$admin['id'],'BACKUP_SENSITIVE_AUTH_FAILURE','BACKUP',null,false,'Falha na confirmação para operação de backup/restauração.');
    }elseif($action==='create'){
        try{
            $file=create_database_backup($pdo,'manual');
            security_audit($pdo,(int)$admin['id'],'BACKUP_CREATED','BACKUP',basename($file),true,'Backup manual criado.');
            admin_audit($pdo,(int)$admin['id'],'CREATE','BACKUP',basename($file),'Backup manual','Backup do banco criado.');
            $msg='Backup criado e validado: '.basename($file);
        }catch(Throwable $e){$err=$e->getMessage();}
    }elseif($action==='restore'){
        try{
            $f=$_FILES['backup_file']??[];
            if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione um arquivo de backup SQLite.');
            if((int)($f['size']??0)>200*1024*1024)throw new RuntimeException('Arquivo acima do limite de 200 MB.');
            $tmp=(string)$f['tmp_name'];
            if(!sqlite_file_valid($tmp))throw new RuntimeException('O arquivo não parece ser um banco SQLite válido.');
            if(!copy($tmp,RESTORE_PENDING_FILE))throw new RuntimeException('Não foi possível preparar a restauração.');
            security_audit($pdo,(int)$admin['id'],'RESTORE_STAGED','BACKUP',basename((string)$f['name']),true,'Restauração preparada para a próxima requisição.');
            admin_audit($pdo,(int)$admin['id'],'RESTORE','BACKUP',basename((string)$f['name']),'Restauração confirmada','Arquivo de restauração preparado. O sistema mantém cópia pré-restauração.');
            header('Location: backups.php?restored=1');exit;
        }catch(Throwable $e){$err=$e->getMessage();}
    }
}
$rows=backup_inventory();
$last=$rows[0]??null;
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Backups - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Backup e Recuperação</span></div></div><div class="right"><a href="nuvem.php">☁️ Google Drive</a><a href="seguranca.php">Segurança</a><a href="saude_sistema.php">Saúde</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="kpi-grid"><article class="kpi"><span>Backups disponíveis</span><strong><?=count($rows)?></strong></article><article class="kpi"><span>Último backup</span><strong style="font-size:16px"><?=$last?e(date('d/m/Y H:i',$last['mtime'])):'NENHUM'?></strong></article><article class="kpi"><span>Automático diário</span><strong><?=system_setting('backup_enabled','1')==='1'?'ATIVO':'DESATIVADO'?></strong></article></section>
<section class="detail-grid">
<section class="card"><h2>💾 Criar backup agora</h2><p>Gera uma cópia consistente do banco e calcula SHA-256 para conferência.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Senha do Admin<input type="password" name="password" required></label><?php if($admin['two_factor_enabled']):?><label>Código 2FA<input name="two_factor" required></label><?php endif;?><button class="primary">Criar backup manual</button></form></section>
<section class="card danger-zone"><h2>♻️ Restaurar backup</h2><p><strong>Cuidado:</strong> restauração substitui o banco atual. Antes da troca, o sistema cria uma cópia pré-restauração.</p>
<form method="post" enctype="multipart/form-data" onsubmit="return confirm('CONFIRMA restaurar este backup? O banco atual será substituído.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><label>Arquivo .sqlite<input type="file" name="backup_file" accept=".sqlite,application/vnd.sqlite3" required></label><label>Senha do Admin<input type="password" name="password" required></label><?php if($admin['two_factor_enabled']):?><label>Código 2FA<input name="two_factor" required></label><?php endif;?><button class="danger">Preparar e aplicar restauração</button></form></section>
</section>
<section class="card"><h2>Histórico de backups</h2><div class="table-wrap"><table><thead><tr><th>Arquivo</th><th>Data</th><th>Tamanho</th><th>SHA-256</th><th>Ação</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['name'])?></td><td><?=e(date('d/m/Y H:i:s',$r['mtime']))?></td><td><?=e(number_format($r['size']/1024/1024,2,',','.'))?> MB</td><td><code><?=e(substr($r['sha256'],0,20))?>…</code></td><td><a class="button-link" href="backup_download.php?file=<?=urlencode($r['name'])?>">Baixar</a></td></tr><?php endforeach;?></tbody></table></div></section>
</main><script src="assets/security.js"></script></body></html>
