<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
// Réponses AJAX (POST) : JSON strict — aucune sortie parasite (warnings/deprecated) ne doit corrompre le corps.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    @ini_set('display_errors', '0');
    ob_start();
    register_shutdown_function(function () {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $detail = sprintf(
            'FATAL type=%d %s @ %s:%d',
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
        error_log('[quizFactuel] action=' . ($_POST['action'] ?? '?')
            . ' user=' . ($_GET['id'] ?? '?')
            . ' test=' . ($_GET['test'] ?? '?')
            . ' level=' . ($_GET['level'] ?? '?')
            . ' | ' . $detail);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        $message = 'Server error during submission';
        if (filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)) {
            $message .= ' — ' . $detail;
        }
        echo json_encode(['success' => false, 'message' => $message]);
    });
}
require "../sendMail.php";
$lang = $_SESSION['lang'] ?? 'FR';
if ($lang === 'EN') {
    include_once "./includes/language_EN.php";
} else {
    include_once "./includes/language.php";
}
require_once "../../vendor/autoload.php";
require_once "./components/ui/quiz.php";
require_once __DIR__ . '/../../core/mongo_compat.php';
require_once __DIR__ . '/includes/quiz_validator.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;
class MongoDBConnection {
    private $client;
    private $collections;
    public function __construct() {
        try {
            $this->client = new Client(medacapMongoUri());
            $db = $this->client->academy;
            $this->collections = [
                'users' => $db->users,
                'quizzes' => $db->quizzes,
                'questions' => $db->questions,
                'results' => $db->results,
                'exams' => $db->exams,
                'tests' => $db->tests,
                'allocations' => $db->allocations,
            ];
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Database connection failed: ' . $e->getMessage());
        }
    }
    public function getCollection($name) {
        return $this->collections[$name] ?? null;
    }
    public function getClient() {
        return $this->client;
    }
    public function jsonResponse($success, $message, $redirect = null) {
        $response = ['success' => $success, 'message' => $message];
        if ($redirect) {
            $response['redirect'] = $redirect;
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit();
    }
}
function quizFactuelDescribeThrowable(Throwable $e) {
    return get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
require_once __DIR__ . '/includes/quiz_factuel_engine.php';

class QuizPostHandler {
    private $db;
    private $userId;
    private $testId;
    private $level;
    private $userData;
    private $allQuestions;
    private $resultProcessor;
    public function __construct($db, $userId, $testId, $level, $userData, $allQuestions) {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->level = $level;
        $this->userData = $userData;
        $this->allQuestions = $allQuestions;
        $this->resultProcessor = new QuizResultProcessor($db, $userId, $testId, $level, $userData, $allQuestions);
    }
    public function handle() {
        $action = $_POST['action'] ?? '';
        $totalQs = count($this->allQuestions);
        if ($action === 'save') {
            $this->save($totalQs);
        } elseif ($action === 'submit') {
            $this->submit($totalQs);
        }
    }
    private function save($totalQs) {
        try {
            $answers = json_decode($_POST['answers'] ?? '{}', true);
            $time = [
                'hour' => (int)($_POST['hr'] ?? 0),
                'minute' => (int)($_POST['mn'] ?? 0),
                'second' => (int)($_POST['sc'] ?? 0)
            ];
            $qTags = array_map(function($q) { return new ObjectId($q['question']['_id']); }, $this->allQuestions);
            $fmtAnswers = [];
            for ($i = 1; $i <= $totalQs; $i++) {
                $fmtAnswers[] = $answers[$i] ?? 'null';
            }
            $quizIds = $this->buildQuizIds();
            $examData = array_merge([
                'questions' => $qTags,
                'answers' => $fmtAnswers,
                'user' => new ObjectId($this->userId),
                'test' => new ObjectId($this->testId),
                'total' => count(array_filter($fmtAnswers, function($a) { return $a !== 'null'; })),
                'active' => true,
                'type' => 'Factuel'
            ], $time, $quizIds);
            $exam = $this->userData['exam'] ?? null;
            $exams = $this->db->getCollection('exams');
            if ($exam) {
                $examData['updated'] = date('d-m-Y H:i:s');
                $exams->updateOne(['_id' => $exam['_id']], ['$set' => $examData]);
            } else {
                $examData['created'] = date('d-m-Y H:i:s');
                $exams->insertOne($examData);
            }
            $this->jsonResponse(true, 'Saved successfully');
        } catch (Throwable $e) {
            $detail = quizFactuelDescribeThrowable($e);
            error_log('[quizFactuel] Save error: user=' . $this->userId
                . ' test=' . $this->testId
                . ' level=' . $this->level
                . ' | ' . $detail);
            $message = 'Enregistrement impossible';
            if (filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)) {
                $message .= ' — ' . $detail;
            }
            $this->jsonResponse(false, $message);
        }
    }
    private function submit($totalQs) {
        @set_time_limit(180);
        $answeredCount = 0;
        try {
            $answers = json_decode($_POST['answers'] ?? '{}', true);
            if (!is_array($answers)) {
                throw new Exception('Invalid answers');
            }
            $userAns = [];
            for ($i = 1; $i <= $totalQs; $i++) {
                $userAns[] = $answers[$i] ?? 'null';
            }

            $answeredCount = count(array_filter($userAns, function($a) { return $a !== 'null'; }));

            if ($answeredCount <= 1) {
                $this->jsonResponse(false, 'Vous devez répondre à au moins 2 questions pour pouvoir finaliser.');
            }

            $this->resultProcessor->process($userAns);

            try {
                $alloc = $this->db->getCollection('allocations');
                $allocData = $alloc->findOne(['user' => new ObjectId($this->userId), 'test' => new ObjectId($this->testId)]);
                if ($allocData) {
                    $alloc->updateOne(['_id' => $allocData['_id']], ['$set' => ['active' => true]]);
                    require_once __DIR__ . '/includes/allocation_progression.php';
                    allocationProgression_tryUnlockNextLevel($alloc, new ObjectId($this->userId), $this->level);
                    allocationProgression_syncUnlockedLevels($alloc, new ObjectId($this->userId));
                }
            } catch (Throwable $e) {
                error_log('[quizFactuel] Allocation update error: ' . quizFactuelDescribeThrowable($e));
            }

            $exam = $this->userData['exam'] ?? null;
            $examsColl = $this->db->getCollection('exams');
            if ($exam && isset($exam['_id'])) {
                $examsColl->updateOne(['_id' => $exam['_id']], ['$set' => ['active' => false]]);
            }
            $examsColl->updateMany(
                ['user' => new ObjectId($this->userId), 'test' => new ObjectId($this->testId), 'active' => true],
                ['$set' => ['active' => false]]
            );

            try {
                $this->sendNotification();
            } catch (Throwable $e) {
                error_log('[quizFactuel] Notification error: ' . quizFactuelDescribeThrowable($e));
            }

            $percent = $totalQs > 0 ? round(($answeredCount / $totalQs) * 100) : 0;
            $this->jsonResponse(true, 'Evaluation completed', './congrat?p=' . $percent);
        } catch (Throwable $e) {
            $detail = quizFactuelDescribeThrowable($e);
            error_log('[quizFactuel] Submit error: user=' . $this->userId
                . ' test=' . $this->testId
                . ' level=' . $this->level
                . ' answered=' . $answeredCount . '/' . $totalQs
                . ' | ' . $detail);
            error_log('[quizFactuel] Submit trace: ' . $e->getTraceAsString());
            $message = 'Server error during submission';
            if (filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)) {
                $message .= ' — ' . $detail;
            }
            $this->jsonResponse(false, $message);
        }
    }
    private function buildQuizIds() {
        $quizIds = [];
        $exam = $this->userData['exam'] ?? null;
        foreach (QuizConstants::$SPECIALTIES as $key => $spec) {
            $quizIds["quiz$key"] = null;
            if (!$exam) {
                foreach ($this->allQuestions as $q) {
                    if ($q['quizId'] && $q['specialty'] === $spec) {
                        $quizIds["quiz$key"] = $q['quizId'];
                        break;
                    }
                }
            } else {
                $quizIds["quiz$key"] = $exam["quiz$key"] ?? null;
            }
        }
        return $quizIds;
    }
    private function sendNotification() {
        $user = $this->userData['user'];
        $manager = $this->userData['manager'] ?? null;
        if (!isset($user['firstName'], $user['lastName'])) return;
        $client = $this->db->getClient();
        $tech = $user;
        $admins = getAdminsDocs($tech, $client);
        if (!$admins) return;
        $mgrEmail = $manager['email'] ?? '';
        $subsidiary = isset($tech['subsidiary']) ? (string) $tech['subsidiary'] : '';
        if ($subsidiary === '') {
            error_log('[quizFactuel] Notification skipped: user=' . ($this->userId ?? '?') . ' sans subsidiary');
        }
        $rhEmails = getRhAndDpsEmails($subsidiary, $client);
        $salut = buildAdminSalutation($admins);
        $isRwanda = $subsidiary === 'CFAO MOTORS RWANDA';
        if ($isRwanda) {
            $subject = sprintf('Knowledge MCQ completed by Technician %s %s for Level %s', $tech['firstName'], $tech['lastName'], $this->level);
            $body = sprintf('<p>%s</p><p>The Knowledge MCQ has been completed by technician <strong>%s %s</strong> for level <strong>%s</strong>. You can view their result in the <em>Technician Results by Level</em> section of the MEDACAP platform.</p>', $salut, htmlspecialchars($tech['firstName']), htmlspecialchars($tech['lastName']), htmlspecialchars($this->level));
        } else {
            $subject = sprintf('QCM Connaissance finalisé par le Technicien %s %s pour le Niveau %s', $tech['firstName'], $tech['lastName'], $this->level);
            $body = sprintf('<p>%s</p><p>Le QCM Connaissance a été finalisé par le technicien <strong>%s %s</strong> pour le niveau <strong>%s</strong>. Vous pouvez consulter son résultat dans l\'espace <em>Résultats des Techniciens par niveau</em> de la plateforme MEDACAP.</p>', $salut, htmlspecialchars($tech['firstName']), htmlspecialchars($tech['lastName']), htmlspecialchars($this->level));
        }
//        sendMailQcmFinalized($admins, $subject, $body, $mgrEmail, $rhEmails);
    }
    private function jsonResponse($success, $message, $redirect = null) {
        $resp = ['success' => $success, 'message' => $message];
        if ($redirect) {
            $resp['redirect'] = $redirect;
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit();
    }
}
// Bootstrap
QuizValidator::validateSession();
// Un technicien ne compose que son propre QCM : ?id= n'est accepté tel quel que pour le Super Admin.
$quizUserId = (string) $_SESSION['id'];
if (isset($_GET['id']) && (string) $_GET['id'] !== $quizUserId) {
    if (($_SESSION['profile'] ?? '') !== 'Super Admin') {
        error_log('[quizFactuel] id refusé : session=' . $quizUserId . ' demandé=' . (string) $_GET['id']);
        header('Location: ./ListeDesQcm');
        exit();
    }
    $quizUserId = (string) $_GET['id'];
}
$params = [
    'id' => $quizUserId,
    'level' => $_GET['level'] ?? '',
    'test' => $_GET['test'] ?? '',
    'guide' => $_GET['guide'] ?? false,
    'debug' => filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)
];
QuizValidator::validateParams($params);
$db = new MongoDBConnection();
// Un QCM déjà terminé ne se repasse pas : sinon l'ouverture crée un nouvel examen vierge.
if ($db->getCollection('allocations')->findOne(['user' => new ObjectId($params['id']), 'test' => new ObjectId($params['test']), 'active' => true])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->jsonResponse(false, 'Ce QCM est déjà terminé.', './ListeDesQcm');
    }
    header('Location: ./ListeDesQcm');
    exit();
}
// Niveau pas encore débloqué (Junior → Senior → Expert) : refusé même en tapant l'URL.
if ($db->getCollection('allocations')->findOne(['user' => new ObjectId($params['id']), 'test' => new ObjectId($params['test']), 'activeTest' => false])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->jsonResponse(false, 'Ce niveau n\'est pas encore débloqué.', './ListeDesQcm');
    }
    header('Location: ./ListeDesQcm');
    exit();
}
$fetcher = new QuizDataFetcher($db);
$data = $fetcher->fetchUserData($params['id'], $params['test'], $params['level']);
if (!$data) {
    $db->jsonResponse(false, 'Invalid user or test data');
}
$generator = new QuizQuestionGenerator($db, $params['level'], $data);
$allQuestions = $generator->generate();
if (empty($allQuestions)) {
    $db->jsonResponse(false, 'No questions found');
}
$totalQuestions = count($allQuestions);
$user = $data['user'];
$exam = $data['exam'] ?? null;
$manager = $data['manager'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler = new QuizPostHandler($db, $params['id'], $params['test'], $params['level'], $data, $allQuestions);
    $handler->handle();
}
?>
<?php include_once "./partials/header.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($evaluation_de ?? 'Évaluation de') . ' ' . htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) . ' | CFAO Mobility Academy'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/quiz.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css">
<?php
renderQuizComponent($allQuestions, $user, $params, $exam, $test_con ?? 'Test de Connaissances', $level_label, 'factuel');
?>
<?php include_once "partials/footer.php"; ?>
<?php include_once "chat.php"; ?>