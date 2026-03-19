<?php
require_once __DIR__ . '/services/PIFService.php';
require_once __DIR__ . '/controllers/PIFController.php';

use Controllers\PIFController;

// Démarrer la session si besoin
session_start();

// Exemple de route très basique : on appelle la méthode showPIFProgress
$controller = new PIFController();
$controller->showPIFProgress();