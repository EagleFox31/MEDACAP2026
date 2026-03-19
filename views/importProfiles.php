<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    if (isset($_POST["submit"])) {
        // Create connection
        $client = new MongoDB\Client("mongodb://localhost:27017");

        // Connecting to the database
        $academy = $client->academy;

        // Connecting to the profiles collection
        $profilesCollection = $academy->profiles;

        // Check if a file has been uploaded
        if (isset($_FILES["excel"]) && $_FILES["excel"]["error"] == 0) {
            $fileName = $_FILES["excel"]["name"];
            $fileTmpPath = $_FILES["excel"]["tmp_name"];
            $fileSize = $_FILES["excel"]["size"];
            $fileType = $_FILES["excel"]["type"];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Allowed file extensions
            $allowedExtensions = ['xls', 'xlsx'];
            if (in_array($fileExtension, $allowedExtensions)) {
                try {
                    // Load the Excel file
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
                    $data = $spreadsheet->getActiveSheet()->toArray();

                    // Check if the file is not empty
                    if (count($data) > 1) {
                        // Remove header row
                        unset($data[0]);

                        foreach ($data as $row) {
                            $profileName = trim($row[0]);
                            $profileDescription = trim($row[1]);
                            $profileIcon = trim($row[2]);
                            $isActive = strtolower(trim($row[3])) == 'oui' ? true : false;

                            // Validate data
                            if (empty($profileName)) {
                                $error_msg = "Le nom du profil est obligatoire pour chaque entrée.";
                                continue;
                            }

                            // Check if the profile already exists
                            $exist = $profilesCollection->findOne(['name' => $profileName]);
                            if ($exist) {
                                $error_msg = "Le profil '{$profileName}' existe déjà et n'a pas été importé à nouveau.";
                                continue;
                            }

                            // Create the profile document
                            $profile = [
                                'name' => $profileName,
                                'description' => $profileDescription,
                                'icon' => $profileIcon,
                                'functionalities' => [],
                                'active' => $isActive,
                                'created' => date("d-m-Y H:i:s"),
                                'updated' => date("d-m-Y H:i:s")
                            ];

                            // Insert the profile into the collection
                            $profilesCollection->insertOne($profile);
                        }
                        $success_msg = "L'importation des profils a été effectuée avec succès.";
                    } else {
                        $error_msg = "Le fichier Excel est vide.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Erreur lors de la lecture du fichier Excel : " . $e->getMessage();
                }
            } else {
                $error_msg = "Extension de fichier non autorisée. Veuillez télécharger un fichier Excel (.xls ou .xlsx).";
            }
        } else {
            $error_msg = "Veuillez sélectionner un fichier Excel à importer.";
        }
    }
?>
<?php include_once "partials/header.php"; ?>

<style>
input {
    background-color: #fff !important;
    border-style: solid;
}
</style>

<!--begin::Title-->
<title>Importer des profils | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50 text-center">
                <img src="../../public/images/logo.png" alt="Logo" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class="my-3 text-center">Importer des profils</h1>

                <?php if (isset($success_msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <center><strong><?php echo $success_msg; ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>
                <?php if (isset($error_msg)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <center><strong><?php echo $error_msg; ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>

                <form enctype="multipart/form-data" method="POST"><br>
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <!--begin::Label-->
                        <label class="required form-label fw-bolder text-dark fs-6">Importer les profils via Excel</label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <div class="input-group">
                            <input type="file" class="form-control form-control-solid" name="excel" style="text-align: center;" accept=".xls,.xlsx" />
                            <div class="input-group-append">
                                <span class="input-group-text" style="height: 10px !important; padding: 15px;">.xls / .xlsx</span>
                            </div>
                        </div>
                        <!--end::Input-->
                    </div>
                    <!--end::Input group-->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" name="submit" class="btn btn-primary">
                            <span class="indicator-label">
                                Valider
                            </span>
                            <span class="indicator-progress">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <!--end::Button-->
                    </div>
                    <!--end::Modal footer-->
                </form>
            </div>
            <!--end::Modal body-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->

<script>
    // Function to handle closing of the alert message
    document.addEventListener('DOMContentLoaded', function() {
        const closeButtons = document.querySelectorAll('.alert .close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = this.closest('.alert');
                alert.remove();
            });
        });
    });
</script>
<?php include_once "partials/footer.php"; ?>

<?php
} // End of else block
?>
