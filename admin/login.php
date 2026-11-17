<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(admin_url('index.php'));
}

$error = '';
if (is_post()) {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (attempt_login($email, $password)) {
        redirect(admin_url('index.php'));
    }
    $error = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar · Vértice Gestão</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(admin_url('assets/admin.css')) ?>">
</head>
<body class="login-body">
  <div class="login-atmosphere" aria-hidden="true"></div>
  <main class="login-card">
    <p class="login-brand">Vértice</p>
    <h1>Gestão</h1>
    <p class="login-lead">Acesse o painel administrativo da academia.</p>
    <?php if ($error): ?>
      <div class="flash flash--error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
      <?= csrf_field() ?>
      <label>
        E-mail
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </label>
      <label>
        Senha
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
  </main>
</body>
</html>
