<?php
session_start();
include_once "../language.php";

// Vérifier si l'utilisateur est authentifié en tant que Super Admin
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Connexion à MongoDB
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $client->academy;
    $functionalitiesCollection = $academy->functionalities;
    $profilesCollection = $academy->profiles; // Pour récupérer les profils disponibles

    $success_msg = ''; // Message de succès
    $errors = []; // Tableau pour les erreurs

    // Générer un token CSRF s'il n'existe pas
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérifier le token CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors[] = "Erreur de validation du formulaire.";
        } else {
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
                                $profilesList = explode(',', trim($row[10] ?? ''));
                                $functionalityKey = trim($row[11]);

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

                                // Créer le document de la fonctionnalité
                                $functionalityData = [
                                    'key' => $functionalityKey,
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
                                    'created' => date('d-m-Y H:i:s')
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
        }
    }

    ?>
    <?php include_once "partials/header.php"; ?>

    <title>Importer des Fonctionnalités | CFAO Mobility Academy</title>

    <style>
        .container {
            max-width: 650px;
        }
        .logo {
            display: block;
            max-width: 75%;
            height: auto;
            margin: 0 auto 20px;
        }
    </style>

    <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
        <div class="container mt-5">
            <img src="../../public/images/logo.png" alt="Logo" class="logo" height="170">
            <h1 class="my-3 text-center">Importer des Fonctionnalités</h1>

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

            <!-- Formulaire d'importation de fonctionnalités -->
            <form enctype="multipart/form-data" method="POST" action="importFunctionalities.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <!-- Importer les fonctionnalités via Excel -->
                <div class="form-group">
                    <label for="excelFile">Importer les fonctionnalités via Excel</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" name="excel" id="excelFile" accept=".xls,.xlsx" required>
                        <label class="custom-file-label" for="excelFile">Choisir un fichier...</label>
                    </div>
                    <small class="form-text text-muted">Le fichier Excel doit contenir les colonnes : Nom, Description, URL, Icône, Type d'icône, Groupe, Ordre, Module, Actif (Oui/Non), Profils</small>
                </div>
                <!-- Bouton de soumission -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Importer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inclusion des scripts nécessaires -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Script pour afficher le nom du fichier sélectionné -->
    <script>
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });
    </script>

    <?php include_once "partials/footer.php"; ?>
<?php } ?>