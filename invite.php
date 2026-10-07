<?php
declare(strict_types=1);

require __DIR__.'/config/bootstrap.php';

$token=trim((string)($_GET['token']??''));
if($token==='' || !preg_match('/^[a-f0-9]{64}$/i',$token)){
    http_response_code(404);
    exit('Приглашение не найдено.');
}

$q=$pdo->prepare("SELECT m.id,m.projectId,m.userId,m.role,m.joinedAt,m.invitedEmail,p.name AS projectName
    FROM projectmembers m
    INNER JOIN projects p ON p.id=m.projectId
    WHERE m.inviteToken=? AND m.role='client'
    LIMIT 1");
$q->execute([$token]);
$invite=$q->fetch();

if(!$invite){
    http_response_code(404);
    exit('Приглашение недействительно или уже использовано.');
}

if(!empty($invite['joinedAt']) && (int)$invite['userId']>0){
    if(current_user() && (int)current_user()['id']===(int)$invite['userId']){
        redirect('workspace.php?view=acceptance&id='.(int)$invite['projectId']);
    }
    http_response_code(410);
    exit('Это приглашение уже использовано.');
}

if(!current_user()){
    $_SESSION['pending_invite_token']=$token;
    $_SESSION['pending_invite_expires']=time()+900;
    redirect('login.php');
}

$user=current_user();
$mq=$pdo->prepare('SELECT id FROM projectmembers WHERE projectId=? AND userId=? LIMIT 1');
$mq->execute([(int)$invite['projectId'],(int)$user['id']]);
if($mq->fetchColumn()){
    http_response_code(409);
    exit('У этого аккаунта уже есть доступ к проекту.');
}

try{
    $pdo->beginTransaction();

    $lock=$pdo->prepare("SELECT id,userId,projectId,role,joinedAt FROM projectmembers WHERE id=? AND inviteToken=? AND role='client' LIMIT 1 FOR UPDATE");
    $lock->execute([(int)$invite['id'],$token]);
    $row=$lock->fetch();
    if(!$row || !empty($row['joinedAt']) || (int)$row['userId']>0){
        throw new RuntimeException('Приглашение уже использовано.');
    }

    $uq=$pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
    $uq->execute([(int)$user['id']]);
    $userEmail=(string)($uq->fetchColumn()?:'');

    $uq=$pdo->prepare('UPDATE projectmembers SET userId=?,invitedEmail=COALESCE(NULLIF(invitedEmail,\'\'),NULLIF(?,\'\')),joinedAt=CURRENT_TIMESTAMP WHERE id=? AND inviteToken=? AND userId IS NULL');
    $uq->execute([(int)$user['id'],$userEmail,(int)$invite['id'],$token]);

    $pdo->commit();
    unset($_SESSION['pending_invite_token'],$_SESSION['pending_invite_expires']);

    create_notification($pdo,(int)$user['id'],(int)$invite['projectId'],'acceptance','Вы подключены к проекту',(string)$invite['projectName'],'workspace.php?view=acceptance&id='.(int)$invite['projectId']);
    notify_project_users($pdo,(int)$invite['projectId'],(int)$user['id'],'team','Заказчик подключился к проекту',(string)($user['name']?:'Заказчик'),'workspace.php?view=team&id='.(int)$invite['projectId']);

    redirect('workspace.php?view=acceptance&id='.(int)$invite['projectId']);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    http_response_code(400);
    exit(e($e->getMessage()));
}
