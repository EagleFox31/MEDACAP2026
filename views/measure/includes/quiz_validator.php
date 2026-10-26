<?php
/**
 * Quiz factuel: constantes et validation.
 * Code historique récupéré depuis le commit d9e8441 (avant extraction des classes).
 * Aucun changement fonctionnel intentionnel.
 */
use MongoDB\BSON\ObjectId;

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
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}
