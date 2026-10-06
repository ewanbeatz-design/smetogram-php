<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
header('Content-Type: application/json; charset=utf-8');
function ai_json(array $data,int $status=200){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') ai_json(['ok'=>false,'error'=>'POST required'],405);
check_csrf();
$projectId=(int)($_POST['project_id']??0);
$roomId=(int)($_POST['room_id']??0);
if($projectId<=0 || $roomId<=0 || !can_manage_project($pdo,$user,$projectId)) ai_json(['ok'=>false,'error'=>'Недостаточно прав.'],403);
$q=$pdo->prepare('SELECT id,name,length_m,width_m,height_m FROM smetogram_rooms WHERE id=? AND project_id=? LIMIT 1');$q->execute([$roomId,$projectId]);$room=$q->fetch();
if(!$room) ai_json(['ok'=>false,'error'=>'Комната не найдена.'],404);
$ids=$_POST['photo_ids']??[];if(!is_array($ids))$ids=[$ids];$ids=array_values(array_filter(array_map('intval',$ids)));if(!$ids)ai_json(['ok'=>false,'error'=>'Выберите фото.'],422);$ids=array_slice($ids,0,$model==='qwen/qwen3.8-27b'?3:5);
$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id,path,mime FROM smetogram_room_photos WHERE project_id=? AND room_id=? AND id IN ($ph)");$q->execute(array_merge([$projectId,$roomId],$ids));$photos=$q->fetchAll();
if(!$photos)ai_json(['ok'=>false,'error'=>'Фотографии не найдены.'],404);
$config=is_file(__DIR__.'/config/config.php')?require __DIR__.'/config/config.php':[];$key=(string)(getenv('GROQ_API_KEY')?:($config['ai']['groq_api_key']??''));$model=(string)(getenv('GROQ_VISION_MODEL')?:($config['ai']['groq_vision_model']??'qwen/qwen3.8-27b'));
// Groq retired Llama 4 Scout on 17.07.2026. Also cap Qwen 3.8 at its current 3-image limit.
if(in_array($model,['meta-llama/llama-4-scout-17b-16e-instruct','qwen/qwen3.6-27b'],true)) $model='qwen/qwen3.8-27b';
if($key==='')ai_json(['ok'=>false,'error'=>'Не настроен GROQ_API_KEY.'],503);
$catalog=[];try{$s=$pdo->query('SELECT name,unit,price FROM estimateitemtemplates ORDER BY id LIMIT 250');$catalog=$s->fetchAll();}catch(Throwable $e){}
$catalogText='';foreach($catalog as $r)$catalogText.='- '.$r['name'].' | '.$r['unit'].' | '.$r['price']."\n";
$area=(float)$room['length_m']*(float)$room['width_m'];$walls=2*((float)$room['length_m']+(float)$room['width_m'])*(float)$room['height_m'];$perimeter=2*((float)$room['length_m']+(float)$room['width_m']);
$prompt='Ты помощник сметчика. Проанализируй фото помещения и составь черновой список видимых работ ремонта. Не выдумывай скрытые инженерные работы. Количество для пола и потолка='.$area.' м2, стен='.$walls.' м2, плинтуса='.$perimeter.' м.п. Используй расценки из каталога. Верни только JSON: {"room_type":"","summary":"","items":[{"name":"","unit":"м2","quantity":0,"price":0,"measurementType":"floor|walls|ceiling|perimeter|count|manual","confidence":0.8,"reason":"","needs_review":false}]} Каталог:\n'.$catalogText;
$content=[['type'=>'text','text'=>$prompt]];
foreach($photos as $p){$file=__DIR__.'/'.$p['path'];if(!is_file($file))continue;$mime=$p['mime'];if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))continue;$bytes=file_get_contents($file);if($bytes===false)continue;$content[]=['type'=>'image_url','image_url'=>['url'=>'data:'.$mime.';base64,'.base64_encode($bytes)]];}
if(count($content)<2)ai_json(['ok'=>false,'error'=>'Для AI доступны JPG, PNG и WebP.'],422);
$payload=['model'=>$model,'messages'=>[['role'=>'user','content'=>$content]],'temperature'=>0.1,'max_completion_tokens'=>3000,'response_format'=>['type'=>'json_object']];
$ch=curl_init('https://api.groq.com/openai/v1/chat/completions');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>90]);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
if($raw===false||$err!=='')ai_json(['ok'=>false,'error'=>'Ошибка соединения с AI: '.$err],502);$data=json_decode($raw,true);if($http<200||$http>=300){$msg=(string)($data['error']['message']??'Groq AI вернул ошибку.');ai_json(['ok'=>false,'error'=>'Groq: '.$msg.' (HTTP '.$http.', модель '.$model.')'],502);}
$answer=trim((string)($data['choices'][0]['message']['content']??''));$answer=trim(preg_replace('/^```(?:json)?\s*|\s*```$/u','',$answer));$result=json_decode($answer,true);
if(!is_array($result))ai_json(['ok'=>false,'error'=>'AI вернул некорректный JSON.'],502);
$items=[];foreach(($result['items']??[]) as $item){$name=trim((string)($item['name']??''));$qty=(float)($item['quantity']??0);$conf=(float)($item['confidence']??0);if($name===''||$qty<=0||$conf<0.65)continue;$items[]=['name'=>$name,'unit'=>(string)($item['unit']??'шт.'),'quantity'=>round($qty,3),'price'=>round((float)($item['price']??0),2),'measurementType'=>(string)($item['measurementType']??'manual'),'confidence'=>round($conf,2),'reason'=>(string)($item['reason']??''),'needs_review'=>(bool)($item['needs_review']??false)];}
ai_json(['ok'=>true,'room'=>['id'=>$roomId,'name'=>$room['name']],'room_type'=>(string)($result['room_type']??''),'summary'=>(string)($result['summary']??''),'items'=>$items]);