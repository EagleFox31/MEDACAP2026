<?php
/**
 * FilterSystem.php
 * PHP 7.3 — MongoDB 4.2
 *
 * Rôle :
 * - Orchestrer l’ensemble des filtres (construction, validation, application).
 * - Exposer une API simple pour le contrôleur (buildQuery, stats, domaines).
 *
 * Dépendances :
 * - Filters.php (doit définir : MongoCompat, FilterInterface, AbstractFilter,
 *   SubsidiaryFilter, AgencyFilter, TechnicianFilter, LevelFilter, BrandFilter,
 *   PermissionFilterDecorator, CacheFilterDecorator).
 *
 * Inclusion typique :
 *   require_once __DIR__ . '/Filters.php';
 */

/* =========================
 *  FilterSystemBuilder
 * =========================
 * Construit une instance FilterSystem avec les filtres standards.
 * Tu peux aussi injecter des filtres custom via addCustomFilter().
 */
class FilterSystemBuilder
{
    /** @var array<string,FilterInterface> */
    private $filters = array();

    /** @var \MongoDB\Database */
    private $database;

    /**
     * @param \MongoDB\Database $database  ex: (new MongoDB\Client)->selectDatabase('academy')
     */
    public function __construct($database)
    {
        $this->database = $database;
    }

    /** Ajoute le filtre "subsidiary" (filiale) */
    public function addSubsidiaryFilter(): self
    {
        $this->filters['subsidiary'] = new SubsidiaryFilter('subsidiary');
        return $this;
    }

    /** Ajoute le filtre "agency" (agence) */
    public function addAgencyFilter(): self
    {
        $this->filters['agency'] = new AgencyFilter('agency');
        return $this;
    }

    /** Ajoute le filtre "technicianId" (tech(s) sélectionné(s)) */
    public function addTechnicianFilter(): self
    {
        $this->filters['technicianId'] = new TechnicianFilter('technicianId');
        return $this;
    }

    /** Ajoute le filtre "level" (Junior/Senior/Expert avec hiérarchie ≤) */
    public function addLevelFilter(): self
    {
        $this->filters['level'] = new LevelFilter('level');
        return $this;
    }

    /** Ajoute le filtre "brand" (marque(s), mono ou multi → ALL/AND) */
    public function addBrandFilter(): self
    {
        $this->filters['brand'] = new BrandFilter('brand');
        return $this;
    }

    /** Injection d’un filtre personnalisé (si besoin) */
    public function addCustomFilter(FilterInterface $filter): self
    {
        $this->filters[$filter->getName()] = $filter;
        return $this;
    }

    /** Construit FilterSystem avec les filtres déjà ajoutés */
    public function build(): FilterSystem
    {
        return new FilterSystem($this->database, $this->filters);
    }

    /** Construction “complète” : tous les filtres standards d’un coup */
    public function buildComplete(): FilterSystem
    {
        return $this
            ->addSubsidiaryFilter()
            ->addAgencyFilter()
            ->addTechnicianFilter()
            ->addLevelFilter()
            ->addBrandFilter()
            ->build();
    }
}

/* =========================
 *  FilterSystem
 * =========================
 * - Centralise l’état courant des filtres (valeurs utilisateur).
 * - Calcule la requête Mongo finale.
 * - Offre des helpers (domaines d’options, stats de niveau, ids techs).
 * - Applique la portée “manager → subordonnés” via la collection
 *   managersBySubsidiaryAgency (sans imposer un champ manager dans users).
 */
class FilterSystem
{
    /** @var \MongoDB\Database */
    private $database;

    /** @var array<string,FilterInterface>  Registre des filtres actifs */
    private $filters = array();

    /** @var array<string,mixed>  Valeurs courantes (UI) */
    private $currentValues = array();

    /**
     * Valeurs par défaut :
     * - 'all' signifie “pas de contrainte” côté UI pour ce filtre.
     * - Pas de filtre "managerId" concret ici : il est traité dans buildQuery (post).
     */
    private const DEFAULT_FILTERS = array(
        'subsidiary'   => 'all',
        'agency'       => 'all',
        'managerId'    => 'all',    // scope managérial appliqué en post-traitement de la query
        'level'        => 'all',
        'brand'        => 'all',
        'technicianId' => 'all',
    );

    /**
     * @param \MongoDB\Database $database
     * @param array<string,FilterInterface> $filters
     */
    public function __construct($database, array $filters = array())
    {
        $this->database = $database;
        $this->filters = $filters;
        $this->currentValues = self::DEFAULT_FILTERS;
    }

    /* -----------------------------------------
     * Cycle de vie des valeurs (provenant de l’UI)
     * -----------------------------------------
     * setValues() : définit les valeurs de l’écran.
     * validateAndAdjustFilters() : corrige l’invalidité (cascade).
     * getCurrentValues() : récupère l’état courant.
     */

    /** Met à jour les valeurs courantes (fusionne avec les défauts) */
    public function setValues(array $values): self
    {
        $this->currentValues = array_merge(self::DEFAULT_FILTERS, $values);
        return $this;
    }

    /** Retourne l’état courant des filtres (utile pour un GET de debug) */
    public function getCurrentValues(): array
    {
        return $this->currentValues;
    }

    /* -----------------------------------------
     * Domaines (listes pour les <select>)
     * -----------------------------------------
     * Pour un filtre donné, on calcule ses options selon les “constraints”
     * provenant des autres filtres (valeurs courantes).
     */

    /** Test d’existence d’un filtre par nom */
    public function hasFilter(string $filterName): bool
    {
        return isset($this->filters[$filterName]);
    }

    /** Accès direct à une instance de filtre (rarement nécessaire côté contrôleur) */
    public function getFilter(string $filterName): ?FilterInterface
    {
        return isset($this->filters[$filterName]) ? $this->filters[$filterName] : null;
    }

    /**
     * Calcule les options disponibles pour un filtre donné,
     * en appliquant les contraintes des autres filtres.
     */
    public function getAvailableOptions(string $filterName): array
    {
        if (!isset($this->filters[$filterName])) {
            return array();
        }

        // Construit le set de contraintes : toutes les valeurs sauf le filtre ciblé.
        $constraints = array();
        foreach ($this->currentValues as $k => $v) {
            if ($k !== $filterName) $constraints[$k] = $v;
        }

        return $this->filters[$filterName]->getAvailableOptions($this->database, $constraints);
    }

    /* -----------------------------------------
     * Requête finale Mongo (T*(σ))
     * -----------------------------------------
     * - Point d’entrée unique pour alimenter tes find/aggregate ailleurs.
     * - Applique tous les filtres via FilterInterface::apply().
     * - Puis applique, en post-traitement, la portée managerId (si fourni).
     */

    /** Construit la requête Mongo finale (array $match-like) */
    public function buildQuery(): array
    {
        // Base : uniquement techniciens actifs (hors candidats)
        $query = array('profile' => 'Technicien', 'active' => true, 'isCandidat' => array('$ne' => true));

        // 1) Applique chaque filtre avec sa valeur actuelle
        foreach ($this->filters as $name => $filter) {
            $value = isset($this->currentValues[$name]) ? $this->currentValues[$name] : 'all';
            $query = $filter->apply($query, $value);
        }

        // 2) Portée “managerId” : restreindre aux subordonnés du manager
        $mid = isset($this->currentValues['managerId']) ? $this->currentValues['managerId'] : 'all';
        if ($mid !== 'all') {
            $ids = $this->subordinateIdsForManager($mid);
            if (empty($ids)) {
                // match impossible : on force un _id inexistant
                $query['_id'] = new \MongoDB\BSON\ObjectId('000000000000000000000000');
            } else {
                $oidList = array();
                foreach ($ids as $s) { $oidList[] = new \MongoDB\BSON\ObjectId($s); }

                // Si _id est déjà filtré (p. ex. technicienId), on intersecte.
                if (isset($query['_id']) && is_array($query['_id']) && isset($query['_id']['$in'])) {
                    $query['_id']['$in'] = array_values(array_uintersect($query['_id']['$in'], $oidList, function($a,$b){
                        return strcmp((string)$a, (string)$b);
                    }));
                } else {
                    $query['_id'] = array('$in' => $oidList);
                }
            }
        }

        return $query;
    }

    /**
     * Helper : renvoie la liste (string) des techniciens subordonnés d’un manager.
     * Source : collection `managersBySubsidiaryAgency` (structure hiérarchique).
     */
    private function subordinateIdsForManager($managerId): array
    {
        try {
            $oid = ($managerId instanceof \MongoDB\BSON\ObjectId)
                ? $managerId
                : new \MongoDB\BSON\ObjectId($managerId);

            $pipeline = array(
                array('$unwind' => '$agencies'),
                array('$unwind' => '$agencies.managers'),
                array('$match'  => array('agencies.managers.-_id' => $oid)),
                array('$unwind' => '$agencies.managers.technicians'),
                array('$group'  => array('_id' => null, 'techs' => array('$addToSet' => '$agencies.managers.technicians.-_id'))),
                array('$project'=> array('_id' => 0, 'techs' => 1))
            );

            $doc = $this->database->managersBySubsidiaryAgency->aggregate($pipeline)->toArray();
            if (empty($doc) || empty($doc[0]['techs'])) return array();

            $out = array();
            foreach ($doc[0]['techs'] as $id) { $out[] = (string)$id; }
            return $out;

        } catch (\Exception $e) {
            error_log("Erreur subordinateIdsForManager: " . $e->getMessage());
            return array();
        }
    }

    /* -----------------------------------------
     * Validation / Ajustement (UX robuste)
     * -----------------------------------------
     * Si une valeur devient invalide (ex: on garde "Expert" mais l’agence
     * choisie n’a plus d’expert), on remet cette valeur à 'all' et on reset
     * les filtres en aval selon une table de dépendances.
     */

    /** Valide puis ajuste les valeurs selon leurs dépendances */
    public function validateAndAdjustFilters(array $newValues): array
    {
        // On part de l’état courant + modifications
        $adjustedValues = array_merge($this->currentValues, $newValues);

        // Ordre amont → aval (important pour la cascade)
        $priorityOrder = array('subsidiary', 'agency', 'technicianId', 'level', 'brand');

        foreach ($priorityOrder as $filterName) {
            if (!isset($this->filters[$filterName])) {
                continue;
            }

            $currentValue = isset($adjustedValues[$filterName]) ? $adjustedValues[$filterName] : 'all';
            if ($currentValue === 'all') {
                continue; // pas de validation si pas de contrainte
            }

            // Constraints = valeurs actuelles (incluant les autres filtres)
            $constraints = array();
            foreach ($adjustedValues as $k => $v) {
                if ($k !== $filterName) $constraints[$k] = $v;
            }

            // Options disponibles pour ce filtre, sous ces contraintes
            $availableOptions = $this->filters[$filterName]->getAvailableOptions($this->database, $constraints);

            // Si valeur devenue invalide → reset à 'all' + cascade sur dépendants
            if (!$this->filters[$filterName]->isValid($currentValue, $availableOptions)) {
                $adjustedValues[$filterName] = 'all';
                $adjustedValues = $this->resetDependentFilters($adjustedValues, $filterName);
            }
        }

        return $adjustedValues;
    }

    /**
     * Réinitialise les filtres dépendants quand un filtre amont change/est invalidé.
     * La table de dépendances reflète l’ordre logique métier :
     * subsidiary → agency → technicianId → level → brand
     */
    private function resetDependentFilters(array $values, string $changedFilter): array
    {
        $dependencies = array(
            'subsidiary'   => array('agency', 'technicianId', 'level', 'brand'),
            'agency'       => array('technicianId', 'level', 'brand'),
            'technicianId' => array('level', 'brand'),
            'level'        => array('brand'),
            'brand'        => array()
        );

        if (isset($dependencies[$changedFilter])) {
            foreach ($dependencies[$changedFilter] as $dependentFilter) {
                // Si tu veux préserver une “sélection explicite récente”,
                // implémente isExplicitSelection() (hook prévu).
                if (!$this->isExplicitSelection($dependentFilter)) {
                    $values[$dependentFilter] = 'all';
                }
            }
        }

        return $values;
    }

    /** Hook éventuel : marquer certains changements comme “explicites” (UI) */
    private function isExplicitSelection(string $filterName): bool
    {
        // À implémenter si tu traces les actions front ; par défaut : false.
        return false;
    }

    /* -----------------------------------------
     * Utilitaires : IDs techs & stats par niveau
     * -----------------------------------------
     * getTechnicianIds() : renvoie les _id des techniciens correspondant à la query.
     * getLevelStats()    : compte Junior/Senior/Expert (via MongoCompat::count).
     */

    /** Liste des ObjectId des techniciens correspondant aux filtres courants */
    public function getTechnicianIds(): array
    {
        $query = $this->buildQuery();
        try {
            $cursor = $this->database->users->find($query, array('projection' => array('_id' => 1)));
            $ids = array();
            foreach ($cursor as $doc) {
                $ids[] = $doc['_id'];
            }
            return $ids;
        } catch (\Exception $e) {
            error_log("Erreur FilterSystem::getTechnicianIds: " . $e->getMessage());
            return array();
        }
    }

    /** Statistiques par niveau (counts) */
    public function getLevelStats(): array
    {
        $baseQuery = $this->buildQuery();

        $stats = array(
            'juniorCount' => 0,
            'seniorCount' => 0,
            'expertCount' => 0
        );

        try {
            $stats['juniorCount'] = MongoCompat::count($this->database->users, array_merge($baseQuery, array('level' => 'Junior')));
            $stats['seniorCount'] = MongoCompat::count($this->database->users, array_merge($baseQuery, array('level' => 'Senior')));
            $stats['expertCount'] = MongoCompat::count($this->database->users, array_merge($baseQuery, array('level' => 'Expert')));
        } catch (\Exception $e) {
            error_log("Erreur FilterSystem::getLevelStats: " . $e->getMessage());
        }

        return $stats;
    }

    /* -----------------------------------------
     * Décorateurs (optionnels) : Permissions & Cache
     * -----------------------------------------
     * Ces helpers retournent une NOUVELLE instance FilterSystem avec
     * les filtres décorés (pattern Decorator, défini dans Filters.php).
     */

    /**
     * Applique des restrictions basées sur la session utilisateur
     * (ex : filiale imposée, manager ne voit que lui-même, etc.).
     */
    public function withPermissions(array $userSession): self
    {
        $decoratedFilters = array();
        foreach ($this->filters as $name => $filter) {
            $decoratedFilters[$name] = new PermissionFilterDecorator($filter, $userSession);
        }
        return new FilterSystem($this->database, $decoratedFilters);
    }


}
