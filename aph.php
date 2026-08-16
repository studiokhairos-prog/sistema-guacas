<?php
require __DIR__ . '/config.php';
$u=require_user();
$pdo=db();

$id=(int)($_GET['id']??0);
$occId=(int)($_GET['occurrence_id']??0);
$record=$id?load_aph($id):null;
if($record && !empty($record['deleted_at'])){http_response_code(404);exit('Ficha excluída. Consulte a lixeira administrativa.');}
if($record && !aph_can_access($u,$record)){http_response_code(403);exit('Acesso negado.');}
if($record) $occId=(int)($record['occurrence_id']??0);

$data=[];
if($record) $data=json_decode($record['data_json']??'{}',true) ?: [];
$clientUuid=$record['client_uuid']??bin2hex(random_bytes(16));
$readonly=$record && $record['status']==='ARQUIVADA';

$occurrencesSql="SELECT id,protocol,nature,type,address,team,status,patient_name_hint,source FROM occurrences";
$params=[];
if($u['role']==='CAMPO' && !empty($u['team'])){$occurrencesSql.=" WHERE team=? OR team IS NULL OR team=''";$params[]=$u['team'];}
$occurrencesSql.=" ORDER BY id DESC LIMIT 150";
$st=$pdo->prepare($occurrencesSql);$st->execute($params);$occurrences=$st->fetchAll();

$sigs=[];
if($record){
  $s=$pdo->prepare("SELECT * FROM aph_signatures WHERE aph_id=? ORDER BY id");
  $s->execute([$record['id']]);$sigs=$s->fetchAll();
}

function fv(array $d,string $k,string $default=''): string { return e((string)($d[$k]??$default)); }
function sel(array $d,string $k,string $v): string { return (($d[$k]??'')===$v)?'selected':''; }
function chk(array $d,string $k): string { return !empty($d[$k])?'checked':''; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24"><title><?=e(app_display_name())?> - Ficha APH</title><link rel="manifest" href="manifest_bombeiros.php"><link rel="apple-touch-icon" href="assets/icons/guacas-bombeiros-180.png"><link rel="stylesheet" href="assets/app.css"></head>
<body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}" aria-label="Voltar para a página anterior">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e($record['code']??'NOVA FICHA APH')?></strong><span>Atendimento Pré-Hospitalar</span></div></div>
<div class="right"><span id="net" class="pill">...</span><span id="aphSaveState" class="pill"><?=$readonly?'🔒 ARQUIVADA':'RASCUNHO'?></span><a href="aph_arquivo.php">Arquivo APH</a><a href="index.php">Operação</a><a href="logout.php">Sair</a></div></header>

<main class="aph-layout">
<?php if(isset($_GET['signed'])):?><div class="alert ok">Assinatura registrada com sucesso.</div><?php endif;?>
<?php if(isset($_GET['need_signature'])):?><div class="alert error">Para arquivar, registre pelo menos uma assinatura válida.</div><?php endif;?>
<?php if($readonly):?><div class="alert ok"><strong>Ficha arquivada:</strong> o conteúdo está bloqueado para edição. Ela continua disponível para consulta e impressão.</div><?php endif;?>
<div class="notice medical-note"><strong>Registro assistencial:</strong> esta ficha registra informações observadas e procedimentos efetivamente realizados. Ela não substitui protocolos, regulação médica, treinamento ou outros meios operacionais de emergência.</div>

<form id="aphForm" class="aph-form">
<input type="hidden" id="aph_id" value="<?=e((string)($record['id']??0))?>">
<input type="hidden" id="client_uuid" value="<?=e($clientUuid)?>">

<section class="card form-section">
<h2>1. Identificação da ocorrência</h2>
<div class="grid3">
<label>Ocorrência vinculada<select name="occurrence_id" id="occurrence_id" <?=$readonly?'disabled':''?>><option value="">Sem vínculo</option>
<?php foreach($occurrences as $o):?><option value="<?=$o['id']?>" data-nature="<?=e($o['nature']??'')?>" data-type="<?=e($o['type'])?>" data-address="<?=e($o['address'])?>" data-team="<?=e($o['team']??'')?>" data-patient="<?=e($o['patient_name_hint']??'')?>" <?=($occId===$o['id'])?'selected':''?>><?=e($o['protocol'].' — '.(($o['nature']??'')?($o['nature'].' / '):'').$o['type'].' — '.($o['team']?:'Sem equipe'))?></option><?php endforeach;?>
</select></label>
<label>Data do atendimento<input type="date" name="service_date" value="<?=fv($data,'service_date',date('Y-m-d'))?>" <?=$readonly?'readonly':''?>></label>
<label>Natureza / tipo<input name="service_type" value="<?=fv($data,'service_type')?>" placeholder="Clínico, trauma, obstétrico..." <?=$readonly?'readonly':''?>></label>
</div>
<div class="grid4">
<label>Acionamento<input type="time" name="time_dispatch" value="<?=fv($data,'time_dispatch')?>" <?=$readonly?'readonly':''?>></label>
<label>Saída da base<input type="time" name="time_departure" value="<?=fv($data,'time_departure')?>" <?=$readonly?'readonly':''?>></label>
<label>Chegada ao local<input type="time" name="time_scene" value="<?=fv($data,'time_scene')?>" <?=$readonly?'readonly':''?>></label>
<label>Saída do local<input type="time" name="time_leave_scene" value="<?=fv($data,'time_leave_scene')?>" <?=$readonly?'readonly':''?>></label>
</div>
<div class="grid2"><label>Local / endereço<input name="scene_address" value="<?=fv($data,'scene_address')?>" <?=$readonly?'readonly':''?>></label><label>Equipe / viatura<input name="unit_team" value="<?=fv($data,'unit_team',$u['team']??'')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>2. Dados do paciente</h2>
<div class="grid3">
<label>Nome completo *<input name="patient_full_name" required value="<?=fv($data,'patient_full_name')?>" <?=$readonly?'readonly':''?>></label>
<label>Nome social / preferencial<input name="patient_social_name" value="<?=fv($data,'patient_social_name')?>" <?=$readonly?'readonly':''?>></label>
<label>Data de nascimento<input type="date" name="patient_birth_date" value="<?=fv($data,'patient_birth_date')?>" <?=$readonly?'readonly':''?>></label>
</div>
<div class="grid4">
<label>Idade / idade aparente<input name="patient_age" value="<?=fv($data,'patient_age')?>" <?=$readonly?'readonly':''?>></label>
<label>Sexo informado<select name="patient_sex" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'patient_sex','FEMININO')?>>FEMININO</option><option <?=sel($data,'patient_sex','MASCULINO')?>>MASCULINO</option><option <?=sel($data,'patient_sex','OUTRO / NÃO INFORMADO')?>>OUTRO / NÃO INFORMADO</option></select></label>
<label>CNS (Cartão SUS)<input name="patient_cns" value="<?=fv($data,'patient_cns')?>" inputmode="numeric" <?=$readonly?'readonly':''?>></label>
<label>CPF<input name="patient_cpf" value="<?=fv($data,'patient_cpf')?>" <?=$readonly?'readonly':''?>></label>
</div>
<div class="grid3"><label>Tipo sanguíneo informado<select name="patient_blood_type" <?=$readonly?'disabled':''?>><?php foreach(blood_type_options() as $bt):?><option value="<?=e($bt)?>" <?=sel($data,'patient_blood_type',$bt)?>><?=e($bt)?></option><?php endforeach;?></select><span class="small">Informação declarada; não substitui confirmação laboratorial.</span></label><label>RG / documento<input name="patient_document" value="<?=fv($data,'patient_document')?>" <?=$readonly?'readonly':''?>></label><label>Nome da mãe<input name="patient_mother" value="<?=fv($data,'patient_mother')?>" <?=$readonly?'readonly':''?>></label></div>
<div class="grid2"><label>Telefone<input name="patient_phone" value="<?=fv($data,'patient_phone')?>" <?=$readonly?'readonly':''?>></label><label>Endereço residencial<input name="patient_address" value="<?=fv($data,'patient_address')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>3. Acompanhante / responsável</h2>
<div class="grid3"><label>Nome completo<input name="responsible_name" value="<?=fv($data,'responsible_name')?>" <?=$readonly?'readonly':''?>></label><label>Parentesco / vínculo<input name="responsible_relation" value="<?=fv($data,'responsible_relation')?>" <?=$readonly?'readonly':''?>></label><label>Telefone<input name="responsible_phone" value="<?=fv($data,'responsible_phone')?>" <?=$readonly?'readonly':''?>></label></div>
<div class="grid3"><label>CPF / documento<input name="responsible_document" value="<?=fv($data,'responsible_document')?>" <?=$readonly?'readonly':''?>></label><label>CNS<input name="responsible_cns" value="<?=fv($data,'responsible_cns')?>" <?=$readonly?'readonly':''?>></label><label>Acompanhou o transporte?<select name="responsible_accompanied" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'responsible_accompanied','SIM')?>>SIM</option><option <?=sel($data,'responsible_accompanied','NÃO')?>>NÃO</option></select></label></div>
</section>

<section class="card form-section">
<h2>4. Queixa, história e entrevista</h2>
<label>Queixa principal / sinais e sintomas<textarea name="chief_complaint" <?=$readonly?'readonly':''?>><?=fv($data,'chief_complaint')?></textarea></label>
<div class="grid2">
<label>S — Sinais e sintomas<textarea name="sample_signs" <?=$readonly?'readonly':''?>><?=fv($data,'sample_signs')?></textarea></label>
<label>A — Alergias<textarea name="sample_allergies" <?=$readonly?'readonly':''?>><?=fv($data,'sample_allergies')?></textarea></label>
<label>M — Medicamentos em uso<textarea name="sample_medications" <?=$readonly?'readonly':''?>><?=fv($data,'sample_medications')?></textarea></label>
<label>P — Passado médico / comorbidades<textarea name="sample_history" <?=$readonly?'readonly':''?>><?=fv($data,'sample_history')?></textarea></label>
<label>L — Última ingestão de líquidos/alimentos<textarea name="sample_last_intake" <?=$readonly?'readonly':''?>><?=fv($data,'sample_last_intake')?></textarea></label>
<label>E — Eventos relacionados<textarea name="sample_events" <?=$readonly?'readonly':''?>><?=fv($data,'sample_events')?></textarea></label>
</div>
<h3>Dor / OPQRST</h3>
<div class="grid3"><label>O — Início<input name="opqrst_onset" value="<?=fv($data,'opqrst_onset')?>" <?=$readonly?'readonly':''?>></label><label>P — Provoca/alivia<input name="opqrst_provocation" value="<?=fv($data,'opqrst_provocation')?>" <?=$readonly?'readonly':''?>></label><label>Q — Qualidade<input name="opqrst_quality" value="<?=fv($data,'opqrst_quality')?>" <?=$readonly?'readonly':''?>></label><label>R — Região/irradiação<input name="opqrst_region" value="<?=fv($data,'opqrst_region')?>" <?=$readonly?'readonly':''?>></label><label>S — Intensidade 0–10<input type="number" min="0" max="10" name="opqrst_severity" value="<?=fv($data,'opqrst_severity')?>" <?=$readonly?'readonly':''?>></label><label>T — Tempo/evolução<input name="opqrst_time" value="<?=fv($data,'opqrst_time')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>5. Avaliação primária — XABCDE</h2>
<div class="grid2">
<label>X — Hemorragia exsanguinante / controle<textarea name="xabcde_x" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_x')?></textarea></label>
<label>A — Via aérea / coluna cervical<textarea name="xabcde_a" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_a')?></textarea></label>
<label>B — Respiração / tórax<textarea name="xabcde_b" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_b')?></textarea></label>
<label>C — Circulação / perfusão<textarea name="xabcde_c" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_c')?></textarea></label>
<label>D — Neurológico<textarea name="xabcde_d" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_d')?></textarea></label>
<label>E — Exposição / ambiente / lesões<textarea name="xabcde_e" <?=$readonly?'readonly':''?>><?=fv($data,'xabcde_e')?></textarea></label>
</div>
</section>

<section class="card form-section">
<h2>6. Sinais vitais seriados</h2>
<div class="table-wrap"><table class="vitals"><thead><tr><th>Horário</th><th>PA</th><th>FC</th><th>FR</th><th>SpO₂</th><th>Temp.</th><th>Glicemia</th><th>Dor 0–10</th></tr></thead><tbody>
<?php for($i=1;$i<=4;$i++):?><tr>
<td><input type="time" name="v<?=$i?>_time" value="<?=fv($data,"v{$i}_time")?>" <?=$readonly?'readonly':''?>></td>
<td><input name="v<?=$i?>_bp" value="<?=fv($data,"v{$i}_bp")?>" placeholder="120/80" <?=$readonly?'readonly':''?>></td>
<td><input type="number" name="v<?=$i?>_hr" value="<?=fv($data,"v{$i}_hr")?>" <?=$readonly?'readonly':''?>></td>
<td><input type="number" name="v<?=$i?>_rr" value="<?=fv($data,"v{$i}_rr")?>" <?=$readonly?'readonly':''?>></td>
<td><input type="number" name="v<?=$i?>_spo2" value="<?=fv($data,"v{$i}_spo2")?>" <?=$readonly?'readonly':''?>></td>
<td><input type="number" step="0.1" name="v<?=$i?>_temp" value="<?=fv($data,"v{$i}_temp")?>" <?=$readonly?'readonly':''?>></td>
<td><input name="v<?=$i?>_glucose" value="<?=fv($data,"v{$i}_glucose")?>" <?=$readonly?'readonly':''?>></td>
<td><input type="number" min="0" max="10" name="v<?=$i?>_pain" value="<?=fv($data,"v{$i}_pain")?>" <?=$readonly?'readonly':''?>></td>
</tr><?php endfor;?></tbody></table></div>
<div class="grid3"><label>AVDI<select name="avdi" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'avdi','ALERTA')?>>ALERTA</option><option <?=sel($data,'avdi','RESPONDE AO VERBAL')?>>RESPONDE AO VERBAL</option><option <?=sel($data,'avdi','RESPONDE À DOR')?>>RESPONDE À DOR</option><option <?=sel($data,'avdi','IRRESPONSIVO')?>>IRRESPONSIVO</option></select></label><label>Pele / perfusão<input name="skin_perfusion" value="<?=fv($data,'skin_perfusion')?>" <?=$readonly?'readonly':''?>></label><label>Enchimento capilar<input name="capillary_refill" value="<?=fv($data,'capillary_refill')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>7. Escala de Coma de Glasgow e pupilas</h2>
<div class="grid4">
<label>Perfil<select name="gcs_profile" id="gcs_profile" <?=$readonly?'disabled':''?>><option value="ADULTO" <?=sel($data,'gcs_profile','ADULTO')?>>Adulto / criança &gt;4 anos</option><option value="CRIANCA" <?=sel($data,'gcs_profile','CRIANCA')?>>Criança</option><option value="BEBE" <?=sel($data,'gcs_profile','BEBE')?>>Bebê &lt;1 ano</option></select></label>
<label>Abertura ocular<select name="gcs_eye" id="gcs_eye" <?=$readonly?'disabled':''?>><option value=""></option><option value="4" <?=sel($data,'gcs_eye','4')?>>4 — Espontânea</option><option value="3" <?=sel($data,'gcs_eye','3')?>>3 — Ao estímulo verbal</option><option value="2" <?=sel($data,'gcs_eye','2')?>>2 — Ao estímulo doloroso</option><option value="1" <?=sel($data,'gcs_eye','1')?>>1 — Ausente</option></select></label>
<label>Resposta verbal<select name="gcs_verbal" id="gcs_verbal" data-value="<?=fv($data,'gcs_verbal')?>" <?=$readonly?'disabled':''?>></select></label>
<label>Resposta motora<select name="gcs_motor" id="gcs_motor" data-value="<?=fv($data,'gcs_motor')?>" <?=$readonly?'disabled':''?>></select></label>
</div>
<div class="gcs-total">Glasgow total: <strong id="gcs_total_view"><?=fv($data,'gcs_total','—')?></strong><input type="hidden" name="gcs_total" id="gcs_total" value="<?=fv($data,'gcs_total')?>"></div>
<div class="grid4">
<label>Pupila D — tamanho (mm)<input type="number" min="1" max="9" name="pupil_r_size" value="<?=fv($data,'pupil_r_size')?>" <?=$readonly?'readonly':''?>></label>
<label>Pupila D — reação<select name="pupil_r_reaction" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'pupil_r_reaction','FOTORREAGENTE')?>>FOTORREAGENTE</option><option <?=sel($data,'pupil_r_reaction','NÃO REAGENTE')?>>NÃO REAGENTE</option><option <?=sel($data,'pupil_r_reaction','NÃO AVALIÁVEL')?>>NÃO AVALIÁVEL</option></select></label>
<label>Pupila E — tamanho (mm)<input type="number" min="1" max="9" name="pupil_l_size" value="<?=fv($data,'pupil_l_size')?>" <?=$readonly?'readonly':''?>></label>
<label>Pupila E — reação<select name="pupil_l_reaction" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'pupil_l_reaction','FOTORREAGENTE')?>>FOTORREAGENTE</option><option <?=sel($data,'pupil_l_reaction','NÃO REAGENTE')?>>NÃO REAGENTE</option><option <?=sel($data,'pupil_l_reaction','NÃO AVALIÁVEL')?>>NÃO AVALIÁVEL</option></select></label>
</div>
<label>Observações neurológicas<textarea name="neuro_notes" <?=$readonly?'readonly':''?>><?=fv($data,'neuro_notes')?></textarea></label>
</section>

<section class="card form-section">
<h2>8. Trauma / exame físico</h2>
<div class="grid2"><label>Mecanismo / cinemática<textarea name="trauma_mechanism" <?=$readonly?'readonly':''?>><?=fv($data,'trauma_mechanism')?></textarea></label><label>Lesões encontradas / exame segmentar<textarea name="trauma_findings" <?=$readonly?'readonly':''?>><?=fv($data,'trauma_findings')?></textarea></label></div>
<div class="check-grid">
<label><input type="checkbox" name="trauma_bleeding_control" value="1" <?=chk($data,'trauma_bleeding_control')?> <?=$readonly?'disabled':''?>> Controle de hemorragia</label>
<label><input type="checkbox" name="trauma_collar" value="1" <?=chk($data,'trauma_collar')?> <?=$readonly?'disabled':''?>> Colar cervical</label>
<label><input type="checkbox" name="trauma_splint" value="1" <?=chk($data,'trauma_splint')?> <?=$readonly?'disabled':''?>> Imobilização de membro</label>
<label><input type="checkbox" name="trauma_board" value="1" <?=chk($data,'trauma_board')?> <?=$readonly?'disabled':''?>> Dispositivo de movimentação/imobilização</label>
</div>
<div class="grid3"><label>Queimadura — local/extensão<input name="burns" value="<?=fv($data,'burns')?>" <?=$readonly?'readonly':''?>></label><label>Hemorragias / perdas<input name="bleeding" value="<?=fv($data,'bleeding')?>" <?=$readonly?'readonly':''?>></label><label>Outros achados<input name="other_findings" value="<?=fv($data,'other_findings')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>9. Obstétrico e pediátrico, quando aplicável</h2>
<div class="grid4"><label>Gestante?<select name="pregnant" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'pregnant','SIM')?>>SIM</option><option <?=sel($data,'pregnant','NÃO')?>>NÃO</option></select></label><label>Idade gestacional<input name="gestational_age" value="<?=fv($data,'gestational_age')?>" <?=$readonly?'readonly':''?>></label><label>DUM / DPP<input name="obstetric_dates" value="<?=fv($data,'obstetric_dates')?>" <?=$readonly?'readonly':''?>></label><label>Pré-natal<input name="prenatal" value="<?=fv($data,'prenatal')?>" <?=$readonly?'readonly':''?>></label></div>
<div class="grid3"><label>Gesta/Para/Aborto<input name="parity" value="<?=fv($data,'parity')?>" <?=$readonly?'readonly':''?>></label><label>Contrações — frequência/duração<input name="contractions" value="<?=fv($data,'contractions')?>" <?=$readonly?'readonly':''?>></label><label>Perdas vaginais / líquido / sangramento<input name="vaginal_loss" value="<?=fv($data,'vaginal_loss')?>" <?=$readonly?'readonly':''?>></label></div>
<div class="grid3"><label>Pediátrico — peso informado/estimado<input name="pediatric_weight" value="<?=fv($data,'pediatric_weight')?>" <?=$readonly?'readonly':''?>></label><label>Responsável presente<input name="pediatric_guardian" value="<?=fv($data,'pediatric_guardian')?>" <?=$readonly?'readonly':''?>></label><label>Observações pediátricas<input name="pediatric_notes" value="<?=fv($data,'pediatric_notes')?>" <?=$readonly?'readonly':''?>></label></div>
</section>

<section class="card form-section">
<h2>10. Procedimentos e cuidados realizados</h2>
<div class="check-grid">
<label><input type="checkbox" name="proc_airway" value="1" <?=chk($data,'proc_airway')?> <?=$readonly?'disabled':''?>> Manejo básico de via aérea</label>
<label><input type="checkbox" name="proc_oxygen" value="1" <?=chk($data,'proc_oxygen')?> <?=$readonly?'disabled':''?>> Oxigênio suplementar</label>
<label><input type="checkbox" name="proc_bvm" value="1" <?=chk($data,'proc_bvm')?> <?=$readonly?'disabled':''?>> Ventilação com BVM</label>
<label><input type="checkbox" name="proc_suction" value="1" <?=chk($data,'proc_suction')?> <?=$readonly?'disabled':''?>> Aspiração</label>
<label><input type="checkbox" name="proc_cpr" value="1" <?=chk($data,'proc_cpr')?> <?=$readonly?'disabled':''?>> RCP</label>
<label><input type="checkbox" name="proc_aed" value="1" <?=chk($data,'proc_aed')?> <?=$readonly?'disabled':''?>> DEA/AED</label>
<label><input type="checkbox" name="proc_dressing" value="1" <?=chk($data,'proc_dressing')?> <?=$readonly?'disabled':''?>> Curativo/compressão</label>
<label><input type="checkbox" name="proc_immobilization" value="1" <?=chk($data,'proc_immobilization')?> <?=$readonly?'disabled':''?>> Imobilização</label>
<label><input type="checkbox" name="proc_glucose" value="1" <?=chk($data,'proc_glucose')?> <?=$readonly?'disabled':''?>> Glicemia capilar</label>
<label><input type="checkbox" name="proc_other" value="1" <?=chk($data,'proc_other')?> <?=$readonly?'disabled':''?>> Outros</label>
</div>
<div class="grid3"><label>O₂ — dispositivo/fluxo efetivamente utilizado<input name="oxygen_details" value="<?=fv($data,'oxygen_details')?>" <?=$readonly?'readonly':''?>></label><label>Imobilizações / materiais<input name="immobilization_details" value="<?=fv($data,'immobilization_details')?>" <?=$readonly?'readonly':''?>></label><label>Outros procedimentos<input name="procedure_other_details" value="<?=fv($data,'procedure_other_details')?>" <?=$readonly?'readonly':''?>></label></div>
<label>Medicamentos administrados — nome, dose, via, horário e profissional/autorização, conforme protocolo local<textarea name="medications_administered" <?=$readonly?'readonly':''?>><?=fv($data,'medications_administered')?></textarea></label>
<label>Evolução durante o atendimento<textarea name="evolution" <?=$readonly?'readonly':''?>><?=fv($data,'evolution')?></textarea></label>
</section>

<section class="card form-section">
<h2>11. Transporte, destino e passagem do caso</h2>
<div class="grid4"><label>Destino<input name="destination" value="<?=fv($data,'destination')?>" <?=$readonly?'readonly':''?>></label><label>Chegada ao destino<input type="time" name="time_destination" value="<?=fv($data,'time_destination')?>" <?=$readonly?'readonly':''?>></label><label>Profissional que recebeu<input name="receiver_name" value="<?=fv($data,'receiver_name')?>" <?=$readonly?'readonly':''?>></label><label>Função / registro<input name="receiver_role" value="<?=fv($data,'receiver_role')?>" <?=$readonly?'readonly':''?>></label></div>
<label>Passagem do caso / condições na entrega<textarea name="handover" <?=$readonly?'readonly':''?>><?=fv($data,'handover')?></textarea></label>
<div class="grid3"><label>Desfecho<select name="outcome" <?=$readonly?'disabled':''?>><option></option><option <?=sel($data,'outcome','TRANSPORTADO')?>>TRANSPORTADO</option><option <?=sel($data,'outcome','LIBERADO NO LOCAL')?>>LIBERADO NO LOCAL</option><option <?=sel($data,'outcome','RECUSA DE ATENDIMENTO/TRANSPORTE')?>>RECUSA DE ATENDIMENTO/TRANSPORTE</option><option <?=sel($data,'outcome','TRANSFERIDO A OUTRA EQUIPE')?>>TRANSFERIDO A OUTRA EQUIPE</option><option <?=sel($data,'outcome','OUTRO')?>>OUTRO</option></select></label><label>Nome em caso de recusa / responsável<input name="refusal_name" value="<?=fv($data,'refusal_name')?>" <?=$readonly?'readonly':''?>></label><label>Testemunha<input name="refusal_witness" value="<?=fv($data,'refusal_witness')?>" <?=$readonly?'readonly':''?>></label></div>
<label>Observações sobre recusa, orientação, acionamento de apoio ou outros registros<textarea name="final_notes" <?=$readonly?'readonly':''?>><?=fv($data,'final_notes')?></textarea></label>
</section>

<section class="card form-section">
<h2>12. Equipe que realizou o atendimento</h2>
<div class="grid2"><label>Bombeiros / integrantes<textarea name="crew_members" placeholder="Ex.: BC SILVA; BC J. SOUZA" <?=$readonly?'readonly':''?>><?=fv($data,'crew_members',$u['bc_name']??'')?></textarea></label><label>Staff / responsável pelo plantão<textarea name="shift_staff" <?=$readonly?'readonly':''?>><?=fv($data,'shift_staff')?></textarea></label></div>
</section>

<section class="card form-section" id="observationPageSection">
<h2>13. Página extra de observações</h2>
<p class="muted">ESCOLHA SE O RELATÓRIO PRECISA DE UMA PÁGINA ADICIONAL. AS FRASES ABAIXO SÃO MODELOS DE REDAÇÃO DOCUMENTAL E SÓ DEVEM SER INSERIDAS QUANDO CORRESPONDEREM AO QUE REALMENTE FOI OBSERVADO/REALIZADO.</p>
<label>PÁGINA DE OBSERVAÇÕES<select name="report_observation_page" id="reportObservationPage" <?=$readonly?'disabled':''?>><option value="NAO_HA" <?=sel($data,'report_observation_page','NAO_HA')||(!isset($data['report_observation_page'])&&trim((string)($data['report_additional_observations']??''))==='')?'selected':''?>>NÃO HÁ PÁGINA DE OBSERVAÇÃO</option><option value="SIM" <?=sel($data,'report_observation_page','SIM')||(!isset($data['report_observation_page'])&&trim((string)($data['report_additional_observations']??''))!=='')?'selected':''?>>SIM — INCLUIR PÁGINA EXTRA</option></select></label>
<div id="observationBuilder">
<div class="notice"><strong>CONFERÊNCIA OBRIGATÓRIA:</strong> NÃO UTILIZE FRASES PADRÃO PARA DESCREVER FATOS QUE NÃO FORAM VERIFICADOS NO ATENDIMENTO.</div>
<div class="phrase-bank">
<div class="phrase-category"><h3>AVALIAÇÃO E MONITORIZAÇÃO</h3><div class="phrase-buttons"><button type="button" data-phrase="PACIENTE PERMANECEU SOB OBSERVAÇÃO DA EQUIPE DURANTE O PERÍODO DOCUMENTADO, COM REAVALIAÇÕES REGISTRADAS NOS CAMPOS CORRESPONDENTES.">REAVALIAÇÃO SERIADA</button><button type="button" data-phrase="MANTIDA MONITORIZAÇÃO CLÍNICA E REAVALIAÇÃO DOS SINAIS VITAIS CONFORME NECESSIDADE IDENTIFICADA DURANTE O ATENDIMENTO.">MONITORIZAÇÃO</button><button type="button" data-phrase="DADOS CLÍNICOS E INFORMAÇÕES COMPLEMENTARES FORAM REGISTRADOS CONFORME OBSERVAÇÃO DA EQUIPE E/OU RELATO DO PACIENTE OU ACOMPANHANTE.">DADOS OBTIDOS</button></div></div>
<div class="phrase-category"><h3>SEGURANÇA E CENA</h3><div class="phrase-buttons"><button type="button" data-phrase="CENA AVALIADA QUANTO À SEGURANÇA OPERACIONAL, COM OS ACHADOS PERTINENTES REGISTRADOS NA FICHA DE ATENDIMENTO.">SEGURANÇA DA CENA</button><button type="button" data-phrase="FORAM MANTIDAS MEDIDAS DE SEGURANÇA COMPATÍVEIS COM O CENÁRIO OBSERVADO DURANTE O PERÍODO DE ATUAÇÃO DA EQUIPE.">MEDIDAS DE SEGURANÇA</button></div></div>
<div class="phrase-category"><h3>COMUNICAÇÃO E CONTINUIDADE DO CUIDADO</h3><div class="phrase-buttons"><button type="button" data-phrase="INFORMAÇÕES RELEVANTES DO ATENDIMENTO FORAM COMUNICADAS AO PROFISSIONAL OU EQUIPE RESPONSÁVEL PELA CONTINUIDADE DO CUIDADO, CONFORME REGISTRO DE PASSAGEM DO CASO.">PASSAGEM DO CASO</button><button type="button" data-phrase="ORIENTAÇÕES E INFORMAÇÕES PERTINENTES FORAM PRESTADAS AO PACIENTE E/OU RESPONSÁVEL, CONFORME O CONTEXTO DOCUMENTADO.">ORIENTAÇÕES</button><button type="button" data-phrase="OCORRÊNCIAS, INTERCORRÊNCIAS E CONDUTAS EFETIVAMENTE REGISTRADAS FORAM DOCUMENTADAS EM ORDEM CRONOLÓGICA PARA RASTREABILIDADE DO ATENDIMENTO.">RASTREABILIDADE</button></div></div>
</div>
<label>OBSERVAÇÕES COMPLEMENTARES<textarea name="report_additional_observations" id="reportAdditionalObservations" class="large-notes" <?=$readonly?'readonly':''?> placeholder="UTILIZE TEXTO LIVRE OU MONTE O REGISTRO COM AS FRASES ACIMA."><?=fv($data,'report_additional_observations')?></textarea></label>
</div>
</section>

<?php if(!$readonly):?>
<section class="card sticky-actions"><div><strong id="saveText">Alterações ainda não enviadas.</strong><div class="muted">Sem internet, o rascunho fica salvo neste aparelho e será sincronizado quando a conexão voltar.</div></div><button type="button" id="saveAph" class="primary">Salvar / sincronizar ficha</button></section>
<?php endif;?>
</form>

<?php if($record):?>
<section class="card">
<div class="section-head"><h2>Assinaturas</h2><?php if(!$readonly):?><a class="button-link" href="aph_assinar.php?id=<?=$record['id']?>">Assinar ficha</a><?php endif;?></div>
<?php if(!$sigs):?><p class="muted">Ainda não há assinaturas.</p><?php endif;?>
<div class="signature-list">
<?php foreach($sigs as $s):?><div class="signature-item <?=$s['valid']?'':'invalid-signature'?>">
<img src="signature_image.php?id=<?=$s['id']?>" alt="Assinatura"><div><strong><?=e($s['signer_bc_name']?:$s['signer_name'])?></strong><br><?=e($s['signature_capacity'])?><br><span class="small"><?=e($s['signed_at'])?></span><br><span class="small"><?=$s['valid']?'✅ Válida para a versão assinada':'⚠️ Invalidada por alteração posterior'?></span></div>
</div><?php endforeach;?>
</div>
<div class="action-bar"><a class="button-link" target="_blank" href="relatorio_aph.php?id=<?=$record['id']?>">📄 Relatório Automático</a><a class="button-link" target="_blank" href="aph_imprimir.php?id=<?=$record['id']?>">🖨️ Imprimir / Salvar em PDF</a>
<?php if(!$readonly):?><form method="post" action="aph_action.php" onsubmit="return confirm('Arquivar a ficha? Depois disso ela ficará bloqueada para edição.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$record['id']?>"><input type="hidden" name="action" value="archive"><button class="danger">🔒 Arquivar ficha</button></form><?php endif;?>
</div>
</section>
<?php if($record && is_admin_general($u)):?>
<section class="card danger-zone" id="excluir"><h2>Excluir ficha do paciente</h2><p class="muted">Somente Admin Geral. A ficha será retirada do arquivo operacional e enviada à lixeira administrativa, preservando auditoria e possibilidade de restauração.</p><form method="post" action="aph_action.php" onsubmit="return confirm('CONFIRMA a exclusão desta ficha APH?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$record['id']?>"><input type="hidden" name="action" value="delete"><label>Justificativa obrigatória<textarea name="justification" minlength="5" required placeholder="Motivo da exclusão"></textarea></label><button class="danger">🗑️ Excluir ficha APH</button></form></section>
<?php endif;?>
<?php endif;?>
</main>

<script>window.SICOBC={csrf:<?=json_encode(csrf_token())?>,aphId:<?=json_encode((int)($record['id']??0))?>,clientUuid:<?=json_encode($clientUuid)?>,readonly:<?=json_encode((bool)$readonly)?>};</script>
<script src="assets/app.js"></script><script src="assets/aph.js"></script>
<script src="assets/security.js"></script></body></html>
