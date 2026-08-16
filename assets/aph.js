(() => {
  const form=document.getElementById('aphForm');
  if(!form) return;
  const saveBtn=document.getElementById('saveAph'), state=document.getElementById('aphSaveState'), saveText=document.getElementById('saveText');
  const DB='sicobc-aph-offline-v1', STORE='aph_queue';

  function openDb(){return new Promise((res,rej)=>{const r=indexedDB.open(DB,1);r.onupgradeneeded=()=>{if(!r.result.objectStoreNames.contains(STORE))r.result.createObjectStore(STORE,{keyPath:'client_uuid'});};r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
  async function qPut(v){const d=await openDb();return new Promise((res,rej)=>{const t=d.transaction(STORE,'readwrite');t.objectStore(STORE).put(v);t.oncomplete=()=>res();t.onerror=()=>rej(t.error);});}
  async function qAll(){const d=await openDb();return new Promise((res,rej)=>{const t=d.transaction(STORE,'readonly');const r=t.objectStore(STORE).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
  async function qDel(k){const d=await openDb();return new Promise((res,rej)=>{const t=d.transaction(STORE,'readwrite');t.objectStore(STORE).delete(k);t.oncomplete=()=>res();t.onerror=()=>rej(t.error);});}

  const clientUuid=document.getElementById('client_uuid').value;
  const localKey='sicobc-aph-draft-'+clientUuid;

  function setState(txt,cls='pill'){if(state){state.textContent=txt;state.className=cls;}}
  function data(){
    const fd=new FormData(form), d={};
    for(const [k,v] of fd.entries()) d[k]=v;
    form.querySelectorAll('input[type=checkbox]').forEach(x=>d[x.name]=x.checked?'1':'');
    return d;
  }
  function payload(){
    return {id:Number(document.getElementById('aph_id').value||0),client_uuid:clientUuid,occurrence_id:Number(document.getElementById('occurrence_id')?.value||0),data:data()};
  }
  function localSave(){
    if(window.SICOBC?.readonly) return;
    try{localStorage.setItem(localKey,JSON.stringify({saved_at:new Date().toISOString(),data:data()})); if(saveText) saveText.textContent='Rascunho protegido neste aparelho.';}catch(e){}
  }
  function restoreLocal(){
    if(window.SICOBC?.aphId) return;
    try{
      const raw=localStorage.getItem(localKey); if(!raw)return;
      const obj=JSON.parse(raw); const d=obj.data||{};
      for(const [k,v] of Object.entries(d)){
        const el=form.elements.namedItem(k); if(!el)continue;
        if(el.type==='checkbox')el.checked=v==='1'; else el.value=v;
      }
    }catch(e){}
  }

  const occurrenceSelect=form.elements.namedItem('occurrence_id');
  function applyOccurrenceDefaults(){
    if(!occurrenceSelect || !occurrenceSelect.value) return;
    const opt=occurrenceSelect.options[occurrenceSelect.selectedIndex];
    if(!opt) return;
    const serviceType=form.elements.namedItem('service_type');
    const sceneAddress=form.elements.namedItem('scene_address');
    const unitTeam=form.elements.namedItem('unit_team');
    const patientName=form.elements.namedItem('patient_full_name');
    const label=[opt.dataset.nature||'',opt.dataset.type||''].filter(Boolean).join(' / ');
    if(serviceType && !String(serviceType.value||'').trim() && label) serviceType.value=label;
    if(sceneAddress && !String(sceneAddress.value||'').trim() && opt.dataset.address) sceneAddress.value=opt.dataset.address;
    if(unitTeam && !String(unitTeam.value||'').trim() && opt.dataset.team) unitTeam.value=opt.dataset.team;
    if(patientName && !String(patientName.value||'').trim() && opt.dataset.patient) patientName.value=opt.dataset.patient;
    localSave();
  }
  occurrenceSelect?.addEventListener('change',applyOccurrenceDefaults);

  async function send(p){
    const j=await apiFetch('api/aph_save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)});
    document.getElementById('aph_id').value=j.id;
    window.SICOBC.aphId=j.id;
    setState('✅ SALVO','pill online');
    if(saveText) saveText.textContent='Ficha salva no servidor em '+new Date().toLocaleTimeString();
    localStorage.removeItem(localKey);
    const url=new URL(location.href); url.searchParams.set('id',j.id); url.searchParams.delete('occurrence_id'); history.replaceState({},'',url);
    return j;
  }

  async function save(){
    if(window.SICOBC?.readonly)return;
    localSave();
    const p=payload();
    if(!String(p.data.patient_full_name||'').trim()){alert('Informe o nome completo do paciente.');return;}
    if(!navigator.onLine){
      await qPut(p); setState('🔴 OFFLINE — aguardando sincronização','pill offline'); if(saveText)saveText.textContent='Rascunho salvo localmente. Será enviado quando a internet voltar.'; return;
    }
    setState('🟡 SINCRONIZANDO','pill syncing');
    const wasNew=!Number(document.getElementById('aph_id').value||0);
    try{
      const j=await send(p);
      if(wasNew) location.href='aph.php?id='+j.id;
    }catch(e){await qPut(p);setState('⚠️ PENDENTE','pill offline');if(saveText)saveText.textContent='Não foi possível enviar agora; o rascunho ficou na fila local.';}
  }

  async function syncQueue(){
    if(!navigator.onLine)return;
    const items=await qAll(); if(!items.length)return;
    setState('🟡 SINCRONIZANDO','pill syncing');
    let lastId=0;
    for(const p of items){
      try{const j=await send(p);lastId=j.id;await qDel(p.client_uuid);}catch(e){setState('⚠️ PENDENTE','pill offline');return;}
    }
    if(!Number(document.getElementById('aph_id').value||0) && lastId) location.href='aph.php?id='+lastId;
  }

  let timer;
  form.addEventListener('input',()=>{localSave();clearTimeout(timer);if(!navigator.onLine){timer=setTimeout(async()=>{try{await qPut(payload());setState('🔴 OFFLINE — salvo localmente','pill offline');}catch(e){}},700);}});
  saveBtn?.addEventListener('click',save);
  window.addEventListener('sicobc-online',syncQueue);

  // Glasgow
  const profile=document.getElementById('gcs_profile'), verbal=document.getElementById('gcs_verbal'), motor=document.getElementById('gcs_motor'), eye=document.getElementById('gcs_eye'), total=document.getElementById('gcs_total'), view=document.getElementById('gcs_total_view');
  const verbalSets={
    ADULTO:[['',''],['5','5 — Orientada'],['4','4 — Confusa'],['3','3 — Palavras inapropriadas'],['2','2 — Sons incompreensíveis'],['1','1 — Ausente']],
    CRIANCA:[['',''],['5','5 — Palavras apropriadas/orientada'],['4','4 — Confusa'],['3','3 — Palavras inapropriadas'],['2','2 — Palavras incompreensíveis/sons inespecíficos'],['1','1 — Ausente']],
    BEBE:[['',''],['5','5 — Murmura ou balbucia'],['4','4 — Inquieta/irritada/chorosa'],['3','3 — Chora em resposta à dor'],['2','2 — Geme em resposta à dor'],['1','1 — Ausente']]
  };
  const motorSets={
    ADULTO:[['',''],['6','6 — Obedece a comandos'],['5','5 — Localiza estímulo doloroso'],['4','4 — Retira ao estímulo doloroso'],['3','3 — Flexão anormal'],['2','2 — Extensão anormal'],['1','1 — Ausente']],
    CRIANCA:[['',''],['6','6 — Obedece comando verbal simples'],['5','5 — Localiza estímulo doloroso'],['4','4 — Retira ao estímulo doloroso'],['3','3 — Flexão anormal'],['2','2 — Extensão anormal'],['1','1 — Ausente']],
    BEBE:[['',''],['6','6 — Move-se espontânea/intencionalmente'],['5','5 — Retira o membro ao toque'],['4','4 — Retira ao estímulo doloroso'],['3','3 — Flexão anormal'],['2','2 — Extensão anormal'],['1','1 — Ausente']]
  };
  function fillSelect(el,items,keep){el.innerHTML=items.map(([v,l])=>`<option value="${v}" ${String(keep)===v?'selected':''}>${l}</option>`).join('');}
  function gcsInit(){
    if(!profile||!verbal||!motor)return;
    const pv=verbal.dataset.value||verbal.value, pm=motor.dataset.value||motor.value, key=profile.value||'ADULTO';
    fillSelect(verbal,verbalSets[key],pv);fillSelect(motor,motorSets[key],pm);calcGcs();
    verbal.dataset.value='';motor.dataset.value='';
  }
  function calcGcs(){
    const vals=[Number(eye?.value||0),Number(verbal?.value||0),Number(motor?.value||0)];
    const ok=vals.every(x=>x>0); const n=ok?vals.reduce((a,b)=>a+b,0):'';
    if(total)total.value=n;if(view)view.textContent=n||'—';
  }
  profile?.addEventListener('change',()=>{verbal.dataset.value='';motor.dataset.value='';gcsInit();});
  [eye,verbal,motor].forEach(x=>x?.addEventListener('change',calcGcs));


  // Página extra de observações e banco de frases documentais
  const obsPage=document.getElementById('reportObservationPage'),obsBuilder=document.getElementById('observationBuilder'),obsText=document.getElementById('reportAdditionalObservations');
  function syncObsPage(){if(!obsPage||!obsBuilder)return;const on=obsPage.value==='SIM';obsBuilder.hidden=!on;if(obsText)obsText.required=false;}
  obsPage?.addEventListener('change',()=>{if(obsPage.value==='NAO_HA'&&obsText&&obsText.value.trim()&&!confirm('A PÁGINA EXTRA SERÁ DESATIVADA. LIMPAR O TEXTO DE OBSERVAÇÕES COMPLEMENTARES?')){obsPage.value='SIM';return;}if(obsPage.value==='NAO_HA'&&obsText)obsText.value='';syncObsPage();localSave();});
  document.querySelectorAll('[data-phrase]').forEach(btn=>btn.addEventListener('click',()=>{if(!obsText)return;const phrase=String(btn.dataset.phrase||'').trim();const current=obsText.value.trim();obsText.value=current?(current+(current.endsWith('.')?' ':' ') + phrase):phrase;obsText.dispatchEvent(new Event('input',{bubbles:true}));obsText.focus();}));

  restoreLocal(); applyOccurrenceDefaults(); gcsInit(); syncObsPage(); syncQueue();
})();
