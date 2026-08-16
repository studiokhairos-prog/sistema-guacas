<?php
require __DIR__ . '/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$basePath=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/'));
if($basePath==='/'||$basePath==='.')$basePath='';
echo json_encode([
 'id'=>$basePath.'/guacas-publico-app',
 'name'=>app_display_name().' Público',
 'short_name'=>'GUACAS Público',
 'description'=>'Solicitação rápida de ocorrência para a Central GUACAS.',
 'start_url'=>'./app_publico.php?launch=pwa',
 'scope'=>'./',
 'display'=>'standalone',
 'display_override'=>['standalone','minimal-ui'],
 'background_color'=>'#fff8e9',
 'theme_color'=>'#b10f18',
 'orientation'=>'portrait-primary',
 'icons'=>[
   ['src'=>'assets/icons/guacas-publico-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
   ['src'=>'assets/icons/guacas-publico-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
   ['src'=>'assets/icons/guacas-publico-maskable-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'],
 ],
 'shortcuts'=>[
   ['name'=>'Solicitar ocorrência','short_name'=>'Ocorrência','url'=>'solicitar_ocorrencia.php?from=pwa','icons'=>[['src'=>'assets/icons/guacas-publico-192.png','sizes'=>'192x192','type'=>'image/png']]],
   ['name'=>'Privacidade','short_name'=>'Privacidade','url'=>'privacidade.php'],
 ],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
