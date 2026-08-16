<?php
require __DIR__ . '/config.php';
$u=require_user();$pdo=db();$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!is_admin_general($u)){http_response_code(403);exit('Somente Admin Geral.');}
 if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF inválido');
 $id=(int)($_POST['id']??0);$prefix=trim($_POST['prefix']??'');$description=trim($_POST['description']??'');$plate=trim($_POST['plate']??'');$teamId=(int)($_POST['team_id']??0);$status=$_POST['status']??'DISPONIVEL';$notes=trim($_POST['notes']??'');$just=trim($_POST['justification']??'');
 if($prefix===''||$description===''||mb_strlen($just)<5)$err='Informe prefixo, descrição e justificativa.';
 elseif(!in_array($status,['DISPONIVEL','EM_USO','MANUTENCAO','INATIVA'],true))$err='Status inválido.';
 else{
  try{$now=now_iso();if($id){$st=$pdo->prepare("UPDATE vehicles SET prefix=?,description=?,plate=?,team_id=?,status=?,notes=?,updated_at=? WHERE id=?");$st->execute([$prefix,$description,$plate?:null,$teamId?:null,$status,$notes?:null,$now,$id]);admin_audit($pdo,$u['id'],'UPDATE','VEHICLE',(string)$id,$just,$prefix);}
  else{$st=$pdo->prepare("INSERT INTO vehicles(prefix,description,plate,team_id,status,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)");$st->execute([$prefix,$description,$plate?:null,$teamId?:null,$status,$notes?:null,$now,$now]);$id=(int)$pdo->lastInsertId();admin_audit($pdo,$u['id'],'CREATE','VEHICLE',(string)$id,$just,$prefix);}
  $msg='Viatura salva.';}catch(Throwable $e){$err='Não foi possível salvar. Verifique o prefixo.';}
 }
}
$vehicles=$pdo->query("SELECT v.*,t.name team_name FROM vehicles v LEFT JOIN teams t ON t.id=v.team_id WHERE v.active=1 ORDER BY v.prefix")->fetchAll();$teams=active_teams($pdo);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Viaturas - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head>
<body><button class="back-floating" onclick="history.back()">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Viaturas e Checklists</span></div></div><div class="right"><a href="index.php">Operação</a><a href="logout.php">Sair</a></div></header>
<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<?php if(is_admin_general($u)):?><section class="card"><h2>Cadastrar / atualizar viatura</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" id="vehicleId">
<div class="grid3"><label>Prefixo<input name="prefix" id="vehiclePrefix" required placeholder="Ex.: UR-01"></label><label>Descrição<input name="description" id="vehicleDescription" required placeholder="Unidade de Resgate"></label><label>Placa<input name="plate" id="vehiclePlate"></label></div>
<div class="grid2"><label>Equipe de referência<select name="team_id" id="vehicleTeam"><option value="">Sem equipe fixa</option><?php foreach($teams as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach;?></select></label><label>Status<select name="status" id="vehicleStatus"><option>DISPONIVEL</option><option>EM_USO</option><option>MANUTENCAO</option><option>INATIVA</option></select></label></div>
<label>Observações<input name="notes" id="vehicleNotes"></label><label>Justificativa administrativa<input name="justification" required minlength="5"></label><button class="primary">Salvar viatura</button></form></section><?php endif;?>
<section><h2>Frota</h2><div class="cards"><?php foreach($vehicles as $v):?><article class="card"><div class="section-head"><h3><?=e($v['prefix'])?></h3><span class="badge"><?=e($v['status'])?></span></div><p><?=e($v['description'])?></p><p class="muted">Placa: <?=e($v['plate']?:'-')?> · Equipe: <?=e($v['team_name']?:'Sem equipe fixa')?></p><a class="button-link" href="checklist_viatura.php?id=<?=$v['id']?>">✅ Checklist</a><?php if(is_admin_general($u)):?> <button class="edit-vehicle" data-json='<?=e(json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'>Editar</button><?php endif;?></article><?php endforeach;?></div></section>
</main>
<script>document.querySelectorAll('.edit-vehicle').forEach(b=>b.addEventListener('click',()=>{const v=JSON.parse(b.dataset.json);for(const [id,key] of [['vehicleId','id'],['vehiclePrefix','prefix'],['vehicleDescription','description'],['vehiclePlate','plate'],['vehicleTeam','team_id'],['vehicleStatus','status'],['vehicleNotes','notes']]){const el=document.getElementById(id);if(el)el.value=v[key]??'';}scrollTo({top:0,behavior:'smooth'});}));</script>
<script src="assets/security.js"></script></body></html>
