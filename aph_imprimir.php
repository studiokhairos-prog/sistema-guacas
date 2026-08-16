<?php
require __DIR__ . '/config.php';
$u=require_user();
$id=(int)($_GET['id']??0);
$r=load_aph($id);
if(!$r || !aph_can_access($u,$r)){http_response_code(404);exit('Ficha não encontrada');}
$d=json_decode($r['data_json']??'{}',true)?:[];
$pdo=db();
$occ=null;
if(!empty($r['occurrence_id'])){$s=$pdo->prepare("SELECT * FROM occurrences WHERE id=?");$s->execute([$r['occurrence_id']]);$occ=$s->fetch()?:null;}
$s=$pdo->prepare("SELECT * FROM aph_signatures WHERE aph_id=? ORDER BY id");$s->execute([$id]);$sigs=$s->fetchAll();
$baseAddress=system_setting('central_base_address','');
function pv(array $d,string $k): string { $v=trim((string)($d[$k]??'')); return $v===''?'—':e($v); }
function yesv(array $d,string $k): string { return !empty($d[$k])?'SIM':'NÃO'; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title><?=e($r['code'])?> - Ficha APH</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#111;margin:0;background:#ddd}.page{width:210mm;min-height:297mm;margin:8mm auto;background:white;padding:10mm;box-shadow:0 0 8px #777;font-size:10.5px}.head{display:flex;align-items:center;gap:10px;border-bottom:3px solid #a10f16;padding-bottom:8px}.head img{width:28mm;height:28mm;object-fit:contain}.head h1{font-size:18px;margin:0;color:#8b1118}.head p{margin:2px 0}.code{margin-left:auto;text-align:right}.sec{margin-top:9px;border:1px solid #bbb}.sec h2{font-size:11.5px;margin:0;padding:4px 6px;background:#eee;border-bottom:1px solid #bbb;color:#711015}.grid{display:grid;grid-template-columns:repeat(4,1fr)}.cell{padding:4px 6px;border-right:1px solid #ddd;border-bottom:1px solid #ddd;min-height:28px}.cell b{display:block;font-size:8px;text-transform:uppercase;color:#555}.span2{grid-column:span 2}.span4{grid-column:span 4}.signs{display:grid;grid-template-columns:1fr 1fr;gap:8px}.sig{border:1px solid #bbb;padding:6px}.sig img{max-width:100%;height:42px;object-fit:contain}.invalid{opacity:.55}.toolbar{width:210mm;margin:8px auto;text-align:right}.toolbar button{width:auto;padding:10px 18px;font-weight:bold;border-radius:8px}.toolbar .backprint{background:#202020;color:#fff;border:2px solid #f2b51d;margin-right:8px}.toolbar .printbtn{background:#a80e16;color:#fff;border:2px solid #f2b51d}.hash{word-break:break-all;font-family:monospace;font-size:8px}@media print{body{background:white}.toolbar{display:none}.page{margin:0;box-shadow:none;width:auto;min-height:auto;padding:8mm}@page{size:A4;margin:0}} 
</style></head><body><div class="toolbar"><button class="backprint" type="button" onclick="history.length>1?history.back():location.href='index.php'">← Voltar</button><button class="printbtn" onclick="window.print()">🖨️ Imprimir / Salvar em PDF</button></div><main class="page">
<div class="head"><img src="assets/logo_oficial_bombeiros.jpeg" alt="Logo"><div><h1>FICHA DE ATENDIMENTO PRÉ-HOSPITALAR</h1><p><?=e(ORG_NAME)?></p><p><?=e(app_display_name())?> — Registro assistencial</p><p><strong>Base Central:</strong> <?=$baseAddress!==''?e($baseAddress):'_______________________________________________'?></p></div><div class="code"><strong><?=e($r['code'])?></strong><br><?=e($r['status'])?><br>Versão <?=$r['version']?></div></div>

<div class="sec"><h2>Ocorrência e paciente</h2><div class="grid">
<div class="cell"><b>Ocorrência</b><?=e($occ['protocol']??'—')?></div><div class="cell"><b>Data</b><?=pv($d,'service_date')?></div><div class="cell"><b>Acionamento</b><?=pv($d,'time_dispatch')?></div><div class="cell"><b>Chegada ao local</b><?=pv($d,'time_scene')?></div>
<div class="cell span2"><b>Paciente</b><?=pv($d,'patient_full_name')?></div><div class="cell"><b>Nascimento / idade</b><?=pv($d,'patient_birth_date')?> / <?=pv($d,'patient_age')?></div><div class="cell"><b>Sexo</b><?=pv($d,'patient_sex')?></div>
<div class="cell"><b>CNS (Cartão SUS)</b><?=pv($d,'patient_cns')?></div><div class="cell"><b>CPF</b><?=pv($d,'patient_cpf')?></div><div class="cell"><b>Tipo sanguíneo informado</b><?=pv($d,'patient_blood_type')?></div><div class="cell"><b>Telefone</b><?=pv($d,'patient_phone')?></div><div class="cell"><b>Documento</b><?=pv($d,'patient_document')?></div><div class="cell span2"><b>Observação sobre tipo sanguíneo</b>Informação declarada; não substitui confirmação laboratorial.</div>
<div class="cell span4"><b>Endereço</b><?=pv($d,'patient_address')?></div>
</div></div>

<div class="sec"><h2>Acompanhante / responsável</h2><div class="grid"><div class="cell span2"><b>Nome</b><?=pv($d,'responsible_name')?></div><div class="cell"><b>Vínculo</b><?=pv($d,'responsible_relation')?></div><div class="cell"><b>Telefone</b><?=pv($d,'responsible_phone')?></div><div class="cell"><b>Documento</b><?=pv($d,'responsible_document')?></div><div class="cell"><b>CNS</b><?=pv($d,'responsible_cns')?></div><div class="cell span2"><b>Acompanhou transporte</b><?=pv($d,'responsible_accompanied')?></div></div></div>

<div class="sec"><h2>Queixa / SAMPLA / OPQRST</h2><div class="grid"><div class="cell span4"><b>Queixa principal</b><?=pv($d,'chief_complaint')?></div><div class="cell span2"><b>Sinais e sintomas</b><?=pv($d,'sample_signs')?></div><div class="cell span2"><b>Alergias</b><?=pv($d,'sample_allergies')?></div><div class="cell span2"><b>Medicamentos em uso</b><?=pv($d,'sample_medications')?></div><div class="cell span2"><b>Passado médico</b><?=pv($d,'sample_history')?></div><div class="cell span2"><b>Última ingestão</b><?=pv($d,'sample_last_intake')?></div><div class="cell span2"><b>Eventos</b><?=pv($d,'sample_events')?></div><div class="cell span4"><b>OPQRST</b>O: <?=pv($d,'opqrst_onset')?> | P: <?=pv($d,'opqrst_provocation')?> | Q: <?=pv($d,'opqrst_quality')?> | R: <?=pv($d,'opqrst_region')?> | S: <?=pv($d,'opqrst_severity')?> | T: <?=pv($d,'opqrst_time')?></div></div></div>

<div class="sec"><h2>Avaliação primária XABCDE</h2><div class="grid"><div class="cell span2"><b>X</b><?=pv($d,'xabcde_x')?></div><div class="cell span2"><b>A</b><?=pv($d,'xabcde_a')?></div><div class="cell span2"><b>B</b><?=pv($d,'xabcde_b')?></div><div class="cell span2"><b>C</b><?=pv($d,'xabcde_c')?></div><div class="cell span2"><b>D</b><?=pv($d,'xabcde_d')?></div><div class="cell span2"><b>E</b><?=pv($d,'xabcde_e')?></div></div></div>

<div class="sec"><h2>Sinais vitais seriados</h2><div class="grid">
<?php for($i=1;$i<=4;$i++):?><div class="cell span4"><b>Avaliação <?=$i?></b><?=pv($d,"v{$i}_time")?> | PA <?=pv($d,"v{$i}_bp")?> | FC <?=pv($d,"v{$i}_hr")?> | FR <?=pv($d,"v{$i}_rr")?> | SpO₂ <?=pv($d,"v{$i}_spo2")?> | Temp <?=pv($d,"v{$i}_temp")?> | Glicemia <?=pv($d,"v{$i}_glucose")?> | Dor <?=pv($d,"v{$i}_pain")?></div><?php endfor;?>
<div class="cell"><b>AVDI</b><?=pv($d,'avdi')?></div><div class="cell"><b>Pele/perfusão</b><?=pv($d,'skin_perfusion')?></div><div class="cell"><b>Ench. capilar</b><?=pv($d,'capillary_refill')?></div><div class="cell"><b>Glasgow</b><?=pv($d,'gcs_total')?></div>
</div></div>

<div class="sec"><h2>Neurológico</h2><div class="grid"><div class="cell"><b>Perfil Glasgow</b><?=pv($d,'gcs_profile')?></div><div class="cell"><b>Ocular</b><?=pv($d,'gcs_eye')?></div><div class="cell"><b>Verbal</b><?=pv($d,'gcs_verbal')?></div><div class="cell"><b>Motora</b><?=pv($d,'gcs_motor')?></div><div class="cell span2"><b>Pupila D</b><?=pv($d,'pupil_r_size')?> mm — <?=pv($d,'pupil_r_reaction')?></div><div class="cell span2"><b>Pupila E</b><?=pv($d,'pupil_l_size')?> mm — <?=pv($d,'pupil_l_reaction')?></div><div class="cell span4"><b>Observações</b><?=pv($d,'neuro_notes')?></div></div></div>

<div class="sec"><h2>Trauma / obstétrico / pediátrico</h2><div class="grid"><div class="cell span2"><b>Mecanismo</b><?=pv($d,'trauma_mechanism')?></div><div class="cell span2"><b>Lesões</b><?=pv($d,'trauma_findings')?></div><div class="cell span2"><b>Obstétrico</b>Gestante: <?=pv($d,'pregnant')?> | IG: <?=pv($d,'gestational_age')?> | DUM/DPP: <?=pv($d,'obstetric_dates')?> | G/P/A: <?=pv($d,'parity')?> | Contrações: <?=pv($d,'contractions')?> | Perdas: <?=pv($d,'vaginal_loss')?></div><div class="cell span2"><b>Pediátrico</b>Peso: <?=pv($d,'pediatric_weight')?> | Responsável: <?=pv($d,'pediatric_guardian')?> | <?=pv($d,'pediatric_notes')?></div></div></div>

<div class="sec"><h2>Procedimentos / evolução</h2><div class="grid"><div class="cell span4"><b>Itens registrados</b>Via aérea: <?=yesv($d,'proc_airway')?> | O₂: <?=yesv($d,'proc_oxygen')?> | BVM: <?=yesv($d,'proc_bvm')?> | Aspiração: <?=yesv($d,'proc_suction')?> | RCP: <?=yesv($d,'proc_cpr')?> | DEA: <?=yesv($d,'proc_aed')?> | Curativo/compressão: <?=yesv($d,'proc_dressing')?> | Imobilização: <?=yesv($d,'proc_immobilization')?> | Glicemia: <?=yesv($d,'proc_glucose')?></div><div class="cell span2"><b>Detalhes O₂ / materiais</b><?=pv($d,'oxygen_details')?> | <?=pv($d,'immobilization_details')?></div><div class="cell span2"><b>Outros procedimentos</b><?=pv($d,'procedure_other_details')?></div><div class="cell span4"><b>Medicamentos administrados</b><?=pv($d,'medications_administered')?></div><div class="cell span4"><b>Evolução</b><?=pv($d,'evolution')?></div></div></div>

<div class="sec"><h2>Destino / entrega / desfecho</h2><div class="grid"><div class="cell span2"><b>Destino</b><?=pv($d,'destination')?></div><div class="cell"><b>Chegada</b><?=pv($d,'time_destination')?></div><div class="cell"><b>Desfecho</b><?=pv($d,'outcome')?></div><div class="cell span2"><b>Recebedor</b><?=pv($d,'receiver_name')?> — <?=pv($d,'receiver_role')?></div><div class="cell span2"><b>Passagem do caso</b><?=pv($d,'handover')?></div><div class="cell span4"><b>Observações finais / recusa</b><?=pv($d,'final_notes')?> | Responsável: <?=pv($d,'refusal_name')?> | Testemunha: <?=pv($d,'refusal_witness')?></div></div></div>

<div class="sec"><h2>Equipe e assinaturas</h2><div class="grid"><div class="cell span2"><b>Equipe</b><?=pv($d,'crew_members')?></div><div class="cell span2"><b>Staff</b><?=pv($d,'shift_staff')?></div></div>
<div class="signs"><?php foreach($sigs as $s):?><div class="sig <?=$s['valid']?'':'invalid'?>"><img src="signature_image.php?id=<?=$s['id']?>" alt="Assinatura"><strong><?=e($s['signer_bc_name']?:$s['signer_name'])?></strong><br><?=e($s['signature_capacity'])?><br><?=e($s['signed_at'])?><br><?=$s['valid']?'ASSINATURA VÁLIDA PARA A VERSÃO':'ASSINATURA INVALIDADA'?></div><?php endforeach;?></div>
</div>

<?php if(trim((string)($d['report_additional_observations']??''))!==''):?><div class="sec"><h2>Observações complementares</h2><div class="grid"><div class="cell span4"><?=nl2br(e((string)$d['report_additional_observations']))?></div></div></div><?php endif;?>

<div class="sec"><h2>Integridade do registro</h2><div class="grid"><div class="cell span4"><b>Hash SHA-256 do conteúdo atual</b><span class="hash"><?=e($r['content_hash'])?></span></div><div class="cell span2"><b>Criada em</b><?=e($r['created_at'])?></div><div class="cell span2"><b>Atualizada em</b><?=e($r['updated_at'])?></div></div></div>
</main><script src="assets/security.js"></script></body></html>
