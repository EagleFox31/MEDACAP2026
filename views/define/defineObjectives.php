<?php
// defineObjectives.php

require_once "../../vendor/autoload.php";
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

session_start();

// Vérifier l'authentification
if (!isset($_SESSION["profile"]) || ($_SESSION["profile"] !== 'Manager' && $_SESSION["profile"] !== 'Super Admin')) {
    header("Location: /");
    exit();
}

try {
    $mongo   = new Client("mongodb://localhost:27017");
    $academy = $mongo->academy;

    $usersColl     = $academy->users;
    $trainingsColl = $academy->trainings;
} catch (MongoDB\Exception\Exception $e) {
    echo "Erreur de connexion à MongoDB : " . htmlspecialchars($e->getMessage());
    exit();
}

// Récupérer le technicien si spécifié
$techId = $_GET['techId'] ?? null;
$technician = null;

if ($techId) {
    try {
        $techObjId = new ObjectId($techId);
        $technician = $usersColl->findOne([
            '_id' => $techObjId,
            'profile' => 'Technicien',
            'active' => true
        ]);
    } catch (\Exception $e) {
        echo "Identifiant technicien invalide.";
        exit();
    }

    if (!$technician) {
        echo "Technicien introuvable.";
        exit();
    }
}

// Traitement du formulaire de définition des objectifs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $objective = $_POST['objective'] ?? '';
    $techIdPost = $_POST['techId'] ?? '';

    if ($objective && $techIdPost) {
        try {
            $techObjIdPost = new ObjectId($techIdPost);
            // Exemple : Ajouter l'objectif à une collection 'objectives'
            $academy->objectives->insertOne([
                'technicianId' => $techObjIdPost,
                'managerId' => new ObjectId($_SESSION["id"]),
                'objective' => $objective,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
            echo "Objectif défini avec succès.";
            // Rediriger ou afficher un message de succès
        } catch (\Exception $e) {
            echo "Erreur lors de la définition de l'objectif : " . htmlspecialchars($e->getMessage());
        }
    } else {
        echo "Tous les champs sont requis.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définir des Objectifs de Formations</title>
    <!-- Inclure Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Inclure Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* Styles personnalisés */
        .container-custom {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background-color: #fff;
        }
    </style>
</head>
<body>
    <?php include "./partials/header.php"; ?>

    <div class="container-custom">
        <h3 class="mb-4">Définir des Objectifs de Formations</h3>
        <?php if ($technician) { ?>
            <p><strong>Technicien :</strong> <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?></p>
        <?php } else { ?>
            <p><strong>Technicien :</strong> Tous les techniciens</p>
        <?php } ?>
        <form method="POST" action="defineObjectives.php">
            <div class="mb-3">
                <label for="objective" class="form-label">Objectif de Formation</label>
                <textarea class="form-control" id="objective" name="objective" rows="4" required></textarea>
            </div>
            <?php if ($technician) { ?>
                <input type="hidden" name="techId" value="<?php echo htmlspecialchars($techId); ?>">
            <?php } ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Enregistrer l'Objectif</button>
        </form>
    </div>

    <?php include "./partials/footer.php"; ?>
</body>
</html>
