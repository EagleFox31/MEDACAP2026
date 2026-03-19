<?php
session_start();
require_once "../../vendor/autoload.php";

// Vérification session
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

// Connexion MongoDB
$conn    = new MongoDB\Client("mongodb://localhost:27017");
$academy = $conn->academy;
$users   = $academy->users;
$results = $academy->results;

// 1) Charger tous les techniciens actifs
$cursorTech = $users->find([
   'active'  => true,
   'profile' => 'Technicien', // Adapté à votre structure
]);

$allTechnicians = [];

// 2) Construire la structure initiale
foreach ($cursorTech as $u) {
   $techId    = (string)$u['_id'];
   $managerId = (string)$u['manager'] ?? null;

   // Retrouver manager
   $managerDoc = null;
   if ($managerId) {
       $managerDoc = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($managerId)]);
   }
   $managerName = $managerDoc
       ? ($managerDoc['firstName'] . " " . $managerDoc['lastName'])
       : 'Aucun';

   // Récupérer les tableaux de marques
   $juniorBrands = $u['brandJunior'] ?? [];
   $seniorBrands = $u['brandSenior'] ?? [];
   $expertBrands = $u['brandExpert'] ?? [];

   // Déterminer quels niveaux le technicien a (juste pour la colonne “Niveau(x)”)
   $tempLevels = [];
   if (!empty($juniorBrands)) $tempLevels[] = 'Junior';
   if (!empty($seniorBrands)) $tempLevels[] = 'Senior';
   if (!empty($expertBrands)) $tempLevels[] = 'Expert';
   // On crée une chaîne du type "Junior, Senior, Expert"
   $myLevelsString = implode(', ', $tempLevels);

   // Initialiser la structure
   $allTechnicians[$techId] = [
      'firstName'   => $u['firstName'] ?? '',
      'lastName'    => $u['lastName']  ?? '',
      'managerName' => $managerName,
      'levelsString'=> $myLevelsString,  // la future colonne “Niveau(x)”
      'levels' => [
         'Junior' => [], 
         'Senior' => [],
         'Expert' => [],
      ]
   ];

   // Stocker des clés (marques) pour chaque niveau
   foreach ($juniorBrands as $b) {
      $allTechnicians[$techId]['levels']['Junior'][$b] = 0; 
   }
   foreach ($seniorBrands as $b) {
      $allTechnicians[$techId]['levels']['Senior'][$b] = 0; 
   }
   foreach ($expertBrands as $b) {
      $allTechnicians[$techId]['levels']['Expert'][$b] = 0; 
   }
}

// 3) Récupérer les 'results' => calculer moyenne par brand, par level
//    => Ex.: Factuel + Declaratif + Manager

// Imaginons qu'on ait un "numberTest" passé en GET (à adapter)
$numberTest = $_GET["numberTest"] ?? 1;

// On boucle sur $allTechnicians pour renseigner les scores
foreach ($allTechnicians as $techId => &$techData)
{
   $userObjId = new MongoDB\BSON\ObjectId($techId);

   // Pour chaque niveau
   foreach (['Junior','Senior','Expert'] as $lvl) {
      $marquesNiveau = array_keys($techData['levels'][$lvl]);
      if (empty($marquesNiveau)) {
         continue;
      }

      foreach ($marquesNiveau as $brand) {
         // Rechercher Factuel
         $resFac = $results->findOne([
            'user'       => $userObjId,
            'level'      => $lvl,
            'numberTest' => (int)$numberTest,
            'brand'      => $brand,
            'type'       => 'Factuel',
            'active'     => true,
         ]);
         $scoreFac  = $resFac['score']  ?? 0;
         $totalFac  = $resFac['total']  ?? 0;

         // Rechercher Declaratif
         $resDecla = $results->findOne([
            'user'       => $userObjId,
            'level'      => $lvl,
            'numberTest' => (int)$numberTest,
            'brand'      => $brand,
            'type'       => 'Declaratif',
            'active'     => true,
         ]);
         $scoreDecla = $resDecla['score'] ?? 0;
         $totalDecla = $resDecla['total'] ?? 0;

         // Rechercher Manager
         $resMan = $results->findOne([
            'user'       => $userObjId,
            'level'      => $lvl,
            'numberTest' => (int)$numberTest,
            'brand'      => $brand,
            'typeR'      => 'Managers',
            'active'     => true,
         ]);
         $scoreMan  = $resMan['score']  ?? 0;
         $totalMan  = $resMan['total']  ?? 0;

         // Calculer %
         $percentFac   = ($totalFac  > 0) ? ($scoreFac  * 100 / $totalFac)   : 0;
         $percentDecla = ($totalDecla> 0) ? ($scoreDecla* 100 / $totalDecla) : 0;
         $percentMan   = ($totalMan  > 0) ? ($scoreMan  * 100 / $totalMan)   : 0;

         // Moyenne
         $globalPercent = round(($percentFac + $percentDecla + $percentMan) / 3);

         // On le stocke
         $techData['levels'][$lvl][$brand] = $globalPercent;
      }
   }
}
// Fin de la boucle => $allTechnicians est “complet”
?>

<!DOCTYPE html>
<html>
<head>
   <meta charset="utf-8">
   <title>Tableau - Techniciens / Marques / Niveaux</title>
   <!-- Votre CSS, Bootstrap, etc. -->
   <style>
      table, th, td {
         border: 1px solid #ccc;
         border-collapse: collapse;
         padding: 8px;
      }
      th {
         background: #f2f2f2;
      }
      .bullet-list {
         list-style-type: disc;
         padding-left: 20px;
         margin: 0;
      }
   </style>
</head>
<body>

<h1>Liste des Techniciens : Marques & Niveaux</h1>

<table>
   <thead>
      <tr>
         <th>Technicien</th>
         <th>Manager</th>
         <th>Niveau(x)</th>  <!-- NOUVELLE COLONNE -->
         <th>Junior</th>
         <th>Senior</th>
         <th>Expert</th>
      </tr>
   </thead>

   <tbody>
   <?php
   // 4) Affichage final
   foreach ($allTechnicians as $techId => $techData) {
       $techName    = $techData['firstName'] . " " . $techData['lastName'];
       $managerName = $techData['managerName'];
       $levelsStr   = $techData['levelsString']; // ex. "Junior, Senior"

       echo "<tr>";
       // Colonne Technicien
       echo "<td>$techName</td>";
       // Colonne Manager
       echo "<td>$managerName</td>";
       // Colonne Niveau(x)
       echo "<td>$levelsStr</td>";

       // Pour chaque cellule (Junior, Senior, Expert), on liste les marques
       foreach (['Junior','Senior','Expert'] as $lvl) {
          $brandsScore = $techData['levels'][$lvl];
          // ex: ['FUSO'=>60, 'HINO'=>80]

          if (count($brandsScore)==0) {
             // pas de marques => on affiche un message
             echo "<td style='text-align:center;color:#aaa;'>(Aucune)</td>";
             continue;
          }

          echo "<td>";
          echo "<ul class='bullet-list'>";
          foreach ($brandsScore as $brandName => $percent) {
             // Lien vers un détail
             $link = "detailBrand.php?"
                   . "brand=".urlencode($brandName)
                   . "&user=".urlencode($techId)
                   . "&level=$lvl";

             echo "<li>";
             echo "<a href='$link' title='Détails de $brandName'>$brandName</a>";
             echo " : <strong>$percent%</strong>";
             echo "</li>";
          }
          echo "</ul>";
          echo "</td>";
       }

       echo "</tr>";
   }
   ?>
   </tbody>
</table>

</body>
</html>
