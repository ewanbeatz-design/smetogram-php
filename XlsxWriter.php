<?php
declare(strict_types=1);

function xlsx_cell(string $value, int $row, int $col): string {
    $letters='';
    while($col>0){$col--; $letters=chr(65+($col%26)).$letters; $col=intdiv($col,26);}
    $v=htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
    return '<c r="'.$letters.$row.'" t="inlineStr"><is><t>'.$v.'</t></is></c>';
}
function xlsx_download(string $filename, array $rows): never {
    if(!class_exists('ZipArchive')) {
        header('Content-Type:text/csv; charset=UTF-8');
        header('Content-Disposition:attachment; filename="'.preg_replace('/\.xlsx$/','.csv',$filename).'"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        foreach($rows as $row) fputcsv($out,$row,';');
        fclose($out); exit;
    }
    $tmp=tempnam(sys_get_temp_dir(),'smetogram_xlsx_');
    $zip=new ZipArchive();
    if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){@unlink($tmp);throw new RuntimeException('Не удалось создать XLSX');}
    $sheet='';
    foreach($rows as $ri=>$row){
        $sheet.='<row r="'.($ri+1).'">';
        foreach(array_values($row) as $ci=>$value) $sheet.=xlsx_cell((string)$value,$ri+1,$ci+1);
        $sheet.='</row>';
    }
    $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Смета" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');
    $zip->close();
    header('Content-Type:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition:attachment; filename="'.basename($filename).'"');
    header('Content-Length:'.filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}
