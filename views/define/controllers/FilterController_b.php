<?php
// Fichier : views/define/controllers/FilterSystem/FilterSystemRefactored.php

use MongoDB\Model\BSONDocument;

/**
 * Interface pour les filtres
 * Définit le contrat de base pour tous les filtres
 */
interface FilterInterface
{
    public function apply(array $query, $value): array;
    public function getAvailableOptions($database, array $constraints = []): array;
    public function isValid($value, array $availableOptions = []): bool;
    public function getName(): string;
}

/**
 * Classe abstraite de base pour les filtres
 * Implémente la logique commune à tous les filtres
 */
abstract class AbstractFilter implements FilterInterface
{
    protected $name;
    protected $defaultValue = 'all';

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isValid($value, array $availableOptions = []): bool
    {
        if ($value === 'all' || $value === $this->defaultValue) {
            return true;
        }

        if (empty($availableOptions)) {
            return !empty($value);
        }

        if (is_array($value)) {
            // au moins une des valeurs existe dans les options
            return !empty(array_intersect($value, is_array($availableOptions) ? $availableOptions : array_keys($availableOptions)));
        }

        // $availableOptions peut être une liste (brands) ou un map (technicians)
        if (is_array($availableOptions) && array_keys($availableOptions) !== range(0, count($availableOptions) - 1)) {
            return isset($availableOptions[$value]) || in_array($value, $availableOptions, true);
        }

        return in_array($value, $availableOptions, true);
    }

    /**
     * Construit la requête de base pour les techniciens actifs
     * @return array Requête de base
     */
    protected function getBaseTechnicianQuery(): array
    {
        // IMPORTANT: facettes calculées uniquement sur des Techniciens actifs
        return ['profile' => 'Technicien', 'active' => true];
    }

    /**
     * Convertit un ID en ObjectId
     * @param mixed $id ID à convertir
     * @return MongoDB\BSON\ObjectId
     */
    protected function toObjectId($id): \MongoDB\BSON\ObjectId
    {
        return $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id);
    }

    /**
     * Restreint la requête aux subordonnés d’un manager via managersBySubsidiaryAgency
     */
    protected function applyManagerConstraint(array $query, $database, $managerId): array
    {
        if (empty($managerId) || $managerId === 'all') {
            return $query;
        }
        try {
            $oid = $this->toObjectId($managerId);
            $pipeline = array(
                array('$unwind' => '$agencies'),
                array('$unwind' => '$agencies.managers'),
                array('$match'  => array('agencies.managers.-_id' => $oid)),
                array('$unwind' => '$agencies.managers.technicians'),
                array('$group'  => array('_id' => null, 'techs' => array('$addToSet' => '$agencies.managers.technicians.-_id'))),
                array('$project'=> array('_id' => 0, 'techs' => 1))
            );
            $doc = $database->managersBySubsidiaryAgency->aggregate($pipeline)->toArray();
            if (empty($doc) || empty($doc[0]['techs'])) {
                // match impossible
                $query['_id'] = new \MongoDB\BSON\ObjectId('000000000000000000000000');
                return $query;
            }
            $ids = array();
            foreach ($doc[0]['techs'] as $id) { $ids[] = new \MongoDB\BSON\ObjectId((string)$id); }

            if (isset($query['_id'])) {
                // intersection basique si déjà un _id existe (cas liste de techs)
                if (is_array($query['_id']) && isset($query['_id']['$in'])) {
                    $query['_id']['$in'] = array_values(array_uintersect($query['_id']['$in'], $ids, function($a,$b){
                        return strcmp((string)$a, (string)$b);
                    }));
                } else {
                    $query['_id'] = array('$in' => $ids);
                }
            } else {
                $query['_id'] = array('$in' => $ids);
            }
        } catch (\Exception $e) {
            error_log("Erreur applyManagerConstraint: " . $e->getMessage());
        }
        return $query;
    }

    /**
     * Hiérarchie des niveaux (≤ niveau)
     */
    protected function getLevelHierarchy(string $level): array
    {
        switch ($level) {
            case 'Junior': return array('Junior');
            case 'Senior': return array('Junior', 'Senior');
            case 'Expert': return array('Junior', 'Senior', 'Expert');
            default:       return array('Junior', 'Senior', 'Expert');
        }
    }

    /**
     * Contrainte brand pour mono ou multi-sélection (ALL)
     */
    protected function addBrandConstraint(array $query, $brandValue): array
    {
        if ($brandValue === 'all' || empty($brandValue)) {
            return $query;
        }
        $values = is_array($brandValue) ? array_values(array_filter($brandValue, 'strlen')) : array($brandValue);
        if (empty($values)) return $query;

        $and = array();
        foreach ($values as $v) {
            $and[] = array(
                '$or' => array(
                    array('brandJunior' => $v),
                    array('brandSenior' => $v),
                    array('brandExpert' => $v),
                )
            );
        }

        if (isset($query['$and'])) {
            $query['$and'] = array_merge($query['$and'], $and);
        } else {
            $query['$and'] = $and;
        }
        return $query;
    }
}

/**
 * Filtre pour les filiales (subsidiaries)
 */
class SubsidiaryFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            $query['subsidiary'] = $value;
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        // Appliquer les contraintes des autres filtres
        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') {
                continue;
            }
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            return $database->users->distinct('subsidiary', $query);
        } catch (\Exception $e) {
            error_log("Erreur SubsidiaryFilter::getAvailableOptions: " . $e->getMessage());
            return array();
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = array();
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = array('$in' => $ids);
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = array('$in' => $this->getLevelHierarchy($filterValue));
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre pour les agences
 */
class AgencyFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            $query['agency'] = $value;
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        // Les agences dépendent obligatoirement de la filiale
        if (!isset($constraints['subsidiary']) || $constraints['subsidiary'] === 'all') {
            return array(); // pas de filiale => pas d’agences
        }

        $query['subsidiary'] = $constraints['subsidiary'];

        // Appliquer les autres contraintes
        foreach ($constraints as $filterName => $filterValue) {
            if (in_array($filterName, array($this->name, 'subsidiary'), true) || $filterValue === 'all') {
                continue;
            }
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            $agencies = $database->users->distinct('agency', $query);
            $agencies = array_map('trim', $agencies);
            $agencies = array_values(array_unique(array_filter($agencies, 'strlen')));
            return $agencies;
        } catch (\Exception $e) {
            error_log("Erreur AgencyFilter::getAvailableOptions: " . $e->getMessage());
            // Fallback vers la configuration statique
            $file = __DIR__ . '/../components/agencyData.php';
            if (is_file($file)) {
                $agencyMap = include $file;
                return isset($agencyMap[$constraints['subsidiary']]) ? $agencyMap[$constraints['subsidiary']] : array();
            }
            return array();
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = array();
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = array('$in' => $ids);
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = array('$in' => $this->getLevelHierarchy($filterValue));
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre pour les techniciens
 */
class TechnicianFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            if (is_array($value)) {
                $ids = array();
                foreach ($value as $id) { $ids[] = $this->toObjectId($id); }
                $query['_id'] = array('$in' => $ids);
            } else {
                $query['_id'] = $this->toObjectId($value);
            }
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        // Appliquer les contraintes des autres filtres
        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') {
                continue;
            }
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            $cursor = $database->users->find($query, array(
                'projection' => array(
                    '_id' => 1,
                    'firstName' => 1,
                    'lastName' => 1
                )
            ));

            $technicians = array();
            foreach ($cursor as $tech) {
                $technicians[(string)$tech['_id']] =
                    (isset($tech['firstName']) ? $tech['firstName'] : '') . ' ' .
                    (isset($tech['lastName']) ? $tech['lastName'] : '');
            }

            return $technicians;
        } catch (\Exception $e) {
            error_log("Erreur TechnicianFilter::getAvailableOptions: " . $e->getMessage());
            return array();
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = $filterValue;
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'level':
                $query['level'] = array('$in' => $this->getLevelHierarchy($filterValue));
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre pour les niveaux
 */
class LevelFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            $query['level'] = array('$in' => $this->getLevelHierarchy($value));
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        // Appliquer les contraintes des autres filtres
        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') {
                continue;
            }
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            // si un technicien est sélectionné → downset de son niveau
            if (isset($constraints['technicianId']) && $constraints['technicianId'] !== 'all') {
                $techId = is_array($constraints['technicianId']) ?
                    $constraints['technicianId'][0] : $constraints['technicianId'];

                $tech = $database->users->findOne(
                    array('_id' => $this->toObjectId($techId), 'profile' => 'Technicien', 'active' => true),
                    array('projection' => array('level' => 1))
                );

                if ($tech && isset($tech['level'])) {
                    return $this->getLevelHierarchy((string)$tech['level']);
                }
            }

            $levels = $database->users->distinct('level', $query);
            // déterminer le niveau max observé puis retourner le downset
            $maxLevel = $this->determineMaxLevel($levels);
            return $this->getLevelHierarchy($maxLevel);

        } catch (\Exception $e) {
            error_log("Erreur LevelFilter::getAvailableOptions: " . $e->getMessage());
            return array('Junior', 'Senior', 'Expert');
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = $filterValue;
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = array();
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = array('$in' => $ids);
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }

    private function determineMaxLevel(array $levels): string
    {
        // ordre: Junior < Senior < Expert
        $rank = array('Junior' => 1, 'Senior' => 2, 'Expert' => 3);
        $maxV = 0;
        $maxL = 'Junior';
        foreach ($levels as $l) {
            $v = isset($rank[$l]) ? $rank[$l] : 0;
            if ($v > $maxV) { $maxV = $v; $maxL = $l; }
        }
        return $maxL;
    }
}

/**
 * Filtre pour les marques
 */
class BrandFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        // multi-sélection ALL : ET de clauses OR
        return $this->addBrandConstraint($query, $value);
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        // Si un technicien spécifique est sélectionné, retourner ses marques
        if (isset($constraints['technicianId']) && $constraints['technicianId'] !== 'all') {
            $level = (isset($constraints['level']) && $constraints['level'] !== 'all') ? $constraints['level'] : null;
            return $this->getTechnicianBrands($database, $constraints['technicianId'], $level);
        }

        // Appliquer les contraintes des autres filtres
        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') {
                continue;
            }
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            // champs brands à unir selon le level (héritage ≤ level)
            $level = (isset($constraints['level']) && $constraints['level'] !== 'all') ? $constraints['level'] : 'Expert';
            $levels = $this->getLevelHierarchy($level);
            $fields = array();
            foreach ($levels as $lvl) {
                $fields[] = ($lvl === 'Junior' ? 'brandJunior' : ($lvl === 'Senior' ? 'brandSenior' : 'brandExpert'));
            }

            $unionParts = array();
            foreach ($fields as $f) {
                $unionParts[] = array('$ifNull' => array('$'.$f, array()));
            }

            $pipeline = array(
                array('$match' => $query),
                array('$project' => array(
                    '_id' => 0,
                    'allBrands' => array('$setUnion' => $unionParts)
                )),
                array('$unwind' => '$allBrands'),
                array('$group' => array('_id' => '$allBrands')),
                array('$sort'  => array('_id' => 1)),
                array('$project' => array('_id' => 0, 'brand' => '$_id'))
            );

            $res = $database->users->aggregate($pipeline);
            $brands = array();
            foreach ($res as $r) { $brands[] = $r['brand']; }
            return $brands;

        } catch (\Exception $e) {
            error_log("Erreur BrandFilter::getAvailableOptions: " . $e->getMessage());
            return array();
        }
    }

    private function getTechnicianBrands($database, $technicianId, $level = null): array
    {
        try {
            $techId = is_array($technicianId) ? $technicianId[0] : $technicianId;

            $fields = array('brandJunior' => 1, 'brandSenior' => 1, 'brandExpert' => 1);
            $tech = $database->users->findOne(
                array('_id' => $this->toObjectId($techId), 'profile' => 'Technicien', 'active' => true),
                array('projection' => $fields)
            );

            if (!$tech) return array();

            if ($level && $level !== 'all') {
                $map = array('Junior' => 'brandJunior', 'Senior' => 'brandSenior', 'Expert' => 'brandExpert');
                $field = isset($map[$level]) ? $map[$level] : null;
                $arr = $field ? $this->normalizeBrands(isset($tech[$field]) ? $tech[$field] : array()) : array();
                return $arr;
            }

            $brands = array_unique(array_merge(
                $this->normalizeBrands(isset($tech['brandJunior']) ? $tech['brandJunior'] : array()),
                $this->normalizeBrands(isset($tech['brandSenior']) ? $tech['brandSenior'] : array()),
                $this->normalizeBrands(isset($tech['brandExpert']) ? $tech['brandExpert'] : array())
            ));

            sort($brands, SORT_FLAG_CASE | SORT_STRING);
            return $brands;

        } catch (\Exception $e) {
            error_log("Erreur getTechnicianBrands: " . $e->getMessage());
            return array();
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = $filterValue;
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = array();
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = array('$in' => $ids);
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = array('$in' => $this->getLevelHierarchy($filterValue));
                break;
        }
        return $query;
    }

    private function normalizeBrands($raw): array
    {
        if ($raw instanceof \MongoDB\Model\BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $v) {
            $v2 = trim((string)$v);
            if ($v2 !== '') $out[] = $v2;
        }
        $out = array_values(array_unique($out));
        return $out;
    }
}

/**
 * Builder pour construire le système de filtres
 */
class FilterSystemBuilder
{
    private $filters = array();
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function addSubsidiaryFilter(): self
    {
        $this->filters['subsidiary'] = new SubsidiaryFilter('subsidiary');
        return $this;
    }

    public function addAgencyFilter(): self
    {
        $this->filters['agency'] = new AgencyFilter('agency');
        return $this;
    }

    public function addTechnicianFilter(): self
    {
        $this->filters['technicianId'] = new TechnicianFilter('technicianId');
        return $this;
    }

    public function addLevelFilter(): self
    {
        $this->filters['level'] = new LevelFilter('level');
        return $this;
    }

    public function addBrandFilter(): self
    {
        $this->filters['brand'] = new BrandFilter('brand');
        return $this;
    }

    public function addCustomFilter(FilterInterface $filter): self
    {
        $this->filters[$filter->getName()] = $filter;
        return $this;
    }

    public function build(): FilterSystem
    {
        return new FilterSystem($this->database, $this->filters);
    }

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

/**
 * Décorateur de base pour les filtres
 */
abstract class FilterDecorator implements FilterInterface
{
    protected $filter;

    public function __construct(FilterInterface $filter)
    {
        $this->filter = $filter;
    }

    public function apply(array $query, $value): array
    {
        return $this->filter->apply($query, $value);
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        return $this->filter->getAvailableOptions($database, $constraints);
    }

    public function isValid($value, array $availableOptions = []): bool
    {
        return $this->filter->isValid($value, $availableOptions);
    }

    public function getName(): string
    {
        return $this->filter->getName();
    }
}

/**
 * Décorateur pour la validation des permissions utilisateur
 */
class PermissionFilterDecorator extends FilterDecorator
{
    private $userSession;

    public function __construct(FilterInterface $filter, array $userSession)
    {
        parent::__construct($filter);
        $this->userSession = $userSession;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $options = parent::getAvailableOptions($database, $constraints);

        switch ($this->filter->getName()) {
            case 'subsidiary':
                return $this->filterSubsidiaryOptions($options);
            case 'managerId':
                return $this->filterManagerOptions($options);
            default:
                return $options;
        }
    }

    private function filterSubsidiaryOptions(array $options): array
    {
        $profile = isset($this->userSession['profile']) ? $this->userSession['profile'] : '';

        // Super Admin et Group Director voient toutes les filiales
        if (in_array($profile, array('Super Admin', 'Group Director'), true)) {
            return $options;
        }

        // Les autres sont limités à leur filiale
        $userSubsidiary = isset($this->userSession['subsidiary']) ? $this->userSession['subsidiary'] : '';
        return in_array($userSubsidiary, $options, true) ? array($userSubsidiary) : array();
    }

    private function filterManagerOptions(array $options): array
    {
        $profile = isset($this->userSession['profile']) ? $this->userSession['profile'] : '';

        // Un manager ne peut voir que lui-même
        if ($profile === 'Manager') {
            $managerId = isset($this->userSession['id']) ? $this->userSession['id'] : '';
            return isset($options[$managerId]) ? array($managerId => $options[$managerId]) : array();
        }

        return $options;
    }
}

/**
 * Décorateur pour la mise en cache (optionnel)
 */
class CacheFilterDecorator extends FilterDecorator
{
    private $cache = array();
    private $ttl;

    public function __construct(FilterInterface $filter, int $ttl = 300)
    {
        parent::__construct($filter);
        $this->ttl = $ttl;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $cacheKey = $this->generateCacheKey($constraints);

        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey]['data'];
        }

        $options = parent::getAvailableOptions($database, $constraints);

        $this->cache[$cacheKey] = array(
            'data' => $options,
            'timestamp' => time()
        );

        return $options;
    }

    private function generateCacheKey(array $constraints): string
    {
        return $this->filter->getName() . '_' . md5(json_encode($constraints));
    }

    private function isCacheValid(string $cacheKey): bool
    {
        if (!isset($this->cache[$cacheKey])) {
            return false;
        }
        $age = time() - $this->cache[$cacheKey]['timestamp'];
        return $age < $this->ttl;
    }
}

/**
 * Système principal de gestion des filtres
 */
class FilterSystem
{
    private $database;
    private $filters = array();
    private $currentValues = array();

    /** Valeurs par défaut pour tous les filtres */
    private const DEFAULT_FILTERS = array(
        'subsidiary'   => 'all',
        'agency'       => 'all',
        'managerId'    => 'all',
        'level'        => 'all',
        'brand'        => 'all',
        'technicianId' => 'all',
    );

    public function __construct($database, array $filters = array())
    {
        $this->database = $database;
        $this->filters = $filters;
        $this->currentValues = self::DEFAULT_FILTERS;
    }

    public function setValues(array $values): self
    {
        $this->currentValues = array_merge(self::DEFAULT_FILTERS, $values);
        return $this;
    }

    public function getAvailableOptions(string $filterName): array
    {
        if (!isset($this->filters[$filterName])) {
            return array();
        }

        // Construire les contraintes depuis les autres filtres
        $constraints = array();
        foreach ($this->currentValues as $k => $v) {
            if ($k !== $filterName) $constraints[$k] = $v;
        }

        return $this->filters[$filterName]->getAvailableOptions($this->database, $constraints);
    }

    public function buildQuery(): array
    {
        $query = array('profile' => 'Technicien', 'active' => true);

        foreach ($this->filters as $name => $filter) {
            $value = isset($this->currentValues[$name]) ? $this->currentValues[$name] : 'all';
            $query = $filter->apply($query, $value);
        }

        // Scoper par manager en post-traitement (via hiérarchie)
        $mid = isset($this->currentValues['managerId']) ? $this->currentValues['managerId'] : 'all';
        if ($mid !== 'all') {
            try {
                $oid = new \MongoDB\BSON\ObjectId($mid);
                $pipeline = array(
                    array('$unwind' => '$agencies'),
                    array('$unwind' => '$agencies.managers'),
                    array('$match'  => array('agencies.managers.-_id' => $oid)),
                    array('$unwind' => '$agencies.managers.technicians'),
                    array('$group'  => array('_id' => null, 'techs' => array('$addToSet' => '$agencies.managers.technicians.-_id'))),
                    array('$project'=> array('_id' => 0, 'techs' => 1))
                );
                $doc = $this->database->managersBySubsidiaryAgency->aggregate($pipeline)->toArray();
                if (empty($doc) || empty($doc[0]['techs'])) {
                    $query['_id'] = new \MongoDB\BSON\ObjectId('000000000000000000000000');
                } else {
                    $ids = array();
                    foreach ($doc[0]['techs'] as $id) { $ids[] = new \MongoDB\BSON\ObjectId((string)$id); }
                    if (isset($query['_id']) && is_array($query['_id']) && isset($query['_id']['$in'])) {
                        // intersection si déjà un filtre _id
                        $query['_id']['$in'] = array_values(array_uintersect($query['_id']['$in'], $ids, function($a,$b){
                            return strcmp((string)$a, (string)$b);
                        }));
                    } else {
                        $query['_id'] = array('$in' => $ids);
                    }
                }
            } catch (\Exception $e) {
                error_log("Erreur buildQuery manager scope: " . $e->getMessage());
            }
        }

        return $query;
    }

    public function validateAndAdjustFilters(array $newValues): array
    {
        $adjustedValues = array_merge($this->currentValues, $newValues);

        // Ordre de priorité pour la validation (amont → aval)
        $priorityOrder = array('subsidiary', 'agency', 'technicianId', 'level', 'brand');

        foreach ($priorityOrder as $filterName) {
            if (!isset($this->filters[$filterName])) {
                continue;
            }

            $currentValue = isset($adjustedValues[$filterName]) ? $adjustedValues[$filterName] : 'all';

            if ($currentValue === 'all') {
                continue;
            }

            // Contraintes courantes pour ce filtre
            $constraints = array();
            foreach ($adjustedValues as $k => $v) {
                if ($k !== $filterName) $constraints[$k] = $v;
            }

            $availableOptions = $this->filters[$filterName]->getAvailableOptions(
                $this->database,
                $constraints
            );

            if (!$this->filters[$filterName]->isValid($currentValue, $availableOptions)) {
                $adjustedValues[$filterName] = 'all';
                $adjustedValues = $this->resetDependentFilters($adjustedValues, $filterName);
            }
        }

        return $adjustedValues;
    }

    private function resetDependentFilters(array $values, string $changedFilter): array
    {
        $dependencies = array(
            'subsidiary' => array('agency', 'technicianId', 'level', 'brand'),
            'agency'     => array('technicianId', 'level', 'brand'),
            'technicianId'=>array('level', 'brand'),
            'level'      => array('brand'),
            'brand'      => array()
        );

        if (isset($dependencies[$changedFilter])) {
            foreach ($dependencies[$changedFilter] as $dependentFilter) {
                if (!$this->isExplicitSelection($dependentFilter)) {
                    $values[$dependentFilter] = 'all';
                }
            }
        }

        return $values;
    }

    private function isExplicitSelection(string $filterName): bool
    {
        // Hook potentiel pour tracer les actions utilisateur
        return false;
    }

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

    public function getLevelStats(): array
    {
        $baseQuery = $this->buildQuery();

        $stats = array(
            'juniorCount' => 0,
            'seniorCount' => 0,
            'expertCount' => 0
        );

        try {
            $stats['juniorCount'] = $this->database->users->countDocuments(array_merge($baseQuery, array('level' => 'Junior')));
            $stats['seniorCount'] = $this->database->users->countDocuments(array_merge($baseQuery, array('level' => 'Senior')));
            $stats['expertCount'] = $this->database->users->countDocuments(array_merge($baseQuery, array('level' => 'Expert')));
        } catch (\Exception $e) {
            error_log("Erreur FilterSystem::getLevelStats: " . $e->getMessage());
        }

        return $stats;
    }

    public function withPermissions(array $userSession): self
    {
        $decoratedFilters = array();

        foreach ($this->filters as $name => $filter) {
            $decoratedFilters[$name] = new PermissionFilterDecorator($filter, $userSession);
        }

        return new FilterSystem($this->database, $decoratedFilters);
    }

    public function withCache(int $ttl = 300): self
    {
        $cachedFilters = array();

        foreach ($this->filters as $name => $filter) {
            $cachedFilters[$name] = new CacheFilterDecorator($filter, $ttl);
        }

        return new FilterSystem($this->database, $cachedFilters);
    }

    public function getCurrentValues(): array
    {
        return $this->currentValues;
    }

    public function hasFilter(string $filterName): bool
    {
        return isset($this->filters[$filterName]);
    }

    public function getFilter(string $filterName): ?FilterInterface
    {
        return isset($this->filters[$filterName]) ? $this->filters[$filterName] : null;
    }
}

/**
 * Contrôleur de filtres refactorisé utilisant le nouveau système
 */
class RefactoredFilterController
{
    private $filterSystem;
    private $database;

    public function __construct($database = null)
    {
        $this->database = $database;

        // Construire le système de filtres complet avec permissions et cache
        $this->filterSystem = (new FilterSystemBuilder($database))
            ->buildComplete()
            ->withPermissions(isset($_SESSION) ? $_SESSION : array())
            ->withCache(300);
    }

    public function getFilters(): array
    {
        // Récupération des valeurs depuis l'URL
        $rawFilters = array(
            'subsidiary'   => isset($_GET['subsidiary']) ? $_GET['subsidiary'] : (isset($_GET['country']) ? $_GET['country'] : 'all'),
            'agency'       => isset($_GET['agency']) ? $_GET['agency'] : 'all',
            'managerId'    => isset($_GET['managerId']) ? $_GET['managerId'] : 'all',
            'level'        => isset($_GET['level']) ? $_GET['level'] : 'all',
            'brand'        => isset($_GET['brand']) ? $_GET['brand'] : 'all',
            'technicianId' => isset($_GET['technicianId']) ? $_GET['technicianId'] : 'all'
        );

        // Appliquer les restrictions utilisateur
        $rawFilters = $this->applyUserRestrictions($rawFilters);

        // Valider et ajuster
        $validatedFilters = $this->filterSystem
            ->setValues($rawFilters)
            ->validateAndAdjustFilters($rawFilters);

        // Mise à jour du système
        $this->filterSystem->setValues($validatedFilters);

        // Enrichissement UI
        return $this->enrichFiltersForUI($validatedFilters);
    }

    private function applyUserRestrictions(array $filters): array
    {
        $profile = isset($_SESSION['profile']) ? $_SESSION['profile'] : '';

        if ($profile === 'Manager') {
            $filters['managerId'] = isset($_SESSION['id']) ? $_SESSION['id'] : 'all';
        } elseif (!in_array($profile, array('Super Admin', 'Group Director'), true)) {
            $filters['subsidiary'] = isset($_SESSION['subsidiary']) ? $_SESSION['subsidiary'] : 'all';
        }

        return $filters;
    }

    private function enrichFiltersForUI(array $filters): array
    {
        foreach (array_keys($filters) as $filterName) {
            if ($this->filterSystem->hasFilter($filterName)) {
                $optionsKey = 'available' . ucfirst($filterName) . 's';
                $filters[$optionsKey] = $this->filterSystem->getAvailableOptions($filterName);
            }
        }

        $filters['levelStats'] = $this->filterSystem->getLevelStats();

        return $filters;
    }

    public function getSubsidiaries(): array
    {
        return $this->filterSystem->getAvailableOptions('subsidiary');
    }

    public function getAgencies(string $subsidiary): array
    {
        $tempFilters = $this->filterSystem->getCurrentValues();
        $tempFilters['subsidiary'] = $subsidiary;

        return $this->filterSystem
            ->setValues($tempFilters)
            ->getAvailableOptions('agency');
    }

    public function getTechnicians(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }

        return $this->filterSystem->getAvailableOptions('technicianId');
    }

    public function getBrands(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }

        return $this->filterSystem->getAvailableOptions('brand');
    }

    public function getTechnicianIds(array $filters): array
    {
        return $this->filterSystem
            ->setValues($filters)
            ->getTechnicianIds();
    }

    public function getLevelStats(array $filters = array()): array
    {
        if (!empty($filters)) {
            $this->filterSystem->setValues($filters);
        }

        return $this->filterSystem->getLevelStats();
    }

    public function canSelectSubsidiary(): bool
    {
        return in_array(isset($_SESSION['profile']) ? $_SESSION['profile'] : '', array('Super Admin', 'Group Director'), true);
    }

    public static function withDefaults(array $filters = array()): array
    {
        return array_merge(array(
            'subsidiary' => 'all',
            'agency' => 'all',
            'managerId' => 'all',
            'level' => 'all',
            'brand' => 'all',
            'technicianId' => 'all',
            'availableBrands' => array(),
        ), $filters);
    }
}
