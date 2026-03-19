<?php
// coverageCalculator.php

/**
 * Classe de configuration pour injecter les paramètres du domaine.
 * Elle encapsule la logique d'accès aux données de config.
 */
class Configuration
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getFunctionalGroupsByLevel(): array
    {
        return $this->config['functionalGroupsByLevel'] ?? [];
    }

    public function getNonSupportedGroupsByBrand(): array
    {
        return $this->config['nonSupportedGroupsByBrand'] ?? [];
    }
}

/**
 * Interface pour la détection des besoins.
 */
interface NeedDetectorInterface
{
    public function hasNeed(?float $fact, ?float $decl): bool;
}

/**
 * Détecte un besoin si le score factuel ou déclaratif est en dessous d'un seuil donné.
 */
class NeedDetector implements NeedDetectorInterface
{
    private float $threshold;

    public function __construct(float $threshold = 80.0)
    {
        $this->threshold = $threshold;
    }

    public function hasNeed(?float $fact, ?float $decl): bool
    {
        $f = $fact === null ? 100.0 : $fact; // Par défaut, on considère l'absence de données comme un score élevé
        $d = $decl === null ? 100.0 : $decl;
        return ($f < $this->threshold) || ($d < $this->threshold);
    }
}

/**
 * Interface pour vérifier si une spécialité est supportée par une marque.
 */
interface BrandSupportCheckerInterface
{
    public function isSupported(string $brand, string $speciality): bool;
}

/**
 * Vérifie si une spécialité est supportée par une marque en se basant sur la config.
 */
class BrandSupportChecker implements BrandSupportCheckerInterface
{
    private Configuration $config;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function isSupported(string $brand, string $speciality): bool
    {
        $nonSupported = $this->config->getNonSupportedGroupsByBrand()[$brand] ?? [];
        return !in_array($speciality, $nonSupported, true);
    }
}

/**
 * Interface pour la collecte des besoins.
 */
interface NeedCollectorInterface
{
    /**
     * Identifie tous les besoins et retourne un tableau des résultats :
     * [
     *   'brandCoverage' => [brand][level][spec] = ['totalBesoin' => int, 'couvert' => int],
     *   'uniqueSpecialities' => [spec => true],
     *   'totalNeeds' => int
     * ]
     */
    public function collectNeeds(array $technicians, array $scores, array $levels): array;
}

/**
 * Collecte tous les besoins par technicien/niveau/spécialité/marque.
 */
class NeedCollector implements NeedCollectorInterface
{
    private NeedDetectorInterface $needDetector;
    private BrandSupportCheckerInterface $brandChecker;
    private Configuration $config;

    public function __construct(
        NeedDetectorInterface $needDetector,
        BrandSupportCheckerInterface $brandChecker,
        Configuration $config
    ) {
        $this->needDetector = $needDetector;
        $this->brandChecker = $brandChecker;
        $this->config = $config;
    }

    /**
     * @param array $technicians Liste des techniciens filtrés.
     * @param array $scores Scores factuels et déclaratifs.
     * @param array $levels Niveaux à considérer.
     * @return array Résultats de la collecte des besoins.
     */
    public function collectNeeds(array $technicians, array $scores, array $levels): array
    {
        $brandCoverage = [];
        $uniqueSpecialities = [];
        $totalNeeds = 0;

        foreach ($technicians as $tech) {
            $techId = (string)$tech['_id'];
            foreach ($levels as $level) {
                if (!isset($scores[$techId][$level])) {
                    error_log("No scores found for Technician ID: $techId at Level: $level");
                    continue;
                }

                $validBrands = $this->getValidBrandsByLevel($tech, $level);
                if (empty($validBrands)) {
                    error_log("No valid brands for Technician ID: $techId at Level: $level");
                    continue;
                }

                foreach ($scores[$techId][$level] as $spec => $scoreData) {
                    $fact = $scoreData['Factuel'] ?? 100;
                    $decl = $scoreData['Declaratif'] ?? 100;

                    if (!$this->needDetector->hasNeed($fact, $decl)) {
                        error_log("No need detected for Technician ID: $techId, Level: $level, Speciality: $spec");
                        continue;
                    }

                    $uniqueSpecialities[$spec] = true;

                    // Un besoin est évalué par marque
                    foreach ($validBrands as $brand) {
                        if (!$this->brandChecker->isSupported($brand, $spec)) {
                            continue;
                        }

                        if (!isset($brandCoverage[$brand][$level][$spec])) {
                            $brandCoverage[$brand][$level][$spec] = ['totalBesoin' => 0, 'couvert' => 0];
                        }
                        $brandCoverage[$brand][$level][$spec]['totalBesoin']++;
                        $totalNeeds++;
                    }
                }
            }
        }

        return [
            'brandCoverage' => $brandCoverage,
            'uniqueSpecialities' => $uniqueSpecialities,
            'totalNeeds' => $totalNeeds
        ];
    }

    /**
     * Récupère les marques valides pour un technicien à un niveau donné.
     * Accepte à la fois des tableaux et des objets.
     */
    private function getValidBrandsByLevel($technician, string $level): array
    {
        // Convertir l'objet en tableau associatif si nécessaire
        if (is_object($technician)) {
            if (method_exists($technician, 'getArrayCopy')) {
                $technician = $technician->getArrayCopy();
            } else {
                $technician = (array)$technician;
            }
        }

        $brandField = 'brand' . ucfirst($level);
        if (isset($technician[$brandField]) && is_array($technician[$brandField])) {
            return array_values(array_filter($technician[$brandField], function ($b) {
                return is_string($b) && trim($b) !== '';
            }));
        }

        return [];
    }
}

/**
 * Interface pour la détermination de la couverture.
 */
interface CoverageDeterminerInterface
{
    /**
     * Détermine la couverture des besoins à partir des formations recommandées.
     * Modifie $brandCoverage par référence.
     * Retourne ['totalCovered' => int, 'uniqueTrainingsUsed' => array]
     */
    public function determineCoverage(array &$brandCoverage, array $technicians, array $trainings, array $levels): array;
}

/**
 * Détermine la couverture des besoins grâce aux formations recommandées.
 */
class CoverageDeterminer implements CoverageDeterminerInterface
{
    public function determineCoverage(array &$brandCoverage, array $technicians, array $trainings, array $levels): array
    {
        $uniqueTrainingsUsed = [];
        $totalCovered = 0;

        foreach ($technicians as $tech) {
            $techId = (string)$tech['_id'];
            foreach ($levels as $level) {
                if (!isset($trainings[$techId][$level])) continue;

                foreach ($trainings[$techId][$level] as $trainingCode => $training) {
                    if (!isset($training['brand']) || !isset($training['speciality'])) {
                        continue;
                    }

                    $trainingBrand = $training['brand'];
                    $specialities = $this->normalizeSpecialities($training['speciality']);
                    $trainingUniqueCode = $training['code'] ?? $trainingCode;

                    foreach ($specialities as $spec) {
                        if (isset($brandCoverage[$trainingBrand][$level][$spec])) {
                            $brandCoverage[$trainingBrand][$level][$spec]['couvert']++;
                            $totalCovered++;
                            $uniqueTrainingsUsed[$trainingUniqueCode] = true;
                        }
                    }
                }
            }
        }

        return [
            'totalCovered' => $totalCovered,
            'uniqueTrainingsUsed' => $uniqueTrainingsUsed
        ];
    }

    private function normalizeSpecialities($specData): array
    {
        if ($specData instanceof MongoDB\Model\BSONArray) {
            return $specData->getArrayCopy();
        }

        if (!is_array($specData)) {
            return [];
        }

        return $specData;
    }
}

/**
 * Interface pour le calcul des KPI.
 */
interface KpiCalculatorInterface
{
    /**
     * Calcule les KPI finaux.
     * Retourne un tableau avec toutes les métriques nécessaires.
     */
    public function calculateKpi(
        array $brandCoverage,
        array $uniqueSpecialities,
        int $totalNeeds,
        int $totalCovered,
        array $uniqueTrainingsUsed,
        array $trainings
    ): array;
}

/**
 * Classe pour calculer la durée totale des formations recommandées.
 */
class DurationCalculator
{
    /**
     * Calcule la durée totale des formations.
     *
     * @param array $trainings Formations recommandées.
     * @return array Durée totale en jours et heures.
     */
    public function calculateTotalDuration(array $trainings): array
    {
        $totalDays = 0;
        $totalHours = 0;

        foreach ($trainings as $techId => $levelsData) {
            foreach ($levelsData as $level => $trainingList) {
                foreach ($trainingList as $trainingCode => $training) {
                    if (isset($training['durationDays'])) {
                        $totalDays += (int)$training['durationDays'];
                    }
                    if (isset($training['durationHours'])) {
                        $totalHours += (int)$training['durationHours'];
                    }
                }
            }
        }

        // Convertir les heures en jours si nécessaire (par exemple, 8 heures = 1 jour)
        $additionalDays = intdiv($totalHours, 8);
        $remainingHours = $totalHours % 8;
        $totalDays += $additionalDays;

        return [
            'totalDays' => $totalDays,
            'totalHours' => $remainingHours
        ];
    }
}

/**
 * Calcule les KPI finaux (pourcentage, totaux, répartitions, etc.).
 */
class KpiCalculator implements KpiCalculatorInterface
{
    private Configuration $config;
    private DurationCalculator $durationCalculator;

    public function __construct(Configuration $config, DurationCalculator $durationCalculator)
    {
        $this->config = $config;
        $this->durationCalculator = $durationCalculator;
    }

    public function calculateKpi(
        array $brandCoverage,
        array $uniqueSpecialities,
        int $totalNeeds,
        int $totalCovered,
        array $uniqueTrainingsUsed,
        array $trainings
    ): array {
        $totalSpecialitiesCount = count($uniqueSpecialities);
        $globalCoveragePercent = ($totalNeeds > 0) ? ($totalCovered / $totalNeeds) * 100 : 100;
        $numberOfRecommendedTrainings = count($uniqueTrainingsUsed);
        $nonCovered = $totalNeeds - $totalCovered;

        $allBrands = array_keys($this->config->getNonSupportedGroupsByBrand());
        list($coveredData, $uncoveredData) = $this->calculateBrandData($brandCoverage, $allBrands);

        // Calcul de la durée totale des formations
        $duration = $this->durationCalculator->calculateTotalDuration($trainings);

        $coverageDistribution = [
            'Couverts' => $totalCovered,
            'Non Couverts' => $nonCovered
        ];

        return [
            'globalCoveragePercent' => $globalCoveragePercent,
            'totalSpecialitiesCount' => $totalSpecialitiesCount,
            'totalNeeds' => $totalNeeds,
            'nonCovered' => $nonCovered,
            'numberOfRecommendedTrainings' => $numberOfRecommendedTrainings,
            'totalCovered' => $totalCovered,
            'allBrands' => $allBrands,
            'coveredData' => $coveredData,
            'uncoveredData' => $uncoveredData,
            'coverageDistribution' => $coverageDistribution,
            'totalDuration' => $duration // Ajout de la durée totale
        ];
    }

    private function calculateBrandData(array $brandCoverage, array $allBrands): array
    {
        $coveredData = [];
        $uncoveredData = [];

        foreach ($allBrands as $brand) {
            $totalBesoinsBrand = 0;
            $totalCouvertsBrand = 0;
            if (isset($brandCoverage[$brand])) {
                foreach ($brandCoverage[$brand] as $levelData) {
                    foreach ($levelData as $specData) {
                        $totalBesoinsBrand += $specData['totalBesoin'];
                        $totalCouvertsBrand += $specData['couvert'];
                    }
                }
            }

            $nonCouvertBrand = $totalBesoinsBrand - $totalCouvertsBrand;
            $coveredData[] = $totalCouvertsBrand;
            $uncoveredData[] = $nonCouvertBrand;
        }

        return [$coveredData, $uncoveredData];
    }
}

/**
 * Classe principale qui orchestre le calcul de la couverture.
 * Elle applique le principe de l'inversion de dépendances en injectant des interfaces.
 */
class CoverageCalculator
{
    private NeedCollectorInterface $needCollector;
    private CoverageDeterminerInterface $coverageDeterminer;
    private KpiCalculatorInterface $kpiCalculator;

    public function __construct(
        NeedCollectorInterface $needCollector,
        CoverageDeterminerInterface $coverageDeterminer,
        KpiCalculatorInterface $kpiCalculator
    ) {
        $this->needCollector = $needCollector;
        $this->coverageDeterminer = $coverageDeterminer;
        $this->kpiCalculator = $kpiCalculator;
    }

    /**
     * Calcule la couverture et retourne un tableau contenant:
     * - $brandCoverage détaillé
     * - Les KPI finaux
     */
    public function calculateCoverage(array $technicians, array $scores, array $trainings, array $levels): array
    {
        // Étape 1 : Identifier tous les besoins
        $needResult = $this->needCollector->collectNeeds($technicians, $scores, $levels);
        $brandCoverage = $needResult['brandCoverage'];
        $uniqueSpecialities = $needResult['uniqueSpecialities'];
        $totalNeeds = $needResult['totalNeeds'];

        // Étape 2 : Déterminer la couverture
        $coverageResult = $this->coverageDeterminer->determineCoverage($brandCoverage, $technicians, $trainings, $levels);
        $totalCovered = $coverageResult['totalCovered'];
        $uniqueTrainingsUsed = $coverageResult['uniqueTrainingsUsed'];

        // Étape 3 : Calculer les KPI
        $kpi = $this->kpiCalculator->calculateKpi($brandCoverage, $uniqueSpecialities, $totalNeeds, $totalCovered, $uniqueTrainingsUsed, $trainings);

        return [
            'brandCoverage' => $brandCoverage,
            'kpi' => $kpi
        ];
    }
}
?>
