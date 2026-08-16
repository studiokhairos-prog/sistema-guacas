<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();$id=(int)($_GET['id']??$_POST['id']??0);
$st=$pdo->prepare("SELECT id,name,bc_name,registration_number,registered_signature_path,registered_signature_updated_at,deleted_at FROM users WHERE id=?");$st->execute([$id]);$target=$st->fetch();
if(!$target||$target['deleted_at']){http_response_code(404);exit('CADASTRO NÃO ENCONTRADO.');}
$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),$_POST['csrf']??''))exit('CSRF INVÁLIDO');
 $action=$_POST['action']??'';$just=upper_text($_POST['justification']??'');
 if(mb_strlen($just)<5)$err='INFORME UMA JUSTIFICATIVA COM PELO MENOS 5 CARACTERES.';
 else{
  try{
   if($action==='save'){
    $new=save_registered_signature_data((string)($_POST['signature_data']??''));$old=(string)($target['registered_signature_path']??'');$now=now_iso();
    $pdo->prepare("UPDATE users SET registered_signature_path=?,registered_signature_updated_at=?,registered_signature_updated_by=? WHERE id=?")->execute([$new,$now,$admin['id'],$id]);
    if($old!=='')delete_registered_signature_file($old);
    admin_audit($pdo,$admin['id'],'UPDATE_SIGNATURE','USER',(string)$id,$just,'ASSINATURA CADASTRADA/ATUALIZADA PARA '.$target['registration_number']);$msg='ASSINATURA CADASTRADA COM SUCESSO.';
   }elseif($action==='delete'){
    $old=(string)($target['registered_signature_path']??'');$pdo->prepare("UPDATE users SET registered_signature_path=NULL,registered_signature_updated_at=?,registered_signature_updated_by=? WHERE id=?")->execute([now_iso(),$admin['id'],$id]);delete_registered_signature_file($old);
    admin_audit($pdo,$admin['id'],'DELETE_SIGNATURE','USER',(string)$id,$just,'ASSINATURA CADASTRADA REMOVIDA');$msg='ASSINATURA REMOVIDA.';
   }else $err='AÇÃO INVÁLIDA.';
  }catch(Throwable $e){$err=$e->getMessage();}
  $st->execute([$id]);$target=$st->fetch();
 }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assinatura - <?=e($target['bc_name']?:$target['name'])?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<button class="back-floating" onclick="history.back()">← VOLTAR</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>ADMIN GERAL — ASSINATURA CADASTRADA</span></div></div><div class="right"><a href="usuario_editar.php?id=<?=$id?>">CADASTRO</a><a href="usuarios.php">BOMBEIROS</a><a href="logout.php">SAIR</a></div></header>
<main class="layout"><?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card signature-admin-card"><div><h2><?=e($target['bc_name']?:$target['name'])?></h2><p><?=e($target['name'])?></p><div class="registration-highlight"><?=e($target['registration_number']?:'SEM MATRÍCULA')?></div><?php if($target['registered_signature_path']):?><p><img class="registered-signature-preview" src="assinatura_cadastrada_imagem.php?id=<?=$id?>&v=<?=urlencode((string)$target['registered_signature_updated_at'])?>" alt="Assinatura cadastrada"></p><p class="muted">ATUALIZADA: <?=e($target['registered_signature_updated_at']??'-')?></p><?php else:?><p class="muted">NENHUMA ASSINATURA CADASTRADA.</p><?php endif;?></div>
<div><h2>CAPTURAR ASSINATURA OFICIAL</h2><p class="notice"><strong>USO CONTROLADO:</strong> O ADMIN GERAL CADASTRA O TRAÇO DA ASSINATURA. NAS FICHAS APH, O BOMBEIRO AINDA PRECISARÁ INFORMAR SEU Nº DE CADASTRO E CONFIRMAR A PRÓPRIA SENHA PARA APLICÁ-LA.</p>
<form method="post" id="registeredSigForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="save"><input type="hidden" name="signature_data" id="signature_data">
<canvas id="pad" class="signature-pad" width="800" height="260"></canvas><div class="grid2"><button type="button" id="clear">LIMPAR</button><button class="primary">SALVAR ASSINATURA CADASTRADA</button></div><label>JUSTIFICATIVA / OBSERVAÇÃO<textarea name="justification" minlength="5" required placeholder="EX.: CADASTRO INICIAL DA ASSINATURA DO BOMBEIRO"></textarea></label></form>
<?php if($target['registered_signature_path']):?><form method="post" onsubmit="return confirm('REMOVER A ASSINATURA CADASTRADA?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="justification" value="REMOÇÃO DA ASSINATURA CADASTRADA PELO ADMIN GERAL"><button class="danger">REMOVER ASSINATURA</button></form><?php endif;?></div></section></main>
<script>const c=document.getElementById('pad'),ctx=c.getContext('2d');ctx.lineWidth=3;ctx.lineCap='round';let draw=false,ink=false;function pos(e){const r=c.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return{x:(t.clientX-r.left)*(c.width/r.width),y:(t.clientY-r.top)*(c.height/r.height)}}function start(e){draw=true;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault()}function move(e){if(!draw)return;const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();ink=true;e.preventDefault()}function end(e){draw=false;e.preventDefault()}['mousedown','touchstart'].forEach(x=>c.addEventListener(x,start,{passive:false}));['mousemove','touchmove'].forEach(x=>c.addEventListener(x,move,{passive:false}));['mouseup','mouseleave','touchend'].forEach(x=>c.addEventListener(x,end,{passive:false}));document.getElementById('clear').onclick=()=>{ctx.clearRect(0,0,c.width,c.height);ink=false};document.getElementById('registeredSigForm').addEventListener('submit',e=>{if(!ink){e.preventDefault();alert('FAÇA A ASSINATURA NO QUADRO.');return}document.getElementById('signature_data').value=c.toDataURL('image/png')});</script><script src="assets/security.js"></script></body></html>