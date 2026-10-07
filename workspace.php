<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$user = require_auth();
$view = (string)($_GET['view'] ?? 'schedule');
$channel = (string)($_GET['channel'] ?? 'general');
$allowedChannels = ['team','foreman_client','designer_client','general'];
if (!in_array($channel, $allowedChannels, true)) $channel = 'general';
$projectId = (int)($_GET['id'] ?? 0);
$projectViews = ['schedule','team','documents','payments','chat','acceptance','scan','analytics','measurements'];

if ($projectId > 0) {
    // Запоминаем последний выбранный объект, чтобы нижнее меню
    // (Документы / Чат / Приёмка) открывало именно его.
    $_SESSION['last_project_id'] = $projectId;
} elseif (in_array($view, $projectViews, true)) {
    $projectId = (int)($_SESSION['last_project_id'] ?? 0);
    if ($projectId <= 0) redirect('dashboard.php');
}
$project = null;
$error = '';
$notice = '';
$projectsCount = 0;
if (is_admin($user)) {
    $projectsCount = (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
} else {
    $cq = $pdo->prepare('SELECT COUNT(DISTINCT p.id) FROM projects p LEFT JOIN projectmembers pm ON pm.projectId=p.id WHERE p.ownerId=? OR pm.userId=?');
    $cq->execute([(int)$user['id'], (int)$user['id']]);
    $projectsCount = (int)$cq->fetchColumn();
}

// POST/Redirect/GET: повторная загрузка страницы не повторяет INSERT.
if (isset($_SESSION['flash_notice'])) {
    $notice = (string)$_SESSION['flash_notice'];
    unset($_SESSION['flash_notice']);
}
if (isset($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($projectId > 0) {
    $q = $pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
    $q->execute([$projectId]);
    $project = $q->fetch();
    if (!$project || !can_access_project($pdo,$user,$projectId)) {
        redirect('dashboard.php');
    }
    $canManageProject = can_manage_project($pdo,$user,$projectId);
}

$notifyProject = function(string $type, string $title, string $body = '', ?string $url = null) use ($pdo, $projectId, $user): void {
    if ($projectId <= 0) return;
    notify_project_users($pdo, $projectId, (int)$user['id'], $type, $title, $body, $url);
};

/* Persistent account settings. */
$pdo->exec("CREATE TABLE IF NOT EXISTS smetogram_user_settings (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    project_notifications TINYINT(1) NOT NULL DEFAULT 1,
    message_notifications TINYINT(1) NOT NULL DEFAULT 1,
    document_notifications TINYINT(1) NOT NULL DEFAULT 1,
    payment_notifications TINYINT(1) NOT NULL DEFAULT 1,
    acceptance_notifications TINYINT(1) NOT NULL DEFAULT 1,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow',
    date_format VARCHAR(32) NOT NULL DEFAULT 'd.m.Y',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(timezone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->prepare("INSERT IGNORE INTO smetogram_user_settings (user_id) VALUES (?)")->execute([(int)$user['id']]);
$settingsQ = $pdo->prepare("SELECT * FROM smetogram_user_settings WHERE user_id=? LIMIT 1");
$settingsQ->execute([(int)$user['id']]);
$userSettings = $settingsQ->fetch() ?: [
    'project_notifications'=>1,'message_notifications'=>1,'document_notifications'=>1,
    'payment_notifications'=>1,'acceptance_notifications'=>1,
    'timezone'=>'Europe/Moscow','date_format'=>'d.m.Y'
];

/* Acceptance stage photos. */
$pdo->exec("CREATE TABLE IF NOT EXISTS smetogram_acceptance_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime VARCHAR(80) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(stage_id), INDEX(project_id), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* Lightweight chat polling endpoint — no page reload. */
if (isset($_GET['chat_poll']) && (string)$_GET['chat_poll'] === '1' && $projectId > 0) {
    $pollChannel = (string)($_GET['channel'] ?? $channel);
    if (!in_array($pollChannel, $allowedChannels, true)) $pollChannel = 'general';
    $afterId = max(0, (int)($_GET['after'] ?? 0));
    $q = $pdo->prepare('SELECT m.id,m.authorId,m.body,m.createdAt,u.name AS authorName FROM projectmessages m LEFT JOIN users u ON u.id=m.authorId WHERE m.projectId=? AND m.channel=? AND m.id>? ORDER BY m.id ASC LIMIT 100');
    $q->execute([$projectId,$pollChannel,$afterId]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'messages'=>$q->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxPost = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $ajaxChatPayload = null;
    $ajaxPhotoPayload = null;
    try {
        check_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'choose_plan') {
            $plan = (string)($_POST['plan'] ?? 'free');
            if (!in_array($plan, ['free','project','brigade','studio'], true)) throw new RuntimeException('Неизвестный тариф.');
            $expires = $plan === 'free' ? null : date('Y-m-d H:i:s', strtotime('+1 month'));
            $q = $pdo->prepare('UPDATE users SET subscriptionPlan=?,subscriptionStatus=?,subscriptionStartedAt=?,subscriptionExpiresAt=? WHERE id=?');
            $q->execute([$plan,'active',date('Y-m-d H:i:s'),$expires,$user['id']]);
            $notice = 'Тариф выбран.';
        } elseif ($action === 'add_payment') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $title=trim((string)($_POST['title']??'Платёж'));
            $amount=(float)str_replace(',', '.', (string)($_POST['amount']??'0'));
            if($title==='' || $amount<=0) throw new RuntimeException('Укажите назначение и сумму платежа.');
            $q=$pdo->prepare('INSERT INTO smetogram_payments(projectId,userId,type,title,amount,status) VALUES(?,?,?,?,?,?)');
            $q->execute([$projectId,$user['id'],'payment',$title,$amount,'pending']);
            $paymentId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO smetogram_payment_events(paymentId,eventType,payloadJson) VALUES(?,?,?)')->execute([$paymentId,'created',json_encode(['amount'=>$amount],JSON_UNESCAPED_UNICODE)]);
            $notice='Платёж добавлен.';
            $notifyProject('payment','Новый платёж',$title.' · '.number_format($amount,0,',',' ').' ₽','workspace.php?view=payments&id='.$projectId);
        } elseif ($action === 'mark_payment') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $paymentId=(int)($_POST['payment_id']??0);
            $q=$pdo->prepare('UPDATE smetogram_payments SET status=\'paid\',paidAt=CURRENT_TIMESTAMP WHERE id=? AND projectId=? AND userId=?');
            $q->execute([$paymentId,$projectId,$user['id']]);
            $notice='Платёж отмечен как оплаченный.';
            $notifyProject('payment','Платёж отмечен как оплаченный','Изменение платежа по проекту.','workspace.php?view=payments&id='.$projectId);
        } elseif ($action === 'upload_document') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $documentId=(int)($_POST['document_id']??0);
            $q=$pdo->prepare('SELECT * FROM projectdocuments WHERE id=? AND projectId=?'); $q->execute([$documentId,$projectId]); $doc=$q->fetch();
            if(!$doc) throw new RuntimeException('Документ не найден.');
            if(empty($_FILES['document_file']) || $_FILES['document_file']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Не удалось загрузить файл.');
            $file=$_FILES['document_file']; if((int)$file['size']>30*1024*1024) throw new RuntimeException('Максимальный размер файла — 30 МБ.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed=['application/pdf','image/jpeg','image/png','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            if(!in_array($mime,$allowed,true)) throw new RuntimeException('Формат файла не поддерживается.');
            $dir=__DIR__.'/uploads/documents/'.$projectId; if(!is_dir($dir)) mkdir($dir,0755,true);
            $stored=bin2hex(random_bytes(12)).'-'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename($file['name'])); $path=$dir.'/'.$stored;
            if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('Не удалось сохранить файл.');
            $rel='uploads/documents/'.$projectId.'/'.$stored;
            $q=$pdo->prepare('INSERT INTO smetogram_document_files(documentId,projectId,userId,originalName,storedName,mime,sizeBytes,path) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$documentId,$projectId,$user['id'],$file['name'],$stored,$mime,(int)$file['size'],$rel]);
            $pdo->prepare('INSERT INTO smetogram_document_events(documentId,userId,eventType,comment) VALUES(?,?,?,?)')->execute([$documentId,$user['id'],'file_uploaded',$file['name']]);
            $notice='Файл документа загружен.';
            $notifyProject('document','Загружен файл документа',(string)$file['name'],'document.php?id='.$documentId);
        } elseif ($action === 'add_task') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Введите название этапа.');
            }
            $starts = trim((string)($_POST['startsAt'] ?? ''));
            $ends = trim((string)($_POST['endsAt'] ?? ''));
            $payment = (float)str_replace(',', '.', (string)($_POST['paymentMilestone'] ?? '0'));

            $q = $pdo->prepare('INSERT INTO scheduletasks (projectId,title,startsAt,endsAt,status,paymentMilestone) VALUES (?,?,?,?,?,?)');
            $q->execute([$projectId, $title, $starts !== '' ? $starts . ' 00:00:00' : null, $ends !== '' ? $ends . ' 23:59:59' : null, 'planned', $payment]);
            $notice = 'Этап сохранён.';
            $notifyProject('schedule','Добавлен этап',$title,'workspace.php?view=schedule&id='.$projectId);
        } elseif ($action === 'add_member') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            if (empty($canManageProject)) throw new RuntimeException('Назначать сотрудников может только владелец проекта или главный администратор.');
            $assignedUserId = (int)($_POST['user_id'] ?? 0);
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $role = (string)($_POST['role'] ?? 'client');
            $roles = ['client', 'foreman', 'contractor', 'designer'];
            if (!in_array($role, $roles, true)) {
                $role = 'client';
            }
            if ($assignedUserId > 0) {
                $uq = $pdo->prepare('SELECT id,email,name,username FROM users WHERE id=? LIMIT 1');
                $uq->execute([$assignedUserId]);
                $assignedUser = $uq->fetch();
                if (!$assignedUser) throw new RuntimeException('Сотрудник не найден.');
                $dq = $pdo->prepare('SELECT id FROM projectmembers WHERE projectId=? AND userId=? LIMIT 1');
                $dq->execute([$projectId,$assignedUserId]);
                if ($dq->fetchColumn()) throw new RuntimeException('Этот сотрудник уже назначен на проект.');
                $q = $pdo->prepare('INSERT INTO projectmembers (projectId,userId,invitedEmail,role,inviteToken,joinedAt) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)');
                $q->execute([$projectId,$assignedUserId,$assignedUser['email'] ?: null,$role,bin2hex(random_bytes(12))]);
                $notice = 'Сотрудник назначен на проект.';
                $notifyProject('team','Назначен сотрудник',(string)($assignedUser['name'] ?: $assignedUser['username'] ?: $assignedUser['email'] ?: 'Сотрудник'),'workspace.php?view=team&id='.$projectId);
            } else {
                if ($email === '' && $phone === '') throw new RuntimeException('Укажите сотрудника из списка или email/телефон для приглашения.');
                $q = $pdo->prepare('INSERT INTO projectmembers (projectId,invitedEmail,invitedPhone,role,inviteToken) VALUES (?,?,?,?,?)');
                $q->execute([$projectId, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $role, bin2hex(random_bytes(12))]);
                $notice = 'Приглашение создано.';
                $notifyProject('team','Изменена команда проекта',$email !== '' ? $email : $phone,'workspace.php?view=team&id='.$projectId);
            }
        } elseif ($action === 'add_doc') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            $type = (string)($_POST['type'] ?? 'contract');
            if ($title === '') {
                throw new RuntimeException('Введите название документа.');
            }
            if (!in_array($type, ['contract', 'invoice', 'act'], true)) {
                $type = 'contract';
            }

            $q = $pdo->prepare('INSERT INTO projectdocuments (projectId,type,title,status) VALUES (?,?,?,?)');
            $q->execute([$projectId, $type, $title, 'draft']);
            $notice = 'Документ создан.';
            $documentId=(int)$pdo->lastInsertId();
            $notifyProject('document','Создан документ',$title,'document.php?id='.$documentId);
        } elseif ($action === 'send_message') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            $body = trim((string)($_POST['body'] ?? ''));
            $channel = (string)($_POST['channel'] ?? 'general');
            if ($body === '') {
                throw new RuntimeException('Введите сообщение.');
            }
            if (!in_array($channel, ['team', 'foreman_client', 'designer_client', 'general'], true)) {
                $channel = 'general';
            }

            $q = $pdo->prepare('INSERT INTO projectmessages (projectId,authorId,channel,body) VALUES (?,?,?,?)');
            $q->execute([$projectId, $user['id'], $channel, $body]);
            $messageId = (int)$pdo->lastInsertId();
            $mq = $pdo->prepare('SELECT m.id,m.authorId,m.body,m.createdAt,u.name AS authorName FROM projectmessages m LEFT JOIN users u ON u.id=m.authorId WHERE m.id=? AND m.projectId=? LIMIT 1');
            $mq->execute([$messageId,$projectId]);
            $ajaxChatPayload = $mq->fetch() ?: null;
            $notice = 'Сообщение отправлено.';
            $notifyProject('message','Новое сообщение','Новое сообщение в канале проекта.','workspace.php?view=chat&id='.$projectId.'&channel='.rawurlencode($channel));
        } elseif ($action === 'add_stage') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('Введите название этапа.');
            $amount = (float)str_replace([' ', ','], ['', '.'], (string)($_POST['amount'] ?? '0'));
            $holdback = (float)str_replace([' ', ','], ['', '.'], (string)($_POST['holdback'] ?? '0'));
            $scheduleTaskId = (int)($_POST['schedule_task_id'] ?? 0);
            $q = $pdo->prepare('INSERT INTO acceptancestages (projectId,scheduleTaskId,title,amount,holdback,status) VALUES (?,?,?,?,?,?)');
            $q->execute([$projectId, $scheduleTaskId ?: null, $title, $amount, $holdback, 'pending']);
            $notice = 'Этап приёмки создан.';
            $notifyProject('acceptance','Создан этап приёмки',$title,'workspace.php?view=acceptance&id='.$projectId);
        } elseif ($action === 'submit_stage') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            $stageId = (int)($_POST['stage_id'] ?? 0);
            $q = $pdo->prepare("UPDATE acceptancestages SET status='submitted',submittedAt=CURRENT_TIMESTAMP WHERE id=? AND projectId=?");
            $q->execute([$stageId, $projectId]);
            $notice = 'Этап отправлен на приёмку.';
            $notifyProject('acceptance','Этап отправлен на приёмку','Требуется проверка.','workspace.php?view=acceptance&id='.$projectId);
        } elseif ($action === 'upload_acceptance_photo') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $stageId=(int)($_POST['stage_id']??0);
            $sq=$pdo->prepare('SELECT id,title FROM acceptancestages WHERE id=? AND projectId=? LIMIT 1');
            $sq->execute([$stageId,$projectId]);
            $stage=$sq->fetch();
            if(!$stage) throw new RuntimeException('Этап приёмки не найден.');
            if(empty($_FILES['acceptance_photo']) || $_FILES['acceptance_photo']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Не удалось загрузить фотографию.');
            $file=$_FILES['acceptance_photo'];
            if((int)$file['size']>15*1024*1024) throw new RuntimeException('Максимальный размер фотографии — 15 МБ.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed=['image/jpeg','image/png','image/webp','image/heic','image/heif'];
            if(!in_array($mime,$allowed,true)) throw new RuntimeException('Можно загружать только фотографии JPG, PNG, WebP или HEIC.');
            $dir=__DIR__.'/uploads/acceptance-photos/'.$projectId.'/'.$stageId;
            if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку для фотографий.');
            $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION)); if($ext==='') $ext='jpg';
            $stored=bin2hex(random_bytes(16)).'.'.$ext; $path=$dir.'/'.$stored;
            if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('Не удалось сохранить фотографию.');
            $rel='uploads/acceptance-photos/'.$projectId.'/'.$stageId.'/'.$stored;
            $pdo->prepare('INSERT INTO smetogram_acceptance_photos(stage_id,project_id,user_id,original_name,stored_name,mime,size_bytes,path) VALUES(?,?,?,?,?,?,?,?)')->execute([$stageId,$projectId,$user['id'],(string)$file['name'],$stored,$mime,(int)$file['size'],$rel]);
            $photoId=(int)$pdo->lastInsertId();
            $ajaxPhotoPayload=['id'=>$photoId,'stageId'=>$stageId,'path'=>$rel,'originalName'=>(string)$file['name']];
            $notice='Фото приёмки добавлено.';
        } elseif ($action === 'delete_acceptance_photo') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $photoId=(int)($_POST['photo_id']??0);
            $q=$pdo->prepare('SELECT * FROM smetogram_acceptance_photos WHERE id=? AND project_id=? LIMIT 1'); $q->execute([$photoId,$projectId]); $photo=$q->fetch();
            if(!$photo) throw new RuntimeException('Фотография не найдена.');
            if(!is_admin($user) && (int)$photo['user_id']!==(int)$user['id'] && empty($canManageProject)) throw new RuntimeException('Недостаточно прав для удаления фотографии.');
            $filePath=__DIR__.'/'.$photo['path']; if(is_file($filePath)) @unlink($filePath);
            $pdo->prepare('DELETE FROM smetogram_acceptance_photos WHERE id=?')->execute([$photoId]);
            $notice='Фото удалено.';
        } elseif ($action === 'upload_room_photo') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $roomId=(int)($_POST['room_id']??0);
            $rq=$pdo->prepare('SELECT id,name FROM smetogram_rooms WHERE id=? AND project_id=? LIMIT 1');
            $rq->execute([$roomId,$projectId]);
            $room=$rq->fetch();
            if(!$room) throw new RuntimeException('Комната не найдена.');
            if(empty($_FILES['room_photo']) || $_FILES['room_photo']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Не удалось загрузить фотографию.');
            $file=$_FILES['room_photo'];
            if((int)$file['size']>15*1024*1024) throw new RuntimeException('Максимальный размер фотографии — 15 МБ.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed=['image/jpeg','image/png','image/webp','image/heic','image/heif'];
            if(!in_array($mime,$allowed,true)) throw new RuntimeException('Можно загружать только фотографии JPG, PNG, WebP или HEIC.');
            $dir=__DIR__.'/uploads/room-photos/'.$projectId.'/'.$roomId;
            if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку для фотографий.');
            $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
            if($ext==='') $ext=$mime==='image/png'?'png':'jpg';
            $stored=bin2hex(random_bytes(16)).'.'.$ext;
            $path=$dir.'/'.$stored;
            if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('Не удалось сохранить фотографию.');
            $rel='uploads/room-photos/'.$projectId.'/'.$roomId.'/'.$stored;
            $q=$pdo->prepare('INSERT INTO smetogram_room_photos(room_id,project_id,user_id,original_name,stored_name,mime,size_bytes,path) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$roomId,$projectId,$user['id'],(string)$file['name'],$stored,$mime,(int)$file['size'],$rel]);
            $notice='Фотография комнаты добавлена.';
        } elseif ($action === 'delete_room_photo') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $photoId=(int)($_POST['photo_id']??0);
            $q=$pdo->prepare('SELECT * FROM smetogram_room_photos WHERE id=? AND project_id=? LIMIT 1');
            $q->execute([$photoId,$projectId]);
            $photo=$q->fetch();
            if(!$photo) throw new RuntimeException('Фотография не найдена.');
            if(!is_admin($user) && (int)$photo['user_id']!==(int)$user['id'] && empty($canManageProject)) throw new RuntimeException('Недостаточно прав для удаления фотографии.');
            $filePath=__DIR__.'/'.$photo['path'];
            if(is_file($filePath)) @unlink($filePath);
            $pdo->prepare('DELETE FROM smetogram_room_photos WHERE id=?')->execute([$photoId]);
            $notice='Фотография удалена.';
        } elseif ($action === 'add_room') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            $name = trim((string)($_POST['name'] ?? 'Комната'));
            $length = (float)str_replace(',', '.', (string)($_POST['length'] ?? '0'));
            $width = (float)str_replace(',', '.', (string)($_POST['width'] ?? '0'));
            $height = (float)str_replace(',', '.', (string)($_POST['height'] ?? '0'));

            if ($length <= 0 || $width <= 0 || $height <= 0) {
                throw new RuntimeException('Укажите длину, ширину и высоту больше нуля.');
            }

            $q = $pdo->prepare('INSERT INTO smetogram_rooms (user_id,project_id,name,length_m,width_m,height_m) VALUES (?,?,?,?,?,?)');
            $q->execute([$user['id'], $projectId > 0 ? $projectId : null, $name !== '' ? $name : 'Комната', $length, $width, $height]);
            if ($projectId > 0) {
                try { refresh_measurement_estimate_items($pdo,$projectId); } catch(Throwable $ignore) {}
            }
            $notice = 'Замер сохранён. Связанные позиции сметы пересчитаны автоматически.';
        } elseif ($action === 'delete_room') {
            $roomId = (int)($_POST['room_id'] ?? 0);
            $q = $pdo->prepare('DELETE FROM smetogram_rooms WHERE id=? AND user_id=? AND project_id=?');
            $q->execute([$roomId, $user['id'], $projectId]);
            $notice = 'Замер удалён.';
        } elseif ($action === 'add_measurement_to_estimate') {
            if (!$project) throw new RuntimeException('Сначала откройте проект.');
            if (empty($canManageProject)) throw new RuntimeException('Добавлять позиции в смету может только владелец проекта или администратор.');
            $roomId = (int)($_POST['room_id'] ?? 0);
            $q = $pdo->prepare('SELECT * FROM smetogram_rooms WHERE id=? AND project_id=? LIMIT 1');
            $q->execute([$roomId, $projectId]);
            $room = $q->fetch();
            if (!$room) throw new RuntimeException('Замер помещения не найден.');

            $length = (float)$room['length_m'];
            $width = (float)$room['width_m'];
            $height = (float)$room['height_m'];
            $floor = round($length * $width, 3);
            $walls = round(2 * ($length + $width) * $height, 3);
            $ceiling = $floor;
            $perimeter = round(2 * ($length + $width), 3);
            $roomName = (string)$room['name'];

            $q = $pdo->prepare('SELECT id FROM estimatecategories WHERE projectId=? AND name=? LIMIT 1');
            $q->execute([$projectId, 'Замеры помещений']);
            $categoryId = (int)$q->fetchColumn();
            if (!$categoryId) {
                $q = $pdo->prepare('SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?');
                $q->execute([$projectId]);
                $sortOrder = (int)$q->fetchColumn();
                $q = $pdo->prepare('INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)');
                $q->execute([$projectId, 'Замеры помещений', $sortOrder]);
                $categoryId = (int)$pdo->lastInsertId();
            }

            $items = [
                ['Площадь пола — '.$roomName, $floor, 'м²'],
                ['Площадь стен — '.$roomName, $walls, 'м²'],
                ['Площадь потолка — '.$roomName, $ceiling, 'м²'],
                ['Периметр — '.$roomName, $perimeter, 'м.п.']
            ];
            $exists = $pdo->prepare('SELECT id FROM estimateitems WHERE categoryId=? AND name=? LIMIT 1');
            $insert = $pdo->prepare('INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)');
            $added = 0;
            foreach ($items as [$name,$quantity,$unit]) {
                $exists->execute([$categoryId, $name]);
                if ($exists->fetchColumn()) continue;
                $insert->execute([$categoryId,$name,$quantity,$unit,0,'measurement']);
                $added++;
            }
            $notice = $added > 0
                ? 'Замеры помещения добавлены в смету. Количество уже заполнено — осталось указать расценки.'
                : 'Эти замеры уже есть в смете.';
        } elseif ($action === 'save_notifications') {
            $q = $pdo->prepare('UPDATE smetogram_user_settings SET project_notifications=?,message_notifications=?,document_notifications=?,payment_notifications=?,acceptance_notifications=? WHERE user_id=?');
            $q->execute([
                isset($_POST['project_notifications']) ? 1 : 0,
                isset($_POST['message_notifications']) ? 1 : 0,
                isset($_POST['document_notifications']) ? 1 : 0,
                isset($_POST['payment_notifications']) ? 1 : 0,
                isset($_POST['acceptance_notifications']) ? 1 : 0,
                (int)$user['id']
            ]);
            $settingsQ = $pdo->prepare("SELECT * FROM smetogram_user_settings WHERE user_id=? LIMIT 1");
            $settingsQ->execute([(int)$user['id']]);
            $userSettings = $settingsQ->fetch() ?: $userSettings;
            $notice = 'Настройки уведомлений сохранены.';
        } elseif ($action === 'save_regional') {
            $allowedTimezones = ['Europe/Moscow','Europe/Paris','Europe/London','Asia/Yekaterinburg','Asia/Novosibirsk','Asia/Krasnoyarsk','Asia/Irkutsk','Asia/Vladivostok'];
            $allowedDateFormats = ['d.m.Y','d/m/Y','Y-m-d'];
            $timezone = (string)($_POST['timezone'] ?? 'Europe/Moscow');
            $dateFormat = (string)($_POST['date_format'] ?? 'd.m.Y');
            if (!in_array($timezone, $allowedTimezones, true)) $timezone = 'Europe/Moscow';
            if (!in_array($dateFormat, $allowedDateFormats, true)) $dateFormat = 'd.m.Y';
            $q = $pdo->prepare('UPDATE smetogram_user_settings SET timezone=?,date_format=? WHERE user_id=?');
            $q->execute([$timezone,$dateFormat,(int)$user['id']]);
            $settingsQ = $pdo->prepare("SELECT * FROM smetogram_user_settings WHERE user_id=? LIMIT 1");
            $settingsQ->execute([(int)$user['id']]);
            $userSettings = $settingsQ->fetch() ?: $userSettings;
            $notice = 'Региональные настройки сохранены.';
        } elseif ($action === 'save_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (mb_strlen($name) < 2) {
                throw new RuntimeException('Введите имя.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Введите корректный email.');
            }

            $q = $pdo->prepare('UPDATE users SET name=?,email=? WHERE id=?');
            $q->execute([$name, $email, $user['id']]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $user['name'] = $name;
            $user['email'] = $email;
            $notice = 'Профиль сохранён.';
        } elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if ($newPassword === '' || $confirmPassword === '') throw new RuntimeException('Заполните новый пароль и его подтверждение.');
            if (mb_strlen($newPassword) < 8) throw new RuntimeException('Новый пароль должен содержать минимум 8 символов.');
            if ($newPassword !== $confirmPassword) throw new RuntimeException('Пароли не совпадают.');

            $uq = $pdo->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
            $uq->execute([(int)$user['id']]);
            $storedHash = (string)$uq->fetchColumn();
            if ($storedHash !== '') {
                if ($currentPassword === '' || !password_verify($currentPassword, $storedHash)) {
                    throw new RuntimeException('Текущий пароль указан неверно.');
                }
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, (int)$user['id']]);
            $_SESSION['user']['password_hash'] = $newHash;
            $user['password_hash'] = $newHash;
            $notice = $storedHash !== '' ? 'Пароль успешно изменён.' : 'Пароль установлен. Теперь можно входить с ним.';
        } elseif ($action === 'import_csv') {
            if (!$project) {
                throw new RuntimeException('Сначала откройте проект.');
            }
            if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Не удалось загрузить CSV-файл.');
            }

            $fh = fopen($_FILES['csv']['tmp_name'], 'r');
            if ($fh === false) {
                throw new RuntimeException('Не удалось открыть файл.');
            }

            $header = fgetcsv($fh, 0, ';');
            if (!$header || count($header) < 2) {
                rewind($fh);
                $header = fgetcsv($fh, 0, ',');
            }
            if (!$header) {
                fclose($fh);
                throw new RuntimeException('Файл пустой или имеет неверный формат.');
            }

            $map = [];
            foreach ($header as $i => $h) {
                $map[mb_strtolower(trim((string)$h))] = $i;
            }

            $catName = trim((string)($_POST['category'] ?? 'Импорт из файла'));
            if ($catName === '') {
                $catName = 'Импорт из файла';
            }

            $q = $pdo->prepare('SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?');
            $q->execute([$projectId]);
            $sortOrder = (int)$q->fetchColumn();

            $q = $pdo->prepare('INSERT INTO estimatecategories (projectId,name,sortOrder) VALUES (?,?,?)');
            $q->execute([$projectId, $catName, $sortOrder]);
            $categoryId = (int)$pdo->lastInsertId();

            $insert = $pdo->prepare('INSERT INTO estimateitems (categoryId,name,quantity,unit,price,source) VALUES (?,?,?,?,?,?)');
            $count = 0;

            while (($row = fgetcsv($fh, 0, ';')) !== false) {
                if (count($row) < 2) {
                    continue;
                }
                $nameIndex = $map['наименование'] ?? 0;
                $qtyIndex = $map['количество'] ?? 1;
                $unitIndex = $map['ед.'] ?? ($map['единица'] ?? 2);
                $priceIndex = $map['цена'] ?? 3;

                $name = trim((string)($row[$nameIndex] ?? ''));
                $qty = (float)str_replace(',', '.', trim((string)($row[$qtyIndex] ?? '1')));
                $unit = trim((string)($row[$unitIndex] ?? 'шт.'));
                $price = (float)str_replace(',', '.', trim((string)($row[$priceIndex] ?? '0')));

                if ($name !== '') {
                    $insert->execute([$categoryId, $name, $qty, $unit !== '' ? $unit : 'шт.', $price, 'import']);
                    $count++;
                }
            }
            fclose($fh);
            $notice = 'Импортировано позиций: ' . $count . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($isAjaxPost && $action === 'send_message') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $error === '',
            'message' => $error !== '' ? $error : $notice,
            'chatMessage' => $error === '' ? $ajaxChatPayload : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($isAjaxPost && in_array($action, ['upload_room_photo', 'delete_room_photo', 'upload_acceptance_photo', 'delete_acceptance_photo'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $error === '',
            'message' => $error !== '' ? $error : $notice,
            'photo' => $error === '' ? $ajaxPhotoPayload : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($error === '') {
        if ($notice !== '') {
            $_SESSION['flash_notice'] = $notice;
        }
        $target = 'workspace.php?view=' . rawurlencode($view);
        if ($projectId > 0) {
            $target .= '&id=' . $projectId;
        }
        if ($view === 'chat') {
            $target .= '&channel=' . rawurlencode($channel);
        }
        redirect($target);
    }
}

$titleMap = [
    'schedule' => 'График работ',
    'team' => 'Команда',
    'documents' => 'Документы',
    'payments' => 'Оплаты',
    'chat' => 'Чат проекта',
    'acceptance' => 'Приёмка',
    'billing' => 'План использования',
    'scan' => 'Смета из файла',
    'analytics' => 'Графики и показатели',
    'settings' => 'Настройки',
    'measurements' => 'Замеры'
];

$pageTitle = $titleMap[$view] ?? 'Рабочее пространство';
require __DIR__ . '/includes/app_header.php';
?>
<section class="page-wrap">
    <div class="module-heading">
        <div>
            <div class="eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО</div>
            <h1><?= e($pageTitle) ?></h1>
            <p class="lede"><?= e($project ? ($project['name'] . ' · ' . $project['city'] . ' · ' . $project['clientName']) : 'Сметограм — рабочее пространство строительного проекта.') ?></p>
        </div>
        <div class="module-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
    </div>
    <?php if ($project || $view === 'settings'): ?>
    <?php if ($project): ?>
    <div class="workspace-project-line">
        <a href="dashboard.php" class="workspace-back"><i class="fa-solid fa-arrow-left"></i> Мои проекты <b class="workspace-project-count"><?= $projectsCount ?></b></a>
        <span class="workspace-project-name"><?= e($project['name']) ?></span>
        <span class="workspace-project-meta"><?= e(($project['city'] ?? '') . ' · ' . ($project['clientName'] ?? '')) ?></span>
    </div>
    <div class="workspace-tabs" aria-label="Разделы проекта">
        <a href="project.php?id=<?= $projectId ?>" class="workspace-estimate-tab"><i class="fa-solid fa-calculator"></i> Смета</a>
        <a href="workspace.php?view=schedule&id=<?= $projectId ?>" class="<?= $view==='schedule'?'active':'' ?>"><i class="fa-solid fa-calendar-days"></i> График</a>
        <a href="workspace.php?view=measurements&id=<?= $projectId ?>" class="<?= $view==='measurements'?'active':'' ?>"><i class="fa-solid fa-ruler-combined"></i> Замеры</a>
        <a href="workspace.php?view=team&id=<?= $projectId ?>" class="<?= $view==='team'?'active':'' ?>"><i class="bi bi-people"></i> Команда</a>
        <a href="workspace.php?view=chat&id=<?= $projectId ?>&channel=<?= e($channel) ?>" class="<?= $view==='chat'?'active':'' ?>"><i class="fa-solid fa-comments"></i> Чат</a>
        <a href="workspace.php?view=documents&id=<?= $projectId ?>" class="<?= $view==='documents'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Документы</a>
        <a href="workspace.php?view=payments&id=<?= $projectId ?>" class="<?= $view==='payments'?'active':'' ?>"><i class="fa-solid fa-wallet"></i> Оплаты</a>
        <a href="workspace.php?view=acceptance&id=<?= $projectId ?>" class="<?= $view==='acceptance'?'active':'' ?>"><i class="fa-solid fa-check-circle"></i> Приёмка</a>
        <a href="workspace.php?view=analytics&id=<?= $projectId ?>" class="<?= $view==='analytics'?'active':'' ?>"><i class="fa-solid fa-chart-column"></i> Аналитика</a>
    </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
        <div class="alert alert-success"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($view === 'schedule'): ?>
        <?php
        $tasks = [];
        if ($project) {
            $q = $pdo->prepare('SELECT * FROM scheduletasks WHERE projectId=? ORDER BY startsAt,id');
            $q->execute([$projectId]);
            $tasks = $q->fetchAll();
        }
        ?>
        <div class="module-grid">
            <div class="module-panel wide-panel">
                <div class="panel-heading">
                    <div><h2>Этапы объекта</h2><p>Сроки и вехи оплаты по проекту.</p></div>
                    <?php if ($project): ?><button class="primary-button" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="fa-solid fa-plus"></i> Добавить этап</button><?php endif; ?>
                </div>
                <?php if (!$project): ?>
                    <div class="empty-state">Откройте проект, чтобы вести график.</div>
                <?php elseif (!$tasks): ?>
                    <div class="empty-state">Добавьте первый этап.</div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $progress = $task['status'] === 'done' ? 100 : ($task['status'] === 'in_progress' ? 50 : 5);
                        $statusLabels = ['planned'=>'Запланировано','in_progress'=>'В работе','done'=>'Завершено','blocked'=>'Заблокировано'];
                        ?>
                        <div class="timeline-row">
                            <div class="timeline-dot <?= $task['status'] === 'done' ? 'done' : '' ?>"><i class="fa-solid fa-check"></i></div>
                            <div class="timeline-content">
                                <strong><?= e($task['title']) ?></strong>
                                <span><?= e($task['startsAt'] ? date('d.m.Y', strtotime($task['startsAt'])) : 'Без даты') ?> — <?= e($task['endsAt'] ? date('d.m.Y', strtotime($task['endsAt'])) : '') ?></span>
                                <div class="progress-track"><span style="width:<?= $progress ?>%"></span></div>
                            </div>
                            <b class="timeline-status"><?= e($statusLabels[$task['status']] ?? $task['status']) ?></b>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="module-panel">
                <h2>Ближайшая веха</h2>
                <p class="panel-copy">Платёжные вехи можно привязать к этапам.</p>
                <div class="milestone"><i class="fa-solid fa-calendar-check"></i><div><strong>Следующий этап</strong><span>Настройте даты и сумму</span></div></div>
            </div>
        </div>

        <?php if ($project): ?>
        <div class="modal fade" id="taskModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header"><h5>Новый этап</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_task">
                            <input class="form-control mb-3" name="title" required placeholder="Электромонтажные работы">
                            <div class="form-grid">
                                <input class="form-control" type="date" name="startsAt">
                                <input class="form-control" type="date" name="endsAt">
                                <input class="form-control" name="paymentMilestone" value="0" placeholder="Сумма этапа">
                            </div>
                        </div>
                        <div class="modal-footer"><button class="primary-button">Добавить</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($view === 'team'): ?>
        <?php
        $members = [];
        $assignableUsers = [];
        if ($project) {
            $q = $pdo->prepare('SELECT m.*,u.name AS userName,u.email AS userEmail,u.username AS userUsername FROM projectmembers m LEFT JOIN users u ON u.id=m.userId WHERE m.projectId=? ORDER BY m.id');
            $q->execute([$projectId]);
            $members = $q->fetchAll();
            if (!empty($canManageProject)) {
                $uq = $pdo->prepare('SELECT id,name,email,username FROM users WHERE id<>? ORDER BY name IS NULL,name,id');
                $uq->execute([(int)$project['ownerId']]);
                $assignableUsers = $uq->fetchAll();
            }
        }
        ?>
        <div class="module-grid">
            <div class="module-panel">
                <div class="panel-heading">
                    <div><h2>Участники</h2><p>Роли и контакты проекта.</p></div>
                    <?php if ($project && !empty($canManageProject)): ?><button class="primary-button" data-bs-toggle="modal" data-bs-target="#memberModal"><i class="fa-solid fa-user-plus"></i> Назначить сотрудника</button><?php endif; ?>
                </div>
                <?php if (!$members): ?>
                    <div class="empty-state">Участников пока нет.</div>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                        <div class="member-row">
                            <div class="member-avatar"><?= e(mb_strtoupper(mb_substr($member['role'], 0, 2))) ?></div>
                            <div><strong><?= e($member['userName'] ?: ($member['userUsername'] ? '@'.$member['userUsername'] : ($member['invitedEmail'] ?: ($member['invitedPhone'] ?: 'Участник')))) ?></strong><span><?= e(['owner'=>'Владелец','foreman'=>'Прораб','contractor'=>'Бригада','designer'=>'Дизайнер','client'=>'Заказчик'][$member['role']] ?? $member['role']) ?></span></div>
                            <em><?= e($member['joinedAt'] ? 'Активен' : 'Приглашён') ?></em>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="module-panel"><h2>Права доступа</h2><div class="role-list"><p><b>Прораб</b><br><small>Смета, сроки, команда и приёмка</small></p><p><b>Бригада</b><br><small>Этапы и фото</small></p><p><b>Заказчик</b><br><small>Ход работ и приёмка</small></p></div></div>
        </div>

        <?php if ($project): ?>
        <div class="modal fade" id="memberModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
            <div class="modal-header"><h5>Назначить сотрудника</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_member">
                <?php if ($assignableUsers): ?>
                <label class="form-label">Сотрудник с аккаунтом</label>
                <select class="form-select mb-3" name="user_id"><option value="0">Выбрать сотрудника…</option><?php foreach ($assignableUsers as $employee): ?><option value="<?=$employee['id']?>"><?=e($employee['name'] ?: ($employee['username'] ? '@'.$employee['username'] : ($employee['email'] ?: 'Пользователь #'.$employee['id'])))?></option><?php endforeach; ?></select>
                <div class="small text-muted mb-3">Или создайте приглашение для человека, у которого ещё нет аккаунта:</div>
                <?php endif; ?>
                <input class="form-control mb-3" type="email" name="email" placeholder="email@example.ru">
                <input class="form-control mb-3" name="phone" placeholder="+7 999 000-00-00">
                <select class="form-select" name="role"><option value="client">Заказчик</option><option value="foreman">Прораб</option><option value="contractor">Бригада</option><option value="designer">Дизайнер</option></select>
            </div>
            <div class="modal-footer"><button class="primary-button">Назначить сотрудника</button></div>
        </form></div></div></div>
        <?php endif; ?>

    <?php elseif ($view === 'documents'): ?>
        <?php
        $documents = [];
        if ($project) {
            $q = $pdo->prepare('SELECT * FROM projectdocuments WHERE projectId=? ORDER BY createdAt DESC,id DESC');
            $q->execute([$projectId]);
            $documents = $q->fetchAll();
        }
        ?>
        <div class="module-panel">
            <div class="panel-heading"><div><h2>Документы проекта</h2><p>Договоры, счета и акты.</p></div><?php if ($project): ?><button class="primary-button" data-bs-toggle="modal" data-bs-target="#docModal"><i class="fa-solid fa-plus"></i> Создать документ</button><?php endif; ?></div>
            <?php if (!$documents): ?><div class="empty-state">Документов пока нет.</div><?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $fq=$pdo->prepare('SELECT * FROM smetogram_document_files WHERE documentId=? AND projectId=? ORDER BY createdAt DESC,id DESC');
                    $fq->execute([(int)$doc['id'],$projectId]);
                    $docFiles=$fq->fetchAll();
                    $latestFile=$docFiles[0]??null;
                    ?>
                    <div class="document-row">
                        <div class="member-avatar"><i class="fa-solid fa-file-lines"></i></div>
                        <div><strong><?= e($doc['title']) ?></strong><span><?= e(mb_strtoupper($doc['type'])) ?> · <?= count($docFiles) ?> файл(ов)</span></div>
                        <em><?= e($doc['status']) ?></em>
                        <a class="outline-button" href="document.php?id=<?= (int)$doc['id'] ?>"><i class="fa-solid fa-eye"></i> Открыть</a>
                        <?php if ($latestFile): ?><a class="icon-button subtle" target="_blank" href="document_file.php?id=<?= (int)$latestFile['id'] ?>" title="Открыть файл"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($project): ?>
        <div class="modal fade" id="docModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
            <div class="modal-header"><h5>Новый документ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_doc"><input class="form-control mb-3" name="title" required placeholder="Договор подряда"><select class="form-select" name="type"><option value="contract">Договор</option><option value="invoice">Счёт</option><option value="act">Акт</option></select></div>
            <div class="modal-footer"><button class="primary-button">Создать</button></div>
        </form></div></div></div>
        <?php endif; ?>

    <?php elseif ($view === 'payments'): ?>
        <?php
        /*
         * Оплаты строятся напрямую из сметы:
         * каждый раздел сметы с суммой становится этапом проекта один раз.
         * После импорта сумму этапа можно вручную подогнать — повторный вход
         * в раздел «Оплаты» её не перезаписывает.
         */
        if($project){
            $cq=$pdo->prepare("SELECT c.id,c.name,COALESCE(SUM(i.quantity*i.price),0) total
                FROM estimatecategories c
                LEFT JOIN estimateitems i ON i.categoryId=c.id
                WHERE c.projectId=?
                GROUP BY c.id
                ORDER BY c.sortOrder,c.id");
            $cq->execute([$projectId]);
            $estimateStages=$cq->fetchAll();
            $existing=$pdo->prepare("SELECT id FROM scheduletasks WHERE projectId=? AND estimateCategoryId=? LIMIT 1");
            $ins=$pdo->prepare("INSERT INTO scheduletasks(projectId,title,status,paymentMilestone,estimateCategoryId) VALUES(?,?,?,?,?)");
            foreach($estimateStages as $es){
                $total=(float)$es['total'];
                if($total<=0) continue;
                $existing->execute([$projectId,(int)$es['id']]);
                if(!$existing->fetchColumn()){
                    $ins->execute([$projectId,(string)$es['name'],'planned',$total,(int)$es['id']]);
                }
            }
        }

        $stagePayments=[];$payments=[];$paid=0;$pending=0;$totalStageAmount=0;
        if($project){
            $q=$pdo->prepare("SELECT st.id,st.title,st.paymentMilestone,st.estimateCategoryId,
                COALESCE((SELECT SUM(i.quantity*i.price) FROM estimateitems i WHERE i.categoryId=st.estimateCategoryId),0) AS estimateTotal,
                COALESCE((SELECT sp.paidAmount FROM smetogram_payments sp WHERE sp.projectId=st.projectId AND sp.stageId=st.id AND sp.type='stage' ORDER BY sp.id DESC LIMIT 1),0) AS paidAmount,
                COALESCE((SELECT sp.status FROM smetogram_payments sp WHERE sp.projectId=st.projectId AND sp.stageId=st.id AND sp.type='stage' ORDER BY sp.id DESC LIMIT 1),'pending') AS paymentStatus
                FROM scheduletasks st
                WHERE st.projectId=? AND COALESCE(st.paymentMilestone,0)>0
                ORDER BY st.estimateCategoryId IS NULL,st.id");
            $q->execute([$projectId]);$stagePayments=$q->fetchAll();
            foreach($stagePayments as $stagePay){
                $total=(float)$stagePay['paymentMilestone'];
                $stagePaid=min($total,max(0,(float)$stagePay['paidAmount']));
                $totalStageAmount+=$total;$paid+=$stagePaid;$pending+=max(0,$total-$stagePaid);
            }
            $q=$pdo->prepare("SELECT sp.* FROM smetogram_payments sp WHERE sp.projectId=? AND (sp.stageId IS NULL OR sp.stageId=0) ORDER BY sp.id DESC");
            $q->execute([$projectId]);$payments=$q->fetchAll();
            foreach($payments as $pay){
                if($pay['status']==='paid') $paid+=(float)$pay['amount']; else $pending+=(float)$pay['amount'];
            }
        }
        ?>
        <div class="module-grid">
            <div class="module-panel payments-main-panel">
                <div class="panel-heading">
                    <div>
                        <div class="eyebrow">СМЕТА → ОПЛАТЫ</div>
                        <h2>Оплаты проекта</h2>
                        <p>Этапы автоматически взяты из разделов сметы. Сумму каждого этапа можно подогнать под договор и затем вносить авансы.</p>
                    </div>
                    <?php if($project): ?><button class="outline-button" type="button" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="fa-solid fa-plus"></i> Другой платёж</button><?php endif; ?>
                </div>

                <div class="metric-grid payment-metrics">
                    <div class="metric-card"><span>Всего по этапам</span><strong><?=number_format($totalStageAmount,0,',',' ')?> ₽</strong><small>после корректировок</small></div>
                    <div class="metric-card"><span>Получено</span><strong><?=number_format($paid,0,',',' ')?> ₽</strong><small>включая авансы</small></div>
                    <div class="metric-card"><span>Осталось</span><strong><?=number_format($pending,0,',',' ')?> ₽</strong><small>к получению</small></div>
                </div>

                <?php if($stagePayments): ?>
                    <div class="payment-stage-list">
                    <?php foreach($stagePayments as $stagePay):
                        $total=(float)$stagePay['paymentMilestone'];
                        $estimateTotal=max(0,(float)$stagePay['estimateTotal']);
                        $stagePaid=min($total,max(0,(float)$stagePay['paidAmount']));
                        $remaining=max(0,$total-$stagePaid);
                        $percent=$total>0?min(100,round($stagePaid/$total*100)):0;
                        $estimateChanged=abs($estimateTotal-$total)>0.009;
                    ?>
                        <article class="payment-stage-card" data-payment-stage-card="<?=$stagePay['id']?>">
                            <div class="payment-stage-card-head">
                                <div class="payment-stage-icon"><i class="fa-solid fa-list-check"></i></div>
                                <div class="payment-stage-title-wrap">
                                    <strong><?=e($stagePay['title'])?></strong>
                                    <span><?= $stagePay['estimateCategoryId'] ? 'Из сметы' : 'Добавлен вручную' ?></span>
                                </div>
                                <em><?= $remaining<=0 ? 'Оплачено' : ($stagePaid>0 ? 'Аванс' : 'Не оплачено') ?></em>
                            </div>
                            <div class="payment-stage-edit-grid">
                                <label><span>Согласованная сумма этапа</span><div class="payment-input-wrap"><input type="text" inputmode="decimal" value="<?=number_format($total,0,',',' ')?>" data-stage-amount="<?=$stagePay['id']?>"><b>₽</b></div>
                                <?php if($stagePay['estimateCategoryId']): ?><small class="payment-stage-estimate <?= $estimateChanged ? 'is-changed' : '' ?>">По текущей смете: <b><?=number_format($estimateTotal,0,',',' ')?> ₽</b><?php if($estimateChanged): ?> · сумма сметы изменилась<?php endif; ?></small><?php endif; ?></label>
                                <div class="payment-stage-stat"><span>Получено</span><strong data-stage-paid="<?=$stagePay['id']?>"><?=number_format($stagePaid,0,',',' ')?> ₽</strong></div>
                                <div class="payment-stage-stat"><span>Осталось</span><strong data-stage-remaining="<?=$stagePay['id']?>"><?=number_format($remaining,0,',',' ')?> ₽</strong></div>
                                <label><span>Аванс / платёж</span><div class="payment-input-wrap"><input type="text" inputmode="decimal" placeholder="150 000" data-stage-payment-input="<?=$stagePay['id']?>"><b>₽</b></div></label>
                            </div>
                            <div class="payment-stage-progress"><span data-stage-progress="<?=$stagePay['id']?>" style="width:<?=$percent?>%"></span></div>
                            <div class="payment-stage-actions">
                                <button type="button" class="outline-button" data-stage-save-amount="<?=$stagePay['id']?>"><i class="fa-solid fa-pen"></i> Сохранить сумму</button>
                                <?php if($remaining>0): ?><button type="button" class="primary-button" data-stage-payment="<?=$stagePay['id']?>"><i class="fa-solid fa-circle-plus"></i> Внести оплату</button><?php else: ?><span class="payment-complete-note"><i class="fa-solid fa-check-circle"></i> Этап оплачен полностью</span><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fa-solid fa-receipt fs-2"></i><h3 class="mt-3">В смете пока нет этапов с суммой</h3><p>Добавьте разделы и работы в смету — они автоматически появятся здесь как этапы.</p><a class="primary-button" href="project.php?id=<?=$projectId?>">Открыть смету</a></div>
                <?php endif; ?>

                <?php if($payments): ?>
                    <div class="payment-manual-list"><h3>Другие платежи</h3>
                    <?php foreach($payments as $pay): ?>
                        <div class="document-row"><div class="member-avatar"><i class="fa-solid fa-credit-card"></i></div><div><strong><?=e($pay['title'])?></strong><span><?=number_format((float)$pay['amount'],0,',',' ')?> ₽</span></div><em><?= $pay['status']==='paid' ? 'Оплачено' : 'Ожидает оплаты' ?></em>
                        <?php if($pay['status']==='pending'): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="mark_payment"><input type="hidden" name="payment_id" value="<?= (int)$pay['id']?>"><button class="outline-button">Оплачено</button></form><?php endif; ?></div>
                    <?php endforeach; ?></div>
                <?php endif; ?>
            </div>

            <div class="module-panel">
                <div class="eyebrow">ФИНАНСЫ</div>
                <h2>Как это работает</h2>
                <div class="generated-row"><i class="fa-solid fa-1"></i><span><b>Смета</b> — разделы автоматически становятся этапами.</span></div>
                <div class="generated-row"><i class="fa-solid fa-2"></i><span><b>Сумма</b> — при необходимости подгоняете под договор.</span></div>
                <div class="generated-row"><i class="fa-solid fa-3"></i><span><b>Аванс</b> — например 150 000 из 500 000 ₽.</span></div>
                <div class="generated-row"><i class="fa-solid fa-4"></i><span><b>Остаток</b> — Сметограм считает автоматически.</span></div>
            </div>
        </div>
        <?php if($project): ?><div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Другой платёж</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_payment"><input class="form-control mb-3" name="title" required placeholder="Например: Дополнительные работы"><input class="form-control" name="amount" required placeholder="150000"></div><div class="modal-footer"><button class="primary-button">Сохранить</button></div></form></div></div></div><?php endif; ?>

    <?php elseif ($view === 'chat'): ?>
        <?php
        $messages = [];
        if ($project) {
            $q = $pdo->prepare('SELECT m.*,u.name AS authorName FROM projectmessages m LEFT JOIN users u ON u.id=m.authorId WHERE m.projectId=? AND m.channel=? ORDER BY m.createdAt ASC,m.id ASC');
            $q->execute([$projectId,$channel]);
            $messages = $q->fetchAll();
        }
        ?>
        <div class="module-panel chat-panel chat-app" data-chat-root data-project-id="<?= (int)$projectId ?>" data-user-id="<?= (int)$user['id'] ?>" data-channel="<?= e($channel) ?>" data-last-id="<?= (int)($messages ? end($messages)['id'] : 0) ?>">
            <div class="chat-tabs">
                <?php foreach (['foreman_client'=>'Прораб — заказчик','team'=>'Бригада','designer_client'=>'Дизайнер — заказчик','general'=>'Общий'] as $channelKey => $channelName): ?>
                    <a href="workspace.php?view=chat&id=<?= $projectId ?>&channel=<?= rawurlencode($channelKey) ?>" class="<?= $channel === $channelKey ? 'active' : '' ?>"><?= e($channelName) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="chat-header"><div><strong>Чат проекта</strong><span data-chat-status>В сети</span></div><i class="fa-solid fa-ellipsis"></i></div><div class="chat-messages" data-chat-messages>
                <?php if (!$messages): ?><div class="empty-state">Сообщений пока нет.</div><?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="chat-message <?= (int)$message['authorId'] === (int)$user['id'] ? 'mine' : '' ?>">
                            <div class="member-avatar"><?= e(mb_strtoupper(mb_substr($message['authorName'] ?: 'Вы', 0, 2))) ?></div>
                            <div><strong><?= e($message['authorName'] ?: 'Пользователь') ?></strong><p><?= nl2br(e($message['body'])) ?></p><small><?= e($message['createdAt']) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($project): ?>
            <form class="chat-compose" data-chat-form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="channel" value="<?= e($channel) ?>"><textarea name="body" required rows="1" data-chat-input placeholder="Напишите сообщение..."></textarea><button class="primary-button" data-chat-send type="submit"><i class="fa-solid fa-paper-plane"></i></button></form>
            <?php endif; ?>
        </div>

    <?php elseif ($view === 'acceptance'): ?>
        <?php
        $stages = [];
        $photosByStage = [];
        if ($project) {
            // Приёмка наследует этапы проекта. Этапы, созданные из разделов сметы,
            // сначала попадают в график, а затем автоматически связываются с приёмкой.
            $cq=$pdo->prepare("SELECT c.id,c.name,COALESCE(SUM(i.quantity*i.price),0) total
                FROM estimatecategories c
                LEFT JOIN estimateitems i ON i.categoryId=c.id
                WHERE c.projectId=?
                GROUP BY c.id
                ORDER BY c.sortOrder,c.id");
            $cq->execute([$projectId]);
            $estimateStages=$cq->fetchAll();

            $existing=$pdo->prepare('SELECT id FROM scheduletasks WHERE projectId=? AND estimateCategoryId=? LIMIT 1');
            $ins=$pdo->prepare('INSERT INTO scheduletasks(projectId,title,status,paymentMilestone,estimateCategoryId) VALUES(?,?,?,?,?)');
            foreach($estimateStages as $es){
                // Для приёмки нужен каждый раздел сметы, даже если его текущая сумма
                // равна 0 (например, позиции ещё без количества). Такие этапы должны
                // появляться в приёмке и ждать заполнения/актуализации суммы.
                $total=(float)$es['total'];
                $existing->execute([$projectId,(int)$es['id']]);
                if(!$existing->fetchColumn()){
                    $ins->execute([$projectId,(string)$es['name'],'planned',$total,(int)$es['id']]);
                }
            }

            $sync=$pdo->prepare('SELECT id,title,paymentMilestone,estimateCategoryId FROM scheduletasks WHERE projectId=? ORDER BY startsAt,id');
            $sync->execute([$projectId]);
            foreach($sync->fetchAll() as $task){
                $chk=$pdo->prepare('SELECT id FROM acceptancestages WHERE projectId=? AND scheduleTaskId=? LIMIT 1');
                $chk->execute([$projectId,(int)$task['id']]);
                if(!$chk->fetchColumn()){
                    $amount=(float)$task['paymentMilestone'];
                    $pdo->prepare('INSERT INTO acceptancestages(projectId,scheduleTaskId,title,amount,status) VALUES(?,?,?,?,?)')
                        ->execute([$projectId,(int)$task['id'],$task['title'],$amount,'pending']);
                }
            }

            $q=$pdo->prepare('SELECT * FROM acceptancestages WHERE projectId=? ORDER BY id ASC');
            $q->execute([$projectId]);
            $stages=$q->fetchAll();

            $pq=$pdo->prepare('SELECT * FROM smetogram_acceptance_photos WHERE project_id=? ORDER BY id DESC');
            $pq->execute([$projectId]);
            foreach($pq->fetchAll() as $photo) $photosByStage[(int)$photo['stage_id']][]=$photo;
        }

        $acceptanceStatusLabels=[
            'pending'=>'Ожидает сдачи',
            'submitted'=>'На проверке',
            'accepted'=>'Принят',
            'rejected'=>'Есть замечания'
        ];
        ?>
        <div class="module-grid">
            <div class="module-panel wide-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Приёмка по этапам</h2>
                        <p>Этапы из сметы и графика подгружаются автоматически. Для каждого этапа можно сохранить фото выполненных работ.</p>
                    </div>
                    <?php if($project): ?>
                        <button class="primary-button" type="button" data-bs-toggle="modal" data-bs-target="#acceptanceStageModal"><i class="fa-solid fa-plus"></i> Этап</button>
                    <?php endif; ?>
                </div>

                <?php if (!$stages): ?>
                    <div class="empty-state">Этапов проекта пока нет. Добавьте позиции в смету или этап в графике.</div>
                <?php else: ?>
                    <div class="acceptance-stage-list">
                    <?php foreach ($stages as $stage):
                        $photos=$photosByStage[(int)$stage['id']]??[];
                        $status=(string)$stage['status'];
                        $statusLabel=$acceptanceStatusLabels[$status]??$status;
                        $isLinked=(int)($stage['scheduleTaskId']??0)>0;
                    ?>
                        <article class="acceptance-stage-card">
                            <div class="acceptance-stage-head">
                                <div class="member-avatar"><i class="fa-solid fa-clipboard-check"></i></div>
                                <div>
                                    <strong><?=e($stage['title'])?></strong>
                                    <span><?=number_format((float)$stage['amount'],0,',',' ')?> ₽ · <?= $isLinked ? 'этап проекта' : 'добавлен вручную' ?></span>
                                </div>
                                <em><?=e($statusLabel)?></em>
                            </div>

                            <?php if(!empty($stage['comment'])): ?>
                                <div class="acceptance-stage-comment"><?=nl2br(e($stage['comment']))?></div>
                            <?php endif; ?>

                            <div class="acceptance-photo-actions">
                                <form method="post" enctype="multipart/form-data" class="acceptance-photo-upload">
                                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                    <input type="hidden" name="action" value="upload_acceptance_photo">
                                    <input type="hidden" name="stage_id" value="<?= (int)$stage['id'] ?>">
                                    <label class="outline-button"><i class="fa-solid fa-camera"></i> Сфотографировать<input hidden type="file" name="acceptance_photo" accept="image/*" capture="environment"></label>
                                </form>
                                <form method="post" enctype="multipart/form-data" class="acceptance-photo-upload">
                                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                    <input type="hidden" name="action" value="upload_acceptance_photo">
                                    <input type="hidden" name="stage_id" value="<?= (int)$stage['id'] ?>">
                                    <label class="outline-button"><i class="fa-solid fa-images"></i> Из галереи<input hidden type="file" name="acceptance_photo" accept="image/*"></label>
                                </form>
                            </div>

                            <?php if($photos): ?>
                                <div class="acceptance-photo-grid">
                                    <?php foreach($photos as $photo): ?>
                                        <div class="acceptance-photo-thumb">
                                            <a href="<?=e($photo['path'])?>" data-fancybox="acceptance-<?=$stage['id']?>">
                                                <img src="<?=e($photo['path'])?>" alt="<?=e($photo['original_name'])?>" loading="lazy">
                                            </a>
                                            <form method="post" class="acceptance-photo-delete">
                                                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                                <input type="hidden" name="action" value="delete_acceptance_photo">
                                                <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                                                <button type="submit" class="icon-button" title="Удалить"><i class="fa-solid fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="acceptance-photo-empty"><i class="fa-solid fa-camera"></i> Фото пока нет</div>
                            <?php endif; ?>

                            <?php if($status==='pending'): ?>
                                <form method="post" class="mt-3">
                                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                    <input type="hidden" name="action" value="submit_stage">
                                    <input type="hidden" name="stage_id" value="<?= (int)$stage['id'] ?>">
                                    <button class="primary-button" type="submit"><i class="fa-solid fa-paper-plane"></i> Сдать этап</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="module-panel">
                <i class="fa-solid fa-shield-check fs-4 text-primary"></i>
                <h2 class="mt-3">Контроль приёмки</h2>
                <p class="panel-copy">Фотографии хранятся отдельно у каждого этапа. На телефоне кнопка «Сфотографировать» сразу открывает камеру, а «Из галереи» — выбор существующих фото.</p>
            </div>
        </div>

        <?php if($project): ?>
        <div class="modal fade" id="acceptanceStageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Новый этап приёмки</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="add_stage">
                            <input class="form-control mb-3" name="title" required placeholder="Дополнительный этап">
                            <div class="form-grid">
                                <input class="form-control" name="amount" value="0" inputmode="decimal" placeholder="Сумма">
                                <input class="form-control" name="holdback" value="0" inputmode="decimal" placeholder="Удержание">
                            </div>
                            <input type="hidden" name="schedule_task_id" value="0">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="outline-button" data-bs-dismiss="modal">Отмена</button>
                            <button class="primary-button" type="submit"><i class="fa-solid fa-plus"></i> Создать этап</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

<?php elseif ($view === 'scan'): ?>
        <div class="module-grid">
            <div class="module-panel upload-panel">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="import_csv">
                    <label class="upload-zone"><div class="upload-icon"><i class="fa-solid fa-file-excel"></i></div><h2>Загрузите смету из CSV</h2><p>Колонки: Наименование; Количество; Ед.; Цена</p><span class="outline-button">Выбрать файл</span><input hidden type="file" name="csv" accept=".csv,.txt" required></label>
                    <?php if ($project): ?><div class="mt-3 d-flex gap-2"><input class="form-control" name="category" value="Импорт из файла" placeholder="Раздел"><button class="primary-button" type="submit">Импортировать</button></div><?php else: ?><div class="alert alert-info mt-3">Откройте проект и перейдите сюда для импорта.</div><?php endif; ?>
                </form>
            </div>
            <div class="module-panel"><i class="fa-solid fa-wand-magic-sparkles fs-4 text-primary"></i><h2 class="mt-3">Смета из файла</h2><p class="panel-copy">Импорт переносит позиции в выбранный раздел.</p><div class="generated-row"><i class="fa-solid fa-check-circle"></i> CSV / Excel-совместимый формат</div><div class="generated-row"><i class="fa-solid fa-check-circle"></i> Количество, единица и цена</div><div class="generated-row"><i class="fa-solid fa-check-circle"></i> Источник позиции сохраняется</div></div>
        </div>

    <?php elseif ($view === 'analytics'): ?>
        <?php
        $total = 0.0;
        $count = 0;
        if ($project) {
            $q = $pdo->prepare('SELECT COALESCE(SUM(i.quantity*i.price),0) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=?');
            $q->execute([$projectId]);
            $total = (float)$q->fetchColumn();
            $q = $pdo->prepare('SELECT COUNT(*) FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=?');
            $q->execute([$projectId]);
            $count = (int)$q->fetchColumn();
        }
        ?>
        <div class="module-grid"><div class="module-panel"><div class="panel-heading"><div><h2>Показатели проекта</h2><p>Данные считаются напрямую из сметы.</p></div></div><div class="metric-grid">
            <div class="metric-card"><div><span>Стоимость</span><strong><?= number_format($total,0,',',' ') ?> ₽</strong><small>текущий итог</small></div></div>
            <div class="metric-card"><div><span>Позиции</span><strong><?= $count ?></strong><small>в смете</small></div></div>
            <div class="metric-card"><div><span>Проект</span><strong><?= e($project['status'] ?? '—') ?></strong><small>текущий статус</small></div></div>
        </div></div><div class="metric-stack"><div class="module-panel metric-mini"><span>Контроль бюджета</span><strong>100%</strong><small>Фактические данные сметы</small></div><div class="module-panel metric-mini"><span>Последнее изменение</span><strong><?= e($project['updatedAt'] ?? '—') ?></strong><small>из базы проекта</small></div></div></div>

    <?php elseif ($view === 'measurements'): ?>
        <?php
        $q = $pdo->prepare('SELECT * FROM smetogram_rooms WHERE project_id=? ORDER BY id DESC');
        $q->execute([$projectId]);
        $rooms = $q->fetchAll();
        $roomPhotos = [];
        $pq = $pdo->prepare('SELECT * FROM smetogram_room_photos WHERE project_id=? ORDER BY id DESC');
        $pq->execute([$projectId]);
        foreach ($pq->fetchAll() as $photo) $roomPhotos[(int)$photo['room_id']][] = $photo;
        ?>
        <div class="module-grid">
            <div class="module-panel">
                <div class="panel-heading"><div><h2>Размеры помещения</h2><p>Площадь, объём и площадь стен считаются автоматически.</p></div><button class="primary-button" data-bs-toggle="modal" data-bs-target="#roomModal"><i class="fa-solid fa-plus"></i> Добавить комнату</button></div>
                <?php if (!$rooms): ?><div class="empty-state">Замеров пока нет. Добавьте первую комнату.</div><?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <?php $area=(float)$room['length_m']*(float)$room['width_m']; $walls=2*((float)$room['length_m']+(float)$room['width_m'])*(float)$room['height_m']; ?>
                        <div class="room-photo-card">
                            <div class="document-row room-measure-row">
                                <div class="member-avatar"><i class="fa-solid fa-ruler-combined"></i></div>
                                <div><strong><?= e($room['name']) ?></strong><span><?= e((string)$room['length_m']) ?> × <?= e((string)$room['width_m']) ?> × <?= e((string)$room['height_m']) ?> м · стены <?= number_format($walls,1,',',' ') ?> м²</span></div>
                                <em><?= number_format($area,1,',',' ') ?> м²</em>
                                <form method="post" class="ms-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_room"><input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>"><button class="icon-button" type="submit" title="Удалить"><i class="fa-solid fa-trash-can"></i></button></form>
                            </div>
                            <div class="room-photo-actions">
                                
                                <form method="post" enctype="multipart/form-data" class="room-photo-upload">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="upload_room_photo">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <label class="outline-button room-camera-button"><i class="fa-solid fa-camera"></i> Сфотографировать<input type="file" name="room_photo" accept="image/*" capture="environment"></label>
                                    <div class="room-photo-progress" aria-hidden="true"><div class="room-photo-progress-track"><span></span></div><strong>0%</strong></div>
                                </form>
                                <form method="post" enctype="multipart/form-data" class="room-photo-upload">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="upload_room_photo">
                                    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                                    <label class="outline-button"><i class="fa-solid fa-images"></i> Добавить фото<input type="file" name="room_photo" accept="image/*"></label>
                                    <div class="room-photo-progress" aria-hidden="true"><div class="room-photo-progress-track"><span></span></div><strong>0%</strong></div>
                                </form>
                            </div>
                            <?php if (!empty($roomPhotos[(int)$room['id']])): ?>
                                <div class="room-photo-grid">
                                <?php foreach ($roomPhotos[(int)$room['id']] as $photo): ?>
                                    <div class="room-photo-thumb">
                                        <a href="<?= e($photo['path']) ?>" data-fancybox="room-<?= (int)$room['id'] ?>" data-caption="<?= e($room['name']) ?>"><img src="<?= e($photo['path']) ?>" alt="<?= e($room['name']) ?>" loading="lazy"></a>
                                        <form method="post" class="room-photo-delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_room_photo"><input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>"><button type="submit" class="room-photo-delete" title="Удалить" aria-label="Удалить фотографию"><i class="fa-solid fa-xmark-lg"></i></button></form>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="room-photo-empty"><i class="fa-solid fa-camera"></i><span>Фотографии комнаты ещё не добавлены</span></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="module-panel">
                <h2>Замеры → смета</h2>
                <p class="panel-copy">Замеры автоматически используются в смете. Вы добавляете работу — Сметограм сам подставляет нужное количество по всем помещениям.</p>
                <div class="generated-row"><i class="fa-solid fa-check-circle"></i><span>Пол — длина × ширина</span></div>
                <div class="generated-row"><i class="fa-solid fa-check-circle"></i><span>Стены — 2 × (длина + ширина) × высота</span></div>
                <div class="generated-row"><i class="fa-solid fa-check-circle"></i><span>Потолок — площадь пола</span></div>
                <div class="generated-row"><i class="fa-solid fa-check-circle"></i><span>Периметр — 2 × (длина + ширина)</span></div>
                <p class="panel-copy mt-3 mb-0">Расценка ставится уже в смете. Позиции можно редактировать или удалить как обычные работы.</p>
            </div>
        </div>
        <div class="modal fade" id="aiEstimateModal" tabindex="-1">
            <div class="modal-dialog modal-lg"><div class="modal-content ai-estimate-modal-content">
                <div class="modal-header"><div><h5 id="aiEstimateTitle">Расчёт по фото</h5><p class="panel-copy mb-0" id="aiEstimateSummary">Анализируем фотографии помещения…</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="ai-estimate-loading" id="aiEstimateLoading"><div class="ai-estimate-spinner"><i class="fa-solid fa-wand-magic-sparkles"></i></div><strong>AI анализирует помещение</strong><span>Определяем поверхности и подходящие работы</span></div>
                    <div id="aiEstimateResult" class="ai-estimate-result d-none">
                        <div class="ai-estimate-items" id="aiEstimateItems"></div>
                        <div class="ai-estimate-note"><i class="fa-solid fa-circle-info"></i><span>Это черновой расчёт по фотографиям. Перед добавлением в смету проверьте состав работ и количества.</span></div>
                    </div>
                </div>
                <div class="modal-footer" id="aiEstimateFooter"><button type="button" class="outline-button" data-bs-dismiss="modal">Закрыть</button></div>
            </div></div>
        </div>
        <div class="modal fade" id="roomModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
            <div class="modal-header"><h5>Новая комната</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_room"><input class="form-control mb-3" name="name" value="Комната"><div class="form-grid"><input class="form-control" name="length" value="5.2" placeholder="Длина"><input class="form-control" name="width" value="3.8" placeholder="Ширина"><input class="form-control" name="height" value="2.75" placeholder="Высота"></div></div>
            <div class="modal-footer"><button class="primary-button" type="submit">Сохранить</button></div>
        </form></div></div></div>

    <?php elseif ($view === 'settings'): ?>
        <?php
        $subscriptionQ = $pdo->prepare('SELECT subscriptionPlan,subscriptionStatus,subscriptionExpiresAt FROM users WHERE id=? LIMIT 1');
        $subscriptionQ->execute([(int)$user['id']]);
        $subscription = $subscriptionQ->fetch() ?: [];
        $planLabels = ['free'=>'Первый проект','project'=>'Проект','brigade'=>'Бригада','studio'=>'Студия'];
        $currentPlanLabel = $planLabels[(string)($subscription['subscriptionPlan'] ?? 'free')] ?? 'Первый проект';
        $allowedTimezones = [
            'Europe/Moscow'=>'Москва (UTC+3)',
            'Europe/Paris'=>'Париж (UTC+1/2)',
            'Europe/London'=>'Лондон (UTC+0/1)',
            'Asia/Yekaterinburg'=>'Екатеринбург (UTC+5)',
            'Asia/Novosibirsk'=>'Новосибирск (UTC+7)',
            'Asia/Krasnoyarsk'=>'Красноярск (UTC+7)',
            'Asia/Irkutsk'=>'Иркутск (UTC+8)',
            'Asia/Vladivostok'=>'Владивосток (UTC+10)'
        ];
        ?>
        <div class="settings-shell">
            <aside class="settings-sidebar module-panel">
                <div class="settings-sidebar-title">
                    <span class="settings-avatar"><?=e($initials)?></span>
                    <div><strong><?=e($user['name'] ?: 'Пользователь')?></strong><small><?=e($user['email'] ?: 'Email не указан')?></small></div>
                </div>
                <nav class="settings-menu" aria-label="Разделы настроек">
                    <a class="active" href="#profile-settings"><i class="fa-regular fa-user"></i><span>Профиль</span><i class="fa-solid fa-chevron-right menu-arrow"></i></a>
                    <a href="#security-settings"><i class="fa-solid fa-shield-halved"></i><span>Безопасность</span><i class="fa-solid fa-chevron-right menu-arrow"></i></a>
                    <a href="#notifications-settings"><i class="fa-regular fa-bell"></i><span>Уведомления</span><i class="fa-solid fa-chevron-right menu-arrow"></i></a>
                    <a href="#regional-settings"><i class="fa-solid fa-globe"></i><span>Регион и формат</span><i class="fa-solid fa-chevron-right menu-arrow"></i></a>
                    <a href="#billing-settings"><i class="fa-regular fa-credit-card"></i><span>Тариф</span><i class="fa-solid fa-chevron-right menu-arrow"></i></a>
                </nav>
                <div class="settings-sidebar-note"><i class="fa-solid fa-circle-info"></i><span>Изменения применяются к вашему аккаунту и не меняют данные проектов.</span></div>
            </aside>

            <div class="settings-content">
                <section class="settings-card module-panel" id="profile-settings">
                    <div class="settings-card-head">
                        <div><span class="settings-eyebrow">АККАУНТ</span><h2>Личные данные</h2><p>Данные, которые используются в вашем профиле, команде и документах.</p></div>
                        <span class="settings-status"><i class="fa-solid fa-circle-check"></i> Активен</span>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="action" value="save_profile">
                        <div class="settings-form-grid">
                            <label class="settings-field"><span>Имя и фамилия</span><input name="name" value="<?=e($user['name'] ?? '')?>" autocomplete="name" placeholder="Иван Иванов"></label>
                            <label class="settings-field"><span>Email</span><input type="email" name="email" value="<?=e($user['email'] ?? '')?>" autocomplete="email" placeholder="name@example.com"></label>
                        </div>
                        <div class="settings-form-foot"><small>Этот email используется для вашего аккаунта.</small><button class="primary-button" type="submit"><i class="fa-solid fa-check"></i> Сохранить профиль</button></div>
                    </form>
                </section>

                <section class="settings-card module-panel" id="security-settings">
                    <div class="settings-card-head">
                        <div><span class="settings-eyebrow">БЕЗОПАСНОСТЬ</span><h2><?=!empty($user['password_hash']) ? 'Сменить пароль' : 'Установить пароль'?></h2><p><?=!empty($user['password_hash']) ? 'Для изменения потребуется текущий пароль.' : 'Добавьте пароль к аккаунту, если хотите входить без Telegram.'?></p></div>
                        <span class="settings-security-icon"><i class="fa-solid fa-lock"></i></span>
                    </div>
                    <form method="post" class="settings-password-form">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="action" value="change_password">
                        <?php if (!empty($user['password_hash'])): ?>
                        <label class="settings-field"><span>Текущий пароль</span><input type="password" name="current_password" autocomplete="current-password" required placeholder="••••••••"></label>
                        <?php endif; ?>
                        <div class="settings-form-grid settings-password-grid">
                            <label class="settings-field"><span>Новый пароль</span><input type="password" name="new_password" minlength="8" autocomplete="new-password" required placeholder="Не менее 8 символов"></label>
                            <label class="settings-field"><span>Повторите новый пароль</span><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required placeholder="Повторите пароль"></label>
                        </div>
                        <div class="settings-form-foot"><small>Минимум 8 символов. Хранится в защищённом виде.</small><button class="outline-button" type="submit"><i class="fa-solid fa-key"></i> <?=!empty($user['password_hash']) ? 'Изменить пароль' : 'Установить пароль'?></button></div>
                    </form>
                </section>

                <section class="settings-card module-panel" id="notifications-settings">
                    <div class="settings-card-head">
                        <div><span class="settings-eyebrow">ОПОВЕЩЕНИЯ</span><h2>Что показывать</h2><p>Управляйте типами событий, которые попадают в вашу ленту уведомлений.</p></div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="action" value="save_notifications">
                        <label class="settings-toggle-row">
                            <span class="settings-option-copy"><span class="settings-option-icon"><i class="fa-solid fa-layer-group"></i></span><span><strong>Оповещения проектов</strong><small>Главный переключатель ленты уведомлений.</small></span></span>
                            <input class="settings-switch" type="checkbox" name="project_notifications" value="1" <?=!empty($userSettings['project_notifications'])?'checked':''?>>
                        </label>
                        <label class="settings-toggle-row">
                            <span class="settings-option-copy"><span class="settings-option-icon"><i class="fa-regular fa-comments"></i></span><span><strong>Сообщения</strong><small>Новые сообщения в чатах проекта.</small></span></span>
                            <input class="settings-switch" type="checkbox" name="message_notifications" value="1" <?=!empty($userSettings['message_notifications'])?'checked':''?>>
                        </label>
                        <label class="settings-toggle-row">
                            <span class="settings-option-copy"><span class="settings-option-icon"><i class="fa-regular fa-file-lines"></i></span><span><strong>Документы</strong><small>Новые документы и загруженные файлы.</small></span></span>
                            <input class="settings-switch" type="checkbox" name="document_notifications" value="1" <?=!empty($userSettings['document_notifications'])?'checked':''?>>
                        </label>
                        <label class="settings-toggle-row">
                            <span class="settings-option-copy"><span class="settings-option-icon"><i class="fa-regular fa-credit-card"></i></span><span><strong>Платежи</strong><small>Создание и изменение платежей.</small></span></span>
                            <input class="settings-switch" type="checkbox" name="payment_notifications" value="1" <?=!empty($userSettings['payment_notifications'])?'checked':''?>>
                        </label>
                        <label class="settings-toggle-row">
                            <span class="settings-option-copy"><span class="settings-option-icon"><i class="fa-solid fa-clipboard-check"></i></span><span><strong>Приёмка</strong><small>Отправка этапов на проверку и изменения приёмки.</small></span></span>
                            <input class="settings-switch" type="checkbox" name="acceptance_notifications" value="1" <?=!empty($userSettings['acceptance_notifications'])?'checked':''?>>
                        </label>
                        <div class="settings-info-row"><i class="fa-solid fa-rotate"></i><span><strong>Автообновление ленты</strong><small>Проверка новых уведомлений выполняется автоматически каждые 10 секунд.</small></span><b>Активно</b></div>
                        <div class="settings-form-foot"><small>Снимите галочки с тех событий, которые не хотите видеть.</small><button class="primary-button" type="submit"><i class="fa-solid fa-check"></i> Сохранить уведомления</button></div>
                    </form>
                </section>

                <section class="settings-card module-panel" id="regional-settings">
                    <div class="settings-card-head">
                        <div><span class="settings-eyebrow">РЕГИОН</span><h2>Регион и формат</h2><p>Настройки даты и времени для вашего аккаунта.</p></div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="action" value="save_regional">
                        <div class="settings-form-grid">
                            <label class="settings-field"><span>Часовой пояс</span>
                                <select name="timezone">
                                    <?php foreach($allowedTimezones as $tz=>$label): ?><option value="<?=e($tz)?>" <?=($userSettings['timezone']??'Europe/Moscow')===$tz?'selected':''?>><?=e($label)?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="settings-field"><span>Формат даты</span>
                                <select name="date_format">
                                    <option value="d.m.Y" <?=($userSettings['date_format']??'d.m.Y')==='d.m.Y'?'selected':''?>>31.12.2026</option>
                                    <option value="d/m/Y" <?=($userSettings['date_format']??'d.m.Y')==='d/m/Y'?'selected':''?>>31/12/2026</option>
                                    <option value="Y-m-d" <?=($userSettings['date_format']??'d.m.Y')==='Y-m-d'?'selected':''?>>2026-12-31</option>
                                </select>
                            </label>
                        </div>
                        <div class="settings-form-foot"><small>Применяется к настройкам аккаунта и новым интерфейсам.</small><button class="primary-button" type="submit"><i class="fa-solid fa-check"></i> Сохранить формат</button></div>
                    </form>
                </section>

                <section class="settings-card module-panel" id="billing-settings">
                    <div class="settings-card-head">
                        <div><span class="settings-eyebrow">ТАРИФ</span><h2>Тариф и оплата</h2><p>Управление режимом работы аккаунта.</p></div>
                        <span class="settings-status"><i class="fa-solid fa-circle"></i> <?=e($currentPlanLabel)?></span>
                    </div>
                    <div class="settings-billing-row">
                        <div class="settings-billing-main"><span class="settings-option-icon"><i class="fa-regular fa-credit-card"></i></span><div><strong>Текущий тариф</strong><small><?=e($currentPlanLabel)?><?=!empty($subscription['subscriptionExpiresAt'])?' · до '.date('d.m.Y',strtotime((string)$subscription['subscriptionExpiresAt'])):' · без срока'?></small></div></div>
                        <a class="outline-button" href="workspace.php?view=billing<?= $projectId ? '&id='.$projectId : '' ?>">Открыть тарифы <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </section>

                <section class="settings-card module-panel" id="account-settings">
                    <div class="settings-card-head"><div><span class="settings-eyebrow">СИСТЕМА</span><h2>Информация об аккаунте</h2><p>Технические данные вашего профиля.</p></div></div>
                    <div class="settings-meta-grid">
                        <div><span>ID пользователя</span><strong>#<?= (int)$user['id'] ?></strong></div>
                        <div><span>Способ входа</span><strong><?=e($user['loginMethod'] ?? '—')?></strong></div>
                        <div><span>Роль</span><strong><?=is_admin($user) ? 'Администратор' : 'Пользователь'?></strong></div>
                        <div><span>Дата регистрации</span><strong><?=!empty($user['createdAt']) ? date('d.m.Y',strtotime((string)$user['createdAt'])) : '—'?></strong></div>
                    </div>
                </section>
            </div>
        </div>

    <?php elseif ($view === 'billing'): ?>
        <?php
        $plans = [
            'free' => ['Первый проект','0 ₽','навсегда','Один полноценный объект без карты'],
            'project' => ['Проект','1 490 ₽','в месяц','Смета + дорожная карта + документы'],
            'brigade' => ['Бригада','3 900 ₽','в месяц','До 5 объектов и командная работа'],
            'studio' => ['Студия','7 900 ₽','в месяц','Безлимитные объекты и расширенные функции']
        ];
        $uq = $pdo->prepare('SELECT subscriptionPlan,subscriptionStatus,subscriptionExpiresAt FROM users WHERE id=? LIMIT 1');
        $uq->execute([$user['id']]);
        $subscription = $uq->fetch() ?: [];
        $currentPlan = (string)($subscription['subscriptionPlan'] ?? 'free');
        if (!isset($plans[$currentPlan])) $currentPlan = 'free';
        ?>
        <div class="billing-hero module-panel">
            <div><div class="eyebrow">ТАРИФ И ОПЛАТА</div><h2>Выберите режим работы</h2><p class="panel-copy">Тариф сохраняется в вашем аккаунте и применяется ко всем объектам.</p></div>
            <div class="billing-current"><span>ТЕКУЩИЙ ТАРИФ</span><strong><?= e($plans[$currentPlan][0]) ?></strong><small>Активен<?= !empty($subscription['subscriptionExpiresAt']) ? ' · до '.date('d.m.Y',strtotime($subscription['subscriptionExpiresAt'])) : '' ?></small></div>
        </div>
        <div class="plans-grid billing-plans">
            <?php foreach ($plans as $id => $plan): ?>
                <div class="plan-card billing-card <?= $id === $currentPlan ? 'selected' : '' ?>">
                    <?php if ($id === $currentPlan): ?><span class="plan-badge">ТЕКУЩИЙ</span><?php endif; ?>
                    <span><?= e($plan[0]) ?></span><strong><?= e($plan[1]) ?></strong><small><?= e($plan[2]) ?></small><p><?= e($plan[3]) ?></p>
                    <ul><li>Смета и рабочее пространство</li><li>Команда и документы</li><li>Чат и приёмка</li></ul>
                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="choose_plan"><input type="hidden" name="plan" value="<?= e($id) ?>"><button class="<?= $id === $currentPlan ? 'outline-button' : 'primary-button' ?>" type="submit"><?= $id === $currentPlan ? 'Текущий тариф' : 'Выбрать тариф' ?></button></form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="billing-grid">
            <div class="module-panel"><div class="panel-heading"><div><h2>Состав тарифов</h2><p>Функции Сметограма развиваются внутри рабочего пространства.</p></div></div><div class="billing-feature-list"><div><i class="fa-solid fa-calculator"></i><span><b>Смета</b><small>Разделы, позиции, цены, импорт и экспорт.</small></span></div><div><i class="fa-solid fa-table-columns"></i><span><b>Объект</b><small>График, замеры, команда, документы, чат и приёмка.</small></span></div><div><i class="fa-solid fa-wand-magic-sparkles"></i><span><b>ИИ</b><small>Распознавание файлов и подготовка черновика.</small></span></div></div></div>
            <div class="module-panel"><div class="panel-heading"><div><h2>Оплата</h2><p>Без фиктивных списаний.</p></div></div><div class="billing-note"><i class="fa-solid fa-credit-card-2-front"></i><div><b>Онлайн-оплата</b><span>Выбор тарифа уже сохраняется. Эквайринг подключим отдельным шагом, когда будет выбран платёжный провайдер.</span></div></div></div>
        </div>
    <?php else: ?>
        <div class="module-panel"><div class="empty-state">Раздел не найден.</div></div>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
