<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
if(current_user()) redirect('dashboard.php');

$telegram = $config['telegram'] ?? [];
$telegramClientId = trim((string)($telegram['client_id'] ?? ''));
$telegramReady = $telegramClientId !== '';
if ($telegramReady && empty($_SESSION['telegram_login_nonce'])) {
    $_SESSION['telegram_login_nonce'] = bin2hex(random_bytes(24));
}
$telegramNonce = (string)($_SESSION['telegram_login_nonce'] ?? '');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Вход — Сметограм</title>
  <meta name="description" content="Вход в Сметограм через Telegram.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=20261008-logo1" rel="stylesheet">
  <link rel="icon" href="/logo.svg" type="image/svg+xml">
</head>
<body class="auth-body">
<div class="auth-page auth-page-clean">
  <div class="auth-orb auth-orb-one" aria-hidden="true"></div>
  <div class="auth-orb auth-orb-two" aria-hidden="true"></div>

  <main class="auth-clean-card">
    <div class="auth-clean-top">
      <a class="auth-clean-brand" href="index.php" aria-label="Сметограм">
        <span class="brand-mark">S</span>
        <span>сметограм</span>
      </a>
      <span class="auth-clean-label">АВТОРИЗАЦИЯ</span>
    </div>

    <div class="auth-clean-content">
      <div class="auth-clean-icon"><i class="fa-solid fa-lock"></i></div>
      <div class="eyebrow">ВХОД В АККАУНТ</div>
      <h1>Войдите в Сметограм</h1>
      <p class="auth-login-lead">Сметы, график работ, документы и приёмка — в одном рабочем пространстве.</p>

      <?php if ($telegramReady): ?>
        <div class="telegram-login-box auth-clean-login-box">
          <a href="telegram-start.php" class="telegram-login-button" id="telegramLoginButton">
            <i class="fa-brands fa-telegram"></i>
            <span>Войти через Telegram</span>
          </a>
          <?php if (!empty($_SESSION['telegram_login_error'])): ?>
            <div class="auth-error mt-3"><?=htmlspecialchars((string)$_SESSION['telegram_login_error'])?></div>
            <?php unset($_SESSION['telegram_login_error']); ?>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="auth-error">Telegram-вход пока не настроен. Добавьте TELEGRAM_CLIENT_ID в секреты GitHub Actions.</div>
      <?php endif; ?>

      <div class="auth-login-security auth-clean-security">
        <span class="auth-security-dot"></span>
        <div>
          <strong>Без пароля и SMS</strong>
          <small>Telegram подтверждает вашу личность, а пароль Telegram остаётся у Telegram.</small>
        </div>
      </div>

      <div class="auth-clean-bottom">
        <span><i class="fa-solid fa-sparkles"></i> Первый проект — бесплатно</span>
        <a href="guide.php"><i class="fa-solid fa-book-open"></i> Как пользоваться</a>
      </div>
    </div>
  </main>

  <div class="auth-clean-caption">Сметы без Excel-хаоса</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js?v=20261007-login1"></script>
</body>
</html>
