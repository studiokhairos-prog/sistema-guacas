<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);
$pdo=db();
$id=(int)($_GET['id']??0);
$isEdit=$id>0;
$team=['id'=>0,'name'=>'','code'=>'','active'=>1,'notes'=>''];
if($isEdit){
    $st=$pdo->prepare("SELECT * FROM teams WHERE id=?");$st->execute([$id]);$team=$st->fetch();
    if(!$team){http_response_code(404);exit('Equipe não encontrada.');}
}
$msg=$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
    $name=trim($_POST['name']??'');
    $code=trim($_POST['code']??'');
    $active=isset($_POST['active'])?1:0;
    $notes=trim($_POST['notes']??'');
    $members=array_values(array_unique(array_map('intval',$_POST['members']??[])));
    $just=trim($_POST['justification']??'');
    $obs=trim($_POST['change_observations']??'');

    if(mb_strlen($name)<3){$err='Informe um nome válido para a equipe.';}
    elseif(count($members)>50){$err='Uma equipe pode ter no máximo 50 bombeiros.';}
    elseif($active && count($members)<3){$err='Equipe ativa precisa ter no mínimo 3 bombeiros.';}
    elseif($isEdit && (mb_strlen($just)<5 || mb_strlen($obs)<3)){$err='Para atualizar uma equipe, a justificativa e as observações são obrigatórias.';}
    else{
        // Todos os integrantes precisam existir e estar ativos.
        if($members){
            $ph=implode(',',array_fill(0,count($members),'?'));
            $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE active=1 AND id IN ($ph)");$q->execute($members);
            if((int)$q->fetchColumn()!==count($members)) $err='Há integrante inválido ou inativo na seleção.';
        }
    }

    if(!$err && $members){
        $ph=implode(',',array_fill(0,count($members),'?'));
        $params=$members;
        $sql="SELECT u.bc_name,t.name FROM team_members tm JOIN teams t ON t.id=tm.team_id JOIN users u ON u.id=tm.user_id WHERE tm.active=1 AND t.active=1 AND tm.user_id IN ($ph)";
        if($isEdit){$sql.=" AND tm.team_id<>?";$params[]=$id;}
        $q=$pdo->prepare($sql);$q->execute($params);
        $conflicts=$q->fetchAll();
        if($conflicts){
            $names=array_map(fn($r)=>($r['bc_name']?:'Bombeiro').' já está em '.$r['name'],$conflicts);
            $err='Não foi possível salvar: '.implode('; ',$names).'. Remova o integrante da outra equipe primeiro.';
        }
    }

    if(!$err){
        $before=$isEdit?team_snapshot($pdo,$id):[];
        try{
            $pdo->beginTransaction();
            $now=now_iso();
            if($isEdit){
                $oldTeamName=(string)($before['name']??'');
                $up=$pdo->prepare("UPDATE teams SET name=?,code=?,active=?,notes=?,updated_by=?,updated_at=? WHERE id=?");
                $up->execute([$name,$code?:null,$active,$notes?:null,(int)$admin['id'],$now,$id]);
                if($oldTeamName!=='' && $oldTeamName!==$name){
                    $renameOcc=$pdo->prepare("UPDATE occurrences SET team=? WHERE team=?");
                    $renameOcc->execute([$name,$oldTeamName]);
                }
            }else{
                $ins=$pdo->prepare("INSERT INTO teams(name,code,active,notes,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)");
                $ins->execute([$name,$code?:null,$active,$notes?:null,(int)$admin['id'],(int)$admin['id'],$now,$now]);
                $id=(int)$pdo->lastInsertId();$isEdit=true;
            }

            $off=$pdo->prepare("UPDATE team_members SET active=0,removed_at=? WHERE team_id=? AND active=1");
            $off->execute([$now,$id]);
            $sel=$pdo->prepare("SELECT id FROM team_members WHERE team_id=? AND user_id=?");
            $upd=$pdo->prepare("UPDATE team_members SET active=1,assigned_at=?,removed_at=NULL WHERE team_id=? AND user_id=?");
            $insm=$pdo->prepare("INSERT INTO team_members(team_id,user_id,active,assigned_at) VALUES(?,?,1,?)");
            foreach($members as $uid){
                $sel->execute([$id,$uid]);
                if($sel->fetchColumn()) $upd->execute([$now,$id,$uid]); else $insm->execute([$id,$uid,$now]);
            }
            sync_users_team_labels($pdo);
            $after=team_snapshot($pdo,$id);
            $action=$before?'UPDATE':'CREATE';
            $audit=$pdo->prepare("INSERT INTO team_audit(team_id,action,admin_user_id,justification,observations,before_json,after_json,created_at) VALUES(?,?,?,?,?,?,?,?)");
            $audit->execute([$id,$action,(int)$admin['id'],$before?$just:'CRIAÇÃO INICIAL DA EQUIPE',$before?$obs:($notes?:'Equipe criada'),json_encode($before,JSON_UNESCAPED_UNICODE),json_encode($after,JSON_UNESCAPED_UNICODE),$now]);
            admin_audit($pdo,(int)$admin['id'],$action,'TEAM',(string)$id,$before?$just:'Criação da equipe',$before?$obs:$notes);
            $pdo->commit();
            $msg=$before?'Equipe atualizada e alteração registrada no histórico.':'Equipe criada com sucesso.';
            $st=$pdo->prepare("SELECT * FROM teams WHERE id=?");$st->execute([$id]);$team=$st->fetch();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $err=str_contains($e->getMessage(),'UNIQUE')?'Já existe uma equipe com esse nome ou um integrante está em outra equipe ativa.':'Não foi possível salvar a equipe.';
        }
    }
}

$current=$isEdit?team_member_ids($pdo,$id):[];
$users=$pdo->query("SELECT u.id,u.name,u.bc_name,u.role,u.active,t.id AS current_team_id,t.name AS current_team_name FROM users u LEFT JOIN team_members tm ON tm.user_id=u.id AND tm.active=1 LEFT JOIN teams t ON t.id=tm.team_id AND t.active=1 WHERE u.active=1 ORDER BY u.bc_name,u.name")->fetchAll();
$history=[];
if($isEdit){$h=$pdo->prepare("SELECT a.*,u.bc_name,u.name AS admin_name FROM team_audit a LEFT JOIN users u ON u.id=a.admin_user_id WHERE a.team_id=? ORDER BY a.id DESC LIMIT 30");$h->execute([$id]);$history=$h->fetchAll();}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($isEdit?'Editar equipe':'Criar equipe')?> - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head><body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}" aria-label="Voltar para a página anterior">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Admin Geral — Gestão de Equipes</span></div></div><div class="right"><a href="equipes.php">Voltar</a><a href="usuarios.php">Usuários</a><a href="logout.php">Sair</a></div></header>
<main class="layout"><section class="card"><h2><?=$isEdit?'Editar equipe':'Criar nova equipe'?></h2>
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="grid2"><label>Nome da equipe<input name="name" required value="<?=e($team['name'])?>" placeholder="Ex.: Equipe Alfa"></label><label>Código / identificação<input name="code" value="<?=e($team['code']??'')?>" placeholder="Ex.: ALFA-01"></label></div>
<label>Observações da equipe<textarea name="notes" placeholder="Informações permanentes da equipe."><?=e($team['notes']??'')?></textarea></label>
<label class="checkbox-line"><input type="checkbox" name="active" value="1" <?=$team['active']?'checked':''?>> Equipe ativa</label>

<h3>Composição da equipe</h3><div class="notice"><strong>Regra:</strong> equipe ativa precisa ter de <strong>3 a 50 integrantes</strong>. Cada integrante pode permanecer em uma equipe operacional ativa por vez. <strong>STAFF não é obrigado a fazer parte de equipe</strong>, mas pode ser incluído quando a operação exigir.</div>
<div class="member-counter">Selecionados: <strong id="memberCount">0</strong> / 50</div>
<div class="member-list">
<?php foreach($users as $x):$checked=in_array((int)$x['id'],$current,true);$blocked=!$checked&&!empty($x['current_team_id'])&&((int)$x['current_team_id']!==$id);?>
<label class="member-row <?=$blocked?'member-blocked':''?>"><input class="member-check" type="checkbox" name="members[]" value="<?=$x['id']?>" <?=$checked?'checked':''?> <?=$blocked?'disabled':''?>><span><strong><?=e($x['bc_name']?:$x['name'])?></strong><br><small><?=e($x['name'])?> · <?=e(role_label($x['role']))?><?=$blocked?' · já em '.e($x['current_team_name']):''?></small></span></label>
<?php endforeach;?>
</div>
<?php if($isEdit):?><section class="change-box"><h3>Registro obrigatório da atualização</h3><label>Justificativa da alteração<textarea name="justification" required placeholder="Por que a equipe está sendo alterada?"></textarea></label><label>Observações da alteração<textarea name="change_observations" required placeholder="Descreva o que mudou, substituições, inclusão/retirada de integrantes etc."></textarea></label></section><?php endif;?>
<button class="primary">Salvar equipe</button></form></section>

<?php if($isEdit):?><section class="card"><h2>Histórico de alterações</h2><?php if(!$history):?><p class="muted">Sem alterações registradas.</p><?php endif;?>
<div class="timeline"><?php foreach($history as $h):?><article><strong><?=e($h['action'])?></strong> · <?=e($h['created_at'])?> · <?=e($h['bc_name']?:$h['admin_name'])?><br><b>Justificativa:</b> <?=e($h['justification'])?><br><b>Observações:</b> <?=nl2br(e($h['observations']))?></article><?php endforeach;?></div></section><?php endif;?>
</main>
<script>const checks=[...document.querySelectorAll('.member-check')],counter=document.getElementById('memberCount');function count(){counter.textContent=checks.filter(x=>x.checked).length}checks.forEach(x=>x.addEventListener('change',count));count();</script>
<script src="assets/security.js"></script></body></html>
