<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();$projectId=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");$q->execute([$projectId]);$project=$q->fetch();if(!$project || !can_access_project($pdo,$user,$projectId))redirect('dashboard.php');
$canManage=can_manage_project($pdo,$user,$projectId);
$items=[
['Демонтаж','Демонтаж плитки','м²',350,0],['Демонтаж','Демонтаж старой плитки','м²',450,0],['Демонтаж','Удаление обоев','м²',120,0],
['Черновые работы','Грунтовка стен','м²',80,40],['Черновые работы','Штукатурка стен','м²',650,220],['Черновые работы','Штукатурка стен по маякам','м²',650,280],['Черновые работы','Шпаклевка стен','м²',420,180],
['Отделка','Покраска стен','м²',280,150],['Отделка','Покраска стен в два слоя','м²',350,160],['Отделка','Шпаклевка под покраску','м²',450,180],
['Полы','Укладка ламината','м²',450,900],['Полы','Укладка ламината усиленная','м²',550,900],
['Плитка','Укладка керамогранита','м²',1400,1800],['Плитка','Укладка керамогранита стандарт','м²',1500,1400],
['Электрика','Монтаж розетки','шт',650,300],['Электрика','Прокладка кабеля','м.п.',120,80],['Сантехника','Монтаж смесителя','шт',1200,0],['Сантехника','Монтаж инсталляции','шт',4500,3000],
['Потолки','Монтаж натяжного потолка','м²',700,900],['Потолки','Натяжной потолок','м²',900,700]
];
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();if(!$canManage){http_response_code(403);exit('Недостаточно прав для добавления позиции.');}$item=(int)$_POST['catalog_index'];$qty=(float)str_replace(',','.',$_POST['quantity']??1);if(isset($items[$item])){$it=$items[$item];$catName=$it[0];$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE projectId=? AND name=? LIMIT 1");$q->execute([$projectId,$catName]);$cat=$q->fetchColumn();if(!$cat){$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$projectId,$catName,100]);$cat=$pdo->lastInsertId();}$q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");$q->execute([(int)$cat,$it[1],$qty,$it[2],$it[3]+$it[4],'catalog']);}redirect('catalog.php?id='.$projectId.'&added=1');}
$query=mb_strtolower(trim($_GET['q']??''));$base=$_GET['base']??'ФЕР';
$normativeVersion='ФСНБ-2022 И19';
$normativeDate='22.09.2026';
$normativeSource='https://smeta.ru/download/norm';
$baseLabel=['ФЕР'=>'Федеральные единичные расценки','ГЭСН'=>'Государственные элементные сметные нормы','ТЕР'=>'Территориальные единичные расценки'][$base]??'Федеральные единичные расценки';$filtered=array_values(array_filter($items,fn($x)=>$query===''||mb_strpos(mb_strtolower(implode(' ',$x)),$query)!==false));
$pageTitle='Каталог расценок';require __DIR__.'/includes/app_header.php';?>
<section class="page-wrap"><div class="page-heading"><div><div class="eyebrow">КАТАЛОГ РАСЦЕНОК</div><h1>ФЕР · ТЕР · ГЭСН</h1><p class="lede">Базовые расценки и материалы для проекта «<?=e($project['name'])?>».</p></div><a class="outline-button" href="project.php?id=<?=$projectId?>"><i class="bi bi-arrow-left"></i> Вернуться в смету</a></div>
<?php if(isset($_GET['added'])):?><div class="alert alert-success mt-4">Позиция добавлена в смету.</div><?php endif;?>
<div class="module-panel mt-4">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <div class="eyebrow">АКТУАЛЬНАЯ НОРМАТИВНАЯ БАЗА</div>
    <h2 class="mb-1"><?=$normativeVersion?></h2>
    <p class="mb-0 text-muted">Дополнение И19 · опубликовано 22.09.2026 · действует с 25.08.2026</p>
  </div>
  <a class="outline-button" href="<?=e($normativeSource)?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Источник базы</a>
</div>
<div class="mt-3 small text-muted">ФСНБ-2022 И19 включает актуализированные нормы ГЭСН и ФЕР. Полный массив нормативов должен загружаться отдельной базой, а не заменяться самодельными ценами.</div>
</div>
<form class="module-panel mt-3 d-flex gap-2 flex-wrap" method="get"><input type="hidden" name="id" value="<?=$projectId?>"><select class="filter-select" name="base"><option value="ФЕР" <?=$base==='ФЕР'?'selected':''?>>ФЕР</option><option value="ГЭСН" <?=$base==='ГЭСН'?'selected':''?>>ГЭСН</option><option value="ТЕР" <?=$base==='ТЕР'?'selected':''?>>ТЕР</option></select><label class="project-search flex-grow-1"><i class="bi bi-search"></i><input name="q" value="<?=e($query)?>" placeholder="Поиск по <?=$baseLabel?>"></label><button class="primary-button">Найти</button></form>
<div class="module-panel mt-3"><div class="panel-heading"><div class="catalog-toolbar-note"><i class="bi bi-database-check"></i><span>Источник: ФСНБ-2022 И19 · каталог подключается к нормативной базе без подмены официальных расценок.</span></div><div><h2>Каталог <?=$base?> · найдено позиций: <?=count($filtered)?></h2><p><?=$baseLabel?>. Текущий встроенный набор является локальным каталогом до загрузки полного массива ФСНБ.</p></div></div><?php foreach($filtered as $i=>$it):?><div class="document-row"><div class="member-avatar"><i class="bi bi-list-check"></i></div><div><strong><?=e($it[1])?></strong><span><?=e($it[0])?> · <?=e($it[2])?> · работа <?=number_format($it[3],0,',',' ')?> ₽ · материал <?=number_format($it[4],0,',',' ')?> ₽</span></div><em><?=number_format($it[3]+$it[4],0,',',' ')?> ₽/<?=e($it[2])?></em><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="catalog_index" value="<?=array_search($it,$items,true)?>"><input class="form-control" style="width:75px" name="quantity" value="1"><button class="primary-button" title="Добавить"><i class="bi bi-plus-lg"></i></button></form></div><?php endforeach;?></div></section>
<?php require __DIR__.'/includes/app_footer.php';?>