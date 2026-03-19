<?php
namespace MyApp\DataCollection;

use MongoDB\Client;
use MongoDB\Exception\Exception;

class DataCollection
{
    private $client;
    private $database;
    private $collectionManagers; // managersBySubsidiaryAgency
    private $collectionScores;   // technicianBrandScores

    /**
     * Constructeur
     *
     * @param string $uri   URI de connexion MongoDB
     * @param string $dbName Nom de la base de données
     */
    public function __construct($uri = "mongodb://localhost:27017", $dbName = "academy")
    {
        try {
            $this->client = new Client($uri);
            $this->database = $this->client->$dbName;

            $this->collectionManagers = $this->database->managersBySubsidiaryAgency;
            $this->collectionScores   = $this->database->technicianBrandScores;
        } catch (Exception $e) {
            throw new \RuntimeException("Erreur de connexion MongoDB: " . $e->getMessage());
        }
    }

    /**
     * Méthode principale pour récupérer la hiérarchie et les agrégations
     *   Filiale -> Agences -> Managers -> Techniciens
     *   + agrégations (score sum et count) pour calculer les moyennes par brand/level
     *
     * @return array Structure hiérarchique + agrégateurs
     */
    public function fetchHierarchyAndAggregations(): array
    {
        $cursor = $this->collectionManagers->find([]);
        $result = [];

        foreach ($cursor as $document) {
            $subsidiary = $document['subsidiary']; // Nom de la filiale
            $agencies   = $document['agencies'];   // Liste des agences

            // Initialiser la structure de la filiale s'il le faut
            if (!isset($result[$subsidiary])) {
                $result[$subsidiary] = [
                    'agencies'   => [],
                    'aggregator' => [], // somme+count pour la filiale
                ];
            }

            // Parcourir les agences
            foreach ($agencies as $agency) {
                $agencyName = $agency['_id']; // Nom de l'agence

                // Initialiser la structure de l’agence
                if (!isset($result[$subsidiary]['agencies'][$agencyName])) {
                    $result[$subsidiary]['agencies'][$agencyName] = [
                        'managers'   => [],
                        'aggregator' => [], // somme+count pour toute l’agence
                    ];
                }

                // Parcourir les managers
                foreach ($agency['managers'] as $manager) {
                    $managerName = $manager['firstName'] . " " . $manager['lastName'];

                    // Agrégateur pour ce manager (somme+count de tous ses techniciens)
                    $managerAggregator = [];

                    // Récupération des techniciens
                    $techniciansList = []; // IMPORTANT : initialiser avant la boucle
                    if (isset($manager['technicians'])) {
                        foreach ($manager['technicians'] as $technician) {
                            $technicianId   = $technician['_id'];
                            $technicianName = $technician['firstName'] . " " . $technician['lastName'];

                            // Récupération des marques (distinctBrands) 
                            $brands = isset($technician['distinctBrands']) 
                                ? (array) $technician['distinctBrands'] 
                                : [];

                            // Chercher dans technicianBrandScores si ce technicien a un document
                            $scoreDoc = $this->collectionScores->findOne(['userId' => $technicianId]);

                            // Tableau de scores par niveau (juste pour info, si besoin de l’affichage)
                            $scoresByLevel = [];
                            if ($scoreDoc && isset($scoreDoc['scores'])) {
                                foreach ($scoreDoc['scores'] as $level => $brandsScores) {
                                    $tempBrandScore = [];
                                    foreach ($brandsScores as $brandName => $scoreDetails) {
                                        $avgTotal = isset($scoreDetails['averageTotal']) 
                                            ? (float) $scoreDetails['averageTotal'] 
                                            : 0; // ou "N/A"
                                        $tempBrandScore[$brandName] = $avgTotal;

                                        // -- AJOUT DANS L'AGRÉGATEUR DU MANAGER --
                                        $this->addScoreToAggregator($managerAggregator, $level, $brandName, $avgTotal);
                                    }
                                    $scoresByLevel[$level] = $tempBrandScore;
                                }
                            }

                            // Conserver l'info du technicien
                            $techniciansList[] = [
                                'id'           => $technicianId,
                                'name'         => $technicianName,
                                'brands'       => $brands,
                                'scoresLevels' => $scoresByLevel
                            ];
                        }
                    }

                    // Créer l’entrée manager
                    $managerEntry = [
                        'name'        => $managerName,
                        'technicians' => $techniciansList,
                        'aggregator'  => $managerAggregator, 
                    ];

                    // Ajouter le manager dans la liste
                    $result[$subsidiary]['agencies'][$agencyName]['managers'][] = $managerEntry;

                    // Fusionner l'agrégateur du manager dans celui de l'agence
                    $this->mergeAggregatorsAccurate(
                        $result[$subsidiary]['agencies'][$agencyName]['aggregator'],
                        $managerAggregator
                    );
                } // fin foreach manager

                // Fusionner l'agrégateur de l’agence dans celui de la filiale
                $this->mergeAggregatorsAccurate(
                    $result[$subsidiary]['aggregator'],
                    $result[$subsidiary]['agencies'][$agencyName]['aggregator']
                );
            } // fin foreach agencies
        } // fin foreach cursor

        return $result;
    }

    /**
     * Calcule les moyennes par level/brand dans un agrégateur
     *   et ajoute éventuellement un noeud "ALL" regroupant tous les niveaux.
     *
     * @param array $aggregator
     * @param bool $withAllLevel
     * @return array
     */
    public function finalizeAverages(array $aggregator, bool $withAllLevel = true): array
    {
        $result = [];
        $allLevelAggregator = [];

        foreach ($aggregator as $level => $brandArr) {
            foreach ($brandArr as $brand => $vals) {
                $count = ($vals['count'] === 0) ? 1 : $vals['count'];
                $avg   = round($vals['sum'] / $count, 2);

                // On stocke la moyenne dans $result[$level][$brand]
                $result[$level][$brand] = $avg;

                // Agrégation pour "ALL"
                if ($withAllLevel) {
                    if (!isset($allLevelAggregator[$brand])) {
                        $allLevelAggregator[$brand] = ['sum' => 0, 'count' => 0];
                    }
                    $allLevelAggregator[$brand]['sum']   += $vals['sum'];
                    $allLevelAggregator[$brand]['count'] += $vals['count'];
                }
            }
        }

        if ($withAllLevel) {
            foreach ($allLevelAggregator as $brand => $vals) {
                $countAll = ($vals['count'] === 0) ? 1 : $vals['count'];
                $avgAll   = round($vals['sum'] / $countAll, 2);
                $result['ALL'][$brand] = $avgAll;
            }
        }

        return $result;
    }

    // ==========================
    // Fonctions utilitaires
    // ==========================

    /**
     * Ajout d'un score (somme + count) dans l'agrégateur
     *
     * @param array  $aggregator
     * @param string $level
     * @param string $brand
     * @param float  $avg
     */
    private function addScoreToAggregator(array &$aggregator, string $level, string $brand, float $avg): void
    {
        if (!isset($aggregator[$level])) {
            $aggregator[$level] = [];
        }
        if (!isset($aggregator[$level][$brand])) {
            $aggregator[$level][$brand] = ['sum' => 0, 'count' => 0];
        }
        $aggregator[$level][$brand]['sum']   += $avg;
        $aggregator[$level][$brand]['count'] += 1;
    }

    /**
     * Fusion (addition) de deux agrégateurs : on ajoute sum et count.
     *
     * @param array &$dest
     * @param array $source
     */
    private function mergeAggregatorsAccurate(array &$dest, array $source): void
    {
        foreach ($source as $level => $brandArr) {
            if (!isset($dest[$level])) {
                $dest[$level] = [];
            }
            foreach ($brandArr as $brand => $vals) {
                if (!isset($dest[$level][$brand])) {
                    $dest[$level][$brand] = ['sum' => 0, 'count' => 0];
                }
                $dest[$level][$brand]['sum']   += $vals['sum'];
                $dest[$level][$brand]['count'] += $vals['count'];
            }
        }
    }

    // ==========================
    // EXEMPLE d'utilisation (commenté)
    // ==========================
    /*
    public function exampleUsage()
    {
        // 1) Récupérer la hiérarchie + agrégations
        $data = $this->fetchHierarchyAndAggregations();

        // 2) Parcourir le résultat
        foreach ($data as $subsidiary => $subsidiaryData) {
            // echo "Subsidiary: ".$subsidiary."\n";

            // A) Avoir la moyenne globale de la filiale
            $subsidiaryAverages = $this->finalizeAverages($subsidiaryData['aggregator'], true);
            // echo "  Filiale brand/level averages:\n";
            // print_r($subsidiaryAverages);

            // B) Parcourir les agences
            foreach ($subsidiaryData['agencies'] as $agencyName => $agencyData) {
                // echo "   Agency: $agencyName\n";
                $agencyAverages = $this->finalizeAverages($agencyData['aggregator'], true);
                // echo "     Agency brand/level averages:\n";
                // print_r($agencyAverages);

                // C) Parcourir les managers
                foreach ($agencyData['managers'] as $managerInfo) {
                    $managerName = $managerInfo['name'];
                    // echo "       Manager: $managerName\n";
                    $managerAvg = $this->finalizeAverages($managerInfo['aggregator'], true);
                    // echo "         Manager brand/level averages:\n";
                    // print_r($managerAvg);

                    // D) Parcourir les techniciens du manager
                    foreach ($managerInfo['technicians'] as $tech) {
                        // echo "         Technician: {$tech['name']}\n";
                        // echo "           Distinct Brands: ".implode(',', $tech['brands'])."\n";
                        // print_r($tech['scoresLevels']);
                    }
                }
            }
        }
    }
    */
}
