<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);
$pdo=db();
sync_users_team_labels($pdo);
$teams=$pdo->query("SELECT t.*,u.bc_name AS updated_bc,(SELECT COUNT(*) FROM team_members tm WHERE tm.team_id=t.id AND tm.active=1) member_count FROM teams t LEFT JOIN users u ON u.id=t.updated_by ORDER BY t.active DESC,t.name")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(app_display_name())?> - Equipes</title><link rel="stylesheet" href="assets/app.css"></head><body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}" aria-label="Voltar para a página anterior">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Gestão de Equipes</span></div></div><div class="right"><span class="pill">Somente Admin Geral</span><a href="base.php">Central</a><a href="usuarios.php">Usuários</a><a href="configuracoes.php">Configurações</a><a href="viaturas.php">Viaturas</a><a href="materiais.php">Materiais</a><a href="relatorios.php">Relatórios</a><a href="logout.php">Sair</a></div></header>
<main class="layout">
<section class="card"><div class="section-head"><div><h2>Equipes / Guarnições</h2><p class="muted">Somente os Administradores Gerais podem criar ou alterar equipes. Equipe ativa: mínimo de 3 e máximo de 50 bombeiros.</p></div><a class="button-link primary-link" href="equipe_editar.php">+ Criar equipe</a></div></section>
<section class="cards">
<?php if(!$teams):?><div class="card">Nenhuma equipe cadastrada.</div><?php endif;?>
<?php foreach($teams as $t):?><article class="occ">
<div class="section-head"><strong><?=e($t['name'])?></strong><span class="badge <?=$t['active']?'online':'offline'?>"><?=$t['active']?'ATIVA':'INATIVA'?></span></div>
<div class="meta"><span class="badge"><?=e($t['code']?:'SEM CÓDIGO')?></span><span class="badge"><strong><?=$t['member_count']?></strong>&nbsp; bombeiros</span></div>
<p><?=nl2br(e($t['notes']??''))?></p>
<div class="small">Última atualização: <?=e($t['updated_at'])?><?=!empty($t['updated_bc'])?' · '.e($t['updated_bc']):''?></div>
<a class="button-link" href="equipe_editar.php?id=<?=$t['id']?>">Editar equipe / composição</a>
</article><?php endforeach;?>
</section>
</main><script src="assets/security.js"></script></body></html>
