// manageGroups.js
document.addEventListener('DOMContentLoaded', function() {

    // Définir csrfToken
    var csrfToken = '<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>';

    // Initialiser Select2 pour les sélections multiples
    $('#selectFunctionalities').select2();
    $('#selectGroupName').select2();

    // Initialiser Sortable pour la liste des groupes
    var groupsList = document.getElementById('groupsList');
    if (groupsList) {
        new Sortable(groupsList, {
            animation: 150
        });
    }

    // Initialiser Sortable pour les listes de fonctionnalités
    $('.functionalities-list').each(function(){
        new Sortable(this, {
            animation:150
        });
    });

    // Gestion de l'enregistrement de l'ordre des groupes
    $('#saveGroupsOrderBtn').on('click', function() {
        var groupNames = [];
        $('#groupsList li').each(function(){
            groupNames.push($(this).data('group-name'));
        });

        $.post('manageGroups.ajax.php', {
            action: 'reorder_groups',
            groupNames: groupNames,
            csrf_token: csrfToken
        }, function(response){
            if (response.success) {
                alert('Ordre des groupes enregistré');
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json');
    });

    // Gestion de l'enregistrement de l'ordre des fonctionnalités
    $('.saveFunctionalitiesOrderBtn').on('click', function(){
        var parentCard = $(this).closest('.card');
        var functionalitiesList = parentCard.find('.functionalities-list');
        var groupName = functionalitiesList.data('group-name');
        var funcIds = [];
        functionalitiesList.find('li').each(function(){
            funcIds.push($(this).data('functionality-id'));
        });

        $.post('manageGroups.ajax.php', {
            action:'reorder_functionalities',
            groupName: groupName,
            funcIds: funcIds,
            csrf_token: csrfToken
        }, function(response){
            if (response.success){
                alert('Ordre des fonctionnalités enregistré');
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json');
    });

    // Gestion de la création d'un groupe
    $('#createGroupForm').on('submit', function(e){
        e.preventDefault();
        var name = $('#newGroupName').val();

        $.post('manageGroups.ajax.php', {
            action:'create_group',
            name:name,
            csrf_token: csrfToken
        }, function(response){
            if (response.success) {
                // Ajouter le nouveau groupe à la liste de sélection dans la modal d'assignation
                var groupName = response.group.name; 
                
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
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for create_group:', textStatus, errorThrown);
        });
    });

    // Gestion de l'ouverture de la modal de renommage
    $('.edit-group-btn').on('click', function(){
        var oldName = $(this).data('old-name');
        $('#oldGroupNameForRename').val(oldName);
        $('#renameGroupModal').modal('show');
    });

    // Gestion du renommage d'un groupe
    $('#renameGroupForm').on('submit', function(e){
        e.preventDefault();
        var oldName = $('#oldGroupNameForRename').val();
        var newName = $('#renameGroupName').val();

        $.post('manageGroups.ajax.php', {
            action: 'rename_group',
            oldName: oldName,
            renameGroupName: newName,
            csrf_token: csrfToken
        }, function(response){
            if (response.success) {
                location.reload();
            } else {
                alert(response.error);
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for rename_group:', textStatus, errorThrown);
        });
    });

    // Gestion de la suppression d'un groupe
    $('.delete-group-btn').on('click', function(){
        if (confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')) {
            var groupName = $(this).data('name');
            $.post('manageGroups.ajax.php', {
                action:'delete_group',
                groupName: groupName,
                csrf_token: csrfToken
            }, function(response){
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + (response.error || 'Inconnue'));
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                console.error('AJAX failed for delete_group:', textStatus, errorThrown);
            });
        }
    });

    // Gestion de l'assignation des fonctionnalités à un groupe
    $('#assignFunctionalityForm').on('submit', function(e){
        e.preventDefault();
        var formData = $(this).serialize();

        $.post('manageGroups.ajax.php', formData, function(response){
            if (response.success) {
                location.reload();
            } else {
                alert('Erreur: ' + (response.error || 'Inconnue'));
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            console.error('AJAX failed for assign_functionalities:', textStatus, errorThrown);
        });
    });

    // Gestion de la modification d'une fonctionnalité
    $('.edit-functionality-btn').on('click', function(){
        var funcId = $(this).data('id');

        // Récupérer les détails de la fonctionnalité
        $.post('manageGroups.ajax.php', {
            action: 'get_functionality',
            id: funcId,
            csrf_token: csrfToken
        }, function(response) {
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

    // Gestion de la suppression d'une fonctionnalité d'un groupe
    $('.remove-from-group-btn').on('click', function(){
        if (confirm('Êtes-vous sûr de vouloir retirer cette fonctionnalité du groupe ?')) {
            var funcId = $(this).data('id');
            $.post('manageGroups.ajax.php', {
                action:'remove_from_group',
                functionality_id: funcId,
                csrf_token: csrfToken
            }, function(response){
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

    // Gestion des modales d'information, de succès et d'erreur
    // Ces modales sont déclenchées depuis le script principal lors des actions AJAX
});

