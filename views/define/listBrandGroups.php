<?php
session_start();
include_once "../language.php";

// Vérif Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";

// Connexion MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$collection = $academy->nonSupportedGroupsByBrandLevel;

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_msg = "";

/*********************************************************
 * 1) Pipeline agrégation
 *********************************************************/
$pipeline = [
    [
        '$facet' => [
            'supportedDoc' => [
                [ '$match' => [ 'supportedGroups' => [ '$exists' => true ] ] ],
                [ '$project' => [ '_id' => 0, 'supportedGroups' => 1 ] ]
            ],
            'brandDocs' => [
                [
                    '$match' => [
                        'brand'  => [ '$exists' => true ],
                        'levels' => [ '$exists' => true ]
                    ]
                ]
            ]
        ]
    ],
    [
        '$project' => [
            'supportedGroups' => [
                '$arrayElemAt' => [ '$supportedDoc.supportedGroups', 0 ]
            ],
            'brandDocs' => '$brandDocs'
        ]
    ],
    [ '$unwind' => '$brandDocs' ],
    [
        '$project' => [
            '_id'   => 0,
            'brand' => '$brandDocs.brand',
            'levels'=> [
                '$map' => [
                    'input' => '$brandDocs.levels',
                    'as'    => 'lvl',
                    'in' => [
                        'level'         => '$$lvl.level',
                        'nonSupported'  => '$$lvl.groups',
                        'supportedGroups' => [
                            '$setDifference' => [
                                '$supportedGroups',
                                '$$lvl.groups'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];

$cursor      = $collection->aggregate($pipeline);
$brandGroups = iterator_to_array($cursor);

/*********************************************************
 * 2) Mise à jour sur clic "refresh"
 *********************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = "Erreur de validation du formulaire (CSRF).";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'refresh') {
            try {
                foreach ($brandGroups as $doc) {
                    $brandName = $doc['brand'];
                    $levels    = $doc['levels'];
                    
                    foreach ($levels as $lvl) {
                        $lvlName = $lvl['level'];
                        
                        // Convertir le champ "supportedGroups" calculé
                        $supportedBson = $lvl['supportedGroups'] ?? [];
                        $supportedArr  = ($supportedBson instanceof \MongoDB\Model\BSONArray)
                            ? $supportedBson->getArrayCopy()
                            : (array)$supportedBson;
                        
                        // Chercher le doc en base
                        $existingDoc = $collection->findOne([
                            'brand'         => $brandName,
                            'levels.level'  => $lvlName
                        ]);
                        if (!$existingDoc) {
                            continue;
                        }
                        
                        // Chercher l'élément "levels[i]" correspondant
                        $existingLevel = null;
                        if (isset($existingDoc['levels']) && is_array($existingDoc['levels'])) {
                            foreach ($existingDoc['levels'] as $oneLvl) {
                                if (($oneLvl['level'] ?? '') === $lvlName) {
                                    $existingLevel = $oneLvl;
                                    break;
                                }
                            }
                        }
                        
                        // Ancienne liste
                        $oldSupported = $existingLevel['supportedGroups'] ?? [];
                        if ($oldSupported instanceof \MongoDB\Model\BSONArray) {
                            $oldSupported = $oldSupported->getArrayCopy();
                        }
                        
                        // Comparaison
                        $diff1 = array_diff($supportedArr, $oldSupported);
                        $diff2 = array_diff($oldSupported, $supportedArr);
                        $areDifferent = (count($diff1) > 0 || count($diff2) > 0);
                        
                        if ($areDifferent) {
                            // On met à jour l'élément correspondant (le premier)
                            // au lieu d'utiliser arrayFilters, on utilise l'opérateur positionnel "$"
                            $collection->updateOne(
                                [
                                    'brand'         => $brandName,
                                    'levels.level'  => $lvlName
                                ],
                                [
                                    '$set' => [
                                        'levels.$.supportedGroups' => $supportedArr
                                    ]
                                ]
                            );
                        }
                    }
                }
                $success_msg = "Vérification/Mise à jour effectuée.";
            } catch (Exception $e) {
                $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        } else {
            $errors[] = "Action non reconnue.";
        }
    }
}
?>
<?php include_once "partials/header.php"; ?>

<!-- Titre -->
<title>Groupes Fonctionnels | CFAO Mobility Academy</title>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-5">
            <h1 class="my-3 text-center">Groupes Fonctionnels par Marque</h1>

            <!-- Alertes -->
            <?php if (!empty($success_msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong><?php echo htmlspecialchars($success_msg); ?></strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <?php if (!empty($errors)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul>
                        <?php foreach ($errors as $error) { ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php } ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <!-- Formulaire de refresh -->
            <div class="text-center mb-4">
                <form method="post" action="listBrandGroups.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="refresh">
                    <button type="submit" class="btn btn-primary">
                        Vérifier / Mettre à jour
                    </button>
                </form>
            </div>

            <!-- Tableau principal -->
            <div class="table-responsive table-container">
                <table id="brandGroupsTable" class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Marque</th>
                            <th>Niveau</th>
                            <th>Groupes Non Supportés</th>
                            <th>Groupes Supportés (calculés)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($brandGroups as $doc) {
                        $brandName = $doc['brand'];
                        $levels    = $doc['levels'];

                        foreach ($levels as $lvl) {
                            $lvlName = $lvl['level'];

                            // Convertir potentiels BSONArray
                            $nonSupportedBson = $lvl['nonSupported'];
                            $supportedBson    = $lvl['supportedGroups'];
                            $nonSupportedArr  = ($nonSupportedBson instanceof \MongoDB\Model\BSONArray)
                                ? $nonSupportedBson->getArrayCopy()
                                : (array)$nonSupportedBson;
                            $supportedArr     = ($supportedBson instanceof \MongoDB\Model\BSONArray)
                                ? $supportedBson->getArrayCopy()
                                : (array)$supportedBson;

                            $nonSupportedCount = count($nonSupportedArr);
                            $supportedCount    = count($supportedArr);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($brandName); ?></td>
                                <td><?php echo htmlspecialchars($lvlName); ?></td>
                                <td>
                                    <strong>(<?php echo $nonSupportedCount; ?>)</strong><br>
                                    <?php echo implode(', ', array_map('htmlspecialchars', $nonSupportedArr)); ?>
                                </td>
                                <td>
                                    <strong>(<?php echo $supportedCount; ?>)</strong><br>
                                    <?php echo implode(', ', array_map('htmlspecialchars', $supportedArr)); ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include_once "partials/footer.php"; ?>
