<?php
require __DIR__ . '/config.php';
$data = $_SESSION['public_occurrence_success'] ?? null;

if (!is_array($data) || empty($data['protocol'])) {
    header('Location: solicitar_ocorrencia.php');
    exit;
}

$protocol = (string)$data['protocol'];
$requester = trim((string)($data['requester_name'] ?? ''));
$createdAt = (string)($data['created_at'] ?? now_iso());
$occurrenceLevel=(string)($data['occurrence_level']??'NAO_CLASSIFICADO');
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24">
<title>Solicitação recebida - <?=e(app_display_name())?></title>
<link rel="manifest" href="manifest_publico.php"><link rel="apple-touch-icon" href="assets/icons/guacas-publico-180.png"><link rel="stylesheet" href="assets/app.css">
<style>
.thanks-wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
.thanks-card{width:min(660px,100%);text-align:center;border-top:5px solid #f2b51d!important}
.thanks-icon{font-size:58px;line-height:1;margin:8px 0 12px}
.thanks-card h1{color:#8b0e15;font-size:clamp(26px,5vw,38px);margin-bottom:8px}
.thanks-lead{font-size:18px;line-height:1.5}
.thanks-protocol{margin:20px auto;padding:16px;border-radius:14px;background:linear-gradient(135deg,#151515,#790a10);color:#fff;border:2px solid #f2b51d;max-width:430px}
.thanks-protocol small{display:block;color:#ffd45b;font-weight:800;letter-spacing:.08em;margin-bottom:5px}
.thanks-protocol strong{font-size:28px;letter-spacing:.04em}
.thanks-info{margin:18px 0;padding:14px;background:#fff8e8;border:1px solid #e2c374;border-radius:10px;text-align:left}
.thanks-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:18px}
.thanks-actions a{width:auto;min-width:190px}
</style>
</head>
<body class="public-bg">
<main class="thanks-wrap">
<section class="card thanks-card">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo oficial"></div>
<div class="thanks-icon">✅</div>
<h1>Solicitação recebida</h1>
<p class="thanks-lead"><?php if($requester!==''):?>Obrigado, <strong><?=e($requester)?></strong>. <?php endif;?>Sua solicitação foi enviada para a Central e já possui protocolo.</p>

<div class="thanks-protocol">
<small>PROTOCOLO DA SOLICITAÇÃO</small>
<strong><?=e($protocol)?></strong>
</div>

<div class="thanks-info">
<p><strong>Guarde este número de protocolo.</strong> Ele pode ser informado à equipe durante o atendimento.</p>
<p>A Central receberá a solicitação como <strong>NOVA OCORRÊNCIA PÚBLICA</strong> e poderá iniciar o despacho da equipe.</p><p><strong>DATA/HORA DA SOLICITAÇÃO:</strong> <?=e(date('d/m/Y H:i:s',strtotime($createdAt)))?><br><strong>NÍVEL INICIAL INFORMADO:</strong> <?=e(occurrence_level_label($occurrenceLevel))?></p>
<p class="muted">Em situação de risco imediato, utilize também os canais públicos de emergência e os meios locais disponíveis. Esta página não substitui os serviços públicos de emergência.</p>
</div>

<div class="thanks-actions">
<a class="button-link" href="solicitar_ocorrencia.php">➕ Fazer outra solicitação</a>
<a class="whatsapp-button compact" href="contato.php">📲 WhatsApp de ocorrência / denúncia</a>
</div>
</section>
</main>
<script>window.GUACAS_PWA_TYPE="PUBLICO";</script><script src="assets/pwa_install.js"></script></body></html>
