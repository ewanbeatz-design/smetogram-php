<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';

$user=require_auth();
$id=(int)($_GET['id']??0);
$format=(string)($_GET['format']??'print');

$q=$pdo->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");
$q->execute([$id]);
$p=$q->fetch();
if(!$p || !can_access_project($pdo,$user,$id)) redirect('dashboard.php');

try { refresh_measurement_estimate_items($pdo,$id); } catch(Throwable $ignore) {}

$q=$pdo->prepare("SELECT c.name category,i.id,i.name,i.quantity,i.unit,i.price,i.quantitySource,i.measurementType,(i.quantity*i.price) total
FROM estimateitems i
JOIN estimatecategories c ON c.id=i.categoryId
WHERE c.projectId=? ORDER BY c.sortOrder,c.id,i.id");
$q->execute([$id]);
$rows=$q->fetchAll();

$total=array_sum(array_map(fn($r)=>(float)$r['total'],$rows));
$overhead=$total*0.15;
$profit=$total*0.08;
$vat=($total+$overhead+$profit)*0.20;
$totalWithVat=$total+$overhead+$profit+$vat;
$docNo=(string)($_GET['no']??($id.'-'.date('Y')));
$date=(string)($_GET['date']??date('d.m.Y'));
$period=(string)($_GET['period']??date('m.Y'));

function money(float $v): string { return number_format($v,2,',',' '); }
function docHead(array $p,string $title,string $no,string $date): void {
    echo '<div class="doc-head"><div><div class="doc-title">'.e($title).'</div><div class="doc-sub">по проекту: '.e($p['name']).'</div></div><div class="doc-meta">№ '.e($no).'<br>от '.e($date).'</div></div>';
}

if($format==='csv'){
    header('Content-Type:text/csv; charset=UTF-8');
    header('Content-Disposition:attachment; filename="smetogram-'.$id.'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['Раздел','Наименование','Количество','Ед.','Цена','Сумма','Источник количества'],';');
    foreach($rows as $r) {
        $source=($r['quantitySource']??'')==='measurement'?'Автоматически из замеров':'Вручную';
        fputcsv($out,[$r['category'],$r['name'],$r['quantity'],$r['unit'],$r['price'],$r['total'],$source],';');
    }
    fclose($out); exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?=e($p['name'])?> — <?= $format==='ks2'?'КС-2':($format==='ks3'?'КС-3':'Смета') ?></title>
<style>
@page{size:<?= $format==='ks2'?'A4 landscape':'A4' ?>;margin:10mm}
*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#111;font-size:10px;margin:0}
.doc{width:100%}.doc-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:12px}.doc-title{font-size:18px;font-weight:700;text-transform:uppercase}.doc-sub{font-size:11px;margin-top:4px}.doc-meta{text-align:right;font-size:11px;line-height:1.6}
.info{width:100%;border-collapse:collapse;margin-bottom:10px}.info td{padding:3px 5px;border:1px solid #999}.info .label{width:22%;font-weight:700;background:#f5f5f5}
table.data{width:100%;border-collapse:collapse}table.data th,table.data td{border:1px solid #555;padding:4px 5px;vertical-align:middle}table.data th{text-align:center;font-size:8px;background:#f1f1f1}table.data td.num{text-align:right;white-space:nowrap}.center{text-align:center}.total-row{font-weight:700;background:#f5f5f5}.note{font-size:8px;color:#555;margin-top:7px}.sign{display:flex;gap:45px;margin-top:22px}.sign>div{flex:1}.line{border-bottom:1px solid #111;height:22px;margin-bottom:4px}.muted{font-size:8px;color:#555}.export-toolbar{position:fixed;right:16px;top:16px;z-index:20;display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.print,.export-btn{display:inline-flex;align-items:center;justify-content:center;background:#111;color:#fff;border:0;padding:9px 13px;border-radius:7px;cursor:pointer;text-decoration:none;font-size:12px;font-weight:600}.export-btn{background:#fff;color:#111;border:1px solid #bbb}.export-btn:hover{background:#f5f5f5;color:#111}.print:hover{background:#222}
@media print{.export-toolbar{display:none}.page-break{page-break-before:always}}
</style>
</head>
<body>
<div class="export-toolbar">
 <button class="print" onclick="window.print()"><span>Печать / PDF</span></button>
 <a href="export.php?id=<?=$id?>&format=csv&no=<?=rawurlencode($docNo)?>&date=<?=rawurlencode($date)?>" class="export-btn">CSV</a>
 <a href="export.php?id=<?=$id?>&format=ks2&no=<?=rawurlencode($docNo)?>&date=<?=rawurlencode($date)?>&period=<?=rawurlencode($period)?>" class="export-btn">КС-2</a>
 <a href="export.php?id=<?=$id?>&format=ks3&no=<?=rawurlencode($docNo)?>&date=<?=rawurlencode($date)?>&period=<?=rawurlencode($period)?>" class="export-btn">КС-3</a>
 <a href="project.php?id=<?=$id?>" class="export-btn">Вернуться к смете</a>
</div>
<div class="doc">
<?php if($format==='ks2'): ?>
<?php docHead($p,'Акт о приемке выполненных работ',$docNo,$date); ?>
<table class="info">
<tr><td class="label">Инвестор</td><td></td><td class="label">Форма по ОКУД</td><td>0322005</td></tr>
<tr><td class="label">Заказчик</td><td><?=e((string)$p['clientName'])?></td><td class="label">Проект</td><td><?=e((string)$p['name'])?></td></tr>
<tr><td class="label">Подрядчик</td><td><?=e((string)$user['name'])?></td><td class="label">Место выполнения</td><td><?=e((string)$p['city'])?></td></tr>
<tr><td class="label">Договор</td><td></td><td class="label">Отчетный период</td><td><?=e($period)?></td></tr>
</table>
<table class="data">
<thead><tr>
<th style="width:4%">№</th><th style="width:6%">Поз. сметы</th><th>Наименование работ</th><th style="width:7%">Ед.</th><th style="width:8%">Объем</th><th style="width:11%">Цена без НДС, ₽</th><th style="width:12%">Стоимость без НДС, ₽</th><th style="width:7%">НДС</th><th style="width:11%">НДС, ₽</th><th style="width:12%">Стоимость с НДС, ₽</th>
</tr></thead>
<tbody>
<?php foreach($rows as $n=>$r): $line=(float)$r['total']; $lineVat=$line*.20; ?>
<tr>
<td class="center"><?=$n+1?></td><td class="center"><?=e((string)($n+1))?></td><td><?=e((string)$r['name'])?></td><td class="center"><?=e((string)$r['unit'])?></td><td class="num"><?=e((string)$r['quantity'])?></td><td class="num"><?=money((float)$r['price'])?></td><td class="num"><?=money($line)?></td><td class="center">20%</td><td class="num"><?=money($lineVat)?></td><td class="num"><?=money($line+$lineVat)?></td>
</tr>
<?php endforeach; ?>
<tr class="total-row"><td colspan="6">ВСЕГО ПО АКТУ</td><td class="num"><?=money($total)?></td><td></td><td class="num"><?=money($vat)?></td><td class="num"><?=money($totalWithVat)?></td></tr>
</tbody>
</table>
<div class="note">Документ сформирован на основании позиций сметы проекта. Объемы в текущей версии КС-2 принимаются равными объемам, указанным в смете; перед подписанием их следует проверить по фактически выполненным и принятым работам.</div>
<div class="sign"><div><b>Подрядчик</b><div class="line"></div><span class="muted">должность, Ф.И.О., подпись</span></div><div><b>Заказчик</b><div class="line"></div><span class="muted">должность, Ф.И.О., подпись</span></div></div>

<?php elseif($format==='ks3'): ?>
<?php docHead($p,'Справка о стоимости выполненных работ и затрат',$docNo,$date); ?>
<table class="info">
<tr><td class="label">Инвестор</td><td></td><td class="label">Форма по ОКУД</td><td>0322001</td></tr>
<tr><td class="label">Заказчик / Генподрядчик</td><td><?=e((string)$p['clientName'])?></td><td class="label">Проект</td><td><?=e((string)$p['name'])?></td></tr>
<tr><td class="label">Подрядчик</td><td><?=e((string)$user['name'])?></td><td class="label">Место строительства</td><td><?=e((string)$p['city'])?></td></tr>
<tr><td class="label">Договор</td><td></td><td class="label">Отчетный период</td><td><?=e($period)?></td></tr>
</table>
<table class="data">
<thead><tr><th style="width:6%">№</th><th>Наименование работ / затрат</th><th style="width:18%">Стоимость без НДС, ₽</th><th style="width:18%">НДС 20%, ₽</th><th style="width:20%">Стоимость с НДС, ₽</th></tr></thead>
<tbody>
<?php foreach($rows as $n=>$r): $line=(float)$r['total']; $lineVat=$line*.20; ?>
<tr><td class="center"><?=$n+1?></td><td><?=e((string)$r['name'])?></td><td class="num"><?=money($line)?></td><td class="num"><?=money($lineVat)?></td><td class="num"><?=money($line+$lineVat)?></td></tr>
<?php endforeach; ?>
<tr class="total-row"><td colspan="2">ВСЕГО</td><td class="num"><?=money($total)?></td><td class="num"><?=money($vat)?></td><td class="num"><?=money($totalWithVat)?></td></tr>
</tbody>
</table>
<p><b>Стоимость выполненных работ и затрат за отчетный период:</b> <?=money($totalWithVat)?> руб., в том числе НДС 20% — <?=money($vat)?> руб.</p>
<div class="sign"><div><b>Подрядчик</b><div class="line"></div><span class="muted">должность, Ф.И.О., подпись</span></div><div><b>Заказчик / Генподрядчик</b><div class="line"></div><span class="muted">должность, Ф.И.О., подпись</span></div></div>
<div class="note">КС-3 формируется на основании КС-2. В данной адаптации строки группируются непосредственно из позиций сметы проекта.</div>

<?php else: ?>
<?php docHead($p,'Смета',$docNo,$date); ?>
<table class="data"><thead><tr><th>Раздел</th><th>Наименование</th><th class="num">Кол-во</th><th>Ед.</th><th class="num">Цена</th><th class="num">Сумма</th><th>Источник</th></tr></thead><tbody>
<?php foreach($rows as $r): $measurement=($r['quantitySource']??'')==='measurement'; ?><tr><td><?=e($r['category'])?></td><td><?=e($r['name'])?></td><td class="num"><?=e((string)$r['quantity'])?></td><td><?=e($r['unit'])?></td><td class="num"><?=money((float)$r['price'])?></td><td class="num"><?=money((float)$r['total'])?></td><td class="center"><?= $measurement ? 'Замеры' : 'Вручную' ?></td></tr><?php endforeach; ?>
<tr class="summary-row"><td colspan="5">Прямые затраты</td><td class="num"><?=money($total)?></td><td></td></tr>
<tr class="summary-row"><td colspan="5">Накладные расходы 15%</td><td class="num"><?=money($overhead)?></td><td></td></tr>
<tr class="summary-row"><td colspan="5">Сметная прибыль 8%</td><td class="num"><?=money($profit)?></td><td></td></tr>
<tr class="summary-row"><td colspan="5">НДС 20%</td><td class="num"><?=money($vat)?></td><td></td></tr>
<tr class="total-row"><td colspan="5">ИТОГО С НДС</td><td class="num"><?=money($totalWithVat)?></td><td></td></tr></tbody></table>
<div class="note">Позиции с источником «Замеры» рассчитываются автоматически по сохранённой геометрии помещений проекта.</div>
<?php endif; ?>
</div>
</body></html>