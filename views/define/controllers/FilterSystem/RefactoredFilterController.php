<?php
/**
 * RefactoredFilterController.php
 * PHP 7.3 — MongoDB 4.2
 *
 * ⚙️ Dépendances :
 *   require_once __DIR__ . '/Filters.php';
 *   require_once __DIR__ . '/FilterSystem.php';
 *
 * 🎯 Rôle :
 * - Centraliser la lecture des filtres (GET), appliquer les restrictions
 *   utilisateur (session), valider/ajuster (cascade), exposer les domaines
 *   (options <select>), counts de niveaux et IDs techniciens.
 *
 * 🗄️ DB par défaut : "academy"
 * - Collections utilisées en interne par les filtres/système :
 *   users, managersBySubsidiaryAgency (pour le scope manager → subordonnés)
 */

class RefactoredFilterController
{
    /** @var \MongoDB\Database */
    private $database;

    /** @var FilterSystem */
    private $filterSystem;

    /** @var array  Copie de la session utilisateur (profil, id, subsidiary, …) */
    private $session;

 
    

    /**
     * @param \MongoDB\Client|\MongoDB\Database|null $mongoOrDb
     * @param string $dbName
     * @param array|null $session
     */
    public function __construct($mongoOrDb = null, $dbName = 'academy', array $session = null)
    {
        // 1) Init DB
        if ($mongoOrDb instanceof \MongoDB\Database) {
            $this->database = $mongoOrDb;
        } elseif ($mongoOrDb instanceof \MongoDB\Client) {
            $this->database = $mongoOrDb->selectDatabase($dbName);
        } else {
            require_once __DIR__ . '/../core/portal-mongo.php';
            $this->database = definePortalMongoDatabase();
        }

        // 2) Session (permissions décorateur)
        if ($session === null) {
            $this->session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array();
        } else {
            $this->session = $session;
        }

        // 3) Construction du FilterSystem (tous les filtres), + permissions + cache
        $this->filterSystem = (new FilterSystemBuilder($this->database))
            ->buildComplete()                         // subsidiary, agency, technicianId, level, brand
            ->withPermissions($this->session);        // restreint les listes selon le profil
    }

    /* ============================================================
     *                 POINT D’ENTRÉE PRINCIPAL API
     * ============================================================ */

    /**
     * Lit les filtres depuis GET, applique restrictions/validation/cascade,
     * et renvoie un objet complet pour alimenter l’UI :
     *  - valeurs validées
     *  - options disponibles pour chaque filtre
     *  - levelStats (counts junior/senior/expert)
     */
    public function getFilters(): array
    {
        // 1) Lecture & normalisation des paramètres GET
        $raw = $this->readFiltersFromRequest($_GET);

        // 2) Restrictions liées au profil (ex : Manager → managerId imposé)
        $raw = $this->applyUserRestrictions($raw, $this->session);

        // 3) Validation + cascade (ré-initialise ce qui devient invalide)
        $validated = $this->filterSystem
            ->setValues($raw)
            ->validateAndAdjustFilters($raw);

        // 4) Mise à jour du système avec l’état validé
        $this->filterSystem->setValues($validated);

        // 5) Enrichissement pour l’UI (domaines/options + stats)
        return $this->enrichFiltersForUI($validated);
    }

    /* ============================================================
     *                      MÉTHODES UTILITAIRES
     * ============================================================ */

    /** Liste des filiales disponibles (selon contexte courant) */
    public function getSubsidiaries(): array
    {
        return $this->filterSystem->getAvailableOptions('subsidiary');
    }

    /** Liste des agences d’une filiale donnée (helper direct) */
    public function getAgencies(string $subsidiary): array
    {
        $temp = $this->filterSystem->getCurrentValues();
        $temp['subsidiary'] = $subsidiary;

        return $this->filterSystem
            ->setValues($temp)
            ->getAvailableOptions('agency');
    }

    /** Map id => "First Last" des techniciens, selon filtres courants (ou temporaires) */
    public function getTechnicians(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }
        return $this->filterSystem->getAvailableOptions('technicianId');
    }

    /** Liste des marques disponibles (héritage par level ≤) */
    public function getBrands(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }
        return $this->filterSystem->getAvailableOptions('brand');
    }

    /** IDs des techniciens (ObjectId) correspondant aux filtres fournis */
    public function getTechnicianIds(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }
        return $this->filterSystem->getTechnicianIds();
    }

    /** Stats par niveau (junior/senior/expert) pour des filtres fournis (optionnels) */
    public function getLevelStats(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }
        return $this->filterSystem->getLevelStats();
    }

    /** L’utilisateur peut-il choisir la filiale (selon profil) ? */
    public function canSelectSubsidiary(): bool
    {
        $p = isset($this->session['profile']) ? $this->session['profile'] : '';
        return in_array($p, array('Super Admin', 'Group Director'), true);
    }

    /** Valeurs par défaut “compat” (si besoin côté front) */
    public static function withDefaults(array $filters = array()): array
    {
        return array_merge(array(
            'subsidiary'       => 'all',
            'agency'           => 'all',
            'managerId'        => 'all',
            'level'            => 'all',
            'brand'            => 'all',
            'technicianId'     => 'all',
            'availableBrands'  => array(), // clé legacy si tu en as besoin
        ), $filters);
    }

    /* ============================================================
     *                    IMPLÉMENTATION PRIVÉE
     * ============================================================ */

    /**
     * Parse & normalise les filtres depuis la requête (GET).
     * - Supporte les tableaux (brand[]=A&brand[]=B) et CSV (brand=A,B).
     * - Alias "country" → "subsidiary".
     */
    private function readFiltersFromRequest(array $src): array
    {
        $out = array();

        // Subsidiary (alias country)
        $out['subsidiary'] = isset($src['subsidiary'])
            ? trim((string)$src['subsidiary'])
            : (isset($src['country']) ? trim((string)$src['country']) : 'all');
        if ($out['subsidiary'] === '') $out['subsidiary'] = 'all';

        // Agency
        $out['agency'] = isset($src['agency']) ? trim((string)$src['agency']) : 'all';
        if ($out['agency'] === '') $out['agency'] = 'all';

        // Manager
        $out['managerId'] = isset($src['managerId']) ? trim((string)$src['managerId']) : 'all';
        if ($out['managerId'] === '') $out['managerId'] = 'all';

        // Level
        $out['level'] = isset($src['level']) ? trim((string)$src['level']) : 'all';
        if ($out['level'] === '') $out['level'] = 'all';

        // TechnicianId (string, array, CSV)
        $out['technicianId'] = 'all';
        if (isset($src['technicianId'])) {
            if (is_array($src['technicianId'])) {
                $ids = array();
                foreach ($src['technicianId'] as $v) {
                    $v2 = trim((string)$v);
                    if ($v2 !== '') $ids[] = $v2;
                }
                $out['technicianId'] = empty($ids) ? 'all' : $ids;
            } else {
                $v = trim((string)$src['technicianId']);
                if ($v === '') {
                    $out['technicianId'] = 'all';
                } elseif (strpos($v, ',') !== false) {
                    $arr = array();
                    foreach (explode(',', $v) as $p) {
                        $p2 = trim($p);
                        if ($p2 !== '') $arr[] = $p2;
                    }
                    $out['technicianId'] = empty($arr) ? 'all' : $arr;
                } else {
                    $out['technicianId'] = $v;
                }
            }
        }

        // Brand (string, array, CSV) — multi sélection ALL ⇒ tableau
        $out['brand'] = 'all';
        if (isset($src['brand'])) {
            if (is_array($src['brand'])) {
                $vals = array();
                foreach ($src['brand'] as $b) {
                    $b2 = trim((string)$b);
                    if ($b2 !== '') $vals[] = $b2;
                }
                $out['brand'] = empty($vals) ? 'all' : $vals;
            } else {
                $b = trim((string)$src['brand']);
                if ($b === '') {
                    $out['brand'] = 'all';
                } elseif (strpos($b, ',') !== false) {
                    $arr = array();
                    foreach (explode(',', $b) as $p) {
                        $p2 = trim($p);
                        if ($p2 !== '') $arr[] = $p2;
                    }
                    $out['brand'] = empty($arr) ? 'all' : $arr;
                } else {
                    $out['brand'] = $b;
                }
            }
        }

        return $out;
    }

    /**
     * Applique des restrictions selon le profil utilisateur :
     * - Manager : impose managerId = id du manager
     * - Sinon, sauf Super Admin / Group Director : impose subsidiary = filiale user
     */
    private function applyUserRestrictions(array $filters, array $session): array
    {
        $profile = isset($session['profile']) ? $session['profile'] : '';

        if ($profile === 'Manager') {
            $filters['managerId'] = isset($session['id']) ? $session['id'] : 'all';
        } elseif (!in_array($profile, array('Super Admin', 'Group Director'), true)) {
            // Limite à la filiale utilisateur si connue
            if (isset($session['subsidiary']) && $session['subsidiary'] !== '') {
                $filters['subsidiary'] = $session['subsidiary'];
            }
        }

        return $filters;
    }

    /**
     * Enrichit les valeurs validées avec :
     * - options disponibles pour chaque filtre (<select>)
     * - statistiques de niveau
     *
     * Pluralisation propre des clés :
     *   subsidiary   → availableSubsidiaries
     *   agency       → availableAgencies
     *   technicianId → availableTechnicians
     *   level        → availableLevels
     *   brand        → availableBrands
     *   managerId    → availableManagers (au cas où tu ajoutes un filtre manager)
     */
    private function enrichFiltersForUI(array $validated): array
    {
        $out = $validated;

        $labelMap = array(
            'subsidiary'   => 'availableSubsidiaries',
            'agency'       => 'availableAgencies',
            'technicianId' => 'availableTechnicians',
            'level'        => 'availableLevels',
            'brand'        => 'availableBrands',
            'managerId'    => 'availableManagers',
        );

        foreach ($labelMap as $filterName => $key) {
            if ($this->filterSystem->hasFilter($filterName)) {
                $out[$key] = $this->filterSystem->getAvailableOptions($filterName);
            }
        }

        // Statistiques par niveau
        $out['levelStats'] = $this->filterSystem->getLevelStats();

        return $out;
    }

    /* ============================================================
     *                  HELPERS OPTIONNELS (PRATIQUES)
     * ============================================================ */


    /** Accès direct si besoin (tests, debug) */
    public function getFilterSystem(): FilterSystem
    {
        return $this->filterSystem;
    }
}

/* ============================================================
 *               EXEMPLE D’USAGE (commenté)
 * ============================================================
 *
 * // index.php
 * require_once __DIR__ . '/vendor/autoload.php';
 * require_once __DIR__ . '/Filters.php';
 * require_once __DIR__ . '/FilterSystem.php';
 * require_once __DIR__ . '/RefactoredFilterController.php';
 *
 * session_start();
 * $_SESSION = [
 *   'profile'    => 'Manager',         // ou 'Super Admin' / 'Group Director' / autre
 *   'id'         => '66a1234abc...ef', // ObjectId manager en string
 *   'subsidiary' => 'Cameroon',
 * ];
 *
 * $client = new \MongoDB\Client('mongodb://localhost:27017');
 * $db     = $client->selectDatabase('academy');
 *
 * $controller = new RefactoredFilterController($db, 'academy', $_SESSION);
 * $payload    = $controller->getFilters();   // lit $_GET, applique, enrichit
 *
 * header('Content-Type: application/json; charset=utf-8');
 * echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
 *
 */
