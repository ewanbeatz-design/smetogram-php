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
          <button type="button" class="telegram-login-button" id="telegramLoginButton">
            <i class="bi bi-telegram"></i>
            <span>Войти через Telegram</span>
          </button>
          <div class="auth-error" id="telegramLoginError" hidden></div>
          <script src="https://oauth.telegram.org/js/telegram-login.js?3"></script>
          <script>
            (function () {
              const button = document.getElementById('telegramLoginButton');
              const errorBox = document.getElementById('telegramLoginError');
              if (!button || !window.Telegram || !Telegram.Login) return;

              function showError(message) {
                errorBox.textContent = message || 'Не удалось выполнить вход через Telegram.';
                errorBox.hidden = false;
                button.disabled = false;
              }

              function onTelegramAuth(data) {
                if (!data || data.error || !data.id_token) {
                  showError(data && data.error ? data.error : 'Telegram не подтвердил авторизацию.');
                  return;
                }

                button.disabled = true;
                errorBox.hidden = true;

                fetch('telegram-auth.php', {
                  method: 'POST',
                  headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                  credentials: 'same-origin',
                  body: JSON.stringify({id_token: data.id_token})
                })
                .then(function (response) {
                  return response.json().catch(function () { return {}; }).then(function (payload) {
                    if (!response.ok) throw new Error(payload.error || 'Не удалось создать сессию.');
                    return payload;
                  });
                })
                .then(function (payload) {
                  window.location.href = payload.redirect || 'dashboard.php';
                })
                .catch(function (error) {
                  showError(error.message);
                });
              }

              window.addEventListener('load', function () {
                Telegram.Login.init({
                  client_id: <?=json_encode((int)$telegramClientId)?>,
                  request_access: ['write'],
                  lang: 'ru',
                  nonce: <?=json_encode($telegramNonce)?>
                }, onTelegramAuth);

                button.addEventListener('click', function () {
                  errorBox.hidden = true;
                  Telegram.Login.open(onTelegramAuth);
                });
              });
            })();
          </script>
        <?php else: ?>
          <div class="auth-error">Telegram-вход пока не настроен. Добавьте TELEGRAM_CLIENT_ID в секреты GitHub Actions.</div>
        <?php endif; ?>
      </div>

      <div class="auth-login-security"><span class="auth-security-dot"></span><div><strong>Быстрый и безопасный вход</strong><small>Telegram подтверждает вашу личность без пароля и SMS-кода.</small></div></div>
      <p class="auth-phone-note">Продолжая, вы входите в Сметограм через Telegram. Мы не получаем ваш пароль Telegram.</p>
    </div>

    <div class="auth-login-footer">
      <span>Первый проект — бесплатно</span>
      <a href="register.php">Создать аккаунт</a>
    </div>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php';?>