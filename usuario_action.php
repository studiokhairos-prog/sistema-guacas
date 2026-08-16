<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']); $pdo=db();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
$id=(int)($_POST['id']??0);$action=$_POST['action']??'';$just=trim($_POST['justification']??'');
$st=$pdo->prepare("SELECT * FROM users WHERE id=?");$st->execute([$id]);$target=$st->fetch();
if(!$target){http_response_code(404);exit('Cadastro não encontrado.');}
if(mb_strlen($just)<5) exit('Informe uma justificativa com pelo menos 5 caracteres.');
if($action==='delete'){
 if($id===(int)$admin['id']) exit('Você não pode excluir o próprio cadastro enquanto está conectado.');
 if($target['role']==='ADMIN' && $target['active'] && admin_count($pdo)<=1) exit('É obrigatório manter pelo menos um Admin Geral ativo.');
 $tm=$pdo->prepare("SELECT t.id,t.name,(SELECT COUNT(*) FROM team_members x WHERE x.team_id=t.id AND x.active=1) cnt FROM team_members tm JOIN teams t ON t.id=tm.team_id WHERE tm.user_id=? AND tm.active=1 LIMIT 1");$tm->execute([$id]);$team=$tm->fetch();
 if($team && (int)$team['cnt']<=3) exit('Não é possível excluir agora: a equipe '.$team['name'].' ficaria com menos de 3 integrantes. Ajuste a equipe primeiro.');
 $pdo->beginTransaction();
 try{
  $now=now_iso();
  $pdo->prepare("UPDATE team_members SET active=0,removed_at=? WHERE user_id=? AND active=1")->execute([$now,$id]);
  $pdo->prepare("UPDATE users SET active=0,deleted_at=?,deleted_by=?,delete_reason=?,team=NULL WHERE id=?")->execute([$now,$admin['id'],$just,$id]);
  admin_audit($pdo,(int)$admin['id'],'DELETE','USER',(string)$id,$just,'Exclusão lógica; histórico operacional preservado');
  $pdo->commit();sync_users_team_labels($pdo);header('Location: usuarios.php?deleted=1');exit;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();exit('Não foi possível excluir o cadastro.');}
}
if($action==='restore'){
 if(!$target['deleted_at']) exit('Cadastro já está ativo na base.');
 try{$pdo->prepare("UPDATE users SET deleted_at=NULL,deleted_by=NULL,delete_reason=NULL,active=0 WHERE id=?")->execute([$id]);admin_audit($pdo,(int)$admin['id'],'RESTORE','USER',(string)$id,$just,'Restaurado inativo; Admin deve revisar antes de liberar acesso');header('Location: usuarios.php?restored=1');exit;}catch(Throwable $e){exit('Não foi possível restaurar.');}
}
http_response_code(400);echo 'Ação inválida';
