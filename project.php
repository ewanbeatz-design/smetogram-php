<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
$user=require_auth();$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM projects WHERE id=? AND ownerId=?");$q->execute([$id,$user['id']]);$project=$q->fetch();if(!$project)redirect('dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$action=$_POST['action']??'';
 if($action==='category'){ $name=trim($_POST['name']??'');if($name!==''){$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$name,(int)$pdo->query("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=".(int)$id)->fetchColumn()]);}redirect('project.php?id='.$id);}
 if($action==='template'){ $template=(int)($_POST['template_id']??0);$qty=(float)str_replace(',','.',$_POST['template_quantity']??$_POST['quantity']??1);if($qty<=0)$qty=1;$q=$pdo->prepare("SELECT * FROM estimateitemtemplates WHERE id=? LIMIT 1");$q->execute([$template]);$it=$q->fetch();if($it){$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE projectId=? AND name=? LIMIT 1");$q->execute([$id,$it['categoryName']]);$cat=$q->fetchColumn();if(!$cat){$q=$pdo->prepare("SELECT COALESCE(MAX(sortOrder),0)+1 FROM estimatecategories WHERE projectId=?");$q->execute([$id]);$sort=(int)$q->fetchColumn();$q=$pdo->prepare("INSERT INTO estimatecategories(projectId,name,sortOrder) VALUES(?,?,?)");$q->execute([$id,$it['categoryName'],$sort]);$cat=$pdo->lastInsertId();}$q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");$q->execute([(int)$cat,$it['name'],$qty,$it['unit'],$it['price'],'template']);}redirect('project.php?id='.$id);}
 if($action==='item'){ $cat=(int)($_POST['category_id']??0);$q=$pdo->prepare("SELECT id FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);if($q->fetch()){ $name=trim($_POST['name']??'');$qty=(float)str_replace(',','.',$_POST['quantity']??0);$unit=trim($_POST['unit']??'шт');$price=(float)str_replace(',','.',$_POST['price']??0);if($name!==''){$q=$pdo->prepare("INSERT INTO estimateitems(categoryId,name,quantity,unit,price,source) VALUES(?,?,?,?,?,?)");$q->execute([$cat,$name,$qty,$unit,$price,'manual']);}}redirect('project.php?id='.$id);}
 if($action==='update_item'){ $item=(int)$_POST['item_id'];$q=$pdo->prepare("SELECT i.id FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([$item,$id]);if($q->fetch()){ $name=trim($_POST['name']??'');$qty=(float)str_replace(',','.',$_POST['quantity']??0);$unit=trim($_POST['unit']??'шт');$price=(float)str_replace(',','.',$_POST['price']??0);$q=$pdo->prepare("UPDATE estimateitems SET name=?,quantity=?,unit=?,price=? WHERE id=?");$q->execute([$name,$qty,$unit,$price,$item]);}redirect('project.php?id='.$id);}
 if($action==='delete_item'){ $q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE i.id=? AND c.projectId=?");$q->execute([(int)$_POST['item_id'],$id]);redirect('project.php?id='.$id);}
 if($action==='delete_category'){ $cat=(int)$_POST['category_id'];$q=$pdo->prepare("DELETE i FROM estimateitems i JOIN estimatecategories c ON c.id=i.categoryId WHERE c.id=? AND c.projectId=?");$q->execute([$cat,$id]);$q=$pdo->prepare("DELETE FROM estimatecategories WHERE id=? AND projectId=?");$q->execute([$cat,$id]);redirect('project.php?id='.$id);}
 if($action==='status'){ $allowed=['draft','in_progress','review','completed','archived'];$st=$_POST['status']??'draft';if(in_array($st,$allowed,true)){$q=$pdo->prepare("UPDATE projects SET status=? WHERE id=? AND ownerId=?");$q->execute([$st,$id,$user['id']]);}redirect('project.php?id='.$id);}
 if($action==='delete_project'){ $q=$pdo->prepare("DELETE FROM projects WHERE id=? AND ownerId=?");$q->execute([$id,$user['id']]);redirect('dashboard.php');}
}
$q=$pdo->prepare("SELECT c.*,COALESCE(SUM(i.quantity*i.price),0) total,COUNT(i.id) item_count FROM estimatecategories c LEFT JOIN estimateitems i ON i.categoryId=c.id WHERE c.projectId=? GROUP BY c.id ORDER BY c.sortOrder,c.id");$q->execute([$id]);$cats=$q->fetchAll();$total=0;$itemsCount=0;foreach($cats as &$cat){$q=$pdo->prepare("SELECT * FROM estimateitems WHERE categoryId=? ORDER BY id");$q->execute([$cat['id']]);$cat['items']=$q->fetchAll();$total+=(float)$cat['total'];$itemsCount+=(int)$cat['item_count'];}unset($cat);
$q=$pdo->query("SELECT * FROM estimateitemtemplates ORDER BY categoryName,name");$templates=$q->fetchAll();
$estimateOverhead=$total*.15;$estimateProfit=$total*.08;$estimateVat=($total+$estimateOverhead+$estimateProfit)*.20;$estimateGrandTotal=$total+$estimateOverhead+$estimateProfit+$estimateVat;
$label=['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'][$project['status']]??$project['status'];
$pageTitle=$project['name'];require __DIR__.'/includes/app_header.php';
?>
<section class="page-wrap estimate-page">
<div class="estimate-heading"><div><a href="dashboard.php" class="back-button"><i class="bi bi-arrow-left"></i> Все проекты</a><div class="estimate-title-row"><div class="project-symbol"><i class="bi bi-house"></i><i class="bi bi-check2"></i></div><div><div class="eyebrow">ПРОЕКТ / СМЕТА</div><h1><?=e($project['name'])?></h1><p><?=e($project['city'])?> <b>·</b> <?=e($project['clientName'])?> <b>·</b> <?=e($project['workType'])?></p></div></div></div><div class="heading-actions"><form method="post" data-ajax-estimate><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><select class="status-select" name="status" onchange="estimateStatus(this.form)"><?php foreach(['draft'=>'Черновик','in_progress'=>'В работе','review'=>'На согласовании','completed'=>'Завершён','archived'=>'Архив'] as $v=>$t):?><option value="<?=$v?>" <?=$project['status']===$v?'selected':''?>><?=$t?></option><?php endforeach;?></select></form><a class="outline-button" href="export.php?id=<?=$id?>"><i class="bi bi-file-earmark-text"></i> Экспорт сметы</a><button class="primary-button" type="button" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus-lg"></i> Добавить</button></div></div>
<div class="estimate-summary"><div><span>ИТОГО ПО СМЕТЕ</span><strong><?=number_format($estimateGrandTotal,0,',',' ')?> ₽</strong><small>включая НДС 20%</small></div><div class="summary-stats"><div><b><?=count($cats)?></b> категории</div><div><b><?=$itemsCount?></b> позиций</div><div><b><?=number_format(array_sum(array_map(fn($c)=>(float)$c['items']?0:0,$cats)),0)?></b> объём</div></div><div class="summary-actions"><button class="outline-button" type="button" onclick="document.querySelector('.estimate-controls').scrollIntoView({behavior:'smooth',block:'center'})"><i class="bi bi-calendar3"></i> Создать график</button><button class="icon-button darkish" type="button" title="Дополнительно"><i class="bi bi-three-dots"></i></button></div></div>
<div class="estimate-controls"><div><span class="control-label">МЕТОД РАСЧЁТА</span><select class="status-select"><option>Ресурсный</option><option>Базисно-индексный</option><option>Ресурсно-индексный</option></select></div><label class="check-control"><input type="checkbox"> Зимнее удорожание <b>+12%</b></label><label class="check-control"><input type="checkbox"> Стеснённые условия <b>+8%</b></label><label class="custom-coeff">Свой коэффициент<input min="0.1" max="5" step="0.01" type="number" value="1"></label></div><div class="estimate-toolbar"><div class="toolbar-tabs"><button type="button" class="active">Смета</button><button type="button">График <span>скоро</span></button><button type="button">Документы <span>скоро</span></button></div><div class="estimate-actions"><button type="button" class="text-button" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus-lg"></i> Добавить расценку</button><button type="button" class="text-button" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg"></i> Добавить категорию</button></div></div>
<div class="estimate-list">
<?php foreach($cats as $n=>$cat):?><div class="estimate-group"><div class="group-heading"><span class="group-number"><?=str_pad((string)($n+1),2,'0',STR_PAD_LEFT)?></span><h3><?=e($cat['name'])?></h3><span><?=$cat['item_count']?> позиций</span><strong><?=number_format((float)$cat['total'],0,',',' ')?> ₽</strong><button class="icon-button" title="Удалить раздел" onclick="if(confirm('Удалить раздел и его позиции?'))document.getElementById('delcat<?=$cat['id']?>').submit()"><i class="bi bi-three-dots"></i></button></div>
<div class="estimate-table"><div class="table-head"><span>РАБОТА</span><span>ОБЪЁМ</span><span>ЕД.</span><span>ЦЕНА</span><span>СУММА</span><span></span></div>
<?php foreach($cat['items'] as $item):$sum=(float)$item['quantity']*(float)$item['price'];?><div class="table-row" onclick="event.stopPropagation();editRow(<?=$item['id']?>)"><div class="work-name"><i class="work-dot"></i><?=e($item['name'])?></div><span><?=rtrim(rtrim(number_format((float)$item['quantity'],3,',',' '),'0'),',')?></span><span><?=e($item['unit'])?></span><span><?=number_format((float)$item['price'],2,',',' ')?> ₽</span><strong><?=number_format($sum,2,',',' ')?> ₽</strong><div class="row-actions"><button class="edit-label" type="button" title="Редактировать" onclick="editRow(<?=$item['id']?>)">Изменить</button><form id="edit<?=$item['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_item"><input type="hidden" name="item_id" value="<?=$item['id']?>"><input name="name" value="<?=e($item['name'])?>"><input name="quantity" value="<?=e((string)$item['quantity'])?>"><input name="unit" value="<?=e($item['unit'])?>"><input name="price" value="<?=e((string)$item['price'])?>"></form><button type="button" title="Удалить" onclick="if(confirm('Удалить позицию?'))document.getElementById('delete<?=$item['id']?>').submit()"><i class="bi bi-trash3"></i></button><form id="delete<?=$item['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?=$item['id']?>"></form></div></div><?php endforeach;?>
<button class="add-row" type="button" data-work-catalog data-category-id="<?=$cat['id']?>" data-bs-toggle="modal" data-bs-target="#workCatalogModal"><i class="bi bi-plus-lg"></i> Добавить работу</button></div></div>
<form id="delcat<?=$cat['id']?>" data-ajax-estimate method="post" style="display:none"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?=$cat['id']?>"></form>
<div class="modal fade" id="item<?=$cat['id']?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" data-ajax-estimate><div class="modal-header"><h5 class="modal-title">Добавить позицию</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="item"><input type="hidden" name="category_id" value="<?=$cat['id']?>"><label class="form-label">Наименование</label><input class="form-control mb-3" name="name" required placeholder="Штукатурка стен по маякам"><div class="form-grid"><div><label class="form-label">Количество</label><input class="form-control" name="quantity" value="1"></div><div><label class="form-label">Единица</label><input class="form-control" name="unit" value="м²"></div><div><label class="form-label">Цена</label><input class="form-control" name="price" value="0"></div></div></div><div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Добавить</button></div></form></div></div></div>
<?php endforeach;?>
<?php if(!$cats):?><div class="empty-state module-panel"><i class="bi bi-list-columns-reverse fs-2 text-primary"></i><h3 class="mt-3">Смета пока пустая</h3><p class="text-muted">Создайте первый раздел и добавьте работы или материалы.</p><button class="primary-button" data-bs-toggle="modal" data-bs-target="#categoryModal">Создать раздел</button></div><?php endif;?>
<div class="estimate-totals"><div><span>Прямые затраты</span><b><?=number_format($total,2,',',' ')?> ₽</b></div><div><span>Накладные расходы (НР) · 15%</span><b><?=number_format($estimateOverhead,2,',',' ')?> ₽</b></div><div><span>Сметная прибыль (СП) · 8%</span><b><?=number_format($estimateProfit,2,',',' ')?> ₽</b></div><div><span>Коэффициенты</span><b>× 1.000</b></div><div><span>НДС · 20%</span><b><?=number_format($estimateVat,2,',',' ')?> ₽</b></div><div class="grand-total"><span>Итого в текущем уровне цен</span><strong><?=number_format($estimateGrandTotal,2,',',' ')?> ₽</strong></div><a class="outline-button" href="export.php?id=<?=$id?>">Выбрать экспорт</a><a class="outline-button" href="export.php?id=<?=$id?>">Сформировать КС-2</a><a class="outline-button" href="export.php?id=<?=$id?>">Сформировать КС-3</a></div></div>
<div class="mt-4 d-flex justify-content-between align-items-center"><a class="text-button" href="workspace.php?view=scan&id=<?=$id?>"><i class="bi bi-stars"></i> Смета из файла</a><form method="post" onsubmit="return confirm('Удалить проект и всю смету?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_project"><button class="text-danger border-0 bg-transparent small">Удалить проект</button></form></div>
</section>
<div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Новый раздел</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="category"><label class="form-label">Название</label><input class="form-control" name="name" required placeholder="Материалы, Работы, Электрика..."></div><div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button">Создать</button></div></form></div></div></div>
<div class="modal fade" id="templateModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="post"><div class="modal-header"><div><h5 class="modal-title">Добавить из шаблонов</h5><small class="text-muted">Готовые позиции для текущего проекта</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="template"><div class="template-list"><?php foreach($templates as $tpl):?><label class="template-option"><input type="radio" name="template_id" value="<?=$tpl['id']?>" required><span class="template-info"><strong><?=e($tpl['name'])?></strong><small><?=e($tpl['categoryName'])?> · <?=e($tpl['unit'])?> · <?=number_format((float)$tpl['price'],0,',',' ')?> ₽</small></span><span class="template-check"><i class="bi bi-check-lg"></i></span></label><?php endforeach;?></div><div class="mt-3"><label class="form-label">Количество</label><input class="form-control" type="number" name="template_quantity" value="1" min="0.001" step="0.001" required></div></div><div class="modal-footer"><button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button><button class="primary-button"><i class="bi bi-plus-lg"></i> Добавить в смету</button></div></form></div></div></div>
<div class="modal fade" id="workCatalogModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg work-catalog-dialog">
<div class="modal-content work-catalog-modal">
<div class="modal-header">
 <div><h5 class="modal-title">Добавить работу</h5><small class="text-muted">Все доступные работы и актуальные цены</small></div>
 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
 <input type="hidden" id="workCatalogCategory">
 <div class="work-catalog-search"><i class="bi bi-search"></i><input id="workCatalogSearch" type="search" placeholder="Поиск работы..." autocomplete="off"></div>
 <div class="work-catalog-list" id="workCatalogList">
 <?php foreach($templates as $tpl):?>
 <button type="button" class="work-catalog-item" data-template-id="<?=$tpl['id']?>" data-name="<?=e(mb_strtolower($tpl['name'].' '.$tpl['categoryName']))?>">
  <span class="work-catalog-icon"><i class="bi bi-tools"></i></span>
  <span class="work-catalog-info"><strong><?=e($tpl['name'])?></strong><small><?=e($tpl['categoryName'])?> · <?=e($tpl['unit'])?></small></span>
  <span class="work-catalog-price"><?=number_format((float)$tpl['price'],0,',',' ')?> ₽</span>
  <span class="work-catalog-check"><i class="bi bi-check-lg"></i></span>
 </button>
 <?php endforeach;?>
 </div>
 <div class="work-catalog-empty" id="workCatalogEmpty" hidden>По вашему запросу работы не найдены.</div>
 <div class="work-catalog-selected" id="workCatalogSelected" hidden>
  <div><span>Выбрано</span><strong id="workCatalogSelectedName">—</strong></div>
  <label>Количество<input id="workCatalogQuantity" type="number" value="1" min="0.001" step="0.001"></label>
 </div>
</div>
<div class="modal-footer">
 <button class="outline-button" type="button" data-bs-dismiss="modal">Отмена</button>
 <button class="primary-button" id="workCatalogAdd" type="button" disabled><i class="bi bi-plus-lg"></i> Добавить в смету</button>
</div>
</div></div></div>

<script>
const projectId=<?=json_encode($id)?>;
async function estimateAjax(form){
 const fd=new FormData(form);
 fd.set('project_id',projectId);
 const action=fd.get('action');
 const op=action==='category'?'category':action==='item'?'item':action==='template'?'template':action==='update_item'?'update_item':action==='delete_item'?'delete_item':action==='delete_category'?'delete_category':action==='status'?'status':'';
 if(!op)return false;
 fd.set('op',op);
 try{
  const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
  const data=await res.json();
  if(!res.ok||!data.ok)throw new Error(data.error||'Не удалось сохранить');
  await refreshEstimate();
  return true;
 }catch(err){alert(err.message||'Ошибка сохранения');return false}
}
async function refreshEstimate(){
 const res=await fetch('project.php?id='+encodeURIComponent(projectId),{headers:{'X-Requested-With':'XMLHttpRequest'}});
 if(!res.ok)throw new Error('Не удалось обновить смету');
 const html=await res.text();
 const doc=new DOMParser().parseFromString(html,'text/html');
 const currentSummary=document.querySelector('.estimate-summary');
 const nextSummary=doc.querySelector('.estimate-summary');
 const currentList=document.querySelector('.estimate-list');
 const nextList=doc.querySelector('.estimate-list');
 if(currentSummary&&nextSummary)currentSummary.replaceWith(nextSummary);
 if(currentList&&nextList)currentList.replaceWith(nextList);
 const currentTabs=document.querySelector('.toolbar-tabs');
 const nextTabs=doc.querySelector('.toolbar-tabs');
 if(currentTabs&&nextTabs)currentTabs.replaceWith(nextTabs);
}
function bindEstimateForms(){
 document.querySelectorAll('form[data-ajax-estimate]').forEach(form=>{
  if(form.dataset.ajaxBound)return;
  form.dataset.ajaxBound='1';
  form.addEventListener('submit',async e=>{
   e.preventDefault();
   if(form.dataset.busy)return;
   form.dataset.busy='1';
   const ok=await estimateAjax(form);
   delete form.dataset.busy;
   if(ok){
    const modal=form.closest('.modal');
    if(modal){const instance=bootstrap.Modal.getInstance(modal);if(instance)instance.hide();}
   }
  });
 });
}
function editRow(id){
 const existing=document.querySelector('.table-row.is-editing');
 if(existing){ if(existing.dataset.itemId===String(id)) return; cancelInline(existing.dataset.itemId); return; }
 const f=document.getElementById('edit'+id);if(!f)return;
 const row=f.parentElement.closest('.table-row');f.remove();document.body.appendChild(f);
 const n=f.querySelector('[name="name"]').value,q=f.querySelector('[name="quantity"]').value,u=f.querySelector('[name="unit"]').value,p=f.querySelector('[name="price"]').value;
 row.innerHTML='<div class="work-name"><i class="work-dot"></i><input class="cell-input js-name" value="'+n.replace(/"/g,'&quot;')+'"></div><div><input class="cell-input js-qty" value="'+q+'"></div><div><input class="cell-input js-unit" value="'+u+'"></div><div><input class="cell-input js-price" value="'+p+'"></div><strong>—</strong><div class="row-actions"><button class="save-row" type="button" onclick="saveInline('+id+')"><i class="bi bi-check-lg"></i></button><button class="cancel-row" type="button" onclick="cancelInline('+id+')"><i class="bi bi-x-lg"></i></button></div>';
 row.dataset.itemId=String(id); row.classList.add('is-editing');
}
function saveInline(id){
 const row=document.querySelector('.table-row.is-editing[data-item-id="'+id+'"]');const f=document.getElementById('edit'+id);if(!row||!f)return;
 f.querySelector('[name="name"]').value=row.querySelector('.js-name').value;
 f.querySelector('[name="quantity"]').value=row.querySelector('.js-qty').value;
 f.querySelector('[name="unit"]').value=row.querySelector('.js-unit').value;
 f.querySelector('[name="price"]').value=row.querySelector('.js-price').value;
 estimateAjax(f);
}
function cancelInline(id){refreshEstimate().catch(()=>location.reload())}
document.addEventListener('DOMContentLoaded',bindEstimateForms);
const observer=new MutationObserver(bindEstimateForms);
observer.observe(document.body,{childList:true,subtree:true});

const workCatalogState={templateId:0,categoryId:0};
function resetWorkCatalog(){
 workCatalogState.templateId=0;workCatalogState.categoryId=0;
 const cat=document.getElementById('workCatalogCategory'), search=document.getElementById('workCatalogSearch'), qty=document.getElementById('workCatalogQuantity'), selected=document.getElementById('workCatalogSelected'), add=document.getElementById('workCatalogAdd');
 if(cat)cat.value='';if(search)search.value='';if(qty)qty.value='1';if(selected)selected.hidden=true;if(add)add.disabled=true;
 document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
 filterWorkCatalog();
}
function filterWorkCatalog(){
 const input=document.getElementById('workCatalogSearch');if(!input)return;
 const term=input.value.trim().toLowerCase();let visible=0;
 document.querySelectorAll('.work-catalog-item').forEach(item=>{
  const ok=!term||item.dataset.name.includes(term);item.hidden=!ok;if(ok)visible++;
 });
 const empty=document.getElementById('workCatalogEmpty');if(empty)empty.hidden=visible!==0;
}
function selectWorkCatalogItem(item){
 document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
 item.classList.add('is-selected');workCatalogState.templateId=Number(item.dataset.templateId);
 const selected=document.getElementById('workCatalogSelected'),name=document.getElementById('workCatalogSelectedName'),add=document.getElementById('workCatalogAdd');
 if(selected)selected.hidden=false;if(name)name.textContent=item.querySelector('strong')?.textContent||'Работа';if(add)add.disabled=!workCatalogState.categoryId;
}
async function addCatalogWork(){
 const add=document.getElementById('workCatalogAdd'),qty=document.getElementById('workCatalogQuantity');
 if(!workCatalogState.templateId||!workCatalogState.categoryId)return;
 add.disabled=true;
 const fd=new FormData();fd.set('csrf',<?=json_encode(csrf_token())?>);fd.set('project_id',projectId);fd.set('op','template');fd.set('template_id',workCatalogState.templateId);fd.set('category_id',workCatalogState.categoryId);fd.set('template_quantity',qty?.value||'1');
 try{const res=await fetch('api.php?action=estimate_action',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Не удалось добавить работу');await refreshEstimate();const modal=document.getElementById('workCatalogModal');const instance=bootstrap.Modal.getInstance(modal);if(instance)instance.hide();}catch(err){alert(err.message||'Ошибка добавления');}finally{add.disabled=false;}
}
document.addEventListener('click',e=>{
 const btn=e.target.closest('[data-work-catalog]');
 if(btn){
  workCatalogState.categoryId=Number(btn.dataset.categoryId||0);
  document.getElementById('workCatalogCategory').value=workCatalogState.categoryId;
  document.querySelectorAll('.work-catalog-item.is-selected').forEach(x=>x.classList.remove('is-selected'));
  workCatalogState.templateId=0;
  document.getElementById('workCatalogSelected').hidden=true;
  const add=document.getElementById('workCatalogAdd');if(add)add.disabled=true;
  return;
 }
 const item=e.target.closest('.work-catalog-item');
 if(item){selectWorkCatalogItem(item);}
});
document.addEventListener('DOMContentLoaded',()=>{
 const modal=document.getElementById('workCatalogModal'),search=document.getElementById('workCatalogSearch'),add=document.getElementById('workCatalogAdd');
 search?.addEventListener('input',filterWorkCatalog);
 add?.addEventListener('click',addCatalogWork);
 modal?.addEventListener('shown.bs.modal',()=>search?.focus());
 modal?.addEventListener('hidden.bs.modal',resetWorkCatalog);
});</script>
<?php require __DIR__.'/includes/app_footer.php';?>