<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

$telegram=$config['telegram']??[];
$clientId=trim((string)($telegram['client_id']??''));
$clientSecret=trim((string)($telegram['client_secret']??''));
$redirectUri='https://xn--80aff1adjpdl.xn--p1ai/telegram-callback.php';

function tg_post(string $url,array $data,array $headers=[]):array{
 $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data,'','&',PHP_QUERY_RFC3986),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/x-www-form-urlencoded'],$headers),CURLOPT_TIMEOUT=>15]);
 $raw=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
 if($raw===false||$err!=='')throw new RuntimeException('Не удалось связаться с Telegram.');
 $json=json_decode($raw,true); if(!is_array($json))throw new RuntimeException('Telegram вернул некорректный ответ.');
 if($status<200||$status>=300)throw new RuntimeException((string)($json['error_description']??$json['error']??'Telegram отклонил авторизацию.'));
 return $json;
}
function tg_get(string $url):string{
 $ch=curl_init($url);
 curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_FOLLOWLOCATION=>true,
  CURLOPT_CONNECTTIMEOUT=>5,
  CURLOPT_TIMEOUT=>10,
  CURLOPT_HTTPHEADER=>['Accept: application/json'],
 ]);
 $raw=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 if($raw===false||$err!=='')throw new RuntimeException('Не удалось получить данные Telegram: '.$err);
 if($status<200||$status>=300)throw new RuntimeException('Telegram вернул ошибку при получении ключей (HTTP '.$status.').');
 return (string)$raw;
}
function tg_b64(string $v):string{$v=strtr($v,'-_','+/');$v.=str_repeat('=',(4-strlen($v)%4)%4);$r=base64_decode($v,true);if($r===false)throw new RuntimeException('Некорректный Telegram token.');return $r;}
function tg_len(int $n):string{if($n<128)return chr($n);$s='';while($n>0){$s=chr($n&255).$s;$n>>=8;}return chr(128|strlen($s)).$s;}
function tg_pem(array $j):string{
 if(($j['kty']??'')!=='RSA')throw new RuntimeException('Неподдерживаемый ключ Telegram.');
 $int=function(string $v):string{$v=ltrim(tg_b64($v),"\0");if($v===''||(ord($v[0])&128))$v="\0".$v;return "\x02".tg_len(strlen($v)).$v;};
 $rsa=$int((string)$j['n']).$int((string)$j['e']);$rsa="\x30".tg_len(strlen($rsa)).$rsa;
 $alg="\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";$bit="\x03".tg_len(strlen("\0".$rsa))."\0".$rsa;$der="\x30".tg_len(strlen($alg)+strlen($bit)).$alg.$bit;
 return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
}
function tg_verify(string $token,string $clientId):array{
 $p=explode('.',$token);if(count($p)!==3)throw new RuntimeException('Некорректный ID-токен Telegram.');
 $h=json_decode(tg_b64($p[0]),true);$c=json_decode(tg_b64($p[1]),true);$sig=tg_b64($p[2]);
 if(!is_array($h)||!is_array($c)||($h['alg']??'')!=='RS256')throw new RuntimeException('Неподдерживаемая подпись Telegram.');
 $keys=json_decode(tg_get('https://oauth.telegram.org/.well-known/jwks.json'),true);$key=null;
 foreach(($keys['keys']??[]) as $k)if((string)($k['kid']??'')===(string)($h['kid']??'')){$key=$k;break;}
 if(!$key)throw new RuntimeException('Ключ подписи Telegram не найден.');
 $pk=openssl_pkey_get_public(tg_pem($key));if($pk===false||openssl_verify($p[0].'.'.$p[1],$sig,$pk,OPENSSL_ALGO_SHA256)!==1)throw new RuntimeException('Не удалось проверить подпись Telegram.');
 $aud=$c['aud']??'';$ok=is_array($aud)?in_array($clientId,array_map('strval',$aud),true):(string)$aud===$clientId;
 $now=time();if(($c['iss']??'')!=='https://oauth.telegram.org'||!$ok)throw new RuntimeException('Telegram-токен выдан для другого приложения.');
 if((int)($c['exp']??0)<$now||(int)($c['iat']??0)>$now+60)throw new RuntimeException('Срок действия Telegram-токена истёк.');
 return $c;
}
try{
 if($clientId===''||$clientSecret==='')throw new RuntimeException('Telegram OAuth не настроен: нужен Client ID и Client Secret.');
 if(!empty($_GET['error']))throw new RuntimeException((string)($_GET['error_description']??$_GET['error']));
 $code=trim((string)($_GET['code']??''));$state=trim((string)($_GET['state']??''));
 $saved=(string)($_SESSION['telegram_oidc_state']??($_COOKIE['telegram_oidc_state']??''));$verifier=(string)($_SESSION['telegram_oidc_verifier']??($_COOKIE['telegram_oidc_verifier']??''));
 unset($_SESSION['telegram_oidc_state'],$_SESSION['telegram_oidc_verifier']);
setcookie('telegram_oidc_state', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
setcookie('telegram_oidc_verifier', '', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
 if($code===''||$state===''||$saved===''||!hash_equals($saved,$state)||$verifier==='')throw new RuntimeException('Сессия авторизации Telegram устарела. Повторите вход.');
 $tokens=tg_post('https://oauth.telegram.org/token',['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$redirectUri,'client_id'=>$clientId,'code_verifier'=>$verifier],['Authorization: Basic '.base64_encode($clientId.':'.$clientSecret)]);
 $claims=tg_verify((string)($tokens['id_token']??''),$clientId);
 $tid=(int)($claims['id']??$claims['sub']??0);if($tid<=0)throw new RuntimeException('Telegram ID не найден.');
 $name=trim((string)($claims['name']??''));if($name==='')$name=trim((string)($claims['given_name']??'').' '.(string)($claims['family_name']??''));
 $username=trim((string)($claims['preferred_username']??''));if($name==='')$name=$username!==''?'@'.$username:'Пользователь Telegram';
 $pdo->beginTransaction();$q=$pdo->prepare('SELECT id,name,email FROM users WHERE telegramId=? LIMIT 1');$q->execute([$tid]);$user=$q->fetch();
 if($user){$u=$pdo->prepare('UPDATE users SET name=?,telegramUsername=?,loginMethod=?,lastSignedIn=CURRENT_TIMESTAMP WHERE id=?');$u->execute([$name,$username!==''?$username:null,'telegram',(int)$user['id']]);$uid=(int)$user['id'];}
 else{$base=$username!==''?preg_replace('/[^a-zA-Z0-9_]/','_',$username):'telegram_'.$tid;$base=trim((string)$base,'_')?:'telegram_'.$tid;$internal=$base;$n=1;$check=$pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');while(true){$check->execute([$internal]);if(!$check->fetchColumn())break;$internal=$base.'_'.$n++;}$open='telegram_'.bin2hex(random_bytes(16));$ins=$pdo->prepare('INSERT INTO users(openId,name,email,loginMethod,role,password_hash,username,telegramId,telegramUsername,lastSignedIn) VALUES(?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');$ins->execute([$open,$name,null,'telegram','user',null,$internal,$tid,$username!==''?$username:null]);$uid=(int)$pdo->lastInsertId();}
 $pdo->commit();session_regenerate_id(true);$_SESSION['user']=['id'=>$uid,'name'=>$name,'email'=>$user['email']??''];redirect('dashboard.php');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['telegram_login_error']=$e->getMessage();redirect('login.php?telegram_error=1');}
