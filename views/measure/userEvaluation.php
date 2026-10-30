<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/error.log');
require "../sendMail.php";
include_once "../language.php";
require_once "../../vendor/autoload.php";
require_once "./components/ui/quiz.php";
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
                'allocations' => $db->allocations
            ];
        } catch (Exception $e) {
            $this->jsonResponse(false, 'Database connection failed: ' . $e->getMessage());
        }
    }
    public function getCollection($name) {
        return $this->collections[$name] ?? null;
    }
    public function jsonResponse($success, $message, $redirect = null) {
        $response = ['success' => $success, 'message' => $message];
        if ($redirect) $response['redirect'] = $redirect;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    public function getClient() {
        return $this->client;
    }
}

class ManagerQuizValidator {
    public static function validateSession() {
        if (!isset($_SESSION["id"])) {
            header("Location: ../../");
            exit();
        }
    }
    public static function validateParams($params) {
        foreach (['technician', 'manager', 'level', 'test'] as $key) {
            if (empty($params[$key])) {
                self::jsonError('Missing parameters');
            }
        }
        try {
            new ObjectId($params['technician']);
            new ObjectId($params['manager']);
            new ObjectId($params['test']);
        } catch (Exception $e) {
            self::jsonError('Invalid IDs');
        }
    }
    private static function jsonError($message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}

class ManagerQuizConstants {
    public static $SPECIALTIES = [
        'Assistance' => 'Assistance à la Conduite',
        'Arbre' => 'Arbre de Transmission',
        'Transfert' => 'Boite de Transfert',
        'Boite' => 'Boite de Vitesse',
        'BoiteAuto' => 'Boite de Vitesse Automatique',
        'BoiteMan' => 'Boite de Vitesse Mécanique',
        'BoiteVc' => 'Boite de Vitesse à Variation Continue',
        'Climatisation' => 'Climatisation',
        'Demi' => 'Demi Arbre de Roue',
        'Direction' => 'Direction',
        'Electricite' => 'Electricité et Electronique',
        'Frei' => 'Freinage',
        'FreinageElec' => 'Freinage Electromagnétique',
        'Freinage' => 'Freinage Hydraulique',
        'Frein' => 'Freinage Pneumatique',
        'Hydraulique' => 'Hydraulique',
        'MoteurDiesel' => 'Moteur Diesel',
        'MoteurElec' => 'Moteur Electrique',
        'MoteurEssence' => 'Moteur Essence',
        'Moteur' => 'Moteur Thermique',
        'Multiplexage' => 'Réseaux de Communication',
        'Pont' => 'Pont',
        'Pneumatique' => 'Pneumatique',
        'Reducteur' => 'Reducteur',
        'Suspension' => 'Suspension',
        'SuspensionLame' => 'Suspension à Lame',
        'SuspensionRessort' => 'Suspension Ressort',
        'SuspensionPneumatique' => 'Suspension Pneumatique',
        'Transversale' => 'Transversale'
    ];
}

class ManagerQuizDataFetcher {
    private $db;
    public function __construct($db) {
        $this->db = $db;
    }
    public function fetchData($technicianId, $managerId, $testId, $level) {
        $users = $this->db->getCollection('users');
        $technician = $users->findOne(['_id' => new ObjectId($technicianId), 'active' => true]);
        $manager = $users->findOne(['_id' => new ObjectId($managerId), 'active' => true]);
        if (!$technician || !$manager) return null;
        $tests = $this->db->getCollection('tests');
        $testData = $tests->findOne(['_id' => $testId, 'user' => new ObjectId($technicianId), 'type' => "Declaratif", 'level' => $level, 'active' => true]);
        if (!$testData) return null;
        $exams = $this->db->getCollection('exams');
        $exam = $exams->findOne(['user' => new ObjectId($technicianId), 'manager' => new ObjectId($managerId), 'test' => $testId, 'type' => "Manager", 'active' => true]);
        return [
            'technician' => $technician,
            'manager' => $manager,
            'test' => $testData,
            'exam' => $exam
        ];
    }
}

class ManagerQuizQuestionLoader {
    private $db;
    private $level;
    private $data;
    public function __construct($db, $level, $data) {
        $this->db = $db;
        $this->level = $level;
        $this->data = $data;
    }
    public function load() {
        $exam = $this->data['exam'] ?? null;
        $test = $this->data['test'];
        $allQuestions = [];
        if ($exam && !empty($exam['questions'])) {
            $allQuestions = $this->loadFromExam($exam);
        } else {
            $allQuestions = $this->loadFromTest($test);
        }
        if (empty($allQuestions)) {
            $this->db->jsonResponse(false, 'No questions found');
        }
        return $allQuestions;
    }
    private function loadFromExam($exam) {
        $questionsColl = $this->db->getCollection('questions');
        $savedAnswers = $exam['answers'] ?? [];
        $questionExam = $exam['questions'] ?? [];
        $allQuestions = [];
        $quizConfigs = ManagerQuizConstants::$SPECIALTIES;
        $fullToShort = array_flip($quizConfigs);
        foreach ($questionExam as $index => $questionId) {
            try {
                $questionIdStr = (string) $questionId;
                $question = $questionsColl->findOne([
                    '_id' => new ObjectId($questionIdStr),
                    'active' => true
                ]);
                if ($question) {
                    $full = $question['speciality'];
                    $short = $fullToShort[$full] ?? '';
                    $quizIdStr = $exam["quiz$short"] ?? null;
                    $allQuestions[] = [
                        'question' => $question,
                        'quizId' => $quizIdStr ? new ObjectId($quizIdStr) : null,
                        'specialty' => $full,
                        'answer' => $savedAnswers[$index] ?? 'null'
                    ];
                }
            } catch (Exception $e) {
                error_log("Error fetching question ID {$questionId}: " . $e->getMessage());
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
        if (empty($deQuizs)) return [];
        $quizIds = array_map(function ($q) { return new ObjectId((string)$q); }, $deQuizs);
        $relevantQuizzes = $this->db->getCollection('quizzes')->find([
            '_id' => ['$in' => $quizIds],
            'type' => "Declaratif",
            'level' => $this->level,
            'active' => true
        ])->toArray();
        $questionIds = [];
        foreach ($relevantQuizzes as $quiz) {
            foreach ($quiz['questions'] as $questionId) {
                $questionIds[] = new ObjectId((string)$questionId);
            }
        }
        $questionsColl = $this->db->getCollection('questions');
        $allQs = $questionsColl->find(['_id' => ['$in' => $questionIds], 'active' => true])->toArray();
        $allQsIndexed = [];
        foreach ($allQs as $q) {
            $allQsIndexed[(string)$q['_id']] = $q;
        }
        foreach ($relevantQuizzes as $quiz) {
            foreach ($quiz['questions'] as $qIdStr) {
                $qKey = (string)$qIdStr;
                if (isset($allQsIndexed[$qKey])) {
                    $allQuestions[] = [
                        'question' => $allQsIndexed[$qKey],
                        'quizId' => $quiz['_id'],
                        'specialty' => $quiz['speciality'],
                        'answer' => 'null'
                    ];
                }
            }
        }
        return $allQuestions;
    }
}

class ManagerQuizDataCollector {
    private $db;
    private $quizConfigs;
    public function __construct($db) {
        $this->db = $db;
        $this->quizConfigs = ManagerQuizConstants::$SPECIALTIES;
    }
    public function collect($allQuestions, $exam = null) {
        $quizData = [];
        if (!$exam) {
            $quizzesColl = $this->db->getCollection('quizzes');
            foreach ($allQuestions as $q) {
                $full = $q['specialty'];
                if (!isset($quizData[$full])) {
                    $quizIdStr = (string) $q['quizId'];
                    $quiz = $quizzesColl->findOne(['_id' => new ObjectId($quizIdStr), 'active' => true]);
                    if ($quiz) {
                        $quizData[$full] = ['id' => new ObjectId($quizIdStr), 'doc' => $quiz];
                    }
                }
            }
        } else {
            $quizzesColl = $this->db->getCollection('quizzes');
            foreach ($this->quizConfigs as $abbrev => $full) {
                $field = 'quiz' . $abbrev;
                if (isset($exam[$field]) && $exam[$field]) {
                    $qidStr = (string) $exam[$field];
                    $qid = new ObjectId($qidStr);
                    $quizDoc = $quizzesColl->findOne(['_id' => $qid, 'active' => true]);
                    if ($quizDoc) {
                        $quizData[$full] = ['id' => $qid, 'doc' => $quizDoc];
                    }
                }
            }
        }
        return $quizData;
    }
}

class ManagerQuizExamManager {
    private $db;
    private $technicianId;
    private $managerId;
    private $testId;
    private $quizConfigs;
    public function __construct($db, $technicianId, $managerId, $testId) {
        $this->db = $db;
        $this->technicianId = $technicianId;
        $this->managerId = $managerId;
        $this->testId = $testId;
        $this->quizConfigs = ManagerQuizConstants::$SPECIALTIES;
    }
    public function create($questionsTags, $answers, $hr, $mn, $sc, $total, $quizFields) {
        try {
            $examData = array_merge([
                'questions' => $questionsTags,
                'answers' => $answers,
                'user' => new ObjectId($this->technicianId),
                'manager' => new ObjectId($this->managerId),
                'test' => new ObjectId($this->testId),
                'hour' => $hr,
                'minute' => $mn,
                'second' => $sc,
                'total' => $total,
                'type' => 'Manager',
                'active' => true,
                'created' => date('d-m-Y H:i:s')
            ], $quizFields);
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
    public function buildQuizFields($allQuestions, $exam = null) {
        $quizFields = [];
        foreach ($this->quizConfigs as $abbrev => $full) {
            $quizFields['quiz' . $abbrev] = null;
        }
        if (!$exam) {
            $questionsByAbbrev = [];
            foreach ($allQuestions as $q) {
                $abbrev = array_search($q['specialty'], $this->quizConfigs);
                if ($abbrev !== false && $q['quizId']) {
                    if (!isset($questionsByAbbrev[$abbrev])) $questionsByAbbrev[$abbrev] = [];
                    $questionsByAbbrev[$abbrev][] = (string)$q['quizId'];
                }
            }
            foreach ($this->quizConfigs as $abbrev => $full) {
                if (isset($questionsByAbbrev[$abbrev]) && !empty($questionsByAbbrev[$abbrev])) {
                    $quizFields['quiz' . $abbrev] = $questionsByAbbrev[$abbrev][0];
                }
            }
        } else {
            foreach ($this->quizConfigs as $abbrev => $full) {
                $field = 'quiz' . $abbrev;
                $quizFields[$field] = $exam[$field] ?? null;
            }
        }
        return $quizFields;
    }
}

class ManagerQuizResultSaver {
    private $db;
    private $technicianId;
    private $managerId;
    private $testId;
    private $level;
    private $data;
    private $quizData;
    public function __construct($db, $technicianId, $managerId, $testId, $level, $data, $quizData) {
        $this->db = $db;
        $this->technicianId = $technicianId;
        $this->managerId = $managerId;
        $this->testId = $testId;
        $this->level = $level;
        $this->data = $data;
        $this->quizData = $quizData;
    }
    public function save($allQuestions, $userAnswer, $time) {
        global $il_maitrise;
        try {
            $quizConfigs = ManagerQuizConstants::$SPECIALTIES;
            $perQuizProposals = [];
            $perQuizScores = [];
            foreach ($quizConfigs as $abbrev => $full) {
                $postKey = 'quiz' . $abbrev;
                $perQuizProposals[$postKey] = [];
                $perQuizScores[$postKey] = [];
            }
            $globalScore = [];
            $globalProposal = [];
            $quizQuestion = [];
            $totalQuestions = count($allQuestions);
            $score = 0;
            $positiveStrings = [
                "Il maitrise (le technicien réalise cette tâche professionnelle)",
                "Il maîtrise (le technicien réalise cette tâche professionnelle)",
                "He masters (the technician performs this professional task)",
                "Il maîtrise",
                "Il maitrise",
                "He masters",
                "Je maitrise (je réalise cette tâche professionnelle seul)",
                "Je maîtrise (je réalise cette tâche professionnelle seul)",
                "I master (I perform this professional task alone)"
            ];
            if (isset($il_maitrise) && !empty($il_maitrise)) {
                $positiveStrings[] = $il_maitrise;
            }

            for ($i = 0; $i < $totalQuestions; $i++) {
                $qData = $allQuestions[$i];
                $question = $qData['question'];
                $userAns = $userAnswer[$i];
                $speciality = $qData['specialty'];
                
                $isCorrect = false;
                $normalizedAns = trim($userAns);
                foreach ($positiveStrings as $posStr) {
                    if (strcasecmp($normalizedAns, trim($posStr)) === 0) {
                        $isCorrect = true;
                        break;
                    }
                }
                
                if ($isCorrect) $score++;
                $abbrev = array_search($speciality, $quizConfigs);
                $postKey = 'quiz' . $abbrev;
                $perQuizProposals[$postKey][] = $isCorrect ? 'Oui' : 'Non';
                if ($isCorrect) {
                    $perQuizScores[$postKey][] = 'Il maitrise (le technicien réalise cette tâche professionnelle)';
                    $globalScore[] = 'Il maitrise (le technicien réalise cette tâche professionnelle)';
                }
                $globalProposal[] = $isCorrect ? 'Oui' : 'Non';
                $quizQuestion[] = new ObjectId((string)$question['_id']);
            }
            $results = $this->db->getCollection('results');
            // Insert per-quiz results
            foreach ($quizConfigs as $abbrev => $full) {
                $postKey = 'quiz' . $abbrev;
                if (isset($this->quizData[$full])) {
                    $quizId = $this->quizData[$full]['id'];
                    $quiz = $this->quizData[$full]['doc'];
                    $proposals = $perQuizProposals[$postKey];
                    $scoreCount = count($perQuizScores[$postKey]);
                    $exist = count($results->find([
                        'user' => new ObjectId($this->technicianId),
                        'manager' => new ObjectId($this->managerId),
                        'speciality' => $full,
                        'type' => $quiz['type'],
                        'typeR' => 'Manager',
                        'level' => $this->level
                    ])->toArray());
                    $numTest = $exist + 1;
                    $result = [
                        'questions' => $quiz['questions'],
                        'answers' => $proposals,
                        'quiz' => $quizId,
                        'user' => new ObjectId($this->technicianId),
                        'manager' => new ObjectId($this->managerId),
                        'score' => $scoreCount,
                        'speciality' => $full,
                        'level' => $this->level,
                        'type' => $quiz['type'],
                        'typeR' => 'Manager',
                        'total' => $quiz['total'],
                        'numberTest' => $numTest,
                        'time' => $time,
                        'active' => true,
                        'created' => date('d-m-Y H:i:s')
                    ];
                    $results->insertOne($result);
                }
            }
            // Global manager result
            $exist = count($results->find([
                'user' => new ObjectId($this->technicianId),
                'manager' => new ObjectId($this->managerId),
                'test' => new ObjectId($this->testId),
                'typeR' => 'Managers',
                'level' => $this->level
            ])->toArray());
            $numTest = $exist + 1;
            $managerAnswers = array_map(function($ua, $q) { return $q['type'] === 'Declarative' ? $ua : null; }, $userAnswer, $allQuestions);
            $managerAnswers = array_filter($managerAnswers);
            $newResult = [
                'questions' => $quizQuestion,
                'answers' => $globalProposal,
                'managerAnswers' => $managerAnswers,
                'user' => new ObjectId($this->technicianId),
                'test' => new ObjectId($this->testId),
                'manager' => new ObjectId($this->managerId),
                'score' => $score,
                'level' => $this->level,
                'type' => "Declaratif",
                'subsidiary' => $this->data['technician']['subsidiary'],
                'typeR' => 'Managers',
                'total' => count($quizQuestion),
                'numberTest' => $numTest,
                'time' => $time,
                'active' => true,
                'created' => date('d-m-Y H:i:s')
            ];
            $resultM = $results->insertOne($newResult);
            $manaResult = $results->findOne(['_id' => $resultM->getInsertedId()]);
            // Update allocation
            $alloc = $this->db->getCollection('allocations')->findOne([
                'user' => new ObjectId($this->technicianId),
                'test' => new ObjectId($this->testId)
            ]);
            if ($alloc) {
                $this->db->getCollection('allocations')->updateOne(
                    ['_id' => $alloc->_id],
                    ['$set' => ['activeManager' => true]]
                );
            }
            // Comparison
            $technicienResult = $results->findOne([
                'user' => new ObjectId($this->technicianId),
                'typeR' => 'Techniciens',
                'numberTest' => $manaResult['numberTest'],
                'level' => $this->level
            ]);
            if ($technicienResult) {
                $scoreF = 0;
                foreach ($globalProposal as $i => $p) {
                    if ($p === "Oui" && $technicienResult['answers'][$i] === "Oui") $scoreF++;
                }
                $tmExist = count($results->find([
                    'user' => new ObjectId($this->technicianId),
                    'manager' => new ObjectId($this->managerId),
                    'test' => new ObjectId($this->testId),
                    'typeR' => 'Technicien - Manager',
                    'level' => $this->level
                ])->toArray());
                $tmNumTest = $tmExist + 1;
                $tmResult = [
                    'user' => new ObjectId($this->technicianId),
                    'manager' => new ObjectId($this->managerId),
                    'test' => new ObjectId($this->testId),
                    'score' => $scoreF,
                    'level' => $this->level,
                    'type' => 'Declaratif',
                    'subsidiary' => $this->data['technician']['subsidiary'],
                    'typeR' => 'Technicien - Manager',
                    'total' => count($quizQuestion),
                    'numberTest' => $tmNumTest,
                    'active' => true,
                    'created' => date('d-m-Y H:i:s')
                ];
                $results->insertOne($tmResult);
            }
            $this->sendNotification($manaResult);
            return true;
        } catch (Exception $e) {
            error_log("Error saving results: " . $e->getMessage());
            return false;
        }
    }
    private function sendNotification($manaResult) {
        try {
            $technician = $this->data['technician'];
            $manager = $this->data['manager'];
            if (!isset($technician['firstName'], $technician['lastName'], $this->level, $manager['firstName'], $manager['lastName'], $manager['email'])) return;
            $client = $this->db->getClient();
            $adminDocs = getAdminsDocs($technician, $client);
            if (!$adminDocs) return;
            $rhDpsEmails = getRhAndDpsEmails($technician['subsidiary'], $client);
            $isRwanda = $technician['subsidiary'] === "CFAO MOTORS RWANDA";
            $subject = sprintf(
                $isRwanda ? 'Professional Task MCQ finalized by Manager %s %s for collaborator %s %s – Level %s' : 'QCM Tâche Professionnelle finalisé par le Manageur %s %s pour son collaborateur %s %s – Niveau %s',
                htmlspecialchars($manager['firstName']), htmlspecialchars($manager['lastName']),
                htmlspecialchars($technician['firstName']), htmlspecialchars($technician['lastName']),
                htmlspecialchars($this->level)
            );
            // Pas de salutation dans le body : generateEmailTemplate ajoute déjà "Bonjour {M./Mme NOM},"
            $body = sprintf(
                $isRwanda ?
                    "<p>Manager <strong>%s&nbsp;%s</strong> has just finalized the <strong>Professional Task MCQ</strong> for their collaborator <strong>%s&nbsp;%s</strong> at level <strong>%s</strong>. You can view the result in the <em>Technician Results by Level</em> section of the platform.</p>" :
                    "<p>Le Manageur <strong>%s&nbsp;%s</strong> vient de finaliser le <strong>QCM Tâche Professionnelle</strong> de son collaborateur <strong>%s&nbsp;%s</strong> au niveau <strong>%s</strong>. Vous pouvez consulter le résultat dans l'espace <em>Résultats des Techniciens par niveau</em> sur la plateforme.</p>",
                htmlspecialchars($manager['firstName']), htmlspecialchars($manager['lastName']),
                htmlspecialchars($technician['firstName']), htmlspecialchars($technician['lastName']),
                htmlspecialchars($this->level)
            );
            sendMailQcmFinalized($adminDocs, $subject, $body, $manager['email'], $rhDpsEmails);
        } catch (Exception $e) {
            error_log("Error sending notification email: " . $e->getMessage());
        }
    }
}

class ManagerQuizPostHandler {
    private $db;
    private $technicianId;
    private $managerId;
    private $testId;
    private $level;
    private $data;
    private $allQuestions;
    private $examManager;
    private $resultSaver;
    private $quizData;
    public function __construct($db, $technicianId, $managerId, $testId, $level, $data, $allQuestions, $quizData) {
        $this->db = $db;
        $this->technicianId = $technicianId;
        $this->managerId = $managerId;
        $this->testId = $testId;
        $this->level = $level;
        $this->data = $data;
        $this->allQuestions = $allQuestions;
        $this->quizData = $quizData;
        $this->examManager = new ManagerQuizExamManager($db, $technicianId, $managerId, $testId);
        $this->resultSaver = new ManagerQuizResultSaver($db, $technicianId, $managerId, $testId, $level, $data, $quizData);
    }
    public function handle($action) {
        $totalQuestions = count($this->allQuestions);
        $exam = $this->data['exam'] ?? null;
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
                return new ObjectId((string)$q['question']['_id']);
            }, $this->allQuestions);
            $totalAnswered = count(array_filter($formattedAnswers, function($a) {
                return $a !== 'null';
            }));
            $quizFields = $this->examManager->buildQuizFields($this->allQuestions, $exam);
            $success = $exam
                ? $this->examManager->update($exam, $formattedAnswers, $hr, $mn, $sc, $totalAnswered)
                : $this->examManager->create($questionsTags, $formattedAnswers, $hr, $mn, $sc, $totalAnswered, $quizFields);
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
            if (in_array('null', $userAnswer)) {
                $unansweredCount = count(array_filter($userAnswer, function($a) {
                    return $a === 'null';
                }));
                $this->db->jsonResponse(false, "Vous n'avez pas répondu à " . $unansweredCount . " question(s), Veuillez vérifier la ou les question(s) dont valider est en vert svp.");
            }
            $this->resultSaver->save($this->allQuestions, $userAnswer, $_POST['timer'] ?? '00:00:00');
            $this->examManager->deactivate($exam);
            $this->db->jsonResponse(true, 'Evaluation completed', './congrat');
        } catch (Exception $e) {
            error_log("Error in submit: " . $e->getMessage());
            $this->db->jsonResponse(false, 'Server error during submission');
        }
    }
}

// Bootstrap
ManagerQuizValidator::validateSession();
$params = [
    'technician' => $_GET['user'] ?? '',
    'manager' => $_GET['id'] ?? '',
    'level' => $_GET['level'] ?? '',
    'test' => $_GET['test'] ?? '',
    'guide' => $_GET['guide'] ?? false,
    'debug' => filter_var($_GET['debug'] ?? '0', FILTER_VALIDATE_BOOLEAN)
];
ManagerQuizValidator::validateParams($params);
$db = new MongoDBConnection();
// Seul le manager connecté évalue, et uniquement ses collaborateurs : ?id= et ?user= ne sont pas des preuves.
if (($_SESSION['profile'] ?? '') !== 'Super Admin') {
    $evalTech = $db->getCollection('users')->findOne(['_id' => new ObjectId($params['technician'])]);
    if ((string) $params['manager'] !== (string) $_SESSION['id']
        || !$evalTech || empty($evalTech['manager']) || (string) $evalTech['manager'] !== (string) $_SESSION['id']) {
        error_log('[userEvaluation] accès refusé : session=' . $_SESSION['id'] . ' manager=' . $params['manager'] . ' tech=' . $params['technician']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db->jsonResponse(false, 'Accès refusé.', './userManagerList');
        }
        header('Location: ./userManagerList');
        exit();
    }
}
// Une évaluation terminée ne se repasse pas : sinon une nouvelle soumission duplique les résultats.
if ($db->getCollection('allocations')->findOne(['user' => new ObjectId($params['technician']), 'test' => new ObjectId($params['test']), 'type' => 'Declaratif', 'activeManager' => true])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->jsonResponse(false, 'Cette évaluation est déjà terminée.', './userManagerList');
    }
    header('Location: ./userManagerList');
    exit();
}
$fetcher = new ManagerQuizDataFetcher($db);
$data = $fetcher->fetchData($params['technician'], $params['manager'], new ObjectId($params['test']), $params['level']);
if (!$data) {
    $db->jsonResponse(false, 'Technician, manager or test not found');
}
$loader = new ManagerQuizQuestionLoader($db, $params['level'], $data);
$allQuestions = $loader->load();
$quizCollector = new ManagerQuizDataCollector($db);
$quizData = $quizCollector->collect($allQuestions, $data['exam']);
$totalQuestions = count($allQuestions);
$technician = $data['technician'];
$exam = $data['exam'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $handler = new ManagerQuizPostHandler($db, $params['technician'], $params['manager'], new ObjectId($params['test']), $params['level'], $data, $allQuestions, $quizData);
    $handler->handle($_POST['action']);
}
$labels = [
    'il_maitrise' => isset($il_maitrise) ? $il_maitrise : 'Il maîtrise',
    'il_ne_maitrise_pas' => isset($il_ne_maitrise_pas) ? $il_ne_maitrise_pas : 'Il ne maîtrise pas',
    'il_na_jamais_fait' => isset($il_na_jamais_fait) ? $il_na_jamais_fait : 'Il n\'a jamais fait'
];
?>
<?php include_once "./partials/header.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($evaluation_de) ? $evaluation_de : 'Évaluation de') . ' ' . htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?> | CFAO Mobility Academy</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/quiz.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css">
<?php
renderQuizComponent($allQuestions, $technician, $params, $exam, isset($evaluer_tache_pro) ? $evaluer_tache_pro : 'Évaluation Tâche Professionnelle', isset($level_label) ? $level_label : 'Niveau', 'declaratif', $labels);
?>
<?php include_once "partials/footer.php"; ?>
<?php include_once "chat.php"; ?>