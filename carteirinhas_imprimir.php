<?php
require __DIR__ . '/config.php';
$admin = require_user(['ADMIN']);
$pdo = db();
ensure_user_registration_numbers($pdo);

$rows = $pdo->query("
    SELECT id,name,bc_name,role,team,active,
           COALESCE(blood_type,'NÃO SABE') blood_type,
           registration_number,firefighter_certificate_number,
           photo_path,card_issued_at,card_updated_at
      FROM users
     WHERE deleted_at IS NULL AND active=1
     ORDER BY name
")->fetchAll();

$base = system_setting('central_base_address','');
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Imprimir carteirinhas - <?=e(app_display_name())?></title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#ddd;margin:0;color:#181818}
.toolbar{max-width:1000px;margin:14px auto;display:flex;justify-content:flex-end;gap:8px}.toolbar button{padding:10px 16px;border-radius:8px;font-weight:800;cursor:pointer}
.back{background:#222;color:#fff;border:2px solid #f2b51d}.print{background:#a80e16;color:#fff;border:2px solid #f2b51d}
.sheet{width:210mm;min-height:297mm;margin:10px auto;background:#fff;padding:8mm;display:grid;grid-template-columns:85.6mm 85.6mm;grid-auto-rows:54mm;gap:6mm 8mm;align-content:start}
.card{width:85.6mm;height:54mm;border-radius:4mm;position:relative;overflow:hidden;page-break-inside:avoid}
.front{background:linear-gradient(130deg,#141414 0%,#63070d 54%,#b31119 100%);color:#fff;border:1.2mm solid #f2b51d;padding:2.7mm}
.head{height:11mm;display:flex;align-items:center;gap:2mm;border-bottom:.35mm solid #f2b51d;padding-bottom:1.2mm}.head img{width:9mm;height:9mm;object-fit:contain;background:#fff;border-radius:1mm}
.title{font-weight:900;font-size:8.5px;color:#ffd45b}.org{font-size:5px}.main{display:grid;grid-template-columns:20mm 1fr;gap:2.4mm;padding-top:2mm}
.photo{width:20mm;height:26.7mm;object-fit:cover;border:.6mm solid #f2b51d;border-radius:1mm;background:#eee}.empty{width:20mm;height:26.7mm;display:grid;place-items:center;text-align:center;border:.6mm dashed #f2b51d;font-size:5.5px}
.identity-top{display:grid;grid-template-columns:1fr 13mm;gap:1.2mm;align-items:start;margin-bottom:.8mm}
.bc{font-size:11.5px;font-weight:900;line-height:1;text-transform:uppercase}
.full{font-size:7.2px;line-height:1.08;font-weight:700;margin:.7mm 0 0}
.blood-badge{min-height:11mm;border:.55mm solid #f2b51d;border-radius:1.5mm;background:#fff;color:#7b0910;text-align:center;display:flex;flex-direction:column;justify-content:center;padding:.5mm}
.blood-badge span{font-size:3.8px;font-weight:900;line-height:1;text-transform:uppercase}
.blood-badge strong{font-size:11px;line-height:1;margin-top:.5mm}
.data{font-size:5.5px;display:grid;grid-template-columns:1fr 1fr;gap:.8mm}.data b{display:block;color:#ffd45b;font-size:4.7px}
.backside{background:linear-gradient(145deg,#fffdf7,#eee3cf);border:1.2mm solid #a80e16;padding:3mm}.backside h2{font-size:10px;color:#8b0e15;margin:0 0 2mm;border-bottom:.4mm solid #f2b51d}.row{font-size:6.1px;margin:1.25mm 0}.row b{color:#75080e}.notice{font-size:5px;border-top:.3mm solid #f2b51d;padding-top:1.3mm;margin-top:1.6mm}
@media print{body{background:#fff}.toolbar{display:none}.sheet{margin:0;box-shadow:none;page-break-after:always}@page{size:A4;margin:0}}
</style></head><body>
<div class="toolbar"><button class="back" onclick="history.back()">← Voltar</button><button class="print" onclick="window.print()">🖨️ Imprimir todas</button></div>

<?php foreach(array_chunk($rows,4) as $pageRows):?>
<section class="sheet">
<?php foreach($pageRows as $x):?>
<div class="card front">
<div class="head"><img src="assets/logo_oficial_bombeiros.jpeg" alt=""><div><div class="title"><?=e(app_display_name())?> · CARTEIRA DA GUARNIÇÃO</div><div class="org"><?=e(ORG_NAME)?></div></div></div>
<div class="main">
<?php if($x['photo_path']):?><img class="photo" src="user_photo.php?id=<?=$x['id']?>&v=<?=urlencode((string)($x['card_updated_at']??''))?>" alt=""><?php else:?><div class="empty">FOTO<br>3x4</div><?php endif;?>
<div><div class="identity-top"><div><div class="bc"><?=e($x['bc_name']?:$x['name'])?></div><div class="full"><?=e($x['name'])?></div></div><div class="blood-badge"><span>Tipo sanguíneo</span><strong><?=e($x['blood_type'])?></strong></div></div><div class="data">
<div><b>Matrícula</b><?=e($x['registration_number']?:'-')?></div><div><b>Certificado</b><?=e($x['firefighter_certificate_number']?:'-')?></div>
<div><b>Função</b><?=e(role_label($x['role']))?></div><div><b>Equipe</b><?=e($x['team']?:'SEM EQUIPE')?></div>
<div><b>Emissão</b><?=$x['card_issued_at']?e(date('d/m/Y',strtotime($x['card_issued_at']))):'NÃO EMITIDA'?></div><div><b>Situação</b><?=$x['active']?'ATIVO':'INATIVO'?></div>
</div></div></div>
</div>

<div class="card backside">
<h2>IDENTIFICAÇÃO OPERACIONAL</h2>
<div class="row"><b>Nome:</b> <?=e($x['name'])?></div>
<div class="row"><b>BC:</b> <?=e($x['bc_name']?:'-')?></div>
<div class="row"><b>Matrícula:</b> <?=e($x['registration_number']?:'-')?></div>
<div class="row"><b>Certificado:</b> <?=e($x['firefighter_certificate_number']?:'NÃO INFORMADO')?></div>
<div class="row"><b>Base Central:</b> <?=$base!==''?e($base):'________________________________'?></div>
<div class="notice">Tipo sanguíneo cadastrado: <?=e($x['blood_type'])?>. Alterações da carteirinha são exclusivas dos Administradores Gerais.</div>
</div>
<?php endforeach;?>
</section>
<?php endforeach;?>
<script src="assets/security.js"></script></body></html>
