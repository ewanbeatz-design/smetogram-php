<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
if(current_user()) redirect('dashboard.php');
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

      <form class="auth-phone-form" id="phoneLoginForm" action="#" method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <label for="phone">Ваш номер телефона</label>
        <div class="phone-input-wrap">
          <span class="phone-prefix">+7</span>
          <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(999) 123-45-67" maxlength="15" required>
        </div>
        <button class="auth-sms-btn" type="submit">Получить код в SMS <i class="bi bi-arrow-right"></i></button>
        <p class="auth-phone-note">Пришлём SMS с четырьмя цифрами — введёте их на следующем шаге. Баланс и история привязаны к вашему номеру.</p>
      </form>

      <p class="auth-legal">Продолжая, вы соглашаетесь с <a href="#">условиями оферты</a> и <a href="#">политикой конфиденциальности</a>.</p>
    </div>

    <div class="auth-login-footer">
      <span>Уже есть аккаунт?</span>
      <a href="register.php">Зарегистрироваться</a>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const input=document.getElementById('phone');
  if(!input)return;
  input.addEventListener('input',function(){
    let v=this.value.replace(/\D/g,'').replace(/^7/,'').slice(0,10);
    let out='';
    if(v.length) out='('+v.slice(0,3);
    if(v.length>=3) out+=') '+v.slice(3,6);
    if(v.length>=6) out+='-'+v.slice(6,8);
    if(v.length>=8) out+='-'+v.slice(8,10);
    this.value=out;
  });
});
</script>
<?php require __DIR__.'/includes/footer.php';?>