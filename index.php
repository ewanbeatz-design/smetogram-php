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
/* INDEX ONLY */
.smeta-home{--purple:#635bff;--purple2:#8179ff;--dark:#11131a;--text:#181b24;--muted:#747b8b;--line:#e7e9ef;--bg:#f6f7fa;background:#fff;color:var(--text);overflow:hidden}
.smeta-home .hero{min-height:calc(100vh - 70px);display:flex;align-items:center;position:relative;background:#f7f8fb}
.smeta-home .hero:before{content:"";position:absolute;width:70vw;height:70vw;max-width:900px;max-height:900px;border-radius:50%;right:-25%;top:-45%;background:radial-gradient(circle,rgba(99,91,255,.15),rgba(99,91,255,0) 68%)}
.smeta-home .hero:after{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(20,24,35,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(20,24,35,.035) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.8),transparent 90%);pointer-events:none}
.smeta-home .hero-inner{position:relative;z-index:2}
.smeta-home .kicker{display:inline-flex;align-items:center;gap:9px;font:700 10px/1 "IBM Plex Mono",monospace;letter-spacing:.13em;text-transform:uppercase;color:#655ed5}
.smeta-home .kicker i{width:7px;height:7px;border-radius:50%;background:var(--purple);box-shadow:0 0 0 5px rgba(99,91,255,.1)}
.smeta-home h1{font-size:clamp(54px,8vw,116px);line-height:.84;letter-spacing:-.075em;font-weight:700;margin:24px 0 30px;max-width:850px}
.smeta-home h1 span{color:var(--purple)}
.smeta-home .hero-copy{font-size:18px;line-height:1.65;color:var(--muted);max-width:600px}
.smeta-home .hero-buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:34px}
.smeta-home .hero-buttons .btn{border-radius:12px;padding:13px 20px;font-weight:600}
.smeta-home .micro{display:flex;flex-wrap:wrap;gap:22px;margin-top:22px;color:#858b99;font-size:11px}
.smeta-home .micro b{color:#2caa72;margin-right:5px}
.smeta-home .hero-ui{position:relative;margin-left:auto;max-width:650px;transform:rotate(-2deg);filter:drop-shadow(0 35px 45px rgba(23,27,40,.16))}
.smeta-home .ui-window{border:1px solid #d9dce4;border-radius:22px;background:#fff;overflow:hidden}
.smeta-home .ui-top{height:42px;border-bottom:1px solid #eceef2;background:#fafbfc;display:flex;align-items:center;padding:0 15px;gap:6px}
.smeta-home .ui-dot{width:7px;height:7px;border-radius:50%;background:#d4d7de}
.smeta-home .ui-address{height:18px;width:150px;border-radius:5px;background:#f0f1f4;margin-left:9px}
.smeta-home .ui-body{display:grid;grid-template-columns:150px 1fr;min-height:440px}
.smeta-home .ui-sidebar{background:#171922;color:#fff;padding:22px 15px}
.smeta-home .ui-logo{font-size:15px;font-weight:800;letter-spacing:-.06em;margin-bottom:32px}
.smeta-home .ui-logo span{color:#8982ff}
.smeta-home .ui-nav{display:grid;gap:5px}
.smeta-home .ui-nav div{font-size:9px;color:#989eab;padding:10px;border-radius:7px}
.smeta-home .ui-nav .sel{color:#fff;background:#292c38}
.smeta-home .ui-main{padding:23px;background:#f6f7fa}
.smeta-home .ui-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.smeta-home .ui-head strong{font-size:17px;letter-spacing:-.04em}
.smeta-home .ui-tag{font-size:8px;font-weight:800;padding:6px 8px;background:#e8e7ff;color:#5c55d7;border-radius:6px}
.smeta-home .ui-sheet{background:#fff;border:1px solid #e5e7ec;border-radius:11px;padding:13px}
.smeta-home .ui-sheet-title{display:flex;justify-content:space-between;font-size:9px;font-weight:700;padding-bottom:11px}
.smeta-home .ui-row{display:grid;grid-template-columns:1fr 55px 70px;gap:7px;padding:9px 0;border-top:1px solid #eff0f3;font-size:8px;color:#7c8390}
.smeta-home .ui-row b{color:#252a34}
.smeta-home .ui-total{display:flex;justify-content:flex-end;gap:15px;align-items:center;margin-top:15px;font-size:9px;color:#858b98}
.smeta-home .ui-total strong{font-size:19px;color:#171922}
.smeta-home .ui-chart{margin-top:11px;background:#fff;border:1px solid #e5e7ec;border-radius:11px;padding:13px}
.smeta-home .bars{height:55px;display:flex;align-items:end;gap:7px}
.smeta-home .bars i{display:block;flex:1;background:#756eff;border-radius:4px 4px 1px 1px}
.smeta-home .hero-card{position:absolute;right:-35px;bottom:25px;width:180px;background:rgba(255,255,255,.94);border:1px solid #e2e4ea;border-radius:15px;padding:16px;backdrop-filter:blur(14px);box-shadow:0 20px 50px rgba(15,18,30,.15)}
.smeta-home .hero-card small{display:block;color:#9298a4;font:700 8px "IBM Plex Mono",monospace}
.smeta-home .hero-card strong{display:block;font-size:21px;letter-spacing:-.05em;margin:7px 0}
.smeta-home .hero-card span{font-size:9px;color:#24a36c}
.smeta-home .marquee{border-top:1px solid #e7e9ef;border-bottom:1px solid #e7e9ef;overflow:hidden;background:#fff}
.smeta-home .marquee-track{display:flex;white-space:nowrap;width:max-content;animation:sm-scroll 28s linear infinite}
.smeta-home .marquee-item{padding:25px 35px;font-size:12px;font-weight:600;color:#8a909c}
.smeta-home .marquee-item b{color:var(--purple);margin-right:9px}
@keyframes sm-scroll{to{transform:translateX(-50%)}}
.smeta-home .block{padding:125px 0}
.smeta-home .dark{background:var(--dark);color:#fff}
.smeta-home .soft{background:#f6f7fa}
.smeta-home .section-label{font:700 10px "IBM Plex Mono",monospace;letter-spacing:.13em;color:#716ae0;text-transform:uppercase}
.smeta-home .dark .section-label{color:#958fff}
.smeta-home .section-title{font-size:clamp(38px,5vw,70px);line-height:.95;letter-spacing:-.065em;font-weight:680;margin:15px 0 20px}
.smeta-home .section-copy{max-width:570px;color:var(--muted);font-size:15px;line-height:1.75}
.smeta-home .dark .section-copy{color:#969cab}
.smeta-home .numbers{display:grid;grid-template-columns:repeat(3,1fr);margin-top:65px;border-top:1px solid #2b2e38;border-bottom:1px solid #2b2e38}
.smeta-home .number{padding:32px 25px}
.smeta-home .number+.number{border-left:1px solid #2b2e38}
.smeta-home .number strong{font-size:40px;letter-spacing:-.06em}
.smeta-home .number span{display:block;margin-top:7px;color:#858b98;font-size:11px}
.smeta-home .compare{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:60px}
.smeta-home .compare-card{min-height:390px;border-radius:24px;padding:30px;border:1px solid var(--line);background:#fff}
.smeta-home .compare-card.bad{background:#f0f1f4;color:#6e7582}
.smeta-home .compare-card.good{background:#171922;color:#fff;border-color:#292c37;box-shadow:0 25px 70px rgba(0,0,0,.15)}
.smeta-home .compare-label{font:700 9px "IBM Plex Mono",monospace;letter-spacing:.12em}
.smeta-home .compare-card h3{font-size:30px;letter-spacing:-.05em;margin:55px 0 14px}
.smeta-home .compare-card p{font-size:13px;line-height:1.65;max-width:370px;color:#858b98}
.smeta-home .good p{color:#9ca2ae}
.smeta-home .fake-excel{margin-top:35px;border:1px solid #dfe2e8;border-radius:9px;background:#fff;overflow:hidden}
.smeta-home .fake-excel div{display:grid;grid-template-columns:1.6fr .7fr .8fr;border-top:1px solid #eceef1;padding:8px 10px;font-size:8px;color:#818794}
.smeta-home .fake-excel div:first-child{border-top:0;background:#e9eaee;font-weight:700}
.smeta-home .feature-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:20px;margin-top:60px}
.smeta-home .feature-big,.smeta-home .feature-small{border:1px solid #e3e5eb;border-radius:25px;background:#fff;overflow:hidden}
.smeta-home .feature-big{min-height:540px;padding:35px;position:relative}
.smeta-home .feature-small{padding:30px;min-height:260px}
.smeta-home .feature-small+.feature-small{margin-top:20px}
.smeta-home .feature-big h3,.smeta-home .feature-small h3{font-size:25px;letter-spacing:-.045em;margin:12px 0}
.smeta-home .feature-big p,.smeta-home .feature-small p{color:var(--muted);font-size:12px;line-height:1.65;max-width:420px}
.smeta-home .estimate-ui{position:absolute;left:35px;right:35px;bottom:-10px;border:1px solid #dedfe7;border-radius:17px 17px 0 0;background:#f8f9fb;box-shadow:0 20px 50px rgba(20,24,40,.1);padding:14px}
.smeta-home .estimate-ui-head{height:35px;display:flex;justify-content:space-between;align-items:center;font-size:10px;font-weight:700}
.smeta-home .estimate-line{height:35px;background:#fff;border-top:1px solid #e8eaf0;display:grid;grid-template-columns:1.5fr .5fr .6fr;padding:0 10px;align-items:center;font-size:8px;color:#818795}
.smeta-home .estimate-line b{color:#303541}
.smeta-home .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:65px}
.smeta-home .step{position:relative;min-height:280px;padding:28px;border-top:2px solid #dfe1e7}
.smeta-home .step:first-child{border-color:var(--purple)}
.smeta-home .step-num{font:700 11px "IBM Plex Mono",monospace;color:#858b98}
.smeta-home .step h3{font-size:24px;letter-spacing:-.045em;margin:70px 0 12px}
.smeta-home .step p{font-size:12px;line-height:1.7;color:var(--muted)}
.smeta-home .pricing-wrap{max-width:980px;margin:65px auto 0}
.smeta-home .plan{height:100%;padding:35px;border:1px solid #e1e3ea;border-radius:22px;background:#fff}
.smeta-home .plan.pro{background:#171922;color:#fff;border-color:#171922;box-shadow:0 25px 70px rgba(23,25,34,.17)}
.smeta-home .plan-label{font:700 9px "IBM Plex Mono",monospace;letter-spacing:.12em;color:#858b98}
.smeta-home .pro .plan-label{color:#a39fff}
.smeta-home .plan h3{font-size:31px;letter-spacing:-.055em;margin:15px 0}
.smeta-home .price{font-size:42px;font-weight:700;letter-spacing:-.06em}
.smeta-home .price small{font-size:11px;font-weight:500;color:#9298a4;letter-spacing:0}
.smeta-home .plan ul{list-style:none;padding:0;margin:30px 0;display:grid;gap:12px}
.smeta-home .plan li{font-size:12px;color:#6f7684}
.smeta-home .pro li{color:#a4a9b5}
.smeta-home .plan li:before{content:"✓";color:#625cff;font-weight:800;margin-right:9px}
.smeta-home .plan .btn{border-radius:11px;padding:12px;font-weight:600}
.smeta-home .final{padding:140px 0;background:#11131a;color:#fff;position:relative;overflow:hidden}
.smeta-home .final:before{content:"";position:absolute;width:650px;height:650px;border-radius:50%;background:radial-gradient(circle,rgba(99,91,255,.25),transparent 68%);right:-180px;top:-220px}
.smeta-home .final-inner{position:relative;z-index:2}
.smeta-home .final h2{font-size:clamp(50px,7vw,95px);line-height:.87;letter-spacing:-.075em;max-width:900px;margin:18px 0 30px}
.smeta-home .final p{color:#969ca8;max-width:510px;font-size:15px;line-height:1.7}
.smeta-home .final .btn{border-radius:12px;padding:14px 22px;margin-top:27px}
@media(max-width:991px){
 .smeta-home .hero{padding:80px 0}
 .smeta-home .hero-ui{margin:55px auto 0;max-width:700px}
 .smeta-home .feature-grid{grid-template-columns:1fr}
}
@media(max-width:767px){
 .smeta-home .hero{min-height:auto;padding:65px 0 80px}
 .smeta-home h1{font-size:clamp(50px,15vw,76px)}
 .smeta-home .hero-copy{font-size:16px}
 .smeta-home .hero-ui{transform:none}
 .smeta-home .ui-body{grid-template-columns:62px 1fr;min-height:330px}
 .smeta-home .ui-sidebar{padding:15px 7px}.smeta-home .ui-nav div{font-size:7px;padding:8px 5px}
 .smeta-home .ui-main{padding:12px}.smeta-home .ui-row{grid-template-columns:1fr 42px 55px;font-size:7px}
 .smeta-home .hero-card{right:5px;bottom:8px}
 .smeta-home .block{padding:75px 0}
 .smeta-home .numbers,.smeta-home .compare,.smeta-home .steps{grid-template-columns:1fr}
 .smeta-home .number+.number{border-left:0;border-top:1px solid #2b2e38}
 .smeta-home .compare-card{min-height:330px}
 .smeta-home .steps{gap:35px}
 .smeta-home .step{min-height:220px}
 .smeta-home .step h3{margin-top:45px}
 .smeta-home .estimate-ui{left:18px;right:18px}
 .smeta-home .pricing-wrap{margin-top:40px}
}
</style>

<div class="smeta-home">
  <section class="hero">
    <div class="container hero-inner">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="kicker"><i></i> цифровая смета для стройки</div>
          <h1>Смета.<br><span>Без хаоса.</span></h1>
          <p class="hero-copy">Сметограм превращает расчёт строительства в нормальный цифровой рабочий процесс. Проекты, материалы, работы и итоговая стоимость — в одном месте.</p>
          <div class="hero-buttons">
            <?php if($user): ?>
              <a href="dashboard.php" class="btn btn-primary btn-lg"><?= $subActive ? 'Открыть Сметограм' : ($projectCount ? 'Продолжить смету' : 'Создать первую смету') ?></a>
            <?php else: ?>
              <a href="login.php" class="btn btn-primary btn-lg">Создать первую смету</a>
              <a href="login.php" class="btn btn-light border btn-lg">Войти</a>
            <?php endif; ?>
          </div>
          <div class="micro"><span><b>✓</b> первая смета бесплатно</span><span><b>✓</b> без установки</span><span><b>✓</b> в браузере</span></div>
        </div>

        <div class="col-lg-6">
          <div class="hero-ui">
            <div class="ui-window">
              <div class="ui-top"><i class="ui-dot"></i><i class="ui-dot"></i><i class="ui-dot"></i><span class="ui-address"></span></div>
              <div class="ui-body">
                <aside class="ui-sidebar">
                  <div class="ui-logo">СМЕТО<span>ГРАМ</span></div>
                  <div class="ui-nav"><div class="sel">Проекты</div><div>Сметы</div><div>Материалы</div><div>Настройки</div></div>
                </aside>
                <div class="ui-main">
                  <div class="ui-head"><strong>Дом на Курортной</strong><span class="ui-tag">В РАБОТЕ</span></div>
                  <div class="ui-sheet">
                    <div class="ui-sheet-title"><span>Смета строительства</span><span>Сумма</span></div>
                    <div class="ui-row"><b>Бетон М300</b><span>18 м³</span><b>153 000 ₽</b></div>
                    <div class="ui-row"><b>Арматура</b><span>2,4 т</span><b>172 800 ₽</b></div>
                    <div class="ui-row"><b>Опалубка</b><span>120 м²</span><b>144 000 ₽</b></div>
                    <div class="ui-row"><b>Работа</b><span>1 компл.</span><b>86 000 ₽</b></div>
                    <div class="ui-total"><span>ИТОГО</span><strong>555 800 ₽</strong></div>
                  </div>
                  <div class="ui-chart"><div class="ui-sheet-title"><span>Структура затрат</span><span>42 позиции</span></div><div class="bars"><i style="height:45%"></i><i style="height:68%"></i><i style="height:53%"></i><i style="height:88%"></i><i style="height:72%"></i><i style="height:100%"></i><i style="height:78%"></i><i style="height:92%"></i></div></div>
                </div>
              </div>
            </div>
            <div class="hero-card"><small>СТОИМОСТЬ ПРОЕКТА</small><strong>2 840 500 ₽</strong><span>↑ расчёт обновлён</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="marquee"><div class="marquee-track">
    <div class="marquee-item"><b>01</b> ПРОЕКТЫ</div><div class="marquee-item"><b>02</b> МАТЕРИАЛЫ</div><div class="marquee-item"><b>03</b> РАБОТЫ</div><div class="marquee-item"><b>04</b> РАСЧЁТ</div><div class="marquee-item"><b>05</b> СМЕТА</div><div class="marquee-item"><b>06</b> КОНТРОЛЬ</div>
    <div class="marquee-item"><b>01</b> ПРОЕКТЫ</div><div class="marquee-item"><b>02</b> МАТЕРИАЛЫ</div><div class="marquee-item"><b>03</b> РАБОТЫ</div><div class="marquee-item"><b>04</b> РАСЧЁТ</div><div class="marquee-item"><b>05</b> СМЕТА</div><div class="marquee-item"><b>06</b> КОНТРОЛЬ</div>
  </div></div>

  <section class="block dark">
    <div class="container">
      <div class="row">
        <div class="col-lg-8">
          <div class="section-label">01 / зачем это нужно</div>
          <h2 class="section-title">Excel был создан не для этого.</h2>
          <p class="section-copy">Когда проектов становится больше одного, таблицы начинают жить своей жизнью. Версии теряются, формулы ломаются, а итог приходится перепроверять вручную.</p>
        </div>
      </div>
      <div class="numbers">
        <div class="number"><strong>01</strong><span>единый проект вместо десятков файлов</span></div>
        <div class="number"><strong>∞</strong><span>позиций и расчётов внутри сметы</span></div>
        <div class="number"><strong>1</strong><span>актуальная сумма перед глазами</span></div>
      </div>
    </div>
  </section>

  <section class="block soft">
    <div class="container">
      <div class="section-label">02 / переход</div>
      <h2 class="section-title">От таблицы<br>к рабочему инструменту.</h2>
      <div class="compare">
        <div class="compare-card bad">
          <div class="compare-label">БЫЛО / EXCEL</div>
          <h3>«Где последняя версия?»</h3>
          <p>Файлы, копии, ручные формулы, бесконечные строки и постоянная проверка итогов.</p>
          <div class="fake-excel"><div><span>Наименование</span><span>Кол-во</span><span>Сумма</span></div><div><span>Бетон</span><span>18</span><span>153 000</span></div><div><span>Арматура</span><span>2,4</span><span>172 800</span></div><div><span>Работа</span><span>—</span><span>86 000</span></div></div>
        </div>
        <div class="compare-card good">
          <div class="compare-label">СТАЛО / СМЕТОГРАМ</div>
          <h3>«Вот итог.»</h3>
          <p>Проект хранит всё в одном месте. Вы меняете количество или цену — расчёт обновляется сразу.</p>
          <div class="ui-sheet" style="margin-top:35px">
            <div class="ui-row"><b>Материалы</b><span>42</span><b>1 275 400 ₽</b></div>
            <div class="ui-row"><b>Работы</b><span>18</span><b>864 300 ₽</b></div>
            <div class="ui-row"><b>Техника</b><span>7</span><b>700 800 ₽</b></div>
            <div class="ui-total"><span>ИТОГО</span><strong>2 840 500 ₽</strong></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="block">
    <div class="container">
      <div class="section-label">03 / внутри</div>
      <div class="row align-items-end">
        <div class="col-lg-8"><h2 class="section-title">Не набор функций.<br>Нормальный процесс.</h2></div>
        <div class="col-lg-4"><p class="section-copy mb-2">Сметограм построен вокруг того, как реально составляется строительная смета.</p></div>
      </div>

      <div class="feature-grid">
        <div class="feature-big">
          <div class="section-label">СМЕТА</div>
          <h3>Позиции складываются в понятный итог.</h3>
          <p>Добавляйте материалы и работы, указывайте количество и цену. Никаких ручных пересчётов.</p>
          <div class="estimate-ui">
            <div class="estimate-ui-head"><span>Дом на Курортной</span><span>555 800 ₽</span></div>
            <div class="estimate-line"><b>Бетон М300</b><span>18 м³</span><b>153 000 ₽</b></div>
            <div class="estimate-line"><b>Арматура</b><span>2,4 т</span><b>172 800 ₽</b></div>
            <div class="estimate-line"><b>Опалубка</b><span>120 м²</span><b>144 000 ₽</b></div>
            <div class="estimate-line"><b>Работа</b><span>1 компл.</span><b>86 000 ₽</b></div>
          </div>
        </div>
        <div>
          <div class="feature-small"><div class="section-label">ПРОЕКТЫ</div><h3>Все объекты рядом.</h3><p>Один аккаунт — единое пространство для ваших проектов и смет.</p></div>
          <div class="feature-small"><div class="section-label">РАСЧЁТ</div><h3>Цифры всегда актуальны.</h3><p>Изменили цену или количество — итог меняется автоматически.</p></div>
        </div>
      </div>
    </div>
  </section>

  <section class="block soft">
    <div class="container">
      <div class="section-label">04 / как это работает</div>
      <h2 class="section-title">Три действия.<br>И смета готова.</h2>
      <div class="steps">
        <div class="step"><div class="step-num">01 / СОЗДАЙ</div><h3>Проект</h3><p>Назовите объект и начните с чистого листа.</p></div>
        <div class="step"><div class="step-num">02 / ДОБАВЬ</div><h3>Позиции</h3><p>Материалы, работы, количество и стоимость.</p></div>
        <div class="step"><div class="step-num">03 / ПОЛУЧИ</div><h3>Итог</h3><p>Смета автоматически считает актуальную стоимость проекта.</p></div>
      </div>
    </div>
  </section>

  <section class="block">
    <div class="container">
      <div class="section-label">05 / доступ</div>
      <div class="row align-items-end">
        <div class="col-lg-8"><h2 class="section-title">Одна смета — бесплатно.<br>Дальше решаете вы.</h2></div>
        <div class="col-lg-4"><p class="section-copy mb-2">Новый пользователь получает одну полноценную смету. Для новых проектов нужна активная подписка.</p></div>
      </div>
      <div class="pricing-wrap">
        <div class="row g-3">
          <div class="col-md-6"><div class="plan"><div class="plan-label">START</div><h3>Бесплатно</h3><div class="price">0 ₽ <small>навсегда</small></div><ul><li>1 полноценная смета</li><li>Редактирование проекта</li><li>Автоматический расчёт</li><li>Работа в браузере</li></ul><a href="<?= $user ? 'dashboard.php' : 'login.php' ?>" class="btn btn-light border w-100"><?= $projectCount ? 'Открыть смету' : 'Начать бесплатно' ?></a></div></div>
          <div class="col-md-6"><div class="plan pro"><div class="plan-label">PRO / ПОДПИСКА</div><h3>Для постоянной работы</h3><div class="price">∞ <small>проектов</small></div><ul><li>Новые сметы без ограничения</li><li>Все возможности бесплатного доступа</li><li>Единое рабочее пространство</li><li>Доступ на весь срок подписки</li></ul><a href="subscription.php" class="btn btn-primary w-100">Выбрать тариф</a></div></div>
        </div>
      </div>
    </div>
  </section>

  <section class="final">
    <div class="container final-inner">
      <div class="section-label">СМЕТОГРАМ / START</div>
      <h2>Хватит считать<br>вручную.</h2>
      <p>Создайте первую смету и посмотрите, насколько проще может выглядеть обычная работа со строительными расчётами.</p>
      <?php if($user): ?>
        <a href="dashboard.php" class="btn btn-light btn-lg"><?= $subActive ? 'Открыть Сметограм' : ($projectCount ? 'Продолжить смету' : 'Создать первую смету') ?></a>
      <?php else: ?>
        <a href="login.php" class="btn btn-light btn-lg">Создать первую смету</a>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>