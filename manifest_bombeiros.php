<?php
require __DIR__ . '/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$basePath=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/'));
if($basePath==='/'||$basePath==='.')$basePath='';
echo json_encode([
 'id'=>$basePath.'/guacas-bombeiros-app',
 'name'=>app_display_name().' Bombeiros',
 'short_name'=>'GUACAS BC',
 'description'=>'Aplicativo operacional GUACAS para Bombeiros Civis autorizados.',
 'start_url'=>'./app_bombeiros.php?launch=pwa',
 'scope'=>'./',
 'display'=>'standalone',
 'display_override'=>['standalone','minimal-ui'],
 'background_color'=>'#151515',
 'theme_color'=>'#75080e',
 'icons'=>[
   ['src'=>'assets/icons/guacas-bombeiros-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
   ['src'=>'assets/icons/guacas-bombeiros-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
   ['src'=>'assets/icons/guacas-bombeiros-maskable-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'],
 ],
 'shortcuts'=>[
   ['name'=>'Abrir GUACAS','short_name'=>'Operação','url'=>'app_bombeiros.php?launch=pwa','icons'=>[['src'=>'assets/icons/guacas-bombeiros-192.png','sizes'=>'192x192','type'=>'image/png']]],
   ['name'=>'Minha carteirinha','short_name'=>'Carteirinha','url'=>'carteirinha.php'],
 ],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
