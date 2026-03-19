<?php
session_start();
include_once "../language.php";
include_once "partials/header.php";

// Vérifier si l'utilisateur est authentifié en tant que Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";

// Connexion à MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$functionalitiesCollection = $academy->functionalities;
$profilesCollection = $academy->profiles; // Pour récupérer les profils disponibles

$success_msg = ''; // Message de succès
$errors = []; // Tableau pour les erreurs

// Fonction pour valider un ObjectId MongoDB
function isValidObjectId($id) {
    return preg_match('/^[a-fA-F0-9]{24}$/', $id) === 1;
}

function getFunctionalityDetails($functionalityId, $functionalitiesCollection, $profilesCollection) {
    global $errors; // Accéder au tableau des erreurs globales
    if (!isValidObjectId($functionalityId)) {
        $errors[] = "ID de fonctionnalité invalide.";
        return null;
    }
    $functionalityObjectId = new MongoDB\BSON\ObjectId($functionalityId);

    // Récupérer la fonctionnalité
    $functionality = $functionalitiesCollection->findOne(['_id' => $functionalityObjectId]);

    if (!$functionality) {
        return null;
    }

    // Récupérer les profils liés à cette fonctionnalité
    $associatedProfiles = [];
    $profilesCursor = $profilesCollection->find(['functionalities' => $functionalityObjectId]);
    foreach ($profilesCursor as $profile) {
        $associatedProfiles[] = $profile['name'];
    }

    // Récupérer les informations sur l'icône
    $icon = $functionality['icon'] ?? '';
    $iconType = $functionality['icon_type'] ?? '';

    return [
        'functionality' => $functionality,
        'associatedProfiles' => $associatedProfiles,
        'icon' => $icon,
        'icon_type' => $iconType
    ];
}

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$icons = [
    'font_awesome' => [
        ['value' => 'fas fa-users-cog', 'text' => 'Gestion des accès / Configuration des utilisateurs'],
        ['value' => 'fas fa-sliders-h', 'text' => 'Configuration des seuils'],
        ['value' => 'fas fa-bullseye', 'text' => 'Objectifs de compétences'],
        ['value' => 'fas fa-users', 'text' => 'Collaborateurs / Plans par équipe'],
        ['value' => 'fas fa-building', 'text' => 'Plans par filiale / Besoins par filiale'],
        ['value' => 'fas fa-user', 'text' => 'Plans individuels / Besoins individuels'],
        ['value' => 'fas fa-chart-pie', 'text' => 'Suivi des techniciens (progression)'],
        ['value' => 'fas fa-chart-bar', 'text' => 'Suivi par filiale (progression)'],
        ['value' => 'fas fa-chart-line', 'text' => 'Évolution des plans de groupe'],
        ['value' => 'fas fa-user-check', 'text' => 'Suivi individuel (progression)'],
        ['value' => 'fas fa-globe', 'text' => 'Besoins globaux (vue d\'ensemble des besoins)'],
        ['value' => 'fas fa-users fs-3', 'text' => 'Collaborateurs (dans les menus contextuels)'],
        ['value' => 'fas fa-chalkboard-teacher fs-2', 'text' => 'Évaluation ou tests des collaborateurs'],
        ['value' => 'fas fa-car-side', 'text' => 'Types de véhicules / Gestion des véhicules'],
        ['value' => 'fas fa-question', 'text' => 'Questions / Gestion des questions'],
        ['value' => 'fas fa-message', 'text' => 'Commentaires utilisateurs / Feedbacks'],
        ['value' => 'fas fa-address-book', 'text' => 'Résultats des tests'],
        ['value' => 'fas fa-laptop-code', 'text' => 'Questionnaires / Code informatique'],
        ['value' => 'fas fa-graduation-cap', 'text' => 'Formations / Chapeau de graduation'],
        ['value' => 'fas fa-edit', 'text' => 'Seuils de validation / Stylo (édition)'],
        ['value' => 'fas fa-refresh', 'text' => 'Activation / Rafraîchir'],
        ['value' => 'fas fa-exchange', 'text' => 'Réassignation des tests / Échange'],
        ['value' => 'fas fa-trash-alt', 'text' => 'Historique des éléments supprimés / Corbeille'],
        ['value' => 'fas fa-search-plus', 'text' => 'Résultats détaillés / Loupe'],
        ['value' => 'fa fa-line-chart', 'text' => 'État d\'avancement des agences / Graphique en ligne'],
        ['value' => 'fas fa-book', 'text' => 'Tableau des Groupes Fonctionnels (Explore)'],
    ],
    'ki_duotone' => [
        ['value' => 'ki-duotone ki-abstract-26', 'text' => 'Actions de planification (programmes)', 'paths' => 2],
        ['value' => 'ki-duotone ki-element-11', 'text' => 'Introduction ou tableau de bord', 'paths' => 4],
        ['value' => 'ki-duotone ki-black-left-line', 'text' => 'Minimisation ou navigation dans le menu latéral', 'paths' => 2],
        ['value' => 'ki-duotone ki-magnifier', 'text' => 'Barre de recherche dans l\'en-tête', 'paths' => 2],
        ['value' => 'ki-duotone ki-cross', 'text' => 'Bouton de fermeture pour réinitialisation ou modale', 'paths' => 2],
        ['value' => 'ki-duotone ki-gift', 'text' => 'Résultats des collaborateurs évalués', 'paths' => 4],
        ['value' => 'ki-duotone ki-code', 'text' => 'Changelog ou fonctionnalités techniques', 'paths' => 4],
        ['value' => 'ki-duotone ki-abstract-14', 'text' => 'Navigation ou bascule du menu latéral', 'paths' => 2],
        ['value' => 'ki-duotone ki-category', 'text' => 'Catégories', 'paths' => 4],
        ['value' => 'ki-duotone ki-gear', 'text' => 'Roue Dentée', 'paths' => 2],
        ['value' => 'ki-duotone ki-calendar', 'text' => 'Calendrier', 'paths' => 2],
        ['value' => 'ki-duotone ki-row-vertical', 'text' => 'Questionnaires (Explore)', 'paths' => 2],
        ['value' => 'ki-duotone ki-abstract-41', 'text' => 'Spécialités (Explore)', 'paths' => 2],
        // Ajoutez d'autres icônes si nécessaire
    ],
    'bi' => [
        ['value' => 'bi bi-person', 'text' => 'Utilisateur'],
        ['value' => 'bi bi-list', 'text' => 'Liste'],
        ['value' => 'bi bi-gear', 'text' => 'Paramètres'],
        ['value' => 'bi bi-bar-chart', 'text' => 'Statistiques'],
        ['value' => 'bi bi-house', 'text' => 'Accueil'],
        ['value' => 'bi bi-box-arrow-right', 'text' => 'Déconnexion'],
        // Ajoutez d'autres icônes si nécessaire
    ],
];

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
                // Création d'une fonctionnalité
                $functionalityName = trim($_POST["name"] ?? '');
                $functionalityDescription = trim($_POST["description"] ?? '');
                $functionalityURL = trim($_POST["url"] ?? '');
                $functionalityIconType = trim($_POST["icon_type"] ?? 'font_awesome');
                $selectedIcon = trim($_POST["icon"] ?? '');
                $customIcon = trim($_POST["custom_icon"] ?? '');
                $functionalityGroup = trim($_POST["group"] ?? '');
                $functionalityOrder = (int) ($_POST["order"] ?? 999);
                $functionalityGroupOrder = (int) ($_POST["group_order"] ?? 0);
                $functionalityModules = $_POST["modules"] ?? [];
                $functionalityModules = array_map('trim', $functionalityModules);
                $isActive = isset($_POST["active"]) ? true : false;
                $selectedProfiles = $_POST['profiles'] ?? [];

                // Déterminer l'icône à utiliser
                if ($selectedIcon === 'other' && !empty($customIcon)) {
                    $functionalityIcon = $customIcon;
                } else {
                    $functionalityIcon = $selectedIcon;
                }

                // Validation des données
                if (empty($functionalityName)) {
                    $errors[] = "Le champ nom est obligatoire.";
                }

                if (empty($errors)) {
                    // Créer le document de la fonctionnalité sans le champ 'profiles'
                    $functionalityData = [
                        'name' => $functionalityName,
                        'description' => $functionalityDescription,
                        'url' => $functionalityURL,
                        'icon' => $functionalityIcon,
                        'icon_type' => $functionalityIconType,
                        'group' => $functionalityGroup,
                        'group_order' => $functionalityGroupOrder,
                        'order' => $functionalityOrder,
                        'modules' => $functionalityModules,
                        'active' => $isActive,
                        'created_at' => date('d-m-Y H:i:s'),
                        'updated_at' => date('d-m-Y H:i:s')
                    ];

                    // Insérer la fonctionnalité dans la collection
                    $insertResult = $functionalitiesCollection->insertOne($functionalityData);

                    // Récupérer l'identifiant de la nouvelle fonctionnalité
                    $functionalityObjectId = $insertResult->getInsertedId();

                    // Mettre à jour les profils sélectionnés
                    if (!empty($selectedProfiles)) {
                        $profilesCollection->updateMany(
                            ['name' => ['$in' => $selectedProfiles]],
                            ['$addToSet' => ['functionalities' => $functionalityObjectId]]
                        );
                    }

                    $success_msg = "La fonctionnalité a été créée avec succès.";
                }
                break;

            case 'edit':
                // Modification d'une fonctionnalité
                $functionalityId = $_POST['functionality_id'] ?? '';

                // Validation de l'ID
                if (!isValidObjectId($functionalityId)) {
                    $errors[] = "ID de fonctionnalité invalide.";
                } else {
                    $functionalityObjectId = new MongoDB\BSON\ObjectId($functionalityId);

                    $functionalityName = trim($_POST["name"] ?? '');
                    $functionalityDescription = trim($_POST["description"] ?? '');
                    $functionalityURL = trim($_POST["url"] ?? '');
                    $functionalityIconType = trim($_POST["icon_type"] ?? 'font_awesome');
                    $selectedIcon = trim($_POST["icon"] ?? '');
                    $customIcon = trim($_POST["custom_icon"] ?? '');
                    $functionalityGroup = trim($_POST["group"] ?? '');
                    $functionalityOrder = (int) ($_POST["order"] ?? 999);
                    $functionalityGroupOrder = (int) ($_POST["group_order"] ?? 0);
                    $functionalityModules = $_POST["modules"] ?? [];
                    $functionalityModules = array_map('trim', $functionalityModules);
                    $isActive = isset($_POST["active"]) ? true : false;
                    $selectedProfiles = $_POST['profiles'] ?? [];

                    // Déterminer l'icône à utiliser
                    if ($selectedIcon === 'other' && !empty($customIcon)) {
                        $functionalityIcon = $customIcon;
                    } else {
                        $functionalityIcon = $selectedIcon;
                    }

                    // Validation des données
                    if (empty($functionalityName)) {
                        $errors[] = "Le champ nom est obligatoire.";
                    }

                    if (empty($errors)) {
                        // Mettre à jour la fonctionnalité sans le champ 'profiles'
                        $updateResult = $functionalitiesCollection->updateOne(
                            ['_id' => $functionalityObjectId],
                            ['$set' => [
                                'name' => $functionalityName,
                                'description' => $functionalityDescription,
                                'url' => $functionalityURL,
                                'icon' => $functionalityIcon,
                                'icon_type' => $functionalityIconType,
                                'group' => $functionalityGroup,
                                'group_order' => $functionalityGroupOrder,
                                'order' => $functionalityOrder,
                                'modules' => $functionalityModules,
                                'active' => $isActive,
                                'updated_at' => date('d-m-Y H:i:s')
                            ]]
                        );

                        if ($updateResult->getModifiedCount() > 0) {
                            // Retirer la fonctionnalité de tous les profils actuels
                            $profilesCollection->updateMany(
                                ['functionalities' => $functionalityObjectId],
                                ['$pull' => ['functionalities' => $functionalityObjectId]]
                            );

                            // Ajouter la fonctionnalité aux profils sélectionnés
                            if (!empty($selectedProfiles)) {
                                $profilesCollection->updateMany(
                                    ['name' => ['$in' => $selectedProfiles]],
                                    ['$addToSet' => ['functionalities' => $functionalityObjectId]]
                                );
                            }

                            $success_msg = "La fonctionnalité a été mise à jour avec succès.";
                        } else {
                            $errors[] = "Aucune modification apportée à la fonctionnalité.";
                        }
                    }
                }
                break;

            case 'delete':
                // Suppression d'une fonctionnalité
                $functionalityId = $_POST['functionality_id'] ?? '';

                // Validation de l'ID
                if (!isValidObjectId($functionalityId)) {
                    $errors[] = "ID de fonctionnalité invalide.";
                } else {
                    $functionalityObjectId = new MongoDB\BSON\ObjectId($functionalityId);

                    // Vérifier que la fonctionnalité existe
                    $functionality = $functionalitiesCollection->findOne(['_id' => $functionalityObjectId]);

                    if (!$functionality) {
                        $errors[] = "La fonctionnalité n'existe pas.";
                    } else {
                        // Supprimer la fonctionnalité
                        $functionalitiesCollection->deleteOne(['_id' => $functionalityObjectId]);

                        // Retirer la fonctionnalité de tous les profils
                        $profilesCollection->updateMany(
                            [],
                            ['$pull' => ['functionalities' => $functionalityObjectId]]
                        );

                        $success_msg = "La fonctionnalité a été supprimée avec succès.";
                    }
                }
                break;

            case 'import':
                // Importation des fonctionnalités depuis un fichier Excel
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
                                    $functionalityName = trim($row[0]);
                                    $functionalityDescription = trim($row[1]);
                                    $functionalityURL = trim($row[2]);
                                    $functionalityIcon = trim($row[3]);
                                    $functionalityIconType = trim($row[4]);
                                    $functionalityGroup = trim($row[5]);
                                    $functionalityGroupOrder = (int) trim($row[6] ?? 0); 
                                    $functionalityOrder = (int) trim($row[7]);
                                    $functionalityModules = explode(',', trim($row[8] ?? ''));
                                    $functionalityModules = array_map('trim', $functionalityModules);
                                    $isActive = strtolower(trim($row[9])) == 'oui' ? true : false;
                                    $profilesList = explode(',', trim($row[10] ?? '')); // Les profils sont séparés par des virgules

                                    // Valider les données
                                    if (empty($functionalityName)) {
                                        $errors[] = "Le champ nom est obligatoire pour chaque entrée.";
                                        continue;
                                    }

                                    // Vérifier si la fonctionnalité existe déjà (par nom et module)
                                    $exist = $functionalitiesCollection->findOne(['name' => $functionalityName, 'modules' => $functionalityModules]);
                                    if ($exist) {
                                        $errors[] = "La fonctionnalité '{$functionalityName}' existe déjà et n'a pas été importée à nouveau.";
                                        continue;
                                    }

                                    // Créer le document de la fonctionnalité sans le champ 'profiles'
                                    $functionalityData = [
                                        'name' => $functionalityName,
                                        'description' => $functionalityDescription,
                                        'url' => $functionalityURL,
                                        'icon' => $functionalityIcon,
                                        'icon_type' => $functionalityIconType,
                                        'group' => $functionalityGroup,
                                        'group_order' => $functionalityGroupOrder,
                                        'order' => $functionalityOrder,
                                        'modules' => $functionalityModules,
                                        'active' => $isActive,
                                        'created_at' => date('d-m-Y H:i:s'),
                                        'updated_at' => date('d-m-Y H:i:s')
                                    ];

                                    // Insérer la fonctionnalité dans la collection
                                    $insertResult = $functionalitiesCollection->insertOne($functionalityData);
                                    $functionalityObjectId = $insertResult->getInsertedId();

                                    // Mettre à jour les profils
                                    foreach ($profilesList as $profileName) {
                                        $profileName = trim($profileName);
                                        if (!empty($profileName)) {
                                            $profilesCollection->updateOne(
                                                ['name' => $profileName],
                                                ['$addToSet' => ['functionalities' => $functionalityObjectId]]
                                            );
                                        }
                                    }
                                }
                                $success_msg = "L'importation des fonctionnalités a été effectuée avec succès.";
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

// Récupérer le profil depuis l'URL si présent
$selectedProfile = $_GET['profile'] ?? '';

// Récupérer les fonctionnalités existantes pour le module courant
// Si un profil est sélectionné, filtrer les fonctionnalités associées à ce profil
if (!empty($selectedProfile)) {
    // Vérifier si le profil existe
    $profile = $profilesCollection->findOne(['name' => $selectedProfile]);
    if ($profile) {
        // Récupérer les ObjectIds des fonctionnalités associées au profil
        $profileFunctionalities = $profile['functionalities'] ?? [];
        $functionalitiesCursor = $functionalitiesCollection->find([
            'modules' => $currentModule,
            '_id' => ['$in' => $profileFunctionalities]
        ]);
    } else {
        $errors[] = "Le profil sélectionné n'existe pas.";
        // Récupérer toutes les fonctionnalités
        $functionalitiesCursor = $functionalitiesCollection->find(['modules' => $currentModule]);
    }
} else {
    // Récupérer toutes les fonctionnalités
    $functionalitiesCursor = $functionalitiesCollection->find(['modules' => $currentModule]);
}
$functionalitiess = iterator_to_array($functionalitiesCursor);

// Récupérer les profils disponibles (actifs)
$profilesCursor = $profilesCollection->find(['active' => true]);
$profiles = iterator_to_array($profilesCursor);

// Récupérer les groupes existants sans doublons
$groups = $functionalitiesCollection->distinct('group', ['modules' => $currentModule]);
$groups = array_filter($groups); // Supprimer les valeurs nulles ou vides

// Calculer les statistiques pour les cartes

// Nombre de fonctionnalités actives
$activeFunctionalitiesCount = $functionalitiesCollection->count(['modules' => $currentModule, 'active' => true]);

// Nombre de fonctionnalités inactives
$inactiveFunctionalitiesCount = $functionalitiesCollection->count(['modules' => $currentModule, 'active' => false]);

// Nombre de fonctionnalités actives non affectées à aucun profil
$unassignedFunctionalitiesCursor = $functionalitiesCollection->aggregate([
    ['$match' => ['modules' => $currentModule, 'active' => true]],
    ['$lookup' => [
        'from' => 'profiles',
        'localField' => '_id',
        'foreignField' => 'functionalities',
        'as' => 'profiles'
    ]],
    ['$match' => ['profiles' => ['$size' => 0]]],
    ['$count' => 'count']
]);
$unassignedFunctionalitiesCount = 0;
foreach ($unassignedFunctionalitiesCursor as $doc) {
    $unassignedFunctionalitiesCount = $doc['count'];
}

// Nombre de fonctionnalités actives sans groupe
$noGroupFunctionalitiesCount = $functionalitiesCollection->count([
    'modules' => $currentModule,
    'active' => true,
    '$or' => [['group' => null], ['group' => ''], ['group' => ['$exists' => false]]]
]);

?>

<!-- Inclure Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.0/css/bootstrap.min.css">

<!-- Inclure DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
<!-- <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.3.2/css/fixedHeader.dataTables.min.css" /> -->

<!-- Inclure Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

<title>Liste des Pages | CFAO Mobility Academy</title>

<style>
    .table-container {
        margin-top: 20px;
    }
    .btn-group .btn {
        margin-right: 5px;
    }
    .btn-group form {
        display: inline-block;
        margin: 0;
    }
    .modal-dialog {
        max-width: 600px;
    }
    .modal-body .form-group label {
        white-space: nowrap;
    }
    .modal-body .form-group select,
    .modal-body .form-group input,
    .modal-body .form-group textarea {
        width: 100%;
    }

    select.form-control {
        white-space: normal;
    }

    /* Styles pour les cartes */
    .card-stats .card {
        margin-bottom: 20px;
    }
    .card-stats .card .card-body {
        padding: 15px;
    }
    /* Ajustement pour DataTables */
    th {
        cursor: pointer;
    }

    @media print {
        .card-stats .col-md-3 {
            float: left;
            width: 25%;
        }
    }

    /* Centrer le titre et l'icône d'actions verticalement */
    .my-3 .dropdown {
        margin-right: 15px;
    }
    /* Ajuster la largeur de la zone de recherche sur les petits écrans */
    @media (max-width: 576px) {
        .row.mb-3 .col-md-6.offset-md-3 {
            max-width: 100%;
            flex: 0 0 100%;
            max-width: 100%;
            margin-left: 0;
        }
    }

    .table-responsive {
        position: relative;
        height: 500px; /* Ajustez la hauteur selon vos besoins */
        overflow: auto;
    }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-3">
            <!-- Titre et Icône d'Actions -->
            <div class="d-flex justify-content-between align-items-center my-3">
                <h1>Liste des Pages / Fonctionnalités</h1>
                <div class="dropdown">
                    <!-- Icône d'Actions -->
                    <button class="btn btn-link" id="actionsButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="padding: 0;">
                        <i class="fas fa-cogs fa-5x"></i>
                    </button>
                    <!-- Menu Déroulant -->
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionsButton">
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#createFunctionalityModal">
                            <i class="fas fa-plus"></i> Ajouter une fonctionnalité
                        </a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#importFunctionalityModal">
                            <i class="fas fa-file-import"></i> Importer des fonctionnalités
                        </a>
                        <a class="dropdown-item" href="#" id="exportMainTable">
                            <i class="fas fa-file-export"></i> Exporter les fonctionnalités
                        </a>
                    </div>
                </div>
            </div>
            <!-- Zone de Recherche -->
            <div class="row mb-4">
                <div class="col-md-6 offset-md-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher par Nom ou Description">
                </div>
            </div>
            <br>
            <!-- Cartes de statistiques -->
            <div class="row card-stats">
                <!-- Carte des Pages Actives -->
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body text-center">
                            <!-- Icône -->
                            <i class="fas fa-check-circle fa-3x mb-2 text-success"></i>
                            <!-- Titre de la carte -->
                            <h5 class="card-title text-success">Pages Actives</h5>
                            <!-- Valeur statistique -->
                            <p class="card-text text-dark" style="font-size: 24px; font-weight: bold;"><?php echo $activeFunctionalitiesCount; ?></p>
                        </div>
                    </div>
                </div>
                <!-- Carte des Pages Inactives -->
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body text-center">
                            <i class="fas fa-times-circle fa-3x mb-2 text-danger"></i>
                            <h5 class="card-title text-danger">Pages Inactives</h5>
                            <p class="card-text text-dark" style="font-size: 24px; font-weight: bold;"><?php echo $inactiveFunctionalitiesCount; ?></p>
                        </div>
                    </div>
                </div>
                <!-- Carte des Pages Actives Non Affectées -->
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body text-center">
                            <i class="fas fa-user-slash fa-3x mb-2 text-warning"></i>
                            <h5 class="card-title text-warning">Pages Actives Sans Profil</h5>
                            <p class="card-text text-dark" style="font-size: 24px; font-weight: bold;"><?php echo $unassignedFunctionalitiesCount; ?></p>
                        </div>
                    </div>
                </div>
                <!-- Carte des Pages Actives Sans Groupe -->
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body text-center">
                            <i class="fas fa-layer-group fa-3x mb-2 text-info"></i>
                            <h5 class="card-title text-info">Pages Actives Sans Groupe</h5>
                            <p class="card-text text-dark" style="font-size: 24px; font-weight: bold;"><?php echo $noGroupFunctionalitiesCount; ?></p>
                        </div>
                    </div>
                </div>
            </div>
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
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php } ?>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <br>

            <!-- Tableau des fonctionnalités -->
            <div id="kt_customers_table_wrapper" class="dataTables_wrapper table-responsive table-container">
                <table id="functionalitiesTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">Icône</th>
                            <th class="text-center table-sort-asc">Nom</th>
                            <th class="text-center">Description</th>
                            <th class="text-center table-sort-asc">Groupe</th>
                            <th class="text-center table-sort-asc">Ordre</th>
                            <th class="text-center">Actif</th>
                            <th class="text-center">Modules</th>
                            <th class="text-center table-sort-asc">Profils</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($functionalitiess as $index => $functionality) { ?>
                            <tr>
                                <td class="text-center">
                                    <?php
                                    $iconClass = htmlspecialchars($functionality['icon'] ?? 'bi bi-list');
                                    $iconType = htmlspecialchars($functionality['icon_type'] ?? 'font_awesome');

                                    echo '<span class="menu-icon">';

                                    if ($iconType === 'ki_duotone') {
                                        // Déterminer le nombre de paths pour l'icône
                                        $paths = 2; // Par défaut
                                        foreach ($icons['ki_duotone'] as $icon) {
                                            if ($icon['value'] === $iconClass) {
                                                $paths = $icon['paths'] ?? 2;
                                                break;
                                            }
                                        }
                                        // Générer les spans
                                        echo '<i class="' . $iconClass . ' fs-2">';
                                        for ($i = 1; $i <= $paths; $i++) {
                                            echo '<span class="path' . $i . '"></span>';
                                        }
                                        echo '</i>';
                                    } else {
                                        echo '<i class="' . $iconClass . ' fs-2"></i>';
                                    }

                                    echo '</span>';
                                    ?>
                                </td>

                                <td class="text-center"><?php echo htmlspecialchars($functionality['name']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($functionality['description'] ?? ''); ?></td>
                                <td class="text-center">
                                    <?php
                                    $group = htmlspecialchars($functionality['group'] ?? '');
                                    $active = $functionality['active'] ?? false;
                                    if (empty($group) && !$active) {
                                        echo '<span class="badge badge-danger">Inactif</span>';
                                    } elseif (empty($group) && $active) {
                                        echo '<span class="badge badge-warning">Non Concerné</span>';
                                    } else {
                                        echo htmlspecialchars($group);
                                    }
                                    ?>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($functionality['order'] ?? 999); ?></td>
                                <td class="text-center"><?php echo ($functionality['active'] ?? false) ? 'Oui' : 'Non'; ?></td>
                                <td class="text-center">
                                    <?php
                                    if (!empty($functionality['modules'])) {
                                        // Convertir BSONArray en tableau PHP
                                        $modules = is_array($functionality['modules']) ? $functionality['modules'] : iterator_to_array($functionality['modules']);
                                        echo htmlspecialchars(implode(', ', $modules));
                                    } else {
                                        echo '<span class="badge badge-secondary">Aucun</span>';
                                    }
                                    ?>
                                </td>

                                <td class="text-center">
                                    <?php
                                    // Récupérer les profils associés à cette fonctionnalité via la collection 'profiles'
                                    $profileNames = []; // Réinitialiser le tableau pour chaque fonctionnalité
                                    $associatedProfilesCursor = $profilesCollection->find(['functionalities' => $functionality['_id']]);
                                    foreach ($associatedProfilesCursor as $profile) {
                                        $profileNames[] = $profile['name'];
                                    }
                                    if (!empty($profileNames)) {
                                        foreach ($profileNames as $profileName) {
                                            echo '<span class="badge badge-success">' . htmlspecialchars($profileName) . '</span> ';
                                        }
                                    } else {
                                        echo '<span class="badge badge-secondary">Aucun</span>';
                                    }
                                    ?>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <!-- Bouton Modifier -->
                                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editFunctionalityModal<?php echo $index; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- Formulaire de suppression -->
                                        <form action="listFunctionalities.php" method="post" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="functionality_id" value="<?php echo $functionality['_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette fonctionnalité ?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <!--begin::Export dropdown-->
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <!--begin::Export-->
                <button type="button" id="excel" class="btn btn-light-danger me-3" data-bs-toggle="modal"
                    data-bs-target="#kt_customers_export_modal">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $excel ?>
                </button>
                <!--end::Export-->
            </div>
            <!--end::Export dropdown-->
            <!-- Bouton Imprimer -->
            <div class="text-center mt-3">
                <button class="btn btn-secondary" onclick="window.print();">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!--begin::Modal - Create Functionality-->
<div class="modal fade" id="createFunctionalityModal" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <!--begin::Modal content-->
        <div class="modal-content">
            <!--begin::Form-->
            <form method="POST" action="listFunctionalities.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create">
                <!--begin::Modal header-->
                <div class="modal-header" id="createFunctionalityModal_header">
                    <!--begin::Logo-->
                    <img src="../../public/images/logo.png" alt="Logo" style="max-height: 50px;">
                    <!--end::Logo-->
                    <!--begin::Modal title-->
                    <h2 class="fs-2 fw-bolder">Créer une fonctionnalité</h2>
                    <!--end::Modal title-->
                    <!--begin::Close-->
                    <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal" aria-label="Close">
                        <!--begin::Svg Icon-->
                        <span class="svg-icon svg-icon-1">
                            <!-- Icône SVG pour le bouton de fermeture -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                      transform="rotate(-45 6 17.3137)" fill="black"/>
                                <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                      transform="rotate(45 7.41422 6)" fill="black"/>
                            </svg>
                        </span>
                        <!--end::Svg Icon-->
                    </button>
                    <!--end::Close-->
                </div>
                <!--end::Modal header-->

                <!--begin::Modal body-->
                <div class="modal-body py-10 px-lg-17">
                    <!--begin::Scroll-->
                    <div class="d-flex flex-column scroll-y me-n7 pe-7" id="createFunctionalityModal_scroll"
                         data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                         data-kt-scroll-max-height="auto"
                         data-kt-scroll-dependencies="#createFunctionalityModal_header"
                         data-kt-scroll-wrappers="#createFunctionalityModal_scroll" data-kt-scroll-offset="300px">
                        <!--begin::Form fields-->
                        <!-- Nom de la fonctionnalité -->
                        <div class="fv-row mb-7">
                            <label for="nameCreate" class="fs-6 fw-bold mb-2">Nom de la fonctionnalité</label>
                            <input type="text" class="form-control form-control-solid" name="name" id="nameCreate"
                                   required>
                        </div>
                        <!-- Description de la fonctionnalité -->
                        <div class="fv-row mb-7">
                            <label for="descriptionCreate"
                                   class="fs-6 fw-bold mb-2">Description de la fonctionnalité</label>
                            <textarea class="form-control form-control-solid" name="description"
                                      id="descriptionCreate"></textarea>
                        </div>
                        <!-- URL de la fonctionnalité -->
                        <div class="fv-row mb-7">
                            <label for="urlCreate" class="fs-6 fw-bold mb-2">URL de la fonctionnalité</label>
                            <input type="text" class="form-control form-control-solid" name="url" id="urlCreate">
                            <small class="form-text text-muted">Chemin d'accès relatif de la fonctionnalité (par
                                exemple, <code>./listFunctionalities.php</code>).</small>
                        </div>
                        <!-- Type d'icône -->
                        <div class="fv-row mb-7">
                            <label for="iconTypeCreate" class="fs-6 fw-bold mb-2">Type d'icône</label>
                            <select name="icon_type" id="iconTypeCreate" class="form-select form-select-solid"
                                    style="padding-bottom: 8px;padding-top: 6px;">
                                <option value="">-- Sélectionner un type d'icône --</option>
                                <option value="font_awesome">Font Awesome</option>
                                <option value="ki_duotone">Ki Duotone</option>
                                <option value="bi">Bootstrap Icons</option>
                            </select>
                        </div>
                        <!-- Icône de la fonctionnalité -->
                        <div class="fv-row mb-7">
                            <label for="iconCreate" class="fs-6 fw-bold mb-2">Icône de la fonctionnalité</label>
                            <select name="icon" id="iconCreate" class="form-select form-select-solid"
                                    style="padding-bottom: 8px;padding-top: 6px;">
                                <option value="">-- Sélectionnez une icône --</option>
                                <!-- Options remplies dynamiquement avec JavaScript -->
                            </select>
                            <small class="form-text text-muted">Sélectionnez une icône ou choisissez "Autre..." pour
                                saisir une icône personnalisée.</small>
                        </div>
                        <!-- Icône personnalisée (champ caché par défaut) -->
                        <div class="fv-row mb-7" id="customIconGroupCreate" style="display: none;">
                            <label for="customIconCreate" class="fs-6 fw-bold mb-2">Icône personnalisée (classe
                                CSS)</label>
                            <input type="text" class="form-control form-control-solid" name="custom_icon"
                                   id="customIconCreate">
                        </div>
                        <!-- Prévisualisation de l'icône -->
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold mb-2">Prévisualisation de l'icône :</label>
                            <div id="iconPreviewCreate" style="font-size: 24px;"></div>
                        </div>
                        <!-- Groupe de la fonctionnalité -->
                        <div class="fv-row mb-7">
                            <label for="groupCreate" class="fs-6 fw-bold mb-2">Groupe de la fonctionnalité</label>
                            <select name="group" id="groupCreate" class="form-select form-select-solid"
                                    style="padding-bottom: 8px;padding-top: 6px;">
                                <option value="">-- Sélectionner un groupe --</option>
                                <?php foreach ($groups as $group) { ?>
                                    <option
                                        value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars($group); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <!-- Ordre du groupe -->
                        <div class="fv-row mb-7">
                            <label for="groupOrderCreate" class="fs-6 fw-bold mb-2">Ordre du groupe</label>
                            <input type="number" class="form-control form-control-solid" name="group_order"
                                   id="groupOrderCreate" value="0">
                            <small class="form-text text-muted">Détermine l'ordre d'affichage des groupes dans le
                                menu.</small>
                        </div>
                        <!-- Ordre d'affichage -->
                        <div class="fv-row mb-7">
                            <label for="orderCreate" class="fs-6 fw-bold mb-2">Ordre d'affichage</label>
                            <input type="number" class="form-control form-control-solid" name="order" id="orderCreate"
                                   value="999">
                        </div>
                        <!-- Modules -->
                        <div class="fv-row mb-7">
                            <label for="modulesCreate" class="fs-6 fw-bold mb-2">Modules</label>
                            <select name="modules[]" id="modulesCreate" class="form-select form-select-solid" multiple required style="padding-bottom: 8px;padding-top: 6px;">
                                <option value="measure">Measure</option>
                                <option value="explore">Explore</option>
                                <option value="define">Define</option>
                                <!-- Ajoutez d'autres modules si nécessaire -->
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs modules.</small>
                        </div>

                       <!-- Assigner à des profils -->
                       <div class="fv-row mb-7">
                            <label for="profilesCreate" class="fs-6 fw-bold mb-2">Assigner à des profils</label>
                            <select name="profiles[]" id="profilesCreate" class="form-select form-select-solid" multiple
                                data-control="select2" data-placeholder="Sélectionnez des profils">
                                <?php
                                    // Remplir les options des profils disponibles
                                    foreach ($profiles as $profile) {
                                        echo '<option value="' . htmlspecialchars($profile['name']) . '">' . htmlspecialchars($profile['name']) . '</option>';
                                    }
                                ?>
                            </select>
                            <small class="form-text text-muted">Maintenez la touche Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs profils.</small>
                        </div>
                        <!-- Activer la fonctionnalité -->
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activeCreate" name="active" checked>
                            <label class="form-check-label" for="activeCreate">Activer la fonctionnalité</label>
                        </div>
                        <!--end::Form fields-->
                    </div>
                    <!--end::Scroll-->
                </div>
                <!--end::Modal body-->

                <!--begin::Modal footer-->
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la fonctionnalité</button>
                </div>
                <!--end::Modal footer-->
            </form>
            <!--end::Form-->
        </div>
        <!--end::Modal content-->
    </div>
    <!--end::Modal dialog-->
</div>
<!--end::Modal - Create Functionality-->

<!--begin::Modal - Import Functionalities-->
<div class="modal fade" id="importFunctionalityModal" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <!--begin::Modal content-->
        <div class="modal-content">
            <!--begin::Form-->
            <form enctype="multipart/form-data" method="POST" action="listFunctionalities.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="import">
                <!--begin::Modal header-->
                <div class="modal-header" id="importFunctionalityModal_header">
                    <!--begin::Logo-->
                    <img src="../../public/images/logo.png" alt="Logo" style="max-height: 50px;">
                    <!--end::Logo-->
                    <!--begin::Modal title-->
                    <h2 class="fs-2 fw-bolder">Importer des fonctionnalités</h2>
                    <!--end::Modal title-->
                    <!--begin::Close-->
                    <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal" aria-label="Close">
                        <!--begin::Svg Icon-->
                        <span class="svg-icon svg-icon-1">
                            <!-- Icône SVG pour le bouton de fermeture -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                      transform="rotate(-45 6 17.3137)" fill="black"/>
                                <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                      transform="rotate(45 7.41422 6)" fill="black"/>
                            </svg>
                        </span>
                        <!--end::Svg Icon-->
                    </button>
                    <!--end::Close-->
                </div>
                <!--end::Modal header-->

                <!--begin::Modal body-->
                <div class="modal-body py-10 px-lg-17">
                    <!--begin::Scroll-->
                    <div class="d-flex flex-column scroll-y me-n7 pe-7" id="importFunctionalityModal_scroll"
                         data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                         data-kt-scroll-max-height="auto"
                         data-kt-scroll-dependencies="#importFunctionalityModal_header"
                         data-kt-scroll-wrappers="#importFunctionalityModal_scroll" data-kt-scroll-offset="300px">
                        <!--begin::Form fields-->
                        <!-- Importer les fonctionnalités via Excel -->
                        <div class="fv-row mb-7">
                            <label for="excelFile" class="fs-6 fw-bold mb-2">Importer les fonctionnalités via Excel</label>
                            <div class="input-group">
                                <input type="file" class="form-control form-control-solid" name="excel" accept=".xls,.xlsx" required />
                                <div class="input-group-append">
                                    <span class="input-group-text">.xls / .xlsx</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">Le fichier Excel doit contenir les colonnes : Nom, Description, URL, Icône, Type d'icône, Groupe, Ordre du Groupe, Ordre, Module, Actif (Oui/Non), Profils</small>
                        </div>
                        <!--end::Form fields-->
                    </div>
                    <!--end::Scroll-->
                </div>
                <!--end::Modal body-->

                <!--begin::Modal footer-->
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </div>
                <!--end::Modal footer-->
            </form>
            <!--end::Form-->
        </div>
        <!--end::Modal content-->
    </div>
    <!--end::Modal dialog-->
</div>
<!--end::Modal - Import Functionalities-->

<!--begin::Modals - Edit Functionalities-->
<?php foreach ($functionalitiess as $index => $functionality) { ?>
    <?php
    // Supposons que $functionalityId est l'ID de la fonctionnalité en cours de modification
    $details = getFunctionalityDetails($functionality['_id'], $functionalitiesCollection, $profilesCollection);

    if ($details === null) {
        // Gérer le cas où la fonctionnalité n'existe pas
        continue; // ou afficher un message d'erreur
    }

    $functionalityData = $details['functionality'];
    $assignedProfileNames = $details['associatedProfiles'];
    $currentIcon = $details['icon'];
    $currentIconType = $details['icon_type'];

    ?>

    <div class="modal fade" id="editFunctionalityModal<?php echo $index; ?>" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <!--begin::Modal content-->
            <div class="modal-content">
                <!--begin::Form-->
                <form method="POST" action="listFunctionalities.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="functionality_id" value="<?php echo $functionality['_id']; ?>">
                    <!--begin::Modal header-->
                    <div class="modal-header" id="editFunctionalityModal_header<?php echo $index; ?>">
                        <!--begin::Logo-->
                        <img src="../../public/images/logo.png" alt="Logo" style="max-height: 50px;">
                        <!--end::Logo-->
                        <!--begin::Modal title-->
                        <h2 class="fs-2 fw-bolder">Modifier la fonctionnalité : <?php echo htmlspecialchars($functionality['name']); ?></h2>
                        <!--end::Modal title-->
                        <!--begin::Close-->
                        <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal" aria-label="Close">
                            <!--begin::Svg Icon-->
                            <span class="svg-icon svg-icon-1">
                                <!-- Icône SVG pour le bouton de fermeture -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
                                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                                          transform="rotate(-45 6 17.3137)" fill="black"/>
                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                          transform="rotate(45 7.41422 6)" fill="black"/>
                                </svg>
                            </span>
                            <!--end::Svg Icon-->
                        </button>
                        <!--end::Close-->
                    </div>
                    <!--end::Modal header-->

                    <!--begin::Modal body-->
                    <div class="modal-body py-10 px-lg-17">
                        <!--begin::Scroll-->
                        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="editFunctionalityModal_scroll<?php echo $index; ?>"
                             data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                             data-kt-scroll-max-height="auto"
                             data-kt-scroll-dependencies="#editFunctionalityModal_header<?php echo $index; ?>"
                             data-kt-scroll-wrappers="#editFunctionalityModal_scroll<?php echo $index; ?>" data-kt-scroll-offset="300px">
                            <!--begin::Form fields-->
                            <!-- Nom de la fonctionnalité -->
                            <div class="fv-row mb-7">
                                <label for="name<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Nom de la fonctionnalité</label>
                                <input type="text" class="form-control form-control-solid" name="name" id="name<?php echo $index; ?>" value="<?php echo htmlspecialchars($functionality['name']); ?>" required>
                            </div>
                            <!-- Description de la fonctionnalité -->
                            <div class="fv-row mb-7">
                                <label for="description<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Description de la fonctionnalité</label>
                                <textarea class="form-control form-control-solid" name="description" id="description<?php echo $index; ?>"><?php echo htmlspecialchars($functionality['description'] ?? ''); ?></textarea>
                            </div>
                            <!-- URL de la fonctionnalité -->
                            <div class="fv-row mb-7">
                                <label for="url<?php echo $index; ?>" class="fs-6 fw-bold mb-2">URL de la fonctionnalité</label>
                                <input type="text" class="form-control form-control-solid" name="url" id="url<?php echo $index; ?>" value="<?php echo htmlspecialchars($functionality['url'] ?? ''); ?>">
                                <small class="form-text text-muted">Chemin d'accès relatif de la fonctionnalité (par exemple, <code>./listFunctionalities.php</code>).</small>
                            </div>
                            <!-- Type d'icône -->
                            <div class="fv-row mb-7">
                                <label for="iconType<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Type d'icône</label>
                                <select name="icon_type" id="iconType<?php echo $index; ?>" class="form-select form-select-solid" style="padding-bottom: 8px;padding-top: 6px;">
                                    <option value="">-- Sélectionner un type d'icône --</option>
                                    <option value="font_awesome" <?php echo ($functionality['icon_type'] == 'font_awesome') ? 'selected' : ''; ?>>Font Awesome</option>
                                    <option value="ki_duotone" <?php echo ($functionality['icon_type'] == 'ki_duotone') ? 'selected' : ''; ?>>Ki Duotone</option>
                                    <option value="bi" <?php echo ($functionality['icon_type'] == 'bi') ? 'selected' : ''; ?>>Bootstrap Icons</option>
                                </select>
                            </div>
                            <!-- Icône de la fonctionnalité -->
                            <div class="fv-row mb-7">
                                <label for="icon<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Icône de la fonctionnalité</label>
                                <select name="icon" id="icon<?php echo $index; ?>" class="form-select form-select-solid" style="padding-bottom: 8px;padding-top: 6px;">
                                    <option value="">-- Sélectionnez une icône --</option>
                                    <?php
                                        if (!empty($currentIconType) && isset($icons[$currentIconType])) {
                                            foreach ($icons[$currentIconType] as $icon) {
                                                $selected = ($icon['value'] === $currentIcon) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($icon['value']) . '" ' . $selected . '>' . htmlspecialchars($icon['text']) . '</option>';
                                            }
                                        }
                                    ?>
                                    <option value="other" <?php echo (empty($currentIcon) || !in_array($currentIcon, array_column($icons[$currentIconType], 'value'))) ? 'selected' : ''; ?>>Autre...</option>
                                    <!-- Options remplies dynamiquement avec JavaScript -->
                                </select>
                                <small class="form-text text-muted">Sélectionnez une icône ou choisissez "Autre..." pour saisir une icône personnalisée.</small>
                            </div>
                            <!-- Icône personnalisée (champ caché par défaut) -->
                            <div class="fv-row mb-7" id="customIconGroup<?php echo $index; ?>" style="display: <?php echo (empty($currentIconType) || !in_array($currentIcon, array_column($icons[$currentIconType], 'value'))) ? 'block' : 'none'; ?>;">
                                <label for="customIcon<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Icône personnalisée (classe CSS)</label>
                                <input type="text" class="form-control form-control-solid" name="custom_icon" id="customIcon<?php echo $index; ?>" value="<?php echo htmlspecialchars($functionality['icon']); ?>">
                            </div>
                            <!-- Prévisualisation de l'icône -->
                            <div class="fv-row mb-7">
                                <label class="fs-6 fw-bold mb-2">Prévisualisation de l'icône :</label>
                                <div id="iconPreview<?php echo $index; ?>" style="font-size: 24px;">
                                    <?php
                                        if (!empty($currentIcon)) {
                                            if ($currentIconType === 'ki_duotone') {
                                                // Exemple de prévisualisation pour Ki Duotone
                                                $paths = 2; // Vous pouvez ajuster en fonction de la logique
                                                echo '<i class="' . htmlspecialchars($currentIcon) . ' fs-2">';
                                                for ($i = 1; $i <= $paths; $i++) {
                                                    echo '<span class="path' . $i . '"></span>';
                                                }
                                                echo '</i>';
                                            } else {
                                                echo '<i class="' . htmlspecialchars($currentIcon) . ' fs-2"></i>';
                                            }
                                        }
                                    ?>
                                </div>
                            </div>
                            <!-- Groupe de la fonctionnalité -->
                            <div class="fv-row mb-7">
                                <label for="group<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Groupe de la fonctionnalité</label>
                                <select name="group" id="group<?php echo $index; ?>" class="form-select form-select-solid" style="padding-bottom: 8px;padding-top: 6px;">
                                    <option value="">-- Sélectionner un groupe --</option>
                                    <?php foreach ($groups as $group) { ?>
                                        <option value="<?php echo htmlspecialchars($group); ?>" <?php echo (isset($functionality['group']) && $functionality['group'] == $group) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <!-- Ordre du groupe -->
                            <div class="fv-row mb-7">
                                <label for="groupOrder<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Ordre du groupe</label>
                                <input type="number" class="form-control form-control-solid" name="group_order" id="groupOrder<?php echo $index; ?>" value="<?php echo htmlspecialchars($functionality['group_order'] ?? 0); ?>">
                                <small class="form-text text-muted">Détermine l'ordre d'affichage des groupes dans le menu.</small>
                            </div>
                            <!-- Ordre d'affichage -->
                            <div class="fv-row mb-7">
                                <label for="order<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Ordre d'affichage</label>
                                <input type="number" class="form-control form-control-solid" name="order" id="order<?php echo $index; ?>" value="<?php echo htmlspecialchars($functionality['order'] ?? 999); ?>">
                            </div>
                            <!-- Modules -->
                            <div class="fv-row mb-7">
                                <label for="modules<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Modules</label>
                                <select name="modules[]" id="modules<?php echo $index; ?>" class="form-select form-select-solid" multiple required>
                                    <?php
                                        // Convertir 'modules' en tableau PHP si ce n'est pas déjà le cas
                                        $modules = isset($functionality['modules']) ? (is_array($functionality['modules']) ? $functionality['modules'] : iterator_to_array($functionality['modules'])) : [];
                                    ?>
                                    <option value="measure" <?php echo (in_array('measure', $modules)) ? 'selected' : ''; ?>>Measure</option>
                                    <option value="explore" <?php echo (in_array('explore', $modules)) ? 'selected' : ''; ?>>Explore</option>
                                    <option value="define" <?php echo (in_array('define', $modules)) ? 'selected' : ''; ?>>Define</option>
                                    <!-- autres options -->
                                </select>
                            </div>

                            <!-- Assigner à des profils -->
                            <div class="fv-row mb-7">
                                <label for="profiles<?php echo $index; ?>" class="fs-6 fw-bold mb-2">Assigner à des profils</label>
                                <select name="profiles[]" id="profiles<?php echo $index; ?>" class="form-select form-select-solid" multiple
                                    data-control="select2" data-placeholder="Sélectionnez des profils">
                                    <?php foreach ($profiles as $profile) { ?>
                                        <option value="<?php echo htmlspecialchars($profile['name']); ?>" <?php echo in_array($profile['name'], $assignedProfileNames) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($profile['name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small class="form-text text-muted">Maintenez la touche Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs profils.</small>
                            </div>

                            <!-- Activer la fonctionnalité -->
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="active<?php echo $index; ?>" name="active" <?php echo ($functionality['active'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active<?php echo $index; ?>">Activer la fonctionnalité</label>
                            </div>
                            <!--end::Form fields-->
                        </div>
                        <!--end::Scroll-->
                    </div>
                    <!--end::Modal body-->

                    <!--begin::Modal footer-->
                    <div class="modal-footer flex-center">
                        <button type="button" class="btn btn-light me-3" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                    <!--end::Modal footer-->
                </form>
                <!--end::Form-->
            </div>
            <!--end::Modal content-->
        </div>
        <!--end::Modal dialog-->
    </div>
<?php } ?>
<!--end::Modals - Edit Functionalities-->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Inclure Bootstrap JS une seule fois -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Inclure DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- <script src="https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js"></script> -->

<!-- Inclure Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

<!-- Inclure TableToExcel JS -->
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>

<!-- Inclure votre script principal -->
<script src="../../public/js/main.js"></script>
<script>
$(document).ready(function() {
    $("#excel").on("click", function() {
        let table = document.getElementsByTagName("table");
        TableToExcel.convert(table[0], {
            name: `Fonctionnalités.xlsx`
        })
    });

    // Initialiser Select2 pour les champs des profils et modules
    $('#modulesCreate').select2({
        placeholder: "Sélectionnez des modules",
        allowClear: true
    });
    <?php foreach ($functionalitiess as $index => $functionality) { ?>
        $('#modules<?php echo $index; ?>').select2({
            placeholder: "Sélectionnez des modules",
            allowClear: true
        });
    <?php } ?>

    // Liste complète des icônes par type
    const icons = <?php echo json_encode($icons); ?>;

    // Fonction pour mettre à jour le sélecteur d'icônes
    function updateIconSelector(iconType, iconSelectId, customIconGroupId, iconPreviewId, selectedIconValue = '') {
        const iconSelect = $('#' + iconSelectId);
        const customIconGroup = $('#' + customIconGroupId);
        const iconPreview = $('#' + iconPreviewId);

        // Vider le sélecteur
        iconSelect.html('<option value="">-- Sélectionnez une icône --</option>');

        if (icons[iconType]) {
            icons[iconType].forEach(icon => {
                const option = $('<option>', {
                    value: icon.value,
                    text: icon.text,
                    'data-paths': icon.paths || 0
                });
                iconSelect.append(option);
            });
        }

        // Ajouter l'option "Autre..."
        const otherOption = $('<option>', {
            value: 'other',
            text: 'Autre...'
        });
        iconSelect.append(otherOption);

        // Sélectionner l'icône appropriée
        if (selectedIconValue) {
            // Vérifier si l'icône est dans la liste
            const iconExists = icons[iconType] && icons[iconType].some(icon => icon.value === selectedIconValue);

            if (iconExists) {
                iconSelect.val(selectedIconValue);
                customIconGroup.hide();
                customIconGroup.find('input').val('');
            } else {
                iconSelect.val('other');
                customIconGroup.show();
                customIconGroup.find('input').val(selectedIconValue);
            }

            // Afficher la prévisualisation
            if (iconType === 'ki_duotone') {
                let spans = '';
                const paths = parseInt(icons[iconType].find(icon => icon.value === selectedIconValue)?.paths) || 2;
                for (let i = 1; i <= paths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.html(`<i class="${selectedIconValue} fs-2">${spans}</i>`);
            } else {
                iconPreview.html(`<i class="${selectedIconValue} fs-2"></i>`);
            }
        } else {
            iconSelect.val('');
            customIconGroup.hide();
            customIconGroup.find('input').val('');
            iconPreview.html('');
        }
    }

    // Gestion du changement du type d'icône pour la modale de création
    $('#iconTypeCreate').on('change', function() {
        const iconType = $(this).val();
        updateIconSelector(iconType, 'iconCreate', 'customIconGroupCreate', 'iconPreviewCreate');
    });

    // Gestion du changement de l'icône sélectionnée pour la modale de création
    $('#iconCreate').on('change', function() {
        const selectedIcon = $(this).val();
        const iconType = $('#iconTypeCreate').val();
        const customIconGroup = $('#customIconGroupCreate');
        const iconPreview = $('#iconPreviewCreate');

        if (selectedIcon === 'other') {
            customIconGroup.show();
            iconPreview.html('');
        } else {
            customIconGroup.hide();
            if (iconType === 'ki_duotone') {
                const selectedOption = $(this).find('option:selected');
                const paths = parseInt(selectedOption.data('paths')) || 2;
                let spans = '';
                for (let i = 1; i <= paths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.html(`<i class="${selectedIcon} fs-2">${spans}</i>`);
            } else {
                iconPreview.html(`<i class="${selectedIcon} fs-2"></i>`);
            }
        }
    });

    // Gestion de la saisie de l'icône personnalisée pour la modale de création
    $('#customIconCreate').on('input', function() {
        const customIcon = $(this).val();
        const iconType = $('#iconTypeCreate').val();
        const iconPreview = $('#iconPreviewCreate');

        if (iconType === 'ki_duotone') {
            let spans = '';
            const defaultPaths = 2;
            for (let i = 1; i <= defaultPaths; i++) {
                spans += `<span class="path${i}"></span>`;
            }
            iconPreview.html(`<i class="${customIcon} fs-2">${spans}</i>`);
        } else {
            iconPreview.html(`<i class="${customIcon} fs-2"></i>`);
        }
    });

    // Initialiser les sélecteurs lors de l'ouverture de la modale de création
    $('#createFunctionalityModal').on('shown.bs.modal', function() {
        const iconType = $('#iconTypeCreate').val();
        updateIconSelector(iconType, 'iconCreate', 'customIconGroupCreate', 'iconPreviewCreate');
    });

    // Initialiser le DataTable
    var table = $('#functionalitiesTable').DataTable({
        "paging": false,
        "scrollY": false,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
        },
        "columnDefs": [
            { "orderable": false, "targets": [0, 7, 8] }
        ],
        "order": [],
        "dom": 'rt',
        // fixedHeader: true
    });

    // Lier la zone de recherche à DataTables
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Exportation Excel pour le tableau principal
    $("#exportMainTable").on("click", function() {
        let table = document.getElementById("functionalitiesTable").cloneNode(true);
        table.querySelectorAll('th').forEach((th, index) => {
            if (index === table.querySelectorAll('th').length - 1 || index === 0) {
                th.remove();
            }
        });
        table.querySelectorAll('tr').forEach(tr => {
            if (tr.children.length > 1) {
                tr.removeChild(tr.lastElementChild);
                tr.removeChild(tr.firstElementChild);
            }
        });
        TableToExcel.convert(table, {
            name: "Fonctionnalités.xlsx",
            sheet: {
                name: "Fonctionnalités"
            }
        });
    });

    // Pour chaque modale de modification
    <?php foreach ($functionalitiess as $index => $functionality) { ?>
        // Gestion du changement du type d'icône pour la modale de modification
        $('#iconType<?php echo $index; ?>').on('change', function() {
            const iconType = $(this).val();
            updateIconSelector(iconType, 'icon<?php echo $index; ?>', 'customIconGroup<?php echo $index; ?>', 'iconPreview<?php echo $index; ?>');
        });

        // Initialiser le sélecteur d'icônes lors de l'ouverture de la modale de modification
        $('#editFunctionalityModal<?php echo $index; ?>').on('shown.bs.modal', function() {
            const iconType = $('#iconType<?php echo $index; ?>').val();
            const selectedIcon = '<?php echo $functionality['icon']; ?>';
            updateIconSelector(iconType, 'icon<?php echo $index; ?>', 'customIconGroup<?php echo $index; ?>', 'iconPreview<?php echo $index; ?>', selectedIcon);

            // Si l'icône est personnalisée (c'est-à-dire que l'option 'other' est sélectionnée)
            if ($('#icon<?php echo $index; ?>').val() === 'other') {
                $('#customIconGroup<?php echo $index; ?>').show();
            } else {
                $('#customIconGroup<?php echo $index; ?>').hide();
            }
        });

        // Gestion du changement de l'icône sélectionnée pour la modale de modification
        $('#icon<?php echo $index; ?>').on('change', function() {
            const selectedIcon = $(this).val();
            const iconType = $('#iconType<?php echo $index; ?>').val();
            const customIconGroup = $('#customIconGroup<?php echo $index; ?>');
            const iconPreview = $('#iconPreview<?php echo $index; ?>');

            if (selectedIcon === 'other') {
                customIconGroup.show();
                iconPreview.html('');
            } else {
                customIconGroup.hide();
                if (iconType === 'ki_duotone') {
                    const selectedOption = $(this).find('option:selected');
                    const paths = parseInt(selectedOption.data('paths')) || 2;
                    let spans = '';
                    for (let i = 1; i <= paths; i++) {
                        spans += `<span class="path${i}"></span>`;
                    }
                    iconPreview.html(`<i class="${selectedIcon} fs-2">${spans}</i>`);
                } else {
                    iconPreview.html(`<i class="${selectedIcon} fs-2"></i>`);
                }
            }
        });

        // Gestion de la saisie de l'icône personnalisée pour la modale de modification
        $('#customIcon<?php echo $index; ?>').on('input', function() {
            const customIcon = $(this).val();
            const iconType = $('#iconType<?php echo $index; ?>').val();
            const iconPreview = $('#iconPreview<?php echo $index; ?>');

            if (iconType === 'ki_duotone') {
                let spans = '';
                const defaultPaths = 2;
                for (let i = 1; i <= defaultPaths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.html(`<i class="${customIcon} fs-2">${spans}</i>`);
            } else {
                iconPreview.html(`<i class="${customIcon} fs-2"></i>`);
            }
        });

        // Activer Select2 pour le champ des profils dans la modale de modification
        $('#profiles<?php echo $index; ?>').select2();
    <?php } ?>

    // Activer Select2 pour le champ des profils dans la modale de création
    $('#profilesCreate').select2();

    // Fonction pour mettre à jour les sélecteurs d'icônes lors des changements dynamiques
    function updateIconSelector(iconType, iconSelectId, customIconGroupId, iconPreviewId, selectedIconValue = '') {
        const iconSelect = $('#' + iconSelectId);
        const customIconGroup = $('#' + customIconGroupId);
        const iconPreview = $('#' + iconPreviewId);

        // Vider le sélecteur
        iconSelect.html('<option value="">-- Sélectionnez une icône --</option>');

        if (icons[iconType]) {
            icons[iconType].forEach(icon => {
                const option = $('<option>', {
                    value: icon.value,
                    text: icon.text,
                    'data-paths': icon.paths || 0
                });
                iconSelect.append(option);
            });
        }

        // Ajouter l'option "Autre..."
        const otherOption = $('<option>', {
            value: 'other',
            text: 'Autre...'
        });
        iconSelect.append(otherOption);

        // Sélectionner l'icône appropriée
        if (selectedIconValue) {
            // Vérifier si l'icône est dans la liste
            const iconExists = icons[iconType] && icons[iconType].some(icon => icon.value === selectedIconValue);

            if (iconExists) {
                iconSelect.val(selectedIconValue);
                customIconGroup.hide();
                customIconGroup.find('input').val('');
            } else {
                iconSelect.val('other');
                customIconGroup.show();
                customIconGroup.find('input').val(selectedIconValue);
            }

            // Afficher la prévisualisation
            if (iconType === 'ki_duotone') {
                let spans = '';
                const paths = parseInt(icons[iconType].find(icon => icon.value === selectedIconValue)?.paths) || 2;
                for (let i = 1; i <= paths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.html(`<i class="${selectedIconValue} fs-2">${spans}</i>`);
            } else {
                iconPreview.html(`<i class="${selectedIconValue} fs-2"></i>`);
            }
        } else {
            iconSelect.val('');
            customIconGroup.hide();
            customIconGroup.find('input').val('');
            iconPreview.html('');
        }
    }
});
</script>

<?php include_once "partials/footer.php"; ?>
