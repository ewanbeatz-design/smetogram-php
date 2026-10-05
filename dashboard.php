<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
  if(($_POST['action']??'')==='create_project'){
   if(!can_create_project($pdo,$user)){
    $error='Бесплатный доступ позволяет создать только одну смету. Чтобы создавать новые сметы, оформите подписку.';
   } else {
  $name=trim($_POST['name']??'');$city=trim($_POST['city']??'');$client=trim($_POST['clientName']??'');$work=trim($_POST['workType']??'Строительство');$deadline=trim($_POST['deadline']??'');
  if($name===''){$error='Введите название проекта.';}else{
   $q=$pdo->prepare("INSERT INTO projects(ownerId,name,city,clientName,workType,status,deadline,budget) VALUES(?,?,?,?,?,?,?,?)");
   $q->execute([$user['id'],$name,$city,$client,$work,'in_progress',$deadline!==''?$deadline:null,0]);
   $pid=(int)$pdo->lastInsertId();
   $q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$pid,'Общестроительные работы',1]);
   redirect('project.php?id='.$pid);
  }
 }
 }
 }
$q=$pdo->prepare("SELECT p.*,COALESCE((SELECT SUM(i.quantity*i.price) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=p.id),0) total,(SELECT COUNT(*) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=p.id) item_count FROM projects p WHERE p.ownerId=? ORDER BY p.updatedAt DESC");
$q->execute([$user['id']]);$all=$q->fetchAll();
$query=trim($_GET['q']??'');$status=$_GET['status']??'all';
$projects=array_values(array_filter($all,function($p)use($query,$status){$hay=mb_strtolower(($p['name']??'').' '.($p['city']??'').' '.($p['clientName']??'').' '.($p['workType']??''));return ($query===''||mb_strpos($hay,mb_strtolower($query))!==false)&&($status==='all'||$p['status']===$status);}));
$active=count(array_filter($all,fn($p)=>in_array($p['status'],['in_progress','draft'],true)));$review=count(array_filter($all,fn($p)=>$p['status']==='review'));$totalBudget=array_sum(array_map(fn($p)=>(float)$p['total'],$all));
$pageTitle='Мои проекты';require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap">
<div class="page-heading"><div><div class="eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО</div><h1>Мои проекты</h1><p class="lede">Весь объект — смета, сроки, команда и документы в одном месте.</p></div><?php $canCreate=can_create_project($pdo,$user); ?><button class="primary-button" <?= $canCreate?'data-bs-toggle="modal" data-bs-target="#newProject"':'data-bs-toggle="modal" data-bs-target="#subscriptionLimitModal' ?>><i class="bi bi-<?= $canCreate?'plus-lg':'lock' ?>"></i> <?= $canCreate?'Новый проект':'Нужна подписка' ?></button></div>
<?php if($error):?><div class="alert alert-danger mt-4"><?=e($error)?></div><?php endif;?>
<div class="metric-grid">
<div class="metric-card"><div class="metric-icon terra"><i class="bi bi-grid"></i></div><div><span>Всего проектов</span><strong><?=count($all)?></strong><small><?=count($all)===1?'проект':'проектов'?></small></div></div>
<div class="metric-card"><div class="metric-icon sage"><i class="bi bi-activity"></i></div><div><span>В работе</span><strong><?=$active?></strong><small>активных объектов</small></div></div>
<div class="metric-card"><div class="metric-icon sand"><i class="bi bi-wallet2"></i></div><div><span>Стоимость смет</span><strong><?=number_format($totalBudget,0,',',' ')?> ₽</strong><small><?=$review?> на согласовании</small></div></div>
</div>
<div class="section-title-row"><div><h2>Проекты <span><?=count($projects)?></span></h2><p>Откройте объект, чтобы перейти в рабочее пространство.</p></div><form class="filter-row" method="get"><label class="project-search"><i class="bi bi-search"></i><input name="q" value="<?=e($query)?>" placeholder="Поиск проекта"></label><select class="filter-select" name="status" onchange="this.form.submit()"><option value="all" <?=$status==='all'?'selected':''?>>Все статусы</option><option value="in_progress" <?=$status==='in_progress'?'selected':''?>>В работе</option><option value="review" <?=$status==='review'?'selected':''?>>На согласовании</option><option value="completed" <?=$status==='completed'?'selected':''?>>Завершён</option></select></form></div>
<div class="project-grid">
<?php foreach($projects as $p): $st=$p['status'];$label=['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'][$st]??$st;$cls=$st==='completed'?'status-done':($st==='review'?'status-review':'status-active');$progress=$p['item_count']?min(100,(int)$p['item_count']*12):0;?>
<a class="project-card" href="project.php?id=<?=$p['id']?>"><div class="card-top"><span class="status <?=$cls?>"><i></i><?=$label?></span><span class="text-muted small"><?=e($p['workType'])?></span></div><div class="project-info"><h3><?=e($p['name'])?></h3><span><?=e($p['city']?:'Город не указан')?></span><div class="client-line mt-3"><span class="mini-avatar"><?=e(mb_strtoupper(mb_substr($p['clientName']??'К',0,1)))?></span><?=e($p['clientName']?:'Заказчик не указан')?></div></div><div class="progress-meta"><span>Заполнено сметы</span><strong><?=$progress?>%</strong></div><div class="progress-track"><span style="width:<?=$progress?>%"></span></div><div class="card-footer"><span><i class="bi bi-calendar3"></i> <?=e($p['deadline']?date('d.m.Y',strtotime($p['deadline'])):'Срок не указан')?></span><strong><?=number_format((float)$p['total'],0,',',' ')?> ₽</strong></div></a>
<?php endforeach;?>
<a class="new-project-card" href="#" data-bs-toggle="modal" data-bs-target="#<?= $canCreate?'newProject':'subscriptionLimitModal' ?>"><span><i class="bi bi-<?= $canCreate?'plus-lg':'lock' ?>"></i></span><strong><?= $canCreate?'Создать новый проект':'Новые сметы — по подписке' ?></strong><small><?= $canCreate?'Добавьте объект и начните собирать смету':'Первая смета уже доступна бесплатно' ?></small></a>
</div>
<div class="modal fade" id="subscriptionLimitModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body p-4 p-lg-5 text-center"><div class="module-icon mx-auto mb-3"><i class="bi bi-lock"></i></div><div class="eyebrow mb-2">БЕСПЛАТНЫЙ ДОСТУП</div><h2 class="h4 fw-bold mb-2">Первая смета — бесплатно</h2><p class="text-muted small mb-4">Вы уже использовали бесплатную смету. Чтобы создать следующую, потребуется активная подписка.</p><div class="d-flex justify-content-center gap-2"><button type="button" class="outline-button" data-bs-dismiss="modal">Оставить как есть</button><a href="subscription.php" class="primary-button">Оформить подписку</a></div></div></div></div></div>
<div class="bottom-callout"><i class="bi bi-stars"></i><div><strong>Смета из файла</strong><p>Загрузите Excel/CSV и быстро перенесите позиции в проект.</p></div><a class="text-button" href="workspace.php?view=scan">Открыть импорт <i class="bi bi-arrow-right"></i></a></div>
</section>
<div class="modal fade" id="newProject" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Новый проект</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_project"><div class="form-grid"><div><label class="form-label">Название проекта</label><input class="form-control" name="name" required placeholder="Дом на Курортной"></div><div><label class="form-label">Город</label><input class="form-control" name="city" placeholder="Сочи"></div><div><label class="form-label">Заказчик</label><input class="form-control" name="clientName" placeholder="Иван Петров"></div><div><label class="form-label">Тип работ</label><input class="form-control" name="workType" value="Строительство"></div><div><label class="form-label">Срок сдачи</label><input class="form-control" type="date" name="deadline"></div></div></div><div class="modal-footer"><button type="button" class="outline-button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Создать проект</button></div></form></div></div></div>
<?php require __DIR__.'/includes/app_footer.php';?>