<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Export / import kits QCM offline MEDACAP.
 * PHP 8.4
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/mongo_compat.php';
require_once __DIR__ . '/../../views/measure/includes/quiz_factuel_engine.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

define('OFFLINE_QCM_FORMAT', 'medacap-offline-qcm-v1');
define('OFFLINE_QCM_VERSION', '1.0');
define('OFFLINE_QCM_SECRET', 'MEDACAP-OFFLINE-2026-CFAO');

class OfflineQcmMongo
{
    private $collections;

    public function __construct($uri = null)
    {
        if ($uri === null) {
            $uri = medacapMongoUri('mongodb://10.68.0.7:27017');
        }
        $db = (new Client($uri))->academy;
        $this->collections = array(
            'users' => $db->users,
            'tests' => $db->tests,
            'questions' => $db->questions,
            'results' => $db->results,
            'exams' => $db->exams,
            'allocations' => $db->allocations,
            'quizzes' => $db->quizzes,
        );
    }

    public function getCollection($name)
    {
        return isset($this->collections[$name]) ? $this->collections[$name] : null;
    }
}

class OfflineQcmDeclaratifConstants
{
    public static $SPECIALTIES = array(
        'Assistance à la Conduite' => array('quizAssistance', 'answerAssistance', 'assistanceDecla', 'assistanceConduite'),
        'Arbre de Transmission' => array('quizArbre', 'answerArbre', 'arbreDecla', 'arbre'),
        'Boite de Transfert' => array('quizTransfert', 'answerTransfert', 'transfertDecla', 'transfert'),
        'Boite de Vitesse' => array('quizBoite', 'answerBoite', 'boiteDecla', 'boite_vitesse'),
        'Boite de Vitesse Automatique' => array('quizBoiteAuto', 'answerBoiteAuto', 'boiteAutoDecla', 'boite_vitesse_auto'),
        'Boite de Vitesse Mécanique' => array('quizBoiteMan', 'answerBoiteMan', 'boiteManDecla', 'boite_vitesse_meca'),
        'Boite de Vitesse à Variation Continue' => array('quizBoiteVc', 'answerBoiteVc', 'boiteVcDecla', 'boite_vitesse_VC'),
        'Climatisation' => array('quizClimatisation', 'answerClimatisation', 'climatisationDecla', 'clim'),
        'Demi Arbre de Roue' => array('quizDemi', 'answerDemi', 'demiDecla', 'demi'),
        'Direction' => array('quizDirection', 'answerDirection', 'directionDecla', 'direction'),
        'Electricité et Electronique' => array('quizElectricite', 'answerElectricite', 'electriciteDecla', 'electricite'),
        'Freinage' => array('quizFrei', 'answerFrei', 'freiDecla', 'freinage'),
        'Freinage Electromagnétique' => array('quizFreinageElec', 'answerFreinageElec', 'freinageElecDecla', 'freinage_elec'),
        'Freinage Hydraulique' => array('quizFreinage', 'answerFreinage', 'freinageHydDecla', 'freinage_hyd'),
        'Freinage Pneumatique' => array('quizFrein', 'answerFrein', 'freinDecla', 'frein'),
        'Hydraulique' => array('quizHydraulique', 'answerHydraulique', 'hydrauliqueDecla', 'hydraulique'),
        'Moteur Diesel' => array('quizMoteurDiesel', 'answerMoteurDiesel', 'moteurDieselDecla', 'moteur_diesel'),
        'Moteur Electrique' => array('quizMoteurElec', 'answerMoteurElec', 'moteurElecDecla', 'moteur_elec'),
        'Moteur Essence' => array('quizMoteurEssence', 'answerMoteurEssence', 'moteurEssenceDecla', 'moteur_essence'),
        'Moteur Thermique' => array('quizMoteur', 'answerMoteur', 'moteurDecla', 'moteur'),
        'Réseaux de Communication' => array('quizMultiplexage', 'answerMultiplexage', 'multiplexageDecla', 'multiplexage'),
        'Pont' => array('quizPont', 'answerPont', 'pontDecla', 'pont'),
        'Pneumatique' => array('quizPneumatique', 'answerPneumatique', 'pneumatiqueDecla', 'pneumatique'),
        'Reducteur' => array('quizReducteur', 'answerReducteur', 'reducteurDecla', 'reducteur'),
        'Suspension' => array('quizSuspension', 'answerSuspension', 'suspensionDecla', 'suspension'),
        'Suspension à Lame' => array('quizSuspensionLame', 'answerSuspensionLame', 'suspensionLameDecla', 'suspension_lame'),
        'Suspension Ressort' => array('quizSuspensionRessort', 'answerSuspensionRessort', 'suspensionRessortDecla', 'suspension_ressort'),
        'Suspension Pneumatique' => array('quizSuspensionPneumatique', 'answerSuspensionPneumatique', 'suspensionPneumatiqueDecla', 'suspension_pneumatique'),
        'Transversale' => array('quizTransversale', 'answerTransversale', 'transversaleDecla', 'transversale')
    );
}

function offlineQcmFetchDeclaratifQuestions(OfflineQcmMongo $db, $user, $testDecla, $level)
{
    $quizArr = offlineQcmToArray(isset($testDecla['quizzes']) ? $testDecla['quizzes'] : array());
    $quizIds = array();
    foreach ($quizArr as $item) {
        $idStr = offlineQcmIdStr($item);
        if ($idStr !== '') {
            $quizIds[] = new ObjectId($idStr);
        }
    }
    if (empty($quizIds)) {
        return array();
    }

    $allDeclaQuestions = array();
    $questionsCol = $db->getCollection('questions');
    $quizzesCol = $db->getCollection('quizzes');

    foreach (OfflineQcmDeclaratifConstants::$SPECIALTIES as $specialty => $data) {
        $quiz = $quizzesCol->findOne(array(
            '_id' => array('$in' => $quizIds),
            'speciality' => $specialty,
            'type' => 'Declaratif',
            'level' => $level,
            'active' => true,
        ));
        if (!$quiz) {
            continue;
        }

        $qArr = offlineQcmToArray(isset($quiz['questions']) ? $quiz['questions'] : array());
        foreach ($qArr as $qid) {
            $qIdStr = offlineQcmIdStr($qid);
            if ($qIdStr === '') {
                continue;
            }
            $qDoc = $questionsCol->findOne(array(
                '_id' => new ObjectId($qIdStr),
                'active' => true,
            ));
            if ($qDoc) {
                $allDeclaQuestions[] = array(
                    'index' => count($allDeclaQuestions) + 1,
                    'id' => (string) $qDoc['_id'],
                    'quizId' => (string) $quiz['_id'],
                    'specialty' => $specialty,
                    'short' => $data[3],
                    'label' => trim(offlineQcmStr(isset($qDoc['label']) ? $qDoc['label'] : '')),
                    'ref' => trim(offlineQcmStr(isset($qDoc['ref']) ? $qDoc['ref'] : '')),
                    'proposals' => array(
                        'Je maîtrise',
                        'Je ne maîtrise pas',
                        'J\'ai jamais fait',
                    ),
                );
            }
        }
    }

    return $allDeclaQuestions;
}

function offlineQcmStr($v)
{
    return is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
}

function offlineQcmToArray($v)
{
    if (is_array($v)) {
        return $v;
    }
    if ($v instanceof MongoDB\Model\BSONDocument || $v instanceof MongoDB\Model\BSONArray) {
        return json_decode(json_encode($v), true);
    }
    if (is_object($v)) {
        return json_decode(json_encode($v), true);
    }
    return array();
}

function offlineQcmSlug($s)
{
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($trans !== false) {
        $s = $trans;
    }
    $s = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $s);
    return trim($s, '_');
}

function offlineQcmDefaultTimer($level)
{
    if ($level === 'Expert') {
        return array('hours' => 1, 'minutes' => 0, 'seconds' => 0);
    }
    return array('hours' => 1, 'minutes' => 30, 'seconds' => 0);
}

function offlineQcmTimerString(array $timer)
{
    return sprintf(
        '%02d:%02d:%02d',
        (int) ($timer['hours'] ?? 0),
        (int) ($timer['minutes'] ?? 0),
        (int) ($timer['seconds'] ?? 0)
    );
}

function offlineQcmSign(array $meta, array $questionIds)
{
    $payload = ($meta['exportId'] ?? '') . '|' . ($meta['userId'] ?? '') . '|' . implode(',', $questionIds);
    return hash_hmac('sha256', $payload, OFFLINE_QCM_SECRET);
}

function offlineQcmImageSrc($question)
{
    $img = isset($question['image']) ? offlineQcmStr($question['image']) : '';
    if ($img === '') {
        return null;
    }
    if (strpos($img, 'data:') === 0 || strpos($img, 'http') === 0) {
        return $img;
    }
    return null;
}

function offlineQcmIdStr($v)
{
    if ($v instanceof MongoDB\BSON\ObjectId) {
        return (string) $v;
    }
    if (is_array($v) && isset($v['$oid'])) {
        return (string) $v['$oid'];
    }
    if (is_object($v) && isset($v->{'$oid'})) {
        return (string) $v->{'$oid'};
    }
    return is_scalar($v) ? (string) $v : '';
}

function offlineQcmBuildPackage(array $allQuestions, $user, $test, $level, array $options = array())
{
    $userId = offlineQcmIdStr(isset($user['_id']) ? $user['_id'] : '');
    $testId = $test ? offlineQcmIdStr(isset($test['_id']) ? $test['_id'] : '') : null;
    $user = offlineQcmToArray($user);
    $test = $test ? offlineQcmToArray($test) : array();
    $exportId = bin2hex(random_bytes(16));
    $questionsOut = array();
    $answersOut = array();
    $questionIds = array();
    $idx = 1;

    foreach ($allQuestions as $item) {
        $q = $item['question'];
        $proposals = array();
        for ($p = 1; $p <= 4; $p++) {
            $key = 'proposal' . $p;
            if (!empty($q[$key])) {
                $proposals[] = offlineQcmStr($q[$key]);
            }
        }
        $questionsOut[] = array(
            'index' => $idx,
            'id' => (string) $q['_id'],
            'ref' => offlineQcmStr(isset($q['ref']) ? $q['ref'] : ''),
            'specialty' => offlineQcmStr(isset($item['specialty']) ? $item['specialty'] : (isset($q['speciality']) ? $q['speciality'] : '')),
            'label' => offlineQcmStr(isset($q['label']) ? $q['label'] : ''),
            'proposals' => $proposals,
            'image' => offlineQcmImageSrc($q),
        );
        $answersOut[(string) $idx] = null;
        $questionIds[] = (string) $q['_id'];
        $idx++;
    }

    $declaratifQuestions = isset($options['declaratifQuestions']) && is_array($options['declaratifQuestions'])
        ? $options['declaratifQuestions']
        : array();
    $declaratifAnswersOut = array();
    $testDecla = isset($options['testDecla']) ? $options['testDecla'] : null;
    $declaratifTestId = $testDecla ? offlineQcmIdStr(isset($testDecla['_id']) ? $testDecla['_id'] : '') : null;

    foreach ($declaratifQuestions as $dq) {
        $dIdx = (string) (isset($dq['index']) ? $dq['index'] : (count($declaratifAnswersOut) + 1));
        $declaratifAnswersOut[$dIdx] = null;
        $questionIds[] = (string) $dq['id'];
    }

    $hasFactuel = !empty($questionsOut);
    $hasDeclaratif = !empty($declaratifQuestions);

    $typeLabel = 'Factuel';
    if ($hasFactuel && $hasDeclaratif) {
        $typeLabel = 'Factuel + Déclaratif';
    } elseif ($hasDeclaratif) {
        $typeLabel = 'Déclaratif';
    }

    $timer = offlineQcmDefaultTimer($level);
    $meta = array(
        'format' => OFFLINE_QCM_FORMAT,
        'version' => OFFLINE_QCM_VERSION,
        'exportId' => $exportId,
        'userId' => $userId,
        'username' => offlineQcmStr(isset($user['username']) ? $user['username'] : ''),
        'firstName' => offlineQcmStr(isset($user['firstName']) ? $user['firstName'] : ''),
        'lastName' => offlineQcmStr(isset($user['lastName']) ? $user['lastName'] : ''),
        'agency' => offlineQcmStr(isset($user['agency']) ? $user['agency'] : ''),
        'subsidiary' => offlineQcmStr(isset($user['subsidiary']) ? $user['subsidiary'] : ''),
        'country' => offlineQcmStr(isset($user['country']) ? $user['country'] : ''),
        'testId' => $testId,
        'declaratifTestId' => $declaratifTestId,
        'level' => $level,
        'type' => $typeLabel,
        'hasFactuel' => $hasFactuel,
        'hasDeclaratif' => $hasDeclaratif,
        'questionCount' => count($questionsOut),
        'declaratifQuestionCount' => count($declaratifQuestions),
        'exportedAt' => gmdate('c'),
        'exportedBy' => isset($options['exportedBy']) ? $options['exportedBy'] : 'cli',
    );
    $meta['signature'] = offlineQcmSign($meta, $questionIds);

    return array(
        'format' => OFFLINE_QCM_FORMAT,
        'version' => OFFLINE_QCM_VERSION,
        'meta' => $meta,
        'status' => 'pending',
        'session' => array(
            'step' => $hasFactuel ? 'factuel' : 'declaratif',
            'startedAt' => null,
            'lastSavedAt' => null,
            'completedAt' => null,
            'timer' => $timer,
            'timerFormatted' => offlineQcmTimerString($timer),
            'remainingSeconds' => null,
            'declaratifStartedAt' => null,
            'declaratifCompletedAt' => null,
        ),
        'questions' => $questionsOut,
        'answers' => $answersOut,
        'declaratifQuestions' => $declaratifQuestions,
        'declaratifAnswers' => $declaratifAnswersOut,
        'import' => array(
            'importedAt' => null,
            'importedBy' => null,
            'score' => null,
            'declaratifScore' => null,
        ),
    );
}

function offlineQcmValidatePackage(array $pkg)
{
    if (($pkg['format'] ?? '') !== OFFLINE_QCM_FORMAT) {
        throw new InvalidArgumentException('Format invalide (attendu: ' . OFFLINE_QCM_FORMAT . ')');
    }
    if (empty($pkg['meta']['exportId']) || empty($pkg['meta']['userId'])) {
        throw new InvalidArgumentException('meta incomplet (exportId, userId requis)');
    }

    $hasFactuel = !empty($pkg['questions']);
    $hasDeclaratif = !empty($pkg['declaratifQuestions']);
    if (!$hasFactuel && !$hasDeclaratif) {
        throw new InvalidArgumentException('Aucune question dans le kit');
    }

    $ids = array();
    if ($hasFactuel && is_array($pkg['questions'])) {
        foreach ($pkg['questions'] as $q) {
            $ids[] = (string) ($q['id'] ?? '');
        }
    }
    if ($hasDeclaratif && is_array($pkg['declaratifQuestions'])) {
        foreach ($pkg['declaratifQuestions'] as $q) {
            $ids[] = (string) ($q['id'] ?? '');
        }
    }

    $expected = offlineQcmSign($pkg['meta'], $ids);
    if (!hash_equals($expected, (string) ($pkg['meta']['signature'] ?? ''))) {
        throw new InvalidArgumentException('Signature HMAC invalide — fichier modifie ou corrompu');
    }
    if (($pkg['status'] ?? '') === 'imported') {
        throw new InvalidArgumentException('Ce kit a deja ete importe en base');
    }
    if (($pkg['status'] ?? '') !== 'completed') {
        throw new InvalidArgumentException('Le QCM n est pas finalise (status=' . ($pkg['status'] ?? '?') . ')');
    }
}

function offlineQcmAnswersToArray(array $pkg)
{
    $n = isset($pkg['questions']) ? count($pkg['questions']) : 0;
    $out = array();
    $answers = isset($pkg['answers']) && is_array($pkg['answers']) ? $pkg['answers'] : array();
    for ($i = 1; $i <= $n; $i++) {
        $key = (string) $i;
        $val = isset($answers[$key]) ? $answers[$key] : null;
        if ($val === null || $val === '') {
            $out[] = 'null';
        } else {
            $out[] = (string) $val;
        }
    }
    return $out;
}

function offlineQcmNormalizeDeclaratifChoice($val)
{
    if ($val === null || $val === '' || $val === 'null') {
        return array('raw' => 'null', 'answer' => 'Non', 'positive' => false);
    }
    $str = trim((string) $val);
    if ($str === '0') {
        return array('raw' => 'Je maîtrise', 'answer' => 'Oui', 'positive' => true);
    }
    if ($str === '1') {
        return array('raw' => 'Je ne maîtrise pas', 'answer' => 'Non', 'positive' => false);
    }
    if ($str === '2') {
        return array('raw' => "J'ai jamais fait", 'answer' => 'Non', 'positive' => false);
    }

    if (mb_stripos($str, 'ne maîtrise pas', 0, 'UTF-8') !== false || mb_stripos($str, 'ne maitrise pas', 0, 'UTF-8') !== false) {
        return array('raw' => 'Je ne maîtrise pas', 'answer' => 'Non', 'positive' => false);
    }
    if (mb_stripos($str, 'maîtrise', 0, 'UTF-8') !== false || mb_stripos($str, 'maitrise', 0, 'UTF-8') !== false) {
        return array('raw' => 'Je maîtrise', 'answer' => 'Oui', 'positive' => true);
    }
    if (mb_stripos($str, 'jamais', 0, 'UTF-8') !== false) {
        return array('raw' => "J'ai jamais fait", 'answer' => 'Non', 'positive' => false);
    }
    return array('raw' => $str, 'answer' => 'Non', 'positive' => false);
}

function offlineQcmRebuildAllQuestions(OfflineQcmMongo $db, array $pkg)
{
    $questionsColl = $db->getCollection('questions');
    $allQuestions = array();
    foreach ($pkg['questions'] as $row) {
        $q = $questionsColl->findOne(array(
            '_id' => new ObjectId($row['id']),
            'active' => true,
        ));
        if (!$q) {
            throw new RuntimeException('Question introuvable en base: ' . $row['id']);
        }
        $allQuestions[] = array(
            'question' => $q,
            'quizId' => null,
            'specialty' => offlineQcmStr(isset($row['specialty']) ? $row['specialty'] : (isset($q['speciality']) ? $q['speciality'] : '')),
            'short' => '',
        );
    }
    return $allQuestions;
}

function offlineQcmImportPackage(OfflineQcmMongo $db, array $pkg, $importedBy = 'cli')
{
    offlineQcmValidatePackage($pkg);

    $userId = $pkg['meta']['userId'];
    $level = $pkg['meta']['level'];
    $allocColl = $db->getCollection('allocations');
    $hasFactuel = !empty($pkg['questions']);
    $hasDeclaratif = !empty($pkg['declaratifQuestions']);

    $factuelScore = null;
    $factuelTotal = null;
    $declaScore = null;
    $declaTotal = null;

    /* ── 1. Traitement Factuel (si présent) ── */
    if ($hasFactuel) {
        $testId = $pkg['meta']['testId'];
        if (!$testId) {
            throw new RuntimeException('testId Factuel manquant dans le kit');
        }

        $fetcher = new QuizDataFetcher($db);
        $userData = $fetcher->fetchUserData($userId, $testId, $level);
        if (!$userData) {
            throw new RuntimeException('Utilisateur ou test Factuel introuvable pour import');
        }

        $existing = $db->getCollection('results')->findOne(array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($testId),
            'type' => 'Factuel',
            'level' => $level,
            'active' => true,
        ));
        if ($existing) {
            throw new RuntimeException('Un result Factuel existe deja pour ce test/niveau — import refuse');
        }

        $userAnswers = offlineQcmAnswersToArray($pkg);
        $answered = count(array_filter($userAnswers, function ($a) {
            return $a !== 'null';
        }));
        if ($answered <= 1) {
            throw new RuntimeException('Moins de 2 reponses au Factuel — import refuse');
        }

        $allQuestions = offlineQcmRebuildAllQuestions($db, $pkg);
        $timer = isset($pkg['session']['timerFormatted']) ? (string) $pkg['session']['timerFormatted'] : '00:00:00';
        if ($timer === '00:00:00' && isset($pkg['session']['remainingSeconds'])) {
            $rs = max(0, (int) $pkg['session']['remainingSeconds']);
            $timer = sprintf('%02d:%02d:%02d', (int) floor($rs / 3600), (int) floor(($rs % 3600) / 60), $rs % 60);
        }

        $processor = new QuizResultProcessor($db, $userId, $testId, $level, $userData, $allQuestions, $timer);
        $factuelScore = $processor->process($userAnswers);
        $factuelTotal = count($userAnswers);

        $examsColl = $db->getCollection('exams');
        $questionsTags = array_map(function ($row) {
            return new ObjectId($row['id']);
        }, $pkg['questions']);
        $fmtAnswers = $userAnswers;

        $quizIds = array();
        foreach (QuizConstants::$SPECIALTIES as $key => $spec) {
            $quizIds['quiz' . $key] = null;
        }

        $parts = explode(':', $timer);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        $examData = array(
            'questions' => $questionsTags,
            'answers' => $fmtAnswers,
            'user' => new ObjectId($userId),
            'test' => new ObjectId($testId),
            'total' => $answered,
            'active' => false,
            'type' => 'Factuel',
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
            'created' => date('d-m-Y H:i:s'),
            'updated' => date('d-m-Y H:i:s'),
            'offlineImport' => true,
            'offlineExportId' => $pkg['meta']['exportId'],
        ) + $quizIds;

        $examsColl->updateMany(
            array('user' => new ObjectId($userId), 'test' => new ObjectId($testId), 'active' => true),
            array('$set' => array('active' => false))
        );
        $examsColl->insertOne($examData);

        $alloc = $allocColl->findOne(array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($testId),
        ));
        if ($alloc) {
            $allocColl->updateOne(
                array('_id' => $alloc['_id']),
                array('$set' => array('active' => true))
            );
        }
    }

    /* ── 2. Traitement Déclaratif (si présent) ── */
    if ($hasDeclaratif) {
        $declaratifTestId = isset($pkg['meta']['declaratifTestId']) ? $pkg['meta']['declaratifTestId'] : null;
        if (!$declaratifTestId) {
            $testDeclaDoc = $db->getCollection('tests')->findOne(array(
                'user' => new ObjectId($userId),
                'type' => 'Declaratif',
                'level' => $level,
                'active' => true,
            ));
            if ($testDeclaDoc) {
                $declaratifTestId = (string) $testDeclaDoc['_id'];
            }
        }
        if (!$declaratifTestId) {
            throw new RuntimeException('Test Déclaratif introuvable pour ce technicien/niveau');
        }

        $existingDecla = $db->getCollection('results')->findOne(array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($declaratifTestId),
            'type' => 'Declaratif',
            'typeR' => 'Techniciens',
            'level' => $level,
            'active' => true,
        ));
        if ($existingDecla) {
            throw new RuntimeException('Un result Déclaratif existe déjà pour ce test/niveau — import refusé');
        }

        $rawDeclaAnswers = isset($pkg['declaratifAnswers']) && is_array($pkg['declaratifAnswers'])
            ? $pkg['declaratifAnswers']
            : array();
        $declaQuestionsTag = array();
        $declaProposals = array();
        $declaUserAnswers = array();
        $declaSpecialtyScores = array();
        foreach (array_column(OfflineQcmDeclaratifConstants::$SPECIALTIES, 3) as $sh) {
            $declaSpecialtyScores[$sh] = array('correct' => 0, 'total' => 0);
        }
        $declaScore = 0;
        $declaAnsweredCount = 0;

        foreach ($pkg['declaratifQuestions'] as $idx => $dq) {
            $qId = (string) (isset($dq['id']) ? $dq['id'] : '');
            $declaQuestionsTag[] = $qId;
            $sh = isset($dq['short']) ? $dq['short'] : 'transversale';
            if (!isset($declaSpecialtyScores[$sh])) {
                $declaSpecialtyScores[$sh] = array('correct' => 0, 'total' => 0);
            }
            $declaSpecialtyScores[$sh]['total']++;

            $key = (string) (isset($dq['index']) ? $dq['index'] : ($idx + 1));
            $userVal = isset($rawDeclaAnswers[$key]) ? $rawDeclaAnswers[$key] : null;
            $norm = offlineQcmNormalizeDeclaratifChoice($userVal);

            if ($norm['raw'] !== 'null') {
                $declaAnsweredCount++;
            }
            if ($norm['positive']) {
                $declaScore++;
                $declaSpecialtyScores[$sh]['correct']++;
                $declaProposals[] = 'Oui';
            } else {
                $declaProposals[] = 'Non';
            }
            $declaUserAnswers[] = $norm['raw'];
        }

        if ($declaAnsweredCount <= 1) {
            throw new RuntimeException('Moins de 2 réponses à l auto-évaluation Déclaratif — import refusé');
        }

        $userDoc = $db->getCollection('users')->findOne(array('_id' => new ObjectId($userId)));
        $subsidiary = $userDoc ? offlineQcmStr(isset($userDoc['subsidiary']) ? $userDoc['subsidiary'] : '') : '';
        $declaTotal = count($declaQuestionsTag);

        $numQuery = array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($declaratifTestId),
            'type' => 'Declaratif',
            'typeR' => 'Techniciens',
            'level' => $level,
        );
        $numTest = count(iterator_to_array($db->getCollection('results')->find($numQuery))) + 1;
        $declaTime = isset($pkg['session']['declaratifTimerFormatted']) ? $pkg['session']['declaratifTimerFormatted'] : '00:00:00';

        $declaResultData = array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($declaratifTestId),
            'type' => 'Declaratif',
            'typeR' => 'Techniciens',
            'level' => $level,
            'questions' => $declaQuestionsTag,
            'answers' => $declaProposals,
            'userAnswers' => $declaUserAnswers,
            'score' => $declaScore,
            'total' => $declaTotal,
            'specialtyScores' => $declaSpecialtyScores,
            'subsidiary' => $subsidiary,
            'numberTest' => $numTest,
            'time' => $declaTime,
            'active' => true,
            'created' => date('d-m-Y H:i:s'),
            'offlineImport' => true,
            'offlineExportId' => $pkg['meta']['exportId'],
        );
        $db->getCollection('results')->insertOne($declaResultData);

        $db->getCollection('exams')->updateMany(
            array('user' => new ObjectId($userId), 'test' => new ObjectId($declaratifTestId), 'active' => true),
            array('$set' => array('active' => false))
        );
        $examDeclaData = array(
            'questions' => array_map(function ($id) {
                return new ObjectId($id);
            }, $declaQuestionsTag),
            'answers' => $declaUserAnswers,
            'user' => new ObjectId($userId),
            'test' => new ObjectId($declaratifTestId),
            'total' => $declaAnsweredCount,
            'active' => false,
            'type' => 'Technicien',
            'created' => date('d-m-Y H:i:s'),
            'updated' => date('d-m-Y H:i:s'),
            'offlineImport' => true,
            'offlineExportId' => $pkg['meta']['exportId'],
        );
        $db->getCollection('exams')->insertOne($examDeclaData);

        $allocDecla = $allocColl->findOne(array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($declaratifTestId),
        ));
        if ($allocDecla) {
            $allocColl->updateOne(
                array('_id' => $allocDecla['_id']),
                array('$set' => array('active' => true))
            );
        }
    }

    /* ── 3. Déblocage de progression niveau suivant ── */
    require_once __DIR__ . '/../../views/measure/includes/allocation_progression.php';
    allocationProgression_tryUnlockNextLevel($allocColl, new ObjectId($userId), $level);
    allocationProgression_syncUnlockedLevels($allocColl, new ObjectId($userId));

    return array(
        'username' => $pkg['meta']['username'],
        'level' => $level,
        'factuelScore' => $factuelScore,
        'factuelTotal' => $factuelTotal,
        'declaratifScore' => $declaScore,
        'declaratifTotal' => $declaTotal,
        'score' => $factuelScore !== null ? $factuelScore : $declaScore,
        'total' => $factuelTotal !== null ? $factuelTotal : $declaTotal,
    );
}

function offlineQcmCopyPlayer($destDir)
{
    $src = __DIR__ . '/../../offline-qcm/player';
    if (!is_dir($src)) {
        throw new RuntimeException('Dossier player introuvable: ' . $src);
    }
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $files = array('index.html', 'styles.css', 'app.js', 'manifest.webmanifest', 'sw.js', 'README.md');
    foreach ($files as $f) {
        $from = $src . DIRECTORY_SEPARATOR . $f;
        if (is_file($from)) {
            copy($from, $destDir . DIRECTORY_SEPARATOR . $f);
        }
    }
}

/**
 * HTML autonome : questions integrees, zero import cote technicien.
 * A la fin, le player telecharge le meme HTML avec les reponses dans #medacap-quiz-data.
 */
function offlineQcmBuildStandaloneHtml(array $pkg, $templateDir = null)
{
    $templateDir = $templateDir ?: __DIR__ . '/../../offline-qcm/player';
    $htmlPath = $templateDir . DIRECTORY_SEPARATOR . 'index.html';
    $cssPath = $templateDir . DIRECTORY_SEPARATOR . 'styles.css';
    $jsPath = $templateDir . DIRECTORY_SEPARATOR . 'app.js';

    if (!is_file($htmlPath) || !is_file($cssPath) || !is_file($jsPath)) {
        throw new RuntimeException('Templates player manquants dans ' . $templateDir);
    }

    $html = file_get_contents($htmlPath);
    $css = file_get_contents($cssPath);
    $js = file_get_contents($jsPath);
    $json = json_encode($pkg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

    $html = str_replace(
        '<link rel="stylesheet" href="styles.css">',
        '<style>' . $css . '</style>',
        $html
    );
    $html = str_replace(
        '<script src="app.js"></script>',
        '<script type="application/json" id="medacap-quiz-data">' . $json . '</script>' . "\n" . '<script>' . $js . '</script>',
        $html
    );
    $html = str_replace(
        '<body>',
        '<body class="embedded-kit">',
        $html
    );

    return $html;
}

function offlineQcmStandaloneFilename($username, $level)
{
    return offlineQcmSlug($username) . '_' . $level . '_QCM.html';
}

function offlineQcmResultFilename($username, $level)
{
    return offlineQcmSlug($username) . '_' . $level . '_QCM.html';
}

/**
 * Charge un kit depuis un .html (reponses embarquees) ou un .medacap.json legacy.
 *
 * @return array
 */
function offlineQcmLoadPackageFromFile($file)
{
    if (!is_file($file)) {
        throw new InvalidArgumentException('Fichier introuvable: ' . $file);
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'html' || $ext === 'htm') {
        $html = file_get_contents($file);
        if (!preg_match('/<script[^>]+id=["\']medacap-quiz-data["\'][^>]*>(.*?)<\/script>/s', $html, $matches)) {
            throw new InvalidArgumentException('Bloc medacap-quiz-data introuvable dans le HTML');
        }
        $pkg = json_decode(trim($matches[1]), true);
    } else {
        $pkg = json_decode(file_get_contents($file), true);
    }

    if (!is_array($pkg)) {
        throw new InvalidArgumentException('Donnees QCM illisibles dans ' . basename($file));
    }

    return $pkg;
}

/**
 * Re-ecrit le kit dans le fichier source (.html ou .json).
 */
function offlineQcmWritePackageToFile($file, array $pkg)
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'html' || $ext === 'htm') {
        $html = file_get_contents($file);
        $json = json_encode($pkg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        $updated = preg_replace(
            '/(<script[^>]+id=["\']medacap-quiz-data["\'][^>]*>)(.*?)(<\/script>)/s',
            '$1' . $json . '$3',
            $html,
            1,
            $count
        );
        if ($count !== 1) {
            throw new RuntimeException('Impossible de mettre a jour medacap-quiz-data dans le HTML');
        }
        file_put_contents($file, $updated);
        return;
    }

    file_put_contents($file, json_encode($pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function offlineQcmLevelsForUser($userLevel)
{
    $order = array('Junior', 'Senior', 'Expert');
    $idx = array_search($userLevel, $order, true);
    if ($idx === false) {
        return array('Junior');
    }
    return array_slice($order, 0, $idx + 1);
}
