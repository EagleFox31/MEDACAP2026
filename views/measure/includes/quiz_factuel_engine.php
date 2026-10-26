<?php
/**
 * Quiz factuel: données, génération de questions et calcul des résultats.
 * Code historique récupéré depuis le commit d9e8441 (avant extraction des classes).
 * Conserve l'usage de medacapCountDocuments().
 */
use MongoDB\BSON\ObjectId;

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
        if (!$exam && !empty($allQuestions) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
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
        $quizzesColl = $this->db->getCollection('quizzes');
        $resultsColl = $this->db->getCollection('results');
        $specQs = [];
        $specAns = [];
        $score = 0;
        foreach ($this->allQuestions as $idx => $item) {
            if (($item['specialty'] ?? '') !== $specName) {
                continue;
            }
            $question = $item['question'];
            $userVal = isset($userAnswers[$idx]) ? $userAnswers[$idx] : 'null';
            $expected = isset($question['answer']) ? (string) $question['answer'] : '';
            $correct = ($userVal !== 'null')
                && (strtolower(trim((string) $userVal)) === strtolower(trim($expected)));
            $score += $correct ? 1 : 0;
            $specQs[] = new ObjectId($question['_id']);
            $specAns[] = $correct ? 'Maitrisé' : 'Non maitrisé';
        }
        if (empty($specQs)) {
            return null;
        }
        $quiz = $quizzesColl->findOne([
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level,
            'active' => true
        ]);
        if (!$quiz) {
            return null;
        }
        $numberTest = medacapCountDocuments($resultsColl, [
            'user' => new ObjectId($this->userId),
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level
        ]) + 1;
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
            'numberTest' => $numberTest,
            'time' => $_POST['timer'] ?? '00:00:00',
            'active' => true,
            'created' => date('d-m-Y H:i:s')
        ];
    }
    private function buildGlobalResult($questionsTag, $userAnswers, $totalScore) {
        $numberTest = medacapCountDocuments($this->db->getCollection('results'), [
            'user' => new ObjectId($this->userId),
            'test' => new ObjectId($this->testId),
            'type' => 'Factuel',
            'level' => $this->level
        ]) + 1;
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
            'numberTest' => $numberTest,
            'time' => $_POST['timer'] ?? '00:00:00',
            'active' => true,
            'created' => date('d-m-Y H:i:s')
        ];
    }
}
