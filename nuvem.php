<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
    $action=$_POST['action']??'';

    if($action==='enable_easy'){
        $email=lower_email($_POST['sync_cloud_email']??'');
        try{
            $folder=enable_simple_cloud($pdo,(int)$admin['id'],$email);
            admin_audit($pdo,(int)$admin['id'],'ENABLE','SIMPLE_CLOUD','GLOBAL','Ativação de nuvem simples','Pasta interna ativada: '.$folder);
            security_audit($pdo,(int)$admin['id'],'SIMPLE_CLOUD_ENABLED','CLOUD',$folder,true,'Nuvem simples ativada.');
            $msg='✅ Nuvem simples ativada. Agora basta sincronizar a pasta GUACAS com o Google Drive para computador.';
        }catch(Throwable $e){$err=$e->getMessage();}
    }elseif($action==='backup_now'){
        if(!sync_cloud_ready($pdo)){
            try{ enable_simple_cloud($pdo,(int)$admin['id'],cloud_setting($pdo,'sync_cloud_email','')); }
            catch(Throwable $e){$err=$e->getMessage();}
        }
        if($err===''){
            if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
                $err='Confirmação de segurança inválida.';
            }else{
                try{
                    $backup=create_database_backup($pdo,'sync_manual');
                    $copied=sync_cloud_copy_encrypted_backup($pdo,$backup);
                    security_audit($pdo,(int)$admin['id'],'SIMPLE_CLOUD_BACKUP_CREATED','CLOUD',$copied['name'],true,'Backup criptografado criado na pasta interna.');
                    $msg='☁️ Backup criado com sucesso: '.$copied['name'];
                }catch(Throwable $e){$err=$e->getMessage();}
            }
        }
    }elseif($action==='save_email'){
        $email=lower_email($_POST['sync_cloud_email']??'');
        cloud_setting_write($pdo,'sync_cloud_email',$email,(int)$admin['id']);
        $msg='E-mail informativo atualizado. A conta real continua sendo controlada pelo aplicativo Google Drive.';
    }elseif($action==='restore'){
        if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
            $err='Confirmação de segurança inválida.';
        }else{
            try{
                $name=basename((string)($_POST['file_name']??''));
                $recoveryKey=trim((string)($_POST['recovery_key']??''));
                sync_cloud_restore_to_pending($pdo,$name,$recoveryKey!==''?$recoveryKey:null);
                security_audit($pdo,(int)$admin['id'],'SIMPLE_CLOUD_RESTORE_STAGED','CLOUD',$name,true,'Backup interno preparado para restauração.');
                admin_audit($pdo,(int)$admin['id'],'RESTORE','SIMPLE_CLOUD_BACKUP',$name,'Restauração de backup','Backup preparado para restauração.');
                header('Location: nuvem.php?restored=1');exit;
            }catch(Throwable $e){$err=$e->getMessage();}
        }
    }
}

if(isset($_GET['restored']))$msg='✅ Backup restaurado. Verifique a Saúde do Sistema e a Homologação.';

$folder=simple_cloud_folder();
$enabled=cloud_setting($pdo,'sync_cloud_enabled','0')==='1';
$ready=sync_cloud_ready($pdo);
$email=lower_email(cloud_setting($pdo,'sync_cloud_email',''));
$lastAt=cloud_setting($pdo,'sync_cloud_last_copy_at','');
$lastFile=cloud_setting($pdo,'sync_cloud_last_copy_file','');
$lastError=cloud_setting($pdo,'sync_cloud_last_error','');
$files=sync_cloud_list_files($pdo,100);
$windowsPath=$folder;
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nuvem simples - <?=e(app_display_name())?></title>
<link rel="manifest" href="manifest_bombeiros.php"><link rel="stylesheet" href="assets/app.css">
</head><body>
<button class="back-floating" onclick="history.length>1?history.back():location.href='base.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Nuvem em 1 Clique</span></div></div>
<div class="right"><a href="implantacao.php">Apps</a><a href="backups.php">Backups</a><a href="base.php">Central</a><a href="logout.php">Sair</a></div></header>

<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="card easy-cloud-hero">
<div><h1>☁️ Nuvem em 1 clique</h1>
<p>Você não precisa descobrir o caminho do Google Drive. A GUACAS já criou a pasta correta para os backups.</p></div>
<div class="cloud-status <?=$ready?'connected':'disconnected'?>"><?=$ready?'✅ ATIVA':'⚪ AINDA NÃO ATIVADA'?></div>
</section>

<section class="card">
<h2>PASSO 1 — Ative a pasta da GUACAS</h2>
<p>A pasta usada será sempre:</p>
<div class="sync-path-box"><code><?=e(simple_cloud_relative_display())?></code></div>

<?php if(!$enabled):?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="enable_easy">
<label>E-mail usado no Google Drive <span class="small">(somente identificação)</span><input type="email" name="sync_cloud_email" data-lowercase="email" placeholder="seuemail@gmail.com"></label>
<button class="primary">☁️ ATIVAR NUVEM EM 1 CLIQUE</button>
</form>
<?php else:?>
<div class="alert ok">✅ Pasta interna ativada e pronta para receber backups.</div>
<form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_email">
<label>E-mail informativo<input type="email" name="sync_cloud_email" data-lowercase="email" value="<?=e($email)?>" placeholder="seuemail@gmail.com"></label><button>Atualizar e-mail</button>
</form>
<?php endif;?>
</section>

<section class="card">
<h2>PASSO 2 — Faça o Google Drive sincronizar esta pasta</h2>
<div class="easy-cloud-steps">
<article><span>1</span><div><strong>Execute o arquivo</strong><code>ABRIR_PASTA_BACKUP_GUACAS.bat</code><p>Ele abre exatamente a pasta certa.</p></div></article>
<article><span>2</span><div><strong>No Google Drive para computador</strong><p>Abra ⚙️ Preferências → Meu computador / Pastas do computador.</p></div></article>
<article><span>3</span><div><strong>Clique “Adicionar pasta”</strong><p>Escolha a pasta que o arquivo BAT abriu.</p></div></article>
<article><span>4</span><div><strong>Ative a sincronização/backup</strong><p>Depois salve. Pronto: o Drive enviará os arquivos para a nuvem.</p></div></article>
</div>
<div class="notice"><strong>Trocar o e-mail:</strong> troque a conta diretamente no aplicativo Google Drive para computador. A GUACAS não precisa da sua senha.</div>
</section>

<section class="card">
<h2>PASSO 3 — Testar agora</h2>
<p>Depois de configurar o Google Drive, clique no botão abaixo. A GUACAS criará um arquivo criptografado dentro da pasta.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="backup_now">
<label>Senha do Admin<input type="password" name="password" required></label>
<?php if($admin['two_factor_enabled']):?><label>Código 2FA<input name="two_factor" required></label><?php endif;?>
<button class="primary">☁️ CRIAR BACKUP AGORA</button>
</form>
<?php if($lastAt):?><div class="alert ok">Última cópia: <?=e($lastAt)?> · <?=e($lastFile)?></div><?php endif;?>
<?php if($lastError):?><div class="alert error"><?=e($lastError)?></div><?php endif;?>
</section>

<section class="card cloud-key-card"><h2>🔑 Chave de recuperação</h2><p>Baixe e guarde esta chave fora do servidor. Ela protege seus backups criptografados.</p><a class="button-link" href="cloud_key_download.php">BAIXAR CHAVE DE RECUPERAÇÃO</a></section>

<section class="card"><h2>Backups encontrados</h2>
<?php if(!$files):?><p class="muted">Nenhum backup criado ainda.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Arquivo</th><th>Data</th><th>Tamanho</th><th>Restaurar</th></tr></thead><tbody>
<?php foreach($files as $f):?><tr><td><?=e($f['name'])?></td><td><?=e(date('d/m/Y H:i:s',$f['mtime']))?></td><td><?=e(number_format($f['size']/1024/1024,2,',','.'))?> MB</td><td><details><summary>Restaurar</summary><form method="post" onsubmit="return confirm('Restaurar este backup?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="file_name" value="<?=e($f['name'])?>"><label>Senha Admin<input type="password" name="password" required></label><?php if($admin['two_factor_enabled']):?><label>2FA<input name="two_factor" required></label><?php endif;?><label>Chave de recuperação (se veio de outra instalação)<input name="recovery_key" data-preserve-case="1"></label><button class="danger">Restaurar</button></form></details></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
</section>

</main><script src="assets/app.js"></script><script src="assets/security.js"></script></body></html>
