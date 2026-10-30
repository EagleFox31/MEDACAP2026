<?php
/**
 * Moteur QCM Factuel partagé (web + CLI offline export/import).
 * PHP 7.3 — require mongo_compat.php avant usage de QuizResultProcessor.
 */
use MongoDB\BSON\ObjectId;

if (!function_exists('quizFactuelDescribeThrowable')) {
    function quizFactuelDescribeThrowable(Throwable $e)
    {
        return get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

class QuizConstants
{
    public static $SPECIALTIES = array(
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
        'Transversale' => 'Transversale',
    );

    public static $LEVEL_MULTIPLIERS = array(
        'Junior' => 100,
        'Senior' => 100,
        'Expert' => 50,
    );

    public static function getAdjustmentKeys($level)
    {
        if ($level === 'Junior') {
            return array('Transversale', 'Moteur', 'Electricite');
        }
        if ($level === 'Senior') {
            return array('MoteurDiesel', 'Moteur', 'Electricite');
        }
        return array('Moteur', 'Transversale', 'Frei');
    }

    public static function getTargetQuestions($level)
    {
        return $level === 'Expert' ? 50 : 100;
    }
}

class QuizDataFetcher
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function fetchUserData($userId, $testId, $level)
    {
        $users = $this->db->getCollection('users');
        $user = $users->findOne(array('_id' => new ObjectId($userId), 'active' => true));
        if (!$user) {
            return null;
        }
        $tests = $this->db->getCollection('tests');
        $testData = $tests->findOne(array(
            '_id' => new ObjectId($testId),
            'user' => new ObjectId($userId),
            'type' => 'Factuel',
            'level' => $level,
            'active' => true,
        ));
        if (!$testData) {
            return null;
        }
        $exams = $this->db->getCollection('exams');
        $exam = $exams->findOne(array(
            'user' => new ObjectId($userId),
            'test' => new ObjectId($testId),
            'active' => true,
        ));
        $manager = $this->fetchManager($users, $user);
        return array(
            'user' => $user,
            'test' => $testData,
            'exam' => $exam,
            'manager' => $manager,
        );
    }

    private function fetchManager($users, $user)
    {
        if (empty($user['manager'])) {
            return null;
        }
        try {
            return $users->findOne(array('_id' => new ObjectId($user['manager']), 'active' => true));
        } catch (Exception $e) {
            error_log('Manager fetch error: ' . $e->getMessage());
            return null;
        }
    }
}

class QuizQuestionGenerator
{
    private $db;
    private $level;
    private $userData;

    public function __construct($db, $level, $userData)
    {
        $this->db = $db;
        $this->level = $level;
        $this->userData = $userData;
    }

    /** Génération web (crée un exam si nouvelle session GET). */
    public function generate()
    {
        $exam = isset($this->userData['exam']) ? $this->userData['exam'] : null;
        $allQuestions = $exam ? $this->fromExam($exam) : $this->generateNew($this->userData['test']);
        if (!$exam && !empty($allQuestions) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
            $this->insertExam($allQuestions, $this->userData['test']);
        }
        return $allQuestions;
    }

    /** Export offline — ne crée pas d'exam en base. */
    public function generateForExport()
    {
        $exam = isset($this->userData['exam']) ? $this->userData['exam'] : null;
        if ($exam) {
            return $this->fromExam($exam);
        }
        return $this->generateNew($this->userData['test']);
    }

    /**
     * Socle offline — fige le tirage en base.
     *
     * Le jeton de retour ne transporte que des réponses : c'est l'exam persisté
     * ici qui dit quelles questions ont été posées, et dans quel ordre. Sans
     * cette persistance, un second tirage donnerait un autre jeu de questions
     * et la correction porterait sur les mauvaises.
     */
    public function generateAndPersist()
    {
        $exam = isset($this->userData['exam']) ? $this->userData['exam'] : null;
        if ($exam) {
            return $this->fromExam($exam);
        }
        $allQuestions = $this->generateNew($this->userData['test']);
        if (!empty($allQuestions)) {
            $this->insertExam($allQuestions, $this->userData['test']);
        }
        return $allQuestions;
    }

    private function fromExam($exam)
    {
        $questionsColl = $this->db->getCollection('questions');
        $fullToShort = array_flip(QuizConstants::$SPECIALTIES);
        $allQuestions = array();
        foreach ($exam['questions'] as $index => $qId) {
            $question = $questionsColl->findOne(array(
                '_id' => new ObjectId($qId),
                'active' => true,
                'speciality' => array('$in' => array_values(QuizConstants::$SPECIALTIES)),
            ));
            if ($question) {
                $full = $question['speciality'];
                $short = isset($fullToShort[$full]) ? $fullToShort[$full] : '';
                $quizId = isset($exam['quiz' . $short]) ? $exam['quiz' . $short] : null;
                $allQuestions[] = array(
                    'question' => $question,
                    'quizId' => $quizId,
                    'specialty' => $full,
                    'short' => $short,
                    'answer' => isset($exam['answers'][$index]) ? $exam['answers'][$index] : null,
                );
            }
        }
        return $allQuestions;
    }

    private function generateNew($testData)
    {
        $target = QuizConstants::getTargetQuestions($this->level);
        $allQuestions = array();
        $tests = $this->db->getCollection('tests');
        $agg = $tests->aggregate(array(
            array('$match' => array('_id' => $testData['_id'])),
            array('$lookup' => array('from' => 'quizzes', 'localField' => 'quizzes', 'foreignField' => '_id', 'as' => 'quizzes')),
            array('$unwind' => '$quizzes'),
            array('$group' => array('_id' => '$_id', 'sumTotal' => array('$sum' => '$quizzes.total'))),
        ))->toArray();
        $sumTotal = isset($agg[0]['sumTotal']) ? $agg[0]['sumTotal'] : 0;
        if ($sumTotal === 0) {
            return array();
        }
        $quizCounts = $this->computeQuizCounts($testData, $sumTotal, $target);
        $this->adjustCounts($quizCounts, $target);
        $quizIds = array_keys($quizCounts);
        $sampled = 0;
        foreach ($quizIds as $quizIdStr) {
            $number = $quizCounts[$quizIdStr];
            if ($sampled + $number > $target) {
                $number = $target - $sampled;
            }
            if ($number <= 0) {
                continue;
            }
            $sampledQuestions = $this->sampleFromQuiz($quizIdStr, $number, $allQuestions, $sampled, $target);
            $sampled += count($sampledQuestions);
            if ($sampled >= $target) {
                break;
            }
        }
        $this->fillGaps($quizIds, $allQuestions, $target, $sampled);
        return $allQuestions;
    }

    private function computeQuizCounts($testData, $sumTotal, $target)
    {
        $quizzesColl = $this->db->getCollection('quizzes');
        $counts = array();
        foreach ($testData['quizzes'] as $quizId) {
            $quiz = $quizzesColl->findOne(array(
                '_id' => new ObjectId($quizId),
                'type' => 'Factuel',
                'level' => $this->level,
                'active' => true,
                'speciality' => array('$in' => array_values(QuizConstants::$SPECIALTIES)),
            ));
            if ($quiz) {
                $num = $sumTotal ? round($quiz['total'] * $target / $sumTotal, 0) : 0;
                if ($num > 0) {
                    $counts[(string) $quizId] = $num;
                }
            }
        }
        return $counts;
    }

    private function adjustCounts(&$counts, $target)
    {
        $total = array_sum($counts);
        if ($total === $target) {
            return;
        }
        $diff = $target - $total;
        $adjustKeys = QuizConstants::getAdjustmentKeys($this->level);
        $quizSpecs = $this->fetchSpecialties(array_keys($counts));
        $adjustIds = array_filter(array_keys($counts), function ($id) use ($quizSpecs, $adjustKeys) {
            return in_array($quizSpecs[$id], $adjustKeys);
        });
        if (empty($adjustIds)) {
            return;
        }
        $absDiff = abs($diff);
        for ($i = 0; $i < $absDiff; $i++) {
            $idx = $i % count($adjustIds);
            $id = $adjustIds[array_keys($adjustIds)[$idx]];
            $counts[$id] += $diff > 0 ? 1 : -1;
            if ($diff < 0) {
                $counts[$id] = max(0, $counts[$id]);
            }
        }
    }

    private function fetchSpecialties($quizIds)
    {
        $quizzesColl = $this->db->getCollection('quizzes');
        $specs = array();
        foreach ($quizIds as $id) {
            $quiz = $quizzesColl->findOne(array('_id' => new ObjectId($id)));
            $specs[$id] = isset($quiz['speciality']) ? $quiz['speciality'] : '';
        }
        return $specs;
    }

    private function sampleFromQuiz($quizIdStr, $number, &$allQuestions, &$sampled, $target)
    {
        $quizId = new ObjectId($quizIdStr);
        $quizzesColl = $this->db->getCollection('quizzes');
        $agg = $quizzesColl->aggregate(array(
            array('$match' => array('_id' => $quizId)),
            array('$lookup' => array('from' => 'questions', 'localField' => 'questions', 'foreignField' => '_id', 'as' => 'questions')),
            array('$unwind' => '$questions'),
            array('$match' => array('questions.active' => true, 'questions.speciality' => array('$in' => array_values(QuizConstants::$SPECIALTIES)))),
            array('$sample' => array('size' => $number)),
            array('$group' => array('_id' => '$_id', 'questions' => array('$push' => '$questions'))),
        ))->toArray();
        $sampledQ = array();
        if (!empty($agg)) {
            $qs = $agg[0]['questions'];
            $quiz = $quizzesColl->findOne(array('_id' => $quizId));
            foreach ($qs as $q) {
                if (in_array($q['speciality'], array_values(QuizConstants::$SPECIALTIES), true)) {
                    $allQuestions[] = array(
                        'question' => $q,
                        'quizId' => $quiz['_id'],
                        'specialty' => $quiz['speciality'],
                        'short' => array_search($quiz['speciality'], QuizConstants::$SPECIALTIES),
                    );
                    $sampled++;
                    $sampledQ[] = $q;
                    if ($sampled >= $target) {
                        break;
                    }
                }
            }
        }
        return $sampledQ;
    }

    private function fillGaps($quizIds, &$allQuestions, $target, $sampled)
    {
        $quizzesColl = $this->db->getCollection('quizzes');
        $availableQuizIds = array_values($quizIds);
        while ($sampled < $target && !empty($availableQuizIds)) {
            $randIndex = array_rand($availableQuizIds);
            $quizIdStr = $availableQuizIds[$randIndex];
            $quizId = new ObjectId($quizIdStr);
            $num = 1;
            $existingIds = array_map(function ($q) {
                return new ObjectId($q['question']['_id']);
            }, $allQuestions);
            $agg = $quizzesColl->aggregate(array(
                array('$match' => array('_id' => $quizId)),
                array('$lookup' => array('from' => 'questions', 'localField' => 'questions', 'foreignField' => '_id', 'as' => 'questions')),
                array('$unwind' => '$questions'),
                array('$match' => array(
                    'questions.active' => true,
                    'questions.speciality' => array('$in' => array_values(QuizConstants::$SPECIALTIES)),
                    'questions._id' => array('$nin' => $existingIds),
                )),
                array('$sample' => array('size' => $num)),
                array('$group' => array('_id' => '$_id', 'questions' => array('$push' => '$questions'))),
            ))->toArray();
            if (!empty($agg) && !empty($agg[0]['questions'])) {
                $qs = $agg[0]['questions'];
                $quiz = $quizzesColl->findOne(array('_id' => $quizId));
                foreach ($qs as $q) {
                    if (in_array($q['speciality'], array_values(QuizConstants::$SPECIALTIES), true)) {
                        $allQuestions[] = array(
                            'question' => $q,
                            'quizId' => $quiz['_id'],
                            'specialty' => $quiz['speciality'],
                            'short' => array_search($quiz['speciality'], QuizConstants::$SPECIALTIES),
                        );
                        $sampled++;
                        if ($sampled >= $target) {
                            break;
                        }
                    }
                }
            } else {
                unset($availableQuizIds[$randIndex]);
                $availableQuizIds = array_values($availableQuizIds);
            }
        }
    }

    private function insertExam($allQuestions, $testData)
    {
        $questionsTags = array_map(function ($q) {
            return new ObjectId($q['question']['_id']);
        }, $allQuestions);
        $quizIds = array();
        foreach (QuizConstants::$SPECIALTIES as $key => $spec) {
            $quizIds['quiz' . $key] = null;
            foreach ($allQuestions as $q) {
                if ($q['quizId'] && $q['specialty'] === $spec) {
                    $quizIds['quiz' . $key] = $q['quizId'];
                    break;
                }
            }
        }
        $examData = array(
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
            'created' => date('d-m-Y H:i:s'),
        ) + $quizIds;
        $this->db->getCollection('exams')->insertOne($examData);
    }
}

class QuizResultProcessor
{
    private $db;
    private $userId;
    private $testId;
    private $level;
    private $userData;
    private $allQuestions;
    private $timerValue;

    public function __construct($db, $userId, $testId, $level, $userData, $allQuestions, $timerValue = null)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->testId = $testId;
        $this->level = $level;
        $this->userData = $userData;
        $this->allQuestions = $allQuestions;
        $this->timerValue = $timerValue;
    }

    public function process($userAnswers)
    {
        $questionsTag = array_map(function ($q) {
            return (string) $q['question']['_id'];
        }, $this->allQuestions);
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

    private function getTimerString()
    {
        if ($this->timerValue !== null && $this->timerValue !== '') {
            return (string) $this->timerValue;
        }
        return isset($_POST['timer']) ? (string) $_POST['timer'] : '00:00:00';
    }

    private function processSpecialtyResult($specName, $questionsTag, $userAnswers)
    {
        $quizzesColl = $this->db->getCollection('quizzes');
        $resultsColl = $this->db->getCollection('results');
        $specQs = array();
        $specAns = array();
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
        $quiz = $quizzesColl->findOne(array(
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level,
            'active' => true,
        ));
        if (!$quiz) {
            return null;
        }
        $numberTest = medacapCountDocuments($resultsColl, array(
            'user' => new ObjectId($this->userId),
            'speciality' => $specName,
            'type' => 'Factuel',
            'level' => $this->level,
        )) + 1;
        return array(
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
            'time' => $this->getTimerString(),
            'active' => true,
            'created' => date('d-m-Y H:i:s'),
        );
    }

    private function buildGlobalResult($questionsTag, $userAnswers, $totalScore)
    {
        $numberTest = medacapCountDocuments($this->db->getCollection('results'), array(
            'user' => new ObjectId($this->userId),
            'test' => new ObjectId($this->testId),
            'type' => 'Factuel',
            'level' => $this->level,
        )) + 1;
        $user = $this->userData['user'];
        $mastered = $totalScore === count($questionsTag);
        return array(
            'questions' => array_map(function ($id) {
                return new ObjectId($id);
            }, $questionsTag),
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
            'time' => $this->getTimerString(),
            'active' => true,
            'created' => date('d-m-Y H:i:s'),
        );
    }
}

// Calcul des pourcentages par spécialité. Non appelé par les pages actuelles, gardé pour la compatibilité.
if (!class_exists('QuizPercentageCalculator')) {
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
}
