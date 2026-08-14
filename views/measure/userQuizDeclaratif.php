<?php
session_start();
// Réponses AJAX (POST) : JSON strict — aucune sortie parasite (warnings/deprecated) ne doit corrompre le corps.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    @ini_set('display_errors', '0');
    ob_start();
}
require "../sendMail.php";
include_once "./includes/language.php";
require_once "../../vendor/autoload.php";
require_once "./components/ui/quiz.php";
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

class MongoDBConnection {
    private $client;
    private $collections;
    public function __construct() {
        try {
            $this->client = new Client("mongodb://localhost:27017");
            $db = $this->client->academy;
            $this->collections = [
                'users' => $db->users,
                'quizzes' => $db->quizzes,
                'questions' => $db->questions,
                'results' => $db->results,
                'exams' => $db->exams,
                'tests' => $db->tests,
                'allocations' => $db->allocations
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
        if ($redirect) $response['redirect'] = $redirect;
        while (ob_get_level() > 0) { @ob_end_clean(); } // purge toute sortie parasite (warnings) avant le JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

class QuizConstants {
    public static $SPECIALTIES = [
        "Assistance à la Conduite" => ["quizAssistance", "answerAssistance", "assistanceDecla", "assistanceConduite"],
        "Arbre de Transmission" => ["quizArbre", "answerArbre", "arbreDecla", "arbre"],
        "Boite de Transfert" => ["quizTransfert", "answerTransfert", "transfertDecla", "transfert"],
        "Boite de Vitesse" => ["quizBoite", "answerBoite", "boiteDecla", "boite_vitesse"],
        "Boite de Vitesse Automatique" => ["quizBoiteAuto", "answerBoiteAuto", "boiteAutoDecla", "boite_vitesse_auto"],
        "Boite de Vitesse Mécanique" => ["quizBoiteMan", "answerBoiteMan", "boiteManDecla", "boite_vitesse_meca"],
        "Boite de Vitesse à Variation Continue" => ["quizBoiteVc", "answerBoiteVc", "boiteVcDecla", "boite_vitesse_VC"],
        "Climatisation" => ["quizClimatisation", "answerClimatisation", "climatisationDecla", "clim"],
        "Demi Arbre de Roue" => ["quizDemi", "answerDemi", "demiDecla", "demi"],
        "Direction" => ["quizDirection", "answerDirection", "directionDecla", "direction"],
        "Electricité et Electronique" => ["quizElectricite", "answerElectricite", "electriciteDecla", "electricite"],
        "Freinage" => ["quizFrei", "answerFrei", "freiDecla", "freinage"],
        "Freinage Electromagnétique" => ["quizFreinageElec", "answerFreinageElec", "freinageElecDecla", "freinage_elec"],
        "Freinage Hydraulique" => ["quizFreinage", "answerFreinage", "freinageHydDecla", "freinage_hyd"],
        "Freinage Pneumatique" => ["quizFrein", "answerFrein", "freinDecla", "frein"],
        "Hydraulique" => ["quizHydraulique", "answerHydraulique", "hydrauliqueDecla", "hydraulique"],
        "Moteur Diesel" => ["quizMoteurDiesel", "answerMoteurDiesel", "moteurDieselDecla", "moteur_diesel"],
        "Moteur Electrique" => ["quizMoteurElec", "answerMoteurElec", "moteurElecDecla", "moteur_elec"],
        "Moteur Essence" => ["quizMoteurEssence", "answerMoteurEssence", "moteurEssenceDecla", "moteur_essence"],
        "Moteur Thermique" => ["quizMoteur", "answerMoteur", "moteurDecla", "moteur"],
        "Réseaux de Communication" => ["quizMultiplexage", "answerMultiplexage", "multiplexageDecla", "multiplexage"],
        "Pont" => ["quizPont", "answerPont", "pontDecla", "pont"],
        "Pneumatique" => ["quizPneumatique", "answerPneumatique", "pneumatiqueDecla", "pneumatique"],
        "Reducteur" => ["quizReducteur", "answerReducteur", "reducteurDecla", "reducteur"],
        "Suspension" => ["quizSuspension", "answerSuspension", "suspensionDecla", "suspension"],
        "Suspension à Lame" => ["quizSuspensionLame", "answerSuspensionLame", "suspensionLameDecla", "suspension_lame"],
        "Suspension Ressort" => ["quizSuspensionRessort", "answerSuspensionRessort", "suspensionRessortDecla", "suspension_ressort"],
        "Suspension Pneumatique" => ["quizSuspensionPneumatique", "answerSuspensionPneumatique", "suspensionPneumatiqueDecla", "suspension_pneumatique"],
        "Transversale" => ["quizTransversale", "answerTransversale", "transversaleDecla", "transversale"]
    ];
    public static function getShortCode($specialty) {
        foreach (self::$SPECIALTIES as $spec => $data) {
            if ($spec === $specialty) return $data[3];
        }
        return '';
    }
    public static function getQuizKey($specialty) {
        foreach (self::$SPECIALTIES as $spec => $data) {
            if ($spec === $specialty) return $data[0];
        }
        return '';
    }
}

class QuizValidator {
    public static function validateSession() {
        if (!isset($_SESSION["id"])) {
            header("Location: ../../");
            exit();
        }
    }
    public static function validateParams($params) {
        foreach (['id', 'level', 'test'] as $key) {
            if (empty($params[$key])) {
                self::jsonError('Missing parameters');
            }
        }
        try {
            new ObjectId($params['id']);
            new ObjectId($params['test']);
        } catch (Exception $e) {
            self::jsonError('Invalid user or test ID');
        }
    }
    private static function jsonError($message) {
        while (ob_get_level() > 0) { @ob_end_clean(); } // purge toute sortie parasite avant le JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}

class QuizDataFetcher {
    private $db;
    public function __construct($db) {
        $this->db = $db;
    }
    public function fetchUserData($userId, $testId, $level) {
        $users = $this->db->getCollection('users');
        $user = $users->findOne(['_id' => new ObjectId($userId), 'active' => true]);
        if (!$user) return null;
        $tests = $this->db->getCollection('tests');
        $testData = $tests->findOne(['_id' => $testId, 'user' => new ObjectId($userId), 'type' => "Declaratif", 'level' => $level, 'active' => true]);
        if (!$testData) return null;
        $exams = $this->db->getCollection('exams');
        $exam = $exams->findOne(['user' => new ObjectId($userId), 'test' => $testId, 'type' => "Technicien", 'active' => true]);
        $manager = $this->fetchManager($users, $user);
        return [
            'user' => $user,
            'test' => $testData,
            'exam' => $exam,
            'manager' => $manager
        ];
    }
    private function fetchManager($users, $user) {
        if (empty($user['manager'])) return null;
        try {
            return $users->findOne(['_id' => new ObjectId($user['manager']), 'active' => true]);
        } catch (Exception $e) {
            error_log("Manager fetch error: " . $e->getMessage());
            return null;
        }
    }
}

class QuizQuestionLoader {
    private $db;
    private $level;
    private $userData;
    public function __construct($db, $level, $userData) {
        $this->db = $db;
        $this->level = $level;
        $this->userData = $userData;
    }
    public function load() {
        $exam = $this->userData['exam'] ?? null;
        $test = $this->userData['test'];
        $allQuestions = [];
        if ($exam && !empty($exam['questions'])) {
            $allQuestions = $this->loadFromExam($exam);
        } else {
            $allQuestions = $this->loadFromTest($test);
        }
        if (empty($allQuestions)) {
            throw new Exception('No questions found');
        }
        return $allQuestions;
    }
    private function loadFromExam($exam) {
        $questionsColl = $this->db->getCollection('questions');
        $savedAnswers = $exam['answers'] ?? [];
        $questionExam = $exam['questions'] ?? [];
        $allQuestions = [];
        foreach ($questionExam as $index => $questionId) {
            foreach (QuizConstants::$SPECIALTIES as $speciality => $data) {
                try {
                    $question = $questionsColl->findOne([
                        '_id' => new ObjectId($questionId),
                        'speciality' => $speciality,
                        'type' => "Declarative",
                        'level' => $this->level,
                        'active' => true
                    ]);
                    if ($question) {
                        $allQuestions[] = [
                            'question' => $question,
                            'quizId' => $exam[$data[0]] ?? null,
                            'specialty' => $speciality,
                            'short' => $data[3],
                            'answer' => isset($savedAnswers[$index]) ? $savedAnswers[$index] : 'null'
                        ];
                        break;
                    }
                } catch (Exception $e) {
                    error_log("Error fetching question ID {$questionId}: " . $e->getMessage());
                }
            }
        }
        return $allQuestions;
    }
    private function loadFromTest($test) {
        $allQuestions = [];
        $deQuizs = isset($test['quizzes']) ? (is_array($test['quizzes']) ? $test['quizzes'] : iterator_to_array($test['quizzes'], false)) : [];
        $deQuizs = array_filter($deQuizs, function($q) {
            try {
                new ObjectId($q);
                return true;
            } catch (Exception $e) {
                error_log("Invalid quiz ID: " . $q);
                return false;
            }
        });
        foreach (QuizConstants::$SPECIALTIES as $speciality => $data) {
            try {
                $quizIds = array_map(function ($q) {
                    return new ObjectId($q);
                }, $deQuizs);
                $quiz = $this->db->getCollection('quizzes')->findOne([
                    '_id' => ['$in' => $quizIds],
                    'speciality' => $speciality,
                    'type' => "Declaratif",
                    'level' => $this->level,
                    'active' => true
                ]);
                if ($quiz) {
                    foreach ($quiz['questions'] as $questionId) {
                        try {
                            $question = $this->db->getCollection('questions')->findOne(['_id' => new ObjectId($questionId), 'active' => true]);
                            if ($question) {
                                $allQuestions[] = [
                                    'question' => $question,
                                    'quizId' => $quiz->_id,
                                    'specialty' => $speciality,
                                    'short' => $data[3],
                                    'answer' => 'null'
                                ];
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching question ID {$questionId}: " . $e->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching quiz for {$speciality}: " . $e->getMessage());
            }
        }
        return $allQuestions;
    }
}

class QuizExamManager {
    private $db;
    private $userId;
    private $testId;
    private $quizIds;
    public function __construct($db, $userId, $testId, $quizIds = []) {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->quizIds = $quizIds;
    }
    public function create($questionsTags, $answers, $hr, $mn, $sc, $total) {
        try {
            $examData = array_merge([
                'questions' => $questionsTags,
                'answers' => $answers,
                'user' => new ObjectId($this->userId),
                'test' => new ObjectId($this->testId),
                'hour' => $hr,
                'minute' => $mn,
                'second' => $sc,
                'total' => $total,
                'type' => 'Technicien',
                'active' => true,
                'created' => date('d-m-Y H:i:s')
            ], $this->quizIds);
            $this->db->getCollection('exams')->insertOne($examData);
            return true;
        } catch (Exception $e) {
            error_log("Error creating exam: " . $e->getMessage());
            return false;
        }
    }
    public function update($exam, $answers, $hr, $mn, $sc, $total) {
        try {
            $this->db->getCollection('exams')->updateOne(
                ['_id' => $exam->_id],
                ['$set' => [
                    'answers' => $answers,
                    'hour' => $hr,
                    'minute' => $mn,
                    'second' => $sc,
                    'total' => $total,
                    'updated' => date('d-m-Y H:i:s')
                ]]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error updating exam: " . $e->getMessage());
            return false;
        }
    }
    public function deactivate($exam) {
        if (!$exam) return true;
        try {
            $this->db->getCollection('exams')->updateOne(
                ['_id' => $exam->_id],
                ['$set' => ['active' => false]]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error deactivating exam: " . $e->getMessage());
            return false;
        }
    }
}

class QuizResultSaver {
    private $db;
    private $userId;
    private $testId;
    private $level;
    private $userData;
    private $manager;
    public function __construct($db, $userId, $testId, $level, $userData) {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->level = $level;
        $this->userData = $userData;
        $this->manager = $userData['manager'] ?? null;
    }
    public function save($questionsTag, $proposal, $userAnswer, $score, $time, $specialtyScores) {
        try {
            $query = [
                'user' => new ObjectId($this->userId),
                'test' => new ObjectId($this->testId),
                'type' => "Declaratif",
                'typeR' => "Techniciens",
                'level' => $this->level
            ];
            $numberTest = count($this->db->getCollection('results')->find($query)->toArray()) + 1;
            $resultData = [
                'questions' => $questionsTag,
                'answers' => $proposal,
                'userAnswers' => $userAnswer,
                'user' => new ObjectId($this->userId),
                'test' => new ObjectId($this->testId),
                'score' => $score,
                'specialtyScores' => $specialtyScores,
                'level' => $this->level,
                'type' => "Declaratif",
                'subsidiary' => $this->userData['user']['subsidiary'],
                'typeR' => "Techniciens",
                'total' => count($questionsTag),
                'numberTest' => $numberTest,
                'time' => $time,
                'active' => true,
                'created' => date('d-m-Y H:i:s')
            ];
            $this->db->getCollection('results')->insertOne($resultData);
            $this->updateAllocation();
            if ($this->manager) $this->saveManagerComparison($proposal, count($questionsTag));
            $this->sendNotification();
            return true;
        } catch (Exception $e) {
            error_log("Error saving results: " . $e->getMessage());
            return false;
        }
    }
    private function updateAllocation() {
        try {
            $alloc = $this->db->getCollection('allocations')->findOne([
                'user' => new ObjectId($this->userId),
                'test' => new ObjectId($this->testId)
            ]);
            if ($alloc) {
                $this->db->getCollection('allocations')->updateOne(
                    ['_id' => $alloc->_id],
                    ['$set' => ['active' => true]]
                );
                require_once __DIR__ . '/includes/allocation_progression.php';
                allocationProgression_tryUnlockNextLevel(
                    $this->db->getCollection('allocations'),
                    new ObjectId($this->userId),
                    $this->level
                );
            }
        } catch (Exception $e) {
            error_log("Error updating allocation: " . $e->getMessage());
        }
    }
    private function saveManagerComparison($proposal, $total) {
        try {
            $results = $this->db->getCollection('results');
            $managerResult = $results->findOne([
                'user' => new ObjectId($this->userId),
                'manager' => new ObjectId($this->userData['user']['manager']),
                'test' => new ObjectId($this->testId),
                'type' => "Declaratif",
                'typeR' => "Managers",
                'level' => $this->level
            ]);
            if ($managerResult) {
                $scoreF = 0;
                foreach ($proposal as $i => $p) {
                    if ($p === "Oui" && $managerResult->answers[$i] === "Oui") $scoreF++;
                }
                $query = [
                    'user' => new ObjectId($this->userId),
                    'manager' => new ObjectId($managerResult->manager),
                    'test' => new ObjectId($this->testId),
                    'type' => "Declaratif",
                    'typeR' => "Technicien - Manager",
                    'level' => $this->level
                ];
                $existf = count($results->find($query)->toArray());
                $resultData = [
                    'user' => new ObjectId($this->userId),
                    'manager' => new ObjectId($managerResult->manager),
                    'test' => new ObjectId($this->testId),
                    'score' => $scoreF,
                    'level' => $this->level,
                    'type' => "Declaratif",
                    'subsidiary' => $this->userData['user']['subsidiary'],
                    'typeR' => "Technicien - Manager",
                    'total' => $total,
                    'numberTest' => $existf + 1,
                    'active' => true,
                    'created' => date('d-m-Y H:i:s')
                ];
                $results->insertOne($resultData);
            }
        } catch (Exception $e) {
            error_log("Error saving manager comparison: " . $e->getMessage());
        }
    }
    private function sendNotification() {
        try {
            $technician = $this->userData['user'];
            if (!isset($technician['firstName'], $technician['lastName'], $this->level)) return;
            $client = $this->db->getClient();
            $adminDocs = getAdminsDocs($technician, $client);
            if (!$adminDocs) return;
            $rhDpsEmails = getRhAndDpsEmails($technician['subsidiary'], $client);
            $salutation = buildAdminSalutation($adminDocs);
            $isRwanda = $technician['subsidiary'] === "CFAO MOTORS RWANDA";
            $subject = sprintf(
                $isRwanda ? 'Professional Task MCQ completed by Technician %s %s for Level %s' : 'QCM Tâche Professionnelle terminé par le Technicien %s %s pour le Niveau %s',
                $technician['firstName'], $technician['lastName'], $this->level
            );
            $body = sprintf(
                $isRwanda ?
                    '<p>%s</p><p>The Professional Task MCQ has just been completed by technician <strong>%s&nbsp;%s</strong> for level <strong>%s</strong>. You can view their result in the <em>Technician Results by Level</em> section of the MEDACAP platform.</p>' :
                    "<p>%s</p><p>Le QCM Tâche Professionnelle vient d'être terminé par le technicien <strong>%s&nbsp;%s</strong> pour le niveau <strong>%s</strong>. Vous pouvez consulter son résultat dans l'espace <em>Résultats des Techniciens par niveau</em> de la plateforme MEDACAP.</p>",
                $salutation, htmlspecialchars($technician['firstName']), htmlspecialchars($technician['lastName']), htmlspecialchars($this->level)
            );
//            sendMailQcmFinalized($adminDocs, $subject, $body, $this->manager ? $this->manager['email'] : null, $rhDpsEmails);
        } catch (Exception $e) {
            error_log("Error sending notification email: " . $e->getMessage());
        }
    }
}

class QuizPostHandler {
    private $db;
    private $userId;
    private $testId;
    private $level;
    private $userData;
    private $allQuestions;
    private $examManager;
    private $resultSaver;
    public function __construct($db, $userId, $testId, $level, $userData, $allQuestions) {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->level = $level;
        $this->userData = $userData;
        $this->allQuestions = $allQuestions;
        $this->examManager = new QuizExamManager($db, $userId, $testId, $this->buildQuizIds());
        $this->resultSaver = new QuizResultSaver($db, $userId, $testId, $level, $userData);
    }
    public function handle($action) {
        $totalQuestions = count($this->allQuestions);
        $exam = $this->userData['exam'] ?? null;
        if ($action === 'save') {
            $this->handleSave($exam, $totalQuestions);
        } elseif ($action === 'submit') {
            $this->handleSubmit($exam, $totalQuestions);
        }
    }
    private function handleSave($exam, $totalQuestions) {
        try {
            $answersJson = json_decode($_POST['answers'] ?? '{}', true);
            $formattedAnswers = [];
            for ($i = 1; $i <= $totalQuestions; $i++) {
                $formattedAnswers[] = isset($answersJson[$i]) ? $answersJson[$i] : 'null';
            }
            $hr = (int)($_POST['hr'] ?? 0);
            $mn = (int)($_POST['mn'] ?? 0);
            $sc = (int)($_POST['sc'] ?? 0);
            $questionsTags = array_map(function($q) {
                return new ObjectId($q['question']['_id']);
            }, $this->allQuestions);
            $totalAnswered = count(array_filter($formattedAnswers, function($a) {
                return $a !== 'null';
            }));
            $success = $exam
                ? $this->examManager->update($exam, $formattedAnswers, $hr, $mn, $sc, $totalAnswered)
                : $this->examManager->create($questionsTags, $formattedAnswers, $hr, $mn, $sc, $totalAnswered);
            $this->db->jsonResponse($success, $success ? 'Saved successfully' : 'Failed to save exam');
        } catch (Exception $e) {
            error_log("Error in save: " . $e->getMessage());
            $this->db->jsonResponse(false, 'Server error during save');
        }
    }
    private function handleSubmit($exam, $totalQuestions) {
        try {
            $answers = json_decode($_POST['answers'] ?? '{}', true);
            if (!is_array($answers)) throw new Exception('Invalid answers format');
            $userAnswer = [];
            for ($i = 1; $i <= $totalQuestions; $i++) {
                $userAnswer[] = isset($answers[$i]) ? $answers[$i] : 'null';
            }
            
            $answeredCount = count(array_filter($userAnswer, function($a) { return $a !== 'null'; }));
            
            if ($answeredCount <= 1) {
                $this->db->jsonResponse(false, 'Vous devez répondre à au moins 2 questions pour pouvoir finaliser.');
            }
            
            $this->processResults($userAnswer, $_POST['timer'] ?? '00:00:00', $exam);
            $this->examManager->deactivate($exam);
            $percent = $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100) : 0;
            $this->db->jsonResponse(true, 'Evaluation completed', './congrat?p=' . $percent);
        } catch (Exception $e) {
            error_log("Error in submit: " . $e->getMessage());
            $this->db->jsonResponse(false, 'Server error during submission');
        }
    }
    private function processResults($userAnswer, $time, $exam) {
        global $je_maitrise;
        $score = 0;
        $proposal = [];
        $specialtyScores = [];
        foreach (array_column(QuizConstants::$SPECIALTIES, 3) as $short) {
            $specialtyScores[$short] = ['correct' => 0, 'total' => 0];
        }
        $questionsTag = array_map(function($q) {
            return (string)$q['question']['_id'];
        }, $this->allQuestions);
        
        $positiveStrings = [
            "Je maitrise (je réalise cette tâche professionnelle seul)",
            "Je maîtrise (je réalise cette tâche professionnelle seul)",
            "I master (I perform this professional task alone)",
            "Je maîtrise",
            "Je maitrise",
            "I master",
            "Il maitrise (le technicien réalise cette tâche professionnelle)",
            "Il maîtrise (le technicien réalise cette tâche professionnelle)",
            "He masters (the technician performs this professional task)"
        ];
        if (isset($je_maitrise) && !empty($je_maitrise)) {
            $positiveStrings[] = $je_maitrise;
        }

        foreach ($this->allQuestions as $index => $q) {
            $shortSpeciality = $q['short'];
            $userAns = $userAnswer[$index];
            $specialtyScores[$shortSpeciality]['total']++;
            
            $isCorrect = false;
            $normalizedAns = trim($userAns);
            foreach ($positiveStrings as $posStr) {
                if (strcasecmp($normalizedAns, trim($posStr)) === 0) {
                    $isCorrect = true;
                    break;
                }
            }
            
            if ($isCorrect) {
                $score++;
                $specialtyScores[$shortSpeciality]['correct']++;
                $proposal[] = "Oui";
            } else {
                $proposal[] = "Non";
            }
        }
        $this->resultSaver->save($questionsTag, $proposal, $userAnswer, $score, $time, $specialtyScores);
    }
    private function buildQuizIds() {
        $quizIds = [];
        foreach (QuizConstants::$SPECIALTIES as $data) {
            $quizIds[$data[0]] = null;
        }
        $exam = $this->userData['exam'] ?? null;
        if ($exam) {
            foreach (QuizConstants::$SPECIALTIES as $data) {
                $quizIds[$data[0]] = isset($exam[$data[0]]) ? $exam[$data[0]] : null;
            }
        } else {
            foreach ($this->allQuestions as $q) {
                $key = QuizConstants::getQuizKey($q['specialty']);
                if ($key && $quizIds[$key] === null) {
                    $quizIds[$key] = $q['quizId'];
                }
            }
        }
        return array_filter($quizIds, function($v) {
            return $v !== null;
        });
    }
}

// Bootstrap
QuizValidator::validateSession();
$params = [
    'id' => $_GET['id'] ?? '',
    'level' => $_GET['level'] ?? '',
    'test' => $_GET['test'] ?? '',
    'guide' => $_GET['guide'] ?? false,
    'debug' => filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)
];
QuizValidator::validateParams($params);
$db = new MongoDBConnection();
$fetcher = new QuizDataFetcher($db);
$data = $fetcher->fetchUserData($params['id'], new ObjectId($params['test']), $params['level']);
if (!$data) {
    $db->jsonResponse(false, 'Technician or test not found');
}
$loader = new QuizQuestionLoader($db, $params['level'], $data);
$allQuestions = $loader->load();
$totalQuestions = count($allQuestions);
$user = $data['user'];
$exam = $data['exam'] ?? null;
$manager = $data['manager'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $handler = new QuizPostHandler($db, $params['id'], new ObjectId($params['test']), $params['level'], $data, $allQuestions);
    $handler->handle($_POST['action']);
}
$labels = [
    'je_maitrise' => isset($je_maitrise) ? $je_maitrise : 'Je maîtrise',
    'je_ne_maitrise_pas' => isset($je_ne_maitrise_pas) ? $je_ne_maitrise_pas : 'Je ne maîtrise pas',
    'jai_jamais_fait' => isset($jai_jamais_fait) ? $jai_jamais_fait : 'J\'ai jamais fait'
];
?>
<?php include_once "./partials/header.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($test_tache_pro) ? htmlspecialchars($test_tache_pro) : 'Tâche Professionnelle'; ?> | CFAO Mobility Academy</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/quiz.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css">
<?php
renderQuizComponent($allQuestions, $user, $params, $exam, isset($test_tache_pro) ? $test_tache_pro : 'Tâche Professionnelle', $level_label, 'declaratif', $labels);
?>
<?php include_once "partials/footer.php"; ?>
<?php include_once "chat.php"; ?>