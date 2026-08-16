(() => {
const list=document.getElementById('occurrences'),form=document.getElementById('newOcc');
const nature=document.getElementById('nature'),type=document.getElementById('occType'),otherRow=document.getElementById('otherRow');
const natureOther=document.getElementById('natureOther'),typeOther=document.getElementById('typeOther');
const catalog=window.SICOBC?.catalog||{};
let items=[],lastSnapshot=new Map(),alertsEnabled=localStorage.getItem('guacas-central-alarm-enabled')==='1',firstLoad=true,publicAlarmTimer=null,unackPublicIds=new Set();

function fillTypes(selected=''){
 const n=nature?.value||''; if(!type)return;
 type.innerHTML='';
 if(!n){type.disabled=true;type.innerHTML='<option value="">Escolha a natureza</option>';otherRow.hidden=true;return;}
 type.disabled=false;
 if(n==='OUTRA'){type.innerHTML='<option value="OUTRO">OUTRO / DIGITAR</option>';otherRow.hidden=false;natureOther.required=true;typeOther.required=true;return;}
 otherRow.hidden=true;natureOther.required=false;typeOther.required=false;
 type.innerHTML='<option value="">Selecione...</option>'+(catalog[n]||[]).map(x=>`<option value="${escapeHtml(x)}">${escapeHtml(x)}</option>`).join('')+'<option value="OUTRO">OUTRO / DIGITAR</option>';
 if(selected)type.value=selected;
}
nature?.addEventListener('change',()=>fillTypes());
type?.addEventListener('change',()=>{const custom=type.value==='OUTRO';otherRow.hidden=!custom;natureOther.required=custom&&nature.value==='OUTRA';typeOther.required=custom;});
document.getElementById('quickOcc')?.addEventListener('click',e=>{const b=e.target.closest('[data-nature]');if(!b)return;nature.value=b.dataset.nature;fillTypes(b.dataset.type);document.querySelector('[name=address]')?.focus();});
document.getElementById('toggleNewOcc')?.addEventListener('click',()=>{form.hidden=!form.hidden;});

function parseDate(s){const d=new Date(s);return Number.isNaN(d.getTime())?null:d;}
function elapsed(s){const d=parseDate(s);if(!d)return '—';let sec=Math.max(0,Math.floor((Date.now()-d.getTime())/1000));const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60);return h?`${h}h ${m}min`:`${m} min`;}
function statusClass(s){return 'status-'+String(s||'').toLowerCase().replaceAll('_','-');}
function statusLabel(s){return String(s||'').replaceAll('_',' ');}
function priorityIcon(p){return p==='CRITICA'?'🔴':p==='ALTA'?'🟠':p==='MEDIA'?'🟡':'🟢';}
function teamsOptions(selected=''){return ['<option value="">Escolha a equipe</option>',...(window.SICOBC.teams||[]).map(t=>`<option ${t.name===selected?'selected':''} value="${escapeHtml(t.name)}">${escapeHtml(t.name)}</option>`)].join('');}
function vehiclesOptions(selected=''){return ['<option value="">Sem viatura</option>',...(window.SICOBC.vehicles||[]).filter(v=>v.active==1).map(v=>`<option ${String(v.id)===String(selected||'')?'selected':''} value="${v.id}">${escapeHtml(v.prefix)} · ${escapeHtml(v.status)}</option>`)].join('');}

function card(o){
 const closed=o.status==='ENCERRADA';
 const timerStart=o.requested_at||o.created_at;
 const publicUnattended=o.source==='PUBLICO'&&!o.central_acknowledged_at&&o.status!=='ENCERRADA';
 return `<article class="op-card ${statusClass(o.status)} ${o.priority==='CRITICA'?'critical-pulse':''} ${publicUnattended?'public-unattended-card':''}" data-search="${escapeHtml([o.protocol,o.nature,o.type,o.address,o.team,o.status].join(' ').toLowerCase())}" data-status="${escapeHtml(o.status)}" data-priority="${escapeHtml(o.priority)}">
   <div class="op-card-top"><div><strong class="protocol">${escapeHtml(o.protocol)}</strong><div class="op-nature">${escapeHtml(o.nature||'SEM NATUREZA')}</div><span class="level-badge level-${escapeHtml(o.occurrence_level||'NAO_CLASSIFICADO')}">${escapeHtml(String(o.occurrence_level||'NAO_CLASSIFICADO').replaceAll('_',' '))}</span></div><span class="priority-badge priority-${o.priority}">${priorityIcon(o.priority)} ${escapeHtml(o.priority)}</span></div>
   ${o.source==='PUBLICO'?`<div class="public-source ${publicUnattended?'public-source-unattended':''}"><strong>${publicUnattended?'🚨 OCORRÊNCIA PÚBLICA AINDA NÃO ABERTA PELA CENTRAL':'✅ Solicitação pública visualizada'}</strong>${o.requester_name?` · ${escapeHtml(o.requester_name)}`:''}${o.requester_phone?` · ${escapeHtml(o.requester_phone)}`:''}${publicUnattended?`<span class="public-wait-timer blink-timer">⏱️ AGUARDANDO HÁ <b data-public-wait="${escapeHtml(o.requested_at||o.created_at)}">${elapsed(o.requested_at||o.created_at)}</b></span>`:''}</div>`:''}
   <h3>${escapeHtml(o.type)}</h3>
   <div class="op-address">📍 ${escapeHtml(o.address)} ${o.lat!=null&&o.lng!=null?`<a class="gps-route-link" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(o.lat+','+o.lng)}">🧭 ROTA GPS DO SOLICITANTE</a>`:''}</div>
   <div class="op-meta">
     <span class="status-chip ${statusClass(o.status)}">${escapeHtml(statusLabel(o.status))}</span>
     <span>🚒 ${escapeHtml(o.team||'Aguardando equipe')}</span>
     <span>🚑 ${escapeHtml(o.vehicle_prefix||'Sem viatura')}</span>
   </div>
   <div class="op-counters"><span>⏱️ <b data-elapsed="${escapeHtml(timerStart)}">${elapsed(timerStart)}</b></span><span>👤 ${Number(o.patient_count||0)} paciente(s)</span><span>💬 ${Number(o.message_count||0)}</span></div>
   ${o.details?`<p class="op-details">${escapeHtml(o.details)}</p>`:''}
   <div class="op-actions">
     <a class="button-link ${publicUnattended?'ack-public-button':''}" href="ocorrencia.php?id=${o.id}">${publicUnattended?'🚨 ABRIR E DAR CIÊNCIA':'Central da ocorrência'}</a>
     <a class="button-link secondary-link" href="aph.php?occurrence_id=${o.id}">+ Paciente / APH</a>
   </div>
   ${!closed?`<div class="dispatch-mini">
      <select data-team="${o.id}">${teamsOptions(o.team||'')}</select>
      <select data-vehicle="${o.id}">${vehiclesOptions(o.vehicle_id||'')}</select>
      <button class="primary" data-dispatch="${o.id}">${o.team?'Atualizar despacho':'Despachar'}</button>
   </div>`:''}
 </article>`;
}

function applyFilters(){
 const q=(document.getElementById('occSearch')?.value||'').trim().toLowerCase();
 const sf=document.getElementById('statusFilter')?.value||'',pf=document.getElementById('priorityFilter')?.value||'';
 document.querySelectorAll('.op-card').forEach(el=>{
   const show=(!q||el.dataset.search.includes(q))&&(!sf||el.dataset.status===sf)&&(!pf||el.dataset.priority===pf);
   el.hidden=!show;
 });
}
['occSearch','statusFilter','priorityFilter'].forEach(id=>document.getElementById(id)?.addEventListener(id==='occSearch'?'input':'change',applyFilters));

function renderTeams(teams){
 const box=document.getElementById('teamPanel');if(!box)return;
 box.innerHTML=teams.length?teams.map(t=>`<div class="side-item ${t.operational_status==='EMPENHADA'?'engaged':'available'}"><div><strong>${escapeHtml(t.name)}</strong><small>${Number(t.member_count||0)} integrantes</small></div><span>${t.operational_status==='EMPENHADA'?'🟠 EMPENHADA':'🟢 DISPONÍVEL'}</span>${t.occurrence?`<small>${escapeHtml(t.occurrence.protocol)} · ${escapeHtml(statusLabel(t.occurrence.status))}</small>`:''}${t.presence?`<small>📍 GPS ${escapeHtml(t.presence.last_seen)}</small>`:''}</div>`).join(''):'<p class="muted">Nenhuma equipe ativa.</p>';
}
function renderVehicles(vehicles){
 const box=document.getElementById('vehiclePanel');if(!box)return;
 box.innerHTML=vehicles.length?vehicles.map(v=>`<div class="side-item vehicle-${String(v.status).toLowerCase()}"><div><strong>${escapeHtml(v.prefix)}</strong><small>${escapeHtml(v.description)}</small></div><span>${escapeHtml(v.status)}</span></div>`).join(''):'<p class="muted">Nenhuma viatura cadastrada.</p>';
}
function setStats(s){
 const map={statOpen:s.open,statCritical:s.critical,statTeamsAvailable:s.teams_available,statTeamsEngaged:s.teams_engaged,statToday:s.today,statPatients:s.patients_today};
 Object.entries(map).forEach(([id,v])=>{const el=document.getElementById(id);if(el)el.textContent=v??0;});
}

function beep(critical=false){
 if(!alertsEnabled)return;
 try{
   const ctx=new (window.AudioContext||window.webkitAudioContext)();
   const gain=ctx.createGain();gain.connect(ctx.destination);gain.gain.value=critical?.16:.11;
   const tones=critical?[920,690,920,690]:[760,610];
   tones.forEach((freq,i)=>{
     const osc=ctx.createOscillator();osc.type='square';osc.frequency.value=freq;osc.connect(gain);
     const start=ctx.currentTime+i*.22;osc.start(start);osc.stop(start+.16);
   });
   setTimeout(()=>ctx.close(),tones.length*240+200);
 }catch(e){}
}
function repeatingPublicAlarm(){
 if(!alertsEnabled||!unackPublicIds.size)return;
 beep(true);
 setTimeout(()=>{if(alertsEnabled&&unackPublicIds.size)beep(true);},900);
}
function updateAlarmState(){
 const el=document.getElementById('alarmState'),btn=document.getElementById('enableAlerts');
 if(el){
   if(!alertsEnabled){el.textContent='ALARME DESATIVADO';el.className='pill alarm-state alarm-off';}
   else if(unackPublicIds.size){el.textContent=`🚨 ${unackPublicIds.size} PÚBLICA(S) AGUARDANDO`;el.className='pill alarm-state alarm-ringing';}
   else{el.textContent='🔔 ALARME ATIVO';el.className='pill alarm-state alarm-on';}
 }
 if(btn){
   btn.textContent=alertsEnabled?'🔔 ALARME ATIVO':'🔔 ATIVAR ALARME DA CENTRAL';
   btn.classList.toggle('active',alertsEnabled);
 }
}
function updatePublicAlarm(newItems){
 unackPublicIds=new Set(newItems.filter(o=>o.source==='PUBLICO'&&!o.central_acknowledged_at&&o.status!=='ENCERRADA').map(o=>String(o.id)));
 updateAlarmState();
 if(publicAlarmTimer){clearInterval(publicAlarmTimer);publicAlarmTimer=null;}
 if(alertsEnabled&&unackPublicIds.size){
   repeatingPublicAlarm();
   publicAlarmTimer=setInterval(repeatingPublicAlarm,8000);
 }
}
function desktopNotify(o){
 if(!alertsEnabled||Notification.permission!=='granted')return;
 try{new Notification(`${o.priority==='CRITICA'?'URGENTE · ':''}${o.protocol}`,{body:`${o.type} · ${o.address}`});}catch(e){}
}
function pushCentralAlertItem(o){
 const box=document.getElementById('centralAlertsBox');
 if(!box||o.central_acknowledged_at)return;
 const empty=box.querySelector('.muted');
 if(empty && empty.textContent.includes('Nenhuma solicitação pública')) empty.remove();

 const existing=box.querySelector(`[data-alert-id="${o.id}"]`);
 if(existing) existing.remove();

 const level=String(o.occurrence_level||'NAO_CLASSIFICADO').replaceAll('_',' ');
 const item=document.createElement('article');
 item.className=`central-alert-item priority-${String(o.priority||'MEDIA').toLowerCase()}`;
 item.dataset.alertId=String(o.id);
 item.innerHTML=`<div class="central-alert-item-top">
   <strong>🚨 NOVA SOLICITAÇÃO PÚBLICA — AGUARDANDO CENTRAL</strong>
   <span class="central-alert-time blink-timer">⏱️ <b data-public-wait="${escapeHtml(o.requested_at||o.created_at)}">${elapsed(o.requested_at||o.created_at)}</b></span>
 </div>
 <div class="central-alert-item-body">
   <p><strong>${escapeHtml(o.type||'OCORRÊNCIA')}</strong></p>
   <p>📍 ${escapeHtml(o.address||'LOCAL NÃO INFORMADO')}</p>
   <p>👤 ${escapeHtml(o.requester_name||'NÃO INFORMADO')} ${o.requester_phone?`· ☎ ${escapeHtml(o.requester_phone)}`:''}</p>
   <p><span class="level-badge">${escapeHtml(level)}</span> <span class="priority-badge priority-${escapeHtml(o.priority)}">${escapeHtml(o.priority||'MEDIA')}</span></p>
   <div class="central-alert-item-actions">
     <a class="button-link ack-public-button" href="ocorrencia.php?id=${o.id}">🚨 ABRIR E DAR CIÊNCIA</a>
     <button type="button" class="secondary-link" data-remove-central-alert="${o.id}">Dispensar aviso</button>
   </div>
 </div>`;
 box.prepend(item);

 if(box.children.length>8){
   [...box.children].slice(8).forEach(el=>el.remove());
 }
 item.querySelector('[data-remove-central-alert]')?.addEventListener('click',()=>item.remove());
}

function showPublicAlert(o){
 const overlay=document.createElement('div');
 overlay.className='public-alert-overlay';
 const level=String(o.occurrence_level||'NAO_CLASSIFICADO').replaceAll('_',' ');
 overlay.innerHTML=`<div class="public-alert-box"><div class="public-alert-head">🚨 NOVA SOLICITAÇÃO PÚBLICA — AÇÃO NECESSÁRIA</div><div class="public-alert-body"><h2>${escapeHtml(o.protocol)}</h2><div class="public-modal-timer blink-timer">⏱️ AGUARDANDO HÁ <b data-public-wait="${escapeHtml(o.requested_at||o.created_at)}">${elapsed(o.requested_at||o.created_at)}</b></div><p><strong>${escapeHtml(o.type)}</strong></p><p>📍 ${escapeHtml(o.address)}</p><p><span class="level-badge">${escapeHtml(level)}</span> <span class="priority-badge priority-${escapeHtml(o.priority)}">${escapeHtml(o.priority)}</span></p><p><strong>SOLICITANTE:</strong> ${escapeHtml(o.requester_name||'NÃO INFORMADO')} · ${escapeHtml(o.requester_phone||'')}</p><div class="public-alert-actions"><a class="button-link ack-public-button" href="ocorrencia.php?id=${o.id}">🚨 ABRIR E DAR CIÊNCIA</a><button type="button" class="close-public-alert">FECHAR SÓ ESTE AVISO</button></div><p class="public-alarm-note">O alarme continuará até alguém da Central abrir esta ocorrência.</p></div></div>`;

 const strip=document.createElement('div');
 strip.className='central-public-strip';
 strip.innerHTML=`<div class="central-public-strip-inner"><strong>🚨 PÚBLICA AGUARDANDO:</strong> <span>${escapeHtml(o.protocol)} · ${escapeHtml(o.type)} · ${escapeHtml(o.address)}</span> <span class="blink-timer">⏱️ <b data-public-wait="${escapeHtml(o.requested_at||o.created_at)}">${elapsed(o.requested_at||o.created_at)}</b></span> <a class="button-link ack-public-button" href="ocorrencia.php?id=${o.id}">ABRIR E DAR CIÊNCIA</a> <button type="button" class="close-strip">OCULTAR FAIXA</button></div>`;

 document.body.appendChild(strip);
 document.body.appendChild(overlay);
 pushCentralAlertItem(o);

 overlay.querySelector('.close-public-alert')?.addEventListener('click',()=>overlay.remove());
 strip.querySelector('.close-strip')?.addEventListener('click',()=>strip.remove());

 setTimeout(()=>{ if(document.body.contains(strip)) strip.classList.add('show'); },50);
 setTimeout(()=>{ if(document.body.contains(strip)) strip.remove(); },25000);

 beep(o.priority==='CRITICA');
 desktopNotify(o);
}

function hydratePublicAlertHistory(newItems){
 const publics=newItems
   .filter(o=>o.source==='PUBLICO'&&!o.central_acknowledged_at&&o.status!=='ENCERRADA')
   .sort((a,b)=>Number(b.id)-Number(a.id))
   .slice(0,5);

 const box=document.getElementById('centralAlertsBox');
 if(!box) return;
 if(!publics.length){
   box.innerHTML='<p class="muted">Nenhuma solicitação pública nova no momento.</p>';
   return;
 }
 box.innerHTML='';
 publics.reverse().forEach(o=>pushCentralAlertItem(o));
}

function checkPublicAlerts(newItems){
 const publics=newItems.filter(o=>o.source==='PUBLICO'&&!o.central_acknowledged_at&&o.status!=='ENCERRADA').sort((a,b)=>Number(a.id)-Number(b.id));
 hydratePublicAlertHistory(newItems);
 if(!publics.length)return;
 const seen=Number(localStorage.getItem('guacas-last-public-alert-id')||0);
 const pending=publics.filter(o=>Number(o.id)>seen);
 if(pending.length){
   pending.forEach(o=>showPublicAlert(o));
   localStorage.setItem('guacas-last-public-alert-id',String(pending[pending.length-1].id));
 }
}
function detectAlerts(newItems){
 if(firstLoad){newItems.forEach(o=>lastSnapshot.set(String(o.id),o.updated_at));firstLoad=false;return;}
 for(const o of newItems){
   const prev=lastSnapshot.get(String(o.id));
   if(!prev || prev!==o.updated_at){
     if(!prev || o.priority==='CRITICA'){beep(o.priority==='CRITICA');desktopNotify(o);}
   }
   lastSnapshot.set(String(o.id),o.updated_at);
 }
}

async function load(){
 try{
   const j=await apiFetch('api/dashboard.php');
   updatePublicAlarm(j.items||[]);
   checkPublicAlerts(j.items||[]);
   detectAlerts(j.items||[]);
   items=j.items||[];
   list.innerHTML=items.length?items.map(card).join(''):'<div class="card">Nenhuma ocorrência registrada.</div>';
   setStats(j.stats||{});renderTeams(j.teams||[]);renderVehicles(j.vehicles||[]);
   window.SICOBC.teams=j.teams||window.SICOBC.teams;window.SICOBC.vehicles=j.vehicles||window.SICOBC.vehicles;
   applyFilters();
 }catch(e){if(!list.innerHTML)list.innerHTML=`<div class="alert error">${escapeHtml(e.message)}</div>`;}
}

list?.addEventListener('click',async e=>{
 const b=e.target.closest('[data-dispatch]');if(!b)return;
 const id=Number(b.dataset.dispatch),team=document.querySelector(`[data-team="${id}"]`)?.value||'',vehicle_id=Number(document.querySelector(`[data-vehicle="${id}"]`)?.value||0);
 if(!team){alert('Escolha uma equipe.');return;}
 b.disabled=true;
 try{await apiFetch('api/occurrence_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,action:'dispatch',team,vehicle_id})});await load();}
 catch(err){alert(err.message);}finally{b.disabled=false;}
});

form?.addEventListener('submit',async e=>{
 e.preventDefault();const data=Object.fromEntries(new FormData(form).entries());
 try{await apiFetch('api/occurrences.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});form.reset();fillTypes();beep(data.priority==='CRITICA');await load();}
 catch(err){alert(err.message);}
});

document.getElementById('enableAlerts')?.addEventListener('click',async e=>{
 alertsEnabled=!alertsEnabled;
 localStorage.setItem('guacas-central-alarm-enabled',alertsEnabled?'1':'0');
 if('Notification'in window&&Notification.permission==='default'&&alertsEnabled){try{await Notification.requestPermission();}catch(_){}}
 updateAlarmState();
 if(alertsEnabled){
   beep(false);
   if(unackPublicIds.size){
     repeatingPublicAlarm();
     if(publicAlarmTimer)clearInterval(publicAlarmTimer);
     publicAlarmTimer=setInterval(repeatingPublicAlarm,8000);
   }
 }else if(publicAlarmTimer){
   clearInterval(publicAlarmTimer);publicAlarmTimer=null;
 }
});
setInterval(()=>{
 document.querySelectorAll('[data-elapsed]').forEach(el=>el.textContent=elapsed(el.dataset.elapsed));
 document.querySelectorAll('[data-public-wait]').forEach(el=>el.textContent=elapsed(el.dataset.publicWait));
},1000);
updateAlarmState();
load();setInterval(()=>{if(navigator.onLine)load();},Math.max(2,Number(window.SICOBC?.refreshSeconds||3))*1000);
})();
