<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Import candidats (stage) — Version Design Premium
 */

session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";
include_once "../sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ----------------------------------------------------------
 * DB & Config
 * -------------------------------------------------------- */
$client = new Client(medacapMongoUri());
$db     = $client->academy;
$users  = $db->users;
$papers = $db->papers;

const FIXED_SUBSIDIARY = "CFAO MOBILITY CAMEROON";
const FIXED_DEPARTMENT = "Equipment & Motors";
const FIXED_COUNTRY    = "Cameroun";
const CAMPAIGN_ROLE    = "Recrutement Technicien Campagne FNE-CFAO 2025";

/* ----------------------------------------------------------
 * Modèle Excel / JSON
 * -------------------------------------------------------- */
if (isset($_GET['template'])) {
    if ($_GET['template'] === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="template_candidats.json"');
        echo json_encode([
            'candidates' => [
                [
                    'firstName' => 'Awa',
                    'lastName' => 'Diop',
                    'tel1' => '+237 699000001',
                    'tel2' => '+237 620000001',
                ],
                [
                    'firstName' => 'Jean',
                    'lastName' => 'Ndola',
                    'tel1' => '699000002',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['firstName', 'lastName', 'Tel 1', 'Tel 2'],
        ['Awa', 'Diop', '+237 699000001', '+237 620000001'],
        ['Jean', 'Ndola', '+237 699000002', ''],
    ], NULL, 'A1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_candidats.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

/**
 * @return list<array{line:int, firstName:string, lastName:string}>
 */
function loadCandidateRowsFromUpload(array $file): array
{
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext === 'json') {
        return loadCandidateRowsFromJson($file['tmp_name']);
    }
    if ($ext === 'xlsx') {
        return loadCandidateRowsFromExcel($file['tmp_name']);
    }
    throw new RuntimeException('Format non supporté. Utilisez un fichier .xlsx ou .json.');
}

/**
 * @return list<array{line:int, firstName:string, lastName:string}>
 */
function loadCandidateRowsFromJson(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Impossible de lire le fichier JSON.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON invalide.');
    }

    $list = $decoded;
    if (isset($decoded['candidates']) && is_array($decoded['candidates'])) {
        $list = $decoded['candidates'];
    } elseif (isset($decoded[0]) === false && (isset($decoded['firstName']) || isset($decoded['firstname']) || isset($decoded['lastName']) || isset($decoded['lastname']))) {
        $list = [$decoded];
    }
    if (!is_array($list)) {
        throw new RuntimeException('Le JSON doit contenir un tableau de candidats.');
    }

    $rows = [];
    foreach ($list as $i => $item) {
        if (!is_array($item)) {
            continue;
        }
        $norm = normalizeCandidateNameRow($item);
        if ($norm === null) {
            continue;
        }
        $rows[] = [
            'line' => $i + 1,
            'firstName' => $norm['firstName'],
            'lastName' => $norm['lastName'],
            'phones' => extractPhonesFromImportRow($item),
        ];
    }
    if (empty($rows)) {
        throw new RuntimeException('Aucun candidat valide dans le JSON (firstName / lastName requis).');
    }
    return $rows;
}

/**
 * @return list<array{line:int, firstName:string, lastName:string}>
 */
function loadCandidateRowsFromExcel(string $path): array
{
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $data = $spreadsheet->getActiveSheet()->toArray();
    if (!$data || count($data) < 2) {
        throw new RuntimeException('Le fichier Excel est vide.');
    }

    $header = array_map('trim', array_map('strtolower', $data[0]));
    $idx = array_flip($header);
    if (!isset($idx['firstname']) && !isset($idx['lastname'])) {
        throw new RuntimeException('Au moins une colonne firstName ou lastName est requise dans le fichier Excel.');
    }
    unset($data[0]);

    $rows = [];
    foreach ($data as $rowNum => $row) {
        $assoc = [];
        foreach ($header as $colIdx => $colName) {
            $assoc[$colName] = $row[$colIdx] ?? '';
        }
        $norm = normalizeCandidateNameRow($assoc);
        if ($norm === null) {
            continue;
        }
        $rows[] = [
            'line' => (int)$rowNum + 1,
            'firstName' => $norm['firstName'],
            'lastName' => $norm['lastName'],
            'phones' => extractPhonesFromImportRow($assoc),
        ];
    }
    if (empty($rows)) {
        throw new RuntimeException('Aucun candidat valide dans le fichier Excel.');
    }
    return $rows;
}

/** @return array{firstName:string,lastName:string}|null */
function normalizeCandidateNameRow(array $row): ?array
{
    $map = [];
    foreach ($row as $k => $v) {
        $map[strtolower(trim((string)$k))] = $v;
    }
    $fNameInput = trim((string)($map['firstname'] ?? $map['prenom'] ?? ''));
    $lNameInput = trim((string)($map['lastname'] ?? $map['nom'] ?? ''));

    // Au moins un nom doit être présent
    if ($fNameInput === '' && $lNameInput === '') {
        return null;
    }

    // Si seulement firstName est rempli (ex: mononyme "HAMIDOU" sous firstName)
    if ($lNameInput === '' && $fNameInput !== '') {
        $lNameInput = $fNameInput;
        $fNameInput = '';
    }

    return [
        'firstName' => $fNameInput !== '' ? ucfirst(strtolower($fNameInput)) : '',
        'lastName'  => strtoupper($lNameInput),
    ];
}

function buildCandidateUsername(string $firstName, string $lastName): string
{
    $firstWord = function (string $value): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $value, 2);
        $word = $parts[0] ?? '';
        return $word === '' ? '' : mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
    };

    $fn = $firstWord($firstName);
    $ln = $firstWord($lastName);

    if ($fn !== '' && $ln !== '') {
        return trim($fn . ' ' . $ln);
    }
    return trim($fn . $ln);
}

/** @return array{label:string,raw:string,dialCode:string,national:string,display:string,e164:string}|null */
function parsePhoneValue(string $raw, string $label): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $dialCode = '';
    $national = '';
    $normalized = preg_replace('/\s+/u', ' ', $raw);

    if (preg_match('/^(\+\d{1,4})\s+(.+)$/u', $normalized, $m)) {
        $dialCode = $m[1];
        $national = preg_replace('/\D+/u', '', $m[2]);
    } elseif (preg_match('/^(\+\d{1,4})(\d{5,})$/u', preg_replace('/\s+/u', '', $normalized), $m)) {
        $dialCode = $m[1];
        $national = $m[2];
    } else {
        $national = preg_replace('/\D+/u', '', $normalized);
    }

    if ($national === '') {
        return null;
    }

    $display = $dialCode !== '' ? $dialCode . ' ' . $national : $national;
    $e164 = $dialCode !== '' ? $dialCode . $national : $national;

    return [
        'label' => $label,
        'raw' => $raw,
        'dialCode' => $dialCode,
        'national' => $national,
        'display' => $display,
        'e164' => $e164,
    ];
}

/** @return list<array{label:string,raw:string,dialCode:string,national:string,display:string,e164:string}> */
function extractPhonesFromImportRow(array $row): array
{
    $map = [];
    foreach ($row as $k => $v) {
        $map[strtolower(trim((string)$k))] = $v;
    }

    $definitions = [
        ['keys' => ['tel 1', 'tel1', 'phone', 'telephone'], 'label' => 'Tel 1'],
        ['keys' => ['tel 2', 'tel2', 'phone2', 'telephone2'], 'label' => 'Tel 2'],
    ];

    $phones = [];
    foreach ($definitions as $def) {
        foreach ($def['keys'] as $key) {
            if (!array_key_exists($key, $map)) {
                continue;
            }
            $parsed = parsePhoneValue((string)$map[$key], $def['label']);
            if ($parsed !== null) {
                $phones[] = $parsed;
            }
            break;
        }
    }

    return $phones;
}

/** @param list<array{label:string,raw:string,dialCode:string,national:string,display:string,e164:string}> $phones */
function candidatePhoneFields(array $phones): array
{
    if (empty($phones)) {
        return ['phones' => [], 'phone' => null];
    }

    return [
        'phones' => array_values($phones),
        'phone' => $phones[0]['display'] ?? $phones[0]['e164'] ?? null,
    ];
}

function importPendingSessionKey(): string
{
    return 'candidate_import_pending';
}

/** @param array{rh_id:string,campaign:string,rows:list<array>,created:int,updated:int,skipped:int,errors:list<string>} $data */
function savePendingImport(array $data): void
{
    $_SESSION[importPendingSessionKey()] = array_merge($data, ['mail_sent' => false, 'saved_at' => date('d-m-Y H:i:s')]);
}

function getPendingImport(): ?array
{
    $pending = $_SESSION[importPendingSessionKey()] ?? null;
    return is_array($pending) ? $pending : null;
}

function clearPendingImport(): void
{
    unset($_SESSION[importPendingSessionKey()]);
}

function markPendingImportMailSent(): void
{
    if (isset($_SESSION[importPendingSessionKey()]) && is_array($_SESSION[importPendingSessionKey()])) {
        $_SESSION[importPendingSessionKey()]['mail_sent'] = true;
    }
}

function importAssignedDemoSessionKey(): string
{
    return 'import_assigned_demo_accounts';
}

/** @param list<array{username:string,password:string}> $accounts */
function saveAssignedDemoAccountsForImport(array $accounts): void
{
    $_SESSION[importAssignedDemoSessionKey()] = array_values($accounts);
}

/** @return list<array{username:string,password:string}> */
function getAssignedDemoAccountsForImport(): array
{
    $accounts = $_SESSION[importAssignedDemoSessionKey()] ?? [];
    return is_array($accounts) ? $accounts : [];
}

/** @param list<string> $usernames */
function buildDemoAccountRowsFromUsernames(array $usernames, $usersCollection): array
{
    $rows = [];
    foreach ($usernames as $un) {
        $un = trim((string)$un);
        if ($un === '') {
            continue;
        }
        $doc = $usersCollection->findOne(['username' => $un, 'active' => true], ['projection' => ['username' => 1, 'visiblePassword' => 1]]);
        if (!$doc) {
            continue;
        }
        $rows[] = [
            'username' => (string)$doc['username'],
            'password' => (string)($doc['visiblePassword'] ?? '0000'),
        ];
    }
    return $rows;
}

include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");

/* ----------------------------------------------------------
 * Logique d'Import (avec Résolution de Conflits)
 * -------------------------------------------------------- */
$result = null; $errors = []; $createdRows = []; $conflicts = []; $testMailFlash = null;
$finalizeFlash = null;
$pendingImport = getPendingImport();

// Conclure : envoyer le mail RH pour l'import en attente
if (isset($_POST['finalize_import'])) {
    try {
        $pending = getPendingImport();
        if (!$pending || empty($pending['rows'])) {
            throw new RuntimeException('Aucun import en attente. Importez d\'abord un fichier.');
        }
        if (!empty($pending['mail_sent'])) {
            throw new RuntimeException('Le mail a déjà été envoyé pour cet import.');
        }

        $rhId = (string)($pending['rh_id'] ?? '');
        $rh = $rhId !== '' ? $users->findOne(['_id' => new ObjectId($rhId)]) : null;
        if (!$rh) {
            throw new RuntimeException('Responsable RH introuvable.');
        }
        $rhEmail = (string)($rh['email'] ?? '');
        if ($rhEmail === '') {
            throw new RuntimeException('Ce responsable RH n\'a pas d\'adresse email.');
        }

        $rows = $pending['rows'];
        $demoAccounts = $pending['demo_accounts'] ?? getAssignedDemoAccountsForImport();
        $sent = sendCandidateImportSummaryMail(
            $rhEmail,
            (string)($rh['firstName'] ?? ''),
            (string)($pending['campaign'] ?? CAMPAIGN_ROLE),
            count($rows),
            $rows,
            FIXED_COUNTRY,
            false,
            $demoAccounts
        );

        if (!$sent) {
            throw new RuntimeException('Échec d\'envoi du mail. Vérifiez la configuration SMTP.');
        }

        markPendingImportMailSent();
        $pendingImport = getPendingImport();
        $finalizeFlash = 'Mail envoyé à ' . $rhEmail . ' avec ' . count($rows) . ' candidat(s). Opération terminée.';
    } catch (Throwable $e) {
        $finalizeFlash = 'Erreur : ' . $e->getMessage();
    }
}

// Envoi d'un mail test (aperçu import, sans copie)
if (isset($_POST['send_test_import_mail'])) {
    try {
        $rhId = $_POST['rh_id'] ?? '';
        $rhFirstName = 'Responsable RH';
        if ($rhId && preg_match('/^[a-f0-9]{24}$/i', $rhId)) {
            $rhTest = $users->findOne(['_id' => new ObjectId($rhId)]);
            if ($rhTest) {
                $rhFirstName = (string)($rhTest['firstName'] ?? 'Responsable RH');
            }
        }

        $paperId = $_POST['paper_id'] ?? '';
        $campaignRole = CAMPAIGN_ROLE;
        if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
            $pDoc = $papers->findOne(['_id' => new ObjectId($paperId)]);
            if ($pDoc) {
                $campaignRole = (string)$pDoc['label'];
            }
        }

        $sampleRows = [
            ['lastName' => 'NGOUA', 'firstName' => 'Roland', 'username' => 'Roland Ngoua', 'password' => '0000'],
            ['lastName' => 'AMINGOUE', 'firstName' => 'Paulin', 'username' => 'Paulin Amingoue', 'password' => '0000'],
            ['lastName' => 'LAMBERT', 'firstName' => 'Eto', 'username' => 'Eto Lambert', 'password' => '0000'],
        ];

        $sent = sendCandidateImportSummaryMail(
            SUPER_ADMIN_EMAIL,
            $rhFirstName,
            $campaignRole,
            count($sampleRows),
            $sampleRows,
            FIXED_COUNTRY,
            true
        );

        $testMailFlash = $sent
            ? 'Mail test envoyé à ' . SUPER_ADMIN_EMAIL . ' (aucune copie).'
            : 'Échec envoi du mail test. Vérifiez la configuration SMTP.';
    } catch (Throwable $e) {
        $testMailFlash = 'Erreur mail test : ' . $e->getMessage();
    }
}

// Traitement d'import initial
if (isset($_POST["submit"])) {
    try {
        $rhId = $_POST['rh_id'] ?? '';
        if (!$rhId || !preg_match('/^[a-f0-9]{24}$/i', $rhId)) throw new RuntimeException("Veuillez sélectionner un RH.");

        // Charger le document RH pour récupérer son email
        $rh = $users->findOne(['_id' => new ObjectId($rhId)]);
        if (!$rh) throw new RuntimeException("Responsable RH introuvable (ID: $rhId).");
        
        $paperId = $_POST['paper_id'] ?? '';
        $campaignRole = CAMPAIGN_ROLE;
        if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
            $pDoc = $papers->findOne(['_id' => new ObjectId($paperId)]);
            if ($pDoc) $campaignRole = (string)$pDoc['label'];
        }

        if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Fichier invalide.");

        $candidateRows = loadCandidateRowsFromUpload($_FILES['excel']);

        $created = 0; $updated = 0; $skipped = 0;
        foreach ($candidateRows as $entry) {
            $rowNum = $entry['line'];
            $fName = $entry['firstName'];
            $lName = $entry['lastName'];
            $phones = $entry['phones'] ?? [];
            $phoneFields = candidatePhoneFields($phones);
            $fNameInput = $fName;
            $lNameInput = $lName;

            // 1. Recherche Exacte
            $exactCriteria = ['lastName' => $lName, 'active' => true];
            if ($fName !== '') {
                $exactCriteria['firstName'] = $fName;
            }
            $exact = $users->findOne($exactCriteria);

            // 2. Recherche Floue (Prénom/Nom inversés, si prénom présent)
            $fuzzy = null;
            if (!$exact && $fNameInput !== '' && $lNameInput !== '') {
                // On cherche si un candidat existe avec Nom = InputPrénom et Prénom = InputNom
                $fuzzy = $users->findOne(['firstName'=>ucfirst(strtolower($lNameInput)), 'lastName'=>strtoupper($fNameInput), 'active'=>true]);
            }

            if ($exact) {
                $existingRoles = ($exact['roles'] instanceof \MongoDB\Model\BSONArray) ? $exact['roles']->getArrayCopy() : (array)($exact['roles'] ?? []);
                if (!in_array($campaignRole, $existingRoles)) {
                    $updateSet = ['role' => $campaignRole, 'hr' => new ObjectId($rhId), 'status' => 'New'];
                    if (!empty($phoneFields['phones']) && empty($exact['phones'])) {
                        $updateSet['phones'] = $phoneFields['phones'];
                        $updateSet['phone'] = $phoneFields['phone'];
                    }
                    if (stripos($campaignRole, 'Garoua') !== false && empty($exact['agency'])) {
                        $updateSet['agency'] = 'Garoua';
                    }
                    $users->updateOne(['_id' => $exact['_id']], [
                        '$set' => $updateSet,
                        '$addToSet' => ['roles' => $campaignRole]
                    ]);
                    $updated++;
                    $createdRows[] = ['firstName'=>$exact['firstName'], 'lastName'=>$exact['lastName'], 'username'=>$exact['username'], 'password'=>$exact['visiblePassword']??'0000'];
                } else {
                    $skipped++;
                    $errors[] = "Ligne ".($rowNum+1).": " . trim($fNameInput . ' ' . $lNameInput) . " est déjà inscrit.";
                }
            } elseif ($fuzzy) {
                // CONFLIT : On demande à l'utilisateur
                $conflicts[] = [
                    'input' => ['firstName' => $fName, 'lastName' => $lName],
                    'match' => ['_id' => (string)$fuzzy['_id'], 'firstName' => $fuzzy['firstName'], 'lastName' => $fuzzy['lastName']],
                    'phones' => $phones,
                    'rh_id' => $rhId,
                    'campaign' => $campaignRole
                ];
            } else {
                // Création Nouvelle
                $base = buildCandidateUsername($fName, $lName);
                $visible = "0000";
                $agency = null;
                if (stripos($campaignRole, 'Garoua') !== false) {
                    $agency = 'Garoua';
                }
                $doc = [
                    "matricule" => '999999', "username" => $base, "firstName" => $fName, "lastName" => $lName,
                    "phone" => $phoneFields['phone'], "phones" => $phoneFields['phones'],
                    "profile" => "Candidat", "country" => FIXED_COUNTRY, "subsidiary" => FIXED_SUBSIDIARY, "department" => FIXED_DEPARTMENT,
                    "agency" => $agency,
                    "role" => $campaignRole, "roles" => [$campaignRole], "password" => sha1($visible), "visiblePassword" => $visible,
                    "hr" => new ObjectId($rhId), "active" => true, "status" => "New", "created" => date("d-m-Y H:i:s")
                ];
                $users->insertOne($doc);
                $created++;
                $createdRows[] = ['firstName'=>$doc['firstName'], 'lastName'=>$doc['lastName'], 'username'=>$doc['username'], 'password'=>$visible];
            }
        }

        // Pas de mail ici : l'envoi se fait via « Terminer et envoyer le mail »
        if (($created + $updated) > 0 && !empty($createdRows)) {
            $existingPending = getPendingImport();
            $demoAccounts = $existingPending['demo_accounts'] ?? getAssignedDemoAccountsForImport();
            if (empty($demoAccounts)) {
                $defaultDemo = $users->findOne(['username' => 'Test DEMO', 'active' => true]);
                if ($defaultDemo) {
                    $demoAccounts = [['username' => 'Test DEMO', 'password' => (string)($defaultDemo['visiblePassword'] ?? '0000')]];
                }
            }
            if (!empty($demoAccounts)) {
                foreach ($demoAccounts as $demo) {
                    $dUn = trim((string)($demo['username'] ?? ''));
                    if ($dUn === '') continue;
                    $dSet = ['role' => $campaignRole, 'status' => 'New', 'active' => true];
                    if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
                        $dSet['papers'] = [new ObjectId($paperId)];
                    }
                    $users->updateOne(
                        ['username' => $dUn, 'active' => true],
                        ['$set' => $dSet, '$addToSet' => ['roles' => $campaignRole]]
                    );
                }
            }
            savePendingImport([
                'rh_id' => $rhId,
                'campaign' => $campaignRole,
                'rows' => $createdRows,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'demo_accounts' => $demoAccounts,
            ]);
            $pendingImport = getPendingImport();
        }

        $result = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'mail_pending' => ($created + $updated) > 0 && empty($conflicts),
        ];
    } catch (Throwable $e) { $errors[] = $e->getMessage(); $result = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>$errors]; }
}

// Traitement des résolutions de conflits
if (isset($_POST["submit_resolution"])) {
    $resolutions = $_POST['res'] ?? [];
    $rhId = $_POST['rh_id_res'] ?? '';
    $camp = $_POST['campaign_res'] ?? '';
    $created = 0; $updated = 0;
    $resolvedRows = [];

    foreach ($resolutions as $idx => $action) {
        $fName = $_POST['fName_res'][$idx];
        $lName = $_POST['lName_res'][$idx];
        $matchId = $_POST['matchId_res'][$idx];
        $phones = json_decode((string)($_POST['phones_res'][$idx] ?? '[]'), true);
        if (!is_array($phones)) {
            $phones = [];
        }
        $phoneFields = candidatePhoneFields($phones);

        if ($action === 'merge') {
            $merged = $users->findOne(['_id' => new ObjectId($matchId)]);
            $users->updateOne(['_id' => new ObjectId($matchId)], [
                '$set' => ['role' => $camp, 'hr' => new ObjectId($rhId), 'status' => 'New'],
                '$addToSet' => ['roles' => $camp]
            ]);
            $updated++;
            if ($merged) {
                $resolvedRows[] = [
                    'firstName' => (string)$merged['firstName'],
                    'lastName' => (string)$merged['lastName'],
                    'username' => (string)$merged['username'],
                    'password' => (string)($merged['visiblePassword'] ?? '0000'),
                ];
            }
        } else {
            $base = buildCandidateUsername($fName, $lName);
            $visible = "0000";
            $users->insertOne([
                "matricule" => '999999', "username" => $base, "firstName" => $fName, "lastName" => $lName,
                "phone" => $phoneFields['phone'], "phones" => $phoneFields['phones'],
                "profile" => "Candidat", "country" => FIXED_COUNTRY, "subsidiary" => FIXED_SUBSIDIARY, "department" => FIXED_DEPARTMENT,
                "role" => $camp, "roles" => [$camp], "password" => sha1($visible), "visiblePassword" => $visible,
                "hr" => new ObjectId($rhId), "active" => true, "status" => "New", "created" => date("d-m-Y H:i:s")
            ]);
            $created++;
            $resolvedRows[] = ['firstName' => $fName, 'lastName' => $lName, 'username' => $base, 'password' => $visible];
        }
    }

    $pending = getPendingImport();
    $allRows = array_merge($pending['rows'] ?? [], $resolvedRows);
    $demoAccounts = $pending['demo_accounts'] ?? getAssignedDemoAccountsForImport();
    if (!empty($allRows)) {
        savePendingImport([
            'rh_id' => $rhId,
            'campaign' => $camp,
            'rows' => $allRows,
            'created' => ($pending['created'] ?? 0) + $created,
            'updated' => ($pending['updated'] ?? 0) + $updated,
            'skipped' => $pending['skipped'] ?? 0,
            'errors' => ["Conflits résolus : $created créés, $updated fusionnés."],
            'demo_accounts' => $demoAccounts,
        ]);
        $pendingImport = getPendingImport();
    }

    $result = [
        'created' => $created,
        'updated' => $updated,
        'skipped' => 0,
        'errors' => ["Conflits résolus : $created créés, $updated fusionnés."],
        'mail_pending' => !empty($allRows),
    ];
}

// Nouvelle Logique : Assignation forcée aux comptes DEMO (Indépendante)
if (isset($_POST["assign_demo"]) && !empty($_POST['demo_accounts'])) {
    try {
        $demoUsernames = (array)$_POST['demo_accounts'];
        $paperId = $_POST['paper_id'] ?? '';
        $campRole = CAMPAIGN_ROLE;
        if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
            $p = $papers->findOne(['_id' => new ObjectId($paperId)]);
            if ($p) $campRole = (string)$p['label'];
        }

        $rhId = $_POST['rh_id'] ?? '';
        $updateData = ['$set' => ['role' => $campRole, 'status' => 'New'], '$addToSet' => ['roles' => $campRole]];

        // On n'ajoute le RH que s'il est sélectionné (facultatif pour les DEMO)
        if ($rhId && preg_match('/^[a-f0-9]{24}$/i', $rhId)) {
            $updateData['$set']['hr'] = new ObjectId($rhId);
        }

        $count = 0;
        foreach ($demoUsernames as $un) {
            $users->updateOne(['username' => $un, 'active' => true], $updateData);
            $count++;
        }

        $demoRows = buildDemoAccountRowsFromUsernames($demoUsernames, $users);
        saveAssignedDemoAccountsForImport($demoRows);

        $pending = getPendingImport();
        if ($pending) {
            savePendingImport(array_merge($pending, ['demo_accounts' => $demoRows]));
            $pendingImport = getPendingImport();
        }

        $result = [
            'created' => 0,
            'updated' => $count,
            'skipped' => 0,
            'errors' => [],
            'demo_assign' => true,
            'message' => "$count compte(s) démo assigné(s) à la campagne « $campRole ».",
        ];
    } catch (Throwable $e) { $errors[] = $e->getMessage(); $result = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>$errors]; }
}

$GLOBALS['pageTitle'] = 'Importation de candidats';
include_once "partials/header.php";
openBackgroundContainer('import-page-scope');
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Hauteur fixe = pas de scroll page */
.import-page-scope.page-background-container {
    height: calc(100vh - 175px);
    max-height: calc(100vh - 175px);
    overflow: hidden;
    box-sizing: border-box;
}
.import-page-scope .page-content-container {
    height: 100%;
    padding: 14px 22px;
    box-sizing: border-box;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.import-candidat-page {
    font-family: 'Inter', system-ui, sans-serif;
    color: #0f172a;
    line-height: 1.45;
    font-size: 16px;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.shell {
    width: 100%;
    max-width: 1380px;
    margin: 0 auto;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Carte titre — séparée */
.title-card {
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    padding: 18px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.title-card h1 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
}
.head-alerts { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
.head-alert {
    padding: 7px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 500;
}
.head-alert.ok { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
.head-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.head-alert.warn { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

/* Carte workflow */
.workflow-card {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,.1);
    overflow: hidden;
}
#importForm {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.master-grid {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 1.05fr 1.25fr 1fr 1fr;
    gap: 0;
}
.step-col {
    padding: 20px 22px;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}
.step-col:last-child { border-right: none; }

.step-label {
    display: block;
    font-size: 0.95rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #475569;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
}
.step-label span { color: #1e3a8a; font-size: 1.05rem; }

.field { margin-bottom: 14px; }
.field label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
}
.req { color: #dc2626; }

select, input[type="file"] {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #fff;
}
select:focus { outline: 2px solid #3b82f6; outline-offset: 1px; }

.hint { margin-top: 6px; font-size: 0.88rem; color: #64748b; line-height: 1.4; }
.hint code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; }
.tpl-inline { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
.tpl-inline a {
    font-size: 0.85rem; font-weight: 600; color: #1d4ed8; text-decoration: none;
    padding: 7px 12px; background: #eff6ff; border-radius: 6px; border: 1px solid #bfdbfe;
}

.btn-step {
    margin-top: auto;
    flex-shrink: 0;
    padding: 13px 16px;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
}
.btn-import { background: #1e3a8a; color: #fff; }
.btn-import:hover { background: #1e40af; }
.btn-demo { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
.btn-demo:hover { background: #e2e8f0; }
.btn-finalize { background: #059669; color: #fff; }
.btn-finalize:hover { background: #047857; }
.btn-finalize:disabled { background: #94a3b8; cursor: not-allowed; }

.demo-list {
    display: flex; flex-direction: column; gap: 8px;
    margin-bottom: 10px; flex: 1; min-height: 0; overflow-y: auto;
}
.demo-list label {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 0.95rem; cursor: pointer;
}
.demo-list input { width: 18px; height: 18px; }

.finalize-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    font-size: 0.92rem;
    color: #475569;
    margin-bottom: 12px;
    line-height: 1.5;
}
.finalize-box strong { color: #0f172a; }
.finalize-box.done { color: #065f46; }

.master-foot {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 12px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.88rem;
}
.master-foot a, .master-foot button {
    color: #64748b; background: none; border: none; cursor: pointer;
    font-size: inherit; text-decoration: underline; padding: 0;
}
.master-foot a:hover, .master-foot button:hover { color: #1e3a8a; }

.conflict-bar {
    flex-shrink: 0;
    max-height: 140px;
    overflow-y: auto;
    padding: 12px 24px;
    border-top: 1px solid #fcd34d;
    background: #fffbeb;
    font-size: 0.88rem;
}
.conflict-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 8px; }
.conflict-table th, .conflict-table td { padding: 6px 10px; border-bottom: 1px solid #fde68a; text-align: left; }
.btn-warn {
    margin-top: 8px; padding: 8px 14px; border: none; border-radius: 8px;
    background: #d97706; color: #fff; font-weight: 600; cursor: pointer; font-size: 0.85rem;
}

@media (max-width: 1100px) {
    .import-page-scope.page-background-container {
        height: auto;
        max-height: none;
        overflow: visible;
    }
    .import-page-scope .page-content-container { overflow: visible; height: auto; }
    .import-candidat-page { min-height: auto; }
    .master-grid { grid-template-columns: 1fr 1fr; }
    .step-col:nth-child(2) { border-right: none; }
    .step-col { border-bottom: 1px solid #e2e8f0; }
    .step-col:nth-child(3), .step-col:nth-child(4) { border-bottom: none; }
}
</style>

<div class="import-candidat-page">
<div class="shell">

    <?php
    $showFinalize = $pendingImport && !empty($pendingImport['rows']) && empty($pendingImport['mail_sent']);
    $mailAlreadySent = $pendingImport && !empty($pendingImport['mail_sent']);
    $isDemoAssign = $result && !empty($result['demo_assign']);
    $isSuccess = $result && ($isDemoAssign || ($result['created'] ?? 0) > 0 || ($result['updated'] ?? 0) > 0);
    ?>

    <div class="title-card">
        <h1>Importation de candidats</h1>
        <div class="head-alerts">
            <?php if ($finalizeFlash): ?>
                <span class="head-alert <?= strpos($finalizeFlash, 'Mail envoyé') === 0 ? 'ok' : 'err' ?>"><?= htmlspecialchars($finalizeFlash) ?></span>
            <?php endif; ?>
            <?php if ($testMailFlash): ?>
                <span class="head-alert <?= strpos($testMailFlash, 'Mail test envoyé') === 0 ? 'ok' : 'err' ?>"><?= htmlspecialchars($testMailFlash) ?></span>
            <?php endif; ?>
            <?php if ($result): ?>
                <span class="head-alert <?= $isDemoAssign ? 'warn' : ($isSuccess ? 'ok' : 'err') ?>">
                    <?php if ($isDemoAssign): ?>
                        Démo assigné — pas dans le mail RH
                    <?php elseif ($isSuccess): ?>
                        <?= (int)$result['created'] ?> créé(s), <?= (int)$result['updated'] ?> MAJ, <?= (int)$result['skipped'] ?> ignoré(s)
                    <?php else: ?>
                        Échec : <?= htmlspecialchars($result['errors'][0] ?? 'erreur') ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($mailAlreadySent): ?>
                <span class="head-alert ok">Mail RH envoyé — opération terminée</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="workflow-card">
        <form method="post" enctype="multipart/form-data" id="importForm">
            <div class="master-grid">
                <section class="step-col">
                    <span class="step-label">Configuration</span>
                    <div class="field">
                        <label>Responsable RH <span class="req">*</span></label>
                        <select name="rh_id" required>
                            <option value="">— Choisir —</option>
                            <?php
                            $cursor = $users->find(['active' => true, 'profile' => 'Ressource Humaine'], ['sort' => ['lastName' => 1]]);
                            foreach ($cursor as $u) {
                                echo '<option value="' . (string)$u->_id . '">' . strtoupper((string)$u->lastName) . ' ' . ucfirst((string)$u->firstName) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Campagne / épreuve</label>
                        <select name="paper_id">
                            <option value="">— Par défaut —</option>
                            <?php
                            $pList = $papers->find(['active' => true], ['sort' => ['created' => -1]]);
                            foreach ($pList as $p) {
                                echo '<option value="' . (string)$p->_id . '">' . htmlspecialchars((string)$p['label']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </section>

                <section class="step-col">
                    <span class="step-label"><span>Étape 1</span> — Import fichier</span>
                    <div class="field">
                        <label>Fichier Excel ou JSON <span class="req">*</span></label>
                        <input type="file" name="excel" id="excelInput" accept=".xlsx,.json,application/json" required>
                        <p class="hint"><code>firstName</code>, <code>lastName</code> (+ tél. optionnel)</p>
                        <div class="tpl-inline">
                            <a href="?template=1">Modèle Excel</a>
                            <a href="?template=json">Modèle JSON</a>
                        </div>
                    </div>
                    <button type="submit" name="submit" class="btn-step btn-import" id="btnImport">Importer</button>
                </section>

                <section class="step-col">
                    <span class="step-label"><span>Étape 2</span> — Compte démo</span>
                    <p class="hint" style="margin:0 0 8px;">Optionnel — avant ou après l'import. Hors mail RH.</p>
                    <div class="demo-list">
                        <?php
                        $demoList = $users->find(['username' => new \MongoDB\BSON\Regex('demo', 'i'), 'active' => true]);
                        $hasDemo = false;
                        foreach ($demoList as $d) {
                            $hasDemo = true;
                            $un = (string)$d['username'];
                            echo '<label><input type="checkbox" name="demo_accounts[]" value="' . htmlspecialchars($un, ENT_QUOTES, 'UTF-8') . '"> ' . htmlspecialchars($un) . '</label>';
                        }
                        if (!$hasDemo) {
                            echo '<span class="hint">Aucun compte démo</span>';
                        }
                        ?>
                    </div>
                    <button type="submit" name="assign_demo" class="btn-step btn-demo">Assigner démo</button>
                </section>

                <section class="step-col">
                    <span class="step-label"><span>Étape 3</span> — Conclure</span>
                    <div class="finalize-box <?= $mailAlreadySent ? 'done' : '' ?>">
                        <?php if ($showFinalize): ?>
                            <strong><?= count($pendingImport['rows']) ?> candidat(s)</strong> prêt(s).<br>
                            Envoyer le récap au RH pour conclure.
                        <?php elseif ($mailAlreadySent): ?>
                            ✓ Mail envoyé pour <?= count($pendingImport['rows']) ?> candidat(s).
                        <?php else: ?>
                            Importez d'abord un fichier (étape 1), puis concluez ici.
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="finalize_import" class="btn-step btn-finalize" <?= $showFinalize ? '' : 'disabled' ?>>Envoyer mail RH</button>
                </section>
            </div>

            <div class="master-foot">
                <button type="submit" name="send_test_import_mail">Mail test</button>
                <a href="resend_import_mail.php">Mail de rattrapage</a>
            </div>
        </form>

        <?php if ($result && !empty($result['conflicts'])): ?>
            <div class="conflict-bar">
                <strong>Conflits de noms</strong> — choisissez fusion ou nouveau compte :
                <form method="post">
                    <input type="hidden" name="rh_id_res" value="<?= htmlspecialchars($result['conflicts'][0]['rh_id']) ?>">
                    <input type="hidden" name="campaign_res" value="<?= htmlspecialchars($result['conflicts'][0]['campaign']) ?>">
                    <table class="conflict-table">
                        <thead><tr><th>Fichier</th><th>Existant</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($result['conflicts'] as $i => $c): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($c['input']['lastName'] . ' ' . $c['input']['firstName']) ?>
                                        <input type="hidden" name="fName_res[<?= $i ?>]" value="<?= htmlspecialchars($c['input']['firstName']) ?>">
                                        <input type="hidden" name="lName_res[<?= $i ?>]" value="<?= htmlspecialchars($c['input']['lastName']) ?>">
                                        <input type="hidden" name="matchId_res[<?= $i ?>]" value="<?= htmlspecialchars($c['match']['_id']) ?>">
                                        <input type="hidden" name="phones_res[<?= $i ?>]" value="<?= htmlspecialchars(json_encode($c['phones'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
                                    </td>
                                    <td><?= htmlspecialchars($c['match']['lastName'] . ' ' . $c['match']['firstName']) ?></td>
                                    <td>
                                        <select name="res[<?= $i ?>]" style="font-size:.8rem;">
                                            <option value="merge">Fusionner</option>
                                            <option value="new">Nouveau</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="submit_resolution" class="btn-warn">Valider conflits</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            const form = document.getElementById('importForm');
            const fileInput = document.getElementById('excelInput');
            function relax() {
                if (fileInput) fileInput.required = false;
                const rh = form?.querySelector('select[name="rh_id"]');
                if (rh) rh.required = false;
            }
            form?.querySelector('button[name="assign_demo"]')?.addEventListener('click', relax);
            form?.querySelector('button[name="send_test_import_mail"]')?.addEventListener('click', relax);
            form?.querySelector('button[name="finalize_import"]')?.addEventListener('click', relax);
            document.getElementById('btnImport')?.addEventListener('click', function () {
                if (fileInput) fileInput.required = true;
                const rh = form?.querySelector('select[name="rh_id"]');
                if (rh) rh.required = true;
            });
        })();
    </script>

</div>
</div>
<?php 
closeBackgroundContainer();
include_once "partials/footer.php"; 
?>
