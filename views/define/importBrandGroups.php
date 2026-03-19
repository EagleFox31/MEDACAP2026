<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Si le formulaire est soumis
    if (isset($_POST["submit"])) {
        try {
            // 1) Connexion à MongoDB
            $conn = new MongoDB\Client("mongodb://localhost:27017");
            $academy = $conn->academy;

            // 2) Choix de la collection
            $collection = $academy->nonSupportedGroupsByBrandLevel;

            // 3) Lecture du fichier Excel
            $filePath = $_FILES["excel"]["tmp_name"];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();

            // La première ligne (index 0) contient normalement l'en-tête :
            // [ "Marque", "Junior", "Senior", "Expert" ]
            // On traite les données à partir de la deuxième ligne (index 1)

            // Prépare un tableau pour les documents à insérer
            $docsToInsert = [];

            // On boucle sur les lignes du fichier, en partant de la ligne 2 (index 1)
            for ($rowIndex = 1; $rowIndex < count($sheetData); $rowIndex++) {
                $row = $sheetData[$rowIndex];

                // Assurons-nous d'avoir au moins 4 colonnes
                if (count($row) < 4) {
                    continue;
                }

                // Lecture des colonnes
                $brandName   = trim($row[0] ?? ""); // Colonne A
                $juniorCell  = trim($row[1] ?? ""); // Colonne B
                $seniorCell  = trim($row[2] ?? ""); // Colonne C
                $expertCell  = trim($row[3] ?? ""); // Colonne D

                // Si la colonne "Marque" est vide, on saute cette ligne
                if (empty($brandName)) {
                    continue;
                }

                // Construction de la structure "levels"
                // On peut séparer plusieurs groupes par virgule, point-virgule, saut de ligne...
                // Ici, on prend l'exemple d'une séparation par virgule.
                $levels = [
                    [
                        'level'  => 'Junior',
                        'groups' => array_filter(array_map('trim', explode(',', $juniorCell)))
                    ],
                    [
                        'level'  => 'Senior',
                        'groups' => array_filter(array_map('trim', explode(',', $seniorCell)))
                    ],
                    [
                        'level'  => 'Expert',
                        'groups' => array_filter(array_map('trim', explode(',', $expertCell)))
                    ]
                ];

                // Prépare un document final pour cette marque
                $doc = [
                    'brand'  => $brandName,
                    'levels' => $levels
                ];

                $docsToInsert[] = $doc;
            }

            // 4) Insertion/Update en base
            // On choisit ici de tout supprimer puis de tout ré-insérer
            $collection->deleteMany([]); // vide la collection avant réimport
            if (!empty($docsToInsert)) {
                $collection->insertMany($docsToInsert);
            }

            $success_msg = "Import réussi pour " . count($docsToInsert) . " marque(s).";
        } catch (Exception $e) {
            // En cas d'erreur
            $error_msg = "Erreur lors de l'import : " . $e->getMessage();
        }
    }
?>
<?php include_once "partials/header.php"; ?>

<!-- Styles supplémentaires -->
<style>
    input {
        background-color: #fff !important;
        border-style: solid;
    }
    /* Spinner masqué par défaut pour éviter qu'il tourne en permanence */
    .indicator-progress {
        display: none;
    }
</style>

<!-- Titre de la page -->
<title>Importer Marques & Groupes Fonctionnels | CFAO Mobility Academy</title>

<!-- Corps de la page -->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50 text-center">
                <img src="../../public/images/logo.png" alt="logo" height="170" style="max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class="my-3 text-center">Importer Marques & Groupes Fonctionnels</h1>

                <?php if (isset($success_msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <center><strong><?php echo $success_msg; ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>
                <?php if (isset($error_msg)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <center><strong><?php echo $error_msg; ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>

                <!-- Formulaire d’import -->
                <form enctype='multipart/form-data' method='POST'><br>
                    <!-- Sélection du fichier -->
                    <div class="fv-row mb-7">
                        <label class="required form-label fw-bolder text-dark fs-6">
                            Import via Excel (.xlsx)
                        </label>
                        <div class="input-group">
                            <input type="file" class="form-control form-control-solid" name="excel" required />
                            <div class="input-group-append">
                                <span class="input-group-text">.xlsx</span>
                            </div>
                        </div>
                    </div>
                    <!-- Bouton Valider -->
                    <div class="modal-footer flex-center">
                        <button type="submit" name="submit" class="btn btn-primary" id="submitBtn">
                            <span class="indicator-label">Valider</span>
                            <span class="indicator-progress" id="spinnerIndicator">
                                Patientez...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
            <!--end::Modal body-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->

<!-- Script pour gérer la fermeture de l’alerte + activer le spinner au click -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fermer alertes
        const closeButtons = document.querySelectorAll('.alert .close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = this.closest('.alert');
                alert.remove();
            });
        });

        // Afficher le spinner lorsqu'on clique sur "Valider"
        const submitBtn = document.getElementById('submitBtn');
        const spinner = document.getElementById('spinnerIndicator');

        if (submitBtn && spinner) {
            submitBtn.addEventListener('click', function() {
                // Masque le label et affiche le spinner
                spinner.style.display = 'inline-block';
            });
        }
    });
</script>

<?php include_once "partials/footer.php"; ?>

<?php
} // fin du else
?>
