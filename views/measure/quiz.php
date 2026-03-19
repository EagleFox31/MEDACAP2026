<?php
$quizType = $_GET['type'] ?? '';
$userParam = $_GET['user'] ?? '';

// Central router for quiz type
if ($quizType === 'Factuel') {
    include_once 'userQuizFactuel.php';
} elseif ($quizType === 'Declaratif') {
    include_once 'userQuizDeclaratif.php';
} elseif (!empty($userParam)) {
    include_once 'userEvaluation.php';
} else {
    // Default or error handling
    header("Location: ../../");
    exit();
}
?>