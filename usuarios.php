<?php
require __DIR__ . '/config.php';
$u = require_user(['ADMIN']);
$pdo = db();
$msg = $err = '';

if (isset($_GET['deleted'])) $msg='Cadastro excluído do uso operacional e enviado para a lixeira administrativa.';
if (isset($_GET['restored'])) $msg='Cadastro restaurado.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) exit('CSRF inválido');
    $name = upper_text($_POST['name'] ?? '');
    $warName = upper_text($_POST['war_name'] ?? '');
    $username = upper_text($_POST['username'] ?? '');
    $email = lower_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'CAMPO';
    $bloodType = $_POST['blood_type'] ?? 'NÃO SABE';
    $certificate = upper_text($_POST['firefighter_certificate_number'] ?? '');
    $cpf = normalize_cpf($_POST['cpf'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $identityRequired = $role !== 'STAFF';

    if (mb_strlen($name) < 5 || mb_strlen($warName) < 2 || mb_strlen($username) < 3 || strlen($password) < 10 || !in_array($role,['ADMIN','BASE','CAMPO','STAFF'],true) || !in_array($bloodType,blood_type_options(),true) || ($role!=='STAFF' && mb_strlen($certificate)<3) || ($identityRequired && (!valid_cpf($cpf) || !valid_birth_date($birthDate))) || (!$identityRequired && (($cpf!=='' && !valid_cpf($cpf)) || ($birthDate!=='' && !valid_birth_date($birthDate))))) {
        $err = 'Revise os dados. Para bombeiros, certificado, CPF e data de nascimento válidos são obrigatórios. Para STAFF, CPF/data podem ser cadastrados para habilitar a recuperação de senha.';
    } elseif ($role === 'ADMIN' && admin_count($pdo) >= MAX_MAIN_ADMINS) {
        $err = 'Limite atingido: o sistema permite no máximo 4 Administradores Gerais.';
    } else {
        $newPhoto = null;
        try {
            $newPhoto = receive_user_photo_from_form('photo_3x4','webcam_photo_data');

            $now = now_iso();
            $cpfHash = $cpf !== '' ? store_cpf_hash($cpf) : null;
            $st = $pdo->prepare("INSERT INTO users(name,war_name,username,email,password_hash,role,team,financial_status,blood_type,firefighter_certificate_number,cpf_hash,cpf_last4,birth_date,photo_path,card_updated_at,card_updated_by,created_at) VALUES(?,?,?,?,?,?,NULL,'REGULAR',?,?,?,?,?,?,?,?,?)");
            $st->execute([
                $name,
                $warName,
                $username,
                $email!==''?$email:null,
                password_hash($password,PASSWORD_DEFAULT),
                $role,
                $bloodType,
                $certificate?:null,
                $cpfHash,
                $cpf!=='' ? cpf_last4($cpf) : null,
                $birthDate!=='' ? $birthDate : null,
                $newPhoto,
                $newPhoto ? $now : null,
                $newPhoto ? (int)$u['id'] : null,
                $now
            ]);
            $newId = (int)$pdo->lastInsertId();

            recalculate_all_bc_names($pdo);
            ensure_user_registration_numbers($pdo);

            $q = $pdo->prepare("SELECT bc_name,registration_number FROM users WHERE id=?");
            $q->execute([$newId]);
            $createdUser = $q->fetch();

            admin_audit(
                $pdo,
                (int)$u['id'],
                'CREATE',
                'USER',
                (string)$newId,
                'Cadastro realizado pelo Admin Geral',
                'Perfil: '.$role.'; situação financeira: REGULAR; foto 3x4: '.($newPhoto ? 'SIM' : 'NÃO')
            );

            $msg = 'CADASTRO CRIADO. IDENTIFICAÇÃO OPERACIONAL: ' . (string)($createdUser['bc_name']??'') . '. Nº CADASTRO GUACAS: ' . (string)($createdUser['registration_number']??'') . '.';
            if ($newPhoto) {
                $msg .= ' A foto 3x4 já foi vinculada automaticamente à carteirinha.';
            }
        } catch(Throwable $e) {
            if ($newPhoto) delete_user_photo_file($newPhoto);
            $err = 'Não foi possível criar o cadastro. ' . $e->getMessage();
        }
    }
}

recalculate_all_bc_names($pdo); sync_users_team_labels($pdo);
$showDeleted = ($_GET['show'] ?? '') === 'deleted';
$sql = "SELECT id,name,war_name,bc_name,username,role,team,active,COALESCE(financial_status,'REGULAR') financial_status,COALESCE(blood_type,'NÃO SABE') blood_type,registration_number,firefighter_certificate_number,email,photo_path,card_updated_at,registered_signature_path,registered_signature_updated_at,created_at,deleted_at,delete_reason FROM users ".($showDeleted?"WHERE deleted_at IS NOT NULL":"WHERE deleted_at IS NULL")." ORDER BY active DESC, role='ADMIN' DESC, id";
$users = $pdo->query($sql)->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(app_display_name())?> - Usuários</title><link rel="stylesheet" href="assets/app.css"></head>
<body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Administração de Bombeiros</span></div></div><div class="right"><span class="pill"><?=admin_count($pdo)?> / <?=MAX_MAIN_ADMINS?> admins gerais</span><a href="base.php">Central</a><a href="equipes.php">Equipes</a><a href="configuracoes.php">Configurações</a><a href="carteirinhas.php">Carteirinhas</a><a href="viaturas.php">Viaturas</a><a href="materiais.php">Materiais</a><a href="relatorios.php">Relatórios</a><a href="seguranca.php">Segurança</a><a href="logout.php">Sair</a></div></header>
<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<?php if(!$showDeleted):?>
<section class="card"><h2>Novo bombeiro / integrante</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="grid2"><label>Nome completo<input name="name" required placeholder="Nome civil completo"></label><label>Nome de farda<input name="war_name" placeholder="Ex.: SILVA" required></label></div><div class="grid2"><label>Usuário de acesso<input name="username" required></label><label>E-mail <span class="small">(sempre minúsculo)</span><input type="email" name="email" data-lowercase="email" placeholder="nome@exemplo.com"></label></div><div class="grid2"><label>CPF <span class="small">(recuperação de senha)</span><input name="cpf" inputmode="numeric" autocomplete="off" placeholder="Obrigatório para bombeiros"></label><label>Data de nascimento <span class="small">(recuperação de senha)</span><input type="date" name="birth_date"></label></div><div class="grid2"><label>Perfil<select name="role"><option value="CAMPO">CAMPO</option><option value="BASE">BASE</option><option value="STAFF">STAFF</option><option value="ADMIN">ADMIN GERAL</option></select></label><label>Nº Cadastro GUACAS<input value="GERADO AUTOMATICAMENTE AO SALVAR" readonly></label></div><div class="grid2"><label>Nº do Certificado de Bombeiro Civil<input name="firefighter_certificate_number" placeholder="Obrigatório para bombeiros; STAFF pode deixar em branco"></label><label class="blood-register-field">🩸 Tipo sanguíneo
<select name="blood_type" required>
<?php foreach(blood_type_options() as $bt):?><option value="<?=e($bt)?>" <?=$bt==='NÃO SABE'?'selected':''?>><?=e($bt)?></option><?php endforeach;?>
</select>
<span class="muted">Escolha o tipo informado pelo bombeiro. Caso não saiba, mantenha <strong>NÃO SABE</strong>. O tipo será mostrado em destaque na carteirinha.</span>
</label></div>
<div class="photo-capture-box" data-photo-camera>
  <div class="photo-capture-preview photo-register-preview" data-photo-preview><span>FOTO<br>3x4</span></div>
  <div class="photo-capture-controls">
    <strong>📷 Foto 3x4 do integrante</strong>
    <p class="muted">A foto escolhida ou tirada pela câmera será salva no cadastro e usada automaticamente na Carteirinha da Guarnição.</p>

    <input type="hidden" name="webcam_photo_data" data-camera-data>

    <div class="photo-choice-buttons">
      <label class="photo-file-button">
        🖼️ ESCOLHER FOTO
        <input type="file" name="photo_3x4" accept="image/jpeg,image/png,image/webp" capture="user" data-photo-file>
      </label>
      <button type="button" class="camera-button" data-open-camera>📷 TIRAR FOTO PELA CÂMERA</button>
      <button type="button" class="photo-clear-button" data-clear-photo hidden>↺ Limpar foto</button>
    </div>

    <div class="camera-state" data-camera-state>JPG, PNG ou WEBP até 5 MB. Na câmera, o sistema enquadra a imagem automaticamente em 3x4.</div>
  </div>

  <div class="camera-modal" data-camera-modal hidden>
    <div class="camera-dialog">
      <div class="camera-dialog-head">
        <strong>📷 FOTO 3x4 — CÂMERA</strong>
        <button type="button" data-close-camera>✕</button>
      </div>
      <div class="camera-video-frame">
        <video data-camera-video playsinline muted></video>
        <div class="camera-guide"><span>Centralize rosto e ombros</span></div>
      </div>
      <canvas data-camera-canvas hidden></canvas>
      <div class="camera-actions">
        <button type="button" data-switch-camera>🔄 Trocar câmera</button>
        <button type="button" class="primary" data-capture-camera>📸 CAPTURAR FOTO</button>
        <button type="button" data-close-camera>Cancelar</button>
      </div>
    </div>
  </div>
</div>
<label>Senha inicial<input name="password" type="password" minlength="10" required></label><button class="primary">Criar cadastro</button></form>
<div class="notice"><strong>Recuperação de senha:</strong> CPF e data de nascimento ficam vinculados ao cadastro. O sistema não exibe o CPF completo após o salvamento; ele é protegido para conferência na recuperação.</div>
<div class="notice"><strong>Inadimplência:</strong> o Admin Geral pode marcar o integrante como REGULAR ou INADIMPLENTE sem apagar o histórico operacional.</div><div class="notice"><strong>Exclusão:</strong> somente Admin Geral pode excluir. O cadastro sai do uso operacional, mas o histórico permanece protegido na lixeira administrativa.</div></section>
<?php endif;?>
<section><div class="section-head"><h2><?=$showDeleted?'Lixeira de cadastros':'Cadastrados'?></h2><a class="button-link" href="usuarios.php<?=$showDeleted?'':'?show=deleted'?>"><?=$showDeleted?'Ver ativos':'Ver lixeira'?></a></div><div class="table-wrap"><table><thead><tr><th>Foto</th><th>BC</th><th>Matrícula</th><th>Nome completo</th><th>Perfil</th><th>Equipe</th><th>Financeiro</th><th>Acesso</th><th>Ação</th></tr></thead><tbody>
<?php if(!$users):?><tr><td colspan="9">Nenhum cadastro.</td></tr><?php endif;?>
<?php foreach($users as $x):?><tr><td><?php if(!empty($x['photo_path'])):?><img class="user-photo-thumb" src="user_photo.php?id=<?=$x['id']?>&v=<?=urlencode((string)($x['card_updated_at']??''))?>" alt="Foto 3x4"><?php else:?><span class="photo-mini-empty">3x4</span><?php endif;?></td><td><strong><?=e($x['bc_name']??'-')?></strong></td><td><strong><?=e($x['registration_number']??'-')?></strong><div class="small">Cert.: <?=e($x['firefighter_certificate_number']??'-')?> · Sangue: <?=e($x['blood_type']??'NÃO SABE')?></div></td><td><?=e($x['name'])?><div class="small"><?=e($x['username'])?><?php if(!empty($x['email'])):?> · <?=e($x['email'])?><?php endif;?></div></td><td><?=e(role_label($x['role']))?></td><td><?=e($x['team']??'-')?></td><td><span class="badge <?=$x['financial_status']==='INADIMPLENTE'?'financial-bad':'financial-ok'?>"><?=e(financial_status_label($x['financial_status']))?></span></td><td><?=$x['deleted_at']?'EXCLUÍDO':($x['active']?'ATIVO':'INATIVO')?></td><td><?php if(!$x['deleted_at']):?><a class="button-link" href="usuario_editar.php?id=<?=$x['id']?>">Editar</a> <a class="button-link" href="carteirinha_editar.php?id=<?=$x['id']?>">Gerenciar carteirinha</a> <a class="button-link" href="assinatura_bombeiro.php?id=<?=$x['id']?>">✍️ Assinatura <?=$x['registered_signature_path']?'✓':''?></a><?php else:?><form method="post" action="usuario_action.php" class="inline-form" onsubmit="return confirm('Restaurar este cadastro?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$x['id']?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="justification" value="Restauração pela lixeira administrativa"><button>Restaurar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
</main>
<style>
.blood-register-field{padding:10px;border:2px solid #b10f18;border-radius:10px;background:#fff3f0}.blood-register-field select{font-weight:900;color:#7b0910;border:2px solid #f2b51d;background:#fff;font-size:16px}.blood-register-field .muted{display:block;margin-top:6px;font-weight:500}.photo-register-box{display:grid;grid-template-columns:112px 1fr;gap:16px;align-items:center;margin:14px 0;padding:14px;border:1px solid #d8b96e;border-radius:12px;background:#fff9ee}
.photo-register-preview{width:90px;height:120px;border:3px dashed #b78a28;border-radius:10px;background:#f2eee7;display:grid;place-items:center;text-align:center;font-weight:900;color:#7b5b16;overflow:hidden}
.photo-register-preview img{width:100%;height:100%;object-fit:cover}
.user-photo-thumb{width:38px;height:51px;object-fit:cover;border:2px solid #f2b51d;border-radius:5px;background:#eee}
.photo-mini-empty{width:38px;height:51px;display:grid;place-items:center;border:2px dashed #b78a28;border-radius:5px;font-size:10px;font-weight:800;color:#7b5b16;background:#fff8e8}
@media(max-width:650px){.photo-register-box{grid-template-columns:1fr}.photo-register-preview{margin:auto}}
</style>

<script src="assets/photo_camera.js"></script><script src="assets/security.js"></script></body></html>
