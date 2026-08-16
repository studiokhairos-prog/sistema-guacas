<?php
require __DIR__ . '/config.php';
$occ=whatsapp_url('whatsapp_occurrence','Olá. Preciso informar uma ocorrência para a Central.');
$den=whatsapp_url('whatsapp_complaints','Olá. Gostaria de registrar uma denúncia/comunicação para a Guarnição.');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(app_display_name())?> - WhatsApp</title><link rel="stylesheet" href="assets/app.css"></head><body class="center"><main class="card auth contact-card"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo"><h1><?=e(app_display_name())?></h1><h2>Contato via WhatsApp</h2><p>Escolha o canal desejado.</p>
<?php if($occ):?><a class="whatsapp-button" href="<?=e($occ)?>" target="_blank" rel="noopener">📲 Informar ocorrência</a><?php else:?><div class="notice">Canal de ocorrências aguardando cadastro pelo Admin Geral.</div><?php endif;?>
<?php if($den):?><a class="whatsapp-button complaint" href="<?=e($den)?>" target="_blank" rel="noopener">📣 Denúncias / comunicações</a><?php else:?><div class="notice">Canal de denúncias aguardando cadastro pelo Admin Geral.</div><?php endif;?>
<p class="muted">Este canal complementa a comunicação da guarnição e não substitui outros meios oficiais de emergência quando necessários.</p><a class="button-link" href="login.php">← Voltar ao login</a><section class="card contact-card"><h2>Abertura rápida pelo sistema</h2><p class="muted">Além do WhatsApp, uma pessoa pode iniciar uma solicitação pelo formulário simplificado.</p><a class="public-request-button" href="solicitar_ocorrencia.php">🚨 Abrir solicitação de ocorrência</a></section></main></body></html>
