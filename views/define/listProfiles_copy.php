<?php
session_start();
include_once "../language.php";

// Vérifier si l'utilisateur est authentifié en tant que Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}


require_once "../../vendor/autoload.php";

// Connexion à MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$profilesCollection = $academy->profiles;
$usersCollection = $academy->users;
$functionalitiesCollection = $academy->functionalities;

$success_msg = ''; // Message de succès
$errors = []; // Tableau pour les erreurs

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Suppression d'un profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    var_dump($_POST['delete']); // Pour le débogage

    // Vérifier le token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Erreur de validation du formulaire.";
    } else {
        try {
            $deleteValue = $_POST['delete'];

            // Vérifier que l'ID est valide
            if (strlen($deleteValue) !== 24 || !ctype_xdigit($deleteValue)) {
                throw new Exception("ID de profil invalide format.");
            }

            $profileId = new MongoDB\BSON\ObjectId($deleteValue);

            // Vérifier que le profil existe
            $profile = $profilesCollection->findOne(['_id' => $profileId]);
            if ($profile) {
                // Vérifier s'il y a des utilisateurs associés à ce profil
                $userCount = $usersCollection->countDocuments(['profile' => $profile['name']]);
                if ($userCount > 0) {
                    $errors[] = "Impossible de supprimer le profil car des utilisateurs y sont associés.";
                } else {
                    $profilesCollection->deleteOne(['_id' => $profileId]);
                    $success_msg = "Le profil a été supprimé avec succès.";
                }
            } else {
                $errors[] = "Le profil n'existe pas.";
            }
        } catch (Exception $e) {
            $errors[] = "ID de profil invalide. Valeur reçue : " . htmlspecialchars($_POST['delete']) . ". Erreur : " . $e->getMessage();
        }
    }
}


// Récupérer les profils avec le nombre d'utilisateurs et de fonctionnalités
$pipeline = [
    [
        '$lookup' => [
            'from' => 'users',
            'localField' => 'name',
            'foreignField' => 'profile',
            'as' => 'users'
        ]
    ],
    [
        '$addFields' => [
            'userCount' => ['$size' => '$users'],
            'functionalityCount' => ['$size' => ['$ifNull' => ['$functionalities', []]]]
        ]
    ],
    [
        '$project' => [
            'users' => 1, // Inclure les utilisateurs pour l'affichage dans les modales
            'name' => 1,
            'description' => 1,
            'icon' => 1,
            'userCount' => 1,
            'functionalityCount' => 1,
            'active' => 1,
            'created_at' => 1,
            'updated_at' => 1
        ]
    ]
];

$profilesCursor = $profilesCollection->aggregate($pipeline);
$profiles = iterator_to_array($profilesCursor);

// Calculer le total des utilisateurs et des fonctionnalités
$totalUsers = 0;
$totalFunctionalities = 0;
foreach ($profiles as $profile) {
    $totalUsers += $profile['userCount'];
    $totalFunctionalities += $profile['functionalityCount'];
}

include_once "partials/header.php";
?>

<!-- Inclure les styles nécessaires -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">


<title>Liste des profils | CFAO Mobility Academy</title>

<style>
    /* Ajuster la couleur de l'en-tête du tableau */
    thead {
        background-color: #edf2f7;
    }
    /* Style pour le bouton d'exportation Excel */
    .export-btn {
        margin-bottom: 15px;
    }
    /* Centrer le tableau */
    .table-container {
        margin: 0 auto;
        width: 100%;
        height: 100%;
    }
    /* Ajuster la hauteur des modales */
    .modal-dialog-centered {
        display: flex;
        align-items: center;
        min-height: calc(100% - 1rem);
    }
    .modal-content {
        max-height: 65vh;
        overflow-y: auto;
    }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-5">
            <h1 class="my-3 text-center">Liste des profils</h1>
<br>
            <?php if (!empty($success_msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong><?php echo $success_msg; ?></strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <?php if (!empty($errors)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul>
                        <?php foreach ($errors as $error) { ?>
                            <li><?php echo $error; ?></li>
                        <?php } ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <!-- Boutons Importer et Ajouter centrés -->
            <div class="text-center mb-3">
                <button class="btn btn-success" data-toggle="modal" data-target="#createProfileModal">
                    <i class="fas fa-plus"></i> Ajouter un profil
                </button>
                <button class="btn btn-primary" data-toggle="modal" data-target="#importProfileModal">
                    <i class="fas fa-file-import"></i> Importer un profil
                </button>
            </div>
                            <br><br>
            <div class="table-responsive table-container">
                <table id="profilesTable" class="table  table-hover">
                    <thead>
                        <tr>
                            <th>Icône</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Utilisateurs</th>
                            <th>Fonctionnalités</th>
                            <th>Liste des utilisateurs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $index => $profile) { ?>
                            <tr>
                                <td class="text-center">
                                    <?php if (!empty($profile['icon'])) { ?>
                                        <i class="<?php echo htmlspecialchars($profile['icon']); ?> fa-2x"></i>
                                    <?php } else { ?>
                                        <i class="fas fa-user fa-2x"></i>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($profile['name']); ?></td>
                                <td><?php echo htmlspecialchars($profile['description'] ?? ''); ?></td>
                                <td class="text-center"><?php echo $profile['userCount']; ?></td>
                                <td class="text-center"><?php echo $profile['functionalityCount']; ?></td>
                                <td class="text-center">
                                    <?php if ($profile['userCount'] > 0) { ?>
                                        <button class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm" data-toggle="modal" data-target="#usersModal<?php echo $index; ?>">
                                            Voir les utilisateurs
                                        </button>
                                    <?php } else { ?>
                                        <span class="badge badge-secondary">Aucun utilisateur</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editProfileModal<?php echo $index; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>


                                    <button class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteModal" data-id="<?php echo htmlspecialchars((string)$profile['_id']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <!-- Ligne des Totaux -->
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Totaux :</strong></td>
                            <td class="text-center"><strong><?php echo $totalUsers; ?></strong></td>
                            <td class="text-center"><strong><?php echo $totalFunctionalities; ?></strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            

        </div>
    </div>
</div>
<!-- Bouton d'exportation Excel pour le tableau principal -->
<div class="text-right mt-3">
                <button id="exportMainTable" class="btn btn-light-primary">
                    <i class="fas fa-file-excel"></i>Excel
                </button>
            </div>
<!-- Modales pour la liste des utilisateurs (déplacées en dehors du tableau) -->
<?php foreach ($profiles as $index => $profile) { ?>
    <!-- Modal pour la liste des utilisateurs -->
    <div class="modal fade" id="usersModal<?php echo $index; ?>" tabindex="-1" role="dialog" aria-labelledby="usersModalLabel<?php echo $index; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Liste des utilisateurs du profil : <?php echo htmlspecialchars($profile['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Bouton d'exportation Excel -->
                    <button class="btn btn-secondary export-btn" onclick="exportTableToExcel('userTable<?php echo $index; ?>', 'Utilisateurs_<?php echo htmlspecialchars($profile['name']); ?>')">
                        <i class="fas fa-file-excel"></i> Exporter en Excel
                    </button>
                    <div id="printArea<?php echo $index; ?>">
                        <table id="userTable<?php echo $index; ?>" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Prénom</th>
                                    <th>Nom</th>
                                    <th>Niveau</th>
                                    <th>Pays</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $users = $usersCollection->find(['profile' => $profile['name']]);
                                $lineNumber = 1;
                                foreach ($users as $user) {
                                    // Déterminer la classe du badge en fonction du niveau
                                    $levelBadgeClass = '';
                                    $level = strtolower($user['level'] ?? '');
                                    switch ($level) {
                                        case 'junior':
                                            $levelBadgeClass = 'badge badge-light-success fs-7 m-1';
                                            break;
                                        case 'senior':
                                            $levelBadgeClass = 'badge badge-light-danger fs-7 m-1';
                                            break;
                                        case 'expert':
                                            $levelBadgeClass = 'badge badge-light-warning fs-7 m-1';
                                            break;
                                        default:
                                            $levelBadgeClass = 'badge badge-light-secondary fs-7 m-1';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $lineNumber++; ?></td>
                                        <td><?php echo htmlspecialchars($user['firstName'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($user['lastName'] ?? ''); ?></td>
                                        <td><span class="<?php echo $levelBadgeClass; ?>"><?php echo htmlspecialchars(ucfirst($level)); ?></span></td>
                                        <td><?php echo htmlspecialchars($user['country'] ?? ''); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Fin de la modal -->
<?php } ?>

<!-- Modales pour modifier les profils -->
<?php foreach ($profiles as $index => $profile) { ?>
    <div class="modal fade" id="editProfileModal<?php echo $index; ?>" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel<?php echo $index; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <?php
                // Contenu de editProfiles.php adapté pour la modal
                ?>
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le profil : <?php echo htmlspecialchars($profile['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php
                    // Gestion des erreurs et messages de succès pour la modification
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editProfile']) && $_POST['editProfile'] == (string)$profile['_id']) {
                        // Récupérer et échapper les données du formulaire
                        $profileName = htmlspecialchars(trim($_POST["name"] ?? ''));
                        $profileDescription = htmlspecialchars(trim($_POST["description"] ?? ''));
                        $profileIcon = htmlspecialchars(trim($_POST["icon"] ?? ''));
                        $isActive = isset($_POST["active"]) ? true : false;

                        // Validation des données
                        if (empty($profileName)) {
                            $errors[] = "Le champ nom est obligatoire.";
                        }

                        // Update the profile
                        if (empty($errors)) {
                            $updateResult = $profilesCollection->updateOne(
                                ['_id' => $profile['_id']],
                                ['$set' => [
                                    'name' => $profileName,
                                    'description' => $profileDescription,
                                    'icon' => $profileIcon,
                                    'active' => $isActive,
                                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                                ]]
                            );

                            $success_msg = "Le profil a été mis à jour avec succès.";

                            // Rafraîchir les données du profil
                            $profile = $profilesCollection->findOne(['_id' => $profile['_id']]);
                        }
                    }
                    ?>

                    <!-- Formulaire de modification du profil -->
                    <form method="POST" action="">
                        <!-- Ajouter un champ caché pour l'ID du profil -->
                        <input type="hidden" name="editProfile" value="<?php echo $profile['_id']; ?>">
                        <!-- Champs du formulaire -->
                        <div class="form-group">
                            <label for="name<?php echo $index; ?>">Nom du profil</label>
                            <input type="text" class="form-control" name="name" id="name<?php echo $index; ?>" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description<?php echo $index; ?>">Description du profil</label>
                            <textarea class="form-control" name="description" id="description<?php echo $index; ?>"><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="icon<?php echo $index; ?>">Icône du profil (classe Font Awesome)</label>
                            <input type="text" class="form-control" name="icon" id="icon<?php echo $index; ?>" value="<?php echo htmlspecialchars($profile['icon'] ?? ''); ?>">
                            <small class="form-text text-muted">Utilisez les classes d'icônes Font Awesome (par exemple, <code>fas fa-user</code>).</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" name="active" id="active<?php echo $index; ?>" <?php echo ($profile['active'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="active<?php echo $index; ?>">Activer le profil</label>
                        </div>
                        <!-- Boutons -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Modal pour la création d'un profil -->
<div class="modal fade" id="createProfileModal" tabindex="-1" role="dialog" aria-labelledby="createProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <?php
            // Contenu de createProfiles.php adapté pour la modal
            ?>
            <div class="modal-header">
                <h5 class="modal-title">Créer un profil</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                // Gestion des erreurs et messages de succès pour la création
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createProfile'])) {
                    // Récupérer et échapper les données du formulaire
                    $profileName = htmlspecialchars(trim($_POST["name"] ?? ''));
                    $profileDescription = htmlspecialchars(trim($_POST["description"] ?? ''));
                    $profileIcon = htmlspecialchars(trim($_POST["icon"] ?? ''));
                    $isActive = isset($_POST["active"]) ? true : false;

                    // Validation des données
                    if (empty($profileName)) {
                        $errors[] = "Le champ nom est obligatoire.";
                    }

                    // Vérifier si le profil existe déjà
                    $exist = $profilesCollection->findOne(['name' => $profileName]);

                    if ($exist) {
                        $errors[] = "Un profil avec ce nom existe déjà.";
                    }

                    if (empty($errors)) {
                        // Créer le document du profil
                        $profileData = [
                            'name' => $profileName,
                            'description' => $profileDescription,
                            'icon' => $profileIcon,
                            'functionalities' => [],
                            'active' => $isActive,
                            'created_at' => new MongoDB\BSON\UTCDateTime(),
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ];

                        // Insérer le profil dans la collection
                        $profilesCollection->insertOne($profileData);

                        $success_msg = "Le profil a été créé avec succès.";
                        // Réinitialiser les variables du formulaire
                        $profileName = $profileDescription = $profileIcon = '';
                    }
                }
                ?>

                <!-- Formulaire de création de profil -->
                <form method="POST" action="">
                    <!-- Champ caché pour identifier la soumission -->
                    <input type="hidden" name="createProfile" value="1">
                    <!-- Champs du formulaire -->
                    <div class="form-group">
                        <label for="nameCreate">Nom du profil</label>
                        <input type="text" class="form-control" name="name" id="nameCreate" required>
                    </div>
                    <div class="form-group">
                        <label for="descriptionCreate">Description du profil</label>
                        <textarea class="form-control" name="description" id="descriptionCreate"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="iconCreate">Icône du profil (classe Font Awesome)</label>
                        <input type="text" class="form-control" name="icon" id="iconCreate">
                        <small class="form-text text-muted">Utilisez les classes d'icônes Font Awesome (par exemple, <code>fas fa-user</code>).</small>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" name="active" id="activeCreate">
                        <label class="form-check-label" for="activeCreate">Activer le profil</label>
                    </div>
                    <!-- Boutons -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">Créer le profil</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour l'importation de profils -->
<div class="modal fade" id="importProfileModal" tabindex="-1" role="dialog" aria-labelledby="importProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <?php
            // Contenu de importProfiles.php adapté pour la modal
            ?>
            <div class="modal-header">
                <h5 class="modal-title">Importer des profils</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                // Gestion des erreurs et messages de succès pour l'importation
                if (isset($_POST["submitImport"])) {
                    // Vérifier si un fichier a été téléchargé
                    if (isset($_FILES["excel"]) && $_FILES["excel"]["error"] == 0) {
                        $fileName = $_FILES["excel"]["name"];
                        $fileTmpPath = $_FILES["excel"]["tmp_name"];
                        $fileSize = $_FILES["excel"]["size"];
                        $fileType = $_FILES["excel"]["type"];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        // Extensions autorisées
                        $allowedExtensions = ['xls', 'xlsx'];
                        if (in_array($fileExtension, $allowedExtensions)) {
                            try {
                                // Charger le fichier Excel
                                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
                                $data = $spreadsheet->getActiveSheet()->toArray();

                                // Vérifier si le fichier n'est pas vide
                                if (count($data) > 1) {
                                    // Supprimer la ligne d'en-tête
                                    unset($data[0]);

                                    foreach ($data as $row) {
                                        $profileName = trim($row[0]);
                                        $profileDescription = trim($row[1]);
                                        $profileIcon = trim($row[2]);
                                        $isActive = strtolower(trim($row[3])) == 'oui' ? true : false;

                                        // Valider les données
                                        if (empty($profileName)) {
                                            $errors[] = "Le nom du profil est obligatoire pour chaque entrée.";
                                            continue;
                                        }

                                        // Vérifier si le profil existe déjà
                                        $exist = $profilesCollection->findOne(['name' => $profileName]);
                                        if ($exist) {
                                            $errors[] = "Le profil '{$profileName}' existe déjà et n'a pas été importé à nouveau.";
                                            continue;
                                        }

                                        // Créer le document du profil
                                        $profileData = [
                                            'name' => $profileName,
                                            'description' => $profileDescription,
                                            'icon' => $profileIcon,
                                            'functionalities' => [],
                                            'active' => $isActive,
                                            'created_at' => new MongoDB\BSON\UTCDateTime(),
                                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                                        ];

                                        // Insérer le profil dans la collection
                                        $profilesCollection->insertOne($profileData);
                                    }
                                    $success_msg = "L'importation des profils a été effectuée avec succès.";
                                } else {
                                    $errors[] = "Le fichier Excel est vide.";
                                }
                            } catch (Exception $e) {
                                $errors[] = "Erreur lors de la lecture du fichier Excel : " . $e->getMessage();
                            }
                        } else {
                            $errors[] = "Extension de fichier non autorisée. Veuillez télécharger un fichier Excel (.xls ou .xlsx).";
                        }
                    } else {
                        $errors[] = "Veuillez sélectionner un fichier Excel à importer.";
                    }
                }
                ?>

                <!-- Formulaire d'importation de profils -->
                <form enctype="multipart/form-data" method="POST" action="">
                    <!-- Champ caché pour identifier la soumission -->
                    <input type="hidden" name="submitImport" value="1">
                    <div class="form-group">
                        <label for="excelFile">Importer les profils via Excel</label>
                        <div class="input-group">
                            <input type="file" class="form-control" name="excel" accept=".xls,.xlsx" required />
                            <div class="input-group-append">
                                <span class="input-group-text">.xls / .xlsx</span>
                            </div>
                        </div>
                    </div>
                    <!-- Boutons -->
                    <div class="text-center mt-4">
                        <button type="submit" name="submit" class="btn btn-primary">Importer</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour la confirmation de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <!-- Contenu de la modal -->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Zone pour les messages d'erreur -->
            <?php if (!empty($errors)) { ?>
                <div class="alert alert-danger m-3" role="alert">
                    <?php foreach ($errors as $error) { ?>
                        <p><?php echo $error; ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <!-- Formulaire de suppression -->
            <form action="listProfiles.php" method="post">
                <!-- Ajouter le token CSRF -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer ce profil ?
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="delete" id="deleteId" value="">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Inclure jQuery, Bootstrap JS et DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.3.js"
integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM=" crossorigin="anonymous"></script>

<!-- Inclure les autres scripts nécessaires -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Scripts DataTables -->
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<!-- Script pour l'exportation Excel -->
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>

<script>
    $(document).ready(function() {
        // Initialiser DataTables
         // Utiliser Event Delegation pour attacher l'événement
         
        $('#profilesTable').DataTable({
            "language": {
                "url": "#"
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 5, 6] } // Désactiver le tri sur les colonnes icône, liste des utilisateurs et actions
            ]
        });

        console.log("Script principal chargé et exécuté.");
        // Passer l'ID du profil au modal de suppression
// Vérifier l'existence du modal
        console.log("deleteModal exists:", $('#deleteModal').length);

        $(document).on('shown.bs.modal', '#deleteModal', function(event) {
            console.log("Événement 'show.bs.modal' déclenché pour la suppression.");
            var button = $(event.relatedTarget); // Bouton qui a déclenché le modal
            console.log("Bouton de suppression cliqué:", button);
            var profileId = button.data('id'); // Extraire l'ID du profil du bouton
            console.log("ID du profil à supprimer :", profileId);
            var modal = $(this);
            modal.find('#deleteId').val(profileId); // Définir la valeur dans le champ caché
        })


            // Exportation Excel pour le tableau principal
            $("#exportMainTable").on("click", function() {
                TableToExcel.convert(document.getElementById("profilesTable"), {
                    name: "Profiles.xlsx",
                    sheet: {
                        name: "Profiles"
                    }
                });
            });
        });

    // Fonction pour exporter un tableau en Excel
    function exportTableToExcel(tableID, filename = '') {
        let table = document.getElementById(tableID);
        TableToExcel.convert(table, {
            name: filename + '.xlsx',
            sheet: {
                name: 'Sheet 1'
            }
        });
    }
</script>

<?php include_once "partials/footer.php"; ?>
