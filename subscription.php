<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require_auth();
$projectId=(int)($_GET['id']??0);
$target='workspace.php?view=billing'.($projectId>0?'&id='.$projectId:'');
redirect($target);
