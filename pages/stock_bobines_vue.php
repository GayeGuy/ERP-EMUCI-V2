<?php
// ============================================================
//  pages/stock_bobines_vue.php
//  Vue synthétique stock bobines : par site × type
//  Exports : CSV · XLSX · PPTX
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Util\Coordinate;

require_auth();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'Vue Stock Bobines';
$active_page = 'stock_bobines_vue';

$roles_autorises = ['gestionnaire_stock_bobines','gestionnaire_stock','superviseur_operation',
                    'admin','superadmin','lecteur'];
if (!in_array($role_slug, $roles_autorises)) {
    http_response_code(403); include __DIR__ . '/../templates/403.php'; exit;
}

// ── FILTRES ──────────────────────────────────────────────────
$f_site   = (int)($_GET['site']   ?? 0);
$f_type   = trim($_GET['type']    ?? '');
$f_statut = trim($_GET['statut']  ?? '');   // en_cours | en_stock | (vide=tous)
$export   = trim($_GET['export']  ?? '');   // csv | xlsx | pptx

// ── REQUÊTE BASE ─────────────────────────────────────────────
$where  = ["b.statut IN ('en_cours','en_stock')"];
$params = [];
if ($f_site)   { $where[] = 'b.site_id=?';   $params[] = $f_site; }
if ($f_type)   { $where[] = 'b.type_code=?'; $params[] = $f_type; }
if ($f_statut) { $where[] = 'b.statut=?';    $params[] = $f_statut; }

$resume = db_fetch_all(
    "SELECT s.nom AS site_nom, s.id AS site_id,
            b.type_code,
            COUNT(*)                          AS nb_bobines,
            SUM(b.films_restants)             AS total_films,
            SUM(CASE WHEN b.statut='en_cours' THEN 1 ELSE 0 END) AS nb_en_cours,
            SUM(CASE WHEN b.statut='en_stock' THEN 1 ELSE 0 END) AS nb_en_stock
     FROM op_bobines b
     JOIN sites s ON s.id = b.site_id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY s.id, b.type_code
     ORDER BY s.nom, b.type_code",
    $params
);

// Bobines sans site
$where_ss = ["b.site_id IS NULL", "b.statut IN ('en_cours','en_stock')"];
$params_ss = [];
if ($f_type)   { $where_ss[] = 'b.type_code=?'; $params_ss[] = $f_type; }
if ($f_statut) { $where_ss[] = 'b.statut=?';    $params_ss[] = $f_statut; }
$sans_site = db_fetch_all(
    "SELECT b.type_code, COUNT(*) AS nb, SUM(b.films_restants) AS films
     FROM op_bobines b WHERE " . implode(' AND ', $where_ss) . "
     GROUP BY b.type_code ORDER BY b.type_code",
    $params_ss
);

// Listes pour filtres
$sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");
$types_list = db_fetch_all("SELECT DISTINCT type_code FROM op_bobines WHERE type_code IS NOT NULL ORDER BY type_code");

// ── INDEX & TOTAUX ────────────────────────────────────────────
$types = array_unique(array_column($resume, 'type_code'));
// Ajouter types de sans_site
foreach ($sans_site as $ss) { if (!in_array($ss['type_code'], $types)) $types[] = $ss['type_code']; }
sort($types);

$idx = [];
foreach ($resume as $r) $idx[$r['site_nom']][$r['type_code']] = $r;

$par_site = [];
foreach ($resume as $r) {
    $par_site[$r['site_nom']]['nb_bobines']  = ($par_site[$r['site_nom']]['nb_bobines']  ?? 0) + $r['nb_bobines'];
    $par_site[$r['site_nom']]['total_films'] = ($par_site[$r['site_nom']]['total_films'] ?? 0) + $r['total_films'];
}
$par_type = [];
foreach ($resume as $r) {
    $par_type[$r['type_code']]['nb_bobines']  = ($par_type[$r['type_code']]['nb_bobines']  ?? 0) + $r['nb_bobines'];
    $par_type[$r['type_code']]['total_films'] = ($par_type[$r['type_code']]['total_films'] ?? 0) + $r['total_films'];
}
foreach ($sans_site as $ss) {
    $par_type[$ss['type_code']]['nb_bobines']  = ($par_type[$ss['type_code']]['nb_bobines']  ?? 0) + $ss['nb'];
    $par_type[$ss['type_code']]['total_films'] = ($par_type[$ss['type_code']]['total_films'] ?? 0) + $ss['films'];
}

$total_bobines = array_sum(array_column($resume, 'nb_bobines')) + array_sum(array_column($sans_site, 'nb'));
$total_films   = array_sum(array_column($resume, 'total_films')) + array_sum(array_column($sans_site, 'films'));

// ============================================================
//  EXPORTS
// ============================================================

// ── Export CSV ───────────────────────────────────────────────
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="stock_bobines_' . date('Ymd') . '.csv"');
    $f = fopen('php://output','w');
    fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    $header = ['Site'];
    foreach ($types as $t) { $header[] = "Bobines $t"; $header[] = "Films $t"; }
    $header[] = 'Total bobines'; $header[] = 'Total films';
    fputcsv($f, $header, ';');

    foreach (array_keys($par_site) as $site_nom) {
        $row = [$site_nom];
        foreach ($types as $t) {
            $cell = $idx[$site_nom][$t] ?? null;
            $row[] = $cell ? $cell['nb_bobines']  : 0;
            $row[] = $cell ? $cell['total_films']  : 0;
        }
        $row[] = $par_site[$site_nom]['nb_bobines'];
        $row[] = $par_site[$site_nom]['total_films'];
        fputcsv($f, $row, ';');
    }
    if (!empty($sans_site)) {
        $row = ['En dépôt (sans site)'];
        $idx_ss = array_column($sans_site, null, 'type_code');
        foreach ($types as $t) {
            $ss = $idx_ss[$t] ?? null;
            $row[] = $ss ? $ss['nb']    : 0;
            $row[] = $ss ? $ss['films'] : 0;
        }
        $row[] = array_sum(array_column($sans_site,'nb'));
        $row[] = array_sum(array_column($sans_site,'films'));
        fputcsv($f, $row, ';');
    }
    $rowt = ['TOTAL'];
    foreach ($types as $t) {
        $rowt[] = $par_type[$t]['nb_bobines'] ?? 0;
        $rowt[] = $par_type[$t]['total_films'] ?? 0;
    }
    $rowt[] = $total_bobines; $rowt[] = $total_films;
    fputcsv($f, $rowt, ';');
    fclose($f); exit;
}

// ── Export XLSX ──────────────────────────────────────────────
if ($export === 'xlsx') {
    if (!file_exists($_autoload)) { echo 'PhpSpreadsheet non installé.'; exit; }

    $sp = new Spreadsheet();
    $ws = $sp->getActiveSheet();
    $ws->setTitle('Stock Bobines');

    // En-tête titre
    $nbCols = 1 + count($types) * 2 + 2;
    $lastCol = Coordinate::stringFromColumnIndex($nbCols);
    $ws->mergeCells("A1:{$lastCol}1");
    $ws->setCellValue([1,1], 'Vue Stock Bobines — ' . date('d/m/Y'));
    $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
        'font'      => ['bold'=>true,'size'=>14,'color'=>['argb'=>'FFFFFFFF']],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF06033A']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);
    $ws->getRowDimension(1)->setRowHeight(28);

    // En-têtes colonnes
    $col = 1; $row = 2;
    $ws->setCellValue([$col++, $row], 'Site');
    foreach ($types as $t) {
        $ws->setCellValue([$col++, $row], "Bobines\n$t");
        $ws->setCellValue([$col++, $row], "Films\n$t");
    }
    $ws->setCellValue([$col++, $row], 'Total\nBobines');
    $ws->setCellValue([$col,   $row], 'Total\nFilms');
    $hdrRange = "A2:{$lastCol}2";
    $ws->getStyle($hdrRange)->applyFromArray([
        'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF1B75BC']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true,'vertical'=>Alignment::VERTICAL_CENTER],
    ]);
    $ws->getRowDimension(2)->setRowHeight(32);

    // Données
    $row = 3;
    foreach (array_keys($par_site) as $site_nom) {
        $col = 1;
        $ws->setCellValue([$col++, $row], $site_nom);
        foreach ($types as $t) {
            $cell = $idx[$site_nom][$t] ?? null;
            $ws->setCellValue([$col++, $row], $cell ? (int)$cell['nb_bobines']  : 0);
            $ws->setCellValue([$col++, $row], $cell ? (int)$cell['total_films']  : 0);
        }
        $ws->setCellValue([$col++, $row], (int)$par_site[$site_nom]['nb_bobines']);
        $ws->setCellValue([$col,   $row], (int)$par_site[$site_nom]['total_films']);
        if ($row % 2 === 0) {
            $ws->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F8FF');
        }
        $row++;
    }

    // Ligne sans site
    if (!empty($sans_site)) {
        $col = 1; $idx_ss = array_column($sans_site, null, 'type_code');
        $ws->setCellValue([$col++, $row], 'En dépôt (sans site)');
        foreach ($types as $t) {
            $ss = $idx_ss[$t] ?? null;
            $ws->setCellValue([$col++, $row], $ss ? (int)$ss['nb']    : 0);
            $ws->setCellValue([$col++, $row], $ss ? (int)$ss['films'] : 0);
        }
        $ws->setCellValue([$col++, $row], array_sum(array_column($sans_site,'nb')));
        $ws->setCellValue([$col,   $row], array_sum(array_column($sans_site,'films')));
        $ws->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF8E1');
        $row++;
    }

    // Ligne total
    $col = 1;
    $ws->setCellValue([$col++, $row], 'TOTAL');
    foreach ($types as $t) {
        $ws->setCellValue([$col++, $row], (int)($par_type[$t]['nb_bobines']  ?? 0));
        $ws->setCellValue([$col++, $row], (int)($par_type[$t]['total_films'] ?? 0));
    }
    $ws->setCellValue([$col++, $row], $total_bobines);
    $ws->setCellValue([$col,   $row], $total_films);
    $ws->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
        'font' => ['bold'=>true],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFE8F0FE']],
    ]);

    // Largeurs colonnes
    $ws->getColumnDimension('A')->setWidth(22);
    for ($c = 2; $c <= $nbCols; $c++) {
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(13);
    }
    // Bordures
    $ws->getStyle("A2:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $ws->getStyle("A2:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle('A2:A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="stock_bobines_' . date('Ymd') . '.xlsx"');
    (new XlsxWriter($sp))->save('php://output');
    exit;
}

// ── Export PPTX (Open XML natif) ─────────────────────────────
if ($export === 'pptx') {
    // Générer PPTX minimal avec table via Open XML (ZIP)
    $tmpDir = sys_get_temp_dir() . '/pptx_' . uniqid();
    foreach ([
        $tmpDir, "$tmpDir/_rels", "$tmpDir/ppt",
        "$tmpDir/ppt/slides",        "$tmpDir/ppt/slides/_rels",
        "$tmpDir/ppt/slideLayouts",  "$tmpDir/ppt/slideLayouts/_rels",
        "$tmpDir/ppt/slideMasters",  "$tmpDir/ppt/slideMasters/_rels",
        "$tmpDir/ppt/theme",         "$tmpDir/ppt/_rels",
    ] as $_d) { if (!is_dir($_d)) mkdir($_d, 0777, true); }

    // Palette couleurs
    $navy = '06033A'; $blue = '1B75BC'; $gray = 'E8EDF8'; $white = 'FFFFFF';

    // Préparer les lignes du tableau
    $tableRows = [];
    // En-tête
    $hdr = ['Site'];
    foreach ($types as $t) { $hdr[] = "Bobines $t"; $hdr[] = "Films $t"; }
    $hdr[] = 'Total Bobines'; $hdr[] = 'Total Films';
    $tableRows[] = ['cells' => $hdr, 'header' => true];

    foreach (array_keys($par_site) as $site_nom) {
        $cells = [$site_nom];
        foreach ($types as $t) {
            $cell = $idx[$site_nom][$t] ?? null;
            $cells[] = $cell ? (string)(int)$cell['nb_bobines']  : '0';
            $cells[] = $cell ? number_format((int)$cell['total_films']) : '0';
        }
        $cells[] = (string)(int)$par_site[$site_nom]['nb_bobines'];
        $cells[] = number_format((int)$par_site[$site_nom]['total_films']);
        $tableRows[] = ['cells' => $cells, 'header' => false];
    }
    if (!empty($sans_site)) {
        $cells = ['En dépôt (sans site)'];
        $idx_ss = array_column($sans_site, null, 'type_code');
        foreach ($types as $t) {
            $ss = $idx_ss[$t] ?? null;
            $cells[] = $ss ? (string)(int)$ss['nb']    : '0';
            $cells[] = $ss ? number_format((int)$ss['films']) : '0';
        }
        $cells[] = (string)array_sum(array_column($sans_site,'nb'));
        $cells[] = number_format(array_sum(array_column($sans_site,'films')));
        $tableRows[] = ['cells' => $cells, 'header' => false];
    }
    // Total
    $cells = ['TOTAL'];
    foreach ($types as $t) {
        $cells[] = (string)(int)($par_type[$t]['nb_bobines']  ?? 0);
        $cells[] = number_format((int)($par_type[$t]['total_films'] ?? 0));
    }
    $cells[] = (string)$total_bobines;
    $cells[] = number_format($total_films);
    $tableRows[] = ['cells' => $cells, 'header' => false, 'total' => true];

    $nbCols = count($hdr);
    // Largeur de chaque colonne en EMU (slide 9144000 EMU = 10 inches)
    // Slide width = 9144000. col0 = 15%, rest = 85%/nbCols-1
    $slideW = 9144000; $slideH = 5143500;
    $colW0  = (int)($slideW * 0.20);
    $colWn  = (int)(($slideW * 0.80) / ($nbCols - 1));

    // Fonction helper pour cellule tableau PPTX
    $makeTc = function(string $text, bool $header, bool $isFirst, bool $total, int $colW) use ($navy, $blue, $gray, $white): string {
        $bold   = $header || $isFirst || $total ? '1' : '0';
        $fgClr  = ($header) ? $white : (($total) ? $navy : '222222');
        $bgClr  = ($header) ? $blue  : (($total) ? $gray : $white);
        $sz     = $header ? '1200' : '1000';
        return <<<XML
<a:tc><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="fr-FR" b="{$bold}" sz="{$sz}"><a:solidFill><a:srgbClr val="{$fgClr}"/></a:solidFill></a:rPr><a:t>{$text}</a:t></a:r></a:p></a:txBody><a:tcPr><a:solidFill><a:srgbClr val="{$bgClr}"/></a:solidFill><a:lnL w="12700"><a:solidFill><a:srgbClr val="CCCCCC"/></a:solidFill></a:lnL><a:lnR w="12700"><a:solidFill><a:srgbClr val="CCCCCC"/></a:solidFill></a:lnR><a:lnT w="12700"><a:solidFill><a:srgbClr val="CCCCCC"/></a:solidFill></a:lnT><a:lnB w="12700"><a:solidFill><a:srgbClr val="CCCCCC"/></a:solidFill></a:lnB></a:tcPr></a:tc>
XML;
    };

    // Construire les colonnes XML
    $gridXml = "<a:tblGrid>";
    $gridXml .= "<a:gridCol w=\"$colW0\"/>";
    for ($c = 1; $c < $nbCols; $c++) $gridXml .= "<a:gridCol w=\"$colWn\"/>";
    $gridXml .= "</a:tblGrid>";

    $rowsXml = '';
    $rowH = 400000;
    foreach ($tableRows as $tr) {
        $isHdr   = $tr['header'] ?? false;
        $isTotal = $tr['total']  ?? false;
        $rowsXml .= "<a:tr h=\"$rowH\">";
        foreach ($tr['cells'] as $ci => $cell) {
            $cw = $ci === 0 ? $colW0 : $colWn;
            $rowsXml .= $makeTc(htmlspecialchars($cell, ENT_XML1), $isHdr, $ci===0 && !$isHdr, $isTotal, $cw);
        }
        $rowsXml .= "</a:tr>";
    }

    $tableW = $slideW;
    $tableH = count($tableRows) * $rowH + 100000;
    $tableY = 680000;
    $dateGen = date('d/m/Y');
    $titreDetail = "{$total_bobines} bobines \u{00B7} " . number_format($total_films) . " films";

    // slide1.xml — pas de <p:ph>, fond via <p:bg>, titres en textbox plain
    $slideXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $slideXml .= '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
    $slideXml .= ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"';
    $slideXml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $slideXml .= '<p:cSld>';
    // Fond uni via p:bg (pas de shape placeholder)
    $slideXml .= '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="F4F6FB"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>';
    $slideXml .= '<p:spTree>';
    $slideXml .= '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>';
    $slideXml .= '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $slideW . '" cy="' . $slideH . '"/><a:chOff x="0" y="0"/><a:chExt cx="' . $slideW . '" cy="' . $slideH . '"/></a:xfrm></p:grpSpPr>';
    // Bande titre (rectangle plein navy)
    $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="2" name="bandeTitre"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>';
    $slideXml .= '<p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $slideW . '" cy="600000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom>';
    $slideXml .= '<a:solidFill><a:srgbClr val="06033A"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr>';
    $slideXml .= '<p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle/><a:p/></p:txBody></p:sp>';
    // Titre principal
    $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="3" name="titre"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>';
    $slideXml .= '<p:spPr><a:xfrm><a:off x="280000" y="80000"/><a:ext cx="6800000" cy="440000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr>';
    $slideXml .= '<p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle/>';
    $slideXml .= '<a:p><a:r><a:rPr lang="fr-FR" b="1" sz="2200" dirty="0"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:rPr>';
    $slideXml .= '<a:t>Vue Stock Bobines</a:t></a:r>';
    $slideXml .= '<a:r><a:rPr lang="fr-FR" sz="1600" dirty="0"><a:solidFill><a:srgbClr val="90b8d8"/></a:solidFill></a:rPr>';
    $slideXml .= '<a:t>  ' . htmlspecialchars($titreDetail, ENT_XML1) . '</a:t></a:r></a:p>';
    $slideXml .= '</p:txBody></p:sp>';
    // Date (en haut à droite)
    $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="4" name="date"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>';
    $slideXml .= '<p:spPr><a:xfrm><a:off x="7200000" y="100000"/><a:ext cx="1800000" cy="400000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr>';
    $slideXml .= '<p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle/>';
    $slideXml .= '<a:p><a:pPr algn="r"/><a:r><a:rPr lang="fr-FR" sz="1100" dirty="0"><a:solidFill><a:srgbClr val="90b8d8"/></a:solidFill></a:rPr>';
    $slideXml .= '<a:t>' . htmlspecialchars($dateGen, ENT_XML1) . '</a:t></a:r></a:p>';
    $slideXml .= '</p:txBody></p:sp>';
    // Tableau
    $slideXml .= '<p:graphicFrame>';
    $slideXml .= '<p:nvGraphicFramePr><p:cNvPr id="5" name="tableau"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>';
    $slideXml .= '<p:xfrm><a:off x="0" y="' . $tableY . '"/><a:ext cx="' . $tableW . '" cy="' . $tableH . '"/></p:xfrm>';
    $slideXml .= '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">';
    $slideXml .= '<a:tbl><a:tblPr firstRow="1" bandRow="1"/>';
    $slideXml .= $gridXml . $rowsXml;
    $slideXml .= '</a:tbl></a:graphicData></a:graphic>';
    $slideXml .= '</p:graphicFrame>';
    $slideXml .= '</p:spTree></p:cSld>';
    $slideXml .= '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';

    file_put_contents("$tmpDir/ppt/slides/slide1.xml", $slideXml);

    file_put_contents("$tmpDir/ppt/slides/_rels/slide1.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');

    file_put_contents("$tmpDir/ppt/slideLayouts/slideLayout1.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" type="blank"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>');

    file_put_contents("$tmpDir/ppt/slideLayouts/_rels/slideLayout1.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');

    file_put_contents("$tmpDir/ppt/slideMasters/slideMaster1.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="acc1" accent2="acc2" accent3="acc3" accent4="acc4" accent5="acc5" accent6="acc6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle><a:lstStyle/></p:titleStyle><p:bodyStyle><a:lstStyle/></p:bodyStyle><p:otherStyle><a:lstStyle/></p:otherStyle></p:txStyles></p:sldMaster>');

    file_put_contents("$tmpDir/ppt/slideMasters/_rels/slideMaster1.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>');

    file_put_contents("$tmpDir/ppt/theme/theme1.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="DigiStock"><a:themeElements><a:clrScheme name="DigiStock"><a:dk1><a:srgbClr val="06033A"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1B75BC"/></a:dk2><a:lt2><a:srgbClr val="E8EDF8"/></a:lt2><a:accent1><a:srgbClr val="1B75BC"/></a:accent1><a:accent2><a:srgbClr val="06033A"/></a:accent2><a:accent3><a:srgbClr val="2e7d32"/></a:accent3><a:accent4><a:srgbClr val="f39c12"/></a:accent4><a:accent5><a:srgbClr val="8e44ad"/></a:accent5><a:accent6><a:srgbClr val="c0392b"/></a:accent6><a:hlink><a:srgbClr val="1B75BC"/></a:hlink><a:folHlink><a:srgbClr val="06033A"/></a:folHlink></a:clrScheme><a:fontScheme name="DigiStock"><a:majorFont><a:latin typeface="Plus Jakarta Sans"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont><a:minorFont><a:latin typeface="Plus Jakarta Sans"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme><a:fmtScheme name="DigiStock"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"><a:tint val="95000"/></a:schemeClr></a:solidFill><a:solidFill><a:schemeClr val="phClr"><a:shade val="75000"/></a:schemeClr></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements></a:theme>');

    file_put_contents("$tmpDir/ppt/presentation.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" saveSubsetFonts="1"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst><p:sldId id="256" r:id="rId2"/></p:sldIdLst><p:sldSz cx="9144000" cy="5143500" type="screen4x3"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>');

    file_put_contents("$tmpDir/ppt/_rels/presentation.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>');

    file_put_contents("$tmpDir/_rels/.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');

    file_put_contents("$tmpDir/[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/></Types>');

    // Créer le ZIP
    $pptxFile = sys_get_temp_dir() . '/stock_bobines_' . date('Ymd') . '.pptx';
    $zip = new ZipArchive();
    $zip->open($pptxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        $relPath = str_replace($tmpDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relPath = str_replace('\\', '/', $relPath);
        $zip->addFile($file->getPathname(), $relPath);
    }
    $zip->close();

    // Nettoyer tmp (suppression récursive)
    $rmrf = function(string $dir) use (&$rmrf): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = "$dir/$item";
            is_dir($path) ? $rmrf($path) : unlink($path);
        }
        rmdir($dir);
    };
    $rmrf($tmpDir);

    header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
    header('Content-Disposition: attachment; filename="stock_bobines_' . date('Ymd') . '.pptx"');
    header('Content-Length: ' . filesize($pptxFile));
    readfile($pptxFile);
    unlink($pptxFile);
    exit;
}

// ============================================================
//  VUE HTML
// ============================================================
include __DIR__ . '/../templates/header.php';
?>
<style>
/* ── Table ── */
.vue-table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px}

/* ── En-têtes types (colonnes) ── */
.vue-table thead tr.row-types th{
  background:#0a1a3a;
  color:#fff;padding:16px 18px;text-align:center;
  white-space:nowrap;position:sticky;top:0;z-index:2;
  border-right:1px solid rgba(255,255,255,.1)}
.vue-table thead tr.row-types th.th-site{
  text-align:left;min-width:190px;padding-left:22px;
  background:#06033A;border-right:3px solid #4da6d8}
.vue-table thead tr.row-types th.th-type .type-label{
  display:inline-block;background:rgba(255,255,255,.12);
  border:1.5px solid rgba(255,255,255,.28);border-radius:8px;
  padding:5px 14px;font-size:13px;font-weight:800;letter-spacing:.5px}
.vue-table thead tr.row-types th.th-total{
  background:#04102a;border-left:3px solid #4da6d8;
  padding:16px 22px}
.vue-table thead tr.row-types th.th-total .type-label{
  background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.25)}

/* ── En-têtes sous-labels (Qté / Films) ── */
.vue-table thead tr.row-sub th{
  background:#0f2144;color:rgba(255,255,255,.6);
  font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  padding:5px 18px 8px;text-align:center;
  position:sticky;top:52px;z-index:2;
  border-bottom:2px solid rgba(255,255,255,.08);
  border-right:1px solid rgba(255,255,255,.06)}
.vue-table thead tr.row-sub th.th-site-sub{
  text-align:left;padding-left:22px;background:#081530;
  border-right:3px solid #1B75BC}
.vue-table thead tr.row-sub th.th-total-sub{
  background:#081530;border-left:3px solid #4da6d8}

/* ── Colonne site (en-tête de ligne) ── */
.vue-table td.site-name{
  background:linear-gradient(90deg,#e8eeff 0%,#f0f4ff 100%);
  text-align:left;font-weight:700;font-size:13px;color:#06033A;
  padding:14px 16px 14px 20px;
  border-right:4px solid #1B75BC;
  white-space:nowrap;min-width:190px}
.vue-table td.site-name .site-icon{
  display:inline-block;width:7px;height:7px;border-radius:50%;
  background:#1B75BC;margin-right:8px;vertical-align:middle;flex-shrink:0}

/* ── Cellules données ── */
.vue-table td{
  padding:12px 14px;border-bottom:1px solid #eef0f5;
  text-align:center;vertical-align:middle;background:#fff;
  border-right:1px solid #f0f2f8}
.vue-table tbody tr:nth-child(even) td{background:#fafbff}
.vue-table tbody tr:nth-child(even) td.site-name{
  background:linear-gradient(90deg,#dde5ff 0%,#e8eeff 100%)}
.vue-table tbody tr:not(.total-row):hover td{background:#edf2ff}
.vue-table tbody tr:not(.total-row):hover td.site-name{
  background:linear-gradient(90deg,#c7d4ff 0%,#d8e2ff 100%)}
.vue-table tbody tr:not(.total-row):hover td.td-total{background:#bdd6f5}

/* ── Colonne Total ── */
.vue-table td.td-total{
  background:linear-gradient(90deg,#d6e8fa 0%,#e4f0fd 100%);
  border-left:4px solid #1B75BC;border-right:none;min-width:110px}
.vue-table tbody tr:nth-child(even) td.td-total{
  background:linear-gradient(90deg,#c5ddf7 0%,#d6e8fa 100%)}

/* ── Ligne sans site ── */
.vue-table tr.sans-site-row td{background:#fffbeb}
.vue-table tr.sans-site-row td.site-name{
  background:linear-gradient(90deg,#fef3c7 0%,#fffbeb 100%);
  color:#92400e;border-right-color:#f59e0b}
.vue-table tr.sans-site-row td.td-total{
  background:linear-gradient(90deg,#fde68a 0%,#fef3c7 100%);
  border-left-color:#f59e0b}

/* ── Ligne TOTAL GÉNÉRAL ── */
.vue-table tbody tr.total-row td,
.vue-table tbody tr.total-row:nth-child(even) td{
  background:#06033A !important;border-top:2px solid #1B75BC;border-bottom:none;
  border-right:1px solid rgba(255,255,255,.08)}
.vue-table tbody tr.total-row td.site-name,
.vue-table tbody tr.total-row:nth-child(even) td.site-name{
  background:linear-gradient(90deg,#06033A 0%,#0d1a4a 100%) !important;
  color:#fff;font-size:12px;font-weight:900;letter-spacing:.8px;text-transform:uppercase;
  border-right:4px solid #4da6d8}
.vue-table tbody tr.total-row td.td-total,
.vue-table tbody tr.total-row:nth-child(even) td.td-total{
  background:linear-gradient(90deg,#04102a 0%,#06033A 100%) !important;
  border-left:4px solid #4da6d8}

/* ── Contenu cellule ── */
.cell-nb{font-size:20px;font-weight:900;color:#06033A;line-height:1}
.cell-films{font-size:11px;color:#9ca3af;margin-top:4px;letter-spacing:.2px}
.cell-empty{color:#d1d5db;font-size:16px}
.tag-cours{display:inline-block;background:#dbeafe;color:#1d4ed8;border-radius:20px;
  padding:2px 7px;font-size:10px;font-weight:700;margin-top:5px}
.total-row .cell-nb{color:#fff;font-size:22px}
.total-row .cell-films{color:rgba(255,255,255,.45)}
.td-total .cell-nb{color:#1B75BC;font-size:22px}
.td-total .cell-films{color:#6b9fd4}
.total-row .td-total .cell-nb{color:#7dc4f5}
.total-row .td-total .cell-films{color:rgba(125,196,245,.5)}
/* KPI */
.kpi-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px}
.kpi{
  background:#fff;border-radius:14px;border:1px solid #e8edf8;
  padding:18px 20px;position:relative;overflow:hidden;
  box-shadow:0 1px 6px rgba(6,3,58,.06)}
.kpi::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:var(--kpi-c,#1B75BC);border-radius:4px 0 0 4px}
.kpi-v{font-size:30px;font-weight:900;color:var(--kpi-c,#06033A);line-height:1;margin-bottom:4px}
.kpi-l{font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.kpi-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:28px;opacity:.12}
/* Section titre */
.page-banner{
  background:linear-gradient(135deg,#06033A 0%,#1B75BC 100%);
  border-radius:16px;padding:24px 28px;margin-bottom:24px;
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;
  box-shadow:0 4px 20px rgba(6,3,58,.2)}
.page-banner h1{color:#fff;font-size:22px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px}
.page-banner p{color:rgba(255,255,255,.75);font-size:13px;margin:4px 0 0}
.export-btn{
  display:inline-flex;align-items:center;gap:7px;padding:10px 18px;
  border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;
  text-decoration:none;transition:transform .1s,box-shadow .1s}
.export-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.btn-csv {background:#fff;color:#2e7d32;border:1.5px solid #a5d6a7}
.btn-xlsx{background:#fff;color:#1565c0;border:1.5px solid #90caf9}
.btn-pptx{background:#fff;color:#c62828;border:1.5px solid #ef9a9a}
/* Filtres */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;
  background:#fff;padding:14px 20px;border-radius:12px;
  border:1px solid #e8edf8;margin-bottom:22px;
  box-shadow:0 1px 4px rgba(6,3,58,.04)}
.fsel{padding:9px 12px;border:1.5px solid #e0e4ef;border-radius:9px;font-size:13px;background:white;outline:none;color:#06033A;cursor:pointer}
.fsel:focus{border-color:#1B75BC}
.overflow-wrap{overflow-x:auto;border-radius:14px;box-shadow:0 2px 16px rgba(6,3,58,.08);border:1px solid #e8edf8}
</style>

<!-- BANNIÈRE TITRE -->
<div class="page-banner">
  <div>
    <h1>🎞️ Vue Stock Bobines</h1>
    <p>Synthèse par site et par type — <?= date('d/m/Y') ?> · <?= $total_bobines ?> bobines · <?= number_format($total_films) ?> films</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv']))  ?>" class="export-btn btn-csv">📄 CSV</a>
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'xlsx'])) ?>" class="export-btn btn-xlsx">📊 Excel</a>
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pptx'])) ?>" class="export-btn btn-pptx">📑 PowerPoint</a>
  </div>
</div>

<!-- FILTRES -->
<div class="filter-bar">
  <form method="GET" style="display:contents">
    <select name="site" class="fsel" onchange="this.form.submit()">
      <option value="">🏢 Tous les sites</option>
      <?php foreach ($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site==$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="fsel" onchange="this.form.submit()">
      <option value="">🎞️ Tous les types</option>
      <?php foreach ($types_list as $t): ?>
      <option value="<?= h($t['type_code']) ?>" <?= $f_type===$t['type_code']?'selected':'' ?>><?= h($t['type_code']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="statut" class="fsel" onchange="this.form.submit()">
      <option value="">⚡ Tous statuts</option>
      <option value="en_cours" <?= $f_statut==='en_cours'?'selected':'' ?>>En cours</option>
      <option value="en_stock" <?= $f_statut==='en_stock'?'selected':'' ?>>En stock</option>
    </select>
    <?php if ($f_site || $f_type || $f_statut): ?>
    <a href="stock_bobines_vue.php" style="font-size:13px;color:#9ca3af;text-decoration:none;padding:8px 12px;border-radius:8px;border:1.5px solid #e0e4ef">✕ Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<!-- KPI -->
<div class="kpi-bar">
  <div class="kpi" style="--kpi-c:#1B75BC">
    <div class="kpi-icon">🎞️</div>
    <div class="kpi-v"><?= $total_bobines ?></div>
    <div class="kpi-l">Bobines actives</div>
  </div>
  <div class="kpi" style="--kpi-c:#2e7d32">
    <div class="kpi-icon">🎬</div>
    <div class="kpi-v"><?= number_format($total_films) ?></div>
    <div class="kpi-l">Films restants</div>
  </div>
  <div class="kpi" style="--kpi-c:#1565c0">
    <div class="kpi-icon">🏢</div>
    <div class="kpi-v"><?= count($par_site) ?></div>
    <div class="kpi-l">Sites actifs</div>
  </div>
  <div class="kpi" style="--kpi-c:#7b1fa2">
    <div class="kpi-icon">🏷️</div>
    <div class="kpi-v"><?= count($types) ?></div>
    <div class="kpi-l">Types de bobine</div>
  </div>
</div>

<!-- TABLEAU -->
<?php if (empty($par_site) && empty($sans_site)): ?>
<div style="text-align:center;padding:60px;color:#9ca3af;background:#fff;border-radius:14px;border:1px solid #e8edf8">
  <div style="font-size:48px;margin-bottom:12px">🎞️</div>
  <div style="font-size:16px;font-weight:600">Aucune bobine active pour les filtres sélectionnés.</div>
</div>
<?php else: ?>
<div class="overflow-wrap">
<table class="vue-table">
  <thead>
    <!-- Ligne 1 : types de bobine -->
    <tr class="row-types">
      <th class="th-site" rowspan="2">Site</th>
      <?php foreach ($types as $t): ?>
      <th class="th-type"><span class="type-label"><?= h($t) ?></span></th>
      <?php endforeach; ?>
      <th class="th-total" rowspan="2"><span class="type-label">Total</span></th>
    </tr>
    <!-- Ligne 2 : sous-labels Qté / Films -->
    <tr class="row-sub">
      <?php foreach ($types as $t): ?>
      <th class="th-sub">Qté · Films</th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($par_site as $site_nom => $site_tot): ?>
    <tr>
      <td class="site-name"><span class="site-icon"></span><?= h($site_nom) ?></td>
      <?php foreach ($types as $t):
            $cell = $idx[$site_nom][$t] ?? null; ?>
      <td>
        <?php if ($cell): ?>
          <div class="cell-nb"><?= $cell['nb_bobines'] ?></div>
          <div class="cell-films"><?= number_format($cell['total_films']) ?> films</div>
          <?php if ($cell['nb_en_cours'] > 0): ?>
          <div class="tag-cours"><?= $cell['nb_en_cours'] ?> en cours</div>
          <?php endif; ?>
        <?php else: ?><span class="cell-empty">—</span><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td class="td-total">
        <div class="cell-nb" style="color:#1B75BC"><?= $site_tot['nb_bobines'] ?></div>
        <div class="cell-films"><?= number_format($site_tot['total_films']) ?> films</div>
      </td>
    </tr>
    <?php endforeach; ?>

    <?php if (!empty($sans_site)): ?>
    <tr class="sans-site-row">
      <td class="site-name">📦 En dépôt</td>
      <?php $idx_ss = array_column($sans_site, null, 'type_code');
            foreach ($types as $t): $found = $idx_ss[$t] ?? null; ?>
      <td>
        <?php if ($found): ?>
          <div class="cell-nb" style="color:#92400e"><?= $found['nb'] ?></div>
          <div class="cell-films"><?= number_format($found['films']) ?> films</div>
        <?php else: ?><span class="cell-empty">—</span><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td class="td-total">
        <div class="cell-nb" style="color:#92400e"><?= array_sum(array_column($sans_site,'nb')) ?></div>
        <div class="cell-films"><?= number_format(array_sum(array_column($sans_site,'films'))) ?> films</div>
      </td>
    </tr>
    <?php endif; ?>

    <tr class="total-row">
      <td class="site-name">TOTAL GÉNÉRAL</td>
      <?php foreach ($types as $t): $tc = $par_type[$t] ?? null; ?>
      <td>
        <?php if ($tc): ?>
          <div class="cell-nb"><?= $tc['nb_bobines'] ?></div>
          <div class="cell-films"><?= number_format($tc['total_films']) ?> films</div>
        <?php else: ?><span class="cell-empty" style="color:rgba(255,255,255,.2)">—</span><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td class="td-total">
        <div class="cell-nb"><?= $total_bobines ?></div>
        <div class="cell-films"><?= number_format($total_films) ?> films</div>
      </td>
    </tr>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
