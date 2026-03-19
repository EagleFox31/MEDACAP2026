<?php
session_start();
include_once "../language.php";

// Vérifier si l'utilisateur est authentifié en tant que Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
} else {

require_once "../../vendor/autoload.php";

// Connexion à MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$profilesCollection = $academy->profiles;
$usersCollection = $academy->users;

if (isset($_POST['submit'])) {
    // Récupérer et échapper les données du formulaire
    $label = htmlspecialchars(trim($_POST["name"] ?? ''));
    $description = htmlspecialchars(trim($_POST["description"] ?? ''));
    $icon = htmlspecialchars(trim($_POST["icon"] ?? ''));
    $isActive = isset($_POST["active"]) ? true : false;
    
    // Vérifier si le profil existe déjà
    $exist = $profilesCollection->findOne(['name' => $label]);

    // Validation des données
    if (empty($label)) {
        $errors = "Le champ nom est obligatoire.";
    } elseif ($exist) {
        $error_msg = "Un profil avec ce nom existe déjà.";
    } else {
        // Créer le document du profil
        $profile = [
            'name' => $label,
            'description' => $description,
            'icon' => $icon,
            'functionalities' => [],
            'active' => $isActive,
            'created' => date("d-m-Y H:i:s")
        ];

        // Insérer le profil dans la collection
        $profilesCollection->insertOne($profile);

        // Réinitialiser les variables du formulaire
        $label = $description = $icon = '';
    }
    $success_msg = "Le profil a été créé avec succès.";
}
?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $addProfile ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class='my-3 text-center'><?php echo $addProfile ?></h1>

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

                <form method="POST"><br>
                    <!--begin::Input group-->
                    <div id='container' class="row fv-row mb-7">
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $profileName ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' 
                            placeholder="Exemple : Technicien" name="name" id="name"
                             value="<?php echo htmlspecialchars($label ?? ''); ?>" required/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $profileDescription ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <textarea type='text' class='form-control form-control-solid' placeholder="Description du profil"
                             name="description" id="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo $profileIcon ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type='text' class='form-control form-control-solid' 
                            placeholder="Exemple : fas fa-user" name="icon" id="icon"
                             value="<?php echo htmlspecialchars($icon ?? ''); ?>" required/>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!-- Champ Actif -->
                        <div class="form-group form-check mb-5">
                            <input type="checkbox" class="form-check-input" name="active" id="active" <?php echo (isset($isActive) && $isActive) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="active"><?php echo $profileActivation ?></label>
                        </div>
                    </div>
                    <!--end::Scroll-->
                    <!--end::Modal body-->
                    <!--begin::Modal footer-->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" name="submit" class=" btn btn-primary">
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
        
        // Gestion du bouton de soumission
        const form = document.querySelector('form');
        const submitButton = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', function() {
            submitButton.querySelector('.indicator-label').style.display = 'none';
            submitButton.querySelector('.indicator-progress').style.display = 'inline-block';
            submitButton.disabled = true;
        });
    });
</script>
<?php include_once "partials/footer.php"; ?>
<?php } ?>