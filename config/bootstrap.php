<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(500);exit('Конфигурация БД не создана. Запустите GitHub Actions deploy.');}
$config=require $configFile;
$dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);
try{$pdo=new PDO($dsn,$config['db']['user'],$config['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(PDOException $e){http_response_code(500);exit('Не удалось подключиться к базе данных. Проверьте параметры MySQL в REG.RU.');}
try{$has=$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_hash'")->fetchColumn();if(!$has)$pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");}catch(Throwable $e){}

// Compatibility for installations where the original estimate item table was created
// with snake_case names (estimate_items) instead of the legacy Smetogram name (estimateitems).
try {
    $tableExists = static function(PDO $pdo, string $table): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $q->execute([$table]);
        return (bool)$q->fetchColumn();
    };
    if (!$tableExists($pdo, 'estimateitems')) {
        $pdo->exec("CREATE TABLE estimateitems (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categoryId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
            unit VARCHAR(40) NOT NULL DEFAULT 'шт.',
            price DECIMAL(14,2) NOT NULL DEFAULT 0,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            INDEX(categoryId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if ($tableExists($pdo, 'estimate_items')) {
            try {
                $pdo->exec("INSERT INTO estimateitems (id,categoryId,name,quantity,unit,price,source)
                    SELECT id,category_id,name,quantity,unit,price,source FROM estimate_items");
            } catch (Throwable $e) {
                // Keep the compatibility table usable even if the legacy/new schemas differ slightly.
            }
        }
    }
} catch (Throwable $e) {
    // Do not break the application bootstrap because of a compatibility migration.
}

function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function redirect(string $u):never{header('Location: '.$u);exit;}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Сессия формы устарела. Обновите страницу.');}}
function current_user():?array{return $_SESSION['user']??null;}
function require_auth():array{if(!current_user())redirect('login.php');return current_user();}
