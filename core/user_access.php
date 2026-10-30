<?php
/**
 * Contrôle d'accès aux données d'un autre utilisateur (id passé en GET).
 * PHP 8.4. Technicien / Candidat : soi seul. Manager : soi + son équipe.
 * Les autres profils (Admin, RH, directeurs, formateurs) gardent leur accès.
 */

function medacapCanViewUser($users, $targetId)
{
    if (!isset($_SESSION['id'], $_SESSION['profile'])) {
        return false;
    }
    $me = (string) $_SESSION['id'];
    $targetId = (string) $targetId;
    if ($targetId === '' || $targetId === 'all' || $targetId === $me) {
        return true;
    }

    $profile = $_SESSION['profile'];
    if ($profile === 'Technicien' || $profile === 'Candidat') {
        return false;
    }
    if ($profile !== 'Manager') {
        return true;
    }

    try {
        $target = new MongoDB\BSON\ObjectId($targetId);
        $meId = new MongoDB\BSON\ObjectId($me);
    } catch (Throwable $e) {
        return false;
    }
    if ($users->findOne(array('_id' => $target, 'manager' => $meId), array('projection' => array('_id' => 1)))) {
        return true;
    }
    return (bool) $users->findOne(array('_id' => $meId, 'users' => $target), array('projection' => array('_id' => 1)));
}

/** Redirige vers le portail si l'utilisateur connecté ne peut pas voir $targetId. */
function medacapRequireUserAccess($users, $targetId)
{
    if (!medacapCanViewUser($users, $targetId)) {
        header('Location: ../portal.php');
        exit();
    }
}
