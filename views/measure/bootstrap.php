<?php
// Inclut automatiquement tous les fichiers de composant
foreach (glob('components/*.php') as $helperFile) {
    require_once $helperFile;
}
?>