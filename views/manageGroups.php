<?php
// manageGroups.php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
    header("Location: ../../");
    exit();
}

require_once "groupFunctions.php";
require_once "moduleHelper.php";

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtenir le module actuel
$client = getMongoClient();
$academy = $client->academy;
$functionalitiesCollection = $academy->functionalities;
$currentModule = ModuleHelper::getCurrentModule($functionalitiesCollection);

// Récupérer les groupes et fonctionnalités en fonction du module actuel
$orderedGroups = getOrderedGroups($currentModule);
$activeFunctionalitiesWithoutGroup = getFunctionalitiesWithoutGroup($currentModule, true);
$inactiveFunctionalitiesWithoutGroup = getFunctionalitiesWithoutGroup($currentModule, false);

$groupedFunctionalities = [];
foreach ($orderedGroups as $g) {
    $groupedFunctionalities[$g['name']] = getFunctionalitiesByGroup($g['name'], $currentModule);
}

// Récupérer les profils actifs depuis la base de données
$client = getMongoClient();
$academy = $client->academy;
$profilesCollection = $academy->profiles;
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

include_once "partials/header.php"; 
?>

<!-- Bootstrap 4.6 CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" integrity="sha512-P5MgMn1jBN01asBgU0z60Qk4QxiXo86+wlFahKrsQf37c9cro517WzVSPPV1tDKzhku2iJ2FVgL67wG03SGnNA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

<title><?php echo htmlspecialchars($gest_group); ?></title>

<style>
.container-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f9fafb;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.dashboard-header {
    text-align: center;
    margin-bottom: 30px;
}
.dashboard-header h1 { font-size: 28px; color: #333; }
.dashboard-header p { font-size:16px; color:#666; }

.card {
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 5px rgba(0,0,0,0.1);
    transition:transform 0.2s,box-shadow 0.2s;
}
.card:hover {
    transform:translateY(-3px);
    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}
.list-group-item { cursor:grab; }
.list-group-item:active { cursor:grabbing; }
.group-position { font-weight:bold; color:#444; margin-right:10px; }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl">
        <div class="container mt-3">
            <div class="dashboard-header">
                <h1>Gestion des Groupes et Fonctionnalités</h1>
                <p>Gérez les groupes et les fonctionnalités grâce à une interface intuitive.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center my-3">
                <h1>Gestion des Groupes et Fonctionnalités</h1>
                <!-- Bouton pour ouvrir la modal de création -->
                <button class="btn btn-primary" data-toggle="modal" data-target="#createGroupModal">Créer un groupe</button>
            </div>

            <!-- Ordre des Groupes -->
            <div class="card" style="border-radius:15px; padding:20px;">
                <h3>Ordre des Groupes</h3>
                <ul id="groupsList" class="list-group">
                    <?php 
                    $pos = 1;
                    foreach ($orderedGroups as $group) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-group-name="<?php echo htmlspecialchars($group['name']); ?>">
                            <span><span class="group-position"><?php echo $pos++; ?></span><?php echo htmlspecialchars($group['name']); ?></span>
                            <div>
                                <!-- Bouton pour renommer le groupe -->
                                <button class="btn btn-sm btn-info edit-group-btn" data-old-name="<?php echo htmlspecialchars($group['name']); ?>" data-toggle="modal" data-target="#renameGroupModal">Modifier</button>
                                <button class="btn btn-sm btn-danger delete-group-btn" data-name="<?php echo htmlspecialchars($group['name']); ?>">Supprimer</button>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <button class="btn btn-success mt-3" id="saveGroupsOrderBtn">Enregistrer l'ordre des groupes</button>
            </div>

            <br>

            <!-- Fonctionnalités par Groupe -->
            <?php foreach ($orderedGroups as $group) { ?>
            <div class="card mt-3" style="border-radius:15px; padding:20px;">
                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                <p>Glissez-déposez pour modifier l'ordre.</p>
                <ul class="list-group functionalities-list" data-group-name="<?php echo htmlspecialchars($group['name']); ?>">
                    <?php 
                    $fpos = 1; 
                    foreach ($groupedFunctionalities[$group['name']] as $func) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-functionality-id="<?php echo (string)$func['_id']; ?>">
                            <span><span class="group-position"><?php echo $fpos++; ?></span><?php echo htmlspecialchars($func['name']); ?></span>
                            <div>
                                <button class="btn btn-sm btn-secondary remove-from-group-btn" data-id="<?php echo (string)$func['_id']; ?>">Retirer du groupe</button>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <button class="btn btn-success mt-3 saveFunctionalitiesOrderBtn">Enregistrer l'ordre des fonctionnalités</button>
            </div>
            <?php } ?>

            <!-- Fonctionnalités Sans Groupe (Actives) -->
            <div class="card mt-3" style="border-radius:15px; padding:20px;">
                <h3>Fonctionnalités Sans Groupe (Actives)</h3>
                <ul class="list-group" id="activeFunctionalitiesWithoutGroupList">
                    <?php foreach ($activeFunctionalitiesWithoutGroup as $func) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-functionality-id="<?php echo (string)$func['_id']; ?>">
                            <span><?php echo htmlspecialchars($func['name']); ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <button class="btn btn-primary mt-3" data-toggle="modal" data-target="#assignFunctionalityModal">Assigner au groupe</button>
            </div>

            <!-- Fonctionnalités Sans Groupe (Inactives) -->
            <div class="card mt-3" style="border-radius:15px; padding:20px;">
                <h3>Fonctionnalités Sans Groupe (Inactives)</h3>
                <ul class="list-group" id="inactiveFunctionalitiesWithoutGroupList">
                    <?php foreach ($inactiveFunctionalitiesWithoutGroup as $func) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-functionality-id="<?php echo (string)$func['_id']; ?>">
                            <span><?php echo htmlspecialchars($func['name']); ?> (Inactif)</span>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <!-- Bouton d'Enregistrement des Permissions (si nécessaire) -->
            <!-- Vous pouvez ajouter un bouton similaire si vous gérez les permissions globales -->

            <!-- Modales -->
            <!-- Modal Créer un Groupe -->
            <div class="modal fade" id="createGroupModal" tabindex="-1" role="dialog" aria-labelledby="createGroupModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document" style="max-height:600px; overflow:auto;">
                <div class="modal-content">
                    <form id="createGroupForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="create_group">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createGroupModalLabel">Créer un Nouveau Groupe</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="newGroupName">Nom du Groupe</label>
                                <input type="text" class="form-control" id="newGroupName" name="newGroupName" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Créer</button>
                        </div>
                    </form>
                </div>
              </div>
            </div>

            <!-- Modal Renommer un Groupe -->
            <div class="modal fade" id="renameGroupModal" tabindex="-1" role="dialog" aria-labelledby="renameGroupModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document" style="max-height:600px; overflow:auto;">
                <div class="modal-content">
                    <form id="renameGroupForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="rename_group">
                        <input type="hidden" name="oldName" id="oldGroupNameForRename">
                        <div class="modal-header">
                            <h5 class="modal-title" id="renameGroupModalLabel">Renommer le Groupe</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="renameGroupName">Nouveau Nom</label>
                                <input type="text" class="form-control" id="renameGroupName" name="renameGroupName" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
              </div>
            </div>

            <!-- Modal Assigner des Fonctionnalités -->
            <div class="modal fade" id="assignFunctionalityModal" tabindex="-1" role="dialog" aria-labelledby="assignFunctionalityModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document" style="max-height:600px; overflow:auto;">
                <div class="modal-content">
                    <form id="assignFunctionalityForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="assign_functionalities">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assignFunctionalityModalLabel">Assigner des Fonctionnalités</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="selectGroupName">Groupe</label>
                                <select class="form-control" id="selectGroupName" name="groupName" required>
                                    <option value="">-- Sélectionner un Groupe --</option>
                                    <?php foreach ($orderedGroups as $group) { ?>
                                        <option value="<?php echo htmlspecialchars($group['name']); ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="selectFunctionalities">Fonctionnalités Sans Groupe (Actives)</label>
                                <select class="form-control" id="selectFunctionalities" name="selectFunctionalities[]" multiple style="width:100%;">
                                    <?php foreach ($activeFunctionalitiesWithoutGroup as $func) { ?>
                                        <option value="<?php echo (string)$func['_id']; ?>"><?php echo htmlspecialchars($func['name']); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="form-text text-muted">Sélectionnez les fonctionnalités à attribuer.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Assigner</button>
                        </div>
                    </form>
                </div>
              </div>
            </div>

            <!-- Modal Modifier une Fonctionnalité (si nécessaire) -->
            <!-- Vous pouvez ajouter cette modal si vous avez besoin de modifier les fonctionnalités -->
            <div class="modal fade" id="editFunctionalityModal" tabindex="-1" role="dialog" aria-labelledby="editFunctionalityModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document" style="max-height:600px; overflow:auto;">
                <div class="modal-content">
                    <form id="editFunctionalityForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="edit_functionality">
                        <input type="hidden" name="functionality_id" id="editFunctionalityId">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editFunctionalityModalLabel">Modifier la Fonctionnalité</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="functionalityName">Nom de la Fonctionnalité</label>
                                <input type="text" class="form-control" id="functionalityName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="functionalityDescription">Description</label>
                                <textarea class="form-control" id="functionalityDescription" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
              </div>
            </div>

            <!-- Modal d'Information -->
            <div class="modal fade" id="infoModal" tabindex="-1" role="dialog" aria-labelledby="infoModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="infoModalLabel">Information</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="infoModalBody">
                        <!-- Message d'information -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
              </div>
            </div>

            <!-- Modal de Succès -->
            <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="successModalLabel">Succès</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="successModalBody">
                        <!-- Message de succès -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
              </div>
            </div>

            <!-- Modal d'Erreur -->
            <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="errorModalLabel">Erreur</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="errorModalBody">
                        <!-- Message d'erreur -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
              </div>
            </div>

        </div>
    </div>
</div>

<!-- jQuery, Bootstrap JS, SortableJS, Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js" integrity="sha512-Eezs+g9Lq4TCCq0wae01s9PuNWzHYoCMkE97e2qdkYthpI0pzC3UGB03lgEHn2XM85hDOUF6qgqqszs+iXU4UA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

<script>
var csrfToken = '<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>';
// Determine the correct AJAX URL based on the current path
var currentPath = window.location.pathname;
var ajaxUrl = 'manageGroups.ajax.php';

// Check if we're in a subdirectory
if (currentPath.includes('/explore/')) {
    ajaxUrl = '../manageGroups.ajax.php';
} else if (currentPath.includes('/measure/')) {
    ajaxUrl = '../manageGroups.ajax.php';
} else if (currentPath.includes('/define/')) {
    ajaxUrl = '../manageGroups.ajax.php';
}

console.log('Current path:', currentPath, 'Using AJAX URL:', ajaxUrl);

$(document).ready(function(){
    console.log('Document ready - jQuery');
    console.log('Using AJAX URL:', ajaxUrl);
    $('#selectFunctionalities').select2();
    $('#selectGroupName').select2();
});

function updateGroupPositions() {
    console.log('Updating group positions...');
    $('#groupsList li').each(function(i){
        $(this).find('.group-position').text(i+1);
    });
}

function updateFunctionalitiesPositions(list) {
    console.log('Updating functionalities positions for list:', list);
    list.find('li').each(function(i){
        $(this).find('.group-position').text(i+1);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event triggered');

    var groupsList = document.getElementById('groupsList');
    if (groupsList) {
        console.log('Initializing Sortable on groupsList');
        new Sortable(groupsList, {
            animation: 150,
            onEnd: function(evt){
                console.log('Group dragged from', evt.oldIndex, 'to', evt.newIndex);
                updateGroupPositions();
            }
        });
    }

    $('.functionalities-list').each(function(){
        console.log('Initializing Sortable on a functionalities-list');
        var currentList = $(this);
        new Sortable(this, {
            animation:150,
            onEnd: function(evt){
                console.log('Functionality dragged from', evt.oldIndex, 'to', evt.newIndex, 'in group', currentList.data('group-name'));
                updateFunctionalitiesPositions(currentList);
            }
        });
    });

    $('#saveGroupsOrderBtn').on('click', function() {
        console.log('#saveGroupsOrderBtn clicked');
        var groupNames = [];
        $('#groupsList li').each(function(){
            groupNames.push($(this).data('group-name'));
        });
        console.log('Reordering groups with new order:', groupNames);

        $.post(ajaxUrl, {
            action: 'reorder_groups',
            groupNames: groupNames,
            csrf_token: csrfToken
        }, function(response){
            console.log('Response from reorder_groups:', response);
            if (response.success) {
                alert('Ordre des groupes enregistré');
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json');
    });

    $('.saveFunctionalitiesOrderBtn').on('click', function(){
        console.log('.saveFunctionalitiesOrderBtn clicked');
        var parentCard = $(this).closest('.card');
        var functionalitiesList = parentCard.find('.functionalities-list');
        var groupName = functionalitiesList.data('group-name');
        var funcIds = [];
        functionalitiesList.find('li').each(function(){
            funcIds.push($(this).data('functionality-id'));
        });

        console.log('Saving functionalities order for group:', groupName, 'funcIds:', funcIds);

        $.post(ajaxUrl, {
            action:'reorder_functionalities',
            groupName: groupName,
            funcIds: funcIds,
            csrf_token: csrfToken
        }, function(response){
            console.log('Response from reorder_functionalities:', response);
            if (response.success){
                alert('Ordre des fonctionnalités enregistré');
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json');
    });

    $('#createGroupForm').on('submit', function(e){
        e.preventDefault();
        console.log('createGroupForm submitted');
        var name = $('#newGroupName').val();
        console.log('Creating group with name:', name);

        // Create a function to try multiple AJAX URLs if one fails
        function tryCreateGroup(urls, index) {
            if (index >= urls.length) {
                alert('Failed to create group after trying all URLs. Check console for details.');
                return;
            }
            
            var currentUrl = urls[index];
            console.log('Trying AJAX URL:', currentUrl);
            
            $.ajax({
                url: currentUrl,
                type: 'POST',
                data: {
                    action: 'create_group',
                    newGroupName: name,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Response from create_group:', response);
                    if (response.success) {
                        // On a la création réussie avec 'group' dans response
                        // response.group devrait contenir le nom et le group_order
                        var groupName = response.group.name;
                        
                        // Mettre à jour la liste des groupes dans la modal d'assignation
                        // Avant d'ouvrir la modal, on ajoute l'option du groupe créé s'il n'existe pas déjà
                        if ($('#selectGroupName option[value="'+groupName+'"]').length === 0) {
                            $('#selectGroupName').append('<option value="'+groupName+'">'+groupName+'</option>');
                        }

                        // Sélectionner automatiquement le groupe nouvellement créé
                        $('#selectGroupName').val(groupName).trigger('change');

                        // Afficher la modal d'assignation
                        $('#assignFunctionalityModal').modal('show');
                    } else {
                        alert(response.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX failed for URL ' + currentUrl + ':', textStatus, errorThrown);
                    console.log('Raw response:', jqXHR.responseText);
                    
                    // Try the next URL
                    tryCreateGroup(urls, index + 1);
                }
            });
        }
        
        // Get the form data directly from the form
        var formData = $(this).serialize();
        console.log("Form data:", formData);
        
        // Try different AJAX URLs in order of likelihood
        var urlsToTry = [
            ajaxUrl,                                  // Current dynamic URL
            '../manageGroups.ajax.php',               // One directory up
            '../../views/manageGroups.ajax.php',      // Two directories up
            '/MEDACAP/views/manageGroups.ajax.php'    // Absolute path
        ];
        
        // Use the serialized form data
        function tryCreateGroup(urls, index) {
            if (index >= urls.length) {
                alert('Failed to create group after trying all URLs. Check console for details.');
                return;
            }
            
            var currentUrl = urls[index];
            console.log('Trying AJAX URL:', currentUrl);
            
            $.ajax({
                url: currentUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Response from create_group:', response);
                    if (response.success) {
                        alert('Groupe créé avec succès. La page va être rechargée.');
                        location.reload();
                    } else {
                        alert(response.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX failed for URL ' + currentUrl + ':', textStatus, errorThrown);
                    console.log('Raw response:', jqXHR.responseText);
                    
                    // Try the next URL
                    tryCreateGroup(urls, index + 1);
                }
            });
        }
        
        tryCreateGroup(urlsToTry, 0);
    });

    $('.edit-group-btn').on('click', function(){
        console.log('Edit group button clicked');
        var oldName = $(this).data('old-name');
        console.log('Old group name:', oldName);
        $('#oldGroupNameForRename').val(oldName);
    });

    $('#renameGroupForm').on('submit', function(e){
        e.preventDefault();
        console.log('renameGroupForm submitted');
        var oldName = $('#oldGroupNameForRename').val();
        var newName = $('#renameGroupName').val();
        console.log('Renaming group:', oldName, 'to:', newName);

        $.post(ajaxUrl, {
            action: 'rename_group',
            oldName: oldName,
            renameGroupName: newName,
            csrf_token: csrfToken
        }, function(response){
            console.log('Response from rename_group:', response);
            if (response.success) {
                location.reload();
            } else {
                alert(response.error);
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for rename_group:', textStatus, errorThrown);
        });
    });

    $('.delete-group-btn').on('click', function(){
        console.log('delete-group-btn clicked');
        if (confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')) {
            var groupName = $(this).data('name');
            console.log('Deleting group:', groupName);
            $.post(ajaxUrl, {
                action:'delete_group',
                groupName: groupName,
                csrf_token: csrfToken
            }, function(response){
                console.log('Response from delete_group:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error);
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                console.error('AJAX failed for delete_group:', textStatus, errorThrown);
            });
        }
    });

    $('#assignFunctionalityForm').on('submit', function(e){
        e.preventDefault();
        console.log('assignFunctionalityForm submitted');
        var formData = $(this).serialize();
        console.log('Assigning functionalities, formData:', formData);

        $.post(ajaxUrl, formData, function(response){
            console.log('Response from assign_functionalities:', response);
            if (response.success) {
                location.reload();
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for assign_functionalities:', textStatus, errorThrown);
        });
    });

    // Ajout de console.log sur le clic des boutons "Modifier" des fonctionnalités
    $('.edit-functionality-btn').on('click', function(){
        console.log('Edit functionality button clicked');
        var funcId = $(this).data('id');
        console.log('Functionality ID:', funcId);

        // Récupérer les détails de la fonctionnalité
        $.post(ajaxUrl, {
            action: 'get_functionality',
            id: funcId,
            csrf_token: csrfToken
        }, function(response) {
            console.log('Response from get_functionality:', response);
            if(response.success) {
                // Remplir la modal
                $('#editFunctionalityId').val(funcId);
                $('#functionalityName').val(response.functionality.name);
                $('#functionalityDescription').val(response.functionality.description);

                // Ouvrir la modal
                $('#editFunctionalityModal').modal('show');
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for get_functionality:', textStatus, errorThrown);
        });
    });

    $('.remove-from-group-btn').on('click', function(){
        if (confirm('Êtes-vous sûr de vouloir retirer cette fonctionnalité du groupe ?')) {
            var funcId = $(this).data('id');
            console.log('Removing functionality from group, ID:', funcId);
            $.post(ajaxUrl, {
                action:'remove_from_group',
                functionality_id: funcId,
                csrf_token: csrfToken
            }, function(response){
                console.log('Response from remove_from_group:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (response.error || 'Inconnue'));
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                console.error('AJAX failed for remove_from_group:', textStatus, errorThrown);
            });
        }
    });

    // Ajout de console.log sur les boutons d'ouverture de modale
    $('button[data-toggle="modal"]').on('click', function(){
        console.log('Modal trigger button clicked, target:', $(this).data('target'));
    });
});
</script>

<?php include_once "partials/footer.php"; ?>
