<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(500);exit('Конфигурация БД не создана. Запустите GitHub Actions deploy.');}
$config=require $configFile;
$dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);
try{$pdo=new PDO($dsn,$config['db']['user'],$config['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(PDOException $e){http_response_code(500);exit('Не удалось подключиться к базе данных. Проверьте параметры MySQL в REG.RU.');}
try{$has=$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_hash'")->fetchColumn();if(!$has)$pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");}catch(Throwable $e){}
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function redirect(string $u):never{header('Location: '.$u);exit;}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Сессия формы устарела. Обновите страницу.');}}
function current_user():?array{return $_SESSION['user']??null;}
function require_auth():array{if(!current_user())redirect('login.php');return current_user();}
