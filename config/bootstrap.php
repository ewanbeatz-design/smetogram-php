<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(500);exit('Конфигурация БД не создана. Запустите GitHub Actions deploy.');}
$config=require $configFile;
$dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);
try{$pdo=new PDO($dsn,$config['db']['user'],$config['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(PDOException $e){http_response_code(500);exit('Не удалось подключиться к базе данных. Проверьте параметры MySQL в REG.RU.');}
try{
$has=$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_hash'")->fetchColumn();
if(!$has)$pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
$hasName=$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='name'")->fetchColumn();
if(!$hasName)$pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER email");
}catch(Throwable $e){}

// Runtime compatibility migration for the existing Smetogram database.
// The PHP port uses the original camelCase column names from the old application.
// Missing columns/tables are added automatically; existing data is never dropped.
try {
    $tableExists = static function(PDO $pdo, string $table): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?)");
        $q->execute([$table]);
        return (bool)$q->fetchColumn();
    };
    $columnExists = static function(PDO $pdo, string $table, string $column): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)=LOWER(?) AND LOWER(COLUMN_NAME)=LOWER(?)");
        $q->execute([$table, $column]);
        return (bool)$q->fetchColumn();
    };
    $addColumn = static function(PDO $pdo, string $table, string $column, string $definition) use ($columnExists): void {
        if (!$columnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    };

    if (!$tableExists($pdo, 'users')) {
        $pdo->exec("CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            openId VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NULL,
            email VARCHAR(320) NULL UNIQUE,
            loginMethod VARCHAR(64) NULL,
            role ENUM('user','admin') NOT NULL DEFAULT 'user',
            password_hash VARCHAR(255) NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            lastSignedIn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $addColumn($pdo,'users','openId',"VARCHAR(64) NULL");
        $addColumn($pdo,'users','name',"VARCHAR(255) NULL");
        $addColumn($pdo,'users','email',"VARCHAR(320) NULL");
        $addColumn($pdo,'users','loginMethod',"VARCHAR(64) NULL");
        $addColumn($pdo,'users','role',"VARCHAR(32) NOT NULL DEFAULT 'user'");
        $addColumn($pdo,'users','password_hash',"VARCHAR(255) NULL");
        $addColumn($pdo,'users','createdAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        $addColumn($pdo,'users','updatedAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $addColumn($pdo,'users','lastSignedIn',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    if (!$tableExists($pdo,'projects')) {
        $pdo->exec("CREATE TABLE projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ownerId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            city VARCHAR(120) NOT NULL DEFAULT '',
            clientName VARCHAR(255) NOT NULL DEFAULT '',
            clientEmail VARCHAR(320) NULL,
            workType VARCHAR(120) NOT NULL DEFAULT 'Строительство',
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            deadline DATETIME NULL,
            budget DECIMAL(14,2) NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(ownerId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $addColumn($pdo,'projects','ownerId',"BIGINT UNSIGNED NULL");
        $addColumn($pdo,'projects','name',"VARCHAR(255) NOT NULL DEFAULT 'Новый проект'");
        $addColumn($pdo,'projects','city',"VARCHAR(120) NOT NULL DEFAULT ''");
        $addColumn($pdo,'projects','clientName',"VARCHAR(255) NOT NULL DEFAULT ''");
        $addColumn($pdo,'projects','clientEmail',"VARCHAR(320) NULL");
        $addColumn($pdo,'projects','workType',"VARCHAR(120) NOT NULL DEFAULT 'Строительство'");
        $addColumn($pdo,'projects','status',"VARCHAR(32) NOT NULL DEFAULT 'draft'");
        $addColumn($pdo,'projects','deadline',"DATETIME NULL");
        $addColumn($pdo,'projects','budget',"DECIMAL(14,2) NOT NULL DEFAULT 0");
        $addColumn($pdo,'projects','createdAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        $addColumn($pdo,'projects','updatedAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    if (!$tableExists($pdo,'estimatecategories')) {
        $pdo->exec("CREATE TABLE estimatecategories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            projectId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sortOrder INT NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(projectId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $addColumn($pdo,'estimatecategories','projectId',"BIGINT UNSIGNED NULL");
        $addColumn($pdo,'estimatecategories','name',"VARCHAR(255) NOT NULL DEFAULT 'Раздел'");
        $addColumn($pdo,'estimatecategories','sortOrder',"INT NOT NULL DEFAULT 0");
        $addColumn($pdo,'estimatecategories','createdAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    if (!$tableExists($pdo,'estimateitems')) {
        $pdo->exec("CREATE TABLE estimateitems (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categoryId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            quantity DECIMAL(14,3) NOT NULL DEFAULT 1,
            unit VARCHAR(40) NOT NULL DEFAULT 'шт.',
            price DECIMAL(14,2) NOT NULL DEFAULT 0,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(categoryId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($tableExists($pdo,'estimate_items')) {
            try {
                $pdo->exec("INSERT INTO estimateitems (id,categoryId,name,quantity,unit,price,source)
                    SELECT id,category_id,name,quantity,unit,price,source FROM estimate_items");
            } catch (Throwable $e) {}
        }
    } else {
        $addColumn($pdo,'estimateitems','categoryId',"BIGINT UNSIGNED NULL");
        $addColumn($pdo,'estimateitems','name',"VARCHAR(255) NOT NULL DEFAULT 'Позиция'");
        $addColumn($pdo,'estimateitems','quantity',"DECIMAL(14,3) NOT NULL DEFAULT 1");
        $addColumn($pdo,'estimateitems','unit',"VARCHAR(40) NOT NULL DEFAULT 'шт.'");
        $addColumn($pdo,'estimateitems','price',"DECIMAL(14,2) NOT NULL DEFAULT 0");
        $addColumn($pdo,'estimateitems','source',"VARCHAR(30) NOT NULL DEFAULT 'manual'");
        $addColumn($pdo,'estimateitems','createdAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        $addColumn($pdo,'estimateitems','updatedAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    $simpleTables = [
        'scheduletasks' => "CREATE TABLE scheduletasks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, projectId BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL, startsAt DATETIME NULL, endsAt DATETIME NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'planned', paymentMilestone DECIMAL(14,2) NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX(projectId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'projectmembers' => "CREATE TABLE projectmembers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, projectId BIGINT UNSIGNED NOT NULL,
            userId BIGINT UNSIGNED NULL, invitedEmail VARCHAR(320) NULL, invitedPhone VARCHAR(32) NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'client', inviteToken VARCHAR(80) NULL, joinedAt DATETIME NULL,
            createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX(projectId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'projectdocuments' => "CREATE TABLE projectdocuments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, projectId BIGINT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft',
            fileUrl TEXT NULL, signedAt DATETIME NULL, createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(projectId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'projectmessages' => "CREATE TABLE projectmessages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, projectId BIGINT UNSIGNED NOT NULL,
            authorId BIGINT UNSIGNED NOT NULL, channel VARCHAR(32) NOT NULL DEFAULT 'general',
            body TEXT NOT NULL, createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX(projectId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'acceptancestages' => "CREATE TABLE acceptancestages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, projectId BIGINT UNSIGNED NOT NULL,
            scheduleTaskId BIGINT UNSIGNED NULL, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'pending',
            amount DECIMAL(14,2) NOT NULL DEFAULT 0, holdback DECIMAL(14,2) NOT NULL DEFAULT 0, comment TEXT NULL,
            submittedAt DATETIME NULL, acceptedAt DATETIME NULL, createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(projectId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($simpleTables as $table=>$sql) {
        if (!$tableExists($pdo,$table)) $pdo->exec($sql);
    }

    $columnSets = [
        'scheduletasks'=>[
            'projectId'=>"BIGINT UNSIGNED NULL",'title'=>"VARCHAR(255) NOT NULL DEFAULT 'Этап'",
            'startsAt'=>"DATETIME NULL",'endsAt'=>"DATETIME NULL",'status'=>"VARCHAR(32) NOT NULL DEFAULT 'planned'",
            'paymentMilestone'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",'createdAt'=>"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        ],
        'projectmembers'=>[
            'projectId'=>"BIGINT UNSIGNED NULL",'userId'=>"BIGINT UNSIGNED NULL",'invitedEmail'=>"VARCHAR(320) NULL",
            'invitedPhone'=>"VARCHAR(32) NULL",'role'=>"VARCHAR(32) NOT NULL DEFAULT 'client'",
            'inviteToken'=>"VARCHAR(80) NULL",'joinedAt'=>"DATETIME NULL",'createdAt'=>"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        ],
        'projectdocuments'=>[
            'projectId'=>"BIGINT UNSIGNED NULL",'type'=>"VARCHAR(32) NOT NULL DEFAULT 'contract'",
            'title'=>"VARCHAR(255) NOT NULL DEFAULT 'Документ'",'status'=>"VARCHAR(32) NOT NULL DEFAULT 'draft'",
            'fileUrl'=>"TEXT NULL",'signedAt'=>"DATETIME NULL",'createdAt'=>"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        ],
        'projectmessages'=>[
            'projectId'=>"BIGINT UNSIGNED NULL",'authorId'=>"BIGINT UNSIGNED NULL",'channel'=>"VARCHAR(32) NOT NULL DEFAULT 'general'",
            'body'=>"TEXT NULL",'createdAt'=>"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        ],
        'acceptancestages'=>[
            'projectId'=>"BIGINT UNSIGNED NULL",'scheduleTaskId'=>"BIGINT UNSIGNED NULL",'title'=>"VARCHAR(255) NOT NULL DEFAULT 'Этап'",
            'status'=>"VARCHAR(32) NOT NULL DEFAULT 'pending'",'amount'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
            'holdback'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",'comment'=>"TEXT NULL",'submittedAt'=>"DATETIME NULL",
            'acceptedAt'=>"DATETIME NULL",'createdAt'=>"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        ]
    ];
    foreach ($columnSets as $table=>$columns) {
        foreach ($columns as $column=>$definition) $addColumn($pdo,$table,$column,$definition);
    }
} catch (Throwable $e) {
    // Compatibility migration must never prevent the application from starting.
}

/* Smetogram business modules: payments, documents, audit and media. */
try {
    $moduleTables = [
        'smetogram_payments' => "CREATE TABLE smetogram_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            projectId BIGINT UNSIGNED NOT NULL, userId BIGINT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'payment', title VARCHAR(255) NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(32) NOT NULL DEFAULT 'pending',
            provider VARCHAR(32) NULL, externalId VARCHAR(120) NULL, description TEXT NULL,
            paidAt DATETIME NULL, createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(projectId), INDEX(userId), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'smetogram_document_files' => "CREATE TABLE smetogram_document_files (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documentId BIGINT UNSIGNED NOT NULL, projectId BIGINT UNSIGNED NOT NULL,
            userId BIGINT UNSIGNED NOT NULL, originalName VARCHAR(255) NOT NULL,
            storedName VARCHAR(255) NOT NULL, mime VARCHAR(120) NOT NULL, sizeBytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            path VARCHAR(500) NOT NULL, createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(documentId), INDEX(projectId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'smetogram_document_events' => "CREATE TABLE smetogram_document_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documentId BIGINT UNSIGNED NOT NULL, userId BIGINT UNSIGNED NOT NULL,
            eventType VARCHAR(40) NOT NULL, comment TEXT NULL, createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(documentId), INDEX(userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'smetogram_payment_events' => "CREATE TABLE smetogram_payment_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            paymentId BIGINT UNSIGNED NOT NULL, eventType VARCHAR(40) NOT NULL,
            payloadJson LONGTEXT NULL, createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(paymentId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach($moduleTables as $table=>$sql) {
        if(!$tableExists($pdo,$table)) $pdo->exec($sql);
    }
    $addColumn($pdo,'projectdocuments','versionNo',"INT NOT NULL DEFAULT 1");
    $addColumn($pdo,'projectdocuments','createdBy',"BIGINT UNSIGNED NULL");
    $addColumn($pdo,'projectdocuments','updatedAt',"TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $addColumn($pdo,'users','subscriptionPlan',"VARCHAR(32) NOT NULL DEFAULT 'free'");
    $addColumn($pdo,'users','subscriptionStatus',"VARCHAR(32) NOT NULL DEFAULT 'active'");
    $addColumn($pdo,'users','subscriptionStartedAt',"DATETIME NULL");
    $addColumn($pdo,'users','subscriptionExpiresAt',"DATETIME NULL");
    if (!$tableExists($pdo,'smetogram_notifications')) {
        $pdo->exec("CREATE TABLE smetogram_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            userId BIGINT UNSIGNED NOT NULL,
            projectId BIGINT UNSIGNED NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            body TEXT NULL,
            url VARCHAR(500) NULL,
            isRead TINYINT(1) NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(userId), INDEX(projectId), INDEX(isRead), INDEX(createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch(Throwable $e) {}

function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function redirect(string $u):never{header('Location: '.$u);exit;}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Сессия формы устарела. Обновите страницу.');}}
function current_user():?array{return $_SESSION['user']??null;}
function create_notification(PDO $pdo, int $userId, ?int $projectId, string $type, string $title, string $body = '', ?string $url = null): void {
    if ($userId <= 0) return;
    $q = $pdo->prepare('INSERT INTO smetogram_notifications (userId,projectId,type,title,body,url) VALUES (?,?,?,?,?,?)');
    $q->execute([$userId, $projectId, $type, $title, $body, $url]);
}

function require_auth():array{if(!current_user())redirect('login.php');return current_user();}
