<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $isAjaxPost=!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';

    try {
        check_csrf();

        $stageAction=(string)($_POST['stage_action']??'');
        if(in_array($stageAction,['add','update','delete','payment'],true)){
            $pid=(int)($_POST['project_id']??0);
            if($pid<=0 || !can_access_project($pdo,$user,$pid)){
                throw new RuntimeException('Нет доступа к проекту.');
            }

            if($stageAction==='payment'){
                if(!can_manage_project($pdo,$user,$pid)){
                    throw new RuntimeException('Нет доступа к оплате этапа.');
                }

                $sid=(int)($_POST['stage_id']??0);
                $paymentStatus=(string)($_POST['payment_status']??'paid');
                $paymentStatus=$paymentStatus==='paid'?'paid':'pending';

                $q=$pdo->prepare('SELECT id,title,paymentMilestone FROM scheduletasks WHERE id=? AND projectId=? LIMIT 1');
                $q->execute([$sid,$pid]);
                $stage=$q->fetch();

                if(!$stage){
                    throw new RuntimeException('Этап не найден.');
                }

                $amount=(float)$stage['paymentMilestone'];
                if($amount<=0){
                    throw new RuntimeException('У этапа не указана сумма оплаты.');
                }

                $q=$pdo->prepare("SELECT id FROM smetogram_payments WHERE projectId=? AND stageId=? AND type='stage' ORDER BY id DESC LIMIT 1");
                $q->execute([$pid,$sid]);
                $paymentId=(int)$q->fetchColumn();

                if($paymentId){
                    $q=$pdo->prepare('UPDATE smetogram_payments SET title=?,amount=?,status=?,paidAt=? WHERE id=? AND projectId=?');
                    $q->execute([
                        'Этап: '.$stage['title'],
                        $amount,
                        $paymentStatus,
                        $paymentStatus==='paid'?date('Y-m-d H:i:s'):null,
                        $paymentId,
                        $pid
                    ]);
                }else{
                    $q=$pdo->prepare('INSERT INTO smetogram_payments(projectId,userId,type,stageId,title,amount,status,paidAt) VALUES(?,?,?,?,?,?,?,?)');
                    $q->execute([
                        $pid,
                        (int)$user['id'],
                        'stage',
                        $sid,
                        'Этап: '.$stage['title'],
                        $amount,
                        $paymentStatus,
                        $paymentStatus==='paid'?date('Y-m-d H:i:s'):null
                    ]);
                    $paymentId=(int)$pdo->lastInsertId();
                }

                $q=$pdo->prepare('INSERT INTO smetogram_payment_events(paymentId,eventType,payloadJson) VALUES(?,?,?)');
                $q->execute([
                    $paymentId,
                    $paymentStatus==='paid'?'stage_paid':'stage_pending',
                    json_encode(['stageId'=>$sid,'amount'=>$amount],JSON_UNESCAPED_UNICODE)
                ]);

                $payload=[
                    'ok'=>true,
                    'status'=>$paymentStatus,
                    'message'=>$paymentStatus==='paid'
                        ?'Этап отмечен как оплаченный.'
                        :'Оплата этапа возвращена в ожидание.'
                ];
            }elseif($stageAction==='delete'){
                if(!can_manage_project($pdo,$user,$pid)){
                    throw new RuntimeException('Редактировать этапы может только владелец проекта.');
                }

                $sid=(int)($_POST['stage_id']??0);
                $q=$pdo->prepare('DELETE FROM scheduletasks WHERE id=? AND projectId=?');
                $q->execute([$sid,$pid]);

                if(!$q->rowCount()){
                    throw new RuntimeException('Этап не найден.');
                }

                $q=$pdo->prepare("DELETE FROM smetogram_payments WHERE projectId=? AND stageId=? AND type='stage'");
                $q->execute([$pid,$sid]);

                $payload=[
                    'ok'=>true,
                    'deleted'=>$sid,
                    'message'=>'Этап удалён.'
                ];
            }else{
                if(!can_manage_project($pdo,$user,$pid)){
                    throw new RuntimeException('Добавлять и редактировать этапы может только владелец проекта.');
                }

                $title=trim((string)($_POST['title']??''));
                if($title===''){
                    throw new RuntimeException('Введите название этапа.');
                }

                $starts=trim((string)($_POST['startsAt']??''));
                $ends=trim((string)($_POST['endsAt']??''));
                $status=(string)($_POST['status']??'planned');
                if(!in_array($status,['planned','in_progress','done','blocked'],true)){
                    $status='planned';
                }

                $payment=(float)str_replace(',','.',(string)($_POST['paymentMilestone']??'0'));
                if($payment<0){
                    $payment=0;
                }

                if($stageAction==='add'){
                    $q=$pdo->prepare('INSERT INTO scheduletasks(projectId,title,startsAt,endsAt,status,paymentMilestone) VALUES(?,?,?,?,?,?)');
                    $q->execute([
                        $pid,
                        $title,
                        $starts!==''?$starts.' 00:00:00':null,
                        $ends!==''?$ends.' 23:59:59':null,
                        $status,
                        $payment
                    ]);
                    $sid=(int)$pdo->lastInsertId();
                }else{
                    $sid=(int)($_POST['stage_id']??0);
                    $q=$pdo->prepare('UPDATE scheduletasks SET title=?,startsAt=?,endsAt=?,status=?,paymentMilestone=? WHERE id=? AND projectId=?');
                    $q->execute([
                        $title,
                        $starts!==''?$starts.' 00:00:00':null,
                        $ends!==''?$ends.' 23:59:59':null,
                        $status,
                        $payment,
                        $sid,
                        $pid
                    ]);

                    $q=$pdo->prepare('SELECT id FROM scheduletasks WHERE id=? AND projectId=? LIMIT 1');
                    $q->execute([$sid,$pid]);
                    if(!$q->fetchColumn()){
                        throw new RuntimeException('Этап не найден.');
                    }
                }

                $q=$pdo->prepare("SELECT id,status FROM smetogram_payments WHERE projectId=? AND stageId=? AND type='stage' ORDER BY id DESC LIMIT 1");
                $q->execute([$pid,$sid]);
                $existingPayment=$q->fetch();

                if($payment>0){
                    if($existingPayment){
                        $q=$pdo->prepare('UPDATE smetogram_payments SET title=?,amount=? WHERE id=? AND projectId=?');
                        $q->execute(['Этап: '.$title,$payment,(int)$existingPayment['id'],$pid]);
                    }else{
                        $q=$pdo->prepare('INSERT INTO smetogram_payments(projectId,userId,type,stageId,title,amount,status) VALUES(?,?,?,?,?,?,?)');
                        $q->execute([$pid,(int)$user['id'],'stage',$sid,'Этап: '.$title,$payment,'pending']);
                    }
                }elseif($existingPayment){
                    $q=$pdo->prepare('DELETE FROM smetogram_payments WHERE id=? AND projectId=?');
                    $q->execute([(int)$existingPayment['id'],$pid]);
                }

                $q=$pdo->prepare('SELECT id,title,startsAt,endsAt,status,paymentMilestone FROM scheduletasks WHERE id=? AND projectId=? LIMIT 1');
                $q->execute([$sid,$pid]);
                $item=$q->fetch();

                $q=$pdo->prepare("SELECT status FROM smetogram_payments WHERE projectId=? AND stageId=? AND type='stage' ORDER BY id DESC LIMIT 1");
                $q->execute([$pid,$sid]);
                $item['paymentStatus']=$q->fetchColumn()?:'none';

                $payload=[
                    'ok'=>true,
                    'item'=>$item,
                    'message'=>$stageAction==='add'?'Этап добавлен.':'Этап обновлён.'
                ];
            }

            if($isAjaxPost){
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($payload,JSON_UNESCAPED_UNICODE);
                exit;
            }

            redirect('dashboard.php');
        }

        if(($_POST['action']??'')==='create_project'){
            if(!can_create_project($pdo,$user)){
                $error='Бесплатный доступ позволяет создать только одну смету. Чтобы создавать новые сметы, оформите подписку.';
            }else{
                $name=trim($_POST['name']??'');
                $city=trim($_POST['city']??'');
                $client=trim($_POST['clientName']??'');
                $work=trim($_POST['workType']??'Строительство');
                $deadline=trim($_POST['deadline']??'');

                if($name===''){
                    $error='Введите название проекта.';
                }else{
                    $q=$pdo->prepare("INSERT INTO projects(ownerId,name,city,clientName,workType,status,estimateDate,deadline,budget) VALUES(?,?,?,?,?,?,?,?,?)");
                    $q->execute([$user['id'],$name,$city,$client,$work,'in_progress',date('Y-m-d'),$deadline!==''?$deadline:null,0]);
                    $pid=(int)$pdo->lastInsertId();

                    $q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");
                    $q->execute([$pid,'Общестроительные работы',1]);

                    redirect('project.php?id='.$pid);
                }
            }
        }
    }catch(Throwable $e){
        if($isAjaxPost){
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error=$e->getMessage();
    }
}

$projectSql="SELECT p.*,
COALESCE((SELECT SUM(i.quantity*i.price) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=p.id),0) total,
(SELECT COUNT(*) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=p.id) item_count,
(SELECT COUNT(*) FROM scheduletasks st WHERE st.projectId=p.id) stage_count,
(SELECT COUNT(*) FROM scheduletasks st WHERE st.projectId=p.id AND st.status='done') stage_done,
(SELECT st.title FROM scheduletasks st WHERE st.projectId=p.id AND st.status='in_progress' ORDER BY st.startsAt IS NULL,st.startsAt,st.id LIMIT 1) current_stage,
(SELECT st.title FROM scheduletasks st WHERE st.projectId=p.id AND st.status='planned' ORDER BY st.startsAt IS NULL,st.startsAt,st.id LIMIT 1) next_stage
FROM projects p";
if(is_admin($user)){
    $q=$pdo->query($projectSql." ORDER BY p.updatedAt DESC");
}else{
    $q=$pdo->prepare($projectSql." WHERE p.ownerId=? OR EXISTS(SELECT 1 FROM projectmembers pm WHERE pm.projectId=p.id AND pm.userId=?) ORDER BY p.updatedAt DESC");
    $q->execute([$user['id'],$user['id']]);
}
$all=$q->fetchAll();
if(isset($_GET['stages_for'])){ $sid=(int)$_GET['stages_for']; if($sid<=0||!can_access_project($pdo,$user,$sid)){http_response_code(403);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>'Нет доступа к проекту.'],JSON_UNESCAPED_UNICODE);exit;} $sq=$pdo->prepare('SELECT id,title,startsAt,endsAt,status,paymentMilestone FROM scheduletasks WHERE projectId=? ORDER BY startsAt IS NULL,startsAt,id');$sq->execute([$sid]);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'items'=>$sq->fetchAll()],JSON_UNESCAPED_UNICODE);exit; }

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
<?php foreach($projects as $p): $st=$p['status'];$label=['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'][$st]??$st;$cls=$st==='completed'?'status-done':($st==='review'?'status-review':'status-active');$progress=$p['item_count']?min(100,(int)$p['item_count']*12):0;$stageCount=(int)($p['stage_count']??0);$stageDone=(int)($p['stage_done']??0);$stageProgress=$stageCount>0?min(100,(int)round($stageDone/$stageCount*100)):0;$currentStage=$p['current_stage']?:($p['next_stage']?:'Этапы ещё не добавлены');?>
<div class="project-card" data-project-id="<?=$p['id']?>">
<div class="card-top"><span class="status <?=$cls?>"><i></i><?=$label?></span><span class="text-muted small"><?=e($p['workType'])?></span></div>
<div class="project-info"><h3><?=e($p['name'])?></h3><span><?=e($p['city']?:'Город не указан')?></span><div class="client-line mt-3"><span class="mini-avatar"><?=e(mb_strtoupper(mb_substr($p['clientName']??'К',0,1)))?></span><?=e($p['clientName']?:'Заказчик не указан')?></div></div>
<div class="project-stage"><div class="project-stage-head"><span><i class="bi bi-list-check"></i> Этапы</span><strong><?=$stageCount?($stageDone.' / '.$stageCount):'—'?></strong></div><div class="project-stage-title"><?=e($currentStage)?></div><div class="project-stage-track"><span style="width:<?=$stageProgress?>%"></span></div></div>
<div class="progress-meta"><span>Заполнено сметы</span><strong><?=$progress?>%</strong></div><div class="progress-track"><span style="width:<?=$progress?>%"></span></div><div class="card-footer"><span><i class="bi bi-calendar3"></i> <?=e($p['deadline']?date('d.m.Y',strtotime($p['deadline'])):'Срок не указан')?></span><strong><?=number_format((float)$p['total'],0,',',' ')?> ₽</strong></div><div class="project-card-actions"><a class="project-open-link" href="project.php?id=<?=$p['id']?>" aria-label="Открыть проект"><span>Открыть проект</span><i class="bi bi-arrow-up-right"></i></a><button type="button" class="stage-manage-button" data-stage-manage data-project-id="<?=$p['id']?>" data-project-name="<?=e($p['name'])?>"><i class="bi bi-list-check"></i><span>Этапы</span></button></div>
</div>
<?php endforeach;?>
<a class="new-project-card" href="#" data-bs-toggle="modal" data-bs-target="#<?= $canCreate?'newProject':'subscriptionLimitModal' ?>"><span><i class="bi bi-<?= $canCreate?'plus-lg':'lock' ?>"></i></span><strong><?= $canCreate?'Создать новый проект':'Новые сметы — по подписке' ?></strong><small><?= $canCreate?'Добавьте объект и начните собирать смету':'Первая смета уже доступна бесплатно' ?></small></a>
</div>
<div class="modal fade" id="subscriptionLimitModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body p-4 p-lg-5 text-center"><div class="module-icon mx-auto mb-3"><i class="bi bi-lock"></i></div><div class="eyebrow mb-2">БЕСПЛАТНЫЙ ДОСТУП</div><h2 class="h4 fw-bold mb-2">Первая смета — бесплатно</h2><p class="text-muted small mb-4">Вы уже использовали бесплатную смету. Чтобы создать следующую, потребуется активная подписка.</p><div class="d-flex justify-content-center gap-2"><button type="button" class="outline-button" data-bs-dismiss="modal">Оставить как есть</button><a href="subscription.php" class="primary-button">Оформить подписку</a></div></div></div></div></div>
<div class="modal fade stage-manager-modal" id="projectStageManager" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">ЭТАПЫ ОБЪЕКТА</div><h5 class="modal-title" id="stageManagerTitle">Этапы проекта</h5><p class="stage-manager-subtitle">Добавляйте, меняйте сроки, статус и сумму прямо из карточки.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><div class="modal-body"><div class="stage-manager-list" data-stage-list><div class="stage-manager-loading">Загружаем этапы…</div></div><form class="stage-manager-form" data-stage-form><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="project_id" data-stage-project><input type="hidden" name="stage_id" data-stage-id><input type="hidden" name="stage_action" value="add"><div class="stage-form-heading"><strong data-stage-form-title>Новый этап</strong><button type="button" class="stage-cancel-edit" data-stage-cancel hidden>Отмена редактирования</button></div><div class="stage-form-grid"><div class="stage-field stage-field-wide"><label>Название этапа</label><input name="title" data-stage-title required placeholder="Например: Черновые работы"></div><div class="stage-field"><label>Начало</label><input type="date" name="startsAt" data-stage-start></div><div class="stage-field"><label>Окончание</label><input type="date" name="endsAt" data-stage-end></div><div class="stage-field"><label>Статус</label><select name="status" data-stage-status><option value="planned">Запланировано</option><option value="in_progress">В работе</option><option value="done">Завершено</option><option value="blocked">Заблокировано</option></select></div><div class="stage-field"><label>Сумма этапа</label><input name="paymentMilestone" data-stage-payment inputmode="decimal" placeholder="0 ₽"></div></div><div class="stage-form-actions"><button type="submit" class="primary-button" data-stage-submit><i class="bi bi-plus-lg"></i> Добавить этап</button></div></form></div></div></div></div><div class="bottom-callout"><i class="bi bi-stars"></i><div><strong>Смета из файла</strong><p>Загрузите Excel/CSV и быстро перенесите позиции в проект.</p></div><a class="text-button" href="workspace.php?view=scan">Открыть импорт <i class="bi bi-arrow-right"></i></a></div>
</section>
<div class="modal fade" id="newProject" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Новый проект</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_project"><div class="form-grid"><div><label class="form-label">Название проекта</label><input class="form-control" name="name" required placeholder="Дом на Курортной"></div><div><label class="form-label">Город</label><input class="form-control" name="city" placeholder="Сочи"></div><div><label class="form-label">Заказчик</label><input class="form-control" name="clientName" placeholder="Иван Петров"></div><div><label class="form-label">Тип работ</label><input class="form-control" name="workType" value="Строительство"></div><div><label class="form-label">Срок сдачи</label><input class="form-control" type="date" name="deadline"></div></div></div><div class="modal-footer"><button type="button" class="outline-button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Создать проект</button></div></form></div></div></div>
<?php require __DIR__.'/includes/app_footer.php';?>