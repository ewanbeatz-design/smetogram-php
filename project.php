<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");$q->execute([$id]);$project=$q->fetch();
if(!$project || !can_access_project($pdo,$user,$id))redirect('dashboard.php');
$canManage=can_manage_project($pdo,$user,$id);
$canDelete=is_admin($user) || (int)$project['ownerId']===(int)$user['id'];
$canEditCard=$canManage;
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 if(!$canManage){http_response_code(403);exit('Недостаточно прав для изменения проекта.');}
 $action=$_POST['action']??'';
 if($action==='update_project'){
  $name=trim((string)($_POST['name']??''));
  $city=trim((string)($_POST['city']??''));
  $client=trim((string)($_POST['clientName']??''));
  $clientEmail=trim((string)($_POST['clientEmail']??''));
  $work=trim((string)($_POST['workType']??''));
  $estimateDate=trim((string)($_POST['estimateDate']??''));
  $deadline=trim((string)($_POST['deadline']??''));
  if($name===''){ $error='Введите название сметы.'; }
  else {
    $q=$pdo->prepare("UPDATE projects SET name=?,city=?,clientName=?,clientEmail=?,workType=?,estimateDate=?,deadline=? WHERE id=?");
    $q->execute([$name,$city,$client,$clientEmail,$work!==''?$work:'Строительство',$estimateDate!==''?$estimateDate:null,$deadline!==''?$deadline:null,$id]);
    notify_project_users($pdo,$id,(int)$user['id'],'project','Проект обновлён','Изменены данные проекта.','project.php?id='.$id);
    redirect('project.php?id='.$id);
  }
 }
 if($action==='delete_project'){
  if(!$canDelete){http_response_code(403);exit('Недостаточно прав для удаления сметы.');}
  $pdo->beginTransaction();
  try{
   $q=$pdo->prepare("DELETE i FROM estimateitems i INNER JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=?");$q->execute([$id]);
   $q=$pdo->prepare("DELETE FROM estimatecategories WHERE projectId=?");$q->execute([$id]);
   foreach(['scheduletasks','projectmembers','projectdocuments','projectpayments','projectmeasurements','projectmessages'] as $table){try{$q=$pdo->prepare("DELETE FROM {$table} WHERE projectId=?");$q->execute([$id]);}catch(Throwable $ignore){}}
   $q=$pdo->prepare("DELETE FROM projects WHERE id=?");$q->execute([$id]);
   $pdo->commit();
   redirect('dashboard.php');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 if($action==='category'){ $name=trim($_POST['name']??'');if($name!==''){$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$name,(int)$pdo->query("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=".(int)$id)->fetchColumn()]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Добавлен раздел сметы',$name,'project.php?id='.$id);}redirect('project.php?id='.$id);}
 if($action==='template'){ $template=(int)($_POST['template_id']??0);$qty=(float)str_replace(',','.',$_POST['template_quantity']??$_POST['quantity']??1);if($qty<=0)$qty=1;$q=$pdo->prepare("SELECT * FROM estimateitemtemplates WHERE id=? LIMIT 1");$q->execute([$template]);$it=$q->fetch();if($it){$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE projectId=? AND name=? LIMIT 1");$q->execute([$id,$it['categoryName']]);$cat=$q->fetchColumn();if(!$cat){$q=$pdo->prepare("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?");$q->execute([$id]);$sort=(int)$q->fetchColumn();$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$it['categoryName'],$sort]);$cat=$pdo->lastInsertId();}$m=project_measurement_quantities($pdo,$id);$mType=measurement_type_for_name((string)$it['name'],(string)$it['unit']);if($mType!==null)$qty=(float)$m[$mType];
$q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source,quantitySource,measurementType,measurementRoomIds) VALUES(?,?,?,?,?,?,?,?,?)");$q->execute([(int)$cat,$it['name'],$qty,$it['unit'],$it['price'],'template',$mType!==null?'measurement':'template',$mType,$mType!==null?implode(',',array_map('intval',$m['roomIds'])):null]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Добавлена расценка',((string)$it['name']).' · '.$it['price'].' ₽','project.php?id='.$id);}redirect('project.php?id='.$id);}
 if($action==='item'){ $cat=(int)($_POST['category_id']??0);$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);if($q->fetch()){ $name=trim($_POST['name']??'');$qty=(float)str_replace(',','.',$_POST['quantity']??0);$unit=trim($_POST['unit']??'шт');$price=(float)str_replace(',','.',$_POST['price']??0);if($name!==''){$m=project_measurement_quantities($pdo,$id);$mType=measurement_type_for_name($name,$unit);if($mType!==null)$qty=(float)$m[$mType];$q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source,quantitySource,measurementType,measurementRoomIds) VALUES(?,?,?,?,?,?,?,?,?)");$q->execute([$cat,$name,$qty,$unit,$price,'manual',$mType!==null?'measurement':'manual',$mType,$mType!==null?implode(',',array_map('intval',$m['roomIds'])):null]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Добавлена позиция сметы',$name.' · '.$price.' ₽','project.php?id='.$id);}}redirect('project.php?id='.$id);}
 if($action==='update_item'){ $item=(int)$_POST['item_id'];$q=$pdo->prepare("SELECT i.id FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([$item,$id]);if($q->fetch()){ $name=trim($_POST['name']??'');$qty=(float)str_replace(',','.',$_POST['quantity']??0);$unit=trim($_POST['unit']??'шт');$price=(float)str_replace(',','.',$_POST['price']??0);$q=$pdo->prepare("UPDATE estimateitems SET name=?,quantity=?,unit=?,price=?,quantitySource='manual',measurementType=NULL,measurementRoomIds=NULL WHERE id=?");$q->execute([$name,$qty,$unit,$price,$item]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Изменена позиция сметы',$name,'project.php?id='.$id);}redirect('project.php?id='.$id);}
 if($action==='delete_item'){ $q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([(int)$_POST['item_id'],$id]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Удалена позиция сметы','Позиция удалена из сметы.','project.php?id='.$id);redirect('project.php?id='.$id);}
 if($action==='delete_category'){ $cat=(int)$_POST['category_id'];$q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.id=? AND c.projectId=?");$q->execute([$cat,$id]);$q=$pdo->prepare("DELETE FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);notify_project_users($pdo,$id,(int)$user['id'],'estimate','Удалён раздел сметы','Раздел удалён из сметы.','project.php?id='.$id);redirect('project.php?id='.$id);}
 if($action==='status'){ $allowed=['draft','in_progress','review','completed','archived'];$st=$_POST['status']??'draft';if(in_array($st,$allowed,true)){$q=$pdo->prepare("UPDATE projects SET status=? WHERE id=? AND ownerId=?");$q->execute([$st,$id,$user['id']]);$labels=['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'];notify_project_users($pdo,$id,(int)$user['id'],'project','Изменён статус проекта',$labels[$st]??$st,'project.php?id='.$id);}redirect('project.php?id='.$id);}
 }
// Перед выводом сметы обновляем все позиции, количество которых связано с замерами помещений.
try { refresh_measurement_estimate_items($pdo,$id); } catch(Throwable $ignore) {}
$q=$pdo->prepare("SELECT c.*,COALESCE(SUM(i.quantity*i.price),0) total,COUNT(i.id) item_count FROM estimatecategories c LEFT JOIN estimateitems i ON i.categoryId=c.id WHERE c.projectId=? GROUP BY c.id ORDER BY c.sortOrder,c.id");$q->execute([$id]);$cats=$q->fetchAll();$total=0;$itemsCount=0;foreach($cats as &$cat){$q=$pdo->prepare("SELECT * FROM estimateitems WHERE categoryId=? ORDER BY id");$q->execute([$cat['id']]);$cat['items']=$q->fetchAll();$total+=(float)$cat['total'];$itemsCount+=(int)$cat['item_count'];}unset($cat);
/* Гарантируем, что готовые шаблоны имеют реальные позиции даже на старой установке. */
$readyTemplateItems=[
'Косметический ремонт квартиры'=>[['Подготовка и демонтаж','Демонтаж обоев','м²',120],['Подготовка и демонтаж','Демонтаж напольного покрытия','м²',150],['Подготовка и демонтаж','Демонтаж плинтуса','м.п.',69],['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Шпаклевка стен','м²',420],['Черновые работы','Подготовка пола','м²',180],['Чистовая отделка','Покраска стен в два слоя','м²',350],['Чистовая отделка','Укладка ламината','м²',450],['Чистовая отделка','Монтаж плинтуса','м.п.',180],['Электрика','Монтаж розетки','шт',650],['Финишные работы','Уборка после ремонта','м²',120]],
'Капитальный ремонт квартиры'=>[['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж стяжки до 5 см','м²',650],['Демонтаж','Демонтаж перегородки','м²',850],['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Штукатурка стен по маякам','м²',650],['Черновые работы','Шпаклевка стен','м²',420],['Черновые работы','Стяжка пола до 50 мм','м²',900],['Черновые работы','Гидроизоляция пола','м²',450],['Электрика','Прокладка кабеля','м.п.',120],['Электрика','Монтаж подрозетника','шт',250],['Электрика','Монтаж розетки','шт',650],['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Разводка канализации','точка',1600],['Сантехника','Монтаж инсталляции','шт',4500],['Чистовая отделка','Укладка керамогранита','м²',1400],['Чистовая отделка','Покраска стен в два слоя','м²',350],['Чистовая отделка','Укладка ламината','м²',450],['Чистовая отделка','Монтаж межкомнатной двери','шт',4500],['Финишные работы','Монтаж плинтуса','м.п.',180],['Финишные работы','Финальная уборка','м²',120]],
'Ремонт санузла'=>[['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж сантехники','шт',1200],['Черновые работы','Гидроизоляция пола','м²',450],['Черновые работы','Выравнивание стен','м²',650],['Черновые работы','Стяжка пола','м²',900],['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Разводка канализации','точка',1600],['Сантехника','Монтаж коллектора','шт',3500],['Сантехника','Монтаж инсталляции','шт',4500],['Сантехника','Монтаж смесителя','шт',1200],['Плитка','Укладка керамогранита','м²',1400],['Плитка','Затирка швов','м²',300],['Электрика','Монтаж розетки','шт',650],['Электрика','Монтаж светильника','шт',1200],['Финишные работы','Герметизация примыканий','м.п.',250]],
'Электромонтаж'=>[['Подготовка','Разметка трасс','м.п.',120],['Черновая электрика','Штробление стен','м.п.',350],['Черновая электрика','Установка подрозетника','шт',250],['Черновая электрика','Прокладка кабеля','м.п.',120],['Черновая электрика','Сборка электрощита','шт',8500],['Черновая электрика','Монтаж распределительной коробки','шт',450],['Чистовая электрика','Монтаж розетки','шт',650],['Чистовая электрика','Монтаж выключателя','шт',650],['Чистовая электрика','Монтаж светильника','шт',1200],['Чистовая электрика','Подключение бытового оборудования','шт',900],['Пусконаладка','Проверка линий и автоматики','компл.',2500]],
'Сантехнические работы'=>[['Демонтаж','Демонтаж сантехнических приборов','шт',1200],['Черновая сантехника','Разводка холодной воды','точка',1800],['Черновая сантехника','Разводка горячей воды','точка',1800],['Черновая сантехника','Разводка канализации','точка',1600],['Черновая сантехника','Монтаж коллектора','шт',3500],['Черновая сантехника','Опрессовка системы','система',2500],['Чистовая сантехника','Монтаж унитаза','шт',2200],['Чистовая сантехника','Монтаж инсталляции','шт',4500],['Чистовая сантехника','Монтаж раковины','шт',1800],['Чистовая сантехника','Монтаж смесителя','шт',1200],['Чистовая сантехника','Подключение стиральной машины','шт',1200]],
'Строительство дома'=>[['Подготовительные работы','Разработка грунта','м³',900],['Подготовительные работы','Вывоз грунта','м³',750],['Фундамент','Устройство монолитного фундамента','м³',8500],['Фундамент','Гидроизоляция фундамента','м²',450],['Фундамент','Устройство утепления фундамента','м²',650],['Коробка','Возведение стен','м²',3200],['Коробка','Устройство перекрытия','м²',4500],['Коробка','Устройство армопояса','м.п.',850],['Кровля','Устройство кровельного покрытия','м²',2200],['Окна и двери','Монтаж оконных блоков','шт',4500],['Электрика','Прокладка кабеля','м.п.',120],['Сантехника','Разводка водоснабжения','точка',1800],['Отделка','Штукатурка стен по маякам','м²',650],['Отделка','Стяжка пола','м²',900]]
];
foreach($readyTemplateItems as $templateName=>$rows){
 $qt=$pdo->prepare("SELECT id FROM estimatetemplates WHERE name=? LIMIT 1");$qt->execute([$templateName]);$tid=(int)$qt->fetchColumn();
 if(!$tid)continue;
 $qc=$pdo->prepare("SELECT COUNT(*) FROM estimatetemplateitems WHERE templateId=?");$qc->execute([$tid]);
 if((int)$qc->fetchColumn()>0)continue;
 $ins=$pdo->prepare("INSERT INTO estimatetemplateitems(templateId,categoryName,name,unit,price,sortOrder) VALUES(?,?,?,?,?,?)");$sort=0;
 foreach($rows as $row)$ins->execute([$tid,$row[0],$row[1],$row[2],$row[3],++$sort]);
}
$q=$pdo->query("SELECT * FROM estimateitemtemplates ORDER BY categoryName,name");$templates=$q->fetchAll();
$q=$pdo->query("SELECT * FROM estimatetemplates ORDER BY id");$estimateTemplates=$q->fetchAll();
$estimateOverhead=$total*.15;$estimateProfit=$total*.08;$estimateVat=($total+$estimateOverhead+$estimateProfit)*.20;$estimateGrandTotal=$total+$estimateOverhead+$estimateProfit+$estimateVat;
$label=['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'][$project['status']]??$project['status'];
$pageTitle=$project['name'];require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap estimate-page">
<div class="estimate-heading"><div><a href="dashboard.php" class="back-button"><i class="fa-solid fa-arrow-left"></i> Все проекты</a><div class="estimate-title-row"><div class="project-symbol"><i class="fa-solid fa-house"></i><i class="fa-solid fa-check"></i></div><div><div class="eyebrow">ПРОЕКТ / СМЕТА</div><h1><?=e($project['name'])?></h1><p><?=e($project['city'])?> <b>·</b> <?=e($project['clientName'])?> <b>·</b> <?=e($project['workType'])?></p></div></div></div><div class="heading-actions"><form method="post" data-ajax-estimate><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><select class="status-select" name="status" onchange="estimateStatus(this.form)"><?php foreach(['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'] as $v=>$t):?><option value="<?=$v?>" <?=$project['status']===$v?'selected':''?>><?=$t?></option><?php endforeach;?></select></form><button class="outline-button" type="button" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="fa-solid fa-file-arrow-down"></i> Экспорт</button><a class="outline-button" href="catalog.php?id=<?=$id?>"><i class="fa-solid fa-book-open"></i> Каталог</a><?php if($canEditCard): ?><button class="outline-button" type="button" data-bs-toggle="modal" data-bs-target="#editProjectModal"><i class="fa-solid fa-pen"></i> Редактировать</button><?php endif; ?><?php if($canDelete): ?><form method="post" class="d-inline" onsubmit="return confirm('Удалить эту смету и все её данные? Это действие нельзя отменить.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_project"><button class="danger-button" type="submit"><i class="fa-solid fa-trash-can"></i> Удалить смету</button></form><?php endif; ?><button class="primary-button" type="button" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="fa-solid fa-grip"></i> Готовая смета</button></div></div>
<div class="estimate-project-tabs" aria-label="Разделы проекта">
    <a href="project.php?id=<?=$id?>" class="active"><i class="fa-solid fa-calculator"></i> Смета</a>
    <a href="workspace.php?view=schedule&id=<?=$id?>"><i class="fa-solid fa-calendar-days"></i> График</a>
    <a href="workspace.php?view=measurements&id=<?=$id?>"><i class="fa-solid fa-ruler-combined"></i> Замеры</a>
    <a href="workspace.php?view=team&id=<?=$id?>"><i class="fa-solid fa-users"></i> Команда</a>
    <a href="workspace.php?view=chat&id=<?=$id?>&channel=general"><i class="fa-solid fa-comments"></i> Чат</a>
    <a href="workspace.php?view=documents&id=<?=$id?>"><i class="fa-solid fa-file-lines"></i> Документы</a>
    <a href="workspace.php?view=payments&id=<?=$id?>"><i class="fa-solid fa-wallet"></i> Оплаты</a>
    <a href="workspace.php?view=acceptance&id=<?=$id?>"><i class="fa-solid fa-circle-check"></i> Приёмка</a>
    <a href="workspace.php?view=analytics&id=<?=$id?>"><i class="fa-solid fa-chart-column"></i> Аналитика</a>
</div>
<div class="estimate-summary"><div><span>ИТОГО ПО СМЕТЕ</span><strong><?=number_format($estimateGrandTotal,0,',',' ')?> ₽</strong><small>включая НДС 20%</small></div><div class="summary-stats"><div><b><?=count($cats)?></b> категории</div><div><b><?=$itemsCount?></b> позиций</div><div><b><?=number_format(array_sum(array_map(fn($c)=>(float)$c['items']?0:0,$cats)),0)?></b> объём</div></div><div class="summary-actions"><button class="outline-button" type="button" onclick="document.querySelector('.estimate-controls').scrollIntoView({behavior:'smooth',block:'center'})"><i class="fa-solid fa-calendar-days"></i> Создать график</button><button class="icon-button darkish" type="button" title="Дополнительно"><i class="fa-solid fa-ellipsis"></i></button></div></div>
<div class="estimate-controls" id="estimateCalcControls"><div><span class="control-label">МЕТОД РАСЧЁТА</span><select class="status-select" id="estimateMethod"><option value="resource">Ресурсный</option><option value="base-index">Базисно-индексный</option><option value="resource-index">Ресурсно-индексный</option></select></div><label class="check-control"><input id="winterCoeff" type="checkbox"> Зимнее удорожание <b>+12%</b></label><label class="check-control"><input id="tightCoeff" type="checkbox"> Стеснённые условия <b>+8%</b></label><label class="custom-coeff">Свой коэффициент<input id="customCoeff" min="0.1" max="5" step="0.01" type="number" value="1"></label></div><div class="estimate-toolbar"><div class="toolbar-tabs"><button type="button" class="active">Смета</button><button type="button">График <span>скоро</span></button><button type="button">Документы <span>скоро</span></button></div><div class="estimate-actions"><button type="button" class="text-button" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="fa-solid fa-grip"></i> Готовая смета</button><button type="button" class="text-button" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="fa-solid fa-plus"></i> Добавить категорию</button></div></div>
<div class="estimate-list">
<?php foreach($cats as $n=>$cat):?><div class="estimate-group"><div class="group-heading"><span class="group-number"><?=str_pad((string)($n+1),2,'0',STR_PAD_LEFT)?></span><h3><?=e($cat['name'])?></h3><span><?=$cat['item_count']?> позиций</span><strong><?=number_format((float)$cat['total'],0,',',' ')?> ₽</strong><button class="icon-button" title="Удалить раздел" onclick="if(confirm('Удалить раздел и его позиции?'))document.getElementById('delcat<?=$cat['id']?>').submit()"><i class="fa-solid fa-ellipsis"></i></button></div>
<div class="estimate-table"><div class="table-head"><span>РАБОТА</span><span>ОБЪЁМ</span><span>ЕД.</span><span>ЦЕНА</span><span>СУММА</span><span></span></div>
<?php foreach($cat['items'] as $item):$sum=(float)$item['quantity']*(float)$item['price'];?><div class="table-row" data-quantity="<?=e((string)$item['quantity'])?>" data-price="<?=e((string)$item['price'])?>" onclick="event.stopPropagation();editRow(<?=$item['id']?>)"><div class="work-name"><i class="work-dot"></i><?=e($item['name'])?></div><span><?=rtrim(rtrim(number_format((float)$item['quantity'],3,',',' '),'0'),',')?><?php if (($item['quantitySource']??'')==='measurement'): ?><em class="estimate-measurement-badge" title="Количество рассчитывается по замерам помещений"><i class="fa-solid fa-ruler-combined"></i></em><?php endif; ?></span><span><?=e($item['unit'])?></span><span><?=number_format((float)$item['price'],2,',',' ')?> ₽</span><strong><?=number_format($sum,2,',',' ')?> ₽</strong><div class="row-actions"><button class="edit-label" type="button" title="Редактировать" onclick="editRow(<?=$item['id']?>)">Изменить</button><form id="edit<?=$item['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_item"><input type="hidden" name="item_id" value="<?=$item['id']?>"><input name="name" value="<?=e($item['name'])?>"><input name="quantity" value="<?=e((string)$item['quantity'])?>"><input name="unit" value="<?=e($item['unit'])?>"><input name="price" value="<?=e((string)$item['price'])?>"></form><button type="button" title="Удалить" onclick="if(confirm('Удалить позицию?'))document.getElementById('delete<?=$item['id']?>').submit()"><i class="fa-solid fa-trash-can"></i></button><form id="delete<?=$item['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?=$item['id']?>"></form></div></div><?php endforeach;?>
<button class="add-row" type="button" data-work-catalog data-category-id="<?=$cat['id']?>" data-bs-toggle="modal" data-bs-target="#workCatalogModal"><i class="fa-solid fa-plus"></i> Добавить работу</button></div></div>
<form id="delcat<?=$cat['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?=$cat['id']?>"></form>
<div class="modal fade" id="item<?=$cat['id']?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" data-ajax-estimate><div class="modal-header"><h5 class="modal-title">Добавить позицию</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="item"><input type="hidden" name="category_id" value="<?=$cat['id']?>"><label class="form-label">Наименование</label><input class="form-control mb-3" name="name" required placeholder="Штукатурка стен по маякам"><div class="form-grid"><div><label class="form-label">Количество</label><input class="form-control" name="quantity" value="1"></div><div><label class="form-label">Единица</label><input class="form-control" name="unit" value="м²"></div><div><label class="form-label">Цена</label><input class="form-control" name="price" value="0"></div></div></div><div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Добавить</button></div></form></div></div></div>
<?php endforeach;?>
<?php if(!$cats):?><div class="empty-state module-panel"><i class="fa-solid fa-list fs-2 text-primary"></i><h3 class="mt-3">Смета пока пустая</h3><p class="text-muted">Создайте первый раздел и добавьте работы или материалы.</p><button class="primary-button" data-bs-toggle="modal" data-bs-target="#categoryModal">Создать раздел</button></div><?php endif;?>
<div class="estimate-totals"><div><span>Прямые затраты</span><b><?=number_format($total,2,',',' ')?> ₽</b></div><div><span>Накладные расходы (НР) · 15%</span><b><?=number_format($estimateOverhead,2,',',' ')?> ₽</b></div><div><span>Сметная прибыль (СП) · 8%</span><b><?=number_format($estimateProfit,2,',',' ')?> ₽</b></div><div><span>Коэффициенты</span><b>× 1.000</b></div><div><span>НДС · 20%</span><b><?=number_format($estimateVat,2,',',' ')?> ₽</b></div><div class="grand-total"><span>Итого в текущем уровне цен</span><strong><?=number_format($estimateGrandTotal,2,',',' ')?> ₽</strong></div></div></div>
<div class="mt-4 d-flex justify-content-between align-items-center"><a class="text-button" href="workspace.php?view=scan&id=<?=$id?>"><i class="fa-solid fa-wand-magic-sparkles"></i> Смета из файла</a><form method="post" onsubmit="return confirm('Удалить проект и всю смету?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_project"><button class="text-danger border-0 bg-transparent small">Удалить смету</button></form></div>
</section>
<div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Новый раздел</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="category"><label class="form-label">Название</label><input class="form-control" name="name" required placeholder="Материалы, Работы, Электрика..."></div><div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Создать</button></div></form></div></div></div>
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-xl">
<div class="modal-content estimate-template-modal">
<form id="estimateTemplateForm">
<div class="modal-header">
 <div><h5 class="modal-title">Готовые шаблоны смет</h5><small class="text-muted">Выберите основу — все позиции добавятся в текущую смету. Потом их можно удалить, изменить или дополнить.</small></div>
 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
 <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
 <input type="hidden" name="project_id" value="<?=$id?>">
 <input type="hidden" name="op" value="estimate_template">
 <div class="estimate-template-grid">
 <?php foreach($estimateTemplates as $tpl):?>
 <label class="estimate-template-card">
  <input type="radio" name="estimate_template_id" value="<?=$tpl['id']?>">
  <span class="estimate-template-icon"><i class="bi <?=e($tpl['icon'])?>"></i></span>
  <span class="estimate-template-content">
   <strong><?=e($tpl['name'])?></strong>
   <small><?=e($tpl['description'])?></small>
   <?php $tc=$pdo->prepare("SELECT COUNT(*) FROM estimatetemplateitems WHERE templateId=?");$tc->execute([$tpl['id']]); ?>
   <?php $templateItemStmt=$pdo->prepare("SELECT name,unit FROM estimatetemplateitems WHERE templateId=? ORDER BY sortOrder,id");$templateItemStmt->execute([$tpl['id']]);$templateItemsForCard=$templateItemStmt->fetchAll(); ?>
   <span class="estimate-template-meta"><b><?=count($templateItemsForCard)?></b> позиций · готовая структура</span>
   <span class="estimate-template-items-hidden"><?php foreach($templateItemsForCard as $ti): ?><span class="estimate-template-item"><?=e($ti['name'])?> · <?=e($ti['unit'])?></span><?php endforeach; ?></span>
  </span>
  <span class="estimate-template-check"><i class="fa-solid fa-check"></i></span>
 </label>
 <?php endforeach;?>
 </div>
 <div class="estimate-template-preview" id="estimateTemplatePreview" hidden></div>
 <div class="estimate-template-note"><i class="fa-solid fa-circle-info"></i><span>Шаблон ничего не блокирует: после добавления можно удалить любой раздел или позицию, изменить количество и цену, а также добавить свои работы.</span></div>
</div>
<div class="modal-footer">
 <button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button>
 <button class="primary-button" id="applyEstimateTemplate" type="submit" disabled><i class="fa-solid fa-plus"></i> Добавить в смету</button>
</div>
</form>
</div></div></div>
<div class="modal fade" id="workCatalogModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg work-catalog-dialog">
<div class="modal-content work-catalog-modal">
<div class="modal-header">
 <div><h5 class="modal-title">Добавить работу</h5><small class="text-muted">Все доступные работы и актуальные цены</small></div>
 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
 <input type="hidden" id="workCatalogCategory">
 <div class="work-catalog-search"><i class="fa-solid fa-magnifying-glass"></i><input id="workCatalogSearch" type="search" placeholder="Поиск работы..." autocomplete="off"></div>
 <div class="work-catalog-list" id="workCatalogList">
 <?php foreach($templates as $tpl):?>
 <button type="button" class="work-catalog-item" data-template-id="<?=$tpl['id']?>" data-name="<?=e(mb_strtolower($tpl['name'].' '.$tpl['categoryName']))?>">
  <span class="work-catalog-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
  <span class="work-catalog-info"><strong><?=e($tpl['name'])?></strong><small><?=e($tpl['categoryName'])?> · <?=e($tpl['unit'])?></small></span>
  <span class="work-catalog-price"><?=number_format((float)$tpl['price'],0,',',' ')?> ₽</span>
  <span class="work-catalog-check"><i class="fa-solid fa-check"></i></span>
 </button>
 <?php endforeach;?>
 </div>
 <div class="work-catalog-empty" id="workCatalogEmpty" hidden>По вашему запросу работы не найдены.</div>
 <div class="work-catalog-selected" id="workCatalogSelected" hidden>
  <div><span>Выбрано</span><strong id="workCatalogSelectedName">—</strong></div>
  <label>Количество<input id="workCatalogQuantity" type="number" value="1" min="0.001" step="0.001"></label>
 </div>
</div>
<div class="modal-footer">
 <button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button>
 <button class="primary-button" id="workCatalogAdd" type="button" disabled><i class="fa-solid fa-plus"></i> Добавить в смету</button>
</div>
</div></div></div>

<script>
const projectId=<?=json_encode($id)?>;

function showEstimateToast(message,type='success'){
 let host=document.getElementById('estimateToastHost');
 if(!host){host=document.createElement('div');host.id='estimateToastHost';host.className='estimate-toast-host';document.body.appendChild(host);}
 const toast=document.createElement('div');toast.className='estimate-toast estimate-toast-'+type;
 toast.innerHTML='<span class="estimate-toast-icon"><i class="bi '+(type==='error'?'bi-exclamation-triangle-fill':'bi-check-circle-fill')+'"></i></span><span class="estimate-toast-text">'+String(message).replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]))+'</span><button type="button" class="estimate-toast-close" aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>';
 host.appendChild(toast);requestAnimationFrame(()=>toast.classList.add('is-visible'));
 const close=()=>{toast.classList.remove('is-visible');setTimeout(()=>toast.remove(),220)};toast.querySelector('.estimate-toast-close').onclick=close;setTimeout(close,4200);
}

document.addEventListener('change',e=>{
 const radio=e.target.closest('#estimateTemplateForm input[name="estimate_template_id"]');
 if(radio){
  const btn=document.getElementById('applyEstimateTemplate');if(btn)btn.disabled=false;
  const card=radio.closest('.estimate-template-card');
  const preview=document.getElementById('estimateTemplatePreview');
  if(card&&preview){
   const items=[...card.querySelectorAll('.estimate-template-item')].map(x=>x.textContent.trim()).filter(Boolean);
   preview.hidden=false;preview.innerHTML='<div class="estimate-template-preview-title"><i class="fa-solid fa-list-check"></i><span>В шаблоне '+items.length+' позиций</span></div><div class="estimate-template-preview-list">'+items.map(x=>'<span>'+x.replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]))+'</span>').join('')+'</div>';
  }
 }
});
document.getElementById('estimateTemplateForm')?.addEventListener('submit',async e=>{
 e.preventDefault();
 const form=e.currentTarget,btn=document.getElementById('applyEstimateTemplate');
 if(!form.querySelector('input[name="estimate_template_id"]:checked'))return;
 btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span> Добавляем...';
 try{
  const fd=new FormData(form);
  const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
  const data=await res.json();
  if(!res.ok||!data.ok)throw new Error(data.error||'Не удалось добавить шаблон');
  await refreshEstimate();
  const modal=document.getElementById('templateModal');const instance=bootstrap.Modal.getInstance(modal);if(instance)instance.hide();
 }catch(err){showEstimateToast(err.message||'Ошибка добавления','error');}
 finally{btn.disabled=!form.querySelector('input[name="estimate_template_id"]:checked');btn.innerHTML='<i class="fa-solid fa-plus"></i> Добавить в смету';}
});

async function estimateStatus(form){
 const ok=await estimateAjax(form);
 if(!ok) return;
}
async function estimateAjax(form){
 const fd=new FormData(form);
 fd.set('project_id',projectId);
 const action=fd.get('action');
 const op=action==='category'?'category':action==='item'?'item':action==='template'?'template':action==='update_item'?'update_item':action==='delete_item'?'delete_item':action==='delete_category'?'delete_category':action==='status'?'status':'';
 if(!op)return false;
 fd.set('op',op);
 try{
  const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
  const data=await res.json();
  if(!res.ok||!data.ok)throw new Error(data.error||'Не удалось сохранить');
  await refreshEstimate();
  return true;
 }catch(err){showEstimateToast(err.message||'Ошибка сохранения','error');return false}
}
async function refreshEstimate(){
 const res=await fetch('project.php?id='+encodeURIComponent(projectId),{headers:{'X-Requested-With':'XMLHttpRequest'}});
 if(!res.ok)throw new Error('Не удалось обновить смету');
 const html=await res.text();
 const doc=new DOMParser().parseFromString(html,'text/html');
 const currentSummary=document.querySelector('.estimate-summary');
 const nextSummary=doc.querySelector('.estimate-summary');
 const currentList=document.querySelector('.estimate-list');
 const nextList=doc.querySelector('.estimate-list');
 if(currentSummary&&nextSummary)currentSummary.replaceWith(nextSummary);
 if(currentList&&nextList)currentList.replaceWith(nextList);
 const currentTabs=document.querySelector('.toolbar-tabs');
 const nextTabs=doc.querySelector('.toolbar-tabs');
 if(currentTabs&&nextTabs)currentTabs.replaceWith(nextTabs);
}
function bindEstimateForms(){
 document.querySelectorAll('form[data-ajax-estimate]').forEach(form=>{
  if(form.dataset.ajaxBound)return;
  form.dataset.ajaxBound='1';
  form.addEventListener('submit',async e=>{
   e.preventDefault();
   if(form.dataset.busy)return;
   form.dataset.busy='1';
   const ok=await estimateAjax(form);
   delete form.dataset.busy;
   if(ok){
    const modal=form.closest('.modal');
    if(modal){const instance=bootstrap.Modal.getInstance(modal);if(instance)instance.hide();}
   }
  });
 });
}
function editRow(id){
 const existing=document.querySelector('.table-row.is-editing');
 if(existing){ if(existing.dataset.itemId===String(id)) return; cancelInline(existing.dataset.itemId); return; }
 const f=document.getElementById('edit'+id);if(!f)return;
 const row=f.parentElement.closest('.table-row');f.remove();document.body.appendChild(f);
 const n=f.querySelector('[name="name"]').value,q=f.querySelector('[name="quantity"]').value,u=f.querySelector('[name="unit"]').value,p=f.querySelector('[name="price"]').value;
 row.innerHTML='<div class="work-name"><i class="work-dot"></i><input class="cell-input js-name" value="'+n.replace(/"/g,'&quot;')+'"></div><div><input class="cell-input js-qty" value="'+q+'"></div><div><input class="cell-input js-unit" value="'+u+'"></div><div><input class="cell-input js-price" value="'+p+'"></div><strong>—</strong><div class="row-actions"><button class="save-row" type="button" onclick="saveInline('+id+')"><i class="fa-solid fa-check"></i></button><button class="cancel-row" type="button" onclick="cancelInline('+id+')"><i class="fa-solid fa-xmark-lg"></i></button></div>';
 row.dataset.itemId=String(id); row.classList.add('is-editing');
}
function saveInline(id){
 const row=document.querySelector('.table-row.is-editing[data-item-id="'+id+'"]');const f=document.getElementById('edit'+id);if(!row||!f)return;
 f.querySelector('[name="name"]').value=row.querySelector('.js-name').value;
 f.querySelector('[name="quantity"]').value=row.querySelector('.js-qty').value;
 f.querySelector('[name="unit"]').value=row.querySelector('.js-unit').value;
 f.querySelector('[name="price"]').value=row.querySelector('.js-price').value;
 estimateAjax(f);
}
function cancelInline(id){refreshEstimate().catch(()=>location.reload())}

// Локальный пересчёт параметров сметы.
// Базовый ресурсный итог берётся из фактических позиций сметы.
// Базисно-индексный и ресурсно-индексный режимы пока не меняют сумму:
// для них нужны реальные индексы, которых в проекте ещё нет.
function recalculateEstimate(){
 const controls=document.getElementById('estimateCalcControls');
 if(!controls)return;
 const rows=[...document.querySelectorAll('.estimate-list .table-row[data-quantity][data-price]')];
 const base=rows.reduce((s,row)=>s+(parseFloat(String(row.dataset.quantity).replace(',','.'))||0)*(parseFloat(String(row.dataset.price).replace(',','.'))||0),0);
 const winter=document.getElementById('winterCoeff')?.checked?1.12:1;
 const tight=document.getElementById('tightCoeff')?.checked?1.08:1;
 const customRaw=parseFloat(document.getElementById('customCoeff')?.value||'1');
 const custom=Number.isFinite(customRaw)&&customRaw>0?customRaw:1;
 const coeff=winter*tight*custom;
 const direct=base*coeff;
 const overhead=direct*.15;
 const profit=direct*.08;
 const vat=(direct+overhead+profit)*.20;
 const grand=direct+overhead+profit+vat;
 const fmt=(v,d=2)=>new Intl.NumberFormat('ru-RU',{minimumFractionDigits:d,maximumFractionDigits:d}).format(v)+' ₽';
 const summary=document.querySelector('.estimate-summary strong');
 if(summary)summary.textContent=new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(grand)+' ₽';
 const totals=document.querySelector('.estimate-totals');
 if(!totals)return;
 const blocks=totals.querySelectorAll(':scope > div');
 if(blocks[0])blocks[0].querySelector('b').textContent=fmt(direct);
 if(blocks[1])blocks[1].querySelector('b').textContent=fmt(overhead);
 if(blocks[2])blocks[2].querySelector('b').textContent=fmt(profit);
 if(blocks[3])blocks[3].querySelector('b').textContent='× '+coeff.toFixed(3);
 if(blocks[4])blocks[4].querySelector('b').textContent=fmt(vat);
 const grandNode=totals.querySelector('.grand-total strong');
 if(grandNode)grandNode.textContent=fmt(grand);
}
function bindEstimateCalculation(){
 const method=document.getElementById('estimateMethod');
 const winter=document.getElementById('winterCoeff');
 const tight=document.getElementById('tightCoeff');
 const custom=document.getElementById('customCoeff');
 [method,winter,tight,custom].forEach(el=>{
  if(!el||el.dataset.calcBound)return;
  el.dataset.calcBound='1';
  el.addEventListener(el.type==='number'?'input':'change',recalculateEstimate);
 });
 recalculateEstimate();
}

document.addEventListener('DOMContentLoaded',()=>{bindEstimateForms();bindEstimateCalculation();});
const observer=new MutationObserver(bindEstimateForms);
observer.observe(document.body,{childList:true,subtree:true});

const workCatalogState={templateId:0,categoryId:0};
function resetWorkCatalog(){
 workCatalogState.templateId=0;workCatalogState.categoryId=0;
 const cat=document.getElementById('workCatalogCategory'), search=document.getElementById('workCatalogSearch'), qty=document.getElementById('workCatalogQuantity'), selected=document.getElementById('workCatalogSelected'), add=document.getElementById('workCatalogAdd');
 if(cat)cat.value='';if(search)search.value='';if(qty)qty.value='1';if(selected)selected.hidden=true;if(add)add.disabled=true;
 document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
 filterWorkCatalog();
}
function filterWorkCatalog(){
 const input=document.getElementById('workCatalogSearch');if(!input)return;
 const term=input.value.trim().toLowerCase();let visible=0;
 document.querySelectorAll('.work-catalog-item').forEach(item=>{
  const ok=!term||item.dataset.name.includes(term);item.hidden=!ok;if(ok)visible++;
 });
 const empty=document.getElementById('workCatalogEmpty');if(empty)empty.hidden=visible!==0;
}
function selectWorkCatalogItem(item){
 document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
 item.classList.add('is-selected');workCatalogState.templateId=Number(item.dataset.templateId);
 const selected=document.getElementById('workCatalogSelected'),name=document.getElementById('workCatalogSelectedName'),add=document.getElementById('workCatalogAdd');
 if(selected)selected.hidden=false;if(name)name.textContent=item.querySelector('strong')?.textContent||'Работа';if(add)add.disabled=!workCatalogState.categoryId;
}
async function addCatalogWork(){
 const add=document.getElementById('workCatalogAdd'),qty=document.getElementById('workCatalogQuantity');
 if(!workCatalogState.templateId||!workCatalogState.categoryId)return;
 add.disabled=true;
 const fd=new FormData();fd.set('csrf',<?=json_encode(csrf_token())?>);fd.set('project_id',projectId);fd.set('op','template');fd.set('template_id',workCatalogState.templateId);fd.set('category_id',workCatalogState.categoryId);fd.set('template_quantity',qty?.value||'1');
 try{const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Не удалось добавить работу');await refreshEstimate();const modal=document.getElementById('workCatalogModal');const instance=bootstrap.Modal.getInstance(modal);if(instance)instance.hide();}catch(err){alert(err.message||'Ошибка добавления');}finally{add.disabled=false;}
}
document.addEventListener('click',e=>{
 const btn=e.target.closest('[data-work-catalog]');
 if(btn){
  workCatalogState.categoryId=Number(btn.dataset.categoryId||0);
  document.getElementById('workCatalogCategory').value=workCatalogState.categoryId;
  document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
  workCatalogState.templateId=0;
  document.getElementById('workCatalogSelected').hidden=true;
  const add=document.getElementById('workCatalogAdd');if(add)add.disabled=true;
  return;
 }
 const item=e.target.closest('.work-catalog-item');
 if(item){selectWorkCatalogItem(item);}
});
document.addEventListener('DOMContentLoaded',()=>{
 const modal=document.getElementById('workCatalogModal'),search=document.getElementById('workCatalogSearch'),add=document.getElementById('workCatalogAdd');
 search?.addEventListener('input',filterWorkCatalog);
 add?.addEventListener('click',addCatalogWork);
 modal?.addEventListener('shown.bs.modal',()=>search?.focus());
 modal?.addEventListener('hidden.bs.modal',resetWorkCatalog);
});
</script>
<div class="modal fade export-choice-modal" id="exportModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header">
    <div>
     <div class="export-modal-brand"><span class="export-modal-brand-mark">S</span><span>сметограм</span></div>
     <div class="eyebrow">ЭКСПОРТ ПРОЕКТА</div>
     <h5 class="modal-title">Что скачать?</h5>
     <p class="export-choice-subtitle">PDF — документы, Excel — таблица сметы.</p>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
   </div>
   <div class="modal-body">
    <div class="export-choice-grid">
     <a class="export-choice-card" href="export.php?id=<?=$id?>&download=1">
      <span class="export-choice-icon"><i class="fa-solid fa-file-pdf"></i></span>
      <span><strong>Смета</strong><small>Готовый PDF-файл сметы</small></span><i class="fa-solid fa-arrow-up-right-from-square"></i>
     </a>
     <a class="export-choice-card" href="export.php?id=<?=$id?>&format=xlsx&download=1">
      <span class="export-choice-icon"><i class="fa-solid fa-file-excel"></i></span>
      <span><strong>Excel XLSX</strong><small>Готовый файл для Excel</small></span><i class="fa-solid fa-arrow-down"></i>
     </a>
     <a class="export-choice-card" href="export.php?id=<?=$id?>&format=ks2&download=1">
      <span class="export-choice-icon"><i class="fa-solid fa-file-lines"></i></span>
      <span><strong>КС-2 PDF</strong><small>Готовый PDF акт выполненных работ</small></span><i class="fa-solid fa-arrow-down"></i>
     </a>
     <a class="export-choice-card" href="export.php?id=<?=$id?>&format=ks3&download=1">
      <span class="export-choice-icon"><i class="fa-solid fa-file-excel"></i></span>
      <span><strong>КС-3 PDF</strong><small>Готовый PDF справка о стоимости</small></span><i class="fa-solid fa-arrow-down"></i>
     </a>
    </div>
   </div>
   <div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button></div>
  </div>
 </div>
</div>

<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
  <form method="post">
   <div class="modal-header"><div><h5 class="modal-title">Редактировать смету</h5><small class="text-muted">Название и данные карточки объекта</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
   <div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_project">
    <div class="form-grid">
     <div class="full"><label class="form-label">Название сметы</label><input class="form-control" name="name" value="<?=e((string)$project['name'])?>" required></div>
     <div><label class="form-label">Дата сметы</label><input class="form-control" type="date" name="estimateDate" value="<?=e((string)($project['estimateDate']??''))?>"></div>
     <div><label class="form-label">Срок сдачи</label><input class="form-control" type="date" name="deadline" value="<?=e((string)($project['deadline']??''))?>"></div>
     <div><label class="form-label">Город</label><input class="form-control" name="city" value="<?=e((string)$project['city'])?>" placeholder="Сочи"></div>
     <div><label class="form-label">Тип работ</label><input class="form-control" name="workType" value="<?=e((string)$project['workType'])?>" placeholder="Строительство"></div>
     <div><label class="form-label">Заказчик</label><input class="form-control" name="clientName" value="<?=e((string)$project['clientName'])?>" placeholder="Иван Петров"></div>
     <div><label class="form-label">Email заказчика</label><input class="form-control" type="email" name="clientEmail" value="<?=e((string)($project['clientEmail']??''))?>" placeholder="client@example.com"></div>
    </div>
   </div>
   <div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button" type="submit"><i class="fa-solid fa-check"></i> Сохранить</button></div>
  </form>
 </div></div>
</div>
<?php require __DIR__.'/includes/app_footer.php';?>