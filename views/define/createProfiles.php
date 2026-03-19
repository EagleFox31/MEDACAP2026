<?php
session_start();
include_once "../language.php";

// Vérifier si l'utilisateur est authentifié en tant que Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}

// Inclure le composant de navigation
include_once 'navigation.php';

// Mettre à jour l'historique de navigation
update_navigation_history();
require_once "../../vendor/autoload.php";

// Connexion à MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$profilesCollection = $academy->profiles;
$usersCollection = $academy->users;

$errors = []; // Tableau pour stocker les messages d'erreur
$success_msg = ''; // Message de succès

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Valeurs par défaut
        $functionalities = []; // Tableau vide
        // Créer le document du profil
        $profile = [
            'name' => $profileName,
            'description' => $profileDescription,
            'icon' => $profileIcon,
            'functionalities' => $functionalities,
            'active' => $isActive,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        // Insérer le profil dans la collection
        $profilesCollection->insertOne($profile);

        $success_msg = "Le profil a été créé avec succès.";
        // Réinitialiser les variables du formulaire
        $profileName = $profileDescription = $profileIcon = '';
    }
}
?>
<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title>Créer un profil | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl">
        <!--begin::Content-->
        <div class="d-flex flex-column flex-center flex-column-fluid p-10 pb-lg-20">
            <!--begin::Wrapper-->
            <div class="w-lg-600px bg-white rounded shadow-sm p-10 p-lg-15 mx-auto">
                <!-- Image du logo -->
                <div class="text-center mb-10">
                    <img src="../../public/images/logo.png" alt="Logo" height="170" style="max-width: 75%; height: auto;">
                </div>
                <!-- Titre -->
                <h1 class="text-center mb-5">Créer un profil</h1>
                <!-- Message de succès -->
                <?php if (!empty($success_msg)) { ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong><?php echo $success_msg; ?></strong>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php } ?>

                <!-- Messages d'erreur -->
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

                <!--begin::Formulaire-->
                <form method="POST">
                    <!-- Champ Nom -->
                    <div class="form-group mb-5">
                        <label for="name" class="form-label fw-bolder text-dark fs-6">
                            <span class="required">Nom du profil</span>
                        </label>
                        <input type="text" class="form-control" placeholder="Exemple : Technicien" name="name" id="name" value="<?php echo htmlspecialchars($profileName ?? ''); ?>" required />
                    </div>

                    <!-- Champ Description -->
                    <div class="form-group mb-5">
                        <label for="description" class="form-label fw-bolder text-dark fs-6">
                            Description du profil
                        </label>
                        <textarea class="form-control" placeholder="Description du profil" name="description" id="description"><?php echo htmlspecialchars($profileDescription ?? ''); ?></textarea>
                    </div>

                    <!-- Champ Icône -->
                    <div class="form-group mb-5">
                        <label for="icon" class="form-label fw-bolder text-dark fs-6">
                            Icône du profil (classe Font Awesome)
                        </label>
                        <input type="text" class="form-control" placeholder="Exemple : fas fa-user" name="icon" id="icon" value="<?php echo htmlspecialchars($profileIcon ?? ''); ?>" />
                        <small class="form-text text-muted">Utilisez les classes d'icônes Font Awesome (par exemple, <code>fas fa-user</code>).</small>
                    </div>

                    <!-- Champ Actif -->
                    <div class="form-group form-check mb-5">
                        <input type="checkbox" class="form-check-input" name="active" id="active" <?php echo (isset($isActive) && $isActive) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="active">Activer le profil</label>
                    </div>

                    <!-- Bouton de soumission -->
                    <div class="text-center">
                        <button type="submit" name="submit" class="btn btn-primary">
                            <span class="indicator-label">
                                Valider
                            </span>
                            <span class="indicator-progress" style="display: none;">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
                <!--end::Formulaire-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Container-->
</div>
<!--end::Body-->

<!--begin::Scripts-->
<script>
    // Fonction pour fermer les messages d'alerte
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
<!--end::Scripts-->

<?php include_once "partials/footer.php"; ?>
