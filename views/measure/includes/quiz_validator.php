<?php
/**
 * Validation session / paramètres QCM technicien (Factuel + Déclaratif).
 * PHP 7.3
 */
use MongoDB\BSON\ObjectId;

if (!class_exists('QuizValidator')) {
    class QuizValidator
    {
        public static function validateSession()
        {
            if (!isset($_SESSION['id'])) {
                header('Location: ../../');
                exit();
            }
        }

        public static function validateParams($params)
        {
            foreach (array('id', 'level', 'test') as $key) {
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

        private static function jsonError($message)
        {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'message' => $message));
            exit();
        }
    }
}
