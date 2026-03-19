<?php
session_start();
include_once "../language.php";

// Check if user is logged in
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";

    // Create MongoDB connection and collections
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $users = $academy->users;
    $results = $academy->results;
    $exams = $academy->exams;
    $tests = $academy->tests;
    $allocations = $academy->allocations;

    // Check if user has appropriate permissions
    if ($_SESSION['profile'] != "Super Admin" && $_SESSION['profile'] != "Admin" && 
        $_SESSION["profile"] != "Ressource Humaine" && $_SESSION['profile'] != "Directeur Pièce et Service" && 
        $_SESSION['profile'] != "Directeur des Opérations") {
        header("Location: ../../");
        exit();
    }

    // Get export type from URL
    $exportType = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    // Define filename based on export type
    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'Techniciens_';
    
    switch ($exportType) {
        case 'incomplete':
            $filename .= 'A_Completer_';
            break;
        case 'complete':
            $filename .= 'Termines_';
            break;
        default:
            $filename .= 'Tous_';
    }
    
    $filename .= $timestamp . '.xlsx';

    // Include helper functions from results1.php
    // Helper function to find test
    function findTest($tests, $userId, $level, $type) {
        return $tests->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($userId),
                    "level" => $level,
                    "type" => $type,
                    "active" => true,
                ],
            ],
        ]);
    }
    
    // Helper function to find exam
    function findExam($exams, $userId, $testId, $type = null, $managerId = null) {
        $query = [
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($userId),
                    "test" => new MongoDB\BSON\ObjectId($testId),
                ],
            ],
        ];
        
        if ($type) {
            $query['$and'][0]["type"] = $type;
        }
        
        if ($managerId) {
            $query['$and'][0]["manager"] = new MongoDB\BSON\ObjectId($managerId);
        }
        
        return $exams->findOne($query);
    }
    
    // Helper function to find allocation
    function findAllocation($allocations, $userId, $level, $type) {
        return $allocations->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($userId),
                    "level" => $level,
                    "type" => $type,
                ],
            ],
        ]);
    }
    
    // Helper function to find result
    function findResult($results, $userId, $level, $type, $typeR) {
        return $results->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($userId),
                    "level" => $level,
                    "type" => $type,
                    "typeR" => $typeR,
                    "active" => true,
                ],
            ],
        ]);
    }
    
    // Helper function to calculate percentage
    function calculatePercentage($result) {
        if (isset($result) && isset($result["score"]) && isset($result["total"]) && $result["total"] > 0) {
            return ceil(($result["score"] * 100) / $result["total"]);
        }
        return null;
    }
    
// Helper function to determine cell content for individual scores
    function getCellContent($percentage, $allocation, $isManagerPart = false) {
        if ($percentage !== null) {
            return $percentage;
        }
    
        if (isset($allocation)) {
            if ($isManagerPart) {
                // Pour la partie manager
                if (isset($allocation['activeManager'])) {
                    if ($allocation['activeManager'] == true) {
                        return 'À compléter';
                    } else {
                        return 'À compléter';  // Allocation inactive: aussi "À compléter"
                    }
                }
            } else {
                // Pour la partie technicien
                if (isset($allocation['active'])) {
                    if ($allocation['active'] == true) {
                        return 'À compléter';
                    } else {
                        return 'À compléter';  // Allocation inactive: aussi "À compléter"
                    }
                }
            }
        }
    
        return '-';
    }
    
    // Modified helper function to determine cell content for final notes
    function getNoteStatus($score, $alloc_fac, $alloc_decla) {
        if ($score !== null) {
            return 'Complété';
        }

        // Si au moins une allocation existe (active ou inactive), on affiche "À compléter"
        if (isset($alloc_fac) || isset($alloc_decla)) {
            return 'À compléter';
        }

        return 'Non applicable';
    }
    // Check if a specific level has incomplete evaluations
    function checkLevelIncomplete($userId, $level, $tests, $allocations, $exams, $results, $managerId = null) {
        // Check factual test
        $testFac = findTest($tests, $userId, $level, "Factuel");
        $allocateFac = findAllocation($allocations, $userId, $level, "Factuel");
        $resultFac = findResult($results, $userId, $level, "Factuel", "Technicien");
        
        // Check declarative test
        $testDecla = findTest($tests, $userId, $level, "Declaratif");
        $allocateDecla = findAllocation($allocations, $userId, $level, "Declaratif");
        $resultDeclaTech = findResult($results, $userId, $level, "Declaratif", "Techniciens");
        $resultDeclaMan = findResult($results, $userId, $level, "Declaratif", "Managers");
        
        // Case 1: Factual test is allocated but no result or exam in progress
        if (isset($allocateFac) && $allocateFac['active'] == true) {
            $examFac = isset($testFac) ? findExam($exams, $userId, $testFac['_id']) : null;
            
            // Exam in progress
            if (isset($examFac) && $examFac['active'] == true) {
                return true;
            }
            
            // No result but allocation is active
            if (!isset($resultFac) || !isset($resultFac['score'])) {
                return true;
            }
        }
        
        // Case 2: Declarative test (technician part) is allocated but no result or exam in progress
        if (isset($allocateDecla) && $allocateDecla['active'] == true) {
            $examDecla = isset($testDecla) ? findExam($exams, $userId, $testDecla['_id'], "Technicien") : null;
            
            // Exam in progress
            if (isset($examDecla) && $examDecla['active'] == true) {
                return true;
            }
            
            // No result but allocation is active
            if (!isset($resultDeclaTech) || !isset($resultDeclaTech['score'])) {
                return true;
            }
        }
        
        // Case 3: Declarative test (manager part) is allocated but no result or exam in progress
        if (isset($allocateDecla) && isset($allocateDecla['activeManager']) && $allocateDecla['activeManager'] == true) {
            $examDeclaMa = isset($testDecla) && isset($userId) ?
                findExam($exams, $userId, $testDecla['_id'], "Manager", $managerId) : null;
            
            // Exam in progress
            if (isset($examDeclaMa) && $examDeclaMa['active'] == true) {
                return true;
            }
            
            // No result but allocation is active
            if (!isset($resultDeclaMan) || !isset($resultDeclaMan['score'])) {
                return true;
            }
        }
        
        return false; // All tests for this level are either completed or not allocated
    }
    
    // Check if a technician has any incomplete evaluations
    function hasIncompleteTests($userId, $tests, $allocations, $exams, $results, $managerId = null) {
        // Check Junior level
        $hasIncomplete = checkLevelIncomplete($userId, "Junior", $tests, $allocations, $exams, $results, $managerId);
        if ($hasIncomplete) return true;
        
        // Check Senior level
        $hasIncomplete = checkLevelIncomplete($userId, "Senior", $tests, $allocations, $exams, $results, $managerId);
        if ($hasIncomplete) return true;
        
        // Check Expert level
        $hasIncomplete = checkLevelIncomplete($userId, "Expert", $tests, $allocations, $exams, $results, $managerId);
        if ($hasIncomplete) return true;
        
        return false;
    }
    
    // Check if a technician has all evaluations completed
    function hasCompleteTests($userId, $tests, $allocations, $exams, $results, $managerId = null) {
        // If any test is incomplete, then not all tests are complete
        return !hasIncompleteTests($userId, $tests, $allocations, $exams, $results, $managerId);
    }

    // Prepare the query based on user permissions
    $query = ["active" => true];
    
    // Filter by profile
    $query['$or'] = [
        ['profile' => 'Technicien'],
        ['$and' => [
            ['profile' => 'Manager'], 
            ['test' => true]
        ]]
    ];
    
    // Add country filter if specified
    if (isset($_GET['country']) && $_GET['country'] !== 'all') {
        $query["country"] = $_GET['country'];
    }
    
    // Add user permissions filters
    if ($_SESSION['profile'] != "Super Admin") {
        $query["subsidiary"] = $_SESSION['subsidiary'];
        
        if ($_SESSION["department"] != 'Equipment & Motors') {
            $query['department'] = $_SESSION["department"];
        }
    }
    
    // Get all technicians matching the query
    $technicians = $users->find($query)->toArray();
    
    // Filter technicians based on export type
    $filteredTechnicians = [];
    
    foreach ($technicians as $tech) {
        $userId = (string)$tech['_id'];
        $managerId = isset($tech['manager']) ? $tech['manager'] : null;
        
        // Get manager info
        $manager = null;
        if ($managerId) {
            $manager = $users->findOne(["_id" => new MongoDB\BSON\ObjectId($managerId)]);
        }
        
        // Calculate scores for all levels
        $resultFacJu = findResult($results, $userId, "Junior", "Factuel", "Technicien");
        $resultFacSe = findResult($results, $userId, "Senior", "Factuel", "Technicien");
        $resultFacEx = findResult($results, $userId, "Expert", "Factuel", "Technicien");
        
        $resultDeclaJu = findResult($results, $userId, "Junior", "Declaratif", "Technicien - Manager");
        $resultDeclaSe = findResult($results, $userId, "Senior", "Declaratif", "Technicien - Manager");
        $resultDeclaEx = findResult($results, $userId, "Expert", "Declaratif", "Technicien - Manager");

        // 1) Résultats séparés Technicien / Manager
        $resultDeclaJuTech = findResult($results, $userId, "Junior", "Declaratif", "Techniciens");
        $resultDeclaJuMa   = findResult($results, $userId, "Junior", "Declaratif", "Managers");

        $resultDeclaSeTech = findResult($results, $userId, "Senior", "Declaratif", "Techniciens");
        $resultDeclaSeMa   = findResult($results, $userId, "Senior", "Declaratif", "Managers");

        $resultDeclaExTech = findResult($results, $userId, "Expert", "Declaratif", "Techniciens");
        $resultDeclaExMa   = findResult($results, $userId, "Expert", "Declaratif", "Managers");

        // 2) Leurs pourcentages
        $percentageDeclaJuTech = calculatePercentage($resultDeclaJuTech);
        $percentageDeclaJuMa   = calculatePercentage($resultDeclaJuMa);

        $percentageDeclaSeTech = calculatePercentage($resultDeclaSeTech);
        $percentageDeclaSeMa   = calculatePercentage($resultDeclaSeMa);

        $percentageDeclaExTech = calculatePercentage($resultDeclaExTech);
        $percentageDeclaExMa   = calculatePercentage($resultDeclaExMa);

        
        // Calculate percentages
        $percentageFacJu = calculatePercentage($resultFacJu);
        $percentageDeclaJu = calculatePercentage($resultDeclaJu);
        $juniorScore = null;
        if (isset($percentageFacJu) && isset($percentageDeclaJu)) {
            $juniorScore = ceil(($percentageFacJu + $percentageDeclaJu) / 2);
        }
        
        $percentageFacSe = calculatePercentage($resultFacSe);
        $percentageDeclaSe = calculatePercentage($resultDeclaSe);
        $seniorScore = null;
        if (isset($percentageFacSe) && isset($percentageDeclaSe)) {
            $seniorScore = ceil(($percentageFacSe + $percentageDeclaSe) / 2);
        }
        
        $percentageFacEx = calculatePercentage($resultFacEx);
        $percentageDeclaEx = calculatePercentage($resultDeclaEx);
        $expertScore = null;
        if (isset($percentageFacEx) && isset($percentageDeclaEx)) {
            $expertScore = ceil(($percentageFacEx + $percentageDeclaEx) / 2);
        }
        
        // Get allocations for all levels to check for "À compléter" cells
        $allocateFacJu = findAllocation($allocations, $userId, "Junior", "Factuel");
        $allocateDeclaJu = findAllocation($allocations, $userId, "Junior", "Declaratif");
        
        $allocateFacSe = findAllocation($allocations, $userId, "Senior", "Factuel");
        $allocateDeclaSe = findAllocation($allocations, $userId, "Senior", "Declaratif");
        
        $allocateFacEx = findAllocation($allocations, $userId, "Expert", "Factuel");
        $allocateDeclaEx = findAllocation($allocations, $userId, "Expert", "Declaratif");
        
        // Déterminer si le technicien a des tests à compléter
        $hasACompleterCell = false;
        
        // Vérifier si des allocations à "À compléter" existent
        $cellJuniorFac = getCellContent($percentageFacJu, $allocateFacJu);
        $cellJuniorTprotech = getCellContent($percentageDeclaJuTech, $allocateDeclaJu);
        $cellJuniorTproman = getCellContent($percentageDeclaJuMa, $allocateDeclaJu, true);
        
        $cellSeniorFac = getCellContent($percentageFacSe, $allocateFacSe);
        $cellSeniorTprotech = getCellContent($percentageDeclaSeTech, $allocateDeclaSe);
        $cellSeniorTproman = getCellContent($percentageDeclaSeMa, $allocateDeclaSe, true);
        
        $cellExpertFac = getCellContent($percentageFacEx, $allocateFacEx);
        $cellExpertTprotech = getCellContent($percentageDeclaExTech, $allocateDeclaEx);
        $cellExpertTproman = getCellContent($percentageDeclaExMa, $allocateDeclaEx, true);
        
        // Si un des scores est "À compléter", alors hasACompleterCell = true
        if ($cellJuniorFac === 'À compléter' || $cellJuniorTprotech === 'À compléter' || $cellJuniorTproman === 'À compléter' ||
            $cellSeniorFac === 'À compléter' || $cellSeniorTprotech === 'À compléter' || $cellSeniorTproman === 'À compléter' ||
            $cellExpertFac === 'À compléter' || $cellExpertTprotech === 'À compléter' || $cellExpertTproman === 'À compléter') {
            $hasACompleterCell = true;
        }
        
        $isIncomplete = $hasACompleterCell || hasIncompleteTests($userId, $tests, $allocations, $exams, $results, $managerId);
        $isComplete = !$isIncomplete;
        
        $includeInExport = false;
        $status = '';
        
        // Additional debug logging
        if ($exportType === 'incomplete') {
            error_log("Technician: {$tech['firstName']} {$tech['lastName']} - hasIncomplete: " . ($isIncomplete ? 'true' : 'false') . " - hasACompleterCell: " . ($hasACompleterCell ? 'true' : 'false'));
        }
        
        switch ($exportType) {
            case 'incomplete':
                if ($isIncomplete) {
                    $includeInExport = true;
                    $status = 'À compléter';
                }
                break;
            case 'complete':
                if ($isComplete) {
                    $includeInExport = true;
                    $status = 'Terminé';
                }
                break;
            default: // 'all'
                $includeInExport = true;
                $status = $isComplete ? 'Terminé' : 'À compléter';
        }
        
        if ($includeInExport) {
            // Prepare technician data for export
            $techData = [
                'firstName' => $tech['firstName'],
                'lastName' => $tech['lastName'],
                'level' => isset($tech['level']) ? $tech['level'] : '',
                'country' => $tech['country'],
                'subsidiary' => $tech['subsidiary'],
                'agency' => isset($tech['agency']) ? $tech['agency'] : '',
                'manager_name' => $manager ? $manager['firstName'] . ' ' . $manager['lastName'] : '',
                // --- JUNIOR ---
                'junior_fac'       => getCellContent($percentageFacJu, $allocateFacJu),
                'junior_tprotech'  => getCellContent($percentageDeclaJuTech, $allocateDeclaJu),
                'junior_tproman'   => getCellContent($percentageDeclaJuMa, $allocateDeclaJu, true),
                'junior_note'      => getNoteStatus($juniorScore, $allocateFacJu, $allocateDeclaJu),
                // --- SENIOR ---
                'senior_fac'       => getCellContent($percentageFacSe, $allocateFacSe),
                'senior_tprotech'  => getCellContent($percentageDeclaSeTech, $allocateDeclaSe),
                'senior_tproman'   => getCellContent($percentageDeclaSeMa, $allocateDeclaSe, true),
                'senior_note'      => getNoteStatus($seniorScore, $allocateFacSe, $allocateDeclaSe),
                // --- EXPERT ---
                'expert_fac'       => getCellContent($percentageFacEx, $allocateFacEx),
                'expert_tprotech'  => getCellContent($percentageDeclaExTech, $allocateDeclaEx),
                'expert_tproman'   => getCellContent($percentageDeclaExMa, $allocateDeclaEx, true),
                'expert_note'      => getNoteStatus($expertScore, $allocateFacEx, $allocateDeclaEx),
                'status' => $status,
            ];
            
            $filteredTechnicians[] = $techData;
        }
    }
    
    // Generate Excel file with filtered technicians
    generateExcel($filteredTechnicians, $exportType, $filename);
}


/**
 * Génère (et pousse en téléchargement) l'export Excel ― même structure que la grille *results1.php*
 *
 * @param array  $technicians  Données prêtes à l'export (chaque clé existe, même à null)
 * @param string $type         Type d'export : incomplete | complete | all
 * @param string $filename     Nom du fichier à générer
 */
function generateExcel(array $technicians, string $type, string $filename): void
{
    /* 1. Préparation classeur */
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    /* ---- Header fusionné ---- */
    // Ligne 1
    $sheet->fromArray(
        ['Nom','Prénom','Niveau','Pays','Filiale','Agence','Manager',
         'Niveau Junior','','','','Niveau Senior','','','','Niveau Expert','','','','Statut'],
        null,'A1'
    );
    foreach (['A','B','C','D','E','F','G','T'] as $ltr)
        $sheet->mergeCells("{$ltr}1:{$ltr}2");

    $sheet->mergeCells('H1:K1');  // Junior
    $sheet->mergeCells('L1:O1');  // Senior
    $sheet->mergeCells('P1:S1');  // Expert

    // Ligne 2 (sous-en-têtes)
    $sheet->fromArray(
        ['QCM Connaissances','QCM Tâches Pro Tech','QCM Tâches Pro Manager','Note Test Junior',
         'QCM Connaissances','QCM Tâches Pro Tech','QCM Tâches Pro Mgr','Note Test Senior',
         'QCM Connaissances','QCM Tâches Pro Tech','QCM Tâches Pro Mgr','Note Test Expert',],
        null,'H2'
    );

    // Style header
    $sheet->getStyle('A1:T2')->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                 'startColor'=>['rgb'=>'4472C4']],
        'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText'=>true],
    ]);

    /* 2. Données */
    $map = [
        'lastName','firstName','level','country','subsidiary','agency','manager_name',
        'junior_fac','junior_tprotech','junior_tproman','junior_note',
        'senior_fac','senior_tprotech','senior_tproman','senior_note',
        'expert_fac','expert_tprotech','expert_tproman','expert_note',
    ];
    $scoreCols = ['H','I','J','L','M','N','P','Q','R']; // Colonnes de scores (sans les colonnes de notes)
    $notesCols = ['K','O','S']; // Colonnes de notes spécifiques
    $row = 3;

    foreach ($technicians as $tech) {
        $ptr = 'A';
        foreach ($map as $k) {
            $val = $tech[$k] ?? null;
            $cellValue = is_numeric($val) ? $val.'%' : $val;
            $sheet->setCellValue($ptr.$row, $cellValue);
            
            // Centrer et styliser les cellules spéciales
            if ($cellValue === '-' || $cellValue === 'Non applicable') {
                // Centrer et mettre en gris
                $sheet->getStyle($ptr.$row)->applyFromArray([
                    'font' => ['color' => ['rgb' => '808080']], // Gris
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ]);
            } elseif ($cellValue === 'À compléter') {
                // Style pour "À compléter" (rouge)
                $sheet->getStyle($ptr.$row)->applyFromArray([
                    'font' => ['color' => ['rgb' => 'FF0000']], // Rouge
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ]);
            } elseif ($cellValue === 'Complété') {
                // Style pour "Complété" (bleu)
                $sheet->getStyle($ptr.$row)->applyFromArray([
                    'font' => ['color' => ['rgb' => '0000FF']], // Bleu
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ]);
            }
            
            $ptr++;
        }
        
        // Traiter la colonne Statut
        $statusValue = $tech['status'];
        $sheet->setCellValue('T'.$row, $statusValue);
        if ($statusValue === '-') {
            $sheet->getStyle('T'.$row)->applyFromArray([
                'font' => ['color' => ['rgb' => '808080']], // Gris
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);
        } elseif ($statusValue === 'À compléter') {
            $sheet->getStyle('T'.$row)->applyFromArray([
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);
        }

        // Appliquer les couleurs pour les scores numériques (exclut les colonnes de notes)
        foreach ($scoreCols as $c) {
            $cellVal = $sheet->getCell($c.$row)->getValue();
            
            // Vérifier si c'est un pourcentage numérique
            if (is_string($cellVal) && strpos($cellVal, '%') !== false) {
                $numericValue = intval($cellVal);
                if ($numericValue > 0) {
                    $color = $numericValue < 60 ? 'FF0000' : ($numericValue < 80 ? 'FFC000' : '00B050');
                    $sheet->getStyle($c.$row)->applyFromArray([
                        'font' => ['color' => ['rgb' => $color]],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }
        }
        $row++;
    }

    // Auto-ajuster la largeur des colonnes
    foreach (range('A','T') as $ltr)
        $sheet->getColumnDimension($ltr)->setAutoSize(true);

    /* 3. Download */
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}