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

$pageTitle='Вход';
require __DIR__.'/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-login-shell">
    <div class="auth-login-visual">
      <div class="auth-visual-top"><span class="brand-mark">S</span><span>Сметограм</span></div>
      <div class="auth-visual-copy">
        <span class="auth-visual-kicker">УПРАВЛЕНИЕ СТРОЙКОЙ</span>
        <h2>Всё по объекту.<br><em>В одном месте.</em></h2>
        <p>Сметы, график, документы и приёмка — без лишних таблиц и переписок.</p>
      </div>
      <div class="auth-visual-points"><span>01&nbsp; Смета и расчёты</span><span>02&nbsp; График работ</span><span>03&nbsp; Документы и приёмка</span></div>
    </div>

    <div class="auth-login">
      <div class="auth-login-brand">
        <span class="brand-mark">S</span>
        <span>Сметограм</span>
      </div>

      <div class="auth-login-content">
        <div class="eyebrow">ВХОД В АККАУНТ</div>
        <h1>Войдите в Сметограм</h1>
        <p class="auth-login-lead">Сметы, график работ, договоры и приёмка — по каждому объекту. <strong>Первый проект — бесплатно.</strong></p>

        <div class="telegram-login-box">
        <?php if ($telegramReady): ?>
          <a href="telegram-start.php" class="telegram-login-button" id="telegramLoginButton">
            <i class="bi bi-telegram"></i>
            <span>Войти через Telegram</span>
          </a>
          <?php if (!empty($_SESSION['telegram_login_error'])): ?>
            <div class="auth-error"><?=htmlspecialchars((string)$_SESSION['telegram_login_error'])?></div>
            <?php unset($_SESSION['telegram_login_error']); ?>
          <?php endif; ?>
        <?php else: ?>
          <div class="auth-error">Telegram-вход пока не настроен. Добавьте TELEGRAM_CLIENT_ID в секреты GitHub Actions.</div>
        <?php endif; ?>
      </div>

      <div class="auth-login-security"><span class="auth-security-dot"></span><div><strong>Быстрый и безопасный вход</strong><small>Telegram подтверждает вашу личность без пароля и SMS-кода.</small></div></div>
      <p class="auth-phone-note">Продолжая, вы входите в Сметограм через Telegram. Мы не получаем ваш пароль Telegram.</p>
      <a href="guide.php" class="auth-guide-button"><i class="bi bi-book"></i><span>Как пользоваться Сметограмом</span><i class="bi bi-arrow-up-right ms-auto"></i></a>
    </div>

    <div class="auth-login-footer">
      <span>Первый проект — бесплатно</span>
      <a href="register.php">Создать аккаунт</a>
    </div>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php';?>