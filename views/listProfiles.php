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

$success_msg = ''; // Message de succès
$errors = []; // Tableau pour les erreurs

/**
 * Validates if a given string is a valid MongoDB ObjectId.
 *
 * This function checks if the provided string matches the format of a MongoDB ObjectId,
 * which is a 24-character hexadecimal string.
 *
 * @param string $id The string to be validated as a MongoDB ObjectId.
 *
 * @return bool Returns true if the string is a valid MongoDB ObjectId, false otherwise.
 */
function isValidObjectId($id) {
    return preg_match('/^[a-fA-F0-9]{24}$/', $id) === 1;
}


// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Erreur de validation du formulaire.";
    } else {
        // Action à effectuer (create, edit, delete, import)
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                // Création d'un profil
                $profileName = trim($_POST["name"] ?? '');
                $profileDescription = trim($_POST["description"] ?? '');
                $profileIcon = trim($_POST["icon"] ?? '');
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
                        'created' => date("d-m-Y H:i:s")
                    ];

                    // Insérer le profil dans la collection
                    $profilesCollection->insertOne($profileData);

                    $success_msg = "Le profil a été créé avec succès.";
                }
                break;

            case 'edit':
                // Modification d'un profil
                $profileId = $_POST['profile_id'] ?? '';

                // Validation de l'ID
                if (!isValidObjectId($profileId)) {
                    $errors[] = "ID de profil invalide.";
                } else {
                    $profileObjectId = new MongoDB\BSON\ObjectId($profileId);

                    $profileName = trim($_POST["name"] ?? '');
                    $profileDescription = trim($_POST["description"] ?? '');
                    $profileIcon = trim($_POST["icon"] ?? '');
                    $isActive = isset($_POST["active"]) ? true : false;

                    // Validation des données
                    if (empty($profileName)) {
                        $errors[] = "Le champ nom est obligatoire.";
                    }

                    if (empty($errors)) {
                        // Mettre à jour le profil
                        $profilesCollection->updateOne(
                            ['_id' => $profileObjectId],
                            ['$set' => [
                                'name' => $profileName,
                                'description' => $profileDescription,
                                'icon' => $profileIcon,
                                'active' => $isActive,
                                'updated' => date("d-m-Y H:i:s")
                            ]]
                        );

                        $success_msg = "Le profil a été mis à jour avec succès.";
                    }
                }
                break;

            case 'delete':
                // Suppression d'un profil
                $profileId = $_POST['profile_id'] ?? '';

                // Validation de l'ID
                if (!isValidObjectId($profileId)) {
                    $errors[] = "ID de profil invalide.";
                } else {
                    $profileObjectId = new MongoDB\BSON\ObjectId($profileId);

                    // Vérifier que le profil existe
                    $profile = $profilesCollection->findOne(['_id' => $profileObjectId]);

                    if (!$profile) {
                        $errors[] = "Le profil n'existe pas.";
                    } else {
                        // Vérifier s'il y a des utilisateurs associés à ce profil
                        $userCount = $usersCollection->count(['profile' => $profile['name']]);

                        if ($userCount > 0) {
                            $errors[] = "Impossible de supprimer le profil car des utilisateurs y sont associés.";
                        } else {
                            // Supprimer le profil
                            $profilesCollection->deleteOne(['_id' => $profileObjectId]);
                            $success_msg = "Le profil a été supprimé avec succès.";
                        }
                    }
                }
                break;

            case 'import':
                 // Importation de profils depuis un fichier Excel

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
                                        'created' => date("d-m-Y H:i:s"),
                                        'updated' => date("d-m-Y H:i:s")
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
                break;
                

            default:
                $errors[] = "Action non reconnue.";
                break;
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
?>
<?php
include_once "partials/header.php";
?>

<!-- Inclure les styles nécessaires -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

<title>Liste des profils | CFAO Mobility Academy</title>

<style>

</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-5">
            <h1 class="my-3 text-center">Liste des profils</h1>
            <br>

            <!-- Messages de succès et d'erreur -->
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
                <!-- Bouton d'importation -->
                <button class="btn btn-primary" data-toggle="modal" data-target="#importProfileModal">
                    <i class="fas fa-file-import"></i> Importer des profils
                </button>
                <!-- Bouton d'exportation -->
                <button class="btn btn-info" id="exportMainTable">
                    <i class="fas fa-file-export"></i> Exporter les profils
                </button>
            </div>
            <br><br>

            <!-- Tableau des profils -->
            <div class="table-responsive table-container">
                <table id="profilesTable" class="table table-hover">
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
                                    <!-- Bouton Modifier -->
                                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editProfileModal<?php echo $index; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <!-- Formulaire de suppression -->
                                    <form action="listProfiles.php" method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="profile_id" value="<?php echo $profile['_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce profil ?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
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

<!-- Modale pour la création d'un profil -->
<div class="modal fade" id="createProfileModal" tabindex="-1" role="dialog" aria-labelledby="createProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <!-- Formulaire de création de profil -->
            <form method="POST" action="listProfiles.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Créer un profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
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
                </div>
                <div class="modal-footer">
                    <!-- Boutons -->
                    <button type="submit" class="btn btn-primary">Créer le profil</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modale pour l'importation de profils -->
<div class="modal fade" id="importProfileModal" tabindex="-1" role="dialog" aria-labelledby="importProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <!-- Formulaire d'importation de profils -->
            <form enctype="multipart/form-data" method="POST" action="listProfiles.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="import">
                <div class="modal-header">
                    <h5 class="modal-title">Importer des profils</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Champs du formulaire -->
                    <div class="form-group">
                        <label for="excelFile">Importer les profils via Excel</label>
                        <div class="input-group">
                            <input type="file" class="form-control" name="excel" accept=".xls,.xlsx" required />
                            <div class="input-group-append">
                                <span class="input-group-text">.xls / .xlsx</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- Boutons -->
                    <button type="submit" class="btn btn-primary">Importer</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modales pour modifier les profils -->
<?php foreach ($profiles as $index => $profile) { ?>
    <div class="modal fade" id="editProfileModal<?php echo $index; ?>" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel<?php echo $index; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <!-- Formulaire de modification du profil -->
                <form method="POST" action="listProfiles.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="profile_id" value="<?php echo $profile['_id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier le profil : <?php echo htmlspecialchars($profile['name']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
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
                    </div>
                    <div class="modal-footer">
                        <!-- Boutons -->
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Modales pour la liste des utilisateurs -->
<?php foreach ($profiles as $index => $profile) { ?>
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
                    <!-- Bouton d'exportation des utilisateurs -->
                    <button class="btn btn-info mb-3" onclick="exportUsersTableToExcel('usersTable<?php echo $index; ?>', 'Utilisateurs_<?php echo htmlspecialchars($profile['name']); ?>')">
                        <i class="fas fa-file-export"></i> Exporter les utilisateurs
                    </button>
                    <!-- Tableau des utilisateurs -->
                    <table id="usersTable<?php echo $index; ?>" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Prénom</th>
                                <th>Nom</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = $usersCollection->find(['profile' => $profile['name']]);
                            $lineNumber = 1;
                            foreach ($users as $user) {
                            ?>
                                <tr>
                                    <td><?php echo $lineNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($user['firstName'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['lastName'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <!-- Bouton Fermer -->
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Inclusion des scripts nécessaires -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>
<script>
    $(document).ready(function() {
        // Exportation Excel pour le tableau principal
        $("#exportMainTable").on("click", function() {
            TableToExcel.convert(document.getElementById("profilesTable"), {
                name: "Profils.xlsx",
                sheet: {
                    name: "Profils"
                }
            });
        });
    });

    // Fonction pour exporter le tableau des utilisateurs
    function exportUsersTableToExcel(tableID, filename = '') {
        let table = document.getElementById(tableID);
        TableToExcel.convert(table, {
            name: filename + '.xlsx',
            sheet: {
                name: 'Utilisateurs'
            }
        });
    }
</script>

<?php include_once "partials/footer.php"; ?>
