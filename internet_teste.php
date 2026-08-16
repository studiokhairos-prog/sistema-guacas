<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();

$urlFile=__DIR__.'/data/public_tunnel_url.txt';
$base=is_file($urlFile)?trim((string)file_get_contents($urlFile)):'';
$folder=basename(__DIR__);
$active=$base!=='' && preg_match('#^https://[A-Za-z0-9-]+\.trycloudflare\.com$#',$base);
$appBase=$active?rtrim($base,'/').'/'.rawurlencode($folder):'';
$links=$active?[
 'Página dos aplicativos'=>$appBase.'/portal.php',
 'Portal Público'=>$appBase.'/app_publico.php',
 'Solicitação pública'=>$appBase.'/solicitar_ocorrencia.php',
 'Acesso Bombeiros'=>$appBase.'/app_bombeiros.php',
 'Login'=>$appBase.'/login.php',
]:[];
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Internet de Teste - <?=e(app_display_name())?></title>
<link rel="manifest" href="manifest_bombeiros.php"><link rel="stylesheet" href="assets/app.css">
</head><body>
<button class="back-floating" onclick="history.length>1?history.back():location.href='base.php'">← Voltar</button>
<header class="topbar"><div class="top-left"><img src="assets/logo_oficial_bombeiros.jpeg" class="logo-mini"><div><strong><?=e(app_display_name())?></strong><span>Sistema Web na Internet — Homologação</span></div></div>
<div class="right"><a href="implantacao.php">Apps</a><a href="nuvem.php">☁️ Nuvem</a><a href="homologacao.php">Homologação</a><a href="base.php">Central</a></div></header>
<main class="layout">
<section class="card internet-test-hero">
<div><h1>🌐 Internet gratuita para TESTE do sistema web</h1>
<p>Esta área ajuda a testar Portal Público e Acesso Bombeiros fora do computador, usando um endereço HTTPS temporário.</p></div>
<div class="cloud-status <?=$active?'connected':'disconnected'?>"><?=$active?'✅ LINK GERADO':'⚪ AINDA NÃO INICIADO'?></div>
</section>

<div class="alert warning"><strong>HOMOLOGAÇÃO / TESTE:</strong> use dados fictícios. Este modo temporário não deve ser tratado como hospedagem operacional definitiva. O computador, XAMPP e o túnel precisam permanecer ligados.</div>

<section class="card"><h2>Como iniciar</h2>
<div class="easy-cloud-steps">
<article><span>1</span><div><strong>Deixe o Apache ligado</strong><p>No XAMPP, Apache deve aparecer em execução.</p></div></article>
<article><span>2</span><div><strong>Execute na pasta da GUACAS</strong><code>INICIAR_INTERNET_MODO_VISIVEL.bat</code></div></article>
<article><span>3</span><div><strong>Aguarde o link HTTPS</strong><p>A janela ficará aberta e mostrará o endereço trycloudflare.com. Copie o endereço e acrescente o caminho da GUACAS mostrado na própria janela.</p></div></article>
<article><span>4</span><div><strong>Teste no celular</strong><p>Abra o link usando 4G/5G ou outra internet e instale os apps.</p></div></article>
</div></section>

<?php if($active):?>
<section class="card"><h2>Links temporários gerados</h2>
<div class="deployment-links">
<?php foreach($links as $label=>$link):?>
<div><strong><?=e($label)?></strong><code id="<?=e(md5($label))?>"><?=e($link)?></code><button type="button" class="copy-link" data-copy="<?=e(md5($label))?>">Copiar</button></div>
<?php endforeach;?>
</div>
<p class="muted">Se você reiniciar/criar outro Quick Tunnel, o endereço pode mudar. Use sempre os links gerados pela execução mais recente.</p>
</section>
<?php endif;?>

<section class="card"><h2>Encerrar o teste</h2><p>Quando terminar, execute:</p><div class="sync-path-box"><code>ENCERRAR_INTERNET_GRATIS.bat</code></div><p>O endereço temporário deixa de encaminhar novas conexões para seu XAMPP.</p></section>

<section class="card"><h2>O que vamos testar</h2>
<p>Primeiro: abrir Portal Público pelo celular, enviar uma ocorrência fictícia e confirmar que a Central recebe o alerta. Depois: abrir Acesso Bombeiros em outro celular/computador, fazer login de teste, instalar como aplicativo e verificar GPS, estados da ocorrência e fluxo offline.</p>
</section>
</main>
<script src="assets/security.js"></script><script>
document.querySelectorAll('[data-copy]').forEach(b=>b.addEventListener('click',async()=>{
 const el=document.getElementById(b.dataset.copy);if(!el)return;
 try{await navigator.clipboard.writeText(el.textContent.trim());b.textContent='COPIADO ✅';}catch(e){}
}));
</script></body></html>
