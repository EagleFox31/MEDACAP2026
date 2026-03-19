<?php
// Fichier : views/define/helpers.php

if (!function_exists('asset')) {
    function asset(string $relative): string
    {
        // Chemin absolu sur le disque
        $file = __DIR__ . '/../' . ltrim($relative, '/');
        // Si fichier introuvable on évite le warning et on renvoie quand même l’URL
        $ver = file_exists($file) ? filemtime($file) : time();
        // URL relative vue du navigateur (on est dans views/director → remonte d’un niveau)
        return '../' . $relative . '?v=' . $ver;
    }
}