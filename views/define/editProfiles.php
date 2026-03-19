<?php
session_start();
include_once "../language.php";

// Verify if the user is authenticated as Super Admin
if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}

// Include navigation component
include_once 'navigation.php';

// Update navigation history
update_navigation_history();
require_once "../../vendor/autoload.php";

// Connect to MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$academy = $client->academy;
$profilesCollection = $academy->profiles;
$functionalitiesCollection = $academy->functionalities;

$errors = [];
$success_msg = '';

if (isset($_GET['id'])) {
    $profileId = new MongoDB\BSON\ObjectId($_GET['id']);

    // Retrieve the profile
    $profile = $profilesCollection->findOne(['_id' => $profileId]);

    if (!$profile) {
        echo "Profil introuvable.";
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve and sanitize form data
        $profileName = htmlspecialchars(trim($_POST["name"] ?? ''));
        $profileDescription = htmlspecialchars(trim($_POST["description"] ?? ''));
        $profileIcon = htmlspecialchars(trim($_POST["icon"] ?? ''));
        $isActive = isset($_POST["active"]) ? true : false;

        // Data validation
        if (empty($profileName)) {
            $errors[] = "Le champ nom est obligatoire.";
        }

        // Update the profile
        if (empty($errors)) {
            $updateResult = $profilesCollection->updateOne(
                ['_id' => $profileId],
                ['$set' => [
                    'name' => $profileName,
                    'description' => $profileDescription,
                    'icon' => $profileIcon,
                    'active' => $isActive,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            $success_msg = "Le profil a été mis à jour avec succès.";

            // Refresh the profile data
            $profile = $profilesCollection->findOne(['_id' => $profileId]);
        }
    }

    include_once "partials/header.php";
    ?>

    <title>Modifier le profil | CFAO Mobility Academy</title>

    <!--begin::Body-->
    <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
        <!--begin::Container-->
        <div class="container-xxl">
            <div class="container mt-5">
                <div class="card mx-auto shadow-sm" style="max-width: 600px;">
                    <div class="card-header text-center bg-primary text-white">
                        <h3>Modifier le profil</h3>
                    </div>
                    <div class="card-body">
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

                        <form method="POST">
                            <!-- Name field -->
                            <div class="form-group">
                                <label for="name">Nom du profil</label>
                                <input type="text" class="form-control" name="name" id="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                            </div>
                            <!-- Description field -->
                            <div class="form-group">
                                <label for="description">Description du profil</label>
                                <textarea class="form-control" name="description" id="description"><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                            </div>
                            <!-- Icon field -->
                            <div class="form-group">
                                <label for="icon">Icône du profil (classe Font Awesome)</label>
                                <input type="text" class="form-control" name="icon" id="icon" value="<?php echo htmlspecialchars($profile['icon'] ?? ''); ?>">
                                <small class="form-text text-muted">Utilisez les classes d'icônes Font Awesome (par exemple, <code>fas fa-user</code>).</small>
                            </div>
                            <!-- Active field -->
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" name="active" id="active" <?php echo ($profile['active'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">Activer le profil</label>
                            </div>
                            <!-- Submit button -->
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <span class="indicator-label">
                                        Enregistrer les modifications
                                    </span>
                                    <span class="indicator-progress" style="display: none;">
                                        Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::Body-->

    <!--begin::Scripts-->
    <script>
        // Function to close alert messages
        document.addEventListener('DOMContentLoaded', function() {
            const closeButtons = document.querySelectorAll('.alert .close');
            closeButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const alert = this.closest('.alert');
                    alert.remove();
                });
            });

            // Handle submit button
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

    <?php
    include_once "partials/footer.php";
} else {
    echo "ID de profil manquant.";
    exit();
}
?>
