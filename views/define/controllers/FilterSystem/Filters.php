<?php
/**
 * Filters.php
 * PHP 7.3 — MongoDB 4.2
 *
 * Contient :
 * - MongoCompat (helper de comptage portable)
 * - FilterInterface
 * - AbstractFilter (base + helpers communs)
 * - Filtres concrets : SubsidiaryFilter, AgencyFilter, TechnicianFilter, LevelFilter, BrandFilter
 * - Décorateurs : FilterDecorator, PermissionFilterDecorator, CacheFilterDecorator
 *
 * Aucune dépendance à FilterSystem. À inclure avant FilterSystem.php.
 */

require_once __DIR__ . '/../../components/subsidiaryFilter.php';

/**
 * Helper compat Mongo (fallback si countDocuments indisponible).
 */
final class MongoCompat
{
    /**
     * Compte les documents d'une collection, compatible vieux drivers.
     * @param \MongoDB\Collection $collection
     * @param array $filter
     * @return int
     */
    public static function count($collection, array $filter): int
    {
        if (method_exists($collection, 'countDocuments')) {
            try { return (int) $collection->countDocuments($filter); }
            catch (\Throwable $e) { /* fallback aggregate */ }
        }
        $cursor = $collection->aggregate([
            ['$match' => $filter],
            ['$count' => 'n'],
        ]);
        foreach ($cursor as $doc) { return (int) ($doc['n'] ?? 0); }
        return 0;
    }
}

/**
 * Interface pour les filtres.
 */
interface FilterInterface
{
    /**
     * Applique le filtre à la requête MongoDB.
     * @param array $query
     * @param mixed $value
     * @return array
     */
    public function apply(array $query, $value): array;

    /**
     * Options disponibles en fonction d'autres contraintes.
     * @param mixed $database  (attendu : $client->selectDatabase('academy'))
     * @param array $constraints
     * @return array
     */
    public function getAvailableOptions($database, array $constraints = []): array;

    /**
     * Valide une valeur contre les options disponibles.
     * @param mixed $value
     * @param array $availableOptions
     * @return bool
     */
    public function isValid($value, array $availableOptions = []): bool;

    /**
     * Nom du filtre.
     * @return string
     */
    public function getName(): string;
}

/**
 * Classe de base pour les filtres.
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
            // au moins une valeur présente dans les options
            $haystack = is_array($availableOptions) && array_keys($availableOptions) !== range(0, count($availableOptions) - 1)
                ? array_keys($availableOptions)
                : $availableOptions;
            return !empty(array_intersect($value, $haystack));
        }

        // $availableOptions peut être liste (brands) ou map (technicians)
        if (is_array($availableOptions) && array_keys($availableOptions) !== range(0, count($availableOptions) - 1)) {
            return isset($availableOptions[$value]) || in_array($value, $availableOptions, true);
        }

        return in_array($value, $availableOptions, true);
    }

    /**
     * Base query : uniquement Techniciens actifs (évite de polluer les facettes).
     */
    protected function getBaseTechnicianQuery(): array
    {
        return ['profile' => 'Technicien', 'active' => true];
    }

    /**
     * Convertit un id en ObjectId.
     * @param mixed $id
     * @return \MongoDB\BSON\ObjectId
     */
    protected function toObjectId($id): \MongoDB\BSON\ObjectId
    {
        return $id instanceof \MongoDB\BSON\ObjectId ? $id : new \MongoDB\BSON\ObjectId($id);
    }

    /**
     * Hiérarchie des niveaux (≤ niveau).
     * Junior -> ['Junior']
     * Senior -> ['Junior','Senior']
     * Expert -> ['Junior','Senior','Expert']
     */
    protected function getLevelHierarchy(string $level): array
    {
        switch ($level) {
            case 'Junior': return ['Junior'];
            case 'Senior': return ['Junior', 'Senior'];
            case 'Expert': return ['Junior', 'Senior', 'Expert'];
            default:       return ['Junior', 'Senior', 'Expert'];
        }
    }

    /**
     * Ajoute une contrainte de marques.
     * - $value string : OR sur les champs brandJunior|brandSenior|brandExpert
     * - $value array  : ALL → AND de clauses OR (chaque marque doit exister quelque part)
     */
    protected function addBrandConstraint(array $query, $value): array
    {
        if ($value === 'all' || empty($value)) {
            return $query;
        }
        $values = is_array($value) ? array_values(array_filter($value, 'strlen')) : [$value];
        if (empty($values)) return $query;

        $and = [];
        foreach ($values as $v) {
            $and[] = [
                '$or' => [
                    ['brandJunior' => $v],
                    ['brandSenior' => $v],
                    ['brandExpert' => $v],
                ]
            ];
        }

        if (isset($query['$and'])) {
            $query['$and'] = array_merge($query['$and'], $and);
        } else {
            $query['$and'] = $and;
        }
        return $query;
    }

    /**
     * Restreint la requête aux subordonnés d’un manager (via managersBySubsidiaryAgency).
     */
    protected function applyManagerConstraint(array $query, $database, $managerId): array
    {
        if (empty($managerId) || $managerId === 'all') {
            return $query;
        }
        try {
            $oid = $this->toObjectId($managerId);
            $pipeline = [
                ['$unwind' => '$agencies'],
                ['$unwind' => '$agencies.managers'],
                ['$match'  => ['agencies.managers.-_id' => $oid]],
                ['$unwind' => '$agencies.managers.technicians'],
                ['$group'  => ['_id' => null, 'techs' => ['$addToSet' => '$agencies.managers.technicians.-_id']]],
                ['$project'=> ['_id' => 0, 'techs' => 1]]
            ];
            $doc = $database->managersBySubsidiaryAgency->aggregate($pipeline)->toArray();
            if (empty($doc) || empty($doc[0]['techs'])) {
                // match impossible
                $query['_id'] = new \MongoDB\BSON\ObjectId('000000000000000000000000');
                return $query;
            }
            $ids = [];
            foreach ($doc[0]['techs'] as $id) { $ids[] = new \MongoDB\BSON\ObjectId((string)$id); }

            if (isset($query['_id'])) {
                if (is_array($query['_id']) && isset($query['_id']['$in'])) {
                    // intersection si _id déjà présent
                    $query['_id']['$in'] = array_values(array_uintersect($query['_id']['$in'], $ids, function($a,$b){
                        return strcmp((string)$a, (string)$b);
                    }));
                } else {
                    $query['_id'] = ['$in' => $ids];
                }
            } else {
                $query['_id'] = ['$in' => $ids];
            }
        } catch (\Exception $e) {
            error_log("Erreur applyManagerConstraint: " . $e->getMessage());
        }
        return $query;
    }
}

/**
 * Filtre : Subsidiary.
 */
class SubsidiaryFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            $query['subsidiary'] = resolveSubsidiaryMongoFilter((string) $value);
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') continue;
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            return $database->users->distinct('subsidiary', $query);
        } catch (\Exception $e) {
            error_log("Erreur SubsidiaryFilter::getAvailableOptions: " . $e->getMessage());
            return [];
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
                    $ids = [];
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = ['$in' => $ids];
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = ['$in' => $this->getLevelHierarchy($filterValue)];
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre : Agency.
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

        // Dépend de subsidiary
        if (!isset($constraints['subsidiary']) || $constraints['subsidiary'] === 'all') {
            return [];
        }
        $query['subsidiary'] = resolveSubsidiaryMongoFilter((string) $constraints['subsidiary']);

        foreach ($constraints as $filterName => $filterValue) {
            if (in_array($filterName, ['subsidiary', $this->name], true) || $filterValue === 'all') continue;
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            $agencies = $database->users->distinct('agency', $query);
            $agencies = array_map('trim', $agencies);
            $agencies = array_values(array_unique(array_filter($agencies, 'strlen')));
            return $agencies;
        } catch (\Exception $e) {
            error_log("Erreur AgencyFilter::getAvailableOptions: " . $e->getMessage());
            // Fallback éventuel sur fichier statique
            $file = __DIR__ . '/../components/agencyData.php';
            if (is_file($file)) {
                $agencyMap = include $file;
                return isset($agencyMap[$constraints['subsidiary']]) ? $agencyMap[$constraints['subsidiary']] : [];
            }
            return [];
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
                    $ids = [];
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = ['$in' => $ids];
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = ['$in' => $this->getLevelHierarchy($filterValue)];
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre : Technician.
 */
class TechnicianFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            if (is_array($value)) {
                $ids = [];
                foreach ($value as $id) { $ids[] = $this->toObjectId($id); }
                $query['_id'] = ['$in' => $ids];
            } else {
                $query['_id'] = $this->toObjectId($value);
            }
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') continue;
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            $cursor = $database->users->find($query, [
                'projection' => ['_id' => 1, 'firstName' => 1, 'lastName' => 1]
            ]);

            $technicians = [];
            foreach ($cursor as $tech) {
                $technicians[(string)$tech['_id']] =
                    (isset($tech['firstName']) ? $tech['firstName'] : '') . ' ' .
                    (isset($tech['lastName']) ? $tech['lastName'] : '');
            }

            return $technicians;
        } catch (\Exception $e) {
            error_log("Erreur TechnicianFilter::getAvailableOptions: " . $e->getMessage());
            return [];
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = resolveSubsidiaryMongoFilter((string) $filterValue);
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'level':
                $query['level'] = ['$in' => $this->getLevelHierarchy($filterValue)];
                break;
            case 'brand':
                $query = $this->addBrandConstraint($query, $filterValue);
                break;
        }
        return $query;
    }
}

/**
 * Filtre : Level.
 */
class LevelFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        if ($value !== 'all' && !empty($value)) {
            $query['level'] = ['$in' => $this->getLevelHierarchy($value)];
        }
        return $query;
    }

    public function getAvailableOptions($database, array $constraints = []): array
    {
        $query = $this->getBaseTechnicianQuery();

        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') continue;
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            // Si technicien choisi → downset du niveau du tech
            if (isset($constraints['technicianId']) && $constraints['technicianId'] !== 'all') {
                $techId = is_array($constraints['technicianId']) ? $constraints['technicianId'][0] : $constraints['technicianId'];
                $tech = $database->users->findOne(
                    ['_id' => $this->toObjectId($techId), 'profile' => 'Technicien', 'active' => true],
                    ['projection' => ['level' => 1]]
                );
                if ($tech && isset($tech['level'])) {
                    return $this->getLevelHierarchy((string)$tech['level']);
                }
            }

            $levels = $database->users->distinct('level', $query);
            $maxLevel = $this->determineMaxLevel($levels);
            return $this->getLevelHierarchy($maxLevel);

        } catch (\Exception $e) {
            error_log("Erreur LevelFilter::getAvailableOptions: " . $e->getMessage());
            return ['Junior', 'Senior', 'Expert'];
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = resolveSubsidiaryMongoFilter((string) $filterValue);
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = [];
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = ['$in' => $ids];
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
        // Junior < Senior < Expert
        $rank = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
        $maxV = 0; $maxL = 'Junior';
        foreach ($levels as $l) {
            $v = isset($rank[$l]) ? $rank[$l] : 0;
            if ($v > $maxV) { $maxV = $v; $maxL = $l; }
        }
        return $maxL;
    }
}

/**
 * Filtre : Brand.
 */
class BrandFilter extends AbstractFilter
{
    public function apply(array $query, $value): array
    {
        // gère mono/multi (ALL) via helper
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

        // Contraintes des autres filtres
        foreach ($constraints as $filterName => $filterValue) {
            if ($filterName === $this->name || $filterValue === 'all') continue;
            $query = $this->applyConstraint($query, $filterName, $filterValue, $database);
        }

        try {
            // champs à unir selon le level (héritage ≤ level)
            $level = (isset($constraints['level']) && $constraints['level'] !== 'all') ? $constraints['level'] : 'Expert';
            $levels = $this->getLevelHierarchy($level);
            $fields = [];
            foreach ($levels as $lvl) {
                $fields[] = ($lvl === 'Junior' ? 'brandJunior' : ($lvl === 'Senior' ? 'brandSenior' : 'brandExpert'));
            }

            $unionParts = [];
            foreach ($fields as $f) {
                $unionParts[] = ['$ifNull' => ['$'.$f, []]];
            }

            $pipeline = [
                ['$match' => $query],
                ['$project' => [
                    '_id' => 0,
                    'allBrands' => ['$setUnion' => $unionParts]
                ]],
                ['$unwind' => '$allBrands'],
                ['$group' => ['_id' => '$allBrands']],
                ['$sort'  => ['_id' => 1]],
                ['$project' => ['_id' => 0, 'brand' => '$_id']]
            ];

            $res = $database->users->aggregate($pipeline);
            $brands = [];
            foreach ($res as $r) { $brands[] = $r['brand']; }
            return $brands;

        } catch (\Exception $e) {
            error_log("Erreur BrandFilter::getAvailableOptions: " . $e->getMessage());
            return [];
        }
    }

    private function getTechnicianBrands($database, $technicianId, $level = null): array
    {
        try {
            $techId = is_array($technicianId) ? $technicianId[0] : $technicianId;

            $tech = $database->users->findOne(
                ['_id' => $this->toObjectId($techId), 'profile' => 'Technicien', 'active' => true],
                ['projection' => ['brandJunior' => 1, 'brandSenior' => 1, 'brandExpert' => 1]]
            );

            if (!$tech) return [];

            if ($level && $level !== 'all') {
                $map = ['Junior' => 'brandJunior', 'Senior' => 'brandSenior', 'Expert' => 'brandExpert'];
                $field = isset($map[$level]) ? $map[$level] : null;
                $arr = $field ? $this->normalizeBrands(isset($tech[$field]) ? $tech[$field] : []) : [];
                return $arr;
            }

            $brands = array_unique(array_merge(
                $this->normalizeBrands(isset($tech['brandJunior']) ? $tech['brandJunior'] : []),
                $this->normalizeBrands(isset($tech['brandSenior']) ? $tech['brandSenior'] : []),
                $this->normalizeBrands(isset($tech['brandExpert']) ? $tech['brandExpert'] : [])
            ));

            sort($brands, SORT_FLAG_CASE | SORT_STRING);
            return $brands;

        } catch (\Exception $e) {
            error_log("Erreur BrandFilter::getTechnicianBrands: " . $e->getMessage());
            return [];
        }
    }

    private function applyConstraint(array $query, string $filterName, $filterValue, $database): array
    {
        switch ($filterName) {
            case 'subsidiary':
                $query['subsidiary'] = resolveSubsidiaryMongoFilter((string) $filterValue);
                break;
            case 'agency':
                $query['agency'] = $filterValue;
                break;
            case 'managerId':
                $query = $this->applyManagerConstraint($query, $database, $filterValue);
                break;
            case 'technicianId':
                if (is_array($filterValue)) {
                    $ids = [];
                    foreach ($filterValue as $id) { $ids[] = $this->toObjectId($id); }
                    $query['_id'] = ['$in' => $ids];
                } else {
                    $query['_id'] = $this->toObjectId($filterValue);
                }
                break;
            case 'level':
                $query['level'] = ['$in' => $this->getLevelHierarchy($filterValue)];
                break;
        }
        return $query;
    }

    private function normalizeBrands($raw): array
    {
        if ($raw instanceof \MongoDB\Model\BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $v) {
            $v2 = trim((string)$v);
            if ($v2 !== '') $out[] = $v2;
        }
        return array_values(array_unique($out));
    }
}

/**
 * Décorateur de base.
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
 * Décorateur permissions (limite options selon profil).
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

        if (in_array($profile, ['Super Admin', 'Group Director'], true)) {
            return $options;
        }

        $userSubsidiary = isset($this->userSession['subsidiary']) ? $this->userSession['subsidiary'] : '';
        return in_array($userSubsidiary, $options, true) ? [$userSubsidiary] : [];
    }

    private function filterManagerOptions(array $options): array
    {
        $profile = isset($this->userSession['profile']) ? $this->userSession['profile'] : '';

        if ($profile === 'Manager') {
            $managerId = isset($this->userSession['id']) ? $this->userSession['id'] : '';
            return isset($options[$managerId]) ? [$managerId => $options[$managerId]] : [];
        }

        return $options;
    }
}


