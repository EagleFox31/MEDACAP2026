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
        $conn = new MongoDB\Client("mongodb://localhost:27017");

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $trainings = $academy->trainings;

        $filePath = $_FILES["excel"]["tmp_name"];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $data = $spreadsheet->getActiveSheet()->toArray();

        // remove header
        unset($data[0]);
        
        function formatDate($dateInput) {
            // Vérifier si l'entrée contient une heure
            if (strpos($dateInput, ' ') === false) {
                // Si l'heure n'est pas spécifiée, ajouter une heure par défaut (par exemple, 00:00:00)
                //$dateInput .= ' 00:00:00'; // Vous pouvez changer cela à l'heure de votre choix
            }

            // Essayer de créer un objet DateTime à partir de l'entrée
            $dateTime = DateTime::createFromFormat('d-m-Y H:i:s', $dateInput);
            
            // Si la date n'est pas au format Y-m-d H:i:s, essayer d'autres formats
            if (!$dateTime) {
                $dateTime = DateTime::createFromFormat('d-m-Y H:i:s', $dateInput) // Format jour-mois-année heure:minute:seconde
                    ?: DateTime::createFromFormat('m/d/Y H:i:s', $dateInput) // Format mois/jour/année heure:minute:seconde
                    ?: DateTime::createFromFormat('Y/m/d H:i:s', $dateInput) // Format année/mois/jour heure:minute:seconde
                    ?: DateTime::createFromFormat('d/m/Y H:i:s', $dateInput) // Format jour/mois/année heure:minute:seconde
                    ?: DateTime::createFromFormat('d-m-Y H:i', $dateInput) // Format jour-mois-année heure:minute
                    ?: DateTime::createFromFormat('d-m-Y', $dateInput); // Format jour-mois-année
            }

            // Vérifier si la conversion a réussi
            if ($dateTime instanceof DateTime) {
                // Retourner la date au format Y-m-d H:i:s
                return $dateTime->format('Y-d-m H:i:s');
            } else {
                // Gérer l'erreur si la date est invalide
                return ''; // Ou vous pouvez lancer une exception ou retourner un message d'erreur
            }
        }

        foreach ($data as $row) {
            $startDate = [];
            $endDate = [];
            $specialities = [];
            
            $code = trim($row["0"]);
            $label = trim($row["1"]);
            $type = trim($row["2"]);
            $brand = trim($row["3"]);
            $specialities = array_map('trim', explode(",", $row["4"] ?? ''));
            $level = trim($row["5"]);
            $link = trim($row["6"]);
            $place = array_map('trim', explode(",", $row["7"] ?? ''));
            $trainer = trim($row["8"]);
            $startDate = array_map('trim', explode(",", $row["9"] ?? ''));
            $endDate = array_map('trim', explode(",", $row["10"] ?? ''));
            $duration = trim($row["11"]);

            // $startDt = [];
            // $endDt = [];
            // foreach($startDate as $i => $start) {
            //     $formattedStartDate = formatDate($start.'08:00:00');
            //     array_push($startDt, $formattedStartDate);
            //     $formattedEndDate = formatDate($endDate[$i].'17:00:00');
            //     array_push($endDt, $formattedEndDate);
            // }

            $exist = $trainings->findOne([
                '$and' => [
                    ["code" => $code],
                    ["active" => true]
                ],
            ]);
            if ($exist) {
                $error_msg = "Une formation existe déjà.";
            }
            $trainingData = [
                "users" => [],
                "code" => $code,
                "label" => $label,
                "type" => $type,
                "brand" => $brand,
                "level" => $level,
                "link" => $link,
                "places" => $place,
                "trainer" => $trainer,
                "specialities" => $specialities,
                "duration" => $duration,
                "startDates" => $startDate,
                "endDates" => $endDate,
                "active" => true,
                "created" => date("Y-m-d H:i:s"),
            ];
            $trainings->insertOne($trainingData);
        }
        $success_msg = $success_training;
    }
    ?>
<?php include_once "partials/header.php"; ?>
<style>
input {
    background-color: #fff ! important;
    border-style: solid;
}
</style>
<!--begin::Title-->
<title><?php echo $import_training ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50 text-center">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class="my-3 text-center"><?php echo $import_training ?></h1>

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

                <form enctype='multipart/form-data' method='POST'><br>
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <!--begin::Label-->
                        <label class="required form-label fw-bolder text-dark fs-6"><?php echo $import_training.' via '.$excel ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <div class="input-group">
                            <input type="file" class="form-control form-control-solid" placeholder="" name="excel" style="text-align: center;" />
                            <div class="input-group-append">
                                <span class="input-group-text" style = "height: 10px !important; padding: 15px;">.xlsx</span>
                            </div>
                        </div>
                        <!--end::Input-->
                    </div>
                    <!--end::Input group-->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" name="submit" class="btn btn-primary">
                            <span class="indicator-label">
                                <?php echo $valider ?>
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
} ?>
