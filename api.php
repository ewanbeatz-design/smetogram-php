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
if($action==='estimate_action'){
 check_csrf();
 $id=(int)($_POST['project_id']??0);
 $q=$pdo->prepare("SELECT id FROM projects WHERE id=? AND ownerId=? LIMIT 1");
 $q->execute([$id,$user['id']]);
 if(!$q->fetch()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Проект не найден'],JSON_UNESCAPED_UNICODE);exit;}
 $op=$_POST['op']??'';
 if($op==='category'){
  $name=trim((string)($_POST['name']??''));
  if($name===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Введите название раздела'],JSON_UNESCAPED_UNICODE);exit;}
  $q=$pdo->prepare("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?");$q->execute([$id]);$sort=(int)$q->fetchColumn();
  $q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$name,$sort]);
 }elseif($op==='item'){
  $cat=(int)($_POST['category_id']??0);$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);
  if(!$q->fetch()){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Раздел не найден'],JSON_UNESCAPED_UNICODE);exit;}
  $name=trim((string)($_POST['name']??''));$qty=(float)str_replace(',','.',(string)($_POST['quantity']??0));$unit=trim((string)($_POST['unit']??'шт'));$price=(float)str_replace(',','.',(string)($_POST['price']??0));
  if($name===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Введите наименование'],JSON_UNESCAPED_UNICODE);exit;}
  $q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");$q->execute([$cat,$name,$qty,$unit,$price,'manual']);
 }elseif($op==='template'){
  $template=(int)($_POST['template_id']??0);$qty=(float)str_replace(',','.',(string)($_POST['template_quantity']??$_POST['quantity']??1));if($qty<=0)$qty=1;
  $q=$pdo->prepare("SELECT * FROM estimateitemtemplates WHERE id=? LIMIT 1");$q->execute([$template]);$it=$q->fetch();
  if(!$it){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Шаблон не найден'],JSON_UNESCAPED_UNICODE);exit;}
  $cat=(int)($_POST['category_id']??0);
  if($cat>0){
   $q=$pdo->prepare("SELECT id FROM estimatecategories WHERE id=? AND projectId=? LIMIT 1");$q->execute([$cat,$id]);$cat=$q->fetchColumn();
   if(!$cat){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Раздел не найден'],JSON_UNESCAPED_UNICODE);exit;}
  }else{
   $q=$pdo->prepare("SELECT id FROM estimatecategories WHERE projectId=? AND name=? LIMIT 1");$q->execute([$id,$it['categoryName']]);$cat=$q->fetchColumn();
   if(!$cat){$q=$pdo->prepare("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?");$q->execute([$id]);$sort=(int)$q->fetchColumn();$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$it['categoryName'],$sort]);$cat=$pdo->lastInsertId();}
  }
  $q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");$q->execute([(int)$cat,$it['name'],$qty,$it['unit'],$it['price'],'template']);
 }elseif($op==='update_item'){
  $item=(int)($_POST['item_id']??0);$q=$pdo->prepare("SELECT i.id FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([$item,$id]);
  if(!$q->fetch()){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Позиция не найдена'],JSON_UNESCAPED_UNICODE);exit;}
  $name=trim((string)($_POST['name']??''));$qty=(float)str_replace(',','.',(string)($_POST['quantity']??0));$unit=trim((string)($_POST['unit']??'шт'));$price=(float)str_replace(',','.',(string)($_POST['price']??0));
  if($name===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Введите наименование'],JSON_UNESCAPED_UNICODE);exit;}
  $q=$pdo->prepare("UPDATE estimateitems SET name=?,quantity=?,unit=?,price=? WHERE id=?");$q->execute([$name,$qty,$unit,$price,$item]);
 }elseif($op==='delete_item'){
  $q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([(int)($_POST['item_id']??0),$id]);
 }elseif($op==='delete_category'){
  $cat=(int)($_POST['category_id']??0);$q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.id=? AND c.projectId=?");$q->execute([$cat,$id]);$q=$pdo->prepare("DELETE FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);
 }elseif($op==='status'){
  $allowed=['draft','in_progress','review','completed','archived'];$st=(string)($_POST['status']??'draft');
  if(!in_array($st,$allowed,true)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Недопустимый статус'],JSON_UNESCAPED_UNICODE);exit;}
  $q=$pdo->prepare("UPDATE projects SET status=? WHERE id=? AND ownerId=?");$q->execute([$st,$id,$user['id']]);
 }else{http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Неизвестное действие'],JSON_UNESCAPED_UNICODE);exit;}
 echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);exit;
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