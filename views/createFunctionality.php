<?php
//createFunctionalities.php
session_start();
include_once "../language.php";
include_once "icons.php";

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

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les profils disponibles
$profilesCursor = $profilesCollection->find(['active' => true]);
$profiles = iterator_to_array($profilesCursor);

// Récupérer les groupes existants sans doublons
$groups = $functionalitiesCollection->distinct('group');
$groups = array_filter($groups); // Supprimer les valeurs nulles ou vides



// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Erreur de validation du formulaire.";
    } else {
        // Création d'une fonctionnalité
        $functionalityKey = trim($_POST["key"] ?? '');
        $functionalityName = trim($_POST["name"] ?? '');
        $functionalityDescription = trim($_POST["description"] ?? '');
        $functionalityURL = trim($_POST["url"] ?? '');
        $functionalityIconType = trim($_POST["icon_type"] ?? 'font_awesome');
        $selectedIcon = trim($_POST["icon"] ?? '');
        $customIcon = trim($_POST["custom_icon"] ?? '');
        $functionalityGroup = trim($_POST["group"] ?? '');
        $functionalityGroupOrder = (int) ($_POST["group_order"] ?? 0);
        $functionalityOrder = (int) ($_POST["order"] ?? 999);
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
                // 'profiles' => $selectedProfiles,
                'created' => date('d-m-Y H:i:s')
            ];

            // Insérer la fonctionnalité dans la collection
            $insertResult = $functionalitiesCollection->insertOne($functionalityData);

            // Récupérer l'identifiant de la nouvelle fonctionnalité
            $functionalityObjectId = $insertResult->getInsertedId();

            // Mettre à jour les profils sélectionnés
            foreach ($selectedProfiles as $profileName) {
                $profilesCollection->updateOne(
                    ['name' => $profileName],
                    ['$addToSet' => ['functionalities' => $functionalityObjectId]]
                );
            }

            $success_msg = "La fonctionnalité a été créée avec succès.";
        }
    }
}

?>
<?php include_once "partials/header.php"; ?>

<!-- Inclure Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<!-- Inclure Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<title>Créer une Fonctionnalité | CFAO Mobility Academy</title>

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
        <h1 class="my-3 text-center">Créer une Fonctionnalité</h1>

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

        <!-- Formulaire de création de fonctionnalité -->
        <form method="POST" action="createFunctionality">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <!-- Clé de la fonctionnalité -->
            <div class="form-group">
                <label for="key">Identifiant de la fonctionnalité</label>
                <input type="text" class="form-control" name="key" id="key" required>
            </div>
            <!-- Nom de la fonctionnalité -->
            <div class="form-group">
                <label for="name">Nom de la fonctionnalité</label>
                <input type="text" class="form-control" name="name" id="name" required>
            </div>
            <!-- Description de la fonctionnalité -->
            <div class="form-group">
                <label for="description">Description de la fonctionnalité</label>
                <textarea class="form-control" name="description" id="description"></textarea>
            </div>
            <!-- URL de la fonctionnalité -->
            <div class="form-group">
                <label for="url">URL de la fonctionnalité</label>
                <input type="text" class="form-control" name="url" id="url">
                <small class="form-text text-muted">Chemin d'accès relatif de la fonctionnalité (par exemple, <code>./listFunctionalities.php</code>).</small>
            </div>
            <!-- Type d'icône -->
            <div class="form-group">
                <label for="iconType">Type d'icône</label>
                <select name="icon_type" id="iconType" class="form-control" style="padding-bottom: 8px;padding-top: 6px;">
                    <option value="">-- Sélectionner un type d'icône --</option>
                    <option value="font_awesome">Font Awesome</option>
                    <option value="ki_duotone">Ki Duotone</option>
                    <option value="bi">Bootstrap Icons</option>
                </select>
            </div>
            <!-- Icône de la fonctionnalité -->
            <div class="form-group">
                <label for="icon">Icône de la fonctionnalité</label>
                <select name="icon" id="icon" class="form-control" style="padding-bottom: 8px;padding-top: 6px;">
                    <!-- Options remplies dynamiquement avec JavaScript -->
                </select>
                <small class="form-text text-muted">Sélectionnez une icône ou choisissez "Autre..." pour saisir une icône personnalisée.</small>
            </div>
            <!-- Icône personnalisée (champ caché par défaut) -->
            <div class="form-group" id="customIconGroup" style="display: none;">
                <label for="customIcon">Icône personnalisée (classe CSS)</label>
                <input type="text" class="form-control" name="custom_icon" id="customIcon">
            </div>
            <!-- Prévisualisation de l'icône -->
            <div class="form-group">
                <label>Prévisualisation de l'icône :</label>
                <div id="iconPreview" style="font-size: 24px;"></div>
            </div>
            <!-- Groupe de la fonctionnalité -->
            <div class="form-group">
                <label for="group">Groupe de la fonctionnalité</label>
                <select name="group" id="group" class="form-control" style="padding-bottom: 8px;padding-top: 6px;">
                    <option value="">-- Sélectionner un groupe --</option>
                    <?php foreach ($groups as $group) { ?>
                        <option value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars($group); ?></option>
                    <?php } ?>
                </select>
            </div>
            <!-- Ordre du groupe -->
            <div class="form-group">
                <label for="groupOrder">Ordre du groupe</label>
                <input type="number" class="form-control" name="group_order" id="groupOrder" value="0">
                <small class="form-text text-muted">Détermine l'ordre d'affichage des groupes dans le menu.</small>
            </div>
            <!-- Ordre d'affichage -->
            <div class="form-group">
                <label for="order">Ordre d'affichage</label>
                <input type="number" class="form-control" name="order" id="order" value="999">
            </div>
            <!-- Modules -->
            <div class="form-group">
                <label for="modules">Modules</label>
                <select name="modules[]" id="modules" class="form-control" multiple required style="padding-bottom: 8px;padding-top: 6px;">
                    <option value="measure">Measure</option>
                    <option value="explore">Explore</option>
                    <option value="define">Define</option>
                    <!-- Ajoutez d'autres modules si nécessaire -->
                </select>
                <small class="form-text text-muted">Maintenez la touche Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs modules.</small>
            </div>

            <!-- Assigner à des profils -->
            <!-- Assigner à des profils -->
            <div class="fv-row mb-7">
                <label for="profilesCreate" class="fs-6 fw-bold mb-2">Assigner à des profils</label>
                <select name="profiles[]" id="profilesCreate" class="form-select form-select-solid bg-white" multiple
                    data-control="select2" data-placeholder="Sélectionnez des profils" >
                    <?php foreach ($profiles as $profile) { ?>
                        <option value="<?php echo htmlspecialchars($profile['name']); ?>">
                            <?php echo htmlspecialchars($profile['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="form-text text-muted">Maintenez la touche Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs profils.</small>
            </div>

            <!-- Activer la fonctionnalité -->
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                <label class="form-check-label" for="active">Activer la fonctionnalité</label>
            </div>
            <!-- Bouton de soumission -->
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Créer la fonctionnalité</button>
            </div>
        </form>
    </div>
</div>

<!-- Inclusion des scripts nécessaires -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Script pour gérer les icônes -->
<script>
    // Activer Select2 pour le champ
    $(document).ready(function () {
        $('#profilesCreate').select2();
    });
    // Liste complète des icônes par type
    const icons = <?php echo json_encode($icons); ?>;
    $(document).ready(function() {
    $('#modules').select2({
        placeholder: "Sélectionnez des modules",
        allowClear: true
    });
    });


    // Fonction pour mettre à jour le sélecteur d'icônes
    function updateIconSelector(iconType, iconSelectId, customIconGroupId, iconPreviewId, selectedIconValue = '') {
        const iconSelect = document.getElementById(iconSelectId);
        const customIconGroup = document.getElementById(customIconGroupId);
        const iconPreview = document.getElementById(iconPreviewId);

        // Vider le sélecteur
        iconSelect.innerHTML = '<option value="">-- Sélectionnez une icône --</option>';

        if (icons[iconType]) {
            icons[iconType].forEach(icon => {
                const option = document.createElement('option');
                option.value = icon.value;
                option.text = icon.text;
                option.dataset.paths = icon.paths || 0; // Stocker le nombre de paths dans un attribut data
                if (icon.value === selectedIconValue) {
                    option.selected = true;
                }
                iconSelect.appendChild(option);
            });
        }

        // Ajouter l'option "Autre..."
        const otherOption = document.createElement('option');
        otherOption.value = 'other';
        otherOption.text = 'Autre...';
        iconSelect.appendChild(otherOption);

        // Prévisualiser l'icône si une icône est sélectionnée
        if (selectedIconValue && selectedIconValue !== 'other') {
            if (iconType === 'ki_duotone') {
                // Récupérer le nombre de paths pour l'icône sélectionnée
                const selectedOption = iconSelect.querySelector(`option[value="${selectedIconValue}"]`);
                const paths = parseInt(selectedOption.dataset.paths) || 2; // Par défaut, 2 paths
                // Générer les spans
                let spans = '';
                for (let i = 1; i <= paths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.innerHTML = `<i class="${selectedIconValue} fs-2">${spans}</i>`;
            } else {
                iconPreview.innerHTML = `<i class="${selectedIconValue} fs-2"></i>`;
            }
        } else {
            iconPreview.innerHTML = '';
        }
    }

    // Gestion du changement du type d'icône
    document.getElementById('iconType').addEventListener('change', function() {
        const iconType = this.value;
        updateIconSelector(iconType, 'icon', 'customIconGroup', 'iconPreview');
    });

    // Gestion du changement de l'icône sélectionnée
    document.getElementById('icon').addEventListener('change', function() {
        const selectedIcon = this.value;
        const iconType = document.getElementById('iconType').value;
        const customIconGroup = document.getElementById('customIconGroup');
        const iconPreview = document.getElementById('iconPreview');

        if (selectedIcon === 'other') {
            customIconGroup.style.display = 'block';
            iconPreview.innerHTML = '';
        } else {
            customIconGroup.style.display = 'none';
            if (iconType === 'ki_duotone') {
                // Récupérer le nombre de paths
                const selectedOption = this.options[this.selectedIndex];
                const paths = parseInt(selectedOption.dataset.paths) || 2;
                let spans = '';
                for (let i = 1; i <= paths; i++) {
                    spans += `<span class="path${i}"></span>`;
                }
                iconPreview.innerHTML = `<i class="${selectedIcon} fs-2">${spans}</i>`;
            } else {
                iconPreview.innerHTML = `<i class="${selectedIcon} fs-2"></i>`;
            }
        }
    });

    // Gestion de la saisie de l'icône personnalisée
    document.getElementById('customIcon').addEventListener('input', function() {
        const customIcon = this.value;
        const iconType = document.getElementById('iconType').value;
        const iconPreview = document.getElementById('iconPreview');

        if (iconType === 'ki_duotone') {
            // Vous pouvez demander à l'utilisateur de spécifier le nombre de paths ou définir un nombre par défaut
            let spans = '';
            const defaultPaths = 2; // Par défaut
            for (let i = 1; i <= defaultPaths; i++) {
                spans += `<span class="path${i}"></span>`;
            }
            iconPreview.innerHTML = `<i class="${customIcon} fs-2">${spans}</i>`;
        } else {
            iconPreview.innerHTML = `<i class="${customIcon} fs-2"></i>`;
        }
    });
</script>

<?php include_once "partials/footer.php"; ?>
