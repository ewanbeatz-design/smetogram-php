<?php
declare(strict_types=1);

$pageTitle = 'Как пользоваться Сметограмом';
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/includes/header.php';
?>

<style>
.smeta-guide{--ink:#171923;--muted:#707786;--line:#e5e7ed;--soft:#f6f7fa;--accent:#635bff;color:var(--ink)}
.smeta-guide .guide-hero{padding:90px 0 75px;background:linear-gradient(135deg,#f8f8ff,#fff)}
.smeta-guide .eyebrow{font:700 10px "IBM Plex Mono",monospace;letter-spacing:.13em;text-transform:uppercase;color:#655ed5}
.smeta-guide h1{font-size:clamp(45px,7vw,82px);line-height:.9;letter-spacing:-.07em;font-weight:700;margin:18px 0 25px}
.smeta-guide .lead{max-width:680px;color:var(--muted);font-size:18px;line-height:1.7}
.smeta-guide .guide-nav{position:sticky;top:20px;border:1px solid var(--line);border-radius:18px;background:#fff;padding:18px}
.smeta-guide .guide-nav a{display:block;padding:10px 11px;border-radius:9px;color:#697080;text-decoration:none;font-size:12px}
.smeta-guide .guide-nav a:hover{background:#f0efff;color:var(--accent)}
.smeta-guide .guide-section{padding:85px 0;border-top:1px solid var(--line)}
.smeta-guide .step-card{border:1px solid var(--line);border-radius:22px;background:#fff;padding:30px;height:100%;box-shadow:0 10px 35px rgba(20,24,40,.04)}
.smeta-guide .num{width:40px;height:40px;display:grid;place-items:center;border-radius:11px;background:#eeedff;color:var(--accent);font:700 11px "IBM Plex Mono",monospace;margin-bottom:28px}
.smeta-guide h2{font-size:clamp(32px,4vw,52px);line-height:1;letter-spacing:-.055em;margin:0 0 15px}
.smeta-guide h3{font-size:22px;letter-spacing:-.04em;margin:0 0 10px}
.smeta-guide p{color:var(--muted);font-size:14px;line-height:1.7}
.smeta-guide .ui-demo{border:1px solid #dfe2e9;border-radius:18px;background:#f7f8fa;padding:15px;box-shadow:0 20px 50px rgba(20,24,40,.08)}
.smeta-guide .ui-demo-head{background:#fff;border:1px solid #e5e7ec;border-radius:10px;padding:14px;font-weight:700;font-size:13px;margin-bottom:10px}
.smeta-guide .ui-line{display:grid;grid-template-columns:1fr 80px 100px;gap:10px;padding:12px;background:#fff;border-top:1px solid #edf0f3;font-size:11px;color:#707786}
.smeta-guide .ui-line:first-of-type{border-radius:10px 10px 0 0}
.smeta-guide .ui-total{display:flex;justify-content:flex-end;gap:20px;padding:16px;background:#171923;color:#fff;border-radius:0 0 10px 10px;font-weight:700}
.smeta-guide .tip{border-left:3px solid var(--accent);padding:15px 18px;background:#f5f4ff;border-radius:0 10px 10px 0;color:#5d5a79;font-size:13px;line-height:1.6}
.smeta-guide .faq{border-bottom:1px solid var(--line);padding:22px 0}
.smeta-guide .faq strong{display:block;margin-bottom:7px}
.smeta-guide .cta{padding:70px 35px;border-radius:24px;background:#171923;color:#fff}
.smeta-guide .cta p{color:#a0a5b1}
@media(max-width:991px){.smeta-guide .guide-nav{position:static;margin-bottom:35px}}
@media(max-width:575px){.smeta-guide .guide-hero{padding:60px 0}.smeta-guide .guide-section{padding:60px 0}.smeta-guide .ui-line{grid-template-columns:1fr 55px 70px;font-size:9px}}
</style>

<div class="smeta-guide">
  <section class="guide-hero">
    <div class="container">
      <div class="eyebrow">СМЕТОГРАМ / ИНСТРУКЦИЯ</div>
      <h1>Как работать<br>в Сметограме.</h1>
      <p class="lead">Короткая инструкция от первого входа до готовой сметы. Здесь собраны основные действия, которые понадобятся для работы с проектами, позициями и расчётами.</p>
    </div>
  </section>

  <section class="guide-section pt-5">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-3">
          <nav class="guide-nav">
            <a href="#start">01 · Начало</a>
            <a href="#project">02 · Проект</a>
            <a href="#estimate">03 · Смета</a>
            <a href="#calculate">04 · Расчёт</a>
            <a href="#access">05 · Доступ</a>
            <a href="#faq">06 · Вопросы</a>
          </nav>
        </div>

        <div class="col-lg-9">
          <div id="start" class="mb-5">
            <div class="eyebrow mb-3">01 / НАЧАЛО</div>
            <h2>Начните с регистрации.</h2>
            <p>После регистрации вы попадаете в рабочее пространство Сметограма. Новый пользователь получает возможность создать одну полноценную смету бесплатно.</p>
          </div>

          <div class="row g-3">
            <div class="col-md-4"><div class="step-card"><div class="num">01</div><h3>Зарегистрируйтесь</h3><p>Создайте аккаунт или войдите удобным способом.</p></div></div>
            <div class="col-md-4"><div class="step-card"><div class="num">02</div><h3>Создайте проект</h3><p>Нажмите создание нового проекта и задайте название объекта.</p></div></div>
            <div class="col-md-4"><div class="step-card"><div class="num">03</div><h3>Откройте смету</h3><p>Внутри проекта добавляйте необходимые материалы и работы.</p></div></div>
          </div>

          <div id="project" class="guide-section">
            <div class="eyebrow mb-3">02 / ПРОЕКТ</div>
            <h2>Один проект — один объект.</h2>
            <p>Используйте проект как контейнер для конкретной стройки, ремонта или другого объекта. Название должно помогать быстро найти его среди остальных.</p>
            <div class="tip mt-4"><strong>Совет:</strong> называйте проекты понятно: «Дом на Курортной», «Ремонт квартиры · Сочи», «Офис · 3 этаж».</div>
          </div>

          <div id="estimate" class="guide-section">
            <div class="eyebrow mb-3">03 / СМЕТА</div>
            <h2>Добавляйте позиции.</h2>
            <p>Каждая строка сметы — отдельная позиция. Укажите наименование, количество и стоимость. Используйте единицы измерения, которые соответствуют конкретному материалу или работе.</p>
            <div class="ui-demo mt-4">
              <div class="ui-demo-head">Пример сметы · Дом на Курортной</div>
              <div class="ui-line"><b>Бетон М300</b><span>18 м³</span><strong>153 000 ₽</strong></div>
              <div class="ui-line"><b>Арматура</b><span>2,4 т</span><strong>172 800 ₽</strong></div>
              <div class="ui-line"><b>Опалубка</b><span>120 м²</span><strong>144 000 ₽</strong></div>
              <div class="ui-total"><span>ИТОГО</span><span>469 800 ₽</span></div>
            </div>
          </div>

          <div id="calculate" class="guide-section">
            <div class="eyebrow mb-3">04 / РАСЧЁТ</div>
            <h2>Меняйте данные — итог обновится.</h2>
            <p>Количество и цена используются для расчёта стоимости позиции. При изменении исходных данных актуальная сумма проекта пересчитывается.</p>
            <div class="row g-3 mt-3">
              <div class="col-md-6"><div class="step-card"><h3>Количество</h3><p>Например: 18 м³ бетона × стоимость единицы.</p></div></div>
              <div class="col-md-6"><div class="step-card"><h3>Стоимость</h3><p>Указывайте актуальную цену за выбранную единицу измерения.</p></div></div>
            </div>
          </div>

          <div id="access" class="guide-section">
            <div class="eyebrow mb-3">05 / ДОСТУП</div>
            <h2>Первая смета бесплатно.</h2>
            <p>Новый пользователь может создать одну смету бесплатно и продолжать её редактировать. Для создания следующих проектов потребуется активная подписка.</p>
            <p>Если подписка активна, ограничение на создание новых проектов снимается.</p>
          </div>

          <div id="faq" class="guide-section">
            <div class="eyebrow mb-3">06 / FAQ</div>
            <h2>Частые вопросы.</h2>
            <div class="faq"><strong>Можно ли редактировать бесплатную смету?</strong><p class="mb-0">Да. Бесплатная смета остаётся доступной для редактирования.</p></div>
            <div class="faq"><strong>Что происходит после создания первой сметы?</strong><p class="mb-0">Создание нового проекта становится доступно после подключения подписки.</p></div>
            <div class="faq"><strong>Нужно ли устанавливать программу?</strong><p class="mb-0">Нет. Сметограм работает в браузере.</p></div>
            <div class="faq"><strong>Где посмотреть проекты?</strong><p class="mb-0">После входа откройте рабочее пространство — там находятся ваши проекты.</p></div>
          </div>

          <div class="cta mt-5">
            <div class="eyebrow">ГОТОВО</div>
            <h2 class="mt-3">Теперь можно создавать первую смету.</h2>
            <p>Перейдите в рабочее пространство и начните с нового проекта.</p>
            <a href="<?= current_user() ? 'dashboard.php' : 'login.php' ?>" class="btn btn-light btn-lg mt-2"><?= current_user() ? 'Открыть Сметограм' : 'Войти и начать' ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>