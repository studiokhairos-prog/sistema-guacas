<?php
require __DIR__ . '/config.php';
$u=require_user();$pdo=db();$msg=$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
    $id=(int)($_POST['id']??0);
    $action=$_POST['action']??'';
    $st=$pdo->prepare("SELECT * FROM device_sessions WHERE id=?");$st->execute([$id]);$d=$st->fetch();
    if(!$d){$err='Sessão/dispositivo não encontrado.';}
    elseif(!is_admin_general($u) && (int)$d['user_id']!==(int)$u['id']){$err='Acesso negado.';}
    elseif($action==='revoke'){
        $pdo->prepare("UPDATE device_sessions SET revoked_at=?,revoked_by=? WHERE id=? AND revoked_at IS NULL")->execute([now_iso(),$u['id'],$id]);
        security_audit($pdo,(int)$u['id'],'DEVICE_REVOKED','DEVICE',(string)$id,true,'Sessão de navegador revogada.');
        if(is_admin_general($u))admin_audit($pdo,(int)$u['id'],'REVOKE','DEVICE',(string)$id,'Revogação de sessão/dispositivo','Sessão de navegador revogada.');
        $msg='Sessão de navegador revogada.';
    }
}

if(is_admin_general($u)){
    $rows=$pdo->query("SELECT d.*,COALESCE(u.bc_name,u.name) user_name,u.registration_number FROM device_sessions d JOIN users u ON u.id=d.user_id ORDER BY d.revoked_at IS NULL DESC,d.last_seen DESC LIMIT 300")->fetchAll();
}else{
    $st=$pdo->prepare("SELECT d.*,COALESCE(u.bc_name,u.name) user_name,u.registration_number FROM device_sessions d JOIN users u ON u.id=d.user_id WHERE d.user_id=? ORDER BY d.revoked_at IS NULL DESC,d.last_seen DESC");
    $st->execute([$u['id']]);$rows=$st->fetchAll();
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dispositivos - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Sessões e Dispositivos de Navegador</span></div></div><div class="right"><?php if(is_admin_general($u)):?><a href="seguranca.php">Segurança</a><?php endif;?><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card"><h2><?=is_admin_general($u)?'Dispositivos registrados':'Meus dispositivos registrados'?></h2>
<p class="muted">Esta função controla sessões de navegador. Revogar encerra o acesso daquela sessão quando ela fizer a próxima comunicação com o sistema. Não é um bloqueio físico de hardware.</p>
<div class="table-wrap"><table><thead><tr><th>Bombeiro</th><th>Navegador/dispositivo</th><th>Criado</th><th>Última atividade</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
<?php foreach($rows as $d):?><tr><td><strong><?=e($d['user_name'])?></strong><div class="small"><?=e($d['registration_number']?:'-')?></div></td><td><?=e($d['device_label']?:'NAVEGADOR')?><div class="small">IP protegido: <?=e(substr((string)$d['ip_hash'],0,12))?>…</div></td><td><?=e($d['created_at'])?></td><td><?=e($d['last_seen'])?></td><td><?=$d['revoked_at']?'<span class="badge financial-bad">REVOGADO</span>':'<span class="badge financial-ok">ATIVO</span>'?></td><td><?php if(!$d['revoked_at']):?><form method="post" onsubmit="return confirm('Revogar esta sessão de navegador?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?=$d['id']?>"><button>Revogar sessão</button></form><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div></section></main><script src="assets/security.js"></script></body></html>
