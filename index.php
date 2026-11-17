<?php
declare(strict_types=1);

$authError = '';
$authSuccess = '';
$authTab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$openLogin = isset($_GET['login']) || isset($_GET['tab']) || isset($_GET['err']);
$units = [];
$bootOk = false;
$loggedUser = null;
if (($_GET['err'] ?? '') === 'csrf') {
    $authError = 'Sessão expirada. Tente entrar de novo.';
    $openLogin = true;
}

try {
    require_once __DIR__ . '/admin/includes/bootstrap.php';
    $bootOk = true;
    $units = db()->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();
    $loggedUser = current_user();

    if ($loggedUser && isset($_GET['admin'])) {
        redirect(admin_url('index.php'));
    }

    if (is_post()) {
        verify_csrf();
        $form = (string) ($_POST['form'] ?? '');

        if ($form === 'staff_login') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (attempt_login($email, $password)) {
                redirect(admin_url('index.php'));
            }
            $authError = 'E-mail ou senha inválidos.';
            $authTab = 'login';
            $openLogin = true;
        }

        if ($form === 'staff_register') {
            $authTab = 'register';
            $openLogin = true;
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $password2 = (string) ($_POST['password_confirm'] ?? '');
            $unitId = (int) ($_POST['unit_id'] ?? 0);

            if ($name === '' || $email === '' || $password === '') {
                $authError = 'Preencha nome, e-mail e senha.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $authError = 'Informe um e-mail válido.';
            } elseif (strlen($password) < 8) {
                $authError = 'A senha deve ter pelo menos 8 caracteres.';
            } elseif ($password !== $password2) {
                $authError = 'As senhas não coincidem.';
            } else {
                $exists = db()->prepare('SELECT id FROM users WHERE lower(email) = lower(?) LIMIT 1');
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    $authError = 'Já existe uma conta com este e-mail. Faça login.';
                    $authTab = 'login';
                } else {
                    $role = db()->query("SELECT id FROM roles WHERE slug = 'recepcao' LIMIT 1")->fetch();
                    if (!$role) {
                        $authError = 'Sistema ainda sem perfis. Execute: php database/seed.php';
                    } else {
                        if ($unitId <= 0 && $units) {
                            $unitId = (int) $units[0]['id'];
                        }
                        db()->prepare(
                            'INSERT INTO users (unit_id, role_id, name, email, password_hash, active)
                             VALUES (?, ?, ?, ?, ?, 1)'
                        )->execute([
                            $unitId > 0 ? $unitId : null,
                            (int) $role['id'],
                            $name,
                            $email,
                            password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        $newId = (int) db()->lastInsertId();
                        audit('user.register', 'users', $newId, ['source' => 'landing']);
                        if (attempt_login($email, $password)) {
                            redirect(admin_url('index.php'));
                        }
                        $authSuccess = 'Conta criada. Faça login para continuar.';
                        $authTab = 'login';
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    $bootOk = false;
    $authError = 'Falha ao iniciar autenticação. Rode: php database/seed.php';
    error_log('Landing boot error: ' . $e->getMessage());
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '';
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return '/admin/' . ltrim($path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Vértice Fitness — SUPERE SEUS LIMITES</title>
  <meta name="description" content="Concierge WhatsApp Vértice Fitness: planos, serviços, agendamento e atendimento premium." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/styles.css" />
  <link rel="icon" href="assets/logo-icon.png" type="image/png" />
</head>
<body class="<?= $openLogin ? 'login-open' : '' ?>">
  <div class="atmosphere" aria-hidden="true">
    <div class="atmosphere__base"></div>
    <div class="atmosphere__mesh atmosphere__mesh--tl"></div>
    <div class="atmosphere__mesh atmosphere__mesh--br"></div>
    <div class="atmosphere__beam atmosphere__beam--tl"></div>
    <div class="atmosphere__beam atmosphere__beam--tl2"></div>
    <div class="atmosphere__beam atmosphere__beam--br"></div>
    <div class="atmosphere__beam atmosphere__beam--br2"></div>
    <div class="atmosphere__glow atmosphere__glow--core"></div>
    <div class="atmosphere__glow atmosphere__glow--a"></div>
    <div class="atmosphere__glow atmosphere__glow--b"></div>
    <div class="atmosphere__vignette"></div>
    <div class="atmosphere__pulse"></div>
  </div>

  <header class="site-bar">
    <a class="site-bar__brand" href="/">
      <img src="assets/logo-icon.png" alt="" width="28" height="28" />
      <span>Vértice</span>
    </a>
    <?php if ($loggedUser): ?>
      <a class="site-bar__login" href="<?= e(admin_url('index.php')) ?>">Abrir painel</a>
    <?php else: ?>
      <button type="button" class="site-bar__login" id="open-login" aria-haspopup="dialog" aria-controls="login-panel">
        Login
      </button>
    <?php endif; ?>
  </header>

  <main class="stage" data-parallax>
    <aside class="brand">
      <p class="brand__eyebrow">
        <span class="brand__pulse" aria-hidden="true"></span>
        <span class="letter-fx" data-letters>Supere seus Limites</span>
      </p>

      <figure class="brand__hero">
        <img src="assets/logo-icon.png" alt="Ícone Vértice Fitness" class="brand__logo" />
        <div class="brand__sheen" aria-hidden="true"></div>
      </figure>

      <h1 class="brand__name">
        <span class="brand__name-main letter-fx" data-letters>Vértice</span>
        <span class="brand__name-sub letter-fx" data-letters>Fitness</span>
      </h1>

      <p class="brand__lead">
        Concierge no WhatsApp: planos, horários, agendamento e cadastro — com a precisão de uma experiência VIP.
      </p>

      <div class="brand__actions">
        <?php if (!$loggedUser): ?>
          <button type="button" class="brand__cta" id="open-login-cta" aria-controls="login-panel">
            Acessar área restrita
          </button>
        <?php else: ?>
          <a class="brand__cta" href="<?= e(admin_url('index.php')) ?>">Ir para a gestão</a>
        <?php endif; ?>
      </div>

      <div class="brand__rail" aria-label="Capacidades">
        <span>Planos</span>
        <span aria-hidden="true">·</span>
        <span>Agendamento</span>
        <span aria-hidden="true">·</span>
        <span>Cadastro</span>
        <span aria-hidden="true">·</span>
        <span>Concierge</span>
      </div>
    </aside>

    <section class="device" aria-label="Simulador WhatsApp Vértice Fitness">
      <div class="device__glow" aria-hidden="true"></div>
      <div class="device__shell">
        <div class="device__side device__side--left" aria-hidden="true"></div>
        <div class="device__side device__side--right" aria-hidden="true"></div>
        <div class="device__bezel">
          <div class="device__island" aria-hidden="true"></div>

          <header class="chat-header">
            <div class="chat-header__avatar">
              <img src="assets/logo-icon.png" alt="" />
            </div>
            <div class="chat-header__meta">
              <strong>Vértice Concierge</strong>
              <span id="status-text">
                <i class="status-dot" aria-hidden="true"></i>
                online · atendimento premium
              </span>
            </div>
            <div class="chat-header__actions" aria-hidden="true">
              <span></span><span></span><span></span>
            </div>
          </header>

          <div class="chat-body" id="chat-body" role="log" aria-live="polite" aria-relevant="additions"></div>

          <form class="chat-composer" id="composer" autocomplete="off">
            <label class="sr-only" for="user-input">Mensagem</label>
            <input
              id="user-input"
              name="message"
              type="text"
              placeholder="Digite sua mensagem…"
              maxlength="80"
              enterkeyhint="send"
            />
            <button type="submit" class="send-btn" aria-label="Enviar">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                <path fill="currentColor" d="M3.4 20.6 21 12 3.4 3.4l-.1 6.7L14 12 3.3 13.9z" />
              </svg>
            </button>
          </form>
        </div>
      </div>
      <p class="device__caption">Toque nas opções do chat para explorar o fluxo</p>
    </section>
  </main>

  <div
    class="login-overlay<?= $openLogin ? ' is-open' : '' ?>"
    id="login-panel"
    data-tab="<?= e($authTab) ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="login-title"
    aria-hidden="<?= $openLogin ? 'false' : 'true' ?>"
  >
    <button type="button" class="login-overlay__backdrop" id="close-login-backdrop" aria-label="Fechar login"></button>
    <section class="login-sheet">
      <button type="button" class="login-sheet__close" id="close-login" aria-label="Fechar">&times;</button>
      <p class="login-sheet__eyebrow">Área restrita</p>
      <h2 id="login-title" class="login-sheet__title">Gestão Vértice</h2>
      <p class="login-sheet__lead">Entre com sua conta ou crie um acesso para o painel administrativo.</p>

      <div class="login-tabs" role="tablist" aria-label="Acesso">
        <button type="button" class="login-tabs__btn<?= $authTab === 'login' ? ' is-active' : '' ?>" data-auth-tab="login" role="tab" aria-selected="<?= $authTab === 'login' ? 'true' : 'false' ?>">
          Entrar
        </button>
        <button type="button" class="login-tabs__btn<?= $authTab === 'register' ? ' is-active' : '' ?>" data-auth-tab="register" role="tab" aria-selected="<?= $authTab === 'register' ? 'true' : 'false' ?>">
          Criar conta
        </button>
      </div>

      <?php if ($authError !== ''): ?>
        <div class="login-sheet__error"><?= e($authError) ?></div>
      <?php endif; ?>
      <?php if ($authSuccess !== ''): ?>
        <div class="login-sheet__success"><?= e($authSuccess) ?></div>
      <?php endif; ?>

      <form
        method="post"
        action="index.php"
        class="login-sheet__form<?= $authTab === 'login' ? ' is-active' : '' ?>"
        id="form-login"
        data-auth-panel="login"
        autocomplete="on"
      >
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="staff_login" />
        <label>
          E-mail
          <input type="email" name="email" required value="<?= e($authTab === 'login' ? ($_POST['email'] ?? '') : '') ?>" placeholder="seu@email.com" />
        </label>
        <label>
          Senha
          <input type="password" name="password" required placeholder="••••••••" />
        </label>
        <button type="submit" class="login-sheet__submit">Entrar no painel</button>
        <p class="login-sheet__switch">
          Não tem conta?
          <button type="button" class="login-sheet__link" data-auth-tab="register">Criar conta</button>
        </p>
      </form>

      <form
        method="post"
        action="index.php"
        class="login-sheet__form<?= $authTab === 'register' ? ' is-active' : '' ?>"
        id="form-register"
        data-auth-panel="register"
        autocomplete="on"
      >
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="staff_register" />
        <label>
          Nome completo
          <input type="text" name="name" required value="<?= e($authTab === 'register' ? ($_POST['name'] ?? '') : '') ?>" placeholder="Seu nome" />
        </label>
        <label>
          E-mail
          <input type="email" name="email" required value="<?= e($authTab === 'register' ? ($_POST['email'] ?? '') : '') ?>" placeholder="seu@email.com" />
        </label>
        <?php if ($units): ?>
        <label>
          Unidade
          <select name="unit_id" required>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= (int) ($_POST['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
        <label>
          Senha
          <input type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres" />
        </label>
        <label>
          Confirmar senha
          <input type="password" name="password_confirm" required minlength="8" placeholder="Repita a senha" />
        </label>
        <p class="login-sheet__hint">Novas contas entram com perfil de <strong>Recepção</strong>. Um admin pode alterar o cargo depois.</p>
        <button type="submit" class="login-sheet__submit">Criar conta e entrar</button>
        <p class="login-sheet__switch">
          Já tem conta?
          <button type="button" class="login-sheet__link" data-auth-tab="login">Fazer login</button>
        </p>
      </form>
    </section>
  </div>

  <script src="assets/app.js"></script>
  <script src="assets/login-modal.js"></script>
</body>
</html>
