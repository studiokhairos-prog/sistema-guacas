<?php
require __DIR__ . '/config.php';
$u = require_user();
$pdo = db();
$id = (int)($_GET['id'] ?? $u['id']);

if ($id !== (int)$u['id'] && !is_admin_general($u)) {
    http_response_code(403);
    exit('Acesso negado.');
}

ensure_user_registration_numbers($pdo);
$st = $pdo->prepare("
    SELECT id,name,war_name,bc_name,role,team,active,
           COALESCE(financial_status,'REGULAR') financial_status,
           COALESCE(blood_type,'NÃO SABE') blood_type,
           registration_number,firefighter_certificate_number,photo_path,
           card_issued_at,card_updated_at,deleted_at
      FROM users WHERE id=?
");
$st->execute([$id]);
$x = $st->fetch();
if (!$x || $x['deleted_at']) { http_response_code(404); exit('Cadastro não encontrado.'); }

if (!is_admin_general($u) && !$x['card_issued_at']) {
    http_response_code(403);
    exit('Sua carteirinha ainda não foi emitida por um Administrador Geral.');
}

$base = system_setting('central_base_address','');
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Carteirinha <?=e($x['bc_name']?:$x['name'])?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#d7d4cf;margin:0;color:#181818}
.toolbar{max-width:1000px;margin:16px auto;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.toolbar button,.toolbar a{width:auto;padding:10px 16px;border-radius:8px;font-weight:800;cursor:pointer;text-decoration:none}
.back{background:#222;color:white;border:2px solid #f2b51d}
.print{background:#a80e16;color:#fff;border:2px solid #f2b51d}
.edit{background:#f2b51d;color:#341f00;border:2px solid #8a5e00}
.sheet{display:flex;gap:10mm;flex-wrap:wrap;justify-content:center;padding:10mm}
.idcard{width:85.6mm;height:54mm;border-radius:4mm;position:relative;overflow:hidden;box-shadow:0 9px 28px #0005}
.front{background:linear-gradient(130deg,#141414 0%,#63070d 54%,#b31119 100%);color:#fff;border:1.3mm solid #f2b51d;padding:3mm}
.front:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 90% 80%,#ffffff16,transparent 34mm);pointer-events:none}
.head{height:12mm;display:flex;align-items:center;gap:2.5mm;border-bottom:.35mm solid #f2b51d;padding-bottom:1.5mm}
.head .logo{width:10mm;height:10mm;object-fit:contain;background:#fff;border-radius:1.3mm;border:.35mm solid #f2b51d}
.head .title{font-weight:900;font-size:10px;color:#ffd45b;line-height:1.05}
.head .org{font-size:5.7px;line-height:1.15;margin-top:.7mm}
.main{display:grid;grid-template-columns:21mm 1fr;gap:3mm;padding-top:2.5mm;position:relative;z-index:2}
.photo{width:21mm;height:28mm;object-fit:cover;background:#efefef;border:.7mm solid #f2b51d;border-radius:1.5mm}
.photo-empty{width:21mm;height:28mm;border:.7mm dashed #f2b51d;border-radius:1.5mm;background:#fff2;display:grid;place-items:center;text-align:center;font-size:6px;font-weight:800}
.identity-top{display:grid;grid-template-columns:1fr 15mm;gap:1.5mm;align-items:start;margin-bottom:1.4mm}
.bc{font-size:14px;font-weight:900;line-height:1.02;margin:0 0 .8mm;color:#fff;text-transform:uppercase}
.full{font-size:8.6px;line-height:1.12;font-weight:700;color:#fff;margin-bottom:0}
.blood-badge{min-height:13mm;border:.65mm solid #f2b51d;border-radius:2mm;background:#fff;color:#7b0910;text-align:center;display:flex;flex-direction:column;justify-content:center;padding:.7mm}
.blood-badge span{font-size:4.5px;line-height:1;text-transform:uppercase;font-weight:900}
.blood-badge strong{font-size:14px;line-height:1.05;margin-top:.6mm}
.data{display:grid;grid-template-columns:1fr 1fr;gap:1mm 2mm;font-size:6.8px}
.data b{display:block;color:#ffd45b;font-size:5.3px;text-transform:uppercase}
.footer{position:absolute;left:27mm;right:3mm;bottom:2.1mm;display:flex;justify-content:space-between;gap:2mm;font-size:5.2px;color:#ffe9b1}
.backside{background:linear-gradient(145deg,#fffdf7,#eee3cf);border:1.3mm solid #a80e16;color:#191919;padding:3.5mm}
.backside:after{content:"";position:absolute;inset:1.4mm;border:.35mm solid #f2b51d;border-radius:2.2mm;pointer-events:none}
.back-logo{position:absolute;width:28mm;height:28mm;object-fit:contain;right:4mm;bottom:4mm;opacity:.08}
.backside h2{position:relative;z-index:2;color:#8b0e15;margin:0 0 2mm;border-bottom:.55mm solid #f2b51d;padding-bottom:1mm;font-size:11px}
.rows{position:relative;z-index:2}
.row{margin:1.35mm 0;font-size:7px}.row b{color:#75080e}
.notice{position:relative;z-index:2;font-size:5.8px;border-top:.35mm solid #f2b51d;padding-top:1.7mm;margin-top:2mm;line-height:1.25}
.code{font-weight:900;color:#8b0e15}
@media print{
 body{background:white}.toolbar{display:none}.sheet{padding:0;display:block}
 .idcard{box-shadow:none;page-break-inside:avoid;margin:5mm auto}
 .backside{page-break-before:always}
 @page{size:A4;margin:8mm}
}
</style></head>
<body>
<div class="toolbar">
<button class="back" onclick="history.length>1?history.back():location.href='index.php'">← Voltar</button>
<?php if(is_admin_general($u)):?><a class="edit" href="carteirinha_editar.php?id=<?=$id?>">✏️ Editar carteirinha</a><?php endif;?>
<button class="print" onclick="window.print()">🖨️ Imprimir carteirinha</button>
</div>

<main class="sheet">
<section class="idcard front">
<div class="head">
<img class="logo" src="assets/logo_oficial_bombeiros.jpeg" alt="Brasão">
<div><div class="title"><?=e(app_display_name())?> · CARTEIRA DA GUARNIÇÃO</div><div class="org"><?=e(ORG_NAME)?></div></div>
</div>
<div class="main">
<div><?php if($x['photo_path']):?><img class="photo" src="user_photo.php?id=<?=$id?>&v=<?=urlencode((string)($x['card_updated_at']??''))?>" alt="Foto 3x4"><?php else:?><div class="photo-empty">FOTO<br>3x4<br>NÃO<br>CADASTRADA</div><?php endif;?></div>
<div>
<div class="identity-top">
<div>
<div class="bc"><?=e($x['bc_name']?:$x['name'])?></div>
<div class="full"><?=e($x['name'])?></div>
</div>
<div class="blood-badge"><span>Tipo sanguíneo</span><strong><?=e($x['blood_type'])?></strong></div>
</div>
<div class="data">
<div><b>Matrícula</b><?=e($x['registration_number']?:'-')?></div>
<div><b>Certificado BC</b><?=e($x['firefighter_certificate_number']?:'NÃO INFORMADO')?></div>
<div><b>Função</b><?=e(role_label($x['role']))?></div>
<div><b>Equipe</b><?=e($x['team']?:'SEM EQUIPE')?></div>
<div><b>Situação</b><?=$x['active']?'ATIVO':'INATIVO'?></div>
<div><b>Cadastro financeiro</b><?=e($x['financial_status'])?></div>
</div>
</div>
</div>
<div class="footer">
<span>Identificação interna da guarnição</span>
<span><?=$x['card_issued_at']?'Emissão: '.e(date('d/m/Y',strtotime($x['card_issued_at']))):'NÃO EMITIDA'?></span>
</div>
</section>

<section class="idcard backside">
<img class="back-logo" src="assets/logo_oficial_bombeiros.jpeg" alt="">
<h2>IDENTIFICAÇÃO OPERACIONAL</h2>
<div class="rows">
<div class="row"><b>Nome completo:</b> <?=e($x['name'])?></div>
<div class="row"><b>Identificação de farda:</b> <?=e($x['bc_name']?:'-')?></div>
<div class="row"><b>Matrícula na Guarnição:</b> <span class="code"><?=e($x['registration_number']?:'-')?></span></div>
<div class="row"><b>Nº Certificado de Bombeiro:</b> <?=e($x['firefighter_certificate_number']?:'NÃO INFORMADO')?></div>
<div class="row"><b>Base Central:</b> <?=$base!==''?e($base):'________________________________________'?></div>
<div class="row"><b>Última atualização:</b> <?=$x['card_updated_at']?e(date('d/m/Y H:i',strtotime($x['card_updated_at']))):'-'?></div>
<div class="notice"><strong>Tipo sanguíneo:</strong> <?=e($x['blood_type'])?> — informação cadastral/autodeclarada; não substitui confirmação laboratorial quando necessária.</div>
<div class="notice"><strong>Controle:</strong> emissão e alterações desta carteirinha são exclusivas dos Administradores Gerais do sistema.</div>
</div>
</section>
</main>
<script src="assets/security.js"></script></body></html>
