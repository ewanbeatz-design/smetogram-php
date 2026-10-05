<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

if (current_user()) redirect('dashboard.php');

$telegram = $config['telegram'] ?? [];
$botToken = trim((string)($telegram['bot_token'] ?? ''));

if ($botToken === '') {
    http_response_code(503);
    exit('Telegram-авторизация не настроена.');
}

$received = [];
foreach (['id','first_name','last_name','username','photo_url','auth_date','hash'] as $key) {
    if (isset($_GET[$key])) {
        $received[$key] = trim((string)$_GET[$key]);
    }
}

$hash = $received['hash'] ?? '';
unset($received['hash']);

if ($hash === '' || !isset($received['id'], $received['auth_date'])) {
    http_response_code(400);
    exit('Некорректные данные авторизации Telegram.');
}

$authDate = (int)$received['auth_date'];
if ($authDate <= 0 || abs(time() - $authDate) > 86400) {
    http_response_code(403);
    exit('Срок действия авторизации Telegram истёк.');
}

ksort($received);
$checkLines = [];
foreach ($received as $key => $value) {
    $checkLines[] = $key.'='.$value;
}
$dataCheckString = implode("\n", $checkLines);

$secretKey = hash('sha256', $botToken, true);
$expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($expectedHash, $hash)) {
    http_response_code(403);
    exit('Не удалось проверить авторизацию Telegram.');
}

$telegramId = (int)$received['id'];
$firstName = trim((string)($received['first_name'] ?? ''));
$lastName = trim((string)($received['last_name'] ?? ''));
$username = trim((string)($received['username'] ?? ''));
$name = trim($firstName.' '.$lastName);
if ($name === '') $name = $username !== '' ? '@'.$username : 'Пользователь Telegram';

try {
    $pdo->beginTransaction();

    $q = $pdo->prepare('SELECT id,name,email,telegramId,telegramUsername FROM users WHERE telegramId = ? LIMIT 1');
    $q->execute([$telegramId]);
    $user = $q->fetch();

    if ($user) {
        $update = $pdo->prepare('UPDATE users SET name = ?, telegramUsername = ?, loginMethod = ?, lastSignedIn = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$name, $username !== '' ? $username : null, 'telegram', (int)$user['id']]);
        $userId = (int)$user['id'];
    } else {
        $openId = 'telegram_'.bin2hex(random_bytes(16));
        $insert = $pdo->prepare('INSERT INTO users (openId,name,email,loginMethod,role,password_hash,telegramId,telegramUsername,lastSignedIn) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $insert->execute([$openId,$name,null,'telegram','user',null,$telegramId,$username !== '' ? $username : null]);
        $userId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $name,
        'email' => $user['email'] ?? '',
    ];

    redirect('dashboard.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    exit('Не удалось создать сессию. Попробуйте ещё раз.');
}
