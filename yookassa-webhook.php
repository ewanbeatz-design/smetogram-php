<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$raw=file_get_contents('php://input') ?: '';
$event=json_decode($raw,true);
if(!is_array($event)){
    http_response_code(400);
    echo json_encode(['ok'=>false],JSON_UNESCAPED_UNICODE);
    exit;
}

$object=$event['object']??[];
$providerPaymentId=(string)($object['id']??'');
if($providerPaymentId===''){
    http_response_code(400);
    echo json_encode(['ok'=>false],JSON_UNESCAPED_UNICODE);
    exit;
}

try{
    // Получаем платёж напрямую из ЮKassa и сверяем сумму, валюту и metadata
    // прежде чем активировать подписку.
    $payment=yookassa_request('GET','/payments/'.rawurlencode($providerPaymentId));
    $q=$pdo->prepare('SELECT id FROM smetogram_subscription_orders WHERE providerPaymentId=? LIMIT 1');
    $q->execute([$providerPaymentId]);
    $orderId=(int)($q->fetchColumn()??0);

    if($orderId>0){
        if(activate_subscription_order($pdo,$orderId,$payment)){
            echo json_encode(['ok'=>true,'status'=>'paid'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if((string)($payment['status']??'')==='canceled'){
            $pdo->prepare('UPDATE smetogram_subscription_orders SET status=? WHERE id=? AND status<>?')->execute(['canceled',$orderId,'paid']);
        }
    }

    echo json_encode(['ok'=>true,'status'=>(string)($payment['status']??'unknown')],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false],JSON_UNESCAPED_UNICODE);
}
