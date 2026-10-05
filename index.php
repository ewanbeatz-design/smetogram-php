<?php
declare(strict_types=1);

$pageTitle = 'Сметограм';
require __DIR__ . '/config/bootstrap.php';

$user = current_user();
$projectCount = $user ? user_project_count($pdo, (int)$user['id']) : 0;
$subActive = $user ? subscription_is_active($user) : false;

require __DIR__ . '/includes/header.php';
?>

<style>
/* INDEX ONLY — styles are intentionally scoped to .sm-home */
.sm-home{--ink:#111827;--muted:#64748b;--line:#e7eaf0;--soft:#f6f7fb;--accent:#635bff;--accent-dark:#5148e8;--accent-soft:#eeedff;background:#fff;color:var(--ink);overflow:hidden}
.sm-home *{box-sizing:border-box}
.sm-home .home-hero{position:relative;padding:72px 0 82px;background:radial-gradient(circle at 78% 18%,rgba(99,91,255,.11),transparent 28%),linear-gradient(180deg,#fff 0%,#fafbff 100%)}
.sm-home .home-hero:after{content:"";position:absolute;width:520px;height:520px;border:1px solid rgba(99,91,255,.08);border-radius:50%;right:-250px;top:-210px;pointer-events:none}
.sm-home .eyebrow{display:inline-flex;align-items:center;gap:9px;font:700 11px/1.2 "IBM Plex Mono",monospace;letter-spacing:.12em;color:#6861d9;text-transform:uppercase}
.sm-home .eyebrow:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--accent)}
.sm-home .hero-title{max-width:790px;margin:20px 0 24px;font-size:clamp(46px,6.1vw,86px);font-weight:650;line-height:.94;letter-spacing:-.065em}
.sm-home .hero-title em{font-style:normal;color:var(--accent)}
.sm-home .hero-text{max-width:620px;margin:0;color:var(--muted);font-size:18px;line-height:1.65}
.sm-home .hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:32px}
.sm-home .hero-actions .btn{border-radius:12px;font-weight:600;padding:13px 20px}
.sm-home .hero-proof{display:flex;flex-wrap:wrap;gap:20px;margin-top:25px;color:#7b8495;font-size:12px}
.sm-home .hero-proof span{display:inline-flex;align-items:center;gap:7px}
.sm-home .hero-proof i{font-style:normal;color:#22a06b;font-weight:800}
.sm-home .hero-visual{position:relative}
.sm-home .browser{position:relative;border:1px solid #dfe3eb;border-radius:22px;background:#fff;box-shadow:0 35px 90px rgba(31,35,52,.13),0 5px 20px rgba(31,35,52,.06);overflow:hidden;transform:perspective(1200px) rotateY(-4deg) rotateX(2deg)}
.sm-home .browser-bar{height:44px;display:flex;align-items:center;gap:7px;padding:0 15px;border-bottom:1px solid #edf0f4;background:#fbfcfe}
.sm-home .browser-dot{width:7px;height:7px;border-radius:50%;background:#d8dce4}
.sm-home .browser-url{height:21px;flex:1;max-width:190px;margin-left:8px;border-radius:6px;background:#f0f2f6}
.sm-home .app-shot{display:grid;grid-template-columns:92px 1fr;min-height:385px}
.sm-home .shot-side{padding:20px 12px;background:#171a24;color:#fff}
.sm-home .shot-logo{font-size:14px;font-weight:750;letter-spacing:-.04em;margin-bottom:28px}
.sm-home .shot-logo span{color:#8b84ff}
.sm-home .shot-nav{display:grid;gap:7px}
.sm-home .shot-nav div{padding:8px 9px;border-radius:7px;color:#a9afbd;font-size:9px}
.sm-home .shot-nav div.active{background:#2a2e3b;color:#fff}
.sm-home .shot-main{padding:20px;background:#f7f8fb}
.sm-home .shot-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.sm-home .shot-heading{font-size:16px;font-weight:700;letter-spacing:-.03em}
.sm-home .shot-chip{padding:5px 8px;border-radius:6px;background:#e9e8ff;color:#5c55d8;font-size:8px;font-weight:700}
.sm-home .shot-card{background:#fff;border:1px solid #e6e9ef;border-radius:10px;padding:12px;margin-bottom:10px}
.sm-home .shot-card-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:11px;font-size:9px;font-weight:700}
.sm-home .shot-line{display:grid;grid-template-columns:1fr 55px 65px;gap:8px;padding:7px 0;border-top:1px solid #f0f1f4;font-size:8px;color:#6d7482}
.sm-home .shot-line b{color:#303642}
.sm-home .shot-total{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:12px;font-size:9px;color:#7b8290}
.sm-home .shot-total strong{font-size:16px;color:#171a24}
.sm-home .float-stat{position:absolute;right:-25px;bottom:25px;width:150px;padding:15px;border:1px solid #e1e3ea;border-radius:14px;background:rgba(255,255,255,.94);box-shadow:0 18px 40px rgba(20,24,38,.12);backdrop-filter:blur(12px)}
.sm-home .float-stat small{display:block;color:#8a91a0;font-size:9px;margin-bottom:5px}
.sm-home .float-stat strong{font-size:19px;letter-spacing:-.04em}
.sm-home .float-stat span{display:block;margin-top:5px;color:#22a06b;font-size:9px;font-weight:700}
.sm-home .section{padding:92px 0}
.sm-home .section-soft{background:#f8f9fc;border-top:1px solid #f0f1f5;border-bottom:1px solid #f0f1f5}
.sm-home .section-heading{max-width:700px}
.sm-home .section-heading h2{margin:12px 0 13px;font-size:clamp(31px,4vw,50px);line-height:1.02;letter-spacing:-.055em;font-weight:650}
.sm-home .section-heading p{margin:0;color:var(--muted);line-height:1.7;font-size:15px}
.sm-home .stats{display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.sm-home .stat{padding:28px 25px}
.sm-home .stat+.stat{border-left:1px solid var(--line)}
.sm-home .stat strong{display:block;font-size:29px;letter-spacing:-.045em}
.sm-home .stat span{display:block;margin-top:6px;color:#7b8495;font-size:12px}
.sm-home .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:45px}
.sm-home .step{min-height:220px;padding:27px;border:1px solid var(--line);border-radius:18px;background:#fff;transition:.2s ease}
.sm-home .step:hover{transform:translateY(-3px);box-shadow:0 15px 35px rgba(20,25,45,.06)}
.sm-home .step-num{font:700 11px "IBM Plex Mono",monospace;color:#7770df}
.sm-home .step h3{margin:46px 0 10px;font-size:20px;letter-spacing:-.035em}
.sm-home .step p{margin:0;color:var(--muted);font-size:13px;line-height:1.65}
.sm-home .workspace{display:grid;grid-template-columns:.8fr 1.2fr;gap:60px;align-items:center}
.sm-home .workspace-list{display:grid;gap:9px;margin-top:30px}
.sm-home .workspace-item{display:flex;gap:13px;align-items:flex-start;padding:14px 0;border-bottom:1px solid var(--line)}
.sm-home .workspace-item b{font-size:13px}
.sm-home .workspace-item p{margin:3px 0 0;color:var(--muted);font-size:11px;line-height:1.5}
.sm-home .workspace-check{width:22px;height:22px;flex:0 0 22px;display:grid;place-items:center;border-radius:7px;background:var(--accent-soft);color:var(--accent);font-size:11px;font-weight:800}
.sm-home .dashboard-card{border:1px solid #dfe3eb;border-radius:20px;background:#fff;padding:18px;box-shadow:0 25px 65px rgba(24,28,45,.09)}
.sm-home .dash-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.sm-home .dash-title{font-size:15px;font-weight:700}
.sm-home .dash-button{padding:7px 10px;border-radius:7px;background:#f0efff;color:#5b54d8;font-size:9px;font-weight:700}
.sm-home .project{display:grid;grid-template-columns:42px 1fr auto;align-items:center;gap:12px;padding:13px 5px;border-top:1px solid #eef0f4}
.sm-home .project-icon{width:42px;height:42px;border-radius:10px;background:#f4f5f8;display:grid;place-items:center;font:700 10px "IBM Plex Mono",monospace;color:#7d8492}
.sm-home .project b{font-size:11px}
.sm-home .project small{display:block;margin-top:3px;color:#8b92a0;font-size:8px}
.sm-home .project strong{font-size:10px}
.sm-home .pricing{padding:92px 0}
.sm-home .pricing-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;max-width:900px;margin:45px auto 0}
.sm-home .plan{position:relative;padding:32px;border:1px solid var(--line);border-radius:20px;background:#fff}
.sm-home .plan.pro{border-color:#8b85ff;box-shadow:0 22px 60px rgba(99,91,255,.12)}
.sm-home .plan-badge{position:absolute;right:18px;top:18px;padding:5px 8px;border-radius:6px;background:var(--accent-soft);color:#5952d2;font:700 8px "IBM Plex Mono",monospace}
.sm-home .plan-label{font:700 10px "IBM Plex Mono",monospace;letter-spacing:.12em;color:#8b92a0}
.sm-home .plan h3{margin:12px 0 7px;font-size:25px;letter-spacing:-.045em}
.sm-home .plan-price{font-size:35px;font-weight:650;letter-spacing:-.055em}
.sm-home .plan-price small{font-size:11px;color:#8b92a0;font-weight:500;letter-spacing:0}
.sm-home .plan ul{list-style:none;padding:0;margin:25px 0;display:grid;gap:11px}
.sm-home .plan li{font-size:12px;color:#697385}
.sm-home .plan li:before{content:"✓";display:inline-block;margin-right:9px;color:#5d57d8;font-weight:800}
.sm-home .plan .btn{border-radius:11px;padding:12px}
.sm-home .final-cta{padding:85px 0 100px}
.sm-home .cta-box{position:relative;overflow:hidden;padding:55px;border-radius:24px;background:#171a24;color:#fff}
.sm-home .cta-box:after{content:"";position:absolute;width:310px;height:310px;border:1px solid rgba(139,132,255,.22);border-radius:50%;right:-80px;top:-150px}
.sm-home .cta-box h2{position:relative;z-index:1;max-width:650px;margin:12px 0;font-size:clamp(30px,4vw,48px);line-height:1.02;letter-spacing:-.055em}
.sm-home .cta-box p{position:relative;z-index:1;margin:0;color:#aeb4c2;font-size:14px}
.sm-home .cta-box .btn{position:relative;z-index:2;margin-top:25px;border-radius:11px}
.sm-home .cta-box .eyebrow{color:#aaa5ff}
@media(max-width:991px){
  .sm-home .home-hero{padding:58px 0 65px}
  .sm-home .hero-title{font-size:clamp(45px,9vw,72px)}
  .sm-home .hero-visual{max-width:680px;margin:10px auto 0}
  .sm-home .workspace{grid-template-columns:1fr;gap:40px}
}
@media(max-width:767px){
  .sm-home .section,.sm-home .pricing{padding:65px 0}
  .sm-home .hero-text{font-size:16px}
  .sm-home .stats,.sm-home .steps,.sm-home .pricing-grid{grid-template-columns:1fr}
  .sm-home .stat+.stat{border-left:0;border-top:1px solid var(--line)}
  .sm-home .step{min-height:0}
  .sm-home .step h3{margin-top:30px}
  .sm-home .app-shot{grid-template-columns:70px 1fr}
  .sm-home .shot-main{padding:12px}
  .sm-home .shot-side{padding:15px 8px}
  .sm-home .float-stat{right:8px;bottom:10px}
  .sm-home .cta-box{padding:35px 25px}
}
</style>

<div class="sm-home">
  <section class="home-hero">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-7">
          <div class="eyebrow">Сервис для строительных смет</div>
          <h1 class="hero-title">Сметы, которые<br><em>просто работают.</em></h1>
          <p class="hero-text">Создавайте точные сметы, управляйте объектами и считайте стоимость работ и материалов в одном рабочем пространстве. Без таблиц, хаоса и лишних действий.</p>

          <div class="hero-actions">
            <?php if($user): ?>
              <a href="dashboard.php" class="btn btn-primary btn-lg">
                <?= $subActive ? 'Открыть рабочее пространство' : ($projectCount ? 'Продолжить смету' : 'Создать первую смету') ?>
              </a>
            <?php else: ?>
              <a href="login.php" class="btn btn-primary btn-lg">Создать первую смету</a>
              <a href="login.php" class="btn btn-light border btn-lg">Войти</a>
            <?php endif; ?>
          </div>

          <div class="hero-proof">
            <span><i>✓</i> Первая смета бесплатно</span>
            <span><i>✓</i> Работает в браузере</span>
            <span><i>✓</i> Без установки</span>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="hero-visual">
            <div class="browser">
              <div class="browser-bar">
                <span class="browser-dot"></span><span class="browser-dot"></span><span class="browser-dot"></span>
                <span class="browser-url"></span>
              </div>
              <div class="app-shot">
                <aside class="shot-side">
                  <div class="shot-logo">СМЕТО<span>ГРАМ</span></div>
                  <div class="shot-nav">
                    <div class="active">Проекты</div>
                    <div>Сметы</div>
                    <div>Материалы</div>
                    <div>Настройки</div>
                  </div>
                </aside>
                <div class="shot-main">
                  <div class="shot-top">
                    <div class="shot-heading">Дом на Курортной</div>
                    <div class="shot-chip">В РАБОТЕ</div>
                  </div>
                  <div class="shot-card">
                    <div class="shot-card-head"><span>Смета строительства</span><span>Кол-во</span></div>
                    <div class="shot-line"><b>Бетон М300</b><span>18 м³</span><b>153 000 ₽</b></div>
                    <div class="shot-line"><b>Арматура</b><span>2,4 т</span><b>172 800 ₽</b></div>
                    <div class="shot-line"><b>Опалубка</b><span>120 м²</span><b>144 000 ₽</b></div>
                    <div class="shot-line"><b>Работа</b><span>1 компл.</span><b>86 000 ₽</b></div>
                    <div class="shot-total"><span>Итого</span><strong>555 800 ₽</strong></div>
                  </div>
                  <div class="shot-card">
                    <div class="shot-card-head"><span>Материалы</span><span>42 позиции</span></div>
                    <div style="height:7px;border-radius:4px;background:#eeeef4;overflow:hidden"><div style="width:72%;height:100%;background:#756eff;border-radius:4px"></div></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="float-stat"><small>СТОИМОСТЬ ПРОЕКТА</small><strong>2 840 500 ₽</strong><span>↑ всё рассчитано автоматически</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-soft">
    <div class="container">
      <div class="stats">
        <div class="stat"><strong>1 смета</strong><span>полностью бесплатно для нового пользователя</span></div>
        <div class="stat"><strong>∞ проектов</strong><span>с активной подпиской</span></div>
        <div class="stat"><strong>24/7</strong><span>доступ к рабочему пространству в браузере</span></div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-heading">
        <div class="eyebrow">Простой рабочий процесс</div>
        <h2>От идеи до готовой сметы — без лишних экранов.</h2>
        <p>Сметограм собран вокруг реальной работы со сметами: создайте проект, добавьте позиции и сразу видите итог.</p>
      </div>

      <div class="steps">
        <div class="step">
          <div class="step-num">01 / ПРОЕКТ</div>
          <h3>Создайте объект</h3>
          <p>Название проекта и базовая информация — всё, что нужно для старта.</p>
        </div>
        <div class="step">
          <div class="step-num">02 / СМЕТА</div>
          <h3>Добавляйте позиции</h3>
          <p>Материалы и работы собираются в понятную структуру с количеством и стоимостью.</p>
        </div>
        <div class="step">
          <div class="step-num">03 / РЕЗУЛЬТАТ</div>
          <h3>Получайте итог</h3>
          <p>Стоимость пересчитывается автоматически, поэтому актуальная сумма всегда перед глазами.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-soft">
    <div class="container">
      <div class="workspace">
        <div>
          <div class="section-heading">
            <div class="eyebrow">Рабочее пространство</div>
            <h2>Всё необходимое — в одном месте.</h2>
            <p>Никаких десятков файлов и потерянных версий. Проекты остаются в вашем аккаунте и доступны в любой момент.</p>
          </div>

          <div class="workspace-list">
            <div class="workspace-item">
              <div class="workspace-check">✓</div>
              <div><b>Проекты и сметы</b><p>Все объекты собраны в едином списке.</p></div>
            </div>
            <div class="workspace-item">
              <div class="workspace-check">✓</div>
              <div><b>Автоматический расчёт</b><p>Количество и цена сразу влияют на итог.</p></div>
            </div>
            <div class="workspace-item">
              <div class="workspace-check">✓</div>
              <div><b>Доступ из браузера</b><p>Работайте без установки дополнительной программы.</p></div>
            </div>
          </div>
        </div>

        <div class="dashboard-card">
          <div class="dash-head"><div class="dash-title">Мои проекты</div><div class="dash-button">+ Новый проект</div></div>
          <div class="project"><div class="project-icon">01</div><div><b>Дом на Курортной</b><small>Последнее изменение сегодня</small></div><strong>2 840 500 ₽</strong></div>
          <div class="project"><div class="project-icon">02</div><div><b>Квартира · Сочи</b><small>Последнее изменение вчера</small></div><strong>1 275 000 ₽</strong></div>
          <div class="project"><div class="project-icon">03</div><div><b>Ремонт офиса</b><small>Последнее изменение 02.10</small></div><strong>864 300 ₽</strong></div>
        </div>
      </div>
    </div>
  </section>

  <section class="pricing">
    <div class="container">
      <div class="section-heading mx-auto text-center">
        <div class="eyebrow">Доступ</div>
        <h2>Начните бесплатно.<br>Расширяйтесь, когда понадобится.</h2>
        <p>Каждому новому пользователю доступна одна полноценная смета. Подписка открывает создание новых проектов.</p>
      </div>

      <div class="pricing-grid">
        <div class="plan">
          <div class="plan-label">START</div>
          <h3>Бесплатно</h3>
          <div class="plan-price">0 ₽ <small>навсегда</small></div>
          <ul>
            <li>1 полноценная смета</li>
            <li>Редактирование проекта</li>
            <li>Автоматический расчёт</li>
            <li>Работа в браузере</li>
          </ul>
          <a href="<?= $user ? 'dashboard.php' : 'login.php' ?>" class="btn btn-light border w-100"><?= $projectCount ? 'Открыть смету' : 'Начать бесплатно' ?></a>
        </div>

        <div class="plan pro">
          <div class="plan-badge">PRO</div>
          <div class="plan-label">ДЛЯ РАБОТЫ</div>
          <h3>Подписка</h3>
          <div class="plan-price">Без лимита</div>
          <ul>
            <li>Новые сметы без ограничения</li>
            <li>Все возможности бесплатного доступа</li>
            <li>Единое рабочее пространство</li>
            <li>Доступ на весь срок подписки</li>
          </ul>
          <a href="subscription.php" class="btn btn-primary w-100">Выбрать тариф</a>
        </div>
      </div>
    </div>
  </section>

  <section class="final-cta">
    <div class="container">
      <div class="cta-box">
        <div class="eyebrow">СМЕТОГРАМ</div>
        <h2>Сделайте первую смету прямо сейчас.</h2>
        <p>Одна полноценная смета доступна бесплатно. Регистрация занимает меньше минуты.</p>
        <?php if($user): ?>
          <a href="dashboard.php" class="btn btn-light btn-lg">Открыть рабочее пространство</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-light btn-lg">Создать первую смету</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>