<?php
/*******************************************************
 * managers_info.php
 * Exemple de récupération des données agrégées MongoDB
 * et affichage en PHP.
 *******************************************************/

// 1. Charger l’autoload de Composer (si vous l’utilisez)
require "../../vendor/autoload.php";

// 2. Importer la classe Client
use MongoDB\Client;

try {
    // 3. Créer une instance de Client pour se connecter à Mongo
    //    Remplacez la chaîne de connexion selon vos paramètres (host, user, pass, etc.)
    $client = new Client("mongodb://localhost:27017");

    // 4. Sélection de la base de données et de la collection
    $db = $client->selectDatabase("ma_base"); // Nom de votre base
    $collection = $db->selectCollection("managerTrainingStats"); // Nom de la collection agrégée

    // -----------------------------------------------------------
    // 5. Récupération du paramètre (par ex. ?manager=MUTOMBO)
    //    Si aucun paramètre n’est fourni, on affichera TOUS les managers.
    // -----------------------------------------------------------
    $managerLastName = isset($_GET['manager']) ? trim($_GET['manager']) : '';

    if ($managerLastName !== '') {
        // a) On cherche un manager par son "lastName"
        $cursor = $collection->find([
            'lastName' => $managerLastName
        ]);
    } else {
        // b) Sinon, on récupère TOUS les managers
        $cursor = $collection->find();
    }

    // 6. Parcourir les documents trouvés (un document = un manager)
    foreach ($cursor as $doc) {
        echo "<h2>Manager : " 
             . htmlspecialchars($doc['firstName'] . ' ' . $doc['lastName']) 
             . "</h2>";

        // Exemple d’affichage basique des principaux champs
        echo "<ul>";
        echo "<li><strong>_id :</strong> " . htmlspecialchars((string)$doc['_id']) . "</li>";
        echo "<li><strong>totalManagers :</strong> " . ($doc['totalManagers'] ?? 0) . "</li>";
        echo "<li><strong>totalTechnicians :</strong> " . ($doc['totalTechnicians'] ?? 0) . "</li>";
        echo "<li><strong>totalDistinctBrands :</strong> " . ($doc['totalDistinctBrands'] ?? 0) . "</li>";
        echo "<li><strong>totalTrainings :</strong> " . ($doc['totalTrainings'] ?? 0) . "</li>";
        echo "</ul>";

        // ----- totalTrainingsByLevel -----
        if (isset($doc['totalTrainingsByLevel']) && is_array($doc['totalTrainingsByLevel'])) {
            echo "<h3>Répartition Globale par Niveau</h3>";
            echo "<ul>";
            foreach ($doc['totalTrainingsByLevel'] as $level => $count) {
                echo "<li>Niveau <strong>" 
                     . htmlspecialchars($level) 
                     . "</strong> : " 
                     . (int)$count 
                     . "</li>";
            }
            echo "</ul>";
        }

        // ----- totalTrainingsByBrandAndLevel -----
        if (isset($doc['totalTrainingsByBrandAndLevel']) && is_array($doc['totalTrainingsByBrandAndLevel'])) {
            echo "<h3>Répartition Globale par Marque et Niveau</h3>";
            echo "<ul>";
            foreach ($doc['totalTrainingsByBrandAndLevel'] as $brand => $brandData) {
                echo "<li>";
                echo "<strong>Marque :</strong> " . htmlspecialchars($brand);
                echo "<ul>";
                echo "<li>totalByBrand : " . ($brandData['totalByBrand'] ?? 0) . "</li>";
                
                // totalByLevel est un objet associatif {level -> count}
                if (isset($brandData['totalByLevel'])) {
                    echo "<li>Répartition par Niveau :";
                    echo "<ul>";
                    foreach ($brandData['totalByLevel'] as $lvl => $nbr) {
                        echo "<li>Niveau <strong>$lvl</strong> : $nbr</li>";
                    }
                    echo "</ul></li>";
                }
                echo "</ul></li>";
            }
            echo "</ul>";
        }

        // ----- Liste des Techniciens -----
        if (isset($doc['technicians']) && is_array($doc['technicians'])) {
            echo "<h3>Techniciens Gérés par ce Manager</h3>";

            foreach ($doc['technicians'] as $tech) {
                $techName = htmlspecialchars($tech['firstName'] . ' ' . $tech['lastName']);
                echo "<h4>Technicien : $techName</h4>";
                
                echo "<ul>";
                // Marques distinctes
                if (isset($tech['distinctBrands']) && is_array($tech['distinctBrands'])) {
                    echo "<li><strong>Marques Distinctes :</strong> ";
                    echo implode(", ", array_map('htmlspecialchars', $tech['distinctBrands']));
                    echo "</li>";
                }

                echo "<li><strong>Total Distinct Brands :</strong> " 
                     . ($tech['totalDistinctBrands'] ?? 0) 
                     . "</li>";

                echo "<li><strong>Total Trainings :</strong> " 
                     . ($tech['totalTrainings'] ?? 0) 
                     . "</li>";

                // Répartition par niveau pour ce tech
                if (isset($tech['trainingsByLevel']) && is_array($tech['trainingsByLevel'])) {
                    echo "<li><strong>Trainings par Level :</strong>";
                    echo "<ul>";
                    foreach ($tech['trainingsByLevel'] as $lvl => $cnt) {
                        echo "<li>Niveau <strong>$lvl</strong> : $cnt</li>";
                    }
                    echo "</ul></li>";
                }

                // Répartition par brand & level pour ce tech
                if (isset($tech['trainingsByBrandAndLevel']) && is_array($tech['trainingsByBrandAndLevel'])) {
                    echo "<li><strong>Trainings par Brand et Level :</strong>";
                    echo "<ul>";
                    foreach ($tech['trainingsByBrandAndLevel'] as $b => $objLevel) {
                        echo "<li>Marque <strong>$b</strong>: ";
                        // $objLevel est un objet { level => count }
                        $tmp = [];
                        foreach ($objLevel as $lvl => $countLvl) {
                            $tmp[] = "$lvl=$countLvl";
                        }
                        echo implode(', ', $tmp);
                        echo "</li>";
                    }
                    echo "</ul></li>";
                }

                echo "</ul>";
            }
        }

        echo "<hr>"; // Séparateur entre managers
    }

} catch (Exception $e) {
    echo "Erreur de connexion ou d'exécution : " . $e->getMessage();
}
?>
