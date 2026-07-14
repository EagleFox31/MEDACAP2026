<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://localhost:27017';
$db = (new Client($uri))->academy;

$extraFixes = [
    '/\s+\?/u' => ' ?',
    '/\bQuelle est intensité\b/ui' => "Quelle est l'intensité",
    '/\bdurée de vie maximal\b/ui' => 'durée de vie maximale',
    '/\bA quoi sert le bande\b/ui' => 'A quoi sert la bande',
    '/\bQuelle est le rôle\b/ui' => 'Quel est le rôle',
    '/\bsignifiance\b/ui' => 'signification',
    '/\bQuelle type\b/ui' => 'Quel type',
    '/\bA quel élément\b/ui' => 'À quel élément',
    '/\bA quoi\b/ui' => 'À quoi',
    '/\bDe quoi\b/ui' => 'De quoi',
    '/\beffectue t-on\b/ui' => 'effectue-t-on',
    '/\bmesure t-on\b/ui' => 'mesure-t-on',
    '/\bappelle t-on\b/ui' => 'appelle-t-on',
    '/\butilise-t-on\b/ui' => 'utilise-t-on',
    '/\bn\'utilise-t-on\b/ui' => "n'utilise-t-on",
    '/\bQuel est la fonction\b/ui' => 'Quelle est la fonction',
    '/\bQuel est la définition\b/ui' => 'Quelle est la définition',
    '/\bQuel est la formule\b/ui' => 'Quelle est la formule',
    '/\bQuel sont\b/ui' => 'Quels sont',
    '/\b12v\b/ui' => '12 V',
    '/\b6 ohms\b/ui' => '6 Ω',
];

$paper = $db->papers->findOne(
    [
        'active' => true,
        '$and' => [
            ['label' => new Regex('Poids Lourds 2026', 'i')],
            ['label' => ['$not' => new Regex('Renault Truck', 'i')]],
        ],
    ],
    ['sort' => ['created' => -1]]
);

$oids = [];
foreach ((array)($paper['questions'] ?? []) as $x) {
    if ($x instanceof ObjectId) $oids[] = $x;
    elseif (is_string($x) && preg_match('/^[a-f0-9]{24}$/i', $x)) $oids[] = new ObjectId($x);
}

$juniorIds = [];
foreach ($db->quizzes->find(['level' => 'Junior'], ['projection' => ['questions' => 1]]) as $qz) {
    foreach ((array)($qz['questions'] ?? []) as $qid) {
        $juniorIds[(string)($qid instanceof ObjectId ? $qid : $qid)] = true;
    }
}

$n = 0;
foreach ($oids as $oid) {
    if (!isset($juniorIds[(string)$oid])) continue;
    $q = $db->questions->findOne(['_id' => $oid]);
    if (!$q) continue;
    $orig = (string)($q['label'] ?? '');
    $new = $orig;
    foreach ($extraFixes as $pat => $rep) {
        $new = preg_replace($pat, $rep, $new);
    }
    $new = trim($new);
    if ($new !== $orig) {
        $db->questions->updateOne(['_id' => $oid], ['$set' => ['label' => $new]]);
        $n++;
    }
}
echo "Second pass: $n corrections\n";

function hasImage($q): bool {
    $img = $q['image'] ?? null;
    return !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
}

function needsImage(string $text): bool {
    return (bool)preg_match('/ci-dessous|ci-joint|schéma|figure|image|représente le|marquage\s+185|1200\s*A|micromètre|train planétaire|embrayage|suspension à lame|direction|freinage pneumatique|valve illustrée|boitier ci/i', $text);
}

function geminiPrompt($q): string {
    if ($q instanceof MongoDB\Model\BSONDocument) {
        $q = $q->getArrayCopy();
    }
    $id = (string)$q['_id'];
    $text = (string)($q['label'] ?? '');
    $spec = (string)($q['speciality'] ?? '');
    $p1 = (string)($q['proposal1'] ?? '');
    $p2 = (string)($q['proposal2'] ?? '');
    $p3 = (string)($q['proposal3'] ?? '');
    $p4 = (string)($q['proposal4'] ?? '');

  $base = "Technical training illustration for heavy-duty truck mechanic exam. Clean educational diagram, white background, high contrast, French labels, no watermark, no text clutter. Style: automotive workshop manual, vector-like clarity.";

    if (preg_match('/185\/60.*\b14\b/i', $text) && preg_match('/représente\s+14/i', $text)) {
        return "$base Subject: Truck tire sidewall marking « 185/60 R 14 88 H » with arrow pointing to rim diameter « 14 » (inches). Label each part of the marking clearly.";
    }
    if (preg_match('/185\/60.*88/i', $text)) {
        return "$base Subject: Close-up of truck tire sidewall marking « 185/60 R 14 88 H » with arrow pointing to load index « 88 ». Show all segments labeled: width, aspect ratio, rim diameter, load index, speed rating.";
    }
    if (preg_match('/1200\s*A/i', $text)) {
        return "$base Subject: Vehicle battery top view showing polarity symbols, terminals, and label « 1200 A » (cranking amps) clearly highlighted with arrow.";
    }
    if (preg_match('/micromètre/i', $text)) {
        return "$base Subject: Outside micrometer measuring a steel shaft, sleeve scale and thimble scale visible, realistic reading zone on the instrument (for mechanic reading exercise).";
    }
    if (preg_match('/train planétaire|pont/i', $text) && preg_match('/numéro|repère|élément\s+[234]/i', $text)) {
        preg_match('/numéro\s*(\d+)|repère\s*(\d+)|élément\s*(\d+)/i', $text, $m);
        $num = $m[1] ?? $m[2] ?? $m[3] ?? '4';
        return "$base Subject: Exploded diagram of truck rear axle planetary gear set (differential). Numbered callouts 1-5 on ring gear, planet gears, sun gear, carrier, axle shaft. Highlight part number $num.";
    }
    if (preg_match('/embrayage/i', $text)) {
        return "$base Subject: Exploded clutch assembly (dry clutch) for truck: flywheel, clutch disc, pressure plate, release bearing. Numbered parts 1-4, highlight part 3 if referenced.";
    }
    if (preg_match('/suspension à lame/i', $text)) {
        return "$base Subject: Leaf spring suspension diagram for heavy truck, side view. Numbered parts: spring leaves, U-bolts, shackles, axle, frame. Highlight element 4.";
    }
    if (preg_match('/schéma de direction|direction ci/i', $text)) {
        return "$base Subject: Truck steering system schematic (steering box, drag link, tie rod, pitman arm). Numbered components, highlight the part referenced in the question.";
    }
    if (preg_match('/freinage pneumatique/i', $text)) {
        return "$base Subject: Air brake system schematic for heavy truck: compressor, reservoirs, brake chambers, valves, lines. Numbered callouts, highlight part 4 if applicable.";
    }
    if (preg_match('/jeu de roulements/i', $text)) {
        return "$base Subject: Four small technical panels (A B C D) showing different methods to check wheel bearing play on a truck hub; one correct method per workshop practice.";
    }
    if (preg_match('/clé dynamométrique/i', $text)) {
        return "$base Subject: Four tools illustrated side by side: torque wrench, open-end wrench, pipe wrench, impact gun. Clear silhouettes for identification.";
    }
    if (preg_match('/moteur Diesel|repère/i', $text)) {
        return "$base Subject: Diesel engine cutaway (truck), numbered arrows on injector, turbo, cylinder head, piston, fuel pump. Clear numbering for identification question.";
    }

    return "$base Subject: Optional supportive diagram for: « $text » ($spec). Simple labeled parts, no answer text visible.";
}

$allNoImage = [];
$missing = [];
$textOnly = [];

foreach ($oids as $oid) {
    if (!isset($juniorIds[(string)$oid])) continue;
    $q = $db->questions->findOne(['_id' => $oid]);
    if (!$q || hasImage($q)) continue;
    $label = (string)($q['label'] ?? '');
    $need = needsImage($label);
    $row = [
        'id' => (string)$oid,
        'label' => $label,
        'speciality' => (string)($q['speciality'] ?? ''),
        'needs_image' => $need,
        'priority' => $need ? 'HAUTE' : 'BASSE',
        'gemini_prompt' => geminiPrompt($q),
    ];
    $allNoImage[] = $row;
    if ($need) $missing[] = $row;
    else $textOnly[] = $row['id'];
}

$out = __DIR__ . '/out_junior_gemini_prompts.json';
file_put_contents($out, json_encode($allNoImage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Sans image total: " . count($allNoImage) . "\n";
echo "Priorité HAUTE (visuel requis): " . count($missing) . "\n";
echo "Priorité BASSE (texte seul): " . count($textOnly) . "\n";
echo "Export: $out\n\n";

foreach ($missing as $r) {
    echo "ID: {$r['id']}\n";
    echo "Q:  {$r['label']}\n";
    echo "P:  {$r['gemini_prompt']}\n\n";
}
