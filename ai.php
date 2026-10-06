<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

$user = require_auth();

// Room photo AI is a JSON endpoint. Handle it before the legacy AI page's
// owner-only project lookup, so admins/employees and AJAX requests are not
// redirected to dashboard.php and returned as HTML instead of JSON.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'room_estimate') {
    require __DIR__.'/ai_estimate.php';
    exit;
}

$projectId = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = null;
if ($projectId <= 0) redirect('dashboard.php');
if ($projectId) {
    $q=$pdo->prepare("SELECT * FROM projects WHERE id=? AND ownerId=?");
    $q->execute([$projectId,$user['id']]);
    $project=$q->fetch();
    if(!$project) redirect('dashboard.php');
}
$aiConfig = is_file(__DIR__.'/config/ai.php') ? require __DIR__.'/config/ai.php' : [];
$aiEnabled = !empty($aiConfig['api_key']);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS smetogram_ai_scans (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        userId BIGINT UNSIGNED NOT NULL,
        projectId BIGINT UNSIGNED NULL,
        sourceType VARCHAR(32) NOT NULL,
        fileName VARCHAR(255) NULL,
        filePath VARCHAR(500) NULL,
        prompt TEXT NULL,
        resultJson LONGTEXT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(userId), INDEX(projectId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e) {}

function ai_json_response(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function ai_call(array $config, string $system, string $userText, ?string $imageData=null): array {
    if(empty($config['api_key'])) return ['ok'=>false,'error'=>'OPENAI_API_KEY не настроен'];
    $content=[['type'=>'text','text'=>$userText]];
    if($imageData) $content[]=['type'=>'image_url','image_url'=>['url'=>$imageData,'detail'=>'high']];
    $payload=[
        'model'=>$config['model'],
        'temperature'=>0.1,
        'messages'=>[
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>$content]
        ],
        'response_format'=>['type'=>'json_object']
    ];
    $ch=curl_init($config['base_url'].'/chat/completions');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$config['api_key'],'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT=>90,
    ]);
    $raw=curl_exec($ch);
    $err=curl_error($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($raw===false || $err) return ['ok'=>false,'error'=>$err ?: 'AI request failed'];
    $data=json_decode($raw,true);
    if($code>=400) return ['ok'=>false,'error'=>$data['error']['message'] ?? ('AI HTTP '.$code)];
    $text=$data['choices'][0]['message']['content'] ?? '';
    $json=json_decode($text,true);
    if(!is_array($json)) return ['ok'=>false,'error'=>'ИИ вернул некорректный JSON','raw'=>$text];
    return ['ok'=>true,'data'=>$json];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $action=$_POST['action']??'';
    if($action==='chat'){
        if(!$project) ai_json_response(['ok'=>false,'error'=>'Сначала выберите проект'],422);
        $prompt=trim($_POST['prompt']??'');
        if($prompt==='') ai_json_response(['ok'=>false,'error'=>'Введите запрос'],422);
        $system='Ты — Сметограм AI, помощник прораба и сметчика. Отвечай на русском. Анализируй строительные работы, материалы, объёмы и сметы. Возвращай JSON с полями answer (string), suggestions (array of strings).';
        $r=ai_call($aiConfig,$system,$prompt);
        if(!$r['ok']) ai_json_response(['ok'=>false,'error'=>$r['error']],502);
        ai_json_response(['ok'=>true,'answer'=>$r['data']['answer']??'','suggestions'=>$r['data']['suggestions']??[]]);
    }
    if($action==='analyze_image'){
        if(!$project) ai_json_response(['ok'=>false,'error'=>'Сначала выберите проект'],422);
        if(empty($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) ai_json_response(['ok'=>false,'error'=>'Не удалось загрузить изображение'],422);
        $file=$_FILES['image'];
        if((int)$file['size']>15*1024*1024) ai_json_response(['ok'=>false,'error'=>'Максимальный размер изображения — 15 МБ'],422);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)) ai_json_response(['ok'=>false,'error'=>'Поддерживаются JPG, PNG и WEBP'],422);
        $dir=__DIR__.'/uploads/ai';
        if(!is_dir($dir)) @mkdir($dir,0755,true);
        $safe=bin2hex(random_bytes(8)).'-'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename($file['name']));
        $path=$dir.'/'.$safe;
        if(!move_uploaded_file($file['tmp_name'],$path)) ai_json_response(['ok'=>false,'error'=>'Не удалось сохранить файл'],500);
        $base64='data:'.$mime.';base64,'.base64_encode((string)file_get_contents($path));
        $description=trim($_POST['description']??'');
        $system='Ты — строительный AI для подготовки черновой сметы. По фото помещения определи тип помещения, примерные размеры только если их можно обоснованно оценить, видимые поверхности и строительные работы. Не выдумывай точные размеры. Верни JSON: room, area, confidence, detected (array), tasks (array объектов name, unit, quantity, confidence, note), warnings (array).';
        $prompt='Проанализируй это фото строительного объекта. Дополнительное описание: '.($description?:'нет');
        $r=ai_call($aiConfig,$system,$prompt,$base64);
        $result=$r['ok']?$r['data']:['error'=>$r['error'],'room'=>'','tasks'=>[]];
        $status=$r['ok']?'analyzed':'error';
        $q=$pdo->prepare("INSERT INTO smetogram_ai_scans(userId,projectId,sourceType,fileName,filePath,prompt,resultJson,status) VALUES(?,?,?,?,?,?,?,?)");
        $q->execute([$user['id'],$projectId,'image',$file['name'],'uploads/ai/'.$safe,$prompt,json_encode($result,JSON_UNESCAPED_UNICODE),$status]);
        ai_json_response(['ok'=>$r['ok'],'scan_id'=>(int)$pdo->lastInsertId(),'result'=>$result,'enabled'=>$aiEnabled],$r['ok']?200:502);
    }
    if($action==='apply_scan'){
        $scanId=(int)($_POST['scan_id']??0);
        $q=$pdo->prepare("SELECT * FROM smetogram_ai_scans WHERE id=? AND userId=? AND projectId=?");
        $q->execute([$scanId,$user['id'],$projectId]);$scan=$q->fetch();
        if(!$scan) ai_json_response(['ok'=>false,'error'=>'Результат не найден'],404);
        if(!$projectId) ai_json_response(['ok'=>false,'error'=>'Сначала выберите проект'],422);
        $data=json_decode((string)$scan['resultJson'],true) ?: [];
        $tasks=$data['tasks']??[];
        if(!$tasks) ai_json_response(['ok'=>false,'error'=>'В результате нет позиций для добавления'],422);
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");
            $categoryName='ИИ — '.trim((string)($data['room']??'Распознанные работы')); if($categoryName==='ИИ —')$categoryName='ИИ — Распознанные работы';
            $q->execute([$projectId,$categoryName,999]);
            $cat=(int)$pdo->lastInsertId();
            $qi=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");
            foreach($tasks as $task){
                $name=trim((string)($task['name']??'')); if($name==='') continue;
                $qty=(float)($task['quantity']??1);$unit=(string)($task['unit']??'шт.');
                $qi->execute([$cat,$name,$qty,$unit,0,'ai']);
            }
            $pdo->commit();
            ai_json_response(['ok'=>true,'category_id'=>$cat]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();ai_json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
    }
}

$pageTitle='ИИ и распознавание';
require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap ai-page">
  <div class="page-heading">
    <div><div class="eyebrow">SMETOGRAM AI</div><h1>ИИ, распознавание и импорт</h1><p class="lede">Фото помещения → распознавание → черновик сметы → экспорт.</p></div>
    <?php if($project): ?><a class="primary-button" href="project.php?id=<?=$projectId?>"><i class="bi bi-arrow-left"></i> Вернуться в смету</a><?php endif; ?>
  </div>

  <?php if(!$aiEnabled): ?>
  <div class="ai-notice"><i class="bi bi-info-circle"></i><div><strong>ИИ-провайдер ещё не подключён</strong><span>Интерфейс и сохранение результатов уже готовы. Для реального распознавания нужен OPENAI_API_KEY на сервере.</span></div></div>
  <?php endif; ?>

  <div class="ai-grid">
    <div class="module-panel ai-chat-panel">
      <div class="panel-heading"><div><div class="eyebrow">AI ASSISTANT</div><h2>Помощник сметчика</h2><p>Расчёты, работы, материалы и проверка сметы.</p></div><i class="bi bi-stars ai-icon"></i></div>
      <div id="aiChat" class="ai-chat-messages"><div class="ai-empty"><i class="bi bi-stars"></i><strong>Чем помочь?</strong><span>Например: «Проверь эту смету на пропущенные работы»</span></div></div>
      <form id="aiChatForm" class="ai-chat-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="chat"><input name="prompt" autocomplete="off" placeholder="Спросите про смету, объёмы или материалы…"><button class="primary-button"><i class="bi bi-send"></i></button></form>
    </div>

    <div class="module-panel">
      <div class="panel-heading"><div><div class="eyebrow">VISION AI</div><h2>Распознать фото</h2><p>Помещения, поверхности и видимые работы.</p></div><i class="bi bi-camera ai-icon"></i></div>
      <form id="aiScanForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="analyze_image"><input type="hidden" name="project_id" value="<?=$projectId?>">
        <label class="ai-upload"><i class="bi bi-cloud-arrow-up"></i><strong>Загрузите фото</strong><span>JPG, PNG или WEBP · до 15 МБ</span><input id="aiImage" type="file" name="image" accept="image/jpeg,image/png,image/webp" capture="environment" required></label>
        <textarea name="description" rows="3" placeholder="Дополнительно: кухня, 18 м², нужна чистовая отделка…"></textarea>
        <button class="primary-button w-100" type="submit"><i class="bi bi-stars"></i> Запустить распознавание</button>
      </form>
      <div id="aiResult" class="ai-result d-none"></div>
    </div>
  </div>

  <div class="module-panel ai-import-panel">
    <div class="panel-heading"><div><div class="eyebrow">IMPORT / EXPORT</div><h2>Файлы и экспорт</h2><p>Работа с готовыми сметами без привязки к Node.js.</p></div></div>
    <div class="ai-actions">
      <?php if($project): ?>
      <a class="outline-button" href="export.php?id=<?=$projectId?>&format=print"><i class="bi bi-filetype-pdf"></i> Печать / PDF</a>
      <a class="outline-button" href="export.php?id=<?=$projectId?>&format=xlsx"><i class="bi bi-file-earmark-spreadsheet"></i> Excel XLSX</a>
      <a class="outline-button" href="export.php?id=<?=$projectId?>&format=csv"><i class="bi bi-filetype-csv"></i> CSV</a>
      <a class="outline-button" href="workspace.php?view=scan&id=<?=$projectId?>"><i class="bi bi-upload"></i> Импорт сметы</a>
      <?php else: ?><a class="outline-button" href="dashboard.php"><i class="bi bi-folder2-open"></i> Выберите проект</a><?php endif; ?>
    </div>
  </div>
</section>
<script>
const aiRoot='ai.php';
const csrf=<?=json_encode(csrf_token())?>;
const chat=document.getElementById('aiChat'), chatForm=document.getElementById('aiChatForm');
chatForm?.addEventListener('submit',async e=>{e.preventDefault();const input=chatForm.querySelector('[name=prompt]');const text=input.value.trim();if(!text)return;chat.insertAdjacentHTML('beforeend','<div class="ai-bubble user"></div>');chat.lastElementChild.textContent=text;input.value='';try{const fd=new FormData(chatForm);const r=await fetch(aiRoot,{method:'POST',body:fd});const d=await r.json();if(!d.ok)throw new Error(d.error);const el=document.createElement('div');el.className='ai-bubble assistant';el.textContent=d.answer||'Готово';chat.appendChild(el);chat.scrollTop=chat.scrollHeight;}catch(err){const el=document.createElement('div');el.className='ai-bubble error';el.textContent=err.message;chat.appendChild(el);}});
const scanForm=document.getElementById('aiScanForm'), resultBox=document.getElementById('aiResult');
scanForm?.addEventListener('submit',async e=>{e.preventDefault();const btn=scanForm.querySelector('button[type=submit]');btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Анализирую…';try{const r=await fetch(aiRoot,{method:'POST',body:new FormData(scanForm)});const d=await r.json();if(!d.ok)throw new Error(d.error);window.__aiScanId=d.scan_id;const x=d.result||{};resultBox.classList.remove('d-none');resultBox.innerHTML='<strong>Распознано: '+escapeHtml(x.room||'Помещение')+'</strong><div class="ai-detected">'+(x.detected||[]).map(v=>'<span>'+escapeHtml(v)+'</span>').join('')+'</div><div class="ai-tasks">'+(x.tasks||[]).map(t=>'<div><span>'+escapeHtml(t.name||'Работа')+'</span><b>'+escapeHtml(String(t.quantity??''))+' '+escapeHtml(t.unit||'')+'</b></div>').join('')+'</div><?php if($projectId): ?><button type="button" class="outline-button" onclick="applyAIScan()">Добавить в смету</button><?php endif; ?>';}catch(err){resultBox.classList.remove('d-none');resultBox.innerHTML='<div class="ai-error">'+escapeHtml(err.message)+'</div>';}finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-stars"></i> Запустить распознавание';}});
async function applyAIScan(){if(!window.__aiScanId)return;const fd=new FormData();fd.append('csrf',csrf);fd.append('action','apply_scan');fd.append('scan_id',window.__aiScanId);fd.append('project_id','<?=$projectId?>');const r=await fetch(aiRoot,{method:'POST',body:fd});const d=await r.json();if(d.ok){location.href='project.php?id=<?=$projectId?>';}else{alert(d.error||'Не удалось добавить результат');}}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
</script>
<?php require __DIR__.'/includes/app_footer.php';?>
