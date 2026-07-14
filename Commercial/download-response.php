<?php
require_once "../vendor/autoload.php";

use MongoDB\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    http_response_code(400);
    exit('Token manquant.');
}

$client   = new Client("mongodb://localhost:27017");
$db       = $client->academy;
$response = $db->commercial_responses->findOne(['download_token' => $token]);

if (!$response) {
    http_response_code(404);
    exit('Réponse introuvable ou lien invalide.');
}

$qType   = (string)($response['questionnaire_type'] ?? '');
$brand   = (string)($response['brand']              ?? '');
$nom     = trim((string)($response['firstName'] ?? '') . ' ' . (string)($response['lastName'] ?? ''));
$email   = (string)($response['email']          ?? '');
$lang    = (string)($response['lang']            ?? 'FR');
$answers = (array)($response['answers']          ?? []);

$jsonFile = $qType === 'sinotruk' ? 'questionnaire_sinotruk.json' : 'questionnaire_bus_et_chariot.json';
$jsonPath = __DIR__ . '/' . $jsonFile;
if (!file_exists($jsonPath)) {
    http_response_code(500);
    exit('Fichier questionnaire introuvable.');
}
$qData   = json_decode(file_get_contents($jsonPath), true);
$langKey = $lang === 'EN' ? 'text_EN' : 'text_FR';

$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Réponses');

// Infos commercial en haut
$sheet->setCellValue('A1', 'Nom');      $sheet->setCellValue('B1', $nom);
$sheet->setCellValue('A2', 'Email');    $sheet->setCellValue('B2', $email);
$sheet->setCellValue('A3', 'Marque');   $sheet->setCellValue('B3', $brand);
$sheet->setCellValue('A4', 'Date');     $sheet->setCellValue('B4', (string)($response['submitted_at'] ?? ''));
$sheet->getStyle('A1:A4')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '24235C']],
]);
$sheet->getStyle('A1:B4')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F1FA']],
]);

// En-tête questions
$row = 6;
$sheet->setCellValue('A' . $row, '#');
$sheet->setCellValue('B' . $row, 'Question');
$sheet->setCellValue('C' . $row, 'Réponse');
$sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D70006']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension($row)->setRowHeight(22);
$row++;

$num = 1;
foreach ($qData['sections'] as $section) {
    $sTitle = $lang === 'EN' ? ($section['section_EN'] ?? $section['section_FR']) : $section['section_FR'];
    $sheet->setCellValue('A' . $row, '');
    $sheet->setCellValue('B' . $row, strtoupper($sTitle));
    $sheet->setCellValue('C' . $row, '');
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '24235C']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF0FF']],
    ]);
    $row++;

    foreach ($section['questions'] as $q) {
        $qId    = $q['id'];
        $qText  = isset($q[$langKey]) ? $q[$langKey] : $q['text_FR'];
        $answer = isset($answers[$qId]) ? trim((string)$answers[$qId]) : '—';
        $sheet->setCellValue('A' . $row, $num);
        $sheet->setCellValue('B' . $row, $qText);
        $sheet->setCellValue('C' . $row, $answer);
        $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true);
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(-1);
        $row++;
        $num++;
    }
}

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(58);
$sheet->getColumnDimension('C')->setWidth(62);

$fileName = 'Questionnaire_' . $brand . '_' . $email . '_' . date('d-m-Y') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
