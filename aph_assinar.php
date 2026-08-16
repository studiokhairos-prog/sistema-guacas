<?php
require __DIR__ . '/config.php';
$u=require_user();
$pdo=db();
$id=(int)($_GET['id']??$_POST['id']??0);
$r=load_aph($id);
if(!$r || !aph_can_access($u,$r)){ http_response_code(404); exit('Ficha não encontrada.'); }
if($r['status']==='ARQUIVADA'){ http_response_code(409); exit('Ficha arquivada não aceita novas assinaturas.'); }

$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF INVÁLIDO');
    $mode=$_POST['mode']??'registered';
    $password=$_POST['password']??'';
    $capacity=upper_text($_POST['capacity']??'BOMBEIRO ATENDENTE');
    if(!in_array($capacity,['BOMBEIRO ATENDENTE','STAFF / RESPONSÁVEL DE PLANTÃO'],true)) $capacity='BOMBEIRO ATENDENTE';

    if($mode==='registered'){
        $registration=upper_text($_POST['registration_number']??'');
        $st=$pdo->prepare("SELECT * FROM users WHERE registration_number=? AND active=1 AND deleted_at IS NULL");$st->execute([$registration]);$signer=$st->fetch();
        if(!$signer){$err='Nº DE CADASTRO NÃO ENCONTRADO OU INATIVO.';}
        elseif(!password_verify($password,$signer['password_hash'])){$err='SENHA DO BOMBEIRO INVÁLIDA.';}
        elseif(!aph_can_access($signer,$r)){$err='ESTE BOMBEIRO NÃO POSSUI ACESSO A ESTA OCORRÊNCIA.';}
        else{
            $registered=registered_signature_absolute_path($signer['registered_signature_path']??null);
            if(!$registered){$err='ESTE BOMBEIRO AINDA NÃO POSSUI ASSINATURA CADASTRADA PELO ADMIN GERAL.';}
            else{
                if(!is_dir(SIGNATURE_DIR))mkdir(SIGNATURE_DIR,0775,true);
                $name='aph_'.$id.'_u_'.$signer['id'].'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.png';$path=SIGNATURE_DIR.'/'.$name;
                if(!copy($registered,$path)){$err='NÃO FOI POSSÍVEL APLICAR A ASSINATURA CADASTRADA.';}
                else{
                    $rel='data/signatures/'.$name;
                    $ins=$pdo->prepare("INSERT INTO aph_signatures(aph_id,signer_user_id,signer_name,signer_bc_name,signer_system_role,signature_capacity,signature_path,signed_at,document_hash,valid) VALUES(?,?,?,?,?,?,?,?,?,1)");
                    $ins->execute([$id,$signer['id'],$signer['name'],$signer['bc_name'],$signer['role'],$capacity,$rel,now_iso(),$r['content_hash']]);
                    aph_audit($pdo,$id,'ASSINADA',$signer['id'],'ASSINATURA CADASTRADA APLICADA POR Nº '.$registration.' · '.$capacity);
                    header('Location: aph.php?id='.$id.'&signed=1');exit;
                }
            }
        }
    }else{
        $sig=$_POST['signature_data']??'';
        if(!password_verify($password,$u['password_hash'])){$err='SENHA DE CONFIRMAÇÃO INVÁLIDA.';}
        elseif(!preg_match('#^data:image/png;base64,(.+)$#',$sig,$m)){$err='FAÇA A ASSINATURA NO QUADRO ANTES DE CONFIRMAR.';}
        else{
            $raw=base64_decode($m[1],true);
            if($raw===false||strlen($raw)<200||strlen($raw)>2_000_000){$err='ASSINATURA INVÁLIDA OU MUITO GRANDE.';}
            else{
                if(!is_dir(SIGNATURE_DIR))mkdir(SIGNATURE_DIR,0775,true);$name='aph_'.$id.'_u_'.$u['id'].'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.png';$path=SIGNATURE_DIR.'/'.$name;file_put_contents($path,$raw,LOCK_EX);$rel='data/signatures/'.$name;
                $st=$pdo->prepare("INSERT INTO aph_signatures(aph_id,signer_user_id,signer_name,signer_bc_name,signer_system_role,signature_capacity,signature_path,signed_at,document_hash,valid) VALUES(?,?,?,?,?,?,?,?,?,1)");$st->execute([$id,$u['id'],$u['name'],$u['bc_name'],$u['role'],$capacity,$rel,now_iso(),$r['content_hash']]);aph_audit($pdo,$id,'ASSINADA',$u['id'],'ASSINATURA DESENHADA · '.$capacity);header('Location: aph.php?id='.$id.'&signed=1');exit;
            }
        }
    }
}?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assinar APH</title><link rel="stylesheet" href="assets/app.css"></head>
<body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}" aria-label="Voltar para a página anterior">← Voltar</button><header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e($r['code'])?></strong><span>Assinatura eletrônica interna</span></div></div><div class="right"><a href="aph.php?id=<?=$id?>">Voltar</a></div></header>
<main class="layout narrow">
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card"><h2>ASSINATURA AUTOMÁTICA CADASTRADA</h2><p class="notice"><strong>FORMA MAIS RÁPIDA:</strong> INFORME O Nº DE CADASTRO GUACAS DO BOMBEIRO E A SENHA PESSOAL DO PRÓPRIO SIGNATÁRIO. O SISTEMA COPIA A ASSINATURA CADASTRADA PARA ESTA VERSÃO DA FICHA.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="mode" value="registered">
<label>Nº DE CADASTRO GUACAS<input name="registration_number" required value="<?=e($u['registration_number']??'')?>" placeholder="EX.: GUA-2026-000001"></label>
<label>ASSINANDO NA CONDIÇÃO DE<select name="capacity"><option>BOMBEIRO ATENDENTE</option><option>STAFF / RESPONSÁVEL DE PLANTÃO</option></select></label>
<label>SENHA PESSOAL DO BOMBEIRO<input type="password" name="password" required autocomplete="current-password"></label><button class="primary">✍️ APLICAR ASSINATURA CADASTRADA</button></form></section>
<section class="card"><h2>OU ASSINAR MANUALMENTE NESTE MOMENTO</h2><p><strong>SIGNATÁRIO CONECTADO:</strong> <?=e($u['bc_name'])?> — <?=e($u['name'])?></p><form method="post" id="sigForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="mode" value="draw"><input type="hidden" name="signature_data" id="signature_data"><label>ASSINANDO NA CONDIÇÃO DE<select name="capacity"><option>BOMBEIRO ATENDENTE</option><option>STAFF / RESPONSÁVEL DE PLANTÃO</option></select></label><label>CONFIRME SUA SENHA<input type="password" name="password" required autocomplete="current-password"></label><label>ASSINATURA</label><canvas id="pad" class="signature-pad" width="800" height="260"></canvas><div class="grid2"><button type="button" id="clear">LIMPAR ASSINATURA</button><button class="primary">CONFIRMAR E ASSINAR</button></div></form></section>
</main>
<script>
const c=document.getElementById('pad'),ctx=c.getContext('2d'); ctx.lineWidth=3;ctx.lineCap='round';let draw=false,ink=false;
function pos(e){const r=c.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return {x:(t.clientX-r.left)*(c.width/r.width),y:(t.clientY-r.top)*(c.height/r.height)}}
function start(e){draw=true;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault()}
function move(e){if(!draw)return;const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();ink=true;e.preventDefault()}
function end(e){draw=false;e.preventDefault()}
['mousedown','touchstart'].forEach(x=>c.addEventListener(x,start,{passive:false}));['mousemove','touchmove'].forEach(x=>c.addEventListener(x,move,{passive:false}));['mouseup','mouseleave','touchend'].forEach(x=>c.addEventListener(x,end,{passive:false}));
document.getElementById('clear').onclick=()=>{ctx.clearRect(0,0,c.width,c.height);ink=false;};
document.getElementById('sigForm').addEventListener('submit',e=>{if(!ink){e.preventDefault();alert('Faça sua assinatura no quadro antes de confirmar.');return;}document.getElementById('signature_data').value=c.toDataURL('image/png');});
</script><script src="assets/security.js"></script></body></html>
