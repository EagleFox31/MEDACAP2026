<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Insère la banque de questions Senior « Engins » (recrutement Electromécanicien Engins, novembre 2026).
 *
 * Source : scripts/data/questions_engins_senior_2026_11.json (une entrée par question, clé = ref « FacEng-… »).
 * Les questions ne sont rattachées à aucun quiz : les quiz Senior existants sont partagés par 18 marques.
 * L'épreuve les retrouve par leur ref (create_electromecanicien_engins_paper.php).
 * Idempotent : une ref existante identique est ignorée, une ref modifiée dans le JSON est mise à jour.
 * La bonne réponse est placée à une position fixe dérivée de la ref (crc32), identique au fichier de relecture.
 * --data=<fichier> : autre banque au même format (ex. électricité PL « FacPL-… », niveau lu dans la clé « level »).
 *
 * Usage:
 *   php scripts/insert_engins_questions.php --dry-run
 *   php scripts/insert_engins_questions.php
 *   php scripts/insert_engins_questions.php --data=scripts/data/questions_pl_elec_2026_11.json [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

$dryRun = in_array('--dry-run', $argv, true);
$uri = medacapMongoUri('mongodb://10.68.0.7:27017');

const LEVEL = 'Senior';
const DATA_FILE = __DIR__ . '/data/questions_engins_senior_2026_11.json';

$dataFile = DATA_FILE;
$dbName = 'academy';
foreach ($argv as $arg) {
    if (strpos($arg, '--data=') === 0) {
        $dataFile = substr($arg, 7);
    } elseif (strpos($arg, '--db=') === 0) {
        $dbName = substr($arg, 5);
    }
}
$db = (new Client($uri))->selectDatabase($dbName);

$items = json_decode((string) file_get_contents($dataFile), true);
if (!is_array($items) || empty($items)) {
    fwrite(STDERR, "[ERREUR] Fichier illisible : $dataFile\n");
    exit(1);
}

echo "Mongo: $uri\nFichier: $dataFile\nQuestions: " . count($items) . "\nMode: " . ($dryRun ? 'DRY-RUN' : 'INSERTION') . "\n\n";

/** Propositions dans l'ordre affiché : la bonne réponse en position crc32(ref) % 4. */
function buildProposals(array $it): array
{
    $wrong = array_values($it['wrong']);
    $pos = crc32($it['ref']) % 4;
    array_splice($wrong, $pos, 0, [$it['answer']]);
    return $wrong;
}

// Contrôles avant toute écriture
$errors = [];
$seen = [];
foreach ($items as $i => $it) {
    $ref = $it['ref'] ?? "#$i";
    foreach (['ref', 'domain', 'speciality', 'label', 'answer', 'source'] as $k) {
        if (!isset($it[$k]) || trim((string) $it[$k]) === '') {
            $errors[] = "$ref : champ $k vide";
        }
    }
    if (!isset($it['wrong']) || count($it['wrong']) !== 3) {
        $errors[] = "$ref : 3 mauvaises réponses attendues";
    } elseif (in_array($it['answer'], $it['wrong'], true) || count(array_unique($it['wrong'])) !== 3) {
        $errors[] = "$ref : propositions en double";
    }
    if (!in_array($it['level'] ?? LEVEL, ['Junior', 'Senior', 'Expert'], true)) {
        $errors[] = "$ref : niveau inconnu";
    }
    if (isset($seen[$ref])) {
        $errors[] = "$ref : ref en double";
    }
    $seen[$ref] = true;
}
if (!empty($errors)) {
    fwrite(STDERR, "[ERREUR]\n  " . implode("\n  ", $errors) . "\n");
    exit(1);
}

$stats = ['insérées' => 0, 'mises à jour' => 0, 'inchangées' => 0];
$byDomain = [];
foreach ($items as $it) {
    $p = buildProposals($it);
    $fields = [
        'label' => $it['label'],
        'proposal1' => $p[0],
        'proposal2' => $p[1],
        'proposal3' => $p[2],
        'proposal4' => $p[3],
        'answer' => $it['answer'],
        'speciality' => $it['speciality'],
        'type' => 'Factuelle',
        'level' => $it['level'] ?? LEVEL,
        'active' => true,
    ];
    // Illustration : chemin relatif à public/files/ (ex. engins/FacEng-MOT-18.svg)
    if (array_key_exists('image', $it)) {
        $fields['image'] = $it['image'];
    }
    $key =$fields['level'] . ' / ' . $it['domain'];
    $byDomain[$key] = ($byDomain[$key] ?? 0) + 1;

    $existing = $db->questions->findOne(['ref' => $it['ref']]);
    if ($existing) {
        $changed = [];
        foreach ($fields as $k => $v) {
            $has = is_array($existing) ? array_key_exists($k, $existing) : $existing->offsetExists($k);
            if (!$has || $existing[$k] !== $v) {
                $changed[$k] = $v;
            }
        }
        if (empty($changed)) {
            $stats['inchangées']++;
            continue;
        }
        $stats['mises à jour']++;
        echo "  ~ {$it['ref']} : " . implode(', ', array_keys($changed)) . "\n";
        if (!$dryRun) {
            $changed['updated'] = date('d-m-Y H:i:s');
            $db->questions->updateOne(['_id' => $existing['_id']], ['$set' => $changed]);
        }
        continue;
    }

    $stats['insérées']++;
    if (!$dryRun) {
        $db->questions->insertOne(['ref' => $it['ref']] + $fields + ['image' => null, 'created' => date('d-m-Y H:i:s')]);
    }
}

foreach ($byDomain as $d => $n) {
    echo "  - $d : $n\n";
}
echo "\n";
foreach ($stats as $k => $n) {
    echo "  $k : $n\n";
}
if ($dryRun) {
    echo "\n[DRY-RUN] Aucune écriture.\n";
}
