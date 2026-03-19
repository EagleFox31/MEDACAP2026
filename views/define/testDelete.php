<?php
session_start();

// Générer un token CSRF pour le test
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test de Suppression</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">
    <h1>Test de Suppression</h1>
    <!-- Bouton de Suppression -->
    <button class="btn btn-danger" data-toggle="modal" data-target="#deleteModal" data-id="60d5ec49f8d2e30c8c4e5d8b">
        Supprimer Profil Test
    </button>
</div>

<!-- Modal de Suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Formulaire de Suppression -->
            <form action="testDelete.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer ce profil ?
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="delete" id="deleteId" value="">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inclusion des Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        console.log("Script de test chargé et exécuté.");

        // Vérifier l'existence du modal
        console.log("deleteModal exists:", $('#deleteModal').length);

        // Passer l'ID du profil au modal de suppression avec Event Delegation
        $(document).on('shown.bs.modal', '#deleteModal', function(event) {
            console.log("Événement 'show.bs.modal' déclenché pour la suppression.");
            var button = $(event.relatedTarget); // Bouton qui a déclenché le modal
            console.log("Bouton de suppression cliqué:", button);
            var profileId = button.data('id'); // Extraire l'ID du profil du bouton
            console.log("ID du profil à supprimer :", profileId);
            var modal = $(this);
            modal.find('#deleteId').val(profileId); // Définir la valeur dans le champ caché
        });

        // Traitement de la suppression (pour le test)
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
            // Vérifier le token CSRF
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                echo "alert('Erreur de validation du formulaire.');";
            } else {
                $deleteValue = $_POST['delete'];
                echo "alert('Suppression du profil avec l\'ID : " . htmlspecialchars($deleteValue) . "');";
            }
        }
        ?>
    });
</script>

</body>
</html>
