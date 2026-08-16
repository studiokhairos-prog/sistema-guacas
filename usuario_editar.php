<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']); $pdo=db(); $id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT id,name,war_name,bc_name,username,email,role,team,active,COALESCE(financial_status,'REGULAR') financial_status,COALESCE(blood_type,'NÃO SABE') blood_type,registration_number,firefighter_certificate_number,photo_path,card_updated_at,registered_signature_path,registered_signature_updated_at,cpf_hash,cpf_last4,birth_date,deleted_at FROM users WHERE id=?");$st->execute([$id]);$target=$st->fetch();
if(!$target||$target['deleted_at']){http_response_code(404);exit('Usuário não encontrado.');}
$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
 $name=upper_text($_POST['name']??'');$war=upper_text($_POST['war_name']??'');$username=upper_text($_POST['username']??'');$email=lower_email($_POST['email']??'');$role=$_POST['role']??'CAMPO';$active=isset($_POST['active'])?1:0;$financial=$_POST['financial_status']??'REGULAR';$bloodType=$_POST['blood_type']??'NÃO SABE';$certificate=upper_text($_POST['firefighter_certificate_number']??'');$newPass=$_POST['new_password']??'';$just=trim($_POST['justification']??'');$cpf=normalize_cpf($_POST['cpf']??'');$birthDate=trim($_POST['birth_date']??'');
 $identityRequired=$role!=='STAFF';
 if(mb_strlen($name)<5||mb_strlen($war)<2||mb_strlen($username)<3||!in_array($role,['ADMIN','BASE','CAMPO','STAFF'],true)||!in_array($financial,['REGULAR','INADIMPLENTE'],true)||!in_array($bloodType,blood_type_options(),true)||($role!=='STAFF'&&mb_strlen($certificate)<3)||mb_strlen($just)<5||($cpf!==''&&!valid_cpf($cpf))||($birthDate!==''&&!valid_birth_date($birthDate))||($identityRequired&&empty($target['cpf_hash'])&&$cpf==='')||($identityRequired&&empty($target['birth_date'])&&$birthDate===''))$err='Preencha os dados obrigatórios. Bombeiros precisam ter CPF e data de nascimento cadastrados para recuperação de senha.';
 elseif($newPass!==''&&strlen($newPass)<10)$err='A nova senha deve ter pelo menos 10 caracteres.';
 elseif($id===(int)$admin['id']&&!$active)$err='Você não pode desativar o próprio acesso conectado.';
 elseif($target['role']==='ADMIN'&&($role!=='ADMIN'||!$active)&&admin_count($pdo)<=1)$err='É obrigatório manter pelo menos um Admin Geral ativo.';
 elseif($target['role']!=='ADMIN'&&$role==='ADMIN'&&$active&&admin_count($pdo)>=MAX_MAIN_ADMINS)$err='Limite de 4 Administradores Gerais atingido.';
 else{
  $newPhoto=null;$oldPhoto=(string)($target['photo_path']??'');
  try{
   $newPhoto=receive_user_photo_from_form('photo_3x4','webcam_photo_data');
   $photoToSave=$newPhoto?:($target['photo_path']??null);
   $cardUpdatedAt=$newPhoto?now_iso():($target['card_updated_at']??null);
   $cardUpdatedBy=$newPhoto?(int)$admin['id']:null;
   $cpfHashToSave=$cpf!==''?store_cpf_hash($cpf):($target['cpf_hash']??null);
   $cpfLast4ToSave=$cpf!==''?cpf_last4($cpf):($target['cpf_last4']??null);
   $birthDateToSave=$birthDate!==''?$birthDate:($target['birth_date']??null);

   if($newPass!==''){
    $up=$pdo->prepare("UPDATE users SET name=?,war_name=?,username=?,email=?,role=?,active=?,financial_status=?,blood_type=?,firefighter_certificate_number=?,cpf_hash=?,cpf_last4=?,birth_date=?,photo_path=?,card_updated_at=COALESCE(?,card_updated_at),card_updated_by=COALESCE(?,card_updated_by),password_hash=? WHERE id=?");
    $up->execute([$name,$war,$username,$email!==''?$email:null,$role,$active,$financial,$bloodType,$certificate?:null,$cpfHashToSave,$cpfLast4ToSave,$birthDateToSave,$photoToSave,$cardUpdatedAt,$cardUpdatedBy,password_hash($newPass,PASSWORD_DEFAULT),$id]);
   }else{
    $up=$pdo->prepare("UPDATE users SET name=?,war_name=?,username=?,email=?,role=?,active=?,financial_status=?,blood_type=?,firefighter_certificate_number=?,cpf_hash=?,cpf_last4=?,birth_date=?,photo_path=?,card_updated_at=COALESCE(?,card_updated_at),card_updated_by=COALESCE(?,card_updated_by) WHERE id=?");
    $up->execute([$name,$war,$username,$email!==''?$email:null,$role,$active,$financial,$bloodType,$certificate?:null,$cpfHashToSave,$cpfLast4ToSave,$birthDateToSave,$photoToSave,$cardUpdatedAt,$cardUpdatedBy,$id]);
   }

   if($newPhoto&&$oldPhoto!=='') delete_user_photo_file($oldPhoto);
   recalculate_all_bc_names($pdo);
   admin_audit($pdo,(int)$admin['id'],'UPDATE','USER',(string)$id,$just,'Cadastro/perfil/acesso/financeiro atualizado para '.$financial.'; foto 3x4: '.($newPhoto?'ATUALIZADA':'MANTIDA'));
   $msg='Cadastro atualizado.'.($newPhoto?' A nova foto 3x4 já está na carteirinha.':'');
   $st->execute([$id]);$target=$st->fetch();
  }catch(Throwable $e){
   if($newPhoto) delete_user_photo_file($newPhoto);
   $err='Não foi possível atualizar. '.$e->getMessage();
  }
 }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Editar usuário - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body><button type="button" class="back-floating no-print" onclick="history.length>1?history.back():location.href='usuarios.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Admin Geral — Editar cadastro</span></div></div><div class="right"><a href="usuarios.php">Usuários</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card"><h2>Editar <?=e($target['bc_name']?:$target['name'])?></h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="grid2"><label>Nome completo<input name="name" required value="<?=e($target['name'])?>"></label><label>Nome de farda<input name="war_name" required value="<?=e($target['war_name'])?>"></label></div><div class="grid2"><label>Usuário<input name="username" required value="<?=e($target['username'])?>"></label><label>E-mail <span class="small">(minúsculo)</span><input type="email" name="email" data-lowercase="email" value="<?=e($target['email']??'')?>"></label></div><div class="grid2"><label>CPF cadastrado<input value="<?=e(cpf_masked_from_last4($target['cpf_last4']??''))?>" readonly><span class="small">Para trocar, informe um novo CPF abaixo.</span></label><label>Data de nascimento<input type="date" name="birth_date" value="<?=e($target['birth_date']??'')?>"></label></div><label>Novo CPF / confirmar CPF <span class="small">(deixe vazio para manter o atual)</span><input name="cpf" inputmode="numeric" autocomplete="off" placeholder="Informe somente se for cadastrar ou substituir"></label><div class="grid2"><label>Perfil<select name="role"><option value="CAMPO" <?=$target['role']==='CAMPO'?'selected':''?>>CAMPO</option><option value="BASE" <?=$target['role']==='BASE'?'selected':''?>>BASE</option><option value="STAFF" <?=$target['role']==='STAFF'?'selected':''?>>STAFF</option><option value="ADMIN" <?=$target['role']==='ADMIN'?'selected':''?>>ADMIN GERAL</option></select></label><label>Nº Cadastro GUACAS<input value="<?=e($target['registration_number']?:'-')?>" readonly></label></div><div class="grid2"><label>Situação financeira<select name="financial_status"><option value="REGULAR" <?=$target['financial_status']==='REGULAR'?'selected':''?>>REGULAR</option><option value="INADIMPLENTE" <?=$target['financial_status']==='INADIMPLENTE'?'selected':''?>>INADIMPLENTE</option></select></label><label class="checkbox-line"><input type="checkbox" name="active" value="1" <?=$target['active']?'checked':''?>> Acesso ativo</label></div><div class="grid3"><label>Matrícula da Guarnição<input value="<?=e($target['registration_number']?:'-')?>" readonly></label><label>Nº do Certificado de Bombeiro Civil<input name="firefighter_certificate_number" value="<?=e($target['firefighter_certificate_number']??'')?>" placeholder="STAFF sem certificado pode deixar vazio"></label><label class="blood-edit-field">🩸 Tipo sanguíneo<select name="blood_type"><?php foreach(blood_type_options() as $bt):?><option value="<?=e($bt)?>" <?=$target['blood_type']===$bt?'selected':''?>><?=e($bt)?></option><?php endforeach;?></select></label></div><div class="grid2"><label>Nova senha (opcional)<input type="password" name="new_password" minlength="10" placeholder="Deixe vazio para manter"></label><label>Equipe atual<input value="<?=e($target['team']?:'Sem equipe')?>" readonly></label></div>
<div class="photo-capture-box" data-photo-camera>
  <div class="photo-capture-preview photo-edit-preview" data-photo-preview>
    <?php if(!empty($target['photo_path'])):?><img src="user_photo.php?id=<?=$id?>&v=<?=urlencode((string)($target['card_updated_at']??''))?>" alt="Foto 3x4"><?php else:?><span>FOTO<br>3x4</span><?php endif;?>
  </div>
  <div class="photo-capture-controls">
    <strong>📷 Alterar foto 3x4</strong>
    <p class="muted">Escolha uma imagem ou tire uma nova foto pela câmera. A alteração aparecerá automaticamente na carteirinha.</p>

    <input type="hidden" name="webcam_photo_data" data-camera-data>

    <div class="photo-choice-buttons">
      <label class="photo-file-button">
        🖼️ ESCOLHER FOTO
        <input type="file" name="photo_3x4" accept="image/jpeg,image/png,image/webp" capture="user" data-photo-file>
      </label>
      <button type="button" class="camera-button" data-open-camera>📷 TIRAR NOVA FOTO</button>
      <button type="button" class="photo-clear-button" data-clear-photo hidden>↺ Cancelar nova seleção</button>
    </div>
    <div class="camera-state" data-camera-state>A foto atual será mantida se você não escolher nem capturar outra.</div>
  </div>

  <div class="camera-modal" data-camera-modal hidden>
    <div class="camera-dialog">
      <div class="camera-dialog-head">
        <strong>📷 NOVA FOTO 3x4</strong>
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
<p><a class="button-link" href="carteirinha_editar.php?id=<?=$id?>">🪪 Gerenciar emissão da Carteirinha</a> <a class="button-link" target="_blank" href="carteirinha.php?id=<?=$id?>">Visualizar</a></p><p><a class="button-link" href="assinatura_bombeiro.php?id=<?=$id?>">✍️ Cadastrar / atualizar assinatura do bombeiro</a> <?php if(!empty($target['registered_signature_path'])):?><span class="badge financial-ok">ASSINATURA CADASTRADA ✓</span><?php endif;?></p><label>Justificativa da alteração<textarea name="justification" required></textarea></label><button class="primary">Salvar alterações</button></form></section>
<section class="card danger-zone"><h2>Excluir bombeiro / integrante</h2><p class="muted">A exclusão retira o cadastro do uso operacional e do login, mas preserva referências históricas de ocorrências e fichas. Somente Admin Geral pode executar.</p><form method="post" action="usuario_action.php" onsubmit="return confirm('CONFIRMA a exclusão deste cadastro?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="delete"><label>Justificativa obrigatória<textarea name="justification" minlength="5" required placeholder="Motivo da exclusão"></textarea></label><button class="danger">🗑️ Excluir cadastro</button></form></section>
</main>
<style>
.photo-edit-inline{display:grid;grid-template-columns:112px 1fr;gap:16px;align-items:center;margin:14px 0;padding:14px;border:1px solid #d8b96e;border-radius:12px;background:#fff9ee}
.photo-edit-preview{width:90px;height:120px;object-fit:cover;border:3px solid #f2b51d;border-radius:10px;background:#eee}
.photo-edit-empty{width:90px;height:120px;display:grid;place-items:center;text-align:center;border:3px dashed #b78a28;border-radius:10px;font-weight:900;color:#7b5b16}
@media(max-width:650px){.photo-edit-inline{grid-template-columns:1fr}.photo-edit-preview,.photo-edit-empty{margin:auto}}
</style>


<style>
.blood-edit-field{padding:8px;border:2px solid #b10f18;border-radius:10px;background:#fff3f0}
.blood-edit-field select{font-weight:900;color:#7b0910;border:2px solid #f2b51d;background:#fff}
</style>
<script src="assets/photo_camera.js"></script><script src="assets/security.js"></script></body></html>
