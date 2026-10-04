<?php
declare(strict_types=1);
$pageTitle = 'Сметограм';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="hero-badge mb-4"><span class="hero-badge-dot"></span> Современный сервис для смет</div>
        <h1 class="hero-title mb-4">Сметы без<br><span>Excel-хаоса.</span></h1>
        <p class="hero-copy mb-4">Создавайте строительные сметы, управляйте проектами и рассчитывайте стоимость работ и материалов — в одном современном сервисе.</p>
        <div class="d-flex flex-wrap gap-3">
          <a href="register.php" class="btn btn-primary btn-lg px-4">Создать смету</a>
          <a href="login.php" class="btn btn-light btn-lg px-4">Войти</a>
        </div>
        <div class="hero-note mt-4"><span>✓ Без установки</span><span>✓ В браузере</span><span>✓ Данные сохраняются</span></div>
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
<section class="py-5"><div class="container"><div class="row g-4">
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">01</div><h3>Проекты</h3><p>Все объекты и сметы находятся в одном месте. Ничего не теряется среди Excel-файлов.</p></div></div>
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">02</div><h3>Смета</h3><p>Добавляйте материалы, работы и технику. Количество, цена и итог рассчитываются автоматически.</p></div></div>
  <div class="col-md-4"><div class="surface feature-card"><div class="feature-icon">03</div><h3>Каталог</h3><p>ФЕР, ТЕР, ГЭСН и собственные позиции — всё можно использовать при составлении сметы.</p></div></div>
</div></div></section>
<section class="py-5"><div class="container"><div class="surface cta-block"><div><div class="eyebrow">СМЕТОГРАМ</div><h2>Первая смета — за несколько минут.</h2><p>Создайте проект и начните добавлять позиции.</p></div><a href="register.php" class="btn btn-primary btn-lg px-4">Начать работу</a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>