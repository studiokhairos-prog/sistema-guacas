(() => {
const DB_NAME='guacas-offline-v3',STORE_QUEUE='queue',STORE_CACHE='occurrences';
const pendingEl=document.getElementById('pending'),list=document.getElementById('occurrences'),gpsState=document.getElementById('gpsState');
let watchId=null,lastIds=new Set(),firstLoad=true;

function idb(){return new Promise((resolve,reject)=>{const r=indexedDB.open(DB_NAME,1);r.onupgradeneeded=()=>{const d=r.result;if(!d.objectStoreNames.contains(STORE_QUEUE))d.createObjectStore(STORE_QUEUE,{keyPath:'uuid'});if(!d.objectStoreNames.contains(STORE_CACHE))d.createObjectStore(STORE_CACHE,{keyPath:'id'});};r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
async function all(store){const d=await idb();return new Promise((res,rej)=>{const tx=d.transaction(store,'readonly'),r=tx.objectStore(store).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
async function put(store,val){const d=await idb();return new Promise((res,rej)=>{const tx=d.transaction(store,'readwrite');tx.objectStore(store).put(val);tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error);});}
async function del(store,key){const d=await idb();return new Promise((res,rej)=>{const tx=d.transaction(store,'readwrite');tx.objectStore(store).delete(key);tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error);});}
async function replaceCache(items){const d=await idb();return new Promise((res,rej)=>{const tx=d.transaction(STORE_CACHE,'readwrite'),s=tx.objectStore(STORE_CACHE);s.clear();items.forEach(x=>s.put(x));tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error);});}
function uuid(){return crypto.randomUUID?crypto.randomUUID():'op-'+Date.now()+'-'+Math.random().toString(16).slice(2);}
function deviceId(){let id=localStorage.getItem('guacas-device');if(!id){id='dev-'+uuid();localStorage.setItem('guacas-device',id);}return id;}
function elapsed(s){const d=new Date(s);if(Number.isNaN(d.getTime()))return'—';const sec=Math.max(0,(Date.now()-d.getTime())/1000),h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60);return h?`${h}h ${m}min`:`${m} min`;}
function beep(){try{const c=new(window.AudioContext||window.webkitAudioContext)(),o=c.createOscillator(),g=c.createGain();o.connect(g);g.connect(c.destination);o.frequency.value=780;g.gain.value=.07;o.start();setTimeout(()=>{o.stop();c.close();},300);}catch(e){}}
function statusButtons(o){
 if(o.status==='ENCERRADA')return'';
 const order=['A_CAMINHO','NO_LOCAL','EM_ATENDIMENTO','RETORNANDO','ENCERRADA'];
 return order.map(s=>`<button data-id="${o.id}" data-status="${s}" class="${s==='ENCERRADA'?'danger':''}">${s==='A_CAMINHO'?'🚒 A CAMINHO':s==='NO_LOCAL'?'📍 CHEGUEI AO LOCAL':s==='EM_ATENDIMENTO'?'🩺 EM ATENDIMENTO':s==='RETORNANDO'?'↩️ RETORNANDO':'✅ FINALIZAR'}</button>`).join('');
}
function card(o){
 const assigned=String(o.team||'').trim(),myTeam=String(window.SICOBC?.team||'').trim(),canClaim=!assigned&&myTeam;
 return `<article class="field-occ status-${String(o.status).toLowerCase().replaceAll('_','-')} ${o.priority==='CRITICA'?'critical-pulse':''}">
 <div class="op-card-top"><div><strong>${escapeHtml(o.protocol)}</strong><br><span class="level-badge">${escapeHtml(String(o.occurrence_level||'NAO_CLASSIFICADO').replaceAll('_',' '))}</span></div><span class="priority-badge priority-${o.priority}">${escapeHtml(o.priority)}</span></div>
 ${o.source==='PUBLICO'?'<div class="public-source">🚨 Solicitação pública</div>':''}
 <div class="small">${escapeHtml(o.nature||'')}</div><h2>${escapeHtml(o.type)}</h2>
 <div class="field-address">📍 ${escapeHtml(o.address)} ${o.lat!=null&&o.lng!=null?`<a class="gps-route-link" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(o.lat+','+o.lng)}">🧭 ABRIR ROTA GPS</a>`:''}</div>
 <div class="op-meta"><span>${escapeHtml(String(o.status).replaceAll('_',' '))}</span><span>🚒 ${escapeHtml(o.team||'Aguardando equipe')}</span><span>🚑 ${escapeHtml(o.vehicle_prefix||'Sem viatura')}</span></div>
 <div class="op-counters"><span>⏱️ ${elapsed(o.requested_at||o.created_at)}</span><span>👤 ${Number(o.patient_count||0)} paciente(s)</span><span>💬 ${Number(o.message_count||0)}</span></div>
 ${o.details?`<p>${escapeHtml(o.details)}</p>`:''}
 ${canClaim?`<button class="primary claim-occ" data-claim="${o.id}">🚒 ASSUMIR OCORRÊNCIA</button>`:''}
 <div class="field-main-actions"><a href="ocorrencia.php?id=${o.id}" class="button-link">💬 Ocorrência / mensagens</a><a href="aph.php?occurrence_id=${o.id}" class="button-link secondary-link">👤 + Paciente / APH</a></div>
 ${assigned===myTeam||window.SICOBC.role==='ADMIN'?`<div class="field-status-actions">${statusButtons(o)}</div>`:''}
 </article>`;
}
async function render(items){list.innerHTML=items.length?items.map(card).join(''):'<div class="card">Nenhuma ocorrência disponível.</div>';}
async function pendingCount(){const q=await all(STORE_QUEUE);if(pendingEl)pendingEl.textContent=`${q.length} pendente${q.length===1?'':'s'}`;return q.length;}
function detectNew(items){const ids=new Set(items.filter(o=>o.status!=='ENCERRADA').map(o=>String(o.id)));if(!firstLoad){for(const id of ids)if(!lastIds.has(id)){beep();break;}}lastIds=ids;firstLoad=false;}
async function load(){if(navigator.onLine){try{const j=await apiFetch('api/occurrences.php');detectNew(j.items||[]);await replaceCache(j.items||[]);await render(j.items||[]);return;}catch(e){}}await render(await all(STORE_CACHE));}
async function queueStatus(occurrence_id,status){
 let note='';if(status==='ENCERRADA'||status==='EM_ATENDIMENTO')note=prompt('Observação do status (opcional):','')??'';
 const op={uuid:uuid(),occurrence_id:Number(occurrence_id),status,note,device_id:deviceId(),client_time:new Date().toISOString()};
 await put(STORE_QUEUE,op);
 const cached=await all(STORE_CACHE),occ=cached.find(x=>Number(x.id)===Number(occurrence_id));if(occ){occ.status=status;occ.updated_at=op.client_time;await put(STORE_CACHE,occ);await render(await all(STORE_CACHE));}
 await pendingCount();if(navigator.onLine)await sync();
}
async function sync(){
 const q=await all(STORE_QUEUE);if(!q.length)return;
 pendingEl.textContent='SINCRONIZANDO';pendingEl.className='pill syncing';
 try{const j=await apiFetch('api/sync.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({operations:q})});for(const r of j.results||[])if(r.ok)await del(STORE_QUEUE,r.uuid);await pendingCount();pendingEl.className='pill';await load();}
 catch(e){await pendingCount();pendingEl.className='pill offline';}
}
async function sendPosition(pos){
 const payload={lat:pos.coords.latitude,lng:pos.coords.longitude,accuracy:pos.coords.accuracy};
 localStorage.setItem('guacas-last-position',JSON.stringify(payload));
 if(!navigator.onLine){gpsState.textContent='GPS ativo · aguardando internet';return;}
 try{await apiFetch('api/presence.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});gpsState.textContent=`GPS ativo · ±${Math.round(pos.coords.accuracy)} m`;}catch(e){gpsState.textContent='GPS ativo · envio pendente';}
}
function startGps(){
 if(!navigator.geolocation){gpsState.textContent='GPS não disponível';return;}
 if(watchId!==null)return;
 document.getElementById('gpsToggle').textContent='📍 GPS ativo';
 watchId=navigator.geolocation.watchPosition(sendPosition,()=>{gpsState.textContent='GPS sem permissão/sinal';},{enableHighAccuracy:true,maximumAge:15000,timeout:15000});
}
function stopGps(){if(watchId!==null)navigator.geolocation.clearWatch(watchId);watchId=null;gpsState.textContent='GPS desligado';document.getElementById('gpsToggle').textContent='📍 Ativar GPS';}
document.getElementById('gpsToggle')?.addEventListener('click',()=>watchId===null?startGps():stopGps());
list.addEventListener('click',async e=>{
 const claim=e.target.closest('[data-claim]');if(claim){claim.disabled=true;try{await apiFetch('api/claim_occurrence.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(claim.dataset.claim)})});await load();}catch(err){alert(err.message);}finally{claim.disabled=false;}return;}
 const b=e.target.closest('[data-status]');if(b){if(b.dataset.status==='ENCERRADA'&&!confirm('Finalizar esta ocorrência?'))return;queueStatus(b.dataset.id,b.dataset.status);}
});
document.getElementById('refresh')?.addEventListener('click',load);document.getElementById('syncNow')?.addEventListener('click',sync);
window.addEventListener('sicobc-online',async()=>{const p=localStorage.getItem('guacas-last-position');if(p){try{await apiFetch('api/presence.php',{method:'POST',headers:{'Content-Type':'application/json'},body:p});}catch(e){}}await sync();await load();});
pendingCount();load();if(navigator.onLine)sync();setInterval(()=>{if(navigator.onLine)load();},4000);
})();
