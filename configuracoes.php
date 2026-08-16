<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);
$pdo=db();
$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals(csrf_token(),$_POST['csrf']??'')) exit('CSRF inválido');
    $action=$_POST['action']??'';
    if($action==='system_name'){
        $systemName=trim($_POST['system_name']??'');
        $just=trim($_POST['justification']??'');
        if(mb_strlen($systemName)<2 || mb_strlen($systemName)>40){$err='Informe um nome do sistema entre 2 e 40 caracteres.';}
        elseif(mb_strlen($just)<5){$err='Informe uma justificativa para alterar o nome do sistema.';}
        else{
            $old=app_display_name();
            update_system_setting('system_name',$systemName,(int)$admin['id']);
            admin_audit($pdo,(int)$admin['id'],'UPDATE','SETTING','system_name',$just,'Nome alterado de '.$old.' para '.$systemName);
            $msg='Nome do sistema atualizado. A alteração será exibida nas telas e documentos.';
        }
    }elseif($action==='base_address'){
        $address=trim($_POST['central_base_address']??'');
        $just=trim($_POST['justification']??'');
        if(mb_strlen($just)<5){$err='Informe uma justificativa para atualizar a configuração.';}
        else{
            update_system_setting('central_base_address',$address,(int)$admin['id']);
            admin_audit($pdo,(int)$admin['id'],'UPDATE','SETTING','central_base_address',$just,$address===''?'Endereço mantido em branco.':'Endereço atualizado.');
            $msg='Configuração da Base Central salva.';
        }
    }elseif($action==='whatsapp'){
        $occ=normalize_whatsapp_number($_POST['whatsapp_occurrence']??'');
        $complaints=normalize_whatsapp_number($_POST['whatsapp_complaints']??'');
        $just=trim($_POST['justification']??'');
        if(mb_strlen($just)<5){$err='Informe uma justificativa para atualizar os canais de WhatsApp.';}
        else{
            update_system_setting('whatsapp_occurrence',$occ,(int)$admin['id']);
            update_system_setting('whatsapp_complaints',$complaints,(int)$admin['id']);
            admin_audit($pdo,(int)$admin['id'],'UPDATE','SETTING','whatsapp_channels',$just,'Canais de ocorrência e denúncias atualizados.');
            $msg='Canais de WhatsApp atualizados.';
        }
    }elseif($action==='add_catalog'){
        $nature=normalize_name($_POST['nature']??'');$type=trim($_POST['type']??'');
        if(mb_strlen($nature)<3||mb_strlen($type)<3){$err='Preencha natureza e tipo.';}
        else{
            try{$st=$pdo->prepare("INSERT INTO occurrence_catalog(nature,type,active,sort_order) VALUES(?,?,1,?)");$st->execute([$nature,$type,500]);$id=(int)$pdo->lastInsertId();admin_audit($pdo,(int)$admin['id'],'CREATE','OCCURRENCE_CATALOG',(string)$id,'Inclusão no catálogo',$nature.' / '.$type);$msg='Natureza/tipo incluído no catálogo rápido.';}catch(Throwable $e){$err='Esse item já existe no catálogo.';}
        }
    }
}
$address=system_setting('central_base_address','');
$systemName=app_display_name();
$whOcc=system_setting('whatsapp_occurrence','');
$whComplaints=system_setting('whatsapp_complaints','');
$catalog=$pdo->query("SELECT * FROM occurrence_catalog ORDER BY active DESC,nature,sort_order,type")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(app_display_name())?> - Configurações</title><link rel="stylesheet" href="assets/app.css"></head><body><button type="button" class="back-floating no-print" onclick="if(history.length>1){history.back()}else{location.href='index.php'}" aria-label="Voltar para a página anterior">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini" alt="Logo"><div><strong><?=e(app_display_name())?></strong><span>Configurações Gerais</span></div></div><div class="right"><span class="pill">Somente Admin Geral</span><a href="base.php">Central</a><a href="equipes.php">Equipes</a><a href="usuarios.php">Usuários</a><a href="viaturas.php">Viaturas</a><a href="materiais.php">Materiais</a><a href="relatorios.php">Relatórios</a><a href="seguranca.php">Segurança</a><a href="logout.php">Sair</a></div></header>
<main class="layout">
<?php if($msg):?><div class="alert ok"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<section class="card"><h2>Identidade do sistema</h2><p class="muted">O nome inicial é <strong>GUACAS</strong>. Somente um Admin Geral pode alterar o nome exibido no sistema. A alteração fica registrada na auditoria.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="system_name">
<label>Nome do sistema<input name="system_name" maxlength="40" required value="<?=e($systemName)?>" placeholder="Ex.: GUACAS"></label>
<label>Justificativa da alteração<textarea name="justification" required placeholder="Informe por que o nome está sendo alterado."></textarea></label>
<button class="primary">Salvar nome do sistema</button></form></section>

<section class="card"><h2>Base Central</h2><p class="muted">O endereço ficará em branco até a Base Central ser construída. Quando houver endereço oficial, um Admin Geral poderá preenchê-lo aqui.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="base_address">
<label>Endereço oficial da Base Central<textarea name="central_base_address" placeholder="Deixar em branco por enquanto."><?=e($address)?></textarea></label>
<label>Justificativa da atualização<textarea name="justification" required placeholder="Ex.: cadastro do endereço oficial / correção de endereço / manter aguardando construção."></textarea></label>
<button class="primary">Salvar configuração</button></form></section>

<section class="card"><h2>Canais de WhatsApp</h2><p class="muted">Cadastre os números oficiais que serão exibidos como atalhos de contato. Deixe em branco enquanto o canal ainda não estiver definido.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="whatsapp"><div class="grid2"><label>WhatsApp — Ocorrências<input name="whatsapp_occurrence" value="<?=e($whOcc)?>" inputmode="tel" placeholder="DDD + número"></label><label>WhatsApp — Denúncias<input name="whatsapp_complaints" value="<?=e($whComplaints)?>" inputmode="tel" placeholder="DDD + número"></label></div><label>Justificativa da atualização<textarea name="justification" required placeholder="Ex.: cadastro dos canais oficiais / troca de número"></textarea></label><button class="primary">Salvar canais de WhatsApp</button></form><p class="muted">Os botões públicos só ficam ativos quando o respectivo número estiver cadastrado.</p></section>

<section class="card"><h2>Catálogo rápido de ocorrências</h2><p class="muted">Natureza e tipo aparecem na abertura rápida da ocorrência. Os Admins Gerais podem ampliar ou ajustar este catálogo.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_catalog"><div class="grid2"><label>Natureza<input name="nature" required placeholder="Ex.: TRAUMA"></label><label>Tipo<input name="type" required placeholder="Ex.: Queda de altura"></label></div><button class="primary">Adicionar ao catálogo</button></form>
<div class="table-wrap"><table><thead><tr><th>Natureza</th><th>Tipo</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach($catalog as $c):?><tr><td><?=e($c['nature'])?></td><td><?=e($c['type'])?></td><td><?=$c['active']?'ATIVO':'INATIVO'?></td><td><a class="button-link" href="catalogo_editar.php?id=<?=$c['id']?>">Editar</a></td></tr><?php endforeach;?></tbody></table></div></section>
</main><script src="assets/security.js"></script></body></html>
