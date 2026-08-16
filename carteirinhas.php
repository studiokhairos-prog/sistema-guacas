<?php
require __DIR__ . '/config.php';
$admin = require_user(['ADMIN']);
$pdo = db();
ensure_user_registration_numbers($pdo);
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) exit('CSRF inválido');
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_all') {
        $just = trim($_POST['justification'] ?? '');
        if (mb_strlen($just) < 5) {
            $err = 'Informe uma justificativa com pelo menos 5 caracteres.';
        } else {
            $count = issue_all_active_cards($pdo, (int)$admin['id']);
            admin_audit(
                $pdo,
                (int)$admin['id'],
                'ISSUE_ALL',
                'CARD',
                'ALL',
                $just,
                'Emissão/atualização em lote de carteirinhas: ' . $count . ' cadastros ativos.'
            );
            $msg = 'Carteirinhas geradas/atualizadas para ' . $count . ' integrantes ativos.';
        }
    }
}

$rows = $pdo->query("
    SELECT id,name,bc_name,role,team,active,registration_number,
           firefighter_certificate_number,photo_path,card_issued_at,card_updated_at,
           COALESCE(financial_status,'REGULAR') financial_status
      FROM users
     WHERE deleted_at IS NULL
     ORDER BY active DESC, role='ADMIN' DESC, name
")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Carteirinhas - <?=e(app_display_name())?></title>
<link rel="stylesheet" href="assets/app.css">
<style>
.card-photo-thumb{width:42px;height:56px;object-fit:cover;border-radius:5px;border:2px solid #f2b51d;background:#eee}
.card-photo-empty{width:42px;height:56px;border-radius:5px;border:2px dashed #aa8a42;display:grid;place-items:center;font-size:9px;text-align:center;background:#fff8e8}
.card-actions{display:flex;gap:6px;flex-wrap:wrap}.card-actions a{white-space:nowrap}
.issue-all{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end}
@media(max-width:700px){.issue-all{grid-template-columns:1fr}}
</style></head>
<body>
<button type="button" class="back-floating no-print" onclick="history.length>1?history.back():location.href='usuarios.php'">← Voltar</button>
<header class="topbar">
<div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div>
<strong><?=e(app_display_name())?></strong><span>Admin Geral — Carteirinhas da Guarnição</span></div></div>
<div class="right"><a href="usuarios.php">Bombeiros</a><a href="carteirinhas_imprimir.php" target="_blank">Imprimir todas</a><a href="logout.php">Sair</a></div>
</header>

<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="card">
<h2>Gerar carteirinhas de toda a guarnição</h2>
<p class="muted">Somente os Administradores Gerais podem emitir, substituir foto ou alterar dados de carteirinha. Cada integrante pode apenas visualizar/apresentar a própria carteirinha depois de emitida.</p>
<form method="post" class="issue-all">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="issue_all">
<label>Justificativa da emissão/atualização em lote
<input name="justification" required minlength="5" placeholder="Ex.: Emissão anual das carteirinhas da guarnição">
</label>
<button class="primary">🪪 Gerar / atualizar todas</button>
</form>
<p><a class="button-link" href="carteirinhas_imprimir.php" target="_blank">🖨️ Abrir folha com todas para impressão</a></p>
</section>

<section>
<div class="section-head"><h2>Carteirinhas cadastradas</h2><span class="pill"><?=count($rows)?> integrantes</span></div>
<div class="table-wrap"><table>
<thead><tr><th>Foto 3x4</th><th>BC / Nome</th><th>Matrícula</th><th>Perfil / Equipe</th><th>Emissão</th><th>Ações</th></tr></thead>
<tbody>
<?php foreach($rows as $x):?>
<tr>
<td><?php if($x['photo_path']):?><img class="card-photo-thumb" src="user_photo.php?id=<?=$x['id']?>&v=<?=urlencode((string)($x['card_updated_at']??''))?>" alt="Foto 3x4"><?php else:?><div class="card-photo-empty">SEM<br>FOTO<br>3x4</div><?php endif;?></td>
<td><strong><?=e($x['bc_name']?:$x['name'])?></strong><div class="small"><?=e($x['name'])?></div></td>
<td><strong><?=e($x['registration_number']?:'-')?></strong><div class="small">Cert.: <?=e($x['firefighter_certificate_number']?:'-')?></div></td>
<td><?=e(role_label($x['role']))?><div class="small"><?=e($x['team']?:'SEM EQUIPE')?></div></td>
<td><?=$x['card_issued_at']?e(date('d/m/Y H:i',strtotime($x['card_issued_at']))):'<span class="badge">NÃO EMITIDA</span>'?></td>
<td><div class="card-actions">
<a class="button-link" href="carteirinha_editar.php?id=<?=$x['id']?>">✏️ Editar</a>
<a class="button-link" href="carteirinha.php?id=<?=$x['id']?>" target="_blank">🪪 Visualizar</a>
</div></td>
</tr>
<?php endforeach;?>
</tbody></table></div>
</section>
</main><script src="assets/security.js"></script></body></html>
