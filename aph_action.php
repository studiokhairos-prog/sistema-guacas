<?php
require __DIR__ . '/config.php';
$u=require_user();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
$id=(int)($_POST['id']??0);$action=$_POST['action']??'';$r=load_aph($id);
if(!$r){http_response_code(404);exit('Ficha não encontrada');}
$pdo=db();
if($action==='archive'){
 if(!aph_can_access($u,$r)){http_response_code(403);exit('Acesso negado.');}
 if(!empty($r['deleted_at'])) exit('Ficha excluída.');
 $c=$pdo->prepare("SELECT COUNT(*) FROM aph_signatures WHERE aph_id=? AND valid=1");$c->execute([$id]);if((int)$c->fetchColumn()<1){header('Location: aph.php?id='.$id.'&need_signature=1');exit;}
 $now=now_iso();$pdo->prepare("UPDATE aph_records SET status='ARQUIVADA',archived_at=?,updated_at=?,updated_by=? WHERE id=?")->execute([$now,$now,$u['id'],$id]);aph_audit($pdo,$id,'ARQUIVADA',$u['id'],'Ficha arquivada e bloqueada para edição');header('Location: aph.php?id='.$id.'&archived=1');exit;
}
if($action==='delete'){
 if(!is_admin_general($u)){http_response_code(403);exit('Somente Admin Geral pode excluir fichas.');}
 $just=trim($_POST['justification']??'');if(mb_strlen($just)<5) exit('Informe uma justificativa para a exclusão.');
 if(!empty($r['deleted_at'])) exit('Ficha já está excluída.');
 $now=now_iso();$pdo->prepare("UPDATE aph_records SET deleted_at=?,deleted_by=?,delete_reason=?,updated_at=?,updated_by=? WHERE id=?")->execute([$now,$u['id'],$just,$now,$u['id'],$id]);aph_audit($pdo,$id,'EXCLUIDA',$u['id'],$just);admin_audit($pdo,(int)$u['id'],'DELETE','APH',(string)$id,$just,'Ficha enviada para lixeira administrativa');header('Location: aph_arquivo.php?deleted=1');exit;
}
if($action==='restore'){
 if(!is_admin_general($u)){http_response_code(403);exit('Somente Admin Geral pode restaurar fichas.');}
 $just=trim($_POST['justification']??'');if(mb_strlen($just)<5) exit('Informe uma justificativa.');
 $now=now_iso();$pdo->prepare("UPDATE aph_records SET deleted_at=NULL,deleted_by=NULL,delete_reason=NULL,updated_at=?,updated_by=? WHERE id=?")->execute([$now,$u['id'],$id]);aph_audit($pdo,$id,'RESTAURADA',$u['id'],$just);admin_audit($pdo,(int)$u['id'],'RESTORE','APH',(string)$id,$just,'Ficha restaurada da lixeira');header('Location: aph_arquivo.php');exit;
}
http_response_code(400);echo 'Ação inválida';
