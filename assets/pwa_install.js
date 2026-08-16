(() => {
let deferredPrompt=null;
const buttons=[...document.querySelectorAll('[data-install-pwa]')];

function standalone(){
 return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone===true;
}
function isIOS(){
 return /iphone|ipad|ipod/i.test(navigator.userAgent);
}
function update(){
 buttons.forEach(b=>{
   if(standalone()){
     b.textContent='✅ APP INSTALADO / ABERTO COMO APP';
     b.disabled=true;
     b.classList.add('installed');
   }else if(deferredPrompt){
     b.hidden=false;
   }else{
     b.hidden=false;
   }
 });
}
function iosHelp(){
 const box=document.createElement('div');
 box.className='pwa-help-overlay';
 box.innerHTML=`<div class="pwa-help-box"><h2>📲 Instalar no iPhone/iPad</h2><p>1. Toque no botão <strong>Compartilhar</strong> do Safari.</p><p>2. Escolha <strong>Adicionar à Tela de Início</strong>.</p><p>3. Confirme em <strong>Adicionar</strong>.</p><button type="button">Entendi</button></div>`;
 document.body.appendChild(box);
 box.querySelector('button').onclick=()=>box.remove();
}

window.addEventListener('beforeinstallprompt',e=>{
 e.preventDefault();deferredPrompt=e;update();
});
window.addEventListener('appinstalled',()=>{deferredPrompt=null;update();});

buttons.forEach(b=>b.addEventListener('click',async()=>{
 if(standalone())return;
 if(deferredPrompt){
   deferredPrompt.prompt();
   try{await deferredPrompt.userChoice;}catch(e){}
   deferredPrompt=null;update();return;
 }
 if(isIOS()){iosHelp();return;}
 const box=document.createElement('div');
 box.className='pwa-help-overlay';
 box.innerHTML=`<div class="pwa-help-box"><h2>📲 Instalar aplicativo</h2><p>Abra o menu do navegador e escolha <strong>Instalar aplicativo</strong> ou <strong>Adicionar à tela inicial</strong>.</p><p>Se a opção ainda não aparecer, use o sistema por alguns instantes e tente novamente.</p><button type="button">Fechar</button></div>`;
 document.body.appendChild(box);box.querySelector('button').onclick=()=>box.remove();
}));

if('serviceWorker' in navigator){
 navigator.serviceWorker.register('sw.js',{scope:'./'}).catch(()=>{});
}
update();
})();