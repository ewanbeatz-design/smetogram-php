<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();

$orderId=(int)($_GET['order_id']??0);
if($orderId<=0) redirect('workspace.php?view=billing');

$q=$pdo->prepare('SELECT * FROM smetogram_subscription_orders WHERE id=? AND userId=? LIMIT 1');
$q->execute([$orderId,(int)$user['id']]);
$order=$q->fetch();
if(!$order) redirect('workspace.php?view=billing');

try{
    if((string)$order['status']==='paid'){
        $_SESSION['flash_notice']='Оплата подтверждена. Тариф активен.';
    }elseif(!empty($order['providerPaymentId'])){
        $payment=yookassa_request('GET','/payments/'.rawurlencode((string)$order['providerPaymentId']));
        if(activate_subscription_order($pdo,$orderId,$payment)){
            $_SESSION['flash_notice']='Оплата подтверждена. Тариф активирован на 1 месяц.';
        }elseif((string)($payment['status']??'')==='canceled'){
            $pdo->prepare('UPDATE smetogram_subscription_orders SET status=? WHERE id=? AND userId=?')->execute(['canceled',$orderId,(int)$user['id']]);
            $_SESSION['flash_error']='Платёж отменён. Тариф не активирован.';
        }else{
            $_SESSION['flash_notice']='Платёж ещё обрабатывается. После подтверждения тариф активируется автоматически.';
        }
    }
}catch(Throwable $e){
    $_SESSION['flash_error']='Не удалось проверить оплату: '.$e->getMessage();
}
redirect('workspace.php?view=billing');
