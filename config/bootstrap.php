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
        $addColumn($pdo,'projects','estimateDate',"DATE NULL");
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
    // Связь позиции сметы с геометрией замеров. Если quantitySource=measurement,
    // количество пересчитывается автоматически при изменении замеров помещения.
    $addColumn($pdo,'estimateitems','quantitySource',"VARCHAR(30) NOT NULL DEFAULT 'manual'");
    $addColumn($pdo,'estimateitems','measurementType',"VARCHAR(30) NULL");
    $addColumn($pdo,'estimateitems','measurementRoomIds',"TEXT NULL");

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
    $addColumn($pdo,'users','telegramId',"BIGINT UNSIGNED NULL");
    $addColumn($pdo,'users','telegramUsername',"VARCHAR(255) NULL");
    $addColumn($pdo,'users','username',"VARCHAR(255) NULL");
    $addColumn($pdo,'smetogram_payments','stageId',"BIGINT UNSIGNED NULL");
    $addColumn($pdo,'smetogram_payments','paidAmount',"DECIMAL(14,2) NOT NULL DEFAULT 0");
    try { $pdo->exec("UPDATE smetogram_payments SET paidAmount=CASE WHEN status='paid' THEN amount ELSE 0 END WHERE type='stage' AND paidAmount=0"); } catch (Throwable $e) {}
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


/* Reusable estimate position templates. */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS estimateitemtemplates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        categoryName VARCHAR(255) NOT NULL DEFAULT 'Работы',
        name VARCHAR(255) NOT NULL,
        unit VARCHAR(40) NOT NULL DEFAULT 'шт.',
        price DECIMAL(14,2) NOT NULL DEFAULT 0,
        createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(categoryName)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ((int)$pdo->query("SELECT COUNT(*) FROM estimateitemtemplates")->fetchColumn() === 0) {
        $templates = [
            ['Демонтаж','Демонтаж плитки','м²',350],
            ['Демонтаж','Удаление обоев','м²',120],
            ['Черновые работы','Грунтовка стен','м²',80],
            ['Черновые работы','Штукатурка стен по маякам','м²',650],
            ['Черновые работы','Шпаклевка стен','м²',420],
            ['Отделка','Покраска стен в два слоя','м²',350],
            ['Отделка','Шпаклевка под покраску','м²',450],
            ['Полы','Укладка ламината','м²',450],
            ['Плитка','Укладка керамогранита','м²',1400],
            ['Электрика','Монтаж розетки','шт',650],
            ['Электрика','Прокладка кабеля','м.п.',120],
            ['Сантехника','Монтаж смесителя','шт',1200],
            ['Сантехника','Монтаж инсталляции','шт',4500],
            ['Потолки','Монтаж натяжного потолка','м²',700]
        ];
        $st = $pdo->prepare("INSERT INTO estimateitemtemplates (categoryName,name,unit,price) VALUES (?,?,?,?)");
        foreach ($templates as $tpl) $st->execute($tpl);
    }
} catch (Throwable $e) {}

/* Ready-made estimate templates. They are editable after applying: any section or position can be removed/changed and new ones can be added. */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS estimatetemplates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description VARCHAR(500) NULL,
        icon VARCHAR(80) NOT NULL DEFAULT 'bi-house',
        createdAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS estimatetemplateitems (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        templateId BIGINT UNSIGNED NOT NULL,
        categoryName VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        unit VARCHAR(40) NOT NULL DEFAULT 'шт.',
        price DECIMAL(14,2) NOT NULL DEFAULT 0,
        sortOrder INT NOT NULL DEFAULT 0,
        INDEX(templateId),
        INDEX(categoryName)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ((int)$pdo->query("SELECT COUNT(*) FROM estimatetemplates")->fetchColumn() === 0) {
        $st=$pdo->prepare("INSERT INTO estimatetemplates(name,description,icon) VALUES(?,?,?)");
        $presets=[
            ['Косметический ремонт квартиры','Подготовка и чистовая отделка без капитальных работ','bi-paint-bucket'],
            ['Капитальный ремонт квартиры','Демонтаж, черновые работы, электрика, сантехника и отделка','bi-building'],
            ['Ремонт санузла','Полный набор работ для ванной комнаты или санузла','bi-droplet'],
            ['Электромонтаж','Черновая и чистовая электрика для объекта','bi-lightning-charge'],
            ['Сантехнические работы','Разводка, монтаж оборудования и подключение','bi-water'],
            ['Строительство дома','Базовая структура сметы от подготовки до инженерии','bi-house-heart']
        ];
        foreach($presets as $p)$st->execute($p);
        $ids=$pdo->query("SELECT id,name FROM estimatetemplates ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
        $items=[
            'Косметический ремонт квартиры'=>[
                ['Подготовка и демонтаж','Демонтаж обоев','м²',120],['Подготовка и демонтаж','Демонтаж напольного покрытия','м²',150],
                ['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Шпаклевка стен','м²',420],
                ['Чистовая отделка','Покраска стен в два слоя','м²',350],['Чистовая отделка','Укладка ламината','м²',450],
                ['Чистовая отделка','Монтаж плинтуса','м.п.',180],['Электрика','Монтаж розетки','шт',650],
                ['Финишные работы','Уборка после ремонта','м²',120]
            ],
            'Капитальный ремонт квартиры'=>[
                ['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж стяжки до 5 см','м²',650],
                ['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Штукатурка стен по маякам','м²',650],
                ['Черновые работы','Шпаклевка стен','м²',420],['Черновые работы','Стяжка пола до 50 мм','м²',900],
                ['Электрика','Прокладка кабеля','м.п.',120],['Электрика','Монтаж розетки','шт',650],
                ['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Монтаж инсталляции','шт',4500],
                ['Чистовая отделка','Укладка керамогранита','м²',1400],['Чистовая отделка','Покраска стен в два слоя','м²',350],
                ['Чистовая отделка','Укладка ламината','м²',450],['Финишные работы','Монтаж межкомнатной двери','шт',4500]
            ],
            'Ремонт санузла'=>[
                ['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж сантехники','шт',1200],
                ['Черновые работы','Гидроизоляция пола','м²',450],['Черновые работы','Выравнивание стен','м²',650],
                ['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Разводка канализации','точка',1600],
                ['Сантехника','Монтаж инсталляции','шт',4500],['Сантехника','Монтаж смесителя','шт',1200],
                ['Плитка','Укладка керамогранита','м²',1400],['Плитка','Затирка швов','м²',300],
                ['Электрика','Монтаж розетки','шт',650],['Финишные работы','Герметизация примыканий','м.п.',250]
            ],
            'Электромонтаж'=>[
                ['Черновая электрика','Разметка и штробление','м.п.',350],['Черновая электрика','Прокладка кабеля','м.п.',120],
                ['Черновая электрика','Монтаж подрозетника','шт',250],['Черновая электрика','Сборка электрощита','шт',8500],
                ['Чистовая электрика','Монтаж розетки','шт',650],['Чистовая электрика','Монтаж выключателя','шт',650],
                ['Чистовая электрика','Монтаж светильника','шт',1200],['Чистовая электрика','Подключение бытового оборудования','шт',900]
            ],
            'Сантехнические работы'=>[
                ['Демонтаж','Демонтаж сантехнических приборов','шт',1200],['Черновая сантехника','Разводка водоснабжения','точка',1800],
                ['Черновая сантехника','Разводка канализации','точка',1600],['Черновая сантехника','Монтаж коллектора','шт',3500],
                ['Чистовая сантехника','Монтаж смесителя','шт',1200],['Чистовая сантехника','Монтаж унитаза','шт',2200],
                ['Чистовая сантехника','Монтаж инсталляции','шт',4500],['Чистовая сантехника','Подключение стиральной машины','шт',1200]
            ],
            'Строительство дома'=>[
                ['Подготовительные работы','Разработка грунта','м³',900],['Фундамент','Устройство монолитного фундамента','м³',8500],
                ['Фундамент','Гидроизоляция фундамента','м²',450],['Коробка','Возведение стен','м²',3200],
                ['Коробка','Устройство перекрытия','м²',4500],['Кровля','Устройство кровельного покрытия','м²',2200],
                ['Окна и двери','Монтаж оконных блоков','шт',4500],['Электрика','Прокладка кабеля','м.п.',120],
                ['Сантехника','Разводка водоснабжения','точка',1800],['Отделка','Штукатурка стен по маякам','м²',650]
            ]
        ];
        $st=$pdo->prepare("INSERT INTO estimatetemplateitems(templateId,categoryName,name,unit,price,sortOrder) VALUES(?,?,?,?,?,?)");
        foreach($items as $templateName=>$rows){
            $tid=(int)($ids[$templateName]??0); $sort=0;
            foreach($rows as $row){$st->execute([$tid,$row[0],$row[1],$row[2],$row[3],++$sort]);}
        }
    }
    // Если шаблоны уже были созданы предыдущей версией, но позиции не попали в БД — дозаполняем их.
    // Это важно для существующих установок: новые позиции не должны зависеть от того, когда была создана таблица.
    $templateItems=$pdo->query("SELECT t.id,t.name FROM estimatetemplates t ORDER BY t.id")->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($templateItems) {
        $checkItems=$pdo->prepare("SELECT COUNT(*) FROM estimatetemplateitems WHERE templateId=?");
        $st=$pdo->prepare("INSERT INTO estimatetemplateitems(templateId,categoryName,name,unit,price,sortOrder) VALUES(?,?,?,?,?,?)");
        $items=[
            'Косметический ремонт квартиры'=>[
                ['Подготовка и демонтаж','Демонтаж обоев','м²',120],['Подготовка и демонтаж','Демонтаж напольного покрытия','м²',150],['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Шпаклевка стен','м²',420],['Чистовая отделка','Покраска стен в два слоя','м²',350],['Чистовая отделка','Укладка ламината','м²',450],['Чистовая отделка','Монтаж плинтуса','м.п.',180],['Электрика','Монтаж розетки','шт',650],['Финишные работы','Уборка после ремонта','м²',120]
            ],
            'Капитальный ремонт квартиры'=>[
                ['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж стяжки до 5 см','м²',650],['Черновые работы','Грунтовка стен','м²',80],['Черновые работы','Штукатурка стен по маякам','м²',650],['Черновые работы','Шпаклевка стен','м²',420],['Черновые работы','Стяжка пола до 50 мм','м²',900],['Электрика','Прокладка кабеля','м.п.',120],['Электрика','Монтаж розетки','шт',650],['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Монтаж инсталляции','шт',4500],['Чистовая отделка','Укладка керамогранита','м²',1400],['Чистовая отделка','Покраска стен в два слоя','м²',350],['Чистовая отделка','Укладка ламината','м²',450],['Финишные работы','Монтаж межкомнатной двери','шт',4500]
            ],
            'Ремонт санузла'=>[
                ['Демонтаж','Демонтаж плитки','м²',350],['Демонтаж','Демонтаж сантехники','шт',1200],['Черновые работы','Гидроизоляция пола','м²',450],['Черновые работы','Выравнивание стен','м²',650],['Сантехника','Разводка водоснабжения','точка',1800],['Сантехника','Разводка канализации','точка',1600],['Сантехника','Монтаж инсталляции','шт',4500],['Сантехника','Монтаж смесителя','шт',1200],['Плитка','Укладка керамогранита','м²',1400],['Плитка','Затирка швов','м²',300],['Электрика','Монтаж розетки','шт',650],['Финишные работы','Герметизация примыканий','м.п.',250]
            ],
            'Электромонтаж'=>[
                ['Черновая электрика','Разметка и штробление','м.п.',350],['Черновая электрика','Прокладка кабеля','м.п.',120],['Черновая электрика','Монтаж подрозетника','шт',250],['Черновая электрика','Сборка электрощита','шт',8500],['Чистовая электрика','Монтаж розетки','шт',650],['Чистовая электрика','Монтаж выключателя','шт',650],['Чистовая электрика','Монтаж светильника','шт',1200],['Чистовая электрика','Подключение бытового оборудования','шт',900]
            ],
            'Сантехнические работы'=>[
                ['Демонтаж','Демонтаж сантехнических приборов','шт',1200],['Черновая сантехника','Разводка водоснабжения','точка',1800],['Черновая сантехника','Разводка канализации','точка',1600],['Черновая сантехника','Монтаж коллектора','шт',3500],['Чистовая сантехника','Монтаж смесителя','шт',1200],['Чистовая сантехника','Монтаж унитаза','шт',2200],['Чистовая сантехника','Монтаж инсталляции','шт',4500],['Чистовая сантехника','Подключение стиральной машины','шт',1200]
            ],
            'Строительство дома'=>[
                ['Подготовительные работы','Разработка грунта','м³',900],['Фундамент','Устройство монолитного фундамента','м³',8500],['Фундамент','Гидроизоляция фундамента','м²',450],['Коробка','Возведение стен','м²',3200],['Коробка','Устройство перекрытия','м²',4500],['Кровля','Устройство кровельного покрытия','м²',2200],['Окна и двери','Монтаж оконных блоков','шт',4500],['Электрика','Прокладка кабеля','м.п.',120],['Сантехника','Разводка водоснабжения','точка',1800],['Отделка','Штукатурка стен по маякам','м²',650]
            ]
        ];
        foreach($items as $templateName=>$rows){
            $tid=(int)($templateItems[$templateName]??0); if($tid<=0)continue;
            $checkItems->execute([$tid]); if((int)$checkItems->fetchColumn()>0)continue;
            $sort=0; foreach($rows as $row)$st->execute([$tid,$row[0],$row[1],$row[2],$row[3],++$sort]);
        }
    }

} catch (Throwable $e) {}

function measurement_type_for_name(string $name,string $unit=''):?string{
    $n=mb_strtolower(trim($name),'UTF-8');
    if(preg_match('/плинтус|периметр|м\.п\.?/u',$n)) return 'perimeter';
    if(preg_match('/потол|натяжн|потолоч/u',$n)) return 'ceiling';
    if(preg_match('/стен|обо[и́]и|обои|штукатур|шпакл|покраск.*стен|грунтов.*стен|выравнив.*стен/u',$n)) return 'walls';
    if(preg_match('/пол|ламинат|линолеум|паркет|стяжк|керамогранит|плитк|напольн|гидроизоляц.*пол/u',$n)) return 'floor';
    return null;
}
function project_measurement_quantities(PDO $pdo,int $projectId):array{
    $q=$pdo->prepare("SELECT id,length_m,width_m,height_m FROM smetogram_rooms WHERE project_id=? ORDER BY id");
    $q->execute([$projectId]); $rooms=$q->fetchAll();
    $out=['floor'=>0.0,'walls'=>0.0,'ceiling'=>0.0,'perimeter'=>0.0,'roomIds'=>[]];
    foreach($rooms as $r){
        $l=(float)$r['length_m']; $w=(float)$r['width_m']; $h=(float)$r['height_m'];
        if($l<=0||$w<=0||$h<=0) continue;
        $floor=$l*$w; $perimeter=2*($l+$w);
        $out['floor']+=$floor; $out['ceiling']+=$floor;
        $out['walls']+=$perimeter*$h; $out['perimeter']+=$perimeter;
        $out['roomIds'][]=(int)$r['id'];
    }
    foreach(['floor','walls','ceiling','perimeter'] as $k)$out[$k]=round($out[$k],3);
    return $out;
}
function refresh_measurement_estimate_items(PDO $pdo,int $projectId):void{
    $m=project_measurement_quantities($pdo,$projectId);
    $q=$pdo->prepare("SELECT i.id,i.measurementType FROM estimateitems i INNER JOIN estimatecategories c ON c.id=i.categoryId WHERE c.projectId=? AND i.quantitySource='measurement'");
    $q->execute([$projectId]); $items=$q->fetchAll();
    if(!$items)return;
    $roomIds=implode(',',array_map('intval',$m['roomIds']));
    $u=$pdo->prepare("UPDATE estimateitems SET quantity=?,measurementRoomIds=? WHERE id=?");
    foreach($items as $it){
        $type=(string)($it['measurementType']??'');
        $qty=isset($m[$type])?(float)$m[$type]:0;
        $u->execute([$qty,$roomIds,(int)$it['id']]);
    }
}
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function redirect(string $u):never{header('Location: '.$u);exit;}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Сессия формы устарела. Обновите страницу.');}}
function current_user():?array{return $_SESSION['user']??null;}
function is_owner(array $user):bool{return (string)($user['telegramId']??'')==='621736637';}
function is_admin(array $user):bool{return is_owner($user) || strtolower(trim((string)($user['role']??'')))==='admin';}
function can_access_project(PDO $pdo,array $user,int $projectId):bool{
    if($projectId<=0)return false;
    if(is_admin($user))return true;
    $q=$pdo->prepare('SELECT 1 FROM projects WHERE id=? AND ownerId=? LIMIT 1');
    $q->execute([$projectId,(int)$user['id']]);
    if($q->fetchColumn())return true;
    $q=$pdo->prepare('SELECT 1 FROM projectmembers WHERE projectId=? AND userId=? LIMIT 1');
    $q->execute([$projectId,(int)$user['id']]);
    return (bool)$q->fetchColumn();
}
function can_manage_project(PDO $pdo,array $user,int $projectId):bool{
    if($projectId<=0)return false;
    if(is_admin($user))return true;
    $q=$pdo->prepare('SELECT 1 FROM projects WHERE id=? AND ownerId=? LIMIT 1');
    $q->execute([$projectId,(int)$user['id']]);
    return (bool)$q->fetchColumn();
}
function create_notification(PDO $pdo, int $userId, ?int $projectId, string $type, string $title, string $body = '', ?string $url = null): void {
    if ($userId <= 0) return;
    $q = $pdo->prepare('INSERT INTO smetogram_notifications (userId,projectId,type,title,body,url) VALUES (?,?,?,?,?,?)');
    $q->execute([$userId, $projectId, $type, $title, $body, $url]);
}

function subscription_is_active(array $user):bool{
    if(is_admin($user))return true;
    $plan=trim((string)($user['subscriptionPlan']??'free'));
    $status=trim((string)($user['subscriptionStatus']??'active'));
    if($plan===''||$plan==='free'||$status!=='active')return false;
    $expires=trim((string)($user['subscriptionExpiresAt']??''));
    return $expires===''||strtotime($expires)===false||strtotime($expires)>=time();
}
function user_project_count(PDO $pdo,int $userId):int{
    $q=$pdo->prepare('SELECT COUNT(*) FROM projects WHERE ownerId=?');
    $q->execute([$userId]);
    return (int)$q->fetchColumn();
}
function can_create_project(PDO $pdo,array $user):bool{
    return is_admin($user)||subscription_is_active($user)||user_project_count($pdo,(int)($user['id']??0))<1;
}
function subscription_label(array $user):string{
    return subscription_is_active($user)?'Подписка активна':'Бесплатный доступ';
}

function require_auth():array{
    global $pdo;
    $session=current_user();
    if(!$session)redirect('login.php');
    $q=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $q->execute([(int)($session['id']??0)]);
    $fresh=$q->fetch();
    if(!$fresh){unset($_SESSION['user']);redirect('login.php');}
    // Владелец системы определяется по Telegram ID, а не по порядку регистрации.
    // Его права нельзя потерять из-за смены роли в админке.
    if((string)($fresh['telegramId']??'')==='621736637' && (string)($fresh['role']??'')!=='admin'){
        try{$pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([(int)$fresh['id']]);}catch(Throwable $e){}
        $fresh['role']='admin';
    }
    $_SESSION['user']=[
        'id'=>(int)$fresh['id'],'name'=>(string)($fresh['name']??''),'email'=>(string)($fresh['email']??''),
        'role'=>(string)($fresh['role']??'user'),'subscriptionPlan'=>(string)($fresh['subscriptionPlan']??'free'),
        'subscriptionStatus'=>(string)($fresh['subscriptionStatus']??'active'),
        'subscriptionExpiresAt'=>(string)($fresh['subscriptionExpiresAt']??''),
        'username'=>(string)($fresh['username']??''),'telegramId'=>$fresh['telegramId']??null
    ];
    return $_SESSION['user'];
}
