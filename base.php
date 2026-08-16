<?php
require __DIR__ . '/config.php';
$u=require_user(['ADMIN','BASE','STAFF']);
$pdo=db();$csrf=csrf_token();
$catalog=occurrence_catalog_grouped($pdo);
$teams=active_teams($pdo);
$vehicles=active_vehicles($pdo);
$refresh=max(2,min(15,(int)system_setting('dashboard_refresh_seconds','3')));
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24">
<title><?=e(app_display_name())?> - Central Operacional Dinâmica</title>
<link rel="manifest" href="manifest_bombeiros.php"><link rel="apple-touch-icon" href="assets/icons/guacas-bombeiros-180.png"><link rel="stylesheet" href="assets/app.css">
</head>
<body class="dynamic-body">
<header class="topbar dynamic-topbar">
<div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div>
<strong><?=e(app_display_name())?></strong><span>Central Operacional Dinâmica</span></div></div>
<div class="right">
<span id="net" class="pill">...</span>
<button id="enableAlerts" class="top-action" type="button">🔔 ATIVAR ALARME DA CENTRAL</button><span id="alarmState" class="pill alarm-state">ALARME DESATIVADO</span>
<a href="mapa.php">Mapa</a><a href="viaturas.php">Viaturas</a><a href="materiais.php">Materiais</a><a href="relatorios.php">Relatórios</a>
<?php if($u['role']==='ADMIN'):?><a href="usuarios.php">Bombeiros</a><a href="equipes.php">Equipes</a><a href="configuracoes.php">Configurações</a><a href="seguranca.php">Segurança</a><a href="nuvem.php">☁️ Nuvem</a><a href="portal.php">🌐 Portal Web</a><a href="web_status.php">🌐 Status Web</a><a href="internet_teste.php">🌐 Internet teste</a><a href="producao.php">🚒 Produção</a><?php endif;?>
<a href="aph_arquivo.php">APH</a><a href="contato.php">WhatsApp</a><a href="logout.php">Sair</a>
</div>
</header>

<main class="dynamic-layout">
<section class="kpi-grid">
<article class="kpi kpi-open"><span>Ocorrências abertas</span><strong id="statOpen">0</strong></article>
<article class="kpi kpi-critical"><span>Críticas agora</span><strong id="statCritical">0</strong></article>
<article class="kpi kpi-team"><span>Equipes disponíveis</span><strong id="statTeamsAvailable">0</strong></article>
<article class="kpi kpi-engaged"><span>Equipes empenhadas</span><strong id="statTeamsEngaged">0</strong></article>
<article class="kpi"><span>Ocorrências hoje</span><strong id="statToday">0</strong></article>
<article class="kpi"><span>Pacientes hoje</span><strong id="statPatients">0</strong></article>
</section>

<section class="central-public-alerts-zone">
<section class="card central-alert-card">
<div class="section-head"><h2>🔔 Avisos da Central</h2><span class="muted">Solicitações públicas recebidas em tempo real</span></div>
<div id="centralAlertsBox" class="central-alerts-box">
<p class="muted">Nenhuma solicitação pública nova no momento.</p>
</div>
</section>
</section>

<section class="dynamic-columns">
<div class="main-column">
<section class="card quick-panel">
<div class="section-head"><h2>🚨 Abertura rápida</h2><button id="toggleNewOcc" type="button">Abrir / fechar formulário</button></div>
<div class="quick-occ" id="quickOcc">
<button type="button" data-nature="ATENDIMENTO PRÉ-HOSPITALAR" data-type="Mal súbito">Mal súbito</button>
<button type="button" data-nature="TRAUMA" data-type="Queda">Queda</button>
<button type="button" data-nature="TRAUMA" data-type="Acidente de trânsito">Acidente trânsito</button>
<button type="button" data-nature="EMERGÊNCIA CRÍTICA" data-type="Parada cardiorrespiratória (PCR)">PCR</button>
<button type="button" data-nature="EMERGÊNCIA CRÍTICA" data-type="Engasgo / OVACE">OVACE</button>
<button type="button" data-nature="INCÊNDIO" data-type="Princípio de incêndio">Incêndio</button>
</div>
<form id="newOcc" class="compact-form">
<div class="grid2">
<label>Natureza<select name="nature" id="nature" required><option value="">Selecione...</option><?php foreach(array_keys($catalog) as $n):?><option value="<?=e($n)?>"><?=e($n)?></option><?php endforeach;?><option value="OUTRA">OUTRA</option></select></label>
<label>Tipo<select name="type" id="occType" required disabled><option value="">Escolha a natureza</option></select></label>
</div>
<div class="grid2" id="otherRow" hidden><label>Natureza personalizada<input name="nature_other" id="natureOther"></label><label>Tipo personalizado<input name="type_other" id="typeOther"></label></div>
<div class="grid4">
<label>Nível da ocorrência<select name="occurrence_level" class="level-select"><option value="NAO_CLASSIFICADO">NÃO CLASSIFICADO</option><option value="N1_CRITICO">N1 CRÍTICO</option><option value="N2_URGENTE">N2 URGENTE</option><option value="N3_PRIORITARIO" selected>N3 PRIORITÁRIO</option><option value="N4_BAIXA_PRIORIDADE">N4 BAIXA PRIORIDADE</option></select></label>
<label>Prioridade<select name="priority"><option>BAIXA</option><option selected>MEDIA</option><option>ALTA</option><option>CRITICA</option></select></label>
<label>Equipe<select name="team"><option value="">Aguardando equipe</option><?php foreach($teams as $t):?><option value="<?=e($t['name'])?>"><?=e($t['name'])?></option><?php endforeach;?></select></label>
<label>Endereço<input name="address" required placeholder="Local / endereço"></label>
</div>
<label>Detalhes iniciais<input name="details" placeholder="Informação curta recebida pela Central"></label>
<button class="primary">Abrir ocorrência</button>
</form>
</section>

<section class="board-section">
<div class="section-head board-head"><h2>Quadro operacional</h2>
<div class="board-filters"><input id="occSearch" placeholder="Buscar protocolo, tipo, endereço..."><select id="statusFilter"><option value="">Todos os status</option><option>SOLICITADA</option><option>ABERTA</option><option>DESPACHADA</option><option>A_CAMINHO</option><option>NO_LOCAL</option><option>EM_ATENDIMENTO</option><option>RETORNANDO</option><option>ENCERRADA</option></select><select id="priorityFilter"><option value="">Todas prioridades</option><option>CRITICA</option><option>ALTA</option><option>MEDIA</option><option>BAIXA</option></select></div>
</div>
<div id="occurrences" class="operation-board"></div>
</section>
</div>

<aside class="side-column">
<section class="card"><div class="section-head"><h2>Equipes</h2><a href="mapa.php">Mapa</a></div><div id="teamPanel" class="side-list"></div></section>
<section class="card"><div class="section-head"><h2>Viaturas</h2><a href="viaturas.php">Gerenciar</a></div><div id="vehiclePanel" class="side-list"></div></section>
<section class="card">
<h2>Atalhos</h2>
<div class="shortcut-grid">
<a href="solicitar_ocorrencia.php" target="_blank">Solicitação pública</a>
<a href="aph.php">Nova Ficha APH</a>
<a href="aph_arquivo.php">Arquivo APH</a>
<a href="contato.php">WhatsApp</a>
</div>
</section>
</aside>
</section>
</main>

<script>
window.SICOBC={
 csrf:<?=json_encode($csrf)?>,
 role:<?=json_encode($u['role'])?>,
 catalog:<?=json_encode($catalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
 teams:<?=json_encode($teams,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
 vehicles:<?=json_encode($vehicles,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
 refreshSeconds:<?=$refresh?>
};
</script>
<script src="assets/app.js"></script><script src="assets/base.js"></script>
<script src="assets/security.js"></script></body></html>
