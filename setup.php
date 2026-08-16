<?php
require __DIR__ . '/config.php';
$pdo = db();
if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) { header('Location: login.php'); exit; }
$error = '';
$setupProtected = is_file(SETUP_CODE_HASH_FILE);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = upper_text($_POST['name'] ?? '');
    $warName = upper_text($_POST['war_name'] ?? '');
    $username = upper_text($_POST['username'] ?? '');
    $email = lower_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $bloodType = $_POST['blood_type'] ?? 'NÃO SABE';
    $certificate = upper_text($_POST['firefighter_certificate_number'] ?? '');
    $cpf = normalize_cpf($_POST['cpf'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');

    $csrfOk = hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''));
    $setupCodeOk = true;
    if ($setupProtected) {
        $storedHash = trim((string)file_get_contents(SETUP_CODE_HASH_FILE));
        $setupCodeOk = $storedHash !== '' && password_verify((string)($_POST['setup_code'] ?? ''), $storedHash);
    }

    if (!$csrfOk) {
        $error = 'Sessão inválida. Atualize a página e tente novamente.';
    } elseif (!$setupCodeOk) {
        $error = 'Código de implantação inválido.';
    } elseif (mb_strlen($name) < 5 || mb_strlen($warName) < 2 || mb_strlen($username) < 3 || strlen($password) < 10 || !in_array($bloodType,blood_type_options(),true) || mb_strlen($certificate) < 3 || !valid_cpf($cpf) || !valid_birth_date($birthDate)) {
        $error = 'Preencha os dados obrigatórios. CPF e data de nascimento válidos são necessários para recuperação futura da senha.';
    } else {
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
                throw new RuntimeException('A configuração inicial já foi concluída.');
            }
            $stmt = $pdo->prepare("INSERT INTO users(name,war_name,bc_name,username,email,password_hash,role,blood_type,firefighter_certificate_number,cpf_hash,cpf_last4,birth_date,created_at) VALUES(?,?,?,?,?,?,'ADMIN',?,?,?,?,?,?)");
            $stmt->execute([$name,$warName,'',$username,$email!==''?$email:null,password_hash($password,PASSWORD_DEFAULT),$bloodType,$certificate,store_cpf_hash($cpf),cpf_last4($cpf),$birthDate,now_iso()]);
            recalculate_all_bc_names($pdo); ensure_user_registration_numbers($pdo);
            $pdo->commit();
            if ($setupProtected) @unlink(SETUP_CODE_HASH_FILE);
            header('Location: login.php?setup=1'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a configuração inicial.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Primeiro acesso - <?=e(app_display_name())?></title><link rel="stylesheet" href="assets/app.css"></head>
<body class="center"><main class="card auth">
<div class="logo-wrap"><img src="assets/logo_oficial_bombeiros.jpeg" alt="Logo oficial" class="logo-auth"></div>
<h1><?=e(app_display_name())?></h1><p><strong><?=e(ORG_NAME)?></strong></p><p>Primeiro acesso: cadastre o 1º Administrador Geral.</p>
<?php if($error): ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<?php if($setupProtected):?><label>Código de implantação<input type="password" name="setup_code" required autocomplete="one-time-code" minlength="10"></label><?php endif;?>
<label>Nome completo<input name="name" required></label>
<label>Nome de farda<input name="war_name" placeholder="Ex.: SILVA" required></label>
<label>Nº do Certificado de Bombeiro Civil<input name="firefighter_certificate_number" required placeholder="Número constante no certificado"></label>
<div class="grid2"><label>CPF <span class="small">(usado na recuperação de senha)</span><input name="cpf" required inputmode="numeric" autocomplete="off" placeholder="Somente números ou CPF formatado"></label><label>Data de nascimento<input type="date" name="birth_date" required></label></div>
<label>Tipo sanguíneo informado<select name="blood_type" required><?php foreach(blood_type_options() as $bt):?><option value="<?=e($bt)?>" <?=$bt==='NÃO SABE'?'selected':''?>><?=e($bt)?></option><?php endforeach;?></select></label>
<label>Usuário<input name="username" required autocomplete="username"></label>
<label>E-mail <span class="small">(minúsculo)</span><input type="email" name="email" data-lowercase="email"></label>
<label>Senha forte<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
<button class="primary">Criar Administrador Geral</button>
</form>
<p class="muted">São permitidos no máximo 4 Administradores Gerais com poder de alteração geral.</p>
</main></body></html>
