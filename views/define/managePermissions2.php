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
    $profiles = [
        ["name" => "Super Admin", "icon" => "fa-user-shield"],
        ["name" => "Directeur Groupe", "icon" => "bi bi-person-badge-fill"],
        ["name" => "Admin", "icon" => "fa-user-cog"],
        ["name" => "RH", "icon" => "fa-user-tie"],
        ["name" => "Directeur Filiale", "icon" => "bi bi-building"],
        ["name" => "Manager", "icon" => "bi bi-person-lines-fill"],
        ["name" => "Manager-Technicien", "icon" => "bi bi-person-check-fill"],
        ["name" => "Technicien", "icon" => "bi bi-tools"]
    ];

    // Récupérer les fonctionnalités actives
    $functionalitiesCursor = $functionalitiesCollection->find(['active' => true]);
    $functionalities = iterator_to_array($functionalitiesCursor);

    // Récupérer les fonctionnalités inactives
    $inactiveFunctionalitiesCursor = $functionalitiesCollection->find(['active' => false]);
    $inactiveFunctionalities = iterator_to_array($inactiveFunctionalitiesCursor);

    

?>
<?php include_once "partials/header.php"; ?>

<!-- Inclure Muuri via CDN avec le hash d'intégrité correct -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/muuri/0.9.5/muuri.min.js" integrity="sha512-<VOTRE_HASH_CORRECT>" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<style>
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
        border-radius: 4px;
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
    }
    .badge-functionality.dragging {
    opacity: 0.7; /* Légèrement transparent */
    filter: blur(1px); /* Réduction du flou pour rendre le texte lisible */
    transform: scale(1.05); /* Légère mise en avant pour un effet visuel attractif */
    transition: filter 0.2s ease, transform 0.2s ease;
}

    .grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .grid-item {
        min-width: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 1rem;
        background-color: #fff;
    }
    .badge-container {
        display: flex;
        flex-wrap: wrap;
        margin: 2.5rem;
    }
    .profile-card {
        border: 1px solid #ddd;
        border-radius: 4px;
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

</style>

<title>Gérer les permissions | CFAO Mobility Academy</title>

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
                        foreach ($functionalities as $functionality) {
                            // Calculer le nombre de profils attribués à cette fonctionnalité
                            $count = isset($functionality['profiles']) ? count($functionality['profiles']) : 0;
                            echo '<span class="badge bg-primary badge-functionality" draggable="true" data-key="' . htmlspecialchars($functionality['key']) . '">'
                                . htmlspecialchars($functionality['name']) .
                                '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">'
                                . $count .
                                '<span class="visually-hidden">profils assignés</span>'
                                . '</span>'
                                . '</span>';
                        }?>
                    </div>
                </div>

                <!-- Fonctionnalités Inactives (Grisées) -->
                <div class="col-md-12 mt-4">
                        <h4 class="text-center">Fonctionnalités Inactives</h4>
                        <div class="badge-container d-flex flex-wrap justify-content-center">
                            <?php
                            foreach ($inactiveFunctionalities as $functionality) {
                                echo '<span class="badge bg-secondary disabled" data-key="' . htmlspecialchars($functionality['key']) . '">' . htmlspecialchars($functionality['name']) . '</span>';
                            }
                            ?>
                    </div>
                </div>

                <!-- Liste des profils sous forme de cartes avec Muuri -->
                <div class="col-md-12 mt-5">
                    <div class="row">
                        <?php foreach ($profiles as $profile) { 
                            // Remplacer les espaces par des tirets pour les IDs
                            $profileId = str_replace(' ', '-', htmlspecialchars($profile['name']));
                        ?>
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-center">
                                            <i class="fas <?php echo htmlspecialchars($profile['icon']); ?>"></i> <?php echo htmlspecialchars($profile['name']); ?>
                                        </h5>
                                        <div class="dropzone" data-profile="<?php echo htmlspecialchars($profile['name']); ?>">
                                            <?php
                                                // Récupérer les fonctionnalités attribuées à ce profil
                                                $assignedFunctionalitiesCursor = $functionalitiesCollection->find([
                                                    'profiles' => $profile['name'],
                                                    'active' => true
                                                ]);
                                                $assignedFunctionalities = iterator_to_array($assignedFunctionalitiesCursor);
                                                foreach ($assignedFunctionalities as $functionality) {
                                                    echo '<span class="badge bg-success badge-functionality" draggable="true" data-key="' . htmlspecialchars($functionality['key']) . '">'
                                                        . htmlspecialchars($functionality['name']) . '</span>';
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
document.addEventListener('DOMContentLoaded', function() {
    let draggedElement = null;

    // Sélectionner tous les badges fonctionnels actifs
    const functionalities = document.querySelectorAll('#active-functionalities .badge-functionality');

    functionalities.forEach(function(func) {
        func.addEventListener('dragstart', function(e) {
            draggedElement = func;
            func.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'copy';
            // Définir les données transférées (clé de la fonctionnalité)
            e.dataTransfer.setData('text/plain', func.getAttribute('data-key'));
        });

        func.addEventListener('dragend', function(e) {
            func.classList.remove('dragging');
            draggedElement = null;
        });
    });

    // Sélectionner toutes les dropzones
    const dropzones = document.querySelectorAll('.dropzone');

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
            const key = e.dataTransfer.getData('text/plain');
            const originalFunc = document.querySelector(`#active-functionalities [data-key="${key}"]`);
            if (originalFunc && !originalFunc.classList.contains('disabled')) {
                // Vérifier si la fonctionnalité est déjà assignée à ce profil
                const alreadyAssigned = zone.querySelector(`[data-key="${key}"]`);
                if (alreadyAssigned) {
                    // Utiliser la modale d'information
                    const infoModal = new bootstrap.Modal(document.getElementById('infoModal'));
                    document.getElementById('infoModalBody').textContent = 'Cette fonctionnalité est déjà assignée à ce profil.';
                    infoModal.show();
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
                    e.dataTransfer.setData('text/plain', clone.getAttribute('data-key'));
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
                    clone.remove();
                    // Décrémenter le compteur dans la liste active
                    const key = clone.getAttribute('data-key');
                    const activeFunc = document.querySelector(`#active-functionalities [data-key="${key}"]`);
                    if (activeFunc) {
                        const badgeCount = activeFunc.querySelector('.badge.rounded-pill.bg-danger');
                        if (badgeCount) {
                            let count = parseInt(badgeCount.textContent);
                            if (count > 0) {
                                badgeCount.textContent = count - 1;
                            }
                        }
                    }

                    // Utiliser la modale de succès pour la suppression
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successModalLabel').textContent = 'Suppression réussie';
                    document.getElementById('successModalBody').textContent = 'La fonctionnalité a été retirée du profil avec succès.';
                    successModal.show();
                });

                // Ajouter le clone à la dropzone
                zone.appendChild(clone);

                // Incrémenter le compteur dans la liste active
                const activeFunc = document.querySelector(`#active-functionalities [data-key="${key}"]`);
                if (activeFunc) {
                    const badgeCount = activeFunc.querySelector('.badge.rounded-pill.bg-danger');
                    if (badgeCount) {
                        let count = parseInt(badgeCount.textContent);
                        badgeCount.textContent = count + 1;
                    }
                }
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
            e.dataTransfer.setData('text/plain', func.getAttribute('data-key'));
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
                draggedElement.remove();

                // Décrémenter le compteur dans la liste active
                const key = draggedElement.getAttribute('data-key');
                const activeFunc = document.querySelector(`#active-functionalities [data-key="${key}"]`);
                if (activeFunc) {
                    const badgeCount = activeFunc.querySelector('.badge.rounded-pill.bg-danger');
                    if (badgeCount) {
                        let count = parseInt(badgeCount.textContent);
                        if (count > 0) {
                            badgeCount.textContent = count - 1;
                        }
                    }
                }

                // Utiliser la modale de succès pour la suppression
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successModalLabel').textContent = 'Suppression réussie';
                document.getElementById('successModalBody').textContent = 'La fonctionnalité a été retirée du profil avec succès.';
                successModal.show();
            }
        }
    });

    // Sauvegarder les permissions
    document.getElementById('savePermissions').addEventListener('click', function() {
        const permissions = {};

        // Parcourir chaque dropzone pour collecter les fonctionnalités assignées
        dropzones.forEach(function(zone) {
            const profile = zone.getAttribute('data-profile');
            const assignedFuncs = zone.querySelectorAll('.badge-functionality');
            permissions[profile] = [];
            assignedFuncs.forEach(function(func) {
                permissions[profile].push(func.getAttribute('data-key'));
            });
        });

        console.log('Permissions à envoyer:', permissions);

        // Envoyer les données au serveur via AJAX
        fetch('savePermissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(permissions)
        })
        .then(response => {
            console.log('Réponse HTTP:', response);
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Données JSON reçues:', data);
            if(data.status === 'success') {
                // Utiliser la modale de succès
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successModalLabel').textContent = 'Succès';
                document.getElementById('successModalBody').textContent = 'Les permissions ont été mises à jour avec succès.';
                successModal.show();
                // Recharger la page après la fermeture de la modale
                document.getElementById('successModal').addEventListener('hidden.bs.modal', () => location.reload());
            } else {
                // Utiliser la modale d'erreur
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                document.getElementById('errorModalBody').textContent = 'Erreur lors de la mise à jour des permissions: ' + data.message;
                errorModal.show();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            // Utiliser la modale d'erreur
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorModalBody').textContent = 'Une erreur est survenue lors de la mise à jour des permissions.';
            errorModal.show();
        });
    });
});
</script>





<?php include_once "partials/footer.php"; ?>
<?php } ?>