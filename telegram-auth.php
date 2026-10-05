<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (current_user()) {
    echo json_encode(['redirect' => 'dashboard.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

$telegram = $config['telegram'] ?? [];
$clientId = trim((string)($telegram['client_id'] ?? ''));
if ($clientId === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Telegram-авторизация не настроена.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$idToken = is_array($payload) ? trim((string)($payload['id_token'] ?? '')) : '';

if ($idToken === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Telegram не передал токен авторизации.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function telegram_b64url_decode(string $value): string {
    $value = strtr($value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode($value, true);
    if ($decoded === false) throw new RuntimeException('Invalid base64url data.');
    return $decoded;
}

function telegram_asn1_length(int $length): string {
    if ($length < 128) return chr($length);
    $bytes = '';
    while ($length > 0) {
        $bytes = chr($length & 255).$bytes;
        $length >>= 8;
    }
    return chr(0x80 | strlen($bytes)).$bytes;
}

function telegram_asn1_integer(string $binary): string {
    $binary = ltrim($binary, "\0");
    if ($binary === '') $binary = "\0";
    if (ord($binary[0]) & 0x80) $binary = "\0".$binary;
    return "\x02".telegram_asn1_length(strlen($binary)).$binary;
}

function telegram_jwk_to_pem(array $jwk): string {
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        throw new RuntimeException('Unsupported Telegram signing key.');
    }
    $modulus = telegram_b64url_decode((string)$jwk['n']);
    $exponent = telegram_b64url_decode((string)$jwk['e']);
    $rsa = telegram_asn1_integer($modulus).telegram_asn1_integer($exponent);
    $rsa = "\x30".telegram_asn1_length(strlen($rsa)).$rsa;
    $bitString = "\x00".$rsa;
    $algorithm = "\x30".telegram_asn1_length(13)."\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $der = "\x30".telegram_asn1_length(strlen($algorithm) + 2 + strlen($bitString)).$algorithm."\x03".telegram_asn1_length(strlen($bitString)).$bitString;
    return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
}

function telegram_verify_id_token(string $token, string $clientId, string $expectedNonce): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new RuntimeException('Некорректный Telegram ID-токен.');

    $header = json_decode(telegram_b64url_decode($parts[0]), true);
    $claims = json_decode(telegram_b64url_decode($parts[1]), true);
    $signature = telegram_b64url_decode($parts[2]);

    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256') {
        throw new RuntimeException('Неподдерживаемая подпись Telegram.');
    }

    $jwksRaw = @file_get_contents('https://oauth.telegram.org/.well-known/jwks.json');
    if ($jwksRaw === false) throw new RuntimeException('Не удалось получить ключ Telegram.');

    $jwks = json_decode($jwksRaw, true);
    $kid = (string)($header['kid'] ?? '');
    $jwk = null;
    foreach (($jwks['keys'] ?? []) as $candidate) {
        if ((string)($candidate['kid'] ?? '') === $kid) {
            $jwk = $candidate;
            break;
        }
    }
    if (!$jwk) throw new RuntimeException('Ключ подписи Telegram не найден.');

    $publicKey = openssl_pkey_get_public(telegram_jwk_to_pem($jwk));
    if ($publicKey === false || openssl_verify($parts[0].'.'.$parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('Не удалось проверить подпись Telegram.');
    }

    $now = time();
    $iss = (string)($claims['iss'] ?? '');
    $aud = $claims['aud'] ?? '';
    $audOk = is_array($aud) ? in_array($clientId, array_map('strval', $aud), true) : ((string)$aud === $clientId);

    if ($iss !== 'https://oauth.telegram.org' || !$audOk) {
        throw new RuntimeException('Telegram-токен выдан для другого приложения.');
    }
    if ((int)($claims['exp'] ?? 0) < $now || (int)($claims['iat'] ?? 0) > $now + 60) {
        throw new RuntimeException('Срок действия Telegram-токена истёк.');
    }
    if ($expectedNonce === '' || !hash_equals($expectedNonce, (string)($claims['nonce'] ?? ''))) {
        throw new RuntimeException('Не удалось проверить сессию Telegram.');
    }

    return $claims;
}

try {
    $expectedNonce = (string)($_SESSION['telegram_login_nonce'] ?? '');
    unset($_SESSION['telegram_login_nonce']);
    $claims = telegram_verify_id_token($idToken, $clientId, $expectedNonce);

    $telegramId = (int)($claims['id'] ?? $claims['sub'] ?? 0);
    if ($telegramId <= 0) throw new RuntimeException('Telegram ID не найден.');

    $name = trim((string)($claims['name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($claims['given_name'] ?? '').' '.(string)($claims['family_name'] ?? ''));
    }
    $username = trim((string)($claims['preferred_username'] ?? ''));
    if ($name === '') $name = $username !== '' ? '@'.$username : 'Пользователь Telegram';

    $pdo->beginTransaction();

    $q = $pdo->prepare('SELECT id,name,email FROM users WHERE telegramId = ? LIMIT 1');
    $q->execute([$telegramId]);
    $user = $q->fetch();

    if ($user) {
        $update = $pdo->prepare('UPDATE users SET name = ?, telegramUsername = ?, loginMethod = ?, lastSignedIn = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$name, $username !== '' ? $username : null, 'telegram', (int)$user['id']]);
        $userId = (int)$user['id'];
    } else {
        $openId = 'telegram_'.bin2hex(random_bytes(16));
        $baseUsername = $username !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '_', $username) : 'telegram_'.$telegramId;
        $baseUsername = trim((string)$baseUsername, '_');
        if ($baseUsername === '') $baseUsername = 'telegram_'.$telegramId;
        $internalUsername = $baseUsername;
        $suffix = 1;
        $checkUsername = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        while (true) {
            $checkUsername->execute([$internalUsername]);
            if (!$checkUsername->fetchColumn()) break;
            $internalUsername = $baseUsername.'_'.$suffix++;
        }

        $insert = $pdo->prepare('INSERT INTO users (openId,name,email,loginMethod,role,password_hash,username,telegramId,telegramUsername,lastSignedIn) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $insert->execute([$openId,$name,null,'telegram','user',null,$internalUsername,$telegramId,$username !== '' ? $username : null]);
        $userId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $name,
        'email' => $user['email'] ?? '',
    ];

    echo json_encode(['redirect' => 'dashboard.php'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
