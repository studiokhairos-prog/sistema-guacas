<?php
require __DIR__ . '/config.php';
$pdo=db();
$err='';

/*
SOLICITAÇÃO PÚBLICA RÁPIDA
O solicitante informa apenas:
1) nome + telefone
2) o que está acontecendo
3) nível percebido
4) endereço (GPS tenta ser capturado automaticamente)
5) observação opcional

A Central continua podendo reclassificar natureza, nível e prioridade.
*/
$quickTypes = [
    'SEM_RESPIRAR' => [
        'label' => 'NÃO RESPIRA / INCONSCIENTE',
        'nature' => 'EMERGÊNCIA CRÍTICA',
        'type' => 'PARADA CARDIORRESPIRATÓRIA / INCONSCIÊNCIA'
    ],
    'MAL_SUBITO' => [
        'label' => 'MAL SÚBITO / DESMAIO',
        'nature' => 'ATENDIMENTO PRÉ-HOSPITALAR',
        'type' => 'MAL SÚBITO / DESMAIO'
    ],
    'RESPIRACAO' => [
        'label' => 'DIFICULDADE PARA RESPIRAR',
        'nature' => 'ATENDIMENTO PRÉ-HOSPITALAR',
        'type' => 'DIFICULDADE RESPIRATÓRIA'
    ],
    'ENGASGO' => [
        'label' => 'ENGASGO',
        'nature' => 'EMERGÊNCIA CRÍTICA',
        'type' => 'ENGASGO / OVACE'
    ],
    'QUEDA' => [
        'label' => 'QUEDA / TRAUMA',
        'nature' => 'TRAUMA',
        'type' => 'QUEDA / TRAUMA'
    ],
    'TRANSITO' => [
        'label' => 'ACIDENTE DE TRÂNSITO',
        'nature' => 'TRAUMA',
        'type' => 'ACIDENTE DE TRÂNSITO'
    ],
    'SANGRAMENTO' => [
        'label' => 'SANGRAMENTO / FERIMENTO',
        'nature' => 'TRAUMA',
        'type' => 'SANGRAMENTO / FERIMENTO'
    ],
    'INCENDIO' => [
        'label' => 'INCÊNDIO / FUMAÇA',
        'nature' => 'INCÊNDIO',
        'type' => 'INCÊNDIO / FUMAÇA'
    ],
    'OUTRO' => [
        'label' => 'OUTRA SITUAÇÃO',
        'nature' => 'OUTRA',
        'type' => 'OUTRA'
    ],
];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')){
        $err='Sessão inválida. Atualize a página e tente novamente.';
    }elseif(trim($_POST['website']??'')!==''){
        $err='Não foi possível registrar a solicitação.';
    }elseif(public_request_rate_limited($pdo)){
        $err='Muitas solicitações foram enviadas deste acesso. Aguarde alguns minutos ou utilize os canais alternativos.';
    }elseif(!empty($_SESSION['last_public_occurrence_at']) && time()-(int)$_SESSION['last_public_occurrence_at']<20){
        $err='Aguarde alguns segundos antes de enviar outra solicitação.';
    }else{
        $requester=upper_text($_POST['requester_name']??'');
        $phone=trim($_POST['requester_phone']??'');
        $quickKey=upper_text($_POST['quick_type']??'');
        $address=upper_text($_POST['address']??'');
        $details=upper_text($_POST['details']??'');
        $customType=upper_text($_POST['custom_type']??'');

        $lat=($_POST['lat']??'')!==''?(float)$_POST['lat']:null;
        $lng=($_POST['lng']??'')!==''?(float)$_POST['lng']:null;
        $gpsAccuracy=($_POST['gps_accuracy']??'')!==''?(float)$_POST['gps_accuracy']:null;

        $urgency=upper_text($_POST['urgency']??'NAO_SEI');
        $occurrenceLevel=match($urgency){
            'RISCO_VIDA' => 'N1_CRITICO',
            'URGENTE' => 'N2_URGENTE',
            default => 'NAO_CLASSIFICADO'
        };

        if(!isset($quickTypes[$quickKey])) $quickKey='OUTRO';
        $nature=$quickTypes[$quickKey]['nature'];
        $type=$quickTypes[$quickKey]['type'];

        if($quickKey==='OUTRO'){
            if($customType!==''){
                $nature='OUTRA';
                $type=$customType;
            }else{
                $type='OUTRA SITUAÇÃO';
            }
        }

        if(mb_strlen($requester)<3 || mb_strlen($phone)<8 || mb_strlen($address)<5 || $quickKey===''){
            $err='Preencha seu nome, telefone, o que está acontecendo e o local.';
        }else{
            try{
                $pdo->beginTransaction();
                $protocol=protocol_new();
                $now=now_iso();

                $detailParts=[];
                if($details!=='') $detailParts[]='INFORMAÇÃO RÁPIDA DO SOLICITANTE: '.$details;
                if($gpsAccuracy!==null) $detailParts[]='GPS DO SOLICITANTE COM PRECISÃO APROXIMADA DE ±'.round($gpsAccuracy).' M.';
                $fullDetails=implode("\n",$detailParts);

                $priority=occurrence_level_priority($occurrenceLevel);

                $st=$pdo->prepare("
                    INSERT INTO occurrences(
                        protocol,nature,type,address,priority,occurrence_level,
                        team,status,details,lat,lng,requester_gps_accuracy,
                        created_by,requested_at,created_at,updated_at,source,
                        requester_name,requester_phone,requester_relation,patient_name_hint
                    )
                    VALUES(
                        ?,?,?,?,?,?,
                        NULL,'SOLICITADA',?,?,?,?,NULL,?,?,?,'PUBLICO',
                        ?,?,'SOLICITANTE',NULL
                    )
                ");
                $st->execute([
                    $protocol,$nature,$type,$address,$priority,$occurrenceLevel,
                    $fullDetails?:null,$lat,$lng,$gpsAccuracy,
                    $now,$now,$now,$requester,$phone
                ]);

                $id=(int)$pdo->lastInsertId();

                $ev=$pdo->prepare("
                    INSERT INTO occurrence_events(
                        occurrence_id,event_type,new_status,note,user_id,created_at
                    ) VALUES(?,?,?,?,NULL,?)
                ");
                $ev->execute([
                    $id,
                    'SOLICITACAO_PUBLICA',
                    'SOLICITADA',
                    'SOLICITAÇÃO PÚBLICA RÁPIDA ABERTA POR '.$requester.
                    ' · TIPO: '.$type.
                    ' · NÍVEL INICIAL: '.occurrence_level_label($occurrenceLevel),
                    $now
                ]);

                security_audit($pdo,null,'PUBLIC_OCCURRENCE_CREATED','OCCURRENCE',(string)$id,true,'Solicitação pública registrada.');
                $pdo->commit();

                $_SESSION['last_public_occurrence_at']=time();
                $_SESSION['public_occurrence_success']=[
                    'protocol'=>$protocol,
                    'requester_name'=>$requester,
                    'created_at'=>$now,
                    'occurrence_level'=>$occurrenceLevel
                ];

                header('Location: solicitacao_agradecimento.php');
                exit;
            }catch(Throwable $e){
                if($pdo->inTransaction()) $pdo->rollBack();
                $err='Não foi possível registrar agora. Utilize também os canais alternativos disponíveis.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#8b1e24">
<title>Solicitar ocorrência - <?=e(app_display_name())?></title>
<link rel="manifest" href="manifest_publico.php"><link rel="apple-touch-icon" href="assets/icons/guacas-publico-180.png"><link rel="stylesheet" href="assets/app.css">
<style>
.quick-request-card{width:min(720px,100%);margin:auto}
.quick-request-card h1{text-align:center;margin-bottom:5px}
.quick-request-sub{text-align:center;margin-bottom:16px}
.quick-step{margin:14px 0;padding:14px;border:1px solid #dfc98e;border-radius:14px;background:#fffdf8}
.quick-step-title{display:flex;align-items:center;gap:9px;font-size:17px;font-weight:900;color:#7c0c12;margin-bottom:10px}
.step-number{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#a80e16;color:#fff;border:2px solid #f2b51d;flex:0 0 auto}
.quick-type-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.quick-type{position:relative}
.quick-type input{position:absolute;opacity:0;pointer-events:none}
.quick-type span{display:flex;align-items:center;justify-content:center;min-height:56px;padding:9px;border:2px solid #dbc175;border-radius:11px;background:#fff8e8;color:#5e0b10;font-weight:900;text-align:center;cursor:pointer;transition:.15s}
.quick-type input:checked+span{background:linear-gradient(135deg,#a80e16,#70080d);color:#fff;border-color:#f2b51d;box-shadow:0 0 0 3px #f2b51d33}
.quick-type span:hover{transform:translateY(-1px)}
.urgency-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.urgency-option{position:relative}
.urgency-option input{position:absolute;opacity:0;pointer-events:none}
.urgency-option span{display:grid;place-items:center;min-height:66px;padding:8px;border:2px solid #d6c5a4;border-radius:11px;background:#fff;text-align:center;font-weight:900;cursor:pointer}
.urgency-option input:checked+span{border-color:#f2b51d;box-shadow:0 0 0 3px #f2b51d33}
.urgency-critical input:checked+span{background:#8e0c13;color:#fff}
.urgency-fast input:checked+span{background:#e9911a;color:#fff}
.urgency-unknown input:checked+span{background:#2b2b2b;color:#fff}
.gps-fast-box{padding:10px;border-radius:10px;background:#eef8f1;border:1px solid #8fc9a6;margin-top:8px}
.gps-fast-box button{width:auto}
.optional-box{margin-top:10px}
.optional-box summary{cursor:pointer;font-weight:800;color:#721015}
.submit-public-fast{font-size:19px;padding:15px}
.public-emergency-note{font-size:13px}
.custom-type-wrap{margin-top:9px}
@media(max-width:650px){
 .quick-type-grid{grid-template-columns:1fr 1fr}
 .urgency-grid{grid-template-columns:1fr}
 .quick-request-card{padding:12px}
}
</style>
</head>
<body class="public-bg">
<button type="button" class="back-floating no-print" onclick="history.length>1?history.back():location.href='login.php'">← Voltar</button>

<main class="public-request-wrap">
<section class="card public-request-card quick-request-card">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-auth" alt="Logo oficial"></div>
<h1>🚨 Solicitar ocorrência</h1>
<p class="quick-request-sub"><strong>RÁPIDO E SIMPLES</strong> — preencha somente o essencial para a equipe começar o atendimento.</p>

<div class="notice medical-note public-emergency-note">
<strong>Atenção:</strong> esta página envia um pedido para a guarnição. Em situação de risco imediato, utilize também os canais públicos de emergência e os meios locais disponíveis.
</div>

<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>

<form method="post" id="publicOccurrence">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="text" name="website" tabindex="-1" autocomplete="off" class="hp-field" aria-hidden="true">
<input type="hidden" name="lat" id="pubLat">
<input type="hidden" name="lng" id="pubLng">
<input type="hidden" name="gps_accuracy" id="pubAccuracy">

<div class="quick-step">
<div class="quick-step-title"><span class="step-number">1</span> Quem está solicitando?</div>
<div class="grid2">
<label>Seu nome<input name="requester_name" required autocomplete="name" placeholder="NOME COMPLETO"></label>
<label>Telefone / WhatsApp<input name="requester_phone" required inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000"></label>
</div>
</div>

<div class="quick-step">
<div class="quick-step-title"><span class="step-number">2</span> O que está acontecendo?</div>
<div class="quick-type-grid">
<label class="quick-type"><input type="radio" name="quick_type" value="SEM_RESPIRAR" required><span>🫁 NÃO RESPIRA / INCONSCIENTE</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="MAL_SUBITO"><span>🤒 MAL SÚBITO / DESMAIO</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="RESPIRACAO"><span>😮‍💨 DIFICULDADE PARA RESPIRAR</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="ENGASGO"><span>🫢 ENGASGO</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="QUEDA"><span>🩹 QUEDA / TRAUMA</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="TRANSITO"><span>🚗 ACIDENTE DE TRÂNSITO</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="SANGRAMENTO"><span>🩸 SANGRAMENTO / FERIMENTO</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="INCENDIO"><span>🔥 INCÊNDIO / FUMAÇA</span></label>
<label class="quick-type"><input type="radio" name="quick_type" value="OUTRO" id="quickOther"><span>➕ OUTRA SITUAÇÃO</span></label>
</div>
<div id="customTypeWrap" class="custom-type-wrap" hidden>
<label>Descreva em poucas palavras<input name="custom_type" id="customType" placeholder="EX.: ANIMAL EM LOCAL DE RISCO"></label>
</div>
</div>

<div class="quick-step">
<div class="quick-step-title"><span class="step-number">3</span> Existe risco de vida agora?</div>
<div class="urgency-grid">
<label class="urgency-option urgency-critical"><input type="radio" name="urgency" value="RISCO_VIDA"><span>🔴 SIM<br>RISCO DE VIDA AGORA</span></label>
<label class="urgency-option urgency-fast"><input type="radio" name="urgency" value="URGENTE"><span>🟠 PRECISA DE ATENDIMENTO RÁPIDO</span></label>
<label class="urgency-option urgency-unknown"><input type="radio" name="urgency" value="NAO_SEI" checked><span>⚪ NÃO SEI<br>A CENTRAL DEFINE</span></label>
</div>
<p class="muted">Esta informação é apenas inicial. A Central pode reclassificar a ocorrência.</p>
</div>

<div class="quick-step">
<div class="quick-step-title"><span class="step-number">4</span> Onde aconteceu?</div>
<label>Endereço / local<input name="address" required autocomplete="street-address" placeholder="RUA, NÚMERO, BAIRRO OU PONTO CONHECIDO"></label>

<div class="gps-fast-box">
<strong>📍 GPS DO SOLICITANTE</strong>
<div><span id="locationState" class="muted">Tentando obter sua localização automaticamente...</span></div>
<button type="button" id="useLocation">Atualizar GPS</button>
</div>
</div>

<details class="quick-step optional-box">
<summary>➕ Deseja acrescentar uma informação? (opcional)</summary>
<label style="margin-top:10px">Informação rápida
<textarea name="details" rows="3" maxlength="700" placeholder="EX.: PACIENTE ESTÁ NO PORTÃO PRINCIPAL; HÁ FUMAÇA; VEÍCULO ESTÁ NA PISTA..."></textarea>
</label>
</details>

<button class="primary submit-public-fast">🚨 ENVIAR SOLICITAÇÃO AGORA</button>
</form>

<div class="action-bar">
<a class="button-link" href="login.php">Acesso da equipe</a>
<a class="whatsapp-button compact" href="contato.php">WhatsApp de ocorrência / denúncia</a>
<a class="button-link" href="privacidade.php">🔒 Privacidade</a>
</div>
</section>
</main>

<script>
(()=>{
 const other=document.getElementById('quickOther');
 const wrap=document.getElementById('customTypeWrap');
 const custom=document.getElementById('customType');

 document.querySelectorAll('input[name="quick_type"]').forEach(r=>{
   r.addEventListener('change',()=>{
     const isOther=r.value==='OUTRO' && r.checked;
     wrap.hidden=!isOther;
     custom.required=isOther;
     if(isOther) custom.focus();
   });
 });

 const locBtn=document.getElementById('useLocation');
 const lat=document.getElementById('pubLat');
 const lng=document.getElementById('pubLng');
 const accuracy=document.getElementById('pubAccuracy');
 const state=document.getElementById('locationState');

 function captureRequesterGps(silent=false){
   if(!navigator.geolocation){
     state.textContent='GPS não disponível. Continue informando o endereço.';
     return;
   }
   if(!silent) state.textContent='Obtendo localização GPS...';

   navigator.geolocation.getCurrentPosition(pos=>{
     lat.value=pos.coords.latitude;
     lng.value=pos.coords.longitude;
     accuracy.value=Math.round(pos.coords.accuracy||0);
     state.textContent='✅ GPS anexado · precisão aproximada ±'+Math.round(pos.coords.accuracy||0)+' m.';
   },()=>{
     state.textContent='GPS não autorizado ou indisponível. O endereço será usado normalmente.';
   },{
     enableHighAccuracy:true,
     timeout:10000,
     maximumAge:15000
   });
 }

 locBtn?.addEventListener('click',()=>captureRequesterGps(false));
 window.addEventListener('load',()=>setTimeout(()=>captureRequesterGps(true),500));

 // Foco rápido no primeiro campo.
 setTimeout(()=>document.querySelector('input[name="requester_name"]')?.focus(),200);
})();
</script>
</body>
</html>
