<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
if(current_user()) redirect('dashboard.php');

$telegram = $config['telegram'] ?? [];
$telegramBotUsername = trim((string)($telegram['bot_username'] ?? ''));
$telegramReady = $telegramBotUsername !== '' && trim((string)($telegram['bot_token'] ?? '')) !== '';

$pageTitle='Вход';
require __DIR__.'/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-login">
    <div class="auth-login-brand">
      <span class="brand-mark">S</span>
      <span>Сметограм</span>
    </div>

    <div class="auth-login-content">
      <div class="eyebrow">ВХОД</div>
      <h1>Войдите в Сметограм</h1>
      <p class="auth-login-lead">Сметы, график работ, договоры и приёмка — по каждому объекту. Войдите, чтобы начать. <strong>Первый проект — бесплатно.</strong></p>

      <div class="telegram-login-box">
        <?php if ($telegramReady): ?>
          <script async src="https://telegram.org/js/telegram-widget.js?22"
                  data-telegram-login="<?=e($telegramBotUsername)?>"
                  data-size="large"
                  data-radius="12"
                  data-auth-url="<?=e((string)($config['app']['url'] ?? 'https://сметограм.рф').'/telegram-auth.php')?>"
                  data-request-access="write"></script>
        <?php else: ?>
          <div class="auth-error">Telegram-вход пока не настроен. Добавьте TELEGRAM_BOT_TOKEN в секреты GitHub Actions.</div>
        <?php endif; ?>
      </div>

      <p class="auth-phone-note">Вход выполняется через Telegram. Пароль и SMS-коды не нужны — после подтверждения вы сразу попадёте в Сметограм.</p>
    </div>

    <div class="auth-login-footer">
      <span>Новый пользователь?</span>
      <a href="register.php">Зарегистрироваться</a>
    </div>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php';?>