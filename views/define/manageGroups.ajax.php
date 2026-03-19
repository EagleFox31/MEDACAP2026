<?php
// manageGroups.ajax.php
session_start();
require_once "groupFunctions.php";

// Définir le Content-Type en JSON
header('Content-Type: application/json');

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }

    // Obtenir le module actuel
    $currentModule = getCurrentModule();
    if (empty($currentModule)) {
        echo json_encode(['error' => 'Current module not specified']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    try {
        switch($action) {
            case 'create_group':
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    echo json_encode(['error' => 'Name is required']);
                    exit();
                }
                // Créer le groupe
                $group = createGroup($name, $currentModule);
                echo json_encode(['success' => true, 'group' => $group]);
                break;

            case 'rename_group':
                $oldName = $_POST['oldName'] ?? '';
                $newName = trim($_POST['renameGroupName'] ?? '');
                if ($oldName && $newName) {
                    renameGroup($oldName, $newName, $currentModule);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'Missing parameters']);
                }
                break;

            case 'delete_group':
                $groupName = $_POST['groupName'] ?? '';
                if ($groupName !== '') {
                    deleteGroup($groupName, $currentModule);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'Missing groupName']);
                }
                break;

            case 'reorder_groups':
                $groupNames = $_POST['groupNames'] ?? [];
                if (!is_array($groupNames)) {
                    echo json_encode(['error' => 'Invalid groupNames format']);
                    exit();
                }
                // Réordonner les groupes pour le module actuel
                reorderGroups($groupNames, $currentModule);
                echo json_encode(['success' => true]);
                break;

            case 'reorder_functionalities':
                $groupName = $_POST['groupName'] ?? '';
                $funcIds = $_POST['funcIds'] ?? [];
                if ($groupName !== '' && is_array($funcIds)) {
                    reorderFunctionalities($groupName, $funcIds, $currentModule);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'Missing parameters']);
                }
                break;

            case 'assign_functionalities':
                $groupName = $_POST['groupName'] ?? '';
                $funcIds = $_POST['selectFunctionalities'] ?? [];
                if ($groupName !== '' && is_array($funcIds)) {
                    assignFunctionalitiesToGroup($groupName, $funcIds, $currentModule);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'Missing parameters']);
                }
                break;

            case 'remove_from_group':
                $funcId = $_POST['functionality_id'] ?? '';
                if ($funcId !== '') {
                    removeFromGroup($funcId, $currentModule);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'Missing functionality_id']);
                }
                break;

            case 'get_functionality':
                $id = $_POST['id'] ?? '';
                if ($id !== '') {
                    $func = getFunctionalityById($id, $currentModule); 
                    if ($func) {
                        echo json_encode(['success' => true, 'functionality' => [
                            'name' => $func['name'] ?? '',
                            'description' => $func['description'] ?? '',
                        ]]);
                    } else {
                        echo json_encode(['error' => 'Fonctionnalité non trouvée']);
                    }
                } else {
                    echo json_encode(['error' => 'ID manquant']);
                }
                break;

            case 'edit_functionality':
                $funcId = $_POST['functionality_id'] ?? '';
                $newName = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if ($funcId !== '' && $newName !== '') {
                    $success = updateFunctionality($funcId, [
                        'name' => $newName,
                        'description' => $description,
                    ], $currentModule);
                    if ($success) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['error' => 'Impossible de mettre à jour la fonctionnalité']);
                    }
                } else {
                    echo json_encode(['error' => 'Paramètres manquants']);
                }
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
