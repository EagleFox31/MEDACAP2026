<?php
// managePermissions.php
session_start();
include_once "../language.php";
include_once "partials/header.php"; 

if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";
    require_once "groupFunctions.php"; 

    // Connexion à MongoDB
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $functionalitiesCollection = $academy->functionalities;
    $profilesCollection = $academy->profiles;

    // Générer un token CSRF si ce n'est pas déjà fait
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Vérifier que le module actuel est défini
    if (empty($currentModule)) {
        die("Module actuel non spécifié.");
    }

    // Récupérer les profils actifs depuis la base de données
    $profilesCursor = $profilesCollection->find(['active' => true]);
    $profiles = iterator_to_array($profilesCursor);

    // Filtrer les profils pour éliminer les doublons basés sur le champ 'name'
    $uniqueProfiles = [];
    foreach ($profiles as $profile) {
        if (!isset($profile['name']) || !isset($profile['icon'])) {
            continue; // Ignorer ce profil
        }
        $profileName = $profile['name'];
        if (!isset($uniqueProfiles[$profileName])) {
            $uniqueProfiles[$profileName] = $profile;
        }
    }

    // Remplacer $profiles par $uniqueProfiles
    $profiles = array_values($uniqueProfiles);

    // Récupérer les fonctionnalités actives et inactives pour le module actuel
    $activeFunctionalitiesCursor = $functionalitiesCollection->find([
        'active' => true,
        'modules' => $currentModule
    ]);
    $functionalitiess = iterator_to_array($activeFunctionalitiesCursor);

    $inactiveFunctionalitiesCursor = $functionalitiesCollection->find([
        'active' => false,
        'modules' => $currentModule
    ]);
    $inactiveFunctionalities = iterator_to_array($inactiveFunctionalitiesCursor);

    // Convertir les _id en chaînes de caractères
    foreach ($functionalitiess as &$functionalityy) {
        $functionalityy['_id'] = (string)$functionalityy['_id'];
    }
    unset($functionalityy);

    foreach ($inactiveFunctionalities as &$functionalityy) {
        $functionalityy['_id'] = (string)$functionalityy['_id'];
    }
    unset($functionalityy);

    // Calcul des compteurs de fonctionnalités avec un pipeline d'agrégation
    $functionalityCountsCursor = $profilesCollection->aggregate([
        [
            '$unwind' => '$functionalities' // Décompose le tableau des fonctionnalités
        ],
        [
            '$group' => [
                '_id' => '$functionalities', // Grouper par clé de fonctionnalité
                'count' => ['$sum' => 1] // Compter les occurrences
            ]
        ],
        [
            '$project' => [
                '_id' => 0,
                'id' => ['$toString' => '$_id'], // Convertir l'_id en chaîne
                'count' => 1
            ]
        ]
    ]);

    // Convertir les résultats d'agrégation en tableau associatif
    $functionalityCounts = [];
    foreach ($functionalityCountsCursor as $doc) {
        $functionalityCounts[$doc['id']] = $doc['count'];
    }

    // S'assurer que chaque profil a un tableau 'functionalities'
    foreach ($profiles as $index => $profile) {
        if (isset($profile['functionalities']) && !is_array($profile['functionalities'])) {
            $profiles[$index]['functionalities'] = (array)$profile['functionalities']; // Conversion explicite en tableau
        } elseif (!isset($profile['functionalities'])) {
            $profiles[$index]['functionalities'] = []; // Initialiser comme tableau vide
        }
    }

?>
<!-- Inclure Muuri via CDN avec le hash d'intégrité correct -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/muuri/0.9.5/muuri.min.js" integrity="sha512-<VOTRE_HASH_CORRECT>" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<style>
    /* Vos styles existants ici */
    .badge-functionality:active {
        box-shadow: inset 0 3px 0 rgba(0, 0, 0, 0.2); /* Inverse l'ombre au clic */
    }
    .badge-functionality {
        cursor: grab;
        user-select: none;
        margin: 0.25rem;
        position: relative;
        display: inline-block;
    }
    .badge-functionality.dragging {
        opacity: 0.7; /* Légèrement transparent */
        filter: blur(1px); /* Réduction du flou pour rendre le texte lisible */
        transform: scale(1.05); /* Légère mise en avant pour un effet visuel attractif */
        transition: filter 0.2s ease, transform 0.2s ease;
    }
    .dropzone {
        min-height: 100px;
        border: 2px dashed #ced4da;
        border-radius: 10px;
        padding: 1rem;
        background-color: #f8f9fa;
        transition: background-color 0.3s, border-color 0.3s;
    }
    .dropzone.dragover {
        background-color: #e2e6ea;
        border-color: #adb5bd;
    }
    .badge-remove {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        text-align: center;
        line-height: 18px;
        font-size: 12px;
        cursor: pointer;
    }
    .badge-functionality {
        cursor: grab;
        user-select: none;
        margin: 0.25rem;
        position: relative;
        background-color: #007bff; /* Couleur de fond */
        color: #fff; /* Couleur du texte */
        border-radius: 10px; /* Coins arrondis */
        padding: 0.5rem 1rem; /* Espacement interne */
        transition: background-color 0.2s ease;
        perspective: 1000px;
        opacity: 0;
        transform: translateY(10px);
        animation: fadeInUp 0.5s forwards;
    }

    /* Augmenter la taille du texte des badges */
    .badge-functionality {
        font-size: 1 rem; /* Taille de police de base */
        padding: 0.5rem 1rem; /* Ajuster le padding pour correspondre à la nouvelle taille */
    }

    /* Assurer que les badges sont flexibles et responsifs */
    .badge-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        margin: 1rem; /* Réduire la marge pour les petits écrans */
    }

    .badge-functionality {
        flex: 1 1 auto; /* Les badges prennent automatiquement la place disponible */
        margin: 0.5rem; /* Ajouter un espace autour des badges */
        max-width: 100%; /* Empêcher les badges de dépasser la largeur du conteneur */
        text-align: center; /* Centrer le texte à l'intérieur du badge */
        word-wrap: break-word; /* Permettre le retour à la ligne du texte si nécessaire */
    }

    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .badge-functionality:hover {
        transform: rotateX(15deg); /* Incline légèrement le badge sur l'axe X */
    }

    .grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .grid-item {
        min-width: 200px;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 1rem;
        background-color: #fff;
    }
    .badge-container {
        display: flex;
        flex-wrap: wrap;
        margin: 2.5rem;
    }
    .profile-card {
        border-radius: 10px;
        padding: 1rem;
        background-color: #f8f9fa;
    }
    .profile-header {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .profile-header i {
        margin-right: 0.5rem;
    }
    .modal-content {
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        background-color: #0d6efd;
        color: #fff;
        border-bottom: none;
    }

    .modal-footer {
        border-top: none;
        justify-content: center;
    }

    .badge-container .badge.bg-secondary {
        margin: 0.5rem;
    }

    .card {
        border: none; /* Supprimer la bordure par défaut */
        border-radius: 10px; /* Coins arrondis plus prononcés */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Ombre portée douce */
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        
    }

    .card:hover {
        transform: translateY(-5px); /* Légère élévation au survol */
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2); /* Accentuation de l'ombre au survol */
    }
</style>

<title>Gérer les permissions | CFAO Mobility Academy</title>

<!-- Modales pour les Notifications -->
<!-- Modale de Succès -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 id="successModalLabel" class="modal-title"><i class="fas fa-check-circle"></i> Succès</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div id="successModalBody" class="modal-body">
                <!-- Message de succès -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modale d'Erreur -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 id="errorModalLabel" class="modal-title"><i class="fas fa-times-circle"></i> Erreur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div id="errorModalBody" class="modal-body">
                <!-- Message d'erreur -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modale d'Information -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 id="infoModalLabel" class="modal-title"><i class="fas fa-exclamation-circle"></i> Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div id="infoModalBody" class="modal-body">
                <!-- Message d'information -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modale de Confirmation -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 id="confirmationModalLabel" class="modal-title"><i class="fas fa-question-circle"></i> Confirmation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div id="confirmationModalBody" class="modal-body">
                <!-- Message de confirmation -->
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelButton" class="btn btn-secondary">Annuler</button>
                <button type="button" id="confirmButton" class="btn btn-primary">Valider</button>
            </div>
        </div>
    </div>
</div>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-5">
            <h1 class="my-3 text-center">Gérer les permissions</h1>
            <p class="text-center">Faites glisser les fonctionnalités vers les profils pour attribuer les permissions.</p>

            <div class="row">
                <!-- Liste des fonctionnalités actives -->
                <div class="col-md-12">
                    <h4 class="text-center">Fonctionnalités Actives</h4>
                    <div id="active-functionalities" class="badge-container d-flex flex-wrap justify-content-center">
                        <?php
                        foreach ($functionalitiess as $functionalityy) {
                            // Utiliser '_id' si 'id' n'est pas défini
                            $id = (string)$functionalityy['_id'];
                            $name = isset($functionalityy['name']) ? $functionalityy['name'] : 'Sans nom';
                            
                            $name = htmlspecialchars($name);
                            $count = isset($functionalityCounts[$id]) ? $functionalityCounts[$id] : 0;

                            echo '<span class="badge bg-primary badge-functionality position-relative" draggable="true" data-id="' .  $id . '">'
                                . $name .
                                '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">'
                                . $count .
                                '</span>'
                                . '</span>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Fonctionnalités Inactives (Grisées) -->
                <div class="col-md-12 mt-4">
                    <h4 class="text-center">Fonctionnalités Inactives</h4>
                    <div class="badge-container d-flex flex-wrap justify-content-center">
                        <?php
                        foreach ($inactiveFunctionalities as $functionalityy) {
                            // Vérifier si 'id' et 'name' sont définis
                            if (!isset($functionalityy['name'])) {
                                error_log("Fonctionnalité inactives manquante: " . json_encode($functionalityy));
                                continue; // Ignorer cette fonctionnalité
                            }

                            $id = htmlspecialchars($functionalityy['_id']);
                            $name = htmlspecialchars($functionalityy['name']);

                            echo '<span class="badge bg-secondary disabled" data-id="' . $id . '">' . $name . '</span>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Liste des profils sous forme de cartes -->
                <div class="col-md-12 mt-5">
                    <div class="row">
                        <?php foreach ($profiles as $profile) {
                            // Vérifier si 'name' et 'icon' sont définis
                            if (!isset($profile['name']) || !isset($profile['icon'])) {
                                error_log("Profil manquant: " . json_encode($profile));
                                continue; // Ignorer ce profil
                            }

                            $profileId = str_replace(' ', '-', htmlspecialchars($profile['name']));
                        ?>
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-center">
                                            <i class="<?php echo htmlspecialchars($profile['icon']); ?>"></i> <?php echo htmlspecialchars($profile['name']); ?>
                                        </h5>
                                        <div class="dropzone" data-profile="<?php echo htmlspecialchars($profile['name']); ?>">
                                            <?php
                                            // Récupérer les fonctionnalités attribuées à ce profil pour le module actuel
                                            $assignedFuncIds = isset($profile['functionalities']) ? $profile['functionalities'] : [];
                                            foreach ($assignedFuncIds as $funcId) {
                                                // Récupérer la fonctionnalité seulement si elle appartient au module actuel et est active
                                                $functionality = $functionalitiesCollection->findOne([
                                                    '_id' => new MongoDB\BSON\ObjectId($funcId),
                                                    'active' => true,
                                                    'modules' => $currentModule
                                                ]);

                                                if ($functionality) {
                                                    $id = (string)$functionality['_id'];
                                                    $name = htmlspecialchars($functionality['name']);

                                                    echo '<span class="badge bg-success badge-functionality" draggable="true" data-id="' . $id . '">'
                                                        . $name . '</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

            </div>

            <div class="text-center mt-4">
                <button id="savePermissions" class="btn btn-primary">Enregistrer les permissions</button>
            </div>

        </div>
    </div>
</div>
<!-- Inclure Bootstrap JS et Font Awesome pour les icônes -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Script JavaScript pour gérer le Drag-and-Drop -->
<script>
    // Variables provenant de PHP
    const functionalities = <?php echo json_encode($functionalitiess); ?>;
    const profiles = <?php echo json_encode(array_values($profiles)); ?>;
    const assignedFuncKeys = <?php echo json_encode(array_column($profiles, 'functionalities')); ?>;
    const inactiveFunctionalities = <?php echo json_encode($inactiveFunctionalities); ?>;
    const functionalityCount = <?php echo json_encode($functionalityCounts); ?>;

    // Logs pour débogage
    profiles.forEach(function(profile, index) {
        console.log(`Profil ${index}:`, profile.name);
    });

    console.log("Fonctionnalités:", functionalities);
    console.log("Profils:", profiles);
    console.log("Permissions attribuées:", assignedFuncKeys);
    console.log("Fonctionnalités inactives:", inactiveFunctionalities);
    console.log("Compteurs de fonctionnalités:", functionalityCount);
    console.log("Clés des fonctionnalités actives:");
    functionalities.forEach(function(func) {
        console.log(func.id);
    });
    profiles.forEach(function(profile) {
        console.log("Profil:", profile.name);
        if (!profile.functionalities) {
            console.log("La clé functionalities est absente.");
        } else if (!Array.isArray(profile.functionalities)) {
            console.log("La clé functionalities n'est pas un tableau.");
        } else if (profile.functionalities.length === 0) {
            console.log("Aucune fonctionnalité n'est assignée à ce profil.");
        } else {
            console.log("Fonctionnalités assignées:", profile.functionalities);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        let draggedElement = null;
        const currentModule = "<?php echo htmlspecialchars($currentModule, ENT_QUOTES, 'UTF-8'); ?>";

        // Sélectionner toutes les dropzones
        const dropzones = document.querySelectorAll('.dropzone');

        // Stocker l'état initial des permissions
        let initialPermissions = {};
        const savePermissionsButton = document.getElementById('savePermissions');

        // Fonction pour capturer les permissions actuelles
        function captureInitialPermissions() {
            dropzones.forEach(function(zone) {
                const profile = zone.getAttribute('data-profile');
                const assignedFuncs = zone.querySelectorAll('.badge-functionality');
                initialPermissions[profile] = [];
                assignedFuncs.forEach(function(func) {
                    initialPermissions[profile].push(func.getAttribute('data-id'));
                });
            });
            console.log('Permissions initiales capturées:', initialPermissions);
        }

        // Appeler la fonction pour capturer les permissions initiales
        captureInitialPermissions();

        // Fonction pour obtenir les différences entre les permissions actuelles et initiales
        function getPermissionDifferences(currentPermissions, initialPermissions) {
            let additions = {};
            let deletions = {};

            for (let profile in currentPermissions) {
                // Ajouts
                additions[profile] = currentPermissions[profile].filter(func => !initialPermissions[profile].includes(func));
                // Suppressions
                deletions[profile] = initialPermissions[profile].filter(func => !currentPermissions[profile].includes(func));
            }

            // Nettoyer les objets pour ne contenir que les modifications
            Object.keys(additions).forEach(profile => {
                if (additions[profile].length === 0) {
                    delete additions[profile];
                }
            });

            Object.keys(deletions).forEach(profile => {
                if (deletions[profile].length === 0) {
                    delete deletions[profile];
                }
            });

            return { additions, deletions };
        }

        // Fonction pour mettre à jour les compteurs de fonctionnalités
        function updateFunctionalityCounters(action, id) {
            const activeFunc = document.querySelector(`#active-functionalities [data-id="${id}"]`);
            if (activeFunc) {
                const badgeCount = activeFunc.querySelector('.badge.rounded-pill.bg-danger');
                if (badgeCount) {
                    let count = parseInt(badgeCount.textContent);
                    if (action === 'increment') {
                        badgeCount.textContent = count + 1;
                    } else if (action === 'decrement' && count > 0) {
                        badgeCount.textContent = count - 1;
                    }
                }
            }
        }

        // Sélectionner tous les badges fonctionnels actifs
        const activeFunctionalitiesElements = document.querySelectorAll('#active-functionalities .badge-functionality');

        activeFunctionalitiesElements.forEach(function(func) {
            func.addEventListener('dragstart', function(e) {
                draggedElement = func;
                func.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'copy';
                // Définir les données transférées (clé de la fonctionnalité)
                e.dataTransfer.setData('text/plain', func.getAttribute('data-id'));
            });

            func.addEventListener('dragend', function(e) {
                func.classList.remove('dragging');
                draggedElement = null;
            });
        });

        dropzones.forEach(function(zone) {
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                zone.classList.add('dragover');
            });

            zone.addEventListener('dragleave', function(e) {
                zone.classList.remove('dragover');
            });

            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Empêche l'événement de se propager au document
                zone.classList.remove('dragover');
                const id = e.dataTransfer.getData('text/plain');
                const originalFunc = document.querySelector(`#active-functionalities [data-id="${id}"]`);
                if (originalFunc && !originalFunc.classList.contains('disabled')) {
                    // Vérifier si la fonctionnalité est déjà assignée à ce profil
                    const alreadyAssigned = zone.querySelector(`[data-id="${id}"]`);
                    if (alreadyAssigned) {
                        // Utiliser la modale d'information
                        const infoModal = new bootstrap.Modal(document.getElementById('infoModal'));
                        document.getElementById('infoModalBody').textContent = 'Cette fonctionnalité est déjà assignée à ce profil.';
                        infoModal.show();
                        return;
                    }

                    // Vérifier que la fonctionnalité appartient au module actuel
                    const func = functionalities.find(f => f._id === id);
                    if (!func) {
                        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        document.getElementById('errorModalBody').textContent = 'Fonctionnalité invalide ou n\'appartient pas au module actuel.';
                        errorModal.show();
                        return;
                    }

                    // Cloner l'élément pour laisser l'original dans la liste active
                    const clone = originalFunc.cloneNode(true);
                    clone.classList.remove('bg-primary');
                    clone.classList.add('bg-success');
                    clone.setAttribute('draggable', 'true'); // Permettre au clone d'être déplacé pour le retirer du profil

                    // Ajouter des écouteurs d'événements pour dragstart et dragend au clone
                    clone.addEventListener('dragstart', function(e) {
                        draggedElement = clone;
                        clone.classList.add('dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', clone.getAttribute('data-id'));
                    });

                    clone.addEventListener('dragend', function(e) {
                        clone.classList.remove('dragging');
                        draggedElement = null;
                    });

                    // Ajouter un bouton de suppression au clone
                    const removeBtn = document.createElement('span');
                    removeBtn.classList.add('badge-remove');
                    removeBtn.innerHTML = '&times;';
                    removeBtn.title = 'Supprimer';
                    clone.appendChild(removeBtn);

                    // Ajouter l'événement de suppression
                    removeBtn.addEventListener('click', function() {
                        const funcId = clone.getAttribute('data-id');
                        clone.remove();
                        // Décrémenter le compteur dans la liste active
                        updateFunctionalityCounters('decrement', funcId);

                        // Utiliser la modale de succès pour la suppression
                        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        document.getElementById('successModalLabel').textContent = 'Suppression réussie';
                        document.getElementById('successModalBody').textContent = 'La fonctionnalité a été retirée du profil avec succès.';
                        successModal.show();

                    });

                    // Ajouter le clone à la dropzone
                    zone.appendChild(clone);

                    // Incrémenter le compteur dans la liste active
                    updateFunctionalityCounters('increment', id);
                }
            });
        });

        // Ajouter des écouteurs aux fonctionnalités déjà assignées
        const assignedFunctionalities = document.querySelectorAll('.dropzone .badge-functionality');

        assignedFunctionalities.forEach(function(func) {
            func.addEventListener('dragstart', function(e) {
                draggedElement = func;
                func.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', func.getAttribute('data-id'));
            });

            func.addEventListener('dragend', function(e) {
                func.classList.remove('dragging');
                draggedElement = null;
            });
        });

        // Permettre le drop sur le document entier pour retirer les fonctionnalités des profils
        document.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        document.addEventListener('drop', function(e) {
            e.preventDefault();
            if (draggedElement) {
                // Vérifier si l'élément glissé provient d'un profil
                const parentZone = draggedElement.parentElement;
                if (parentZone && parentZone.classList.contains('dropzone')) {
                    // Retirer l'élément du profil
                    const funcId = draggedElement.getAttribute('data-id');
                    draggedElement.remove();

                    // Décrémenter le compteur dans la liste active
                    updateFunctionalityCounters('decrement', funcId);

                    // Utiliser la modale de succès pour la suppression
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successModalLabel').textContent = 'Suppression réussie';
                    document.getElementById('successModalBody').textContent = 'La fonctionnalité a été retirée du profil avec succès.';
                    successModal.show();
                }
            }
        });

        // Sauvegarder les permissions
        savePermissionsButton.addEventListener('click', function() {
            const currentPermissions = {};

            // Parcourir chaque dropzone pour collecter les fonctionnalités assignées
            dropzones.forEach(function(zone) {
                const profile = zone.getAttribute('data-profile');
                const assignedFuncs = zone.querySelectorAll('.badge-functionality');
                currentPermissions[profile] = [];
                assignedFuncs.forEach(function(func) {
                    currentPermissions[profile].push(func.getAttribute('data-id'));
                });
            });

            console.log('Permissions à envoyer:', currentPermissions);

            const { additions, deletions } = getPermissionDifferences(currentPermissions, initialPermissions);

            console.log('Ajouts à envoyer:', additions);
            console.log('Suppressions à envoyer:', deletions);

            // Vérifier s'il y a des modifications à envoyer
            if (Object.keys(additions).length === 0 && Object.keys(deletions).length === 0) {
                // Aucune modification détectée
                const infoModal = new bootstrap.Modal(document.getElementById('infoModal'));
                document.getElementById('infoModalBody').textContent = 'Aucune modification détectée.';
                infoModal.show();

                return;
            }

            // Préparer la liste des assignations pour la confirmation
            let assignmentsToConfirm = [];

            // Ajouter les ajouts
            for (let profile in additions) {
                additions[profile].forEach(function(funcId) {
                    let func = functionalities.find(f => f._id === funcId);
                    if (func) {
                        assignmentsToConfirm.push({
                            functionality: func.name,
                            profile: profile,
                            action: 'ajouter'
                        });
                    } else {
                        assignmentsToConfirm.push({
                            functionality: `Inconnue (ID: ${funcId})`,
                            profile: profile,
                            action: 'ajouter'
                        });
                    }
                });
            }

            // Ajouter les suppressions
            for (let profile in deletions) {
                deletions[profile].forEach(function(funcId) {
                    let func = functionalities.find(f => f._id === funcId);
                    if (func) {
                        assignmentsToConfirm.push({
                            functionality: func.name,
                            profile: profile,
                            action: 'supprimer'
                        });
                    } else {
                        assignmentsToConfirm.push({
                            functionality: `Inconnue (ID: ${funcId})`,
                            profile: profile,
                            action: 'supprimer'
                        });
                    }
                });
            }

            // Afficher la modale de confirmation avec les modifications
            let message = '<p>Voulez-vous vraiment effectuer les modifications suivantes :</p><ul>';
            assignmentsToConfirm.forEach(item => {
                if (item.action === 'ajouter') {
                    message += `<li>Assigner la fonctionnalité "<strong class="text-success">${item.functionality}</strong>" au profil "<strong class="text-success">${item.profile}</strong>"</li>`;
                } else if (item.action === 'supprimer') {
                    message += `<li>Retirer la fonctionnalité "<strong class="text-danger">${item.functionality}</strong>" du profil "<strong class="text-danger">${item.profile}</strong>"</li>`;
                }
            });
            message += '</ul>';

            // Afficher la modale de confirmation
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            const confirmationModalBody = document.getElementById('confirmationModalBody');
            confirmationModalBody.innerHTML = message;

            // Gérer les boutons de confirmation
            document.getElementById('confirmButton').onclick = function() {
                confirmationModal.hide();

                // Envoyer les modifications au serveur via AJAX
                fetch('savePermissions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ additions, deletions, currentModule, csrf_token: '<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>' })
                })
                .then(response => {
                    console.log('Réponse HTTP:', response);
                    return response.text().then(text => {
                        console.log('Réponse Texte:', text);
                        try {
                            const data = JSON.parse(text);
                            console.log('Données JSON:', data);
                            return { status: response.status, data: data };
                        } catch (e) {
                            // JSON invalide
                            console.error('Erreur de parsing JSON:', e);
                            throw { status: response.status, data: { message: 'Réponse JSON invalide.' } };
                        }
                    });
                })
                .then(result => {
                    if (result.status === 200 && result.data.status === 'success') {
                        // Déterminer le message de succès en fonction des modifications
                        let successMessage = '';
                        if (Object.keys(additions).length > 0 && Object.keys(deletions).length > 0) {
                            successMessage = 'Les permissions ont été mises à jour avec succès.';
                        } else if (Object.keys(additions).length > 0) {
                            successMessage = 'Les nouvelles fonctionnalités ont été ajoutées avec succès.';
                        } else if (Object.keys(deletions).length > 0) {
                            successMessage = 'Les fonctionnalités ont été supprimées avec succès.';
                        } else {
                            successMessage = 'Les permissions ont été mises à jour.';
                        }
                        // Utiliser la modale de succès
                        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        document.getElementById('successModalLabel').textContent = 'Succès';
                        document.getElementById('successModalBody').textContent = result.data.message;
                        successModal.show();
                        // Recharger la page après la fermeture de la modale
                        document.getElementById('successModal').addEventListener('hidden.bs.modal', () => location.reload());
                    } else {
                        // Autres erreurs
                        console.error('Erreur inattendue:', result);
                        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        document.getElementById('errorModalBody').textContent = 'Une erreur est survenue: ' + (result.data.message || 'Erreur inconnue.');
                        errorModal.show();
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    // Afficher une modale d'erreur
                    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    if (error.data && error.data.message) {
                        document.getElementById('errorModalBody').textContent = 'Erreur lors de la mise à jour des permissions : ' + error.data.message;
                    } else {
                        document.getElementById('errorModalBody').textContent = 'Une erreur est survenue lors de la mise à jour des permissions.';
                    }
                    errorModal.show();
                });

            };

            document.getElementById('cancelButton').onclick = function() {
                // L'utilisateur a annulé
                confirmationModal.hide();
            };

            confirmationModal.show();
        });
    });
</script>

<?php include_once "partials/footer.php"; ?>
<?php } ?>
