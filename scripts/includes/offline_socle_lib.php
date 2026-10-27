<?php
/**
 * Socle offline MEDACAP — banque de questions unique, deduplique, transportable.
 *
 * Principe : le contenu lourd (questions + images) est fige dans UN fichier
 * envoye une seule fois. Ce qui varie par technicien et par campagne circule
 * ensuite sous forme de « cle » (aller) et de « jeton » (retour), tailles texte.
 *
 * PHP 7.3
 */

require_once __DIR__ . '/offline_qcm_lib.php';

define('OFFLINE_SOCLE_FORMAT', 'medacap-socle-v1');
define('OFFLINE_SOCLE_VERSION', '1.0');

/** Largeur max des schemas embarques (px). Au-dela, redimensionnement. */
define('OFFLINE_SOCLE_IMG_WIDTH', 1200);
/** Qualite WebP. 82 = compromis mesure a -89% sur la banque reelle. */
define('OFFLINE_SOCLE_IMG_QUALITY', 82);

/**
 * Convertit une image disque en WebP redimensionne.
 *
 * @return string|null Octets WebP, ou null si l image est illisible.
 */
function offlineSocleEncodeImage($path)
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $im = @imagecreatefromstring($raw);
    if (!$im) {
        return null;
    }

    $w = imagesx($im);
    $h = imagesy($im);
    if ($w > OFFLINE_SOCLE_IMG_WIDTH) {
        $nh = (int) round($h * OFFLINE_SOCLE_IMG_WIDTH / $w);
        $scaled = imagescale($im, OFFLINE_SOCLE_IMG_WIDTH, $nh);
        imagedestroy($im);
        if (!$scaled) {
            return null;
        }
        $im = $scaled;
    }
    imagepalettetotruecolor($im);

    ob_start();
    imagewebp($im, null, OFFLINE_SOCLE_IMG_QUALITY);
    $out = ob_get_clean();
    imagedestroy($im);

    return ($out === false || $out === '') ? null : $out;
}

/**
 * Construit la banque complete : questions actives + schemas dedupliques.
 *
 * Les questions sont stockees en tableaux positionnels plutot qu en objets :
 * sur 1236 questions, les noms de cles repetes pesent plus que les donnees.
 *
 * @param OfflineQcmMongo $db
 * @param array $options 'imageDir' (defaut public/files), 'onProgress' callable
 * @return array
 */
function offlineSocleBuildBank(OfflineQcmMongo $db, array $options = array())
{
    $imageDir = isset($options['imageDir'])
        ? $options['imageDir']
        : __DIR__ . '/../../public/files';
    $onProgress = isset($options['onProgress']) ? $options['onProgress'] : null;

    $questions = array();
    $qids = array();
    $specialties = array();
    $specIndex = array();
    $images = array();
    $imageIndex = array();   // sha1(webp) => position
    $imageByName = array();   // nom disque   => position (evite de reconvertir)

    $missingImages = array();
    $count = 0;

    $cursor = $db->getCollection('questions')->find(array('active' => true));
    foreach ($cursor as $doc) {
        $count++;
        if ($onProgress && $count % 100 === 0) {
            call_user_func($onProgress, $count);
        }

        $spec = offlineQcmStr(isset($doc['speciality']) ? $doc['speciality'] : '');
        if (!isset($specIndex[$spec])) {
            $specIndex[$spec] = count($specialties);
            $specialties[] = $spec;
        }

        $proposals = array();
        for ($p = 1; $p <= 4; $p++) {
            $key = 'proposal' . $p;
            if (isset($doc[$key]) && $doc[$key] !== '') {
                $proposals[] = offlineQcmStr($doc[$key]);
            }
        }

        $imgPos = -1;
        $imgName = offlineQcmStr(isset($doc['image']) ? $doc['image'] : '');
        if ($imgName !== '') {
            if (isset($imageByName[$imgName])) {
                $imgPos = $imageByName[$imgName];
            } else {
                $webp = offlineSocleEncodeImage($imageDir . '/' . $imgName);
                if ($webp === null) {
                    $missingImages[$imgName] = true;
                } else {
                    // Adressage par contenu : deux noms differents pour la meme
                    // image (le cas .png/.jpg de meme racine) ne stockent qu une copie.
                    $hash = sha1($webp);
                    if (!isset($imageIndex[$hash])) {
                        $imageIndex[$hash] = count($images);
                        $images[] = base64_encode($webp);
                    }
                    $imgPos = $imageIndex[$hash];
                    $imageByName[$imgName] = $imgPos;
                }
            }
        }

        $qids[] = (string) $doc['_id'];
        $questions[] = array(
            offlineQcmStr(isset($doc['ref']) ? $doc['ref'] : ''),
            $specIndex[$spec],
            offlineQcmStr(isset($doc['label']) ? $doc['label'] : ''),
            $proposals,
            $imgPos,
        );
    }

    return array(
        'format' => OFFLINE_SOCLE_FORMAT,
        'version' => OFFLINE_SOCLE_VERSION,
        'builtAt' => gmdate('c'),
        'specialties' => $specialties,
        'questions' => $questions,
        'qids' => $qids,
        'images' => $images,
        'stats' => array(
            'questionCount' => count($questions),
            'imageCount' => count($images),
            'missingImages' => array_keys($missingImages),
        ),
    );
}

/**
 * Table de correspondance id Mongo => position dans la banque.
 * Necessaire pour fabriquer les cles et relire les jetons.
 */
function offlineSocleQuestionIndex(array $bank)
{
    $map = array();
    foreach ($bank['qids'] as $pos => $id) {
        $map[$id] = $pos;
    }
    return $map;
}

/* ------------------------------------------------------------------ *
 *  Codec « cle » (aller) et « jeton » (retour)
 *
 *  Le socle ne bouge pas. Ce qui circule a chaque campagne tient dans
 *  un message texte : la cle ouvre des modules, le jeton rapporte les
 *  reponses. Aucun des deux ne transporte de contenu de question.
 * ------------------------------------------------------------------ */

define('OFFLINE_SOCLE_KEY_PREFIX', 'MK1.');
define('OFFLINE_SOCLE_TOKEN_PREFIX', 'MJ1.');

function offlineSocleB64UrlEncode($bin)
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function offlineSocleB64UrlDecode($txt)
{
    $txt = strtr(trim($txt), '-_', '+/');
    $pad = strlen($txt) % 4;
    if ($pad) {
        $txt .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($txt, true);
    return $out === false ? null : $out;
}

/** Niveaux encodes sur 1 octet. L ordre est fige : ne jamais reordonner. */
function offlineSocleLevels()
{
    return array('Junior', 'Senior', 'Expert');
}

function offlineSocleLevelCode($level)
{
    $pos = array_search($level, offlineSocleLevels(), true);
    return $pos === false ? 0 : $pos;
}

function offlineSocleLevelName($code)
{
    $levels = offlineSocleLevels();
    return isset($levels[$code]) ? $levels[$code] : 'Junior';
}

/**
 * Fabrique la cle d un technicien : identite + modules a composer.
 *
 * @param array $modules Chaque entree : type ('Factuel'|'Declaratif'),
 *                       level, testId, questions (positions dans la banque),
 *                       seconds (duree allouee).
 * @return string Cle textuelle, prefixee MK1.
 */
function offlineSocleBuildKey(array $user, array $modules)
{
    $payload = array(
        'u' => (string) $user['userId'],
        'n' => (string) $user['username'],
        'ag' => isset($user['agency']) ? (string) $user['agency'] : '',
        'sb' => isset($user['subsidiary']) ? (string) $user['subsidiary'] : '',
        'm' => array(),
    );

    foreach ($modules as $mod) {
        $payload['m'][] = array(
            $mod['type'] === 'Declaratif' ? 'D' : 'F',
            offlineSocleLevelCode($mod['level']),
            (string) $mod['testId'],
            array_values(array_map('intval', $mod['questions'])),
            (int) $mod['seconds'],
        );
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $packed = gzdeflate($json, 9);

    return OFFLINE_SOCLE_KEY_PREFIX . offlineSocleB64UrlEncode($packed);
}

/**
 * @return array|null Payload de la cle, ou null si illisible.
 */
function offlineSocleParseKey($key)
{
    $key = trim($key);
    if (strpos($key, OFFLINE_SOCLE_KEY_PREFIX) !== 0) {
        return null;
    }
    $bin = offlineSocleB64UrlDecode(substr($key, strlen(OFFLINE_SOCLE_KEY_PREFIX)));
    if ($bin === null) {
        return null;
    }
    $json = @gzinflate($bin);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Empaquete les reponses sur 3 bits chacune (0 = non repondu, 1-4 = A-D).
 */
function offlineSoclePackAnswers(array $answers)
{
    $bits = '';
    foreach ($answers as $a) {
        $v = (int) $a;
        if ($v < 0 || $v > 4) {
            $v = 0;
        }
        $bits .= str_pad(decbin($v), 3, '0', STR_PAD_LEFT);
    }
    $bits = str_pad($bits, (int) (ceil(strlen($bits) / 8) * 8), '0', STR_PAD_RIGHT);

    $out = '';
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }
    return $out;
}

function offlineSocleUnpackAnswers($bin, $count)
{
    $bits = '';
    $len = strlen($bin);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = array();
    for ($i = 0; $i < $count; $i++) {
        $chunk = substr($bits, $i * 3, 3);
        $out[] = strlen($chunk) === 3 ? bindec($chunk) : 0;
    }
    return $out;
}

/**
 * Fabrique le jeton de retour d un module termine.
 *
 * Structure : version | userId(12o) | testId(12o) | type | niveau |
 *             nb reponses(2o) | reponses(3 bits) | duree(3o) | crc32(4o)
 *
 * Pour 100 questions : ~74 octets, soit ~99 caracteres en base64url.
 *
 * @param int[] $answers Valeurs 0-4, dans l ordre des questions du module.
 */
function offlineSocleBuildToken($userId, $testId, $type, $level, array $answers, $elapsedSeconds)
{
    $body = chr(1)
        . hex2bin((string) $userId)
        . hex2bin((string) $testId)
        . chr($type === 'Declaratif' ? 1 : 0)
        . chr(offlineSocleLevelCode($level))
        . pack('n', count($answers))
        . offlineSoclePackAnswers($answers)
        . substr(pack('N', max(0, (int) $elapsedSeconds)), 1);

    $crc = pack('N', crc32($body));

    return OFFLINE_SOCLE_TOKEN_PREFIX . offlineSocleB64UrlEncode($body . $crc);
}

/**
 * @return array|null Contenu du jeton, ou null si corrompu / tronque.
 */
function offlineSocleParseToken($token)
{
    $token = trim($token);
    if (strpos($token, OFFLINE_SOCLE_TOKEN_PREFIX) !== 0) {
        return null;
    }
    $bin = offlineSocleB64UrlDecode(substr($token, strlen(OFFLINE_SOCLE_TOKEN_PREFIX)));
    if ($bin === null || strlen($bin) < 36) {
        return null;
    }

    $body = substr($bin, 0, -4);
    $crc = substr($bin, -4);
    if (crc32($body) !== unpack('N', $crc)[1]) {
        return null;   // troncature ou copier-coller abime
    }

    if (ord($body[0]) !== 1) {
        return null;
    }
    $count = unpack('n', substr($body, 27, 2))[1];
    $ansBytes = (int) ceil($count * 3 / 8);

    return array(
        'userId' => bin2hex(substr($body, 1, 12)),
        'testId' => bin2hex(substr($body, 13, 12)),
        'type' => ord($body[25]) === 1 ? 'Declaratif' : 'Factuel',
        'level' => offlineSocleLevelName(ord($body[26])),
        'count' => $count,
        'answers' => offlineSocleUnpackAnswers(substr($body, 29, $ansBytes), $count),
        'elapsedSeconds' => unpack('N', "\0" . substr($body, 29 + $ansBytes, 3))[1],
    );
}

/**
 * Accuse de reception remis au technicien apres import reussi.
 * Court, saisissable a la main, derive du jeton et du score obtenu.
 */
function offlineSocleReceipt($userId, $testId, $score, $total)
{
    $raw = hash_hmac('sha256', $userId . '|' . $testId . '|' . $score . '/' . $total, OFFLINE_QCM_SECRET, true);
    return strtoupper(substr(offlineSocleB64UrlEncode($raw), 0, 8));
}

/* ------------------------------------------------------------------ *
 *  Import d un jeton
 * ------------------------------------------------------------------ */

/**
 * Corrige et enregistre un module rapporte par jeton.
 *
 * Le jeton ne porte que des reponses. Les questions posees sont relues depuis
 * l exam fige lors de la generation de la cle — c est ce qui permet au jeton
 * de tenir en ~100 caracteres.
 *
 * @throws RuntimeException si le jeton ne correspond a aucun tirage exploitable
 * @return array score, total, receipt, username, level
 */
function offlineSocleImportToken(OfflineQcmMongo $db, array $tok, $importedBy = 'cli')
{
    if ($tok['type'] !== 'Factuel') {
        throw new RuntimeException(
            'Type ' . $tok['type'] . ' non supporte : le moteur Declaratif '
            . 'n est pas encore extrait de views/measure/userQuizDeclaratif.php'
        );
    }

    $userId = $tok['userId'];
    $testId = $tok['testId'];
    $level = $tok['level'];
    $userOid = new ObjectId($userId);
    $testOid = new ObjectId($testId);

    $user = $db->getCollection('users')->findOne(array('_id' => $userOid));
    if (!$user) {
        throw new RuntimeException('Technicien introuvable: ' . $userId);
    }

    $existing = $db->getCollection('results')->findOne(array(
        'user' => $userOid,
        'test' => $testOid,
        'type' => 'Factuel',
        'level' => $level,
        'active' => true,
    ));
    if ($existing) {
        throw new RuntimeException('Module deja corrige pour ce test/niveau — jeton ignore');
    }

    $exam = $db->getCollection('exams')->findOne(array(
        'user' => $userOid,
        'test' => $testOid,
        'active' => true,
    ));
    if (!$exam || empty($exam['questions'])) {
        throw new RuntimeException(
            'Aucun tirage fige pour ce module. La cle a-t-elle ete generee '
            . 'avec --dry-run ? Le jeton n est pas exploitable.'
        );
    }

    $examQuestions = offlineQcmToArray($exam['questions']);
    if (count($examQuestions) !== $tok['count']) {
        throw new RuntimeException(sprintf(
            'Desaccord sur le nombre de questions : jeton=%d, tirage en base=%d',
            $tok['count'],
            count($examQuestions)
        ));
    }

    $answered = 0;
    $userAnswers = array();
    foreach ($tok['answers'] as $a) {
        if ($a >= 1 && $a <= 4) {
            $userAnswers[] = (string) $a;
            $answered++;
        } else {
            $userAnswers[] = 'null';
        }
    }
    if ($answered <= 1) {
        throw new RuntimeException('Moins de 2 reponses — jeton refuse');
    }

    $fetcher = new QuizDataFetcher($db);
    $userData = $fetcher->fetchUserData($userId, $testId, $level);
    if (!$userData) {
        throw new RuntimeException('Donnees test introuvables pour ' . $userId);
    }

    $questionsColl = $db->getCollection('questions');
    $fullToShort = array_flip(QuizConstants::$SPECIALTIES);
    $allQuestions = array();
    foreach ($examQuestions as $qId) {
        $q = $questionsColl->findOne(array('_id' => new ObjectId((string) $qId), 'active' => true));
        if (!$q) {
            throw new RuntimeException('Question du tirage introuvable en base: ' . $qId);
        }
        $full = offlineQcmStr(isset($q['speciality']) ? $q['speciality'] : '');
        $short = isset($fullToShort[$full]) ? $fullToShort[$full] : '';
        $allQuestions[] = array(
            'question' => $q,
            'quizId' => isset($exam['quiz' . $short]) ? $exam['quiz' . $short] : null,
            'specialty' => $full,
            'short' => $short,
        );
    }

    $sec = max(0, (int) $tok['elapsedSeconds']);
    $timer = sprintf('%02d:%02d:%02d', (int) floor($sec / 3600), (int) floor(($sec % 3600) / 60), $sec % 60);

    $processor = new QuizResultProcessor($db, $userId, $testId, $level, $userData, $allQuestions, $timer);
    $score = $processor->process($userAnswers);

    $db->getCollection('exams')->updateOne(
        array('_id' => $exam['_id']),
        array('$set' => array(
            'answers' => $userAnswers,
            'total' => $answered,
            'active' => false,
            'hour' => (int) floor($sec / 3600),
            'minute' => (int) floor(($sec % 3600) / 60),
            'second' => $sec % 60,
            'updated' => date('d-m-Y H:i:s'),
            'offlineImport' => true,
            'offlineImportedBy' => $importedBy,
        ))
    );

    $allocColl = $db->getCollection('allocations');
    $alloc = $allocColl->findOne(array('user' => $userOid, 'test' => $testOid));
    if ($alloc) {
        $allocColl->updateOne(array('_id' => $alloc['_id']), array('$set' => array('active' => true)));
        require_once __DIR__ . '/../../views/measure/includes/allocation_progression.php';
        allocationProgression_tryUnlockNextLevel($allocColl, $userOid, $level);
        allocationProgression_syncUnlockedLevels($allocColl, $userOid);
    }

    $total = count($userAnswers);

    return array(
        'score' => $score,
        'total' => $total,
        'answered' => $answered,
        'username' => offlineQcmStr(isset($user['username']) ? $user['username'] : ''),
        'level' => $level,
        'receipt' => offlineSocleReceipt($userId, $testId, $score, $total),
    );
}
