<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();
$id=(int)($_GET['id']??0);
$q=$pdo->prepare('SELECT d.*,p.name AS projectName,p.city,p.clientName FROM projectdocuments d INNER JOIN projects p ON p.id=d.projectId WHERE d.id=? LIMIT 1');
$q->execute([$id]); $doc=$q->fetch();
if(!$doc || !can_access_project($pdo,$user,(int)$doc['projectId'])){http_response_code(404);exit('Документ не найден.');} $doc=$q->fetch();
if(!$doc){http_response_code(404);exit('Документ не найден.');}
$f=$pdo->prepare('SELECT * FROM smetogram_document_files WHERE documentId=? AND projectId=? ORDER BY createdAt DESC,id DESC');
$f->execute([$id,$doc['projectId']]); $files=$f->fetchAll();
$pageTitle=(string)$doc['title'];
require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap document-page">
  <div class="module-heading">
    <div><div class="eyebrow">ДОКУМЕНТООБОРОТ</div><h1><?=e($doc['title'])?></h1><p class="lede"><?=e($doc['projectName'].' · '.($doc['city']??'').' · '.($doc['clientName']??''))?></p></div>
    <div class="module-icon"><i class="bi bi-file-earmark-text"></i></div>
  </div>
  <div class="document-detail-grid">
    <div class="module-panel">
      <div class="panel-heading"><div><h2>Файлы документа</h2><p><?=e(mb_strtoupper((string)$doc['type']))?> · <?=e((string)$doc['status'])?></p></div><div class="d-flex gap-2"><a class="outline-button" href="workspace.php?view=documents&id=<?= (int)$doc['projectId'] ?>"><i class="bi bi-arrow-left"></i> Документы</a><label class="primary-button mb-0"><i class="bi bi-upload"></i> Загрузить<input hidden type="file" name="document_file" form="documentUploadForm" onchange="document.getElementById('documentUploadForm').submit()"></label></div></div>
      <form id="documentUploadForm" method="post" action="workspace.php?view=documents&amp;id=<?= (int)$doc['projectId'] ?>" enctype="multipart/form-data" class="d-none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload_document"><input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>"></form>
      <?php if(!$files): ?><div class="empty-state">Файл ещё не загружен. Вернитесь в документы проекта и добавьте файл.</div>
      <?php else: foreach($files as $file): ?>
        <div class="document-row">
          <div class="member-avatar"><i class="bi bi-file-earmark"></i></div>
          <div><strong><?=e($file['originalName'])?></strong><span><?=e($file['mime'])?> · <?=number_format(((int)$file['sizeBytes'])/1024/1024,2,',',' ')?> МБ</span></div>
          <em><?=e($file['createdAt'])?></em>
          <a class="outline-button" target="_blank" href="document_file.php?id=<?= (int)$file['id'] ?>"><i class="bi bi-box-arrow-up-right"></i> Открыть</a>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="module-panel">
      <h2>Документ</h2>
      <div class="document-meta-list">
        <span><small>Тип</small><b><?=e(mb_strtoupper((string)$doc['type']))?></b></span>
        <span><small>Статус</small><b><?=e((string)$doc['status'])?></b></span>
        <span><small>Создан</small><b><?=e((string)($doc['createdAt']??''))?></b></span>
        <span><small>Файлов</small><b><?=count($files)?></b></span>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__.'/includes/app_footer.php'; ?>