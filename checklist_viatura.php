<?php
require __DIR__ . '/config.php';
$u=require_user();$pdo=db();$id=(int)($_GET['id']??0);$msg=$err='';
$st=$pdo->prepare("SELECT * FROM vehicles WHERE id=? AND active=1");$st->execute([$id]);$v=$st->fetch();if(!$v){http_response_code(404);exit('Viatura não encontrada.');}
$items=['siren'=>'Sirene','lights'=>'Iluminação/sinalização','tires'=>'Pneus','radio'=>'Rádio/comunicação','extinguisher'=>'Extintor','ppe'=>'EPIs','stretcher'=>'Maca e fixação','firstaid'=>'Materiais de primeiros socorros','oxygen'=>'Sistema/material de oxigênio'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
 $check=[];$problem=false;foreach($items as $k=>$label){$val=$_POST[$k]??'NÃO AVALIADO';$check[$k]=$val;if($val==='NÃO CONFORME')$problem=true;}
 $status=$problem?'PENDENCIA':'OK';$q=$pdo->prepare("INSERT INTO vehicle_checklists(vehicle_id,user_id,odometer,fuel_level,checklist_json,notes,status,created_at) VALUES(?,?,?,?,?,?,?,?)");$q->execute([$id,$u['id'],trim($_POST['odometer']??''),trim($_POST['fuel_level']??''),json_encode($check,JSON_UNESCAPED_UNICODE),trim($_POST['notes']??''),$status,now_iso()]);$msg='Checklist registrado: '.$status;
}
$hist=$pdo->prepare("SELECT c.*,COALESCE(u.bc_name,u.name) user_name FROM vehicle_checklists c JOIN users u ON u.id=c.user_id WHERE c.vehicle_id=? ORDER BY c.id DESC LIMIT 20");$hist->execute([$id]);$history=$hist->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Checklist <?=e($v['prefix'])?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e($v['prefix'])?></strong><span>Checklist da Viatura</span></div></div><div class="right"><a href="viaturas.php">Viaturas</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<section class="card"><h2>Novo checklist</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="grid2"><label>Odômetro<input name="odometer"></label><label>Combustível<select name="fuel_level"><option>CHEIO</option><option>3/4</option><option>1/2</option><option>1/4</option><option>RESERVA</option></select></label></div>
<div class="checklist-grid"><?php foreach($items as $k=>$label):?><label><?=e($label)?><select name="<?=e($k)?>"><option>OK</option><option>NÃO CONFORME</option><option>NÃO AVALIADO</option></select></label><?php endforeach;?></div><label>Observações<textarea name="notes"></textarea></label><button class="primary">Registrar checklist</button></form></section>
<section class="card"><h2>Histórico</h2><div class="timeline"><?php foreach($history as $h):?><article><strong><?=e($h['status'])?></strong> · <?=e($h['user_name'])?><p><?=e($h['notes']??'')?></p><small><?=e($h['created_at'])?></small></article><?php endforeach;?></div></section></main><script src="assets/security.js"></script></body></html>
