<?php
use MongoDB\BSON\ObjectId;
/**
 * Contrôleur de base fournissant des fonctionnalités communes
 * pour tous les contrôleurs spécifiques
 */
class BaseController {
    /** @var MongoDB\Database */
    protected $academy;
    /** @var array */
    protected $translations;

    /**
     * @param MongoDB\Database $academy
     */
    public function __construct($academy) {
        // S'assure d'avoir une session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->checkAuthentication();

        $this->academy = $academy;
        $this->translations = $GLOBALS['translations'] ?? [];
    }

    /**
     * Vérifie que l'utilisateur est authentifié
     */
    protected function checkAuthentication() {
        if (!isset($_SESSION['id']) || !isset($_SESSION['profile'])) {
            // Aligne la redirection sur votre entrypoint (../../)
            header('Location: ../../');
            exit();
        }
    }

    /**
     * Vérifie que l'utilisateur a un profil autorisé
     *
     * @param array $allowedProfiles
     */
    protected function checkAuthorization($allowedProfiles) {
        $profile = $_SESSION['profile'] ?? '';
        if (!in_array($profile, $allowedProfiles, true)) {
            http_response_code(403);
            echo "Accès refusé. Votre profil n'est pas autorisé à accéder à cette page.";
            exit();
        }
    }

    /**
     * Affiche une vue avec des données
     *
     * @param string $viewPath Chemin relatif depuis views/ (ex: 'director/dashboard.php')
     * @param array  $data
     */
    protected function renderView($viewPath, $data = []) {
        // Évite les boucles infinies dans le header
        $GLOBALS['currentPage'] = $GLOBALS['currentPage'] ?? 'dashboard';

        // Expose les variables à la vue
        extract($data, EXTR_OVERWRITE);

        $fullPath = __DIR__ . "/../views/{$viewPath}";
        if (!is_file($fullPath)) {
            // Fallback propre si mauvais chemin
            http_response_code(500);
            echo "Vue introuvable : " . htmlspecialchars($viewPath);
            exit();
        }

        include $fullPath;
    }

    /**
     * Formate un score pour l'affichage
     */
    protected function formatScore($score, $decimals = 1) {
        return number_format((float)$score, $decimals);
    }

    /**
     * Retourne la classe CSS pour un score donné
     */
    protected function getScoreClass($score) {
        // Valeurs par défaut sûres si les constantes ne sont pas chargées
        $thrMed = defined('SCORE_THRESHOLD_MEDIUM') ? SCORE_THRESHOLD_MEDIUM : 80;
        $thrLow = defined('SCORE_THRESHOLD_LOW')    ? SCORE_THRESHOLD_LOW    : 60;

        if ($score >= $thrMed) {
            return 'bg-success';
        } elseif ($score >= $thrLow) {
            return 'bg-warning';
        }
        return 'bg-danger';
    }

    /**
     * Sécurise une chaîne pour l'affichage HTML
     */
    protected function escape($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Crée un critère MongoDB avec _id correctement typé
     *
     * @param string|\MongoDB\BSON\ObjectId $id
     * @return array
     */
    protected function createIdCriteria($id) {
        if (empty($id)) {
            return [];
        }

        // Si on nous passe déjà un ObjectId, on le garde
        if ($id instanceof \MongoDB\BSON\ObjectId) {
            return ['_id' => $id];
        }

        // Conversion sûre vers ObjectId si format valide
        if (is_string($id) && preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return ['_id' => new \MongoDB\BSON\ObjectId($id)];
        }

        // Format non supporté → aucun critère
        return [];
    }

    /**
     * Calcule le pourcentage entre deux valeurs
     */
    protected function calculatePercentage($value, $total, $decimals = 1) {
        $value = (float)$value;
        $total = (float)$total;
        if ($total <= 0) return 0.0;
        return round(($value / $total) * 100, $decimals);
    }

    /**
     * Retourne la traduction d'une clé
     */
    protected function translate($key, $default = '') {
        return $this->translations[$key] ?? $default;
    }
}
