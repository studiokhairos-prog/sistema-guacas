<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
 $id=(int)($_POST['id']??0);$status=$_POST['status']??'PENDENTE';$notes=upper_text($_POST['notes']??'');
 if(!in_array($status,['PENDENTE','APROVADO','REPROVADO'],true))$err='Status inválido.';
 else{
   $pdo->prepare("UPDATE homologation_checks SET status=?,notes=?,checked_by=?,checked_at=? WHERE id=?")->execute([$status,$notes?:null,$admin['id'],now_iso(),$id]);
   admin_audit($pdo,(int)$admin['id'],'UPDATE','HOMOLOGATION_CHECK',(string)$id,'Teste de homologação',$status.' · '.$notes);
   $msg='Item de homologação atualizado.';
 }
}
$rows=$pdo->query("SELECT h.*,COALESCE(u.bc_name,u.name) checked_by_name FROM homologation_checks h LEFT JOIN users u ON u.id=h.checked_by ORDER BY h.category,h.sort_order")->fetchAll();
$total=count($rows);$approved=count(array_filter($rows,fn($r)=>$r['status']==='APROVADO'));$failed=count(array_filter($rows,fn($r)=>$r['status']==='REPROVADO'));
$progress=$total?round($approved/$total*100):0;
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Homologação - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Homologação Operacional</span></div></div><div class="right"><a href="saude_sistema.php">Saúde</a><a href="seguranca.php">Segurança</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="kpi-grid"><article class="kpi"><span>Progresso</span><strong><?=$progress?>%</strong></article><article class="kpi"><span>Aprovados</span><strong><?=$approved?>/<?=$total?></strong></article><article class="kpi kpi-critical"><span>Reprovados</span><strong><?=$failed?></strong></article></section>
<section class="card"><h2>Checklist de homologação</h2><p class="muted">Marque APROVADO somente depois de executar o teste na prática. Use as observações para registrar aparelho, cenário e resultado.</p>
<div class="homologation-list"><?php foreach($rows as $r):?><article class="homologation-item status-<?=strtolower($r['status'])?>"><div class="homologation-title"><span class="badge"><?=e($r['category'])?></span><strong><?=e($r['title'])?></strong><span><?=e($r['status'])?></span></div>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$r['id']?>"><div class="grid2"><label>Resultado<select name="status"><option <?=$r['status']==='PENDENTE'?'selected':''?>>PENDENTE</option><option <?=$r['status']==='APROVADO'?'selected':''?>>APROVADO</option><option <?=$r['status']==='REPROVADO'?'selected':''?>>REPROVADO</option></select></label><label>Observações / evidência<input name="notes" value="<?=e($r['notes']??'')?>" placeholder="EX.: TESTADO EM CELULAR ANDROID..."></label></div><button>Salvar teste</button></form>
<?php if($r['checked_at']):?><small>Última verificação: <?=e($r['checked_at'])?> · <?=e($r['checked_by_name']?:'-')?></small><?php endif;?></article><?php endforeach;?></div></section>
</main><script src="assets/security.js"></script></body></html>
