<?php
declare(strict_types=1);
$pageTitle = 'Сметограм';
require __DIR__ . '/config/bootstrap.php';
$user = current_user();
$projectCount = $user ? user_project_count($pdo, (int)$user['id']) : 0;
$subActive = $user ? subscription_is_active($user) : false;
require __DIR__ . '/includes/header.php';
?>
<style id="home-page-style">`+css+`</style><div class="home-page"><section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="hero-badge mb-4"><span class="hero-badge-dot"></span> Современный сервис для смет</div>
        <h1 class="hero-title mb-4">Сметы без<br><span>Excel-хаоса.</span></h1>
        <p class="hero-copy mb-4">Создавайте строительные сметы, управляйте проектами и рассчитывайте стоимость работ и материалов — в одном современном сервисе.</p>
        <div class="d-flex flex-wrap gap-3">
          <?php if($user): ?>
          <a href="dashboard.php" class="btn btn-primary btn-lg px-4"><?= $subActive ? 'Открыть проекты' : ($projectCount ? 'Продолжить смету' : 'Создать смету') ?></a>
          <?php if(!$subActive): ?><span class="hero-note align-self-center">1 смета бесплатно</span><?php endif; ?>
          <?php else: ?>
          <a href="login.php" class="btn btn-primary btn-lg px-4">Создать первую смету</a>
          <a href="login.php" class="btn btn-light btn-lg px-4">Войти</a>
          <?php endif; ?>
        </div>
        <div class="hero-note mt-4"><span>✓ Без установки</span><span>✓ В браузере</span><span>✓ Первая смета бесплатно</span></div>
      </div>
      <div class="col-lg-5">
        <div class="estimate-preview surface">
          <div class="preview-top"><div><div class="preview-label">ПРОЕКТ</div><div class="preview-title">Дом на Курортной</div></div><div class="preview-status">В работе</div></div>
          <div class="preview-tabs"><span class="active">Все</span><span>Материалы</span><span>Работы</span></div>
          <div class="preview-table">
            <div class="preview-row preview-head"><span>Позиция</span><span>Сумма</span></div>
            <div class="preview-row"><div><strong>Бетон М300</strong><small>18 м³ × 8 500 ₽</small></div><strong>153 000 ₽</strong></div>
            <div class="preview-row"><div><strong>Арматура</strong><small>2,4 т × 72 000 ₽</small></div><strong>172 800 ₽</strong></div>
            <div class="preview-row"><div><strong>Опалубка</strong><small>120 м² × 1 200 ₽</small></div><strong>144 000 ₽</strong></div>
          </div>
          <div class="preview-total"><span>Итого</span><strong>469 800 ₽</strong></div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="py-5"><div class="container"><div class="section-title-row mb-4"><div><div class="eyebrow">КАК ЭТО РАБОТАЕТ</div><h2 class="mt-2">Начните бесплатно. Платите только когда нужен следующий объект.</h2><p class="text-muted mb-0">Одна полноценная смета доступна каждому новому пользователю. Активная подписка снимает ограничение.</p></div></div><div class="row g-4">
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">01</div><h3>Проекты</h3><p>Все объекты и сметы находятся в одном месте. Ничего не теряется среди Excel-файлов.</p></div></div>
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">02</div><h3>Смета</h3><p>Добавляйте материалы, работы и технику. Количество, цена и итог рассчитываются автоматически.</p></div></div>
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">03</div><h3>Подписка</h3><p>Нужно больше одной сметы? Подключаете подписку — и продолжаете создавать новые проекты без ограничения бесплатного тарифа.</p></div></div>
</div></div></section>
<section class="pricing-section"><div class="container"><div class="text-center mb-5"><div class="eyebrow">ПОДПИСКА</div><h2 class="mt-2">Одна смета бесплатно. Дальше — без ограничений.</h2><p class="text-muted mx-auto" style="max-width:620px">Если работаете регулярно, подписка открывает создание новых проектов и снимает лимит бесплатного доступа.</p></div><div class="row g-4 justify-content-center"><div class="col-md-5"><div class="pricing-card"><div class="eyebrow">СТАРТ</div><h3 class="mt-3">Бесплатно</h3><div class="mt-3"><span class="price">0 ₽</span></div><ul class="pricing-list"><li>1 полноценная смета</li><li>Редактирование проекта</li><li>Расчёт стоимости</li><li>Работа в браузере</li></ul><a href="login.php" class="btn btn-light border w-100 mt-4">Начать бесплатно</a></div></div><div class="col-md-5"><div class="pricing-card featured"><div class="eyebrow">ДЛЯ РАБОТЫ</div><h3 class="mt-3">Подписка</h3><div class="mt-3"><span class="price">По тарифу</span></div><ul class="pricing-list"><li>Новые сметы без ограничения</li><li>Все возможности бесплатного доступа</li><li>Единое рабочее пространство</li><li>Доступ пока подписка активна</li></ul><a href="subscription.php" class="btn btn-primary w-100 mt-4">Выбрать тариф</a></div></div></div></div></section><section class="py-5"><div class="container"><div class="surface cta-block"><div><div class="eyebrow">СМЕТОГРАМ</div><h2>Первая смета — за несколько минут.</h2><p>Создайте проект и начните добавлять позиции.</p></div><a href="register.php" class="btn btn-primary btn-lg px-4">Начать работу</a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></div>