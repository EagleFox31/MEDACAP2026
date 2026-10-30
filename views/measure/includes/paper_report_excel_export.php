<?php
/**
 * Export Excel — rapports recrutement (notes /20, graphique maîtrise, multi-épreuves).
 */

require_once __DIR__ . '/paper_candidate_report_lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

function paperReportExcelSanitizeSheetTitle(string $title): string
{
    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?? $title;
    $title = trim($title);
    if ($title === '') {
        $title = 'Feuille';
    }
    return mb_substr($title, 0, 31);
}

function paperReportExcelStyleHeaderRow($sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray(array(
        'font' => array(
            'bold' => true,
            'color' => array('rgb' => 'FFFFFF'),
            'name' => 'Arial',
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'startColor' => array('rgb' => '005BA9'),
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
    ));
}

function paperReportExcelStyleTitleBanner($sheet, string $range, string $text, int $fontSize = 14): void
{
    $topLeft = explode(':', $range)[0];
    $sheet->mergeCells($range);
    $sheet->setCellValue($topLeft, $text);
    $sheet->getStyle($range)->applyFromArray(array(
        'font' => array(
            'bold' => true,
            'size' => $fontSize,
            'color' => array('rgb' => 'FFFFFF'),
            'name' => 'Arial',
        ),
        'fill' => array(
            'fillType' => Fill::FILL_SOLID,
            'startColor' => array('rgb' => '000000'),
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
    ));
}

function paperReportExcelScoreFontColor($note): string
{
    if (!is_numeric($note)) {
        return 'FF000000';
    }
    $value = (float) $note;
    if ($value >= 14) {
        return 'FF008000';
    }
    if ($value >= 10) {
        return 'FFE36C0A';
    }
    return 'FFC00000';
}

function paperReportExcelStyleScoreCell($sheet, string $cellAddress, $note): void
{
    if ($note === null || $note === '' || !is_numeric($note)) {
        $sheet->setCellValue($cellAddress, '—');
        return;
    }

    $value = round((float) $note, 2);
    $sheet->setCellValue($cellAddress, $value);
    $sheet->getStyle($cellAddress)->applyFromArray(array(
        'font' => array(
            'bold' => true,
            'color' => array('argb' => paperReportExcelScoreFontColor($value)),
            'name' => 'Arial',
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
    ));
}

function paperReportExcelSetSheetLink($sheet, string $cellAddress, string $targetSheetTitle, string $label): void
{
    $quoted = str_replace("'", "''", $targetSheetTitle);
    $sheet->setCellValue($cellAddress, $label);
    $sheet->getCell($cellAddress)->setHyperlink(new Hyperlink("sheet://'" . $quoted . "'!A1", $label));
    $sheet->getStyle($cellAddress)->applyFromArray(array(
        'font' => array(
            'underline' => Font::UNDERLINE_SINGLE,
            'color' => array('argb' => 'FF0563C1'),
            'name' => 'Calibri',
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
    ));
}

function paperReportExcelStyleCenteredText($sheet, string $cellAddress): void
{
    $sheet->getStyle($cellAddress)->applyFromArray(array(
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ),
        'font' => array('name' => 'Arial'),
    ));
}

function paperReportExcelCandidateSheetTitle(array $c, array &$usedTitles): string
{
    $name = $c['name'] !== '' ? $c['name'] : $c['username'];
    return paperReportExcelUniqueSheetTitle('Recrutement - ' . $name, $usedTitles);
}

function paperReportExcelSetGlobalColumnWidths($sheet, bool $multi, int $scoreColumnCount): void
{
    $sheet->getColumnDimension('A')->setWidth(20);
    $colIndex = 2;
    if ($multi) {
        $sheet->getColumnDimension('B')->setWidth(43);
        $colIndex = 3;
    }
    for ($i = 0; $i < $scoreColumnCount; $i++) {
        $sheet->getColumnDimensionByColumn($colIndex + $i)->setWidth(14);
    }
}

function paperReportExcelAutoSizeColumns($sheet, int $fromCol, int $toCol): void
{
    for ($c = $fromCol; $c <= $toCol; $c++) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

function paperReportExcelFormatNote($note): string
{
    if ($note === null || $note === '') {
        return '—';
    }
    return is_numeric($note) ? (string) round((float) $note, 2) : (string) $note;
}

function paperReportExcelUniqueSheetTitle(string $base, array &$usedTitles): string
{
    $title = paperReportExcelSanitizeSheetTitle($base);
    $candidate = $title;
    $suffix = 1;
    while (isset($usedTitles[$candidate])) {
        $suffix++;
        $candidate = paperReportExcelSanitizeSheetTitle($base . ' ' . $suffix);
    }
    $usedTitles[$candidate] = true;
    return $candidate;
}

function paperReportExcelQuoteSheet(string $sheetTitle): string
{
    return "'" . str_replace("'", "''", $sheetTitle) . "'";
}

/** @param list<array{ctx:array}> $paperBlocks */
function paperReportExcelActiveLevels(array $paperBlocks): array
{
    $active = array();
    foreach (array('Junior', 'Senior', 'Expert') as $level) {
        foreach ($paperBlocks as $pb) {
            $totals = isset($pb['ctx']['level_totals']) ? $pb['ctx']['level_totals'] : array();
            if (($totals[$level] ?? 0) > 0) {
                $active[] = $level;
                break;
            }
        }
    }
    return $active;
}

function paperReportExcelActiveLevelsForPaper(array $ctx): array
{
    $active = array();
    $totals = isset($ctx['level_totals']) ? $ctx['level_totals'] : array();
    foreach (array('Junior', 'Senior', 'Expert') as $level) {
        if (($totals[$level] ?? 0) > 0) {
            $active[] = $level;
        }
    }
    return $active;
}

function paperReportExcelFormatLevelNote(array $c, string $level): string
{
    if (!isset($c['levels'][$level])) {
        return '';
    }
    $block = $c['levels'][$level];
    if (($block['total'] ?? 0) <= 0 || ($block['answered'] ?? 0) <= 0) {
        return '';
    }
    return paperReportExcelFormatNote($block['note_maitrise'] ?? null);
}

/** @return array{include_completude_globale:bool,include_charts:bool} */
function paperReportExcelDefaultOptions(): array
{
    return array(
        'include_completude_globale' => false,
        'include_charts' => true,
    );
}

/** @return array{include_completude_globale:bool,include_charts:bool} */
function paperReportExcelNormalizeOptions(?array $options = null): array
{
    $defaults = paperReportExcelDefaultOptions();
    if ($options === null) {
        return $defaults;
    }
    $includeCompletude = !empty($options['include_completude_globale']);
    if (!$includeCompletude && !empty($options['include_note_recrutement'])) {
        $includeCompletude = true;
    }
    return array(
        'include_completude_globale' => $includeCompletude,
        'include_charts' => !array_key_exists('include_charts', $options) || !empty($options['include_charts']),
    );
}

/** Lit les options export depuis POST (checkboxes HTML). */
function paperReportExcelOptionsFromPost(array $post): array
{
    return paperReportExcelNormalizeOptions(array(
        'include_completude_globale' => isset($post['include_completude_globale']) && (string) $post['include_completude_globale'] === '1',
        'include_charts' => isset($post['include_charts']) && (string) $post['include_charts'] === '1',
    ));
}

/**
 * @param list<array{ctx:array, candidates:list, paper_label:string, paper_id:string}> $paperBlocks
 */
function paperReportExcelBuildSpreadsheet(array $paperBlocks, ?array $options = null): Spreadsheet
{
    $options = paperReportExcelNormalizeOptions($options);
    $includeCompletudeGlobale = $options['include_completude_globale'];
    $includeCharts = $options['include_charts'];

    $spreadsheet = new Spreadsheet();
    $usedTitles = array();
    $chartCounter = 0;
    $multi = count($paperBlocks) > 1;
    $activeLevels = paperReportExcelActiveLevels($paperBlocks);

    $global = $spreadsheet->getActiveSheet();
    $global->setTitle(paperReportExcelUniqueSheetTitle('Resultats globaux', $usedTitles));

    $candidateEntries = array();
    foreach ($paperBlocks as $pb) {
        foreach ($pb['candidates'] as $c) {
            $candidateEntries[] = array(
                'pb' => $pb,
                'c' => $c,
                'sheetTitle' => paperReportExcelCandidateSheetTitle($c, $usedTitles),
            );
        }
    }

    $headers = array('Candidat');
    if ($multi) {
        $headers[] = 'Épreuve';
    }
    foreach ($activeLevels as $level) {
        $headers[] = $level . ' /20';
    }
    if ($includeCompletudeGlobale) {
        $headers[] = 'Complétude glob. /20';
    }

    $lastCol = count($headers);
    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
    $headerRow = 3;
    $dataStartRow = 4;
    $titleText = $multi ? 'Résultats globaux — épreuves associées' : 'Résultats globaux';

    paperReportExcelStyleTitleBanner($global, 'A1:' . $lastColLetter . '1', $titleText, 14);
    $global->getRowDimension(1)->setRowHeight(17.4);

    foreach ($headers as $i => $h) {
        $global->setCellValueByColumnAndRow($i + 1, $headerRow, $h);
    }
    paperReportExcelStyleHeaderRow($global, 'A' . $headerRow . ':' . $lastColLetter . $headerRow);
    $global->getRowDimension($headerRow)->setRowHeight(21.6);

    $scoreColStart = $multi ? 3 : 2;
    $row = $dataStartRow;
    foreach ($candidateEntries as $entry) {
        $pb = $entry['pb'];
        $c = $entry['c'];
        $paperLabel = $pb['paper_label'] ?? '';
        $paperLevels = paperReportExcelActiveLevelsForPaper($pb['ctx']);
        $g = $c['global'];
        $name = $c['name'] !== '' ? $c['name'] : $c['username'];

        paperReportExcelSetSheetLink($global, 'A' . $row, $entry['sheetTitle'], $name);
        if ($multi) {
            $global->setCellValue('B' . $row, $paperLabel);
            paperReportExcelStyleCenteredText($global, 'B' . $row);
        }

        $levelCol = $scoreColStart;
        foreach ($activeLevels as $level) {
            $cellAddress = Coordinate::stringFromColumnIndex($levelCol) . $row;
            if (!in_array($level, $paperLevels, true)) {
                $global->setCellValue($cellAddress, '');
            } else {
                $block = isset($c['levels'][$level]) ? $c['levels'][$level] : array();
                if (($block['total'] ?? 0) <= 0) {
                    $global->setCellValue($cellAddress, '');
                } else {
                    $note = ($block['answered'] ?? 0) > 0 ? ($block['note_maitrise'] ?? null) : null;
                    paperReportExcelStyleScoreCell($global, $cellAddress, $note);
                }
            }
            $levelCol++;
        }

        if ($includeCompletudeGlobale) {
            $completudeCell = Coordinate::stringFromColumnIndex($levelCol) . $row;
            paperReportExcelStyleScoreCell($global, $completudeCell, $g['note_completude'] ?? null);
        }

        $global->getRowDimension($row)->setRowHeight(21.6);
        $row++;
    }

    paperReportExcelSetGlobalColumnWidths(
        $global,
        $multi,
        count($activeLevels) + ($includeCompletudeGlobale ? 1 : 0)
    );
    $global->freezePane('A' . $dataStartRow);

    foreach ($candidateEntries as $entry) {
        $chartCounter++;
        paperReportExcelAddCandidateSheet(
            $spreadsheet,
            $entry['c'],
            $entry['pb']['paper_label'] ?? '',
            $entry['sheetTitle'],
            $multi,
            $includeCompletudeGlobale,
            $includeCharts,
            $chartCounter
        );
    }

    return $spreadsheet;
}

function paperReportExcelAddCandidateSheet(
    Spreadsheet $spreadsheet,
    array $c,
    string $paperLabel,
    string $sheetTitle,
    bool $multi,
    bool $includeCompletudeGlobale = true,
    bool $includeCharts = true,
    int $chartCounter = 1
): void {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($sheetTitle);

    $displayName = $c['name'] !== '' ? $c['name'] : $c['username'];
    paperReportExcelStyleTitleBanner($sheet, 'A1:B1', $displayName, 13);
    $sheet->getRowDimension(1)->setRowHeight(16.8);

    $sheet->setCellValue('A2', 'Identifiant : ' . $c['username']);
    $sheet->getStyle('A2')->getFont()->setName('Arial');

    $metaRow = 3;
    if ($multi) {
        $sheet->setCellValue('A3', 'Épreuve : ' . $paperLabel);
        $sheet->getStyle('A3')->getFont()->setName('Arial');
        $metaRow = 4;
    }

    $g = $c['global'];
    $sheet->setCellValue('A' . $metaRow, 'Maîtrise globale /20');
    $sheet->getStyle('A' . $metaRow)->getFont()->setBold(true)->setName('Arial');
    paperReportExcelStyleScoreCell($sheet, 'B' . $metaRow, $g['note_maitrise'] ?? null);

    $startRow = $metaRow + 1;
    if ($includeCompletudeGlobale) {
        $sheet->setCellValue('A' . $startRow, 'Complétude globale /20');
        $sheet->getStyle('A' . $startRow)->getFont()->setBold(true)->setName('Arial');
        paperReportExcelStyleScoreCell($sheet, 'B' . $startRow, $g['note_completude'] ?? null);
        $startRow++;
    }
    $startRow++;

    $r = $startRow;
    $levelRows = array();
    foreach (array('Junior', 'Senior', 'Expert') as $lv) {
        if (!isset($c['levels'][$lv])) {
            continue;
        }
        $b = $c['levels'][$lv];
        if (($b['total'] ?? 0) <= 0) {
            continue;
        }
        $note = ($b['answered'] ?? 0) > 0 ? ($b['note_maitrise'] ?? null) : null;
        $levelRows[] = array($lv, $note);
    }
    if ($levelRows !== array()) {
        $sheet->setCellValue('A' . $r, 'Notes par niveau (/20)');
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setName('Arial');
        $r++;
        $sheet->fromArray(array('Niveau', 'Note maîtrise /20'), null, 'A' . $r);
        paperReportExcelStyleHeaderRow($sheet, 'A' . $r . ':B' . $r);
        $r++;
        foreach ($levelRows as $levelRow) {
            $sheet->setCellValue('A' . $r, $levelRow[0]);
            paperReportExcelStyleScoreCell($sheet, 'B' . $r, $levelRow[1]);
            $r++;
        }
        $r += 2;
    }

    $sheet->setCellValue('A' . $r, 'Notes par spécialité (/20)');
    $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setName('Arial');
    $r++;
    $specHeaderRow = $r;
    $sheet->fromArray(array('Spécialité', 'Note maîtrise /20'), null, 'A' . $r);
    paperReportExcelStyleHeaderRow($sheet, 'A' . $r . ':B' . $r);
    $specStart = $r + 1;
    $r = $specStart;
    foreach ($c['specialty_notes'] as $spec => $sn) {
        $note = isset($sn['note_maitrise']) ? $sn['note_maitrise'] : null;
        $sheet->setCellValue('A' . $r, $spec);
        if ($note === null || $note === '') {
            $sheet->setCellValue('B' . $r, '—');
        } else {
            paperReportExcelStyleScoreCell($sheet, 'B' . $r, $note);
        }
        $r++;
    }
    $specEnd = $r - 1;

    $chartDataStart = null;
    $chartDataEnd = null;
    $chartDataRow = $specHeaderRow + 1;
    foreach ($c['specialty_notes'] as $spec => $sn) {
        $note = isset($sn['note_maitrise']) ? $sn['note_maitrise'] : null;
        if ($note === null || $note === '') {
            continue;
        }
        if ($chartDataStart === null) {
            $chartDataStart = $chartDataRow;
        }
        $sheet->setCellValue('F' . $chartDataRow, $spec);
        $sheet->setCellValue('G' . $chartDataRow, round((float) $note, 2));
        $chartDataEnd = $chartDataRow;
        $chartDataRow++;
    }

    $sheet->getColumnDimension('A')->setWidth(52);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(28);
    $sheet->getColumnDimension('G')->setWidth(10);

    if ($includeCharts && $chartDataStart !== null && $chartDataEnd !== null) {
        paperReportExcelAddMasteryChart($sheet, $chartDataStart, $chartDataEnd, 'I' . $specHeaderRow, $chartCounter);
    }
}

function paperReportExcelAddMasteryChart($sheet, int $dataStartRow, int $dataEndRow, string $anchorCell, int $chartCounter = 1): void
{
    $n = $dataEndRow - $dataStartRow + 1;
    if ($n <= 0) {
        return;
    }

    $sheetName = paperReportExcelQuoteSheet($sheet->getTitle());
    $catRef = $sheetName . '!$F$' . $dataStartRow . ':$F$' . $dataEndRow;
    $valRef = $sheetName . '!$G$' . $dataStartRow . ':$G$' . $dataEndRow;

    $categories = new DataSeriesValues('String', $catRef, null, $n);
    $values = new DataSeriesValues('Number', $valRef, null, $n);

    $series = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_CLUSTERED,
        array(0),
        array(),
        array($categories),
        array($values),
        DataSeries::DIRECTION_COL
    );

    $plotArea = new PlotArea(null, array($series));
    $legend = new Legend(Legend::POSITION_RIGHT, null, false);
    $chartTitle = new Title('Maîtrise par spécialité (/20)');
    $chartName = 'mastery_chart_' . max(1, $chartCounter);
    $chart = new Chart($chartName, $chartTitle, $legend, $plotArea, false);
    $chart->setTopLeftPosition($anchorCell);
    $chart->setBottomRightPosition('R' . max($dataEndRow + 12, $dataStartRow + 18));

    $sheet->addChart($chart);
}

function paperReportExcelSave(Spreadsheet $spreadsheet, string $filenameBase, ?string $outDir = null): string
{
    if ($outDir === null) {
        $outDir = dirname(__DIR__, 3) . '/scripts/out';
    }
    if (!is_dir($outDir)) {
        mkdir($outDir, 0777, true);
    }
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filenameBase) ?: 'rapport';
    $outPath = $outDir . '/' . $safe . '_' . date('Ymd_His') . '.xlsx';

    $writer = new Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->save($outPath);

    return $outPath;
}

/**
 * Export une épreuve.
 * @return array{path:string, candidates:list, ctx:array}
 */
function paperReportExportExcel($db, MongoDB\BSON\ObjectId $paperId, ?string $outDir = null, ?array $options = null): array
{
    $options = paperReportExcelNormalizeOptions($options);
    $ctx = paperReportLoadPaperContext($db, $paperId);
    if (!$ctx) {
        throw new RuntimeException('Paper not found');
    }

    $candidates = paperReportBuildAllCandidates($db, $ctx);
    $blocks = array(array(
        'ctx' => $ctx,
        'candidates' => $candidates,
        'paper_label' => $ctx['paper_label'],
        'paper_id' => (string) $paperId,
    ));

    $spreadsheet = paperReportExcelBuildSpreadsheet($blocks, $options);
    $path = paperReportExcelSave($spreadsheet, $ctx['paper_label'], $outDir);

    return array('path' => $path, 'candidates' => $candidates, 'ctx' => $ctx);
}

/**
 * Export plusieurs épreuves dans un même classeur.
 * @param list<MongoDB\BSON\ObjectId|string> $paperIds
 * @return array{path:string, papers:list, total_candidates:int}
 */
function paperReportExportMultiExcel($db, array $paperIds, ?string $outDir = null, ?string $bundleLabel = null, ?array $options = null): array
{
    $options = paperReportExcelNormalizeOptions($options);
    $blocks = array();
    $totalCandidates = 0;

    foreach ($paperIds as $pid) {
        $oid = $pid instanceof MongoDB\BSON\ObjectId ? $pid : new MongoDB\BSON\ObjectId((string) $pid);
        $ctx = paperReportLoadPaperContext($db, $oid);
        if (!$ctx) {
            continue;
        }
        $candidates = paperReportBuildAllCandidates($db, $ctx);
        $totalCandidates += count($candidates);
        $blocks[] = array(
            'ctx' => $ctx,
            'candidates' => $candidates,
            'paper_label' => $ctx['paper_label'],
            'paper_id' => (string) $oid,
        );
    }

    if ($blocks === array()) {
        throw new RuntimeException('Aucune épreuve valide sélectionnée');
    }

    if ($bundleLabel === null || trim($bundleLabel) === '') {
        if (count($blocks) === 1) {
            $bundleLabel = $blocks[0]['paper_label'];
        } else {
            $bundleLabel = 'Recrutement_PL_associe';
        }
    }

    $spreadsheet = paperReportExcelBuildSpreadsheet($blocks, $options);
    $path = paperReportExcelSave($spreadsheet, $bundleLabel, $outDir);

    return array(
        'path' => $path,
        'papers' => array_map(function ($b) {
            return array(
                'id' => $b['paper_id'],
                'label' => $b['paper_label'],
                'candidates' => count($b['candidates']),
            );
        }, $blocks),
        'total_candidates' => $totalCandidates,
    );
}
