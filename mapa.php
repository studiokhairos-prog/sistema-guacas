<?php
require __DIR__ . '/config.php';
$u=require_user(['ADMIN','BASE','STAFF']);$csrf=csrf_token();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mapa Operacional - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>#operationalMap{height:68vh;min-height:430px;border:3px solid #f2b51d;border-radius:14px;background:#e7e2da}.map-grid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px}.map-list{max-height:68vh;overflow:auto}@media(max-width:850px){.map-grid{grid-template-columns:1fr}}</style>
</head><body>
<button class="back-floating" onclick="history.back()">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Mapa Operacional</span></div></div><div class="right"><span id="net" class="pill">...</span><a href="base.php">Central</a><a href="logout.php">Sair</a></div></header>
<main class="dynamic-layout"><div class="map-grid"><div id="operationalMap"></div><section class="card map-list"><h2>Posições recebidas</h2><div id="mapFallback"></div><p class="muted">O mapa utiliza cartografia online. Se a internet cair, as últimas coordenadas continuam listadas, mas os mapas de fundo podem não carregar.</p></section></div></main>
<script>window.SICOBC={csrf:<?=json_encode($csrf)?>};</script><script src="assets/app.js"></script><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(()=>{let map=null,layer=null;
 function esc(s){return escapeHtml(s||'');}
 async function load(){try{const j=await apiFetch('api/dashboard.php');const points=(j.teams||[]).filter(t=>t.presence&&t.presence.lat!=null&&t.presence.lng!=null);document.getElementById('mapFallback').innerHTML=points.length?points.map(t=>`<div class="side-item"><strong>${esc(t.name)}</strong><small>${esc(t.presence.bc_name||t.presence.name)} · ${esc(t.presence.last_seen)}</small><a target="_blank" rel="noopener" href="https://www.google.com/maps?q=${t.presence.lat},${t.presence.lng}">Abrir coordenada</a></div>`).join(''):'<p class="muted">Nenhuma equipe enviou GPS ainda.</p>';
 if(!window.L)return;if(!map){map=L.map('operationalMap').setView([-14.235,-51.9253],4);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);layer=L.layerGroup().addTo(map);}layer.clearLayers();const bounds=[];
 for(const t of points){const ll=[Number(t.presence.lat),Number(t.presence.lng)];bounds.push(ll);L.marker(ll).bindPopup(`<strong>${esc(t.name)}</strong><br>${esc(t.operational_status)}<br>${esc(t.presence.bc_name||t.presence.name)}`).addTo(layer);}
 for(const o of (j.items||[]).filter(x=>x.status!=='ENCERRADA'&&x.lat!=null&&x.lng!=null)){const ll=[Number(o.lat),Number(o.lng)];bounds.push(ll);L.circleMarker(ll,{radius:9}).bindPopup(`<strong>${esc(o.protocol)}</strong><br>${esc(o.type)}<br>${esc(o.address)}`).addTo(layer);}
 if(bounds.length)map.fitBounds(bounds,{padding:[30,30],maxZoom:16});
 }catch(e){}}
 load();setInterval(load,5000);
})();
</script><script src="assets/security.js"></script></body></html>
