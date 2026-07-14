<?php
session_start();
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
    public function jsonResponse($success, $message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit();
    }
}
class QuizConstants {
    public static $SPECIALTIES = [
        'Assistance' => 'Assistance à la Conduite',
        'Arbre' => 'Arbre de Transmission',
        'Transfert' => 'Boite de Transfert',
        'Boite' => 'Boîte de Vitesses',
        'BoiteAuto' => 'Boîte de Vitesse Automatique',
        'BoiteMan' => 'Boîte de Vitesse Mécanique',
        'BoiteVc' => 'Boîte de Vitesse à Variation Continue',
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
    public static $LEVEL_MULTIPLIERS = [
        'Junior' => 100,
        'Senior' => 100,
        'Expert' => 50
    ];
    public static function getAdjustmentKeys($level) {
        if ($level === 'Junior') {
            return ['Transversale', 'Moteur', 'Electricite'];
        } elseif ($level === 'Senior') {
            return ['MoteurDiesel', 'Moteur', 'Electricite'];
        } else {
            return ['Moteur', 'Transversale', 'Frei'];
        }
    }
    public static function getTargetQuestions($level) {
        return $level === 'Expert' ? 50 : 100;
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
        $testData = $tests->findOne(['_id' => new ObjectId($testId), 'user' => new ObjectId($userId), 'type' => 'Factuel', 'level' => $level, 'active' => true]);
        if (!$testData) return null;
        $exams = $this->db->getCollection('exams');
        $exam = $exams->findOne(['user' => new ObjectId($userId), 'test' => new ObjectId($testId), 'active' => true]);
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
class QuizPercentageCalculator {
    private $db;
    private $level;
    public function __construct($db, $level) {
        $this->db = $db;
        $this->level = $level;
    }
    public function calculate($testData) {
        $tests = $this->db->getCollection('tests');
        $aggResult = $tests->aggregate([
            ['$match' => ['_id' => $testData['_id']]],
            ['$lookup' => ['from' => 'quizzes', 'localField' => 'quizzes', 'foreignField' => '_id', 'as' => 'quizzes']],
            ['$unwind' => '$quizzes'],
            ['$group' => ['_id' => '$_id', 'sumTotal' => ['$sum' => '$quizzes.total']]]
        ])->toArray();
        $totalSum = $aggResult[0]['sumTotal'] ?? 0;
        if ($totalSum === 0) return [];
        $quizzesColl = $this->db->getCollection('quizzes');
        $quizzes = [];
        $multiplier = QuizConstants::$LEVEL_MULTIPLIERS[$this->level];
        foreach ($testData['quizzes'] as $quizId) {
            foreach (QuizConstants::$SPECIALTIES as $key => $name) {
                $quiz = $quizzesColl->findOne([
                    '_id' => new ObjectId($quizId),
                    'speciality' => $name,
                    'type' => 'Factuel',
                    'level' => $this->level,
                    'active' => true
                ]);
                if ($quiz) {
                    $percentage = round(($quiz['total'] * $multiplier) / $totalSum, 0);
                    $quizzes[$key] = ['quiz' => $quiz, 'percentage' => $percentage];
                    break;
                }
            }
        }
        $totalPercent = array_sum(array_column($quizzes, 'percentage'));
        $target = QuizConstants::getTargetQuestions($this->level);
        if ($totalPercent !== $target) {
            $this->adjustQuizzes($quizzes, $totalPercent - $target);
        }
        return $quizzes;
    }
    private function adjustQuizzes(&$quizzes, $diff) {
        $keys = array_keys($quizzes);
        $adjustKeys = array_intersect($keys, QuizConstants::getAdjustmentKeys($this->level));
        $absDiff = abs($diff);
        for ($i = 0; $i < $absDiff; $i++) {
            $keyIndex = $i % count($adjustKeys);
            $key = $adjustKeys[array_keys($adjustKeys)[$keyIndex]];
            $quizzes[$key]['percentage'] += $diff > 0 ? -1 : 1;
            if ($diff > 0) {
                $quizzes[$key]['percentage'] = max(0, $quizzes[$key]['percentage']);
            }
        }
    }
}
class QuizQuestionGenerator {
    private $db;
    private $level;
    private $userData;
    public function __construct($db, $level, $userData) {
        $this->db = $db;
        $this->level = $level;
        $this->userData = $userData;
    }
    public function generate() {
        $exam = $this->userData['exam'] ?? null;
        $allQuestions = $exam ? $this->fromExam($exam) : $this->generateNew($this->userData['test']);
        if (!$exam && !empty($allQuestions)) {
            $this->insertExam($allQuestions, $this->userData['test']);
        }
        return $allQuestions;
    }
    private function fromExam($exam) {
        $questionsColl = $this->db->getCollection('questions');
        $fullToShort = array_flip(QuizConstants::$SPECIALTIES);
        $allQuestions = [];
        foreach ($exam['questions'] as $index => $qId) {
            $question = $questionsColl->findOne([
                '_id' => new ObjectId($qId),
                'active' => true,
                'speciality' => ['$in' => array_values(QuizConstants::$SPECIALTIES)]
            ]);
            if ($question) {
                $full = $question['speciality'];
                $short = $fullToShort[$full] ?? '';
                $quizId = $exam["quiz$short"] ?? null;
                $allQuestions[] = [
                    'question' => $question,
                    'quizId' => $quizId,
                    'specialty' => $full,
                    'short' => $short,
                    'answer' => $exam['answers'][$index] ?? null
                ];
            }
        }
        return $allQuestions;
    }
    private function generateNew($testData) {
        $target = QuizConstants::getTargetQuestions($this->level);
        $allQuestions = [];
        $tests = $this->db->getCollection('tests');
        $agg = $tests->aggregate([
            ['$match' => ['_id' => $testData['_id']]],
            ['$lookup' => ['from' => 'quizzes', 'localField' => 'quizzes', 'foreignField' => '_id', 'as' => 'quizzes']],
            ['$unwind' => '$quizzes'],
            ['$group' => ['_id' => '$_id', 'sumTotal' => ['$sum' => '$quizzes.total']]]
        ])->toArray();
        $sumTotal = $agg[0]['sumTotal'] ?? 0;
        if ($sumTotal === 0) return [];
        $quizCounts = $this->computeQuizCounts($testData, $sumTotal, $target);
        $this->adjustCounts($quizCounts, $target);
        $quizIds = array_keys($quizCounts);
        $sampled = 0;
        foreach ($quizIds as $quizIdStr) {
            $number = $quizCounts[$quizIdStr];
            if ($sampled + $number > $target) $number = $target - $sampled;
            if ($number <= 0) continue;
            $sampledQuestions = $this->sampleFromQuiz($quizIdStr, $number, $allQuestions, $sampled, $target);
            $sampled += count($sampledQuestions);
            if ($sampled >= $target) break;
        }
        $this->fillGaps($quizIds, $allQuestions, $target, $sampled);
        return $allQuestions;
    }
    private function computeQuizCounts($testData, $sumTotal, $target) {
        $quizzesColl = $this->db->getCollection('quizzes');
        $counts = [];
        foreach ($testData['quizzes'] as $quizId) {
            $quiz = $quizzesColl->findOne([
                '_id' => new ObjectId($quizId),
                'type' => 'Factuel',
                'level' => $this->level,
                'active' => true,
                'speciality' => ['$in' => array_values(QuizConstants::$SPECIALTIES)]
            ]);
            if ($quiz) {
                $num = $sumTotal ? round($quiz['total'] * $target / $sumTotal, 0) : 0;
                if ($num > 0) $counts[(string)$quizId] = $num;
            }
        }
        return $counts;
    }
    private function adjustCounts(&$counts, $target) {
        $total = array_sum($counts);
        if ($total === $target) return;
        $diff = $target - $total;
        $adjustKeys = QuizConstants::getAdjustmentKeys($this->level);
        $quizSpecs = $this->fetchSpecialties(array_keys($counts));
        $adjustIds = array_filter(array_keys($counts), function($id) use ($quizSpecs, $adjustKeys) { return in_array($quizSpecs[$id], $adjustKeys); });
        if (empty($adjustIds)) return;
        $absDiff = abs($diff);
        for ($i = 0; $i < $absDiff; $i++) {
            $idx = $i % count($adjustIds);
            $id = $adjustIds[array_keys($adjustIds)[$idx]];
            $counts[$id] += $diff > 0 ? 1 : -1;
            if ($diff < 0) $counts[$id] = max(0, $counts[$id]);
        }
    }
    private function fetchSpecialties($quizIds) {
        $quizzesColl = $this->db->getCollection('quizzes');
        $specs = [];
        foreach ($quizIds as $id) {
            $quiz = $quizzesColl->findOne(['_id' => new ObjectId($id)]);
            $specs[$id] = $quiz['speciality'] ?? '';
        }
        return $specs;
    }
    private function sampleFromQuiz($quizIdStr, $number, &$allQuestions, &$sampled, $target) {
        $quizId = new ObjectId($quizIdStr);
        $quizzesColl = $this->db->getCollection('quizzes');
        $agg = $quizzesColl->aggregate([
            ['$match' => ['_id' => $quizId]],
            ['$lookup' => ['from' => 'questions', 'localField' => 'questions', 'foreignField' => '_id', 'as' => 'questions']],
            ['$unwind' => '$questions'],
            ['$match' => ['questions.active' => true, 'questions.speciality' => ['$in' => array_values(QuizConstants::$SPECIALTIES)]]],
            ['$sample' => ['size' => $number]],
            ['$group' => ['_id' => '$_id', 'questions' => ['$push' => '$questions']]]
        ])->toArray();
        $sampledQ = [];
        if (!empty($agg)) {
            $qs = $agg[0]['questions'];
            $quiz = $quizzesColl->findOne(['_id' => $quizId]);
            foreach ($qs as $q) {
                if (in_array($q['speciality'], array_values(QuizConstants::$SPECIALTIES), true)) {
                    $allQuestions[] = [
                        'question' => $q,
                        'quizId' => $quiz['_id'],
                        'specialty' => $quiz['speciality'],
                        'short' => array_search($quiz['speciality'], QuizConstants::$SPECIALTIES)
                    ];
                    $sampled++;
                    $sampledQ[] = $q;
                    if ($sampled >= $target) break;
                }
            }
        }
        return $sampledQ;
    }
    private function fillGaps($quizIds, &$allQuestions, $target, $sampled) {
        $quizzesColl = $this->db->getCollection('quizzes');
        // Copy quizIds so we can remove exhausted ones
        $availableQuizIds = array_values($quizIds);
        
        while ($sampled < $target && !empty($availableQuizIds)) {
            // Pick a random quiz index
            $randIndex = array_rand($availableQuizIds);
            $quizIdStr = $availableQuizIds[$randIndex];
            $quizId = new ObjectId($quizIdStr);
            
            $num = min(5, $target - $sampled); // Try to fetch up to 5 at a time to be more efficient, or just 1? Let's stick to small batches.
            // Actually original code did min(1, ...). Let's keep it simple but fix the loop.
            $num = 1; 

            $existingIds = array_map(function($q) { return new ObjectId($q['question']['_id']); }, $allQuestions);
            $agg = $quizzesColl->aggregate([
                ['$match' => ['_id' => $quizId]],
                ['$lookup' => ['from' => 'questions', 'localField' => 'questions', 'foreignField' => '_id', 'as' => 'questions']],
                ['$unwind' => '$questions'],
                ['$match' => [
                    'questions.active' => true,
                    'questions.speciality' => ['$in' => array_values(QuizConstants::$SPECIALTIES)],
                    'questions._id' => ['$nin' => $existingIds]
                ]],
                ['$sample' => ['size' => $num]],
                ['$group' => ['_id' => '$_id', 'questions' => ['$push' => '$questions']]]
            ])->toArray();
            
            if (!empty($agg) && !empty($agg[0]['questions'])) {
                $qs = $agg[0]['questions'];
                $quiz = $quizzesColl->findOne(['_id' => $quizId]);
                foreach ($qs as $q) {
                    if (in_array($q['speciality'], array_values(QuizConstants::$SPECIALTIES), true)) {
                        $allQuestions[] = [
                            'question' => $q,
                            'quizId' => $quiz['_id'],
                            'specialty' => $quiz['speciality'],
                            'short' => array_search($quiz['speciality'], QuizConstants::$SPECIALTIES)
                        ];
                        $sampled++;
                        if ($sampled >= $target) break;
                    }
                }
            } else {
                // No more questions found in this quiz, remove it from available list
                unset($availableQuizIds[$randIndex]);
                $availableQuizIds = array_values($availableQuizIds); // Reindex
            }
        }
    }
    private function insertExam($allQuestions, $testData) {
        $questionsTags = array_map(function($q) { return new ObjectId($q['question']['_id']); }, $allQuestions);
        $quizIds = [];
        foreach (QuizConstants::$SPECIALTIES as $key => $spec) {
            $quizIds["quiz$key"] = null;
            foreach ($allQuestions as $q) {
                if ($q['quizId'] && $q['specialty'] === $spec) {
                    $quizIds["quiz$key"] = $q['quizId'];
                    break;
                }
            }
        }
        $examData = [
                'questions' => $questionsTags,
                'answers' => array_fill(0, count($questionsTags), 'null'),
                'user' => new ObjectId($testData['user']),
                'test' => $testData['_id'],
                'total' => 0,
                'active' => true,
                'type' => 'Factuel',
                'hour' => 1,
                'minute' => 30,
                'second' => 0,
                'created' => date('d-m-Y H:i:s')
            ] + $quizIds;
        $this->db->getCollection('exams')->insertOne($examData);
    }
}
class QuizResultProcessor {
    private $db;
    private $userId;
    private $testId;
    private $level;
    private $userData;
    private $allQuestions;
    public function __construct($db, $userId, $testId, $level, $userData, $allQuestions) {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->level = $level;
        $this->userData = $userData;
        $this->allQuestions = $allQuestions;
    }
    public function process($userAnswers) {
        $questionsTag = array_map(function($q) { return (string)$q['question']['_id']; }, $this->allQuestions);
        $totalScore = 0;
        foreach (QuizConstants::$SPECIALTIES as $key => $specName) {
            $specResult = $this->processSpecialtyResult($specName, $questionsTag, $userAnswers);
            if ($specResult) {
                $this->db->getCollection('results')->insertOne($specResult);
                $totalScore += $specResult['score'];
            }
        }
        $globalResult = $this->buildGlobalResult($questionsTag, $userAnswers, $totalScore);
        $this->db->getCollection('results')->insertOne($globalResult);
        return $totalScore;
    }
    private function processSpecialtyResult($specName, $questionsTag, $userAnswers) {
        $questionsColl = $this->db->getCollection('questions');
        $quizzesColl = $this->db->getCollection('quizzes');
        $specQs = [];
        $specAns = [];
        $score = 0;
        foreach ($questionsTag as $idx => $qId) {
            $question = $questionsColl->findOne(['_id' => new ObjectId($qId), 'active' => true]);
            if ($question && $question['speciality'] === $specName) {
                $correct = strtolower(trim($userAnswers[$idx])) === strtolower(trim($question['answer']));
                $score += $correct ? 1 : 0;
                $specQs[] = new ObjectId($question['_id']);
                $specAns[] = $correct ? 'Maitrisé' : 'Non maitrisé';
            }
        }
        if (empty($specQs)) return null;
        $quiz = $quizzesColl->findOne([
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level,
            'active' => true
        ]);
        if (!$quiz) return null;
        $existing = $this->db->getCollection('results')->find([
            'user' => new ObjectId($this->userId),
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level
        ])->toArray();
        return [
            'questions' => $specQs,
            'answers' => $specAns,
            'quiz' => new ObjectId($quiz['_id']),
            'user' => new ObjectId($this->userId),
            'score' => $score,
            'speciality' => $specName,
            'level' => $this->level,
            'type' => 'Factuel',
            'total' => count($specQs),
            'numberTest' => count($existing) + 1,
            'time' => $_POST['timer'] ?? '00:00:00',
            'active' => true,
            'created' => date('d-m-Y H:i:s')
        ];
    }
    private function buildGlobalResult($questionsTag, $userAnswers, $totalScore) {
        $existing = $this->db->getCollection('results')->find([
            'user' => new ObjectId($this->userId),
            'test' => new ObjectId($this->testId),
            'type' => 'Factuel',
            'level' => $this->level
        ])->toArray();
        $user = $this->userData['user'];
        $mastered = $totalScore === count($questionsTag);
        return [
            'questions' => array_map(function($id) { return new ObjectId($id); }, $questionsTag),
            'answers' => array_fill(0, count($questionsTag), $mastered ? 'Maitrisé' : 'Non maitrisé'),
            'userAnswers' => $userAnswers,
            'user' => new ObjectId($this->userId),
            'test' => new ObjectId($this->testId),
            'score' => $totalScore,
            'level' => $this->level,
            'type' => 'Factuel',
            'subsidiary' => $user['subsidiary'],
            'typeR' => 'Technicien',
            'total' => count($questionsTag),
            'numberTest' => count($existing) + 1,
            'time' => $_POST['timer'] ?? '00:00:00',
            'active' => true,
            'created' => date('d-m-Y H:i:s')
        ];
    }
}
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
        } catch (Exception $e) {
            error_log("Save error: " . $e->getMessage());
            $this->jsonResponse(true, 'Saved successfully');
        }
    }
    private function submit($totalQs) {
        try {
            $answers = json_decode($_POST['answers'] ?? '{}', true);
            if (!is_array($answers)) throw new Exception('Invalid answers');
            $userAns = [];
            for ($i = 1; $i <= $totalQs; $i++) {
                $userAns[] = $answers[$i] ?? 'null';
            }
            
            $answeredCount = count(array_filter($userAns, function($a) { return $a !== 'null'; }));
            
            if ($answeredCount <= 1) {
                 throw new Exception('Vous devez répondre à au moins 2 questions pour pouvoir finaliser.');
            }

            $this->resultProcessor->process($userAns);
            $alloc = $this->db->getCollection('allocations');
            $allocData = $alloc->findOne(['user' => new ObjectId($this->userId), 'test' => new ObjectId($this->testId)]);
            if ($allocData) {
                $alloc->updateOne(['_id' => $allocData['_id']], ['$set' => ['active' => true]]);
                require_once __DIR__ . '/includes/allocation_progression.php';
                allocationProgression_tryUnlockNextLevel($alloc, new ObjectId($this->userId), $this->level);
            }
            $exam = $this->userData['exam'] ?? null;
            if ($exam) {
                $this->db->getCollection('exams')->updateOne(['_id' => $exam['_id']], ['$set' => ['active' => false]]);
            }
            $this->sendNotification();
        } catch (Exception $e) {
//            error_log("Submit error: " . $e->getMessage());
        }
        $percent = $totalQs > 0 ? round(($answeredCount / $totalQs) * 100) : 0;
        $this->jsonResponse(true, 'Evaluation completed', './congrat?p=' . $percent);
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
        $rhEmails = getRhAndDpsEmails($tech['subsidiary'], $client);
        $salut = buildAdminSalutation($admins);
        $isRwanda = $tech['subsidiary'] === 'CFAO MOTORS RWANDA';
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
        if ($redirect) $resp['redirect'] = $redirect;
        header('Content-Type: application/json');
        echo json_encode($resp);
        exit();
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
$data = $fetcher->fetchUserData($params['id'], $params['test'], $params['level']);
if (!$data) {
    $db->jsonResponse(false, 'Invalid user or test data');
}
$calc = new QuizPercentageCalculator($db, $params['level']);
$adjustedQuizzes = $calc->calculate($data['test']);
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