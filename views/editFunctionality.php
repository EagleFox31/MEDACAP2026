<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Connexion à MongoDB
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $functionalitiesCollection = $academy->functionalities;

    // Récupérer les profils disponibles
    $profiles = ["Super Admin", "Directeur Groupe", "Admin", "RH", "Directeur Filiale", "Manager", "Manager-Technicien", "Technicien"];

    if (isset($_GET['key'])) {
        $functionalityKey = $_GET['key'];

        // Récupérer la fonctionnalité
        $functionality = $functionalitiesCollection->findOne(['key' => $functionalityKey]);

        if (!$functionality) {
            echo "Fonctionnalité introuvable.";
            exit();
        }

        if (isset($_POST["submit"])) {
            $functionalityName = $_POST["name"];
            $functionalityDescription = $_POST["description"];
            $functionalityURL = $_POST["url"];
            $selectedProfiles = $_POST["profiles"] ?? [];
            $isActive = isset($_POST["active"]) ? true : false;

            // Mettre à jour la fonctionnalité
            $functionalitiesCollection->updateOne(
                ['key' => $functionalityKey],
                ['$set' => [
                    'name' => $functionalityName,
                    'description' => $functionalityDescription,
                    'url' => $functionalityURL,
                    'active' => $isActive,
                    'updated' =>  date("d-m-Y H:i:s")
                ]]
            );

            $success_msg = "La fonctionnalité a été mise à jour avec succès.";

            // Rafraîchir la fonctionnalité
            $functionality = $functionalitiesCollection->findOne(['key' => $functionalityKey]);
        }

        ?>
        <?php include_once "partials/header.php"; ?>

        <!--begin::Title-->
        <title>Modifier la fonctionnalité | CFAO Mobility Academy</title>
        <!--end::Title-->
        <!--begin::Body-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
            <!--begin::Container-->
            <div class="container-xxl">
                <!--begin::Modal body-->
                <div class="container mt-5 w-50">
                    <h1 class="my-3 text-center">Modifier la fonctionnalité</h1>

                    <?php if (isset($success_msg)) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <center><strong><?php echo $success_msg; ?></strong></center>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php } ?>

                    <form method="POST">
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                Identifiant de la fonctionnalité
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type="text" class="form-control form-control-solid" name="key" value="<?php echo htmlspecialchars($functionality['key']); ?>" disabled />
                            <!--end::Input-->
                        </div>
                        <!--end::Input group-->

                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                Nom de la fonctionnalité
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type="text" class="form-control form-control-solid" name="name" value="<?php echo htmlspecialchars($functionality['name']); ?>" required />
                            <!--end::Input-->
                        </div>
                        <!--end::Input group-->

                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                Description de la fonctionnalité
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <textarea class="form-control form-control-solid" name="description"><?php echo htmlspecialchars($functionality['description']); ?></textarea>
                            <!--end::Input-->
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                URL de la fonctionnalité
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type="text" class="form-control form-control-solid" name="url" value="<?php echo isset($functionality['url']) ? htmlspecialchars($functionality['url']) : ''; ?>" required />
                            <!--end::Input-->
                        </div>
                        <!--end::Input group-->


                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                Profils ayant accès
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select name="profiles[]" multiple class="form-select form-select-solid fw-bold bg-white">
                                <?php foreach ($profiles as $profileOption) { 
                                    $isSelected = in_array($profileOption, $functionality['profiles']);
                                ?>
                                    <option value="<?php echo $profileOption; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo $profileOption; ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <!--end::Input-->
                        </div>
                        <!--end::Input group-->
                        <!-- Champ pour activer/désactiver la fonctionnalité -->
                        <div class="d-flex flex-column mb-7 fv-row">
                            <label class="form-label fw-bolder text-dark fs-6">
                                Activer la fonctionnalité
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="activeSwitch" <?php echo (isset($functionality['active']) && $functionality['active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activeSwitch">Active</label>
                            </div>
                        </div>

                        <!--begin::Modal footer-->
                        <div class="modal-footer flex-center">
                            <!-- Bouton de soumission -->
                            <div class="modal-footer flex-center">
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <span class="indicator-label">
                                        Enregistrer les modifications
                                    </span>
                                    <span class="indicator-progress">
                                        Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <!--end::Modal footer-->
                    </form>
                </div>
                <!--end::Modal body-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Body-->

        <script>
            // Fonction pour fermer le message d'alerte
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
    } else {
        echo "Aucune fonctionnalité spécifiée.";
        exit();
    }
}
?>
