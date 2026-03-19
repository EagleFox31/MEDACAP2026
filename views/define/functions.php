<?php
function hasPermission($functionalityKey) {
    // Démarrer la session si ce n'est pas déjà fait
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION["profile"])) {
        return false;
    }

    $userProfile = $_SESSION["profile"];

    // Connexion à MongoDB
    require_once "../../vendor/autoload.php";
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $functionalitiesCollection = $academy->functionalities;

    // Récupérer la fonctionnalité
    $functionality = $functionalitiesCollection->findOne(['key' => $functionalityKey]);

    if (!$functionality) {
        // Si la fonctionnalité n'existe pas, on peut considérer qu'il n'y a pas de permission
        return false;
    }

    // Convertir 'profiles' en tableau PHP natif
    $profilesArray = [];

    if (isset($functionality['profiles']) && $functionality['profiles'] instanceof \MongoDB\Model\BSONArray) {
        $profilesArray = $functionality['profiles']->getArrayCopy();
    }


    // Vérifier si le profil de l'utilisateur est dans le tableau des profils autorisés
    return in_array($userProfile, $functionality['profiles']);
}
?>
