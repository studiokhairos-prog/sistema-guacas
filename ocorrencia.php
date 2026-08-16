<?php
require __DIR__ . '/config.php';
$u=require_user();$pdo=db();$csrf=csrf_token();
$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT o.*,v.prefix vehicle_prefix,v.description vehicle_description FROM occurrences o LEFT JOIN vehicles v ON v.id=o.vehicle_id WHERE o.id=?");
$st->execute([$id]);$o=$st->fetch();
if(!$o){http_response_code(404);exit('Ocorrência não encontrada.');}
if(!occurrence_access_allowed($u,$o)){http_response_code(403);exit('Acesso negado.');}

if(in_array($u['role'],['ADMIN','BASE','STAFF'],true) && ($o['source']??'')==='PUBLICO' && empty($o['central_acknowledged_at'])){
    acknowledge_public_occurrence($pdo,$id,(int)$u['id']);
    $st->execute([$id]);
    $o=$st->fetch();
}
$ev=$pdo->prepare("SELECT e.*,COALESCE(u.bc_name,u.name,'SISTEMA') user_name FROM occurrence_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.occurrence_id=? ORDER BY e.id DESC LIMIT 200");
$ev->execute([$id]);$events=$ev->fetchAll();
$aph=$pdo->prepare("SELECT id,code,patient_name,cns,status,updated_at FROM aph_records WHERE occurrence_id=? AND deleted_at IS NULL ORDER BY id");
$aph->execute([$id]);$patients=$aph->fetchAll();
$teams=active_teams($pdo);$vehicles=active_vehicles($pdo);$requestedAt=$o['requested_at']?:$o['created_at'];$totalMinutes=minutes_between($requestedAt,$o['closed_at']??null);$responseMinutes=minutes_between($requestedAt,$o['on_scene_at']??null);$dispatchToScene=minutes_between($o['dispatched_at']??null,$o['on_scene_at']??null);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($o['protocol'])?> - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head>
<body>
<button type="button" class="back-floating no-print" onclick="history.length>1?history.back():location.href='index.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e($o['protocol'])?></strong><span>Central da Ocorrência</span></div></div>
<div class="right"><span id="net" class="pill">...</span><a href="aph.php?occurrence_id=<?=$id?>">+ Paciente / APH</a><a href="logout.php">Sair</a></div></header>
<main class="dynamic-layout occurrence-detail-layout">
<section class="card occurrence-hero <?=e('status-'.strtolower(str_replace('_','-',$o['status'])))?>">
<div class="section-head"><div><div class="small"><?=e($o['nature']??'')?></div><h1><?=e($o['type'])?></h1></div><span class="priority-badge priority-<?=e($o['priority'])?>"><?=e($o['priority'])?></span></div>
<p><strong>📍 <?=e($o['address'])?></strong><?php if($o['lat']!==null&&$o['lng']!==null):?> <a class="button-link gps-route-link" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?=urlencode($o['lat'].','.$o['lng'])?>">🧭 Rota GPS até o solicitante</a><?php endif;?></p><?php if($o['lat']!==null&&$o['lng']!==null):?><p class="muted">GPS informado pelo solicitante: <?=e((string)$o['lat'])?>, <?=e((string)$o['lng'])?><?php if(!empty($o['requester_gps_accuracy'])):?> · precisão aproximada ±<?=e((string)round((float)$o['requester_gps_accuracy']))?> m<?php endif;?></p><?php endif;?><p><?=nl2br(e($o['details']??''))?></p>
<div class="op-meta"><span><?=e(occurrence_status_label($o['status']))?></span><span class="level-badge level-<?=e($o['occurrence_level']??'NAO_CLASSIFICADO')?>"><?=e(occurrence_level_label($o['occurrence_level']??'NAO_CLASSIFICADO'))?></span><span>🚒 <?=e($o['team']?:'Aguardando equipe')?></span><span>🚑 <?=e($o['vehicle_prefix']?:'Sem viatura')?></span></div>
</section>
<section class="card"><h2>⏱️ TEMPOS AUTOMÁTICOS DA OCORRÊNCIA</h2><div class="time-metrics"><div class="time-metric"><b>DATA/HORA DA SOLICITAÇÃO</b><strong><?=e($requestedAt?:'—')?></strong></div><div class="time-metric"><b>DESPACHO</b><strong><?=e($o['dispatched_at']?:'—')?></strong></div><div class="time-metric"><b>CHEGADA AO LOCAL</b><strong><?=e($o['on_scene_at']?:'—')?></strong></div><div class="time-metric"><b>FECHAMENTO AUTOMÁTICO</b><strong><?=e($o['closed_at']?:'EM ANDAMENTO')?></strong></div><div class="time-metric"><b>SOLICITAÇÃO → LOCAL</b><strong><?=$responseMinutes!==null?e((string)$responseMinutes).' MIN':'—'?></strong></div><div class="time-metric"><b>DESPACHO → LOCAL</b><strong><?=$dispatchToScene!==null?e((string)$dispatchToScene).' MIN':'—'?></strong></div><div class="time-metric"><b>TEMPO TOTAL</b><strong><?=$totalMinutes!==null?e((string)$totalMinutes).' MIN':'EM ANDAMENTO'?></strong></div><div class="time-metric"><b>STATUS</b><strong><?=e(occurrence_status_label($o['status']))?></strong></div></div><p class="muted">A DATA/HORA DE FECHAMENTO É PREENCHIDA AUTOMATICAMENTE QUANDO A OCORRÊNCIA É MARCADA COMO ENCERRADA.</p></section>

<?php if(in_array($u['role'],['ADMIN','BASE','STAFF'],true) && $o['status']!=='ENCERRADA'):?>
<section class="card"><h2>Despacho e comando</h2>
<div class="grid4">
<label>Equipe<select id="cmdTeam"><option value="">Selecione</option><?php foreach($teams as $t):?><option value="<?=e($t['name'])?>" <?=$o['team']===$t['name']?'selected':''?>><?=e($t['name'])?></option><?php endforeach;?></select></label>
<label>Viatura<select id="cmdVehicle"><option value="">Sem viatura</option><?php foreach($vehicles as $v):?><option value="<?=$v['id']?>" <?=(int)$o['vehicle_id']===(int)$v['id']?'selected':''?>><?=e($v['prefix'].' · '.$v['status'])?></option><?php endforeach;?></select></label>
<label>Nível operacional<select id="cmdLevel" class="level-select"><?php foreach(occurrence_level_options() as $lv):?><option value="<?=e($lv)?>" <?=($o['occurrence_level']??'NAO_CLASSIFICADO')===$lv?'selected':''?>><?=e(occurrence_level_label($lv))?></option><?php endforeach;?></select></label>
<label>Prioridade<select id="cmdPriority"><?php foreach(['BAIXA','MEDIA','ALTA','CRITICA'] as $p):?><option <?=$o['priority']===$p?'selected':''?>><?=$p?></option><?php endforeach;?></select></label>
</div>
<label>Observação do despacho<input id="cmdNote" placeholder="Opcional"></label>
<div class="action-bar"><button id="cmdDispatch" class="primary">🚒 Despachar / atualizar</button>
<?php foreach(['A_CAMINHO','NO_LOCAL','EM_ATENDIMENTO','RETORNANDO','ENCERRADA'] as $s):?><button data-command-status="<?=$s?>"><?=e(occurrence_status_label($s))?></button><?php endforeach;?></div>
</section>
<?php endif;?>

<section class="detail-grid">
<section class="card"><div class="section-head"><h2>Pacientes / Fichas APH</h2><a class="button-link" href="aph.php?occurrence_id=<?=$id?>">+ Novo paciente</a></div>
<?php if(!$patients):?><p class="muted">Nenhum paciente registrado. Uma mesma ocorrência pode possuir várias fichas APH.</p><?php else:?>
<div class="side-list"><?php foreach($patients as $p):?><div class="side-item"><div><strong><?=e($p['patient_name']?:'Paciente sem nome')?></strong><small><?=e($p['code'])?> · <?=e($p['status'])?></small></div><a href="aph.php?id=<?=$p['id']?>">Abrir</a></div><?php endforeach;?></div><?php endif;?>
</section>

<section class="card messages-card"><h2>💬 Comunicação da ocorrência</h2><div id="messageList" class="message-list"></div>
<form id="messageForm"><div class="message-compose"><input id="messageInput" maxlength="1200" placeholder="Mensagem para Base / equipe..." required><button class="primary">Enviar</button></div></form></section>
</section>

<section class="card"><h2>Linha do tempo</h2><div class="timeline">
<?php foreach($events as $e):?><article><strong><?=e($e['event_type'])?></strong> · <?=e($e['user_name'])?><div><?=e($e['old_status']??'')?> <?=$e['new_status']?'→ '.e($e['new_status']):''?></div><?php if($e['note']):?><p><?=nl2br(e($e['note']))?></p><?php endif;?><small><?=e($e['created_at'])?></small></article><?php endforeach;?>
</div></section>
</main>
<script>window.SICOBC={csrf:<?=json_encode($csrf)?>,occurrenceId:<?=$id?>};</script>
<script src="assets/app.js"></script>
<script>
(()=>{
 const id=window.SICOBC.occurrenceId,list=document.getElementById('messageList'),form=document.getElementById('messageForm'),input=document.getElementById('messageInput');
 async function loadMessages(){try{const j=await apiFetch('api/messages.php?occurrence_id='+id);list.innerHTML=(j.items||[]).map(m=>`<div class="message"><strong>${escapeHtml(m.bc_name||m.name)}</strong><p>${escapeHtml(m.message)}</p><small>${escapeHtml(m.created_at)}</small></div>`).join('')||'<p class="muted">Nenhuma mensagem.</p>';list.scrollTop=list.scrollHeight;}catch(e){}}
 form?.addEventListener('submit',async e=>{e.preventDefault();const message=input.value.trim();if(!message)return;try{await apiFetch('api/messages.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({occurrence_id:id,message})});input.value='';await loadMessages();}catch(err){alert(err.message);}});
 document.getElementById('cmdDispatch')?.addEventListener('click',async()=>{try{await apiFetch('api/occurrence_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,action:'dispatch',team:document.getElementById('cmdTeam').value,vehicle_id:Number(document.getElementById('cmdVehicle').value||0),priority:document.getElementById('cmdPriority').value,occurrence_level:document.getElementById('cmdLevel').value,note:document.getElementById('cmdNote').value})});location.reload();}catch(e){alert(e.message);}});
 document.querySelectorAll('[data-command-status]').forEach(b=>b.addEventListener('click',async()=>{if(b.dataset.commandStatus==='ENCERRADA'&&!confirm('Encerrar esta ocorrência?'))return;try{await apiFetch('api/occurrence_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,action:'status',status:b.dataset.commandStatus,note:document.getElementById('cmdNote')?.value||''})});location.reload();}catch(e){alert(e.message);}}));
 loadMessages();setInterval(()=>{if(navigator.onLine)loadMessages();},3000);
})();
</script><script src="assets/security.js"></script></body></html>
