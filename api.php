<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
header('Content-Type: application/json; charset=utf-8');
$action=$_GET['action']??'';
if($action==='search'){
 $q=trim((string)($_GET['q']??''));
 if(mb_strlen($q)<2){echo json_encode(['items'=>[]],JSON_UNESCAPED_UNICODE);exit;}
 $like='%'.$q.'%'; $items=[];
 $s=$pdo->prepare("SELECT id,name,city,clientName,status FROM projects WHERE ownerId=? AND (name LIKE ? OR city LIKE ? OR clientName LIKE ?) ORDER BY updatedAt DESC,id DESC LIMIT 8");
 $s->execute([$user['id'],$like,$like,$like]);
 foreach($s->fetchAll() as $p){$items[]=['type'=>'project','title'=>$p['name'],'meta'=>trim(($p['city']??'').' · '.($p['clientName']??'')),'group'=>'Проект','url'=>'project.php?id='.(int)$p['id']];}
 $s=$pdo->prepare("SELECT d.id,d.title,d.type,d.projectId,p.name AS projectName FROM projectdocuments d INNER JOIN projects p ON p.id=d.projectId WHERE p.ownerId=? AND d.title LIKE ? ORDER BY d.createdAt DESC,d.id DESC LIMIT 6");
 $s->execute([$user['id'],$like]);
 foreach($s->fetchAll() as $d){$items[]=['type'=>'document','title'=>$d['title'],'meta'=>($d['projectName']??'').' · '.mb_strtoupper((string)$d['type']),'group'=>'Документ','url'=>'workspace.php?view=documents&id='.(int)$d['projectId']];}
 $s=$pdo->prepare("SELECT i.id,i.name,i.unit,i.price,c.projectId,p.name AS projectName FROM estimateitems i INNER JOIN estimatecategories c ON c.id=i.categoryId INNER JOIN projects p ON p.id=c.projectId WHERE p.ownerId=? AND i.name LIKE ? ORDER BY i.id DESC LIMIT 6");
 $s->execute([$user['id'],$like]);
 foreach($s->fetchAll() as $i){$items[]=['type'=>'estimate','title'=>$i['name'],'meta'=>($i['projectName']??'').' · '.number_format((float)$i['price'],2,',',' ').' ₽/'.($i['unit']??'шт.'),'group'=>'Позиция сметы','url'=>'project.php?id='.(int)$i['projectId']];}
 echo json_encode(['items'=>array_slice($items,0,15)],JSON_UNESCAPED_UNICODE);exit;
}
if($action==='notifications'){
 $since=max(0,(int)($_GET['since']??0));
 $s=$pdo->prepare("SELECT id,projectId,type,title,body,url,isRead,createdAt FROM smetogram_notifications WHERE userId=? AND id>? ORDER BY id DESC LIMIT 30");
 $s->execute([$user['id'],$since]);$items=$s->fetchAll();
 $u=$pdo->prepare("SELECT COUNT(*) FROM smetogram_notifications WHERE userId=? AND isRead=0");
 $u->execute([$user['id']]);
 echo json_encode(['items'=>$items,'unread'=>(int)$u->fetchColumn()],JSON_UNESCAPED_UNICODE);exit;
}
if($action==='read_notifications'){
 check_csrf();
 $pdo->prepare("UPDATE smetogram_notifications SET isRead=1 WHERE userId=?")->execute([$user['id']]);
 echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);exit;
}
http_response_code(404);echo json_encode(['error'=>'Not found'],JSON_UNESCAPED_UNICODE);