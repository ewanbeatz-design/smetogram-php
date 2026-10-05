<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

$telegram = $config['telegram'] ?? [];
$clientId = trim((string)($telegram['client_id'] ?? ''));
if ($clientId === '') {
    redirect('login.php');
}

$state = bin2hex(random_bytes(32));
$verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

$_SESSION['telegram_oidc_state'] = $state;
$_SESSION['telegram_oidc_verifier'] = $verifier;

$redirectUri = 'https://сметограм.рф/telegram-callback.php';

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid profile',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
];

header('Location: https://oauth.telegram.org/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), true, 302);
exit;
