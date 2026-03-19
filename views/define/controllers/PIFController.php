<?php
namespace Controllers;

use views\define\services\PIFService;

class PIFController
{
    public function showPIFProgress()
    {
        // Instancier le service
        $pifService = new PIFService();

        // Récupérer les indicateurs
        $indicators  = $pifService->getPIFIndicators();
        $proposition = $pifService->getPIFPropositionData();
        $validation  = $pifService->getPIFValidationData();
        $nbPifJunior = $pifService->countPIFByExactLevel('Junior');
        $nbPifSenior = $pifService->countPIFByExactLevel('Senior');
        $nbPifExpert = $pifService->countPIFByExactLevel('Expert');


        // Inclure la vue en lui passant les données
        // Ici, on fait un require simple, mais en MVC on ferait souvent un "render" template
        require __DIR__ . '/../views/pif_progress.php';
    }
}