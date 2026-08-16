<?php
require __DIR__ . '/config.php';
$admin = require_user(['ADMIN']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
    SELECT id,name,war_name,bc_name,role,team,active,registration_number,
           firefighter_certificate_number,photo_path,card_issued_at,card_updated_at,deleted_at
      FROM users WHERE id=?
");
$st->execute([$id]);
$x = $st->fetch();
if (!$x || $x['deleted_at']) { http_response_code(404); exit('Cadastro não encontrado.'); }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) exit('CSRF inválido');
    $action = $_POST['action'] ?? '';
    $just = trim($_POST['justification'] ?? '');

    if (mb_strlen($just) < 5) {
        $err = 'Informe uma justificativa com pelo menos 5 caracteres.';
    } else {
        try {
            if ($action === 'photo') {
                $old = (string)($x['photo_path'] ?? '');
                $newPhoto = store_user_photo($_FILES['photo'] ?? []);
                $now = now_iso();
                $up = $pdo->prepare("UPDATE users SET photo_path=?,card_updated_at=?,card_updated_by=? WHERE id=?");
                $up->execute([$newPhoto,$now,$admin['id'],$id]);
                if ($old !== '') delete_user_photo_file($old);
                admin_audit($pdo,(int)$admin['id'],'UPDATE_PHOTO','CARD',(string)$id,$just,'Foto 3x4 substituída.');
                $msg = 'Foto 3x4 atualizada.';
            } elseif ($action === 'remove_photo') {
                $old = (string)($x['photo_path'] ?? '');
                $now = now_iso();
                $up = $pdo->prepare("UPDATE users SET photo_path=NULL,card_updated_at=?,card_updated_by=? WHERE id=?");
                $up->execute([$now,$admin['id'],$id]);
                delete_user_photo_file($old);
                admin_audit($pdo,(int)$admin['id'],'REMOVE_PHOTO','CARD',(string)$id,$just,'Foto 3x4 removida.');
                $msg = 'Foto removida.';
            } elseif ($action === 'issue') {
                issue_user_card($pdo,$id,(int)$admin['id']);
                admin_audit($pdo,(int)$admin['id'],'ISSUE','CARD',(string)$id,$just,'Carteirinha emitida/reemitida.');
                $msg = 'Carteirinha emitida/reemitida.';
            } else {
                $err = 'Ação inválida.';
            }
        } catch(Throwable $e) {
            $err = $e->getMessage();
        }
    }

    $st->execute([$id]);
    $x = $st->fetch();
}
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editar carteirinha - <?=e($x['bc_name']?:$x['name'])?></title>
<link rel="stylesheet" href="assets/app.css">
<style>
.photo-editor{display:grid;grid-template-columns:150px 1fr;gap:22px;align-items:start}
.photo-3x4{width:120px;height:160px;object-fit:cover;border:4px solid #f2b51d;border-radius:10px;background:#eee;box-shadow:0 8px 20px #0002}
.photo-placeholder{width:120px;height:160px;border:3px dashed #b89442;border-radius:10px;background:#fff8e8;display:grid;place-items:center;text-align:center;font-weight:800;color:#7b5b16}
.admin-lock{border-left:5px solid #b10f18;background:#fff5e5;padding:12px;border-radius:8px}
@media(max-width:650px){.photo-editor{grid-template-columns:1fr}.photo-3x4,.photo-placeholder{margin:auto}}
</style></head>
<body>
<button type="button" class="back-floating no-print" onclick="history.length>1?history.back():location.href='carteirinhas.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div>
<strong><?=e(app_display_name())?></strong><span>Admin Geral — Editar Carteirinha</span></div></div>
<div class="right"><a href="carteirinhas.php">Carteirinhas</a><a href="carteirinha.php?id=<?=$id?>" target="_blank">Visualizar</a><a href="logout.php">Sair</a></div></header>

<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<section class="card">
<h2><?=e($x['bc_name']?:$x['name'])?></h2>
<div class="admin-lock"><strong>🔒 Edição restrita:</strong> somente os Administradores Gerais podem editar a foto ou emitir/reemitir esta carteirinha.</div>

<div class="photo-editor" style="margin-top:18px">
<div>
<?php if($x['photo_path']):?>
<img class="photo-3x4" src="user_photo.php?id=<?=$id?>&v=<?=urlencode((string)($x['card_updated_at']??''))?>" alt="Foto 3x4">
<?php else:?><div class="photo-placeholder">FOTO<br>3x4</div><?php endif;?>
</div>
<div>
<p><strong>Nome:</strong> <?=e($x['name'])?></p>
<p><strong>Nome de farda:</strong> <?=e($x['bc_name']?:'-')?></p>
<p><strong>Matrícula:</strong> <?=e($x['registration_number']?:'-')?></p>
<p><strong>Certificado:</strong> <?=e($x['firefighter_certificate_number']?:'NÃO INFORMADO')?></p>
<p><strong>Perfil:</strong> <?=e(role_label($x['role']))?> · <strong>Equipe:</strong> <?=e($x['team']?:'SEM EQUIPE')?></p>
<p><strong>Situação da carteirinha:</strong> <?=$x['card_issued_at']?'EMITIDA em '.e(date('d/m/Y H:i',strtotime($x['card_issued_at']))):'NÃO EMITIDA'?></p>
</div>
</div>
</section>

<section class="card">
<h2>Foto oficial 3x4</h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="photo">
<label>Selecionar foto 3x4
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
</label>
<p class="muted">Formatos aceitos: JPG, PNG ou WEBP. Máximo 5 MB. A carteirinha recorta visualmente a foto no formato 3x4.</p>
<label>Justificativa / observação da alteração
<textarea name="justification" required minlength="5" placeholder="Ex.: Foto oficial atualizada no cadastro"></textarea></label>
<button class="primary">📷 Salvar foto 3x4</button>
</form>

<?php if($x['photo_path']):?>
<form method="post" style="margin-top:12px" onsubmit="return confirm('Remover a foto 3x4 desta carteirinha?');">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="remove_photo">
<input type="hidden" name="justification" value="Remoção da foto 3x4 pelo Admin Geral">
<button>Remover foto</button>
</form>
<?php endif;?>
</section>

<section class="card">
<h2>Emissão da carteirinha</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="issue">
<label>Justificativa da emissão / reemissão
<textarea name="justification" required minlength="5" placeholder="Ex.: Primeira emissão / atualização anual / substituição por nova foto"></textarea></label>
<button class="primary">🪪 Emitir / Reemitir Carteirinha</button>
</form>
</section>
</main><script src="assets/security.js"></script></body></html>
