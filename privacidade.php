<?php
require __DIR__ . '/config.php';
$contact=system_setting('privacy_contact','');
$retention=system_setting('privacy_retention','DEFINIR PELA ORGANIZAÇÃO');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacidade - <?=e(app_display_name())?></title><link rel="manifest" href="manifest_publico.php"><link rel="apple-touch-icon" href="assets/icons/guacas-publico-180.png"><link rel="stylesheet" href="assets/app.css"></head><body class="public-bg">
<button class="back-floating" onclick="history.length>1?history.back():location.href='login.php'">← Voltar</button>
<main class="public-request-wrap"><section class="card public-request-card"><div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo"></div><h1>Privacidade e proteção de dados</h1>
<p>A GUACAS registra dados necessários aos cadastros internos, solicitações de ocorrência e documentação do atendimento.</p>
<h2>Dados que podem ser tratados</h2><p>Identificação de integrantes, CPF protegido para conferência de recuperação de senha, data de nascimento, contato, foto 3x4, assinatura interna, localização GPS quando autorizada, dados do solicitante e informações relacionadas ao atendimento e à Ficha APH.</p>
<h2>Finalidade</h2><p>Organização da guarnição, controle de acesso, despacho e acompanhamento de ocorrências, registro de atendimento, comunicação operacional, segurança do sistema e geração de documentos internos.</p>
<h2>Localização</h2><p>O GPS somente é obtido quando o navegador/aparelho autoriza. Na solicitação pública, o endereço continua sendo utilizado mesmo quando a localização não é fornecida.</p>
<h2>Segurança</h2><p>O sistema possui controles de acesso, trilhas de auditoria, recuperação de senha, opção de autenticação em duas etapas para Administradores, bloqueio por inatividade, backups e revogação de sessões de navegador.</p>
<h2>Retenção</h2><p><?=e($retention)?></p>
<h2>Contato</h2><p><?=$contact!==''?e($contact):'O responsável institucional pela privacidade ainda deve ser definido na configuração do sistema.'?></p>
<div class="notice"><strong>Importante:</strong> esta página descreve recursos do software. A organização responsável deve definir formalmente bases legais, prazos de retenção, procedimentos de atendimento aos titulares e demais obrigações aplicáveis antes do uso em produção.</div>
</section></main><script>window.GUACAS_PWA_TYPE="PUBLICO";</script><script src="assets/pwa_install.js"></script></body></html>
