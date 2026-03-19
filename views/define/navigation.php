<?php


/**
 * Initialise l'historique de navigation si nécessaire.
 */
function init_navigation_history() {
    if (!isset($_SESSION['navigation_history'])) {
        $_SESSION['navigation_history'] = [];
        $_SESSION['navigation_position'] = -1; // Position initiale
    }
}

/**
 * Met à jour l'historique de navigation en fonction de la page actuelle et des actions de navigation.
 */
function update_navigation_history() {
    init_navigation_history();

    $current_url = $_SERVER['REQUEST_URI'];

    // Vérifier si on vient de cliquer sur "Précédent" ou "Suivant"
    if (isset($_GET['navigate']) && ($_GET['navigate'] == 'back' || $_GET['navigate'] == 'forward')) {
        // Ne pas ajouter la page à l'historique, simplement mettre à jour la position
        if ($_GET['navigate'] == 'back' && $_SESSION['navigation_position'] > 0) {
            $_SESSION['navigation_position']--;
        } elseif ($_GET['navigate'] == 'forward' && $_SESSION['navigation_position'] < count($_SESSION['navigation_history']) - 1) {
            $_SESSION['navigation_position']++;
        }
        // Rediriger vers la page correspondante
        $new_url = $_SESSION['navigation_history'][$_SESSION['navigation_position']];
        header("Location: $new_url");
        exit();
    } else {
        // L'utilisateur navigue normalement, ajouter la page à l'historique
        // Si on n'est pas à la fin de l'historique, tronquer l'historique
        if ($_SESSION['navigation_position'] < count($_SESSION['navigation_history']) - 1) {
            $_SESSION['navigation_history'] = array_slice($_SESSION['navigation_history'], 0, $_SESSION['navigation_position'] + 1);
        }
        // Ajouter la page actuelle à l'historique
        $_SESSION['navigation_history'][] = $current_url;
        $_SESSION['navigation_position']++;
    }
}

/**
 * Affiche les boutons "Précédent" et "Suivant" en fonction de la position dans l'historique.
 */
function render_navigation_buttons() {
    init_navigation_history();

    echo '<div class="navigation-buttons">';

    // Bouton Précédent
    if ($_SESSION['navigation_position'] > 0) {
        // Activer le bouton
        $back_url = '?navigate=back';
        echo '<a href="' . $back_url . '" class="btn btn-primary">Précédent</a>';
    } else {
        // Désactiver le bouton
        echo '<button class="btn btn-secondary" disabled>Précédent</button>';
    }

    // Bouton Suivant
    if ($_SESSION['navigation_position'] < count($_SESSION['navigation_history']) - 1) {
        // Activer le bouton
        $forward_url = '?navigate=forward';
        echo ' <a href="' . $forward_url . '" class="btn btn-primary">Suivant</a>';
    } else {
        // Désactiver le bouton
        echo ' <button class="btn btn-secondary" disabled>Suivant</button>';
    }

    echo '</div>';
}
?>
