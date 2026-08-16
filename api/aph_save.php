<?php
require dirname(__DIR__) . '/config.php';
$u = require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido'],405);
require_csrf();

$d = json_input();
$recordId = (int)($d['id'] ?? 0);
$clientUuid = trim((string)($d['client_uuid'] ?? ''));
$occurrenceId = (int)($d['occurrence_id'] ?? 0);
$data = $d['data'] ?? [];
if (!is_array($data)) json_response(['ok'=>false,'error'=>'Dados inválidos'],422);
$data = normalize_record_text($data);
if ($clientUuid === '') $clientUuid = bin2hex(random_bytes(16));

$patientName = trim((string)($data['patient_full_name'] ?? ''));
$cns = trim((string)($data['patient_cns'] ?? ''));
if ($patientName === '') json_response(['ok'=>false,'error'=>'Nome do paciente é obrigatório'],422);

$pdo = db();
$record = null;
if ($recordId > 0) $record = load_aph($recordId);
if (!$record && $clientUuid) {
    $st=$pdo->prepare("SELECT * FROM aph_records WHERE client_uuid=?");
    $st->execute([$clientUuid]);
    $record=$st->fetch() ?: null;
}

if ($record) {
    if (!aph_can_access($u,$record)) json_response(['ok'=>false,'error'=>'Sem acesso a esta ficha'],403);
    if ($record['status'] === 'ARQUIVADA') json_response(['ok'=>false,'error'=>'Ficha arquivada é somente leitura'],409);
    if ($u['role']==='CAMPO' && (int)($record['occurrence_id']??0)!==$occurrenceId) {
        json_response(['ok'=>false,'error'=>'A equipe de campo não pode transferir a ficha para outra ocorrência'],403);
    }
}

if ($u['role']==='CAMPO' && $occurrenceId<=0) {
    json_response(['ok'=>false,'error'=>'A ficha da equipe de campo deve estar vinculada a uma ocorrência'],422);
}

if ($occurrenceId > 0) {
    $st=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");
    $st->execute([$occurrenceId]);
    $occ=$st->fetch();
    if (!$occ) json_response(['ok'=>false,'error'=>'Ocorrência não encontrada'],422);
    if (!occurrence_mutation_allowed($u,$occ)) {
        json_response(['ok'=>false,'error'=>'Sua equipe não pode alterar fichas desta ocorrência'],403);
    }
}

$newHash = aph_content_hash($data);
$now = now_iso();

if (!$record) {
    $code = aph_code_new($pdo);
    $st=$pdo->prepare("INSERT INTO aph_records(code,client_uuid,occurrence_id,patient_name,cns,status,data_json,content_hash,version,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,'RASCUNHO',?,?,1,?,?,?,?)");
    $st->execute([$code,$clientUuid,$occurrenceId?:null,$patientName,$cns?:null,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$newHash,$u['id'],$u['id'],$now,$now]);
    $id=(int)$pdo->lastInsertId();
    aph_audit($pdo,$id,'CRIADA',$u['id'],'Ficha APH criada');
} else {
    $id=(int)$record['id'];
    if ($record['content_hash'] !== '' && $record['content_hash'] !== $newHash) {
        $inv=$pdo->prepare("UPDATE aph_signatures SET valid=0,invalidated_at=?,invalidated_reason='Conteúdo alterado após assinatura' WHERE aph_id=? AND valid=1");
        $inv->execute([$now,$id]);
    }
    $ver=((int)$record['version'])+1;
    $st=$pdo->prepare("UPDATE aph_records SET occurrence_id=?,patient_name=?,cns=?,data_json=?,content_hash=?,version=?,updated_by=?,updated_at=? WHERE id=?");
    $st->execute([$occurrenceId?:null,$patientName,$cns?:null,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$newHash,$ver,$u['id'],$now,$id]);
    aph_audit($pdo,$id,'ATUALIZADA',$u['id'],'Ficha APH atualizada; versão '.$ver);
    $code=(string)$record['code'];
}

json_response(['ok'=>true,'id'=>$id,'code'=>$code,'client_uuid'=>$clientUuid,'content_hash'=>$newHash,'saved_at'=>$now]);
