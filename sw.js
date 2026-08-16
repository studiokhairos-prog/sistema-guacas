const CACHE='guacas-shell-v11-web-pwa';
const STATIC=[
 './assets/app.css',
 './assets/app.js',
 './assets/pwa_install.js',
 './assets/base.js',
 './assets/campo.js',
 './assets/aph.js',
 './assets/security.js',
 './assets/photo_camera.js',
 './assets/logo_oficial_bombeiros.jpeg',
 './assets/icons/guacas-publico-180.png',
 './assets/icons/guacas-publico-192.png',
 './assets/icons/guacas-publico-512.png',
 './assets/icons/guacas-publico-maskable-512.png',
 './assets/icons/guacas-bombeiros-180.png',
 './assets/icons/guacas-bombeiros-192.png',
 './assets/icons/guacas-bombeiros-512.png',
 './assets/icons/guacas-bombeiros-maskable-512.png',
 './manifest_publico.php',
 './manifest_bombeiros.php',
 './offline_publico.php',
 './offline_bombeiros.php'
];

self.addEventListener('install',e=>{
 self.skipWaiting();
 e.waitUntil(caches.open(CACHE).then(c=>c.addAll(STATIC).catch(()=>{})));
});

self.addEventListener('activate',e=>{
 e.waitUntil(
   caches.keys()
     .then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))
     .then(()=>self.clients.claim())
 );
});

function publicNavigation(url){
 return [
  'app_publico.php','solicitar_ocorrencia.php','solicitacao_agradecimento.php',
  'privacidade.php','contato.php','apps.php'
 ].some(x=>url.pathname.endsWith('/'+x)||url.pathname.endsWith(x));
}

self.addEventListener('fetch',e=>{
 const r=e.request;
 if(r.method!=='GET')return;
 const u=new URL(r.url);
 if(u.origin!==location.origin)return;

 if(r.mode==='navigate'){
   e.respondWith(fetch(r).catch(()=>caches.match(publicNavigation(u)?'./offline_publico.php':'./offline_bombeiros.php')));
   return;
 }

 if(u.pathname.includes('/api/') || u.pathname.includes('/data/')) return;

 const isStatic=STATIC.some(a=>u.pathname.endsWith(a.replace('./','')));
 if(isStatic){
   e.respondWith(caches.match(r).then(cached=>cached||fetch(r).then(resp=>{
     const copy=resp.clone();caches.open(CACHE).then(c=>c.put(r,copy));return resp;
   })));
 }
});
