<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';
$pendingSecret=$_SESSION['pending_totp_secret']??null;
$newCodes=$_SESSION['new_2fa_recovery_codes']??null;
unset($_SESSION['new_2fa_recovery_codes']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
    $action=$_POST['action']??'';

    if($action==='start_2fa'){
        if(!password_verify($_POST['password']??'',$admin['password_hash'])){
            $err='Senha atual inválida.';
        }else{
            $_SESSION['pending_totp_secret']=totp_secret_new();
            $pendingSecret=$_SESSION['pending_totp_secret'];
            security_audit($pdo,(int)$admin['id'],'TWO_FACTOR_SETUP_STARTED','USER',(string)$admin['id'],true,'Configuração 2FA iniciada.');
            $msg='Chave criada. Cadastre no aplicativo autenticador e confirme com um código de 6 dígitos.';
        }
    }elseif($action==='confirm_2fa'){
        $secret=(string)($_SESSION['pending_totp_secret']??'');
        $code=trim($_POST['code']??'');
        if($secret===''||!totp_verify($secret,$code)){
            $err='Código de confirmação inválido.';
        }else{
            $codes=recovery_codes_new();
            $pdo->prepare("UPDATE users SET two_factor_secret=?,two_factor_enabled=1,two_factor_recovery_hashes=?,two_factor_enabled_at=? WHERE id=?")
                ->execute([encrypt_private_value($secret),json_encode(recovery_hashes($codes)),now_iso(),$admin['id']]);
            unset($_SESSION['pending_totp_secret']);
            $_SESSION['new_2fa_recovery_codes']=$codes;
            security_audit($pdo,(int)$admin['id'],'TWO_FACTOR_ENABLED','USER',(string)$admin['id'],true,'2FA habilitado.');
            admin_audit($pdo,(int)$admin['id'],'ENABLE','2FA',(string)$admin['id'],'Habilitação de segurança','Autenticação em duas etapas habilitada.');
            header('Location: seguranca.php?enabled=1');exit;
        }
    }elseif($action==='disable_2fa'){
        if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
            $err='Não foi possível confirmar sua identidade.';
        }else{
            $pdo->prepare("UPDATE users SET two_factor_secret=NULL,two_factor_enabled=0,two_factor_recovery_hashes=NULL,two_factor_enabled_at=NULL WHERE id=?")->execute([$admin['id']]);
            security_audit($pdo,(int)$admin['id'],'TWO_FACTOR_DISABLED','USER',(string)$admin['id'],true,'2FA desabilitado.');
            admin_audit($pdo,(int)$admin['id'],'DISABLE','2FA',(string)$admin['id'],'Desabilitação de segurança','2FA desabilitado pelo próprio Admin.');
            $msg='Autenticação em duas etapas desabilitada.';
        }
    }elseif($action==='recovery_codes'){
        if(!sensitive_admin_auth($pdo,$admin,$_POST['password']??'',$_POST['two_factor']??'')){
            $err='Não foi possível confirmar sua identidade.';
        }else{
            $codes=recovery_codes_new();
            $pdo->prepare("UPDATE users SET two_factor_recovery_hashes=? WHERE id=?")->execute([json_encode(recovery_hashes($codes)),$admin['id']]);
            $_SESSION['new_2fa_recovery_codes']=$codes;
            security_audit($pdo,(int)$admin['id'],'TWO_FACTOR_RECOVERY_REGENERATED','USER',(string)$admin['id'],true,'Códigos de recuperação substituídos.');
            header('Location: seguranca.php?codes=1');exit;
        }
    }elseif($action==='settings'){
        $idle=max(5,min(240,(int)($_POST['session_idle_minutes']??20)));
        $backupEnabled=isset($_POST['backup_enabled'])?'1':'0';
        $keep=max(7,min(3650,(int)($_POST['backup_keep_days']??30)));
        $require2fa=isset($_POST['admin_2fa_required'])?'1':'0';
        $privacyContact=lower_email($_POST['privacy_contact']??'');
        $retention=upper_text($_POST['privacy_retention']??'');
        $just=trim($_POST['justification']??'');

        if(mb_strlen($just)<5){$err='Informe uma justificativa.';}
        else{
            if($require2fa==='1'){
                $missing=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1 AND deleted_at IS NULL AND COALESCE(two_factor_enabled,0)=0")->fetchColumn();
                if($missing>0){
                    $err='Para exigir 2FA globalmente, todos os Administradores Gerais ativos precisam habilitar o segundo fator primeiro.';
                }
            }
            if($err===''){
                foreach([
                    'session_idle_minutes'=>(string)$idle,
                    'backup_enabled'=>$backupEnabled,
                    'backup_keep_days'=>(string)$keep,
                    'admin_2fa_required'=>$require2fa,
                    'privacy_contact'=>$privacyContact,
                    'privacy_retention'=>$retention?:'DEFINIR PELA ORGANIZAÇÃO'
                ] as $key=>$value) update_system_setting($key,$value,(int)$admin['id']);
                admin_audit($pdo,(int)$admin['id'],'UPDATE','SECURITY_SETTINGS','GLOBAL',$just,'Configurações de segurança, backup e privacidade atualizadas.');
                $msg='Configurações de segurança atualizadas.';
            }
        }
    }
    $st=$pdo->prepare("SELECT * FROM users WHERE id=?");$st->execute([$admin['id']]);$admin=$st->fetch();
}

if(isset($_GET['enabled'])||isset($_GET['codes'])){
    $newCodes=$_SESSION['new_2fa_recovery_codes']??$newCodes;
    unset($_SESSION['new_2fa_recovery_codes']);
}
$pendingSecret=$_SESSION['pending_totp_secret']??$pendingSecret;
$admins=$pdo->query("SELECT id,name,bc_name,registration_number,active,COALESCE(two_factor_enabled,0) two_factor_enabled,two_factor_enabled_at FROM users WHERE role='ADMIN' AND deleted_at IS NULL ORDER BY name")->fetchAll();
$idle=system_setting('session_idle_minutes','20');
$backupEnabled=system_setting('backup_enabled','1');
$keep=system_setting('backup_keep_days','30');
$required=system_setting('admin_2fa_required','0');
$privacyContact=system_setting('privacy_contact','');
$retention=system_setting('privacy_retention','DEFINIR PELA ORGANIZAÇÃO');
$issuer=rawurlencode(app_display_name());
$account=rawurlencode($admin['registration_number']?:$admin['username']);
$uri=$pendingSecret?'otpauth://totp/'.$issuer.':'.$account.'?secret='.rawurlencode($pendingSecret).'&issuer='.$issuer.'&digits=6&period=30':'';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Segurança - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.length>1?history.back():location.href='base.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Segurança e Proteção</span></div></div>
<div class="right"><a href="producao.php">🚒 Produção</a><a href="internet_teste.php">🌐 Internet teste</a><a href="nuvem.php">☁️ Nuvem</a><a href="portal.php">🌐 Portal Web</a><a href="backups.php">Backups</a><a href="dispositivos.php">Dispositivos</a><a href="auditoria.php">Auditoria</a><a href="saude_sistema.php">Saúde do sistema</a><a href="homologacao.php">Homologação</a><a href="logout.php">Sair</a></div></header>
<main class="layout">
<?php if(isset($_GET['setup2fa'])):?><div class="alert error"><strong>2FA obrigatório:</strong> configure a autenticação em duas etapas antes de continuar usando as áreas administrativas.</div><?php endif;?>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<?php if(is_array($newCodes)&&$newCodes):?>
<section class="card recovery-codes"><h2>⚠️ Códigos de recuperação — copie agora</h2><p>Estes códigos são exibidos apenas nesta tela. Guarde-os em local seguro e separado do computador da Central.</p>
<div class="recovery-code-grid"><?php foreach($newCodes as $c):?><code><?=e($c)?></code><?php endforeach;?></div></section>
<?php endif;?>

<section class="card"><h2>Autenticação em duas etapas — seu Admin</h2>
<?php if(!empty($admin['two_factor_enabled'])):?>
<div class="alert ok">✅ 2FA ATIVO para <?=e($admin['bc_name']?:$admin['name'])?>.</div>
<div class="grid2">
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="recovery_codes">
<label>Senha atual<input type="password" name="password" required></label><label>Código 2FA atual<input name="two_factor" required autocomplete="one-time-code"></label><button>Gerar novos códigos de recuperação</button></form>
<form method="post" onsubmit="return confirm('Desabilitar o segundo fator deste Admin?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="disable_2fa">
<label>Senha atual<input type="password" name="password" required></label><label>Código 2FA atual<input name="two_factor" required autocomplete="one-time-code"></label><button class="danger">Desabilitar 2FA</button></form>
</div>
<?php elseif($pendingSecret):?>
<div class="twofa-setup-box">
<p><strong>1.</strong> Abra um aplicativo autenticador compatível com TOTP e escolha adicionar conta por chave de configuração.</p>
<p><strong>2. Chave:</strong></p><div class="twofa-secret"><?=e($pendingSecret)?></div>
<p><strong>Conta:</strong> <?=e($admin['registration_number']?:$admin['username'])?> · <strong>Emissor:</strong> <?=e(app_display_name())?></p>
<details><summary>URI técnica para aplicativo</summary><code class="uri-code"><?=e($uri)?></code></details>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="confirm_2fa"><label>3. Digite o código de 6 dígitos gerado pelo aplicativo<input name="code" required autocomplete="one-time-code" maxlength="6"></label><button class="primary">Confirmar e ativar 2FA</button></form>
</div>
<?php else:?>
<p>O segundo fator adiciona um código temporário ao login dos Administradores Gerais.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="start_2fa"><label>Confirme sua senha atual<input type="password" name="password" required></label><button class="primary">🔐 Configurar meu 2FA</button></form>
<?php endif;?>
</section>

<section class="card"><h2>Status dos Administradores Gerais</h2><div class="table-wrap"><table><thead><tr><th>Admin</th><th>Matrícula</th><th>2FA</th><th>Ativado em</th></tr></thead><tbody>
<?php foreach($admins as $a):?><tr><td><?=e($a['bc_name']?:$a['name'])?></td><td><?=e($a['registration_number']?:'-')?></td><td><?=$a['two_factor_enabled']?'<span class="badge financial-ok">ATIVO</span>':'<span class="badge financial-bad">PENDENTE</span>'?></td><td><?=e($a['two_factor_enabled_at']?:'-')?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="card"><h2>Políticas de segurança, backup e privacidade</h2>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="settings">
<div class="grid3"><label>Bloquear após inatividade (min)<input type="number" min="5" max="240" name="session_idle_minutes" value="<?=e($idle)?>"></label>
<label>Reter backups automáticos (dias)<input type="number" min="7" max="3650" name="backup_keep_days" value="<?=e($keep)?>"></label>
<label>Contato de privacidade / LGPD<input type="email" name="privacy_contact" value="<?=e($privacyContact)?>" placeholder="responsavel@exemplo.com"></label></div>
<label><input type="checkbox" name="backup_enabled" <?=$backupEnabled==='1'?'checked':''?>> Backup automático diário ativado</label>
<label><input type="checkbox" name="admin_2fa_required" <?=$required==='1'?'checked':''?>> Exigir 2FA de todos os Administradores Gerais</label>
<label>Política/resumo de retenção de dados<input name="privacy_retention" value="<?=e($retention)?>" placeholder="Ex.: conforme política institucional e legislação aplicável"></label>
<label>Justificativa administrativa<textarea name="justification" required minlength="5"></textarea></label>
<button class="primary">Salvar políticas</button></form>
</section>
</main><script src="assets/security.js"></script></body></html>
