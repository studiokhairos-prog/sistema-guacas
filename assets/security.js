(() => {
let idleMinutes=20,lastHuman=Date.now(),lastBeat=0,warning=null;
const events=['pointerdown','keydown','touchstart','scroll'];

async function beat(force=false){
 const now=Date.now();
 if(!force && now-lastBeat<30000)return;
 lastBeat=now;
 try{
   const r=await fetch('api/heartbeat.php',{method:'POST',credentials:'same-origin',cache:'no-store'});
   if(r.status===401){location.href='login.php?expired=1';return;}
   const j=await r.json();
   if(j.idle_minutes)idleMinutes=Number(j.idle_minutes)||20;
 }catch(e){}
}

function human(){
 lastHuman=Date.now();
 if(warning){warning.remove();warning=null;}
 beat(false);
}
events.forEach(ev=>addEventListener(ev,human,{passive:true}));

function showWarning(seconds){
 if(warning)return;
 warning=document.createElement('div');
 warning.className='security-idle-warning';
 warning.innerHTML=`<strong>🔒 Sessão será bloqueada em breve</strong><span>Inatividade detectada. Restam aproximadamente ${Math.max(1,Math.ceil(seconds/60))} minuto(s).</span><button type="button">Continuar sessão</button>`;
 document.body.appendChild(warning);
 warning.querySelector('button').addEventListener('click',()=>{lastHuman=Date.now();warning.remove();warning=null;beat(true);});
}

setInterval(()=>{
 const elapsed=(Date.now()-lastHuman)/1000;
 const limit=idleMinutes*60;
 if(elapsed>=limit){location.href='logout.php?idle=1';return;}
 if(limit-elapsed<=120)showWarning(limit-elapsed);
},5000);

(async()=>{
 try{
   const r=await fetch('api/security_status.php',{credentials:'same-origin',cache:'no-store'});
   if(r.ok){const j=await r.json();idleMinutes=Number(j.idle_minutes)||20;}
 }catch(e){}
 beat(true);
})();
})();
