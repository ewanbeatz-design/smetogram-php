<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
$id=(int)($_GET['id']??0);
$q=$pdo->prepare('SELECT f.* FROM smetogram_document_files f INNER JOIN projects p ON p.id=f.projectId WHERE f.id=? AND p.ownerId=? LIMIT 1');
$q->execute([$id,$user['id']]); $file=$q->fetch();
if(!$file){http_response_code(404);exit('Файл не найден.');}
$path=__DIR__.'/'.$file['path'];
if(!is_file($path)){http_response_code(404);exit('Файл отсутствует на сервере.');}
$mime=(string)$file['mime'];
$inline=in_array($mime,['application/pdf','image/jpeg','image/png'],true);
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('X-Content-Type-Options: nosniff');
$name=str_replace(['"',"\r","\n"],['',' ',' '],basename((string)$file['originalName']));
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"');
readfile($path);