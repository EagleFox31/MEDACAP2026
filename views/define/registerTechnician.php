<?php
session_start();
include_once "../language.php";
require "../sendMail.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");

// Connecting to the database
$academy = $conn->academy;

// Connecting to collections
$users = $academy->users;
$trainings = $academy->trainings;
$applications = $academy->applications;

// Récupérer les valeurs des filtres
$selectedUser = $_GET['user'] ?? 'all';
$selectedTraining = $_GET['training'] ?? 'all';

// Récupérer les valeurs de l'utilisateur connecté
$subsidiary = $_SESSION['subsidiary'];

// Extraire les données pour l'affichage
$filters = [
    'active' => true,
    'profile' => ['$in' => ['Technicien', 'Manager']], // Filtrer les techniciens et managers
    'subsidiary' => $subsidiary
];

// Filtrer uniquement les managers qui ont "test: true"
$filters['$or'] = [
    ['profile' => 'Technicien'], // Inclure les techniciens (en fonction des autres filtres)
    [
        'profile' => 'Manager',
        'test' => true // Inclure uniquement les managers qui ont passé un test
    ]
];

if ($selectedUser != 'all') {
    $technician = $users->findOne([
        '_id' => new MongoDB\BSON\ObjectId($selectedUser),
        'active' => true
    ]);
} else {
    $technicians = $users->find($filters)->toArray();
}
if ($selectedTraining != 'all') {
    $trainingDt = $trainings->findOne([
        '_id' => new MongoDB\BSON\ObjectId($selectedTraining),
        'active' => true
    ]);
}

if (isset($_POST['userId'])) {
    $selectedUser  = $_POST['userId'];

    // Définir les sous-options en fonction de l'option sélectionnée
    $trainingsData = $trainings->find([
        'users' => new MongoDB\BSON\ObjectId($selectedUser ),
        'active' => true
    ])->toArray();

    $trainingData = $trainings->findOne([
        '_id' => new MongoDB\BSON\ObjectId($trainingsData[0]['_id']),
        'active' => true
    ]);

    $trainingOptions = "";
    // Générer les options HTML
    foreach ($trainingsData as $training) {
        $trainingOptions .= "<option value=\"" . htmlspecialchars($training["_id"]) . "\" title=\"" . htmlspecialchars($training["label"]) . "\">" . htmlspecialchars($training["code"]) . "</option>";
    }
    
    $dateOptions = "";
    // Vérifier si des données de formation ont été trouvées
    if ($trainingData && isset($trainingData["startDate"]) && isset($trainingData['endDate'])) {
        foreach ($trainingData["startDate"] as $i => $start) {
            $dateOptions .= "<option value=\"" . htmlspecialchars($start) . " au " . htmlspecialchars($trainingData['endDate'][$i]) . "\">" . htmlspecialchars($start) . " au " . htmlspecialchars($trainingData['endDate'][$i]) . "</option>";
        }
    }

    $response = [
        'trainingOptions' => $trainingOptions,
        'dateOptions' => $dateOptions
    ];
    echo json_encode($response);

    exit(); // Terminer le script après avoir envoyé la réponse
}

if (isset($_POST['trainingId'])) {
    $selectedTraining = $_POST['trainingId'];

    // Définir les sous-options en fonction de l'option sélectionnée
    $trainingData = $trainings->findOne([
        '_id' => new MongoDB\BSON\ObjectId($selectedTraining),
        'active' => true
    ]);

    // Vérifier si des données de formation ont été trouvées
    if ($trainingData && isset($trainingData["startDate"]) && isset($trainingData['endDate'])) {
        foreach ($trainingData["startDate"] as $i => $start) {
            echo "<option value=\"" . htmlspecialchars($start) . " au " . htmlspecialchars($trainingData['endDate'][$i]) . "\">" . htmlspecialchars($start) . " au " . htmlspecialchars($trainingData['endDate'][$i]) . "</option>";
        }
    }
    exit(); // Terminer le script après avoir envoyé la réponse
}

if (isset($_POST['submit'])) {
    $user = $_POST["user"] ?? '';
    $training = $_POST["training"] ?? '';
    $date = $_POST["date"] ?? '';
    
    if(empty($user) || empty($training) || empty($date)) {
        if ($selectedTraining == 'all' && $selectedUser == 'all') {
            $error = $champ_obligatoire;
        } else {
            $response = [
                'success' => false,
                'message' => $champ_obligatoire
            ];
            echo json_encode($response);
            exit(); // Terminer le script après avoir envoyé la réponse
        }
    } else {
        $userData = $users->findOne([
            '$and' => [
                [
                    '_id' => new MongoDB\BSON\ObjectId($_SESSION['id']),
                    'active' => true
                ]
            ]
        ]);
        $collab = $users->findOne([
            '$and' => [
                [
                    '_id' => new MongoDB\BSON\ObjectId($user),
                    'active' => true
                ]
            ]
        ]);
        
        $trainingData = $trainings->findOne([
            '$and' => [
                [
                    '_id' => new MongoDB\BSON\ObjectId($training),
                    'active' => true
                ]
            ]
        ]);
        
        
        $trainers = $users->find([
            '$expr' => [
                '$eq' => [
                    ['$concat' => ['$firstName', ' ', '$lastName']],
                    $trainingData["trainer"]
                ]
            ]
        ]);
        
        $trainer = [];
        // Afficher les résultats
        foreach ($trainers as $document) {
            $trainer['_id'] = $document['_id'];
            $trainer['email'] = $document['email'];
        }

        $exist = $applications->findOne([
            '$and' => [
                [
                    'user' => new MongoDB\BSON\ObjectId($user),
                    'training' => new MongoDB\BSON\ObjectId($training),
                    'date' => $date,
                    'active' => true
                ]
            ],
        ]);
    
        if ($exist) {
            if ($selectedTraining == 'all' && $selectedUser == 'all') {
                $error_msg = $error_register_tech_training;
            } else {
                $response = [
                    'success' => false,
                    'message' => $error_register_tech_training
                ];
                echo json_encode($response);
                exit(); // Terminer le script après avoir envoyé la réponse
            }
        } else {
            $register = [
                'user' => new MongoDB\BSON\ObjectId($user),
                'training' => new MongoDB\BSON\ObjectId($training),
                'trainer' => new MongoDB\BSON\ObjectId($trainer['_id']),
                'level' => $trainingData['level'],
                'date' => $date,
                'active' => true,
                "created" => date("d-m-Y H:i:s")
            ];
                
            // Assurez-vous que les variables sont définies et valides
            if (isset($manager['lastName'], $technician['firstName'], $technician['lastName'], $trainingData['label'])) {
                // Échapper les données pour éviter les problèmes de sécurité
                $managerLastName = htmlspecialchars($userData['lastName']);
                $technicianFirstName = htmlspecialchars($collab['firstName']);
                $technicianLastName = htmlspecialchars($collab['lastName']);
                $trainingLabel = htmlspecialchars($trainingData['label']);

                $message = '<p>Bonjour Madame/Monsieur '.$userData['lastName'].',</p>
                        <p>Nous avons bien reçu l\'inscription de  M. 
                        <strong>'.$technicianFirstName.' '.$technicianLastName.'</strong>
                         à la formation <strong>'.$trainingLabel.'</strong>, 
                           prévue du <strong>'.$date.'</strong>.<br><br>
                           Nous restons à votre disposition pour toute question ou information complémentaire.</p><br>
                        <p>Si vous souhaitez annuler l\'inscription de <strong>'.$technicianFirstName.' '.$technicianLastName.'</strong> à cette formation,
                            veuillez nous en informer en repondant à ce courriel.</p>                    
                        <p style="font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いしま。</p>
                    ';
                $subject = 'Confirmation de réception de l\'inscription à la formation '.$trainingLabel;         
                $sendMail = sendMailRegisterTraining($userData['email'], $subject, $message, $trainer['email']);
                
                if ($sendMail) {
                    $applications->insertOne($register);
                    if ($selectedTraining == 'all' && $selectedUser == 'all') {
                        $success_msg = $success_register_training;
                    } else {
                        $response = [
                            'success' => true,
                            'message' => $success_register_training
                        ];
                        echo json_encode($response);
                        exit(); // Terminer le script après avoir envoyé la réponse
                    }
                } else {
                    if ($selectedTraining == 'all' && $selectedUser == 'all') {
                        $error_msg = 'Une erreur est survenue lors de l\'envoi du mail. Veuillez réessayer.';
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Une erreur est survenue lors de l\'envoi du mail. Veuillez réessayer.'
                        ];
                        echo json_encode($response);
                        exit(); // Terminer le script après avoir envoyé la réponse
                    }
                }
            } else {
                $applications->insertOne($register);
                if ($selectedTraining == 'all' && $selectedUser == 'all') {
                    $success_msg = $success_register_training;
                } else {
                    $response = [
                        'success' => true,
                        'message' => $success_register_training
                    ];
                    echo json_encode($response);
                    exit(); // Terminer le script après avoir envoyé la réponse
                }
            }
        }
    }
}
?>

<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo htmlspecialchars($register_training); ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('#user').change(function() {
            var selectedUser  = $(this).val();
            
            $.ajax({
                type: 'POST',
                data: { userId: selectedUser  },
                dataType: 'json', // Indique que vous attendez une réponse JSON
                success: function(data) {
                    $('#training').html(data.trainingOptions); // Met à jour le second select
                    $('#date').html(data.dateOptions); // Met à jour le second select
                }
            });
        });

        $('#training').change(function() {
            var selectedTraining = $(this).val();
            
            $.ajax({
                type: 'POST',
                data: { trainingId: selectedTraining },
                success: function(data) {
                    $('#date').html(data); // Met à jour le troisième select
                }
            });
        });

        $('#submit').click(function(event) {
            event.preventDefault(); // Empêche le comportement par défaut du bouton

            var user = document.querySelector('#user').value;
            var training = document.querySelector('#training').value;
            var date = document.querySelector('#date').value;
            
            $.ajax({
                type: 'POST',
                data: {
                    submit: 1,
                    user: user,
                    training: training,
                    date: date
                },
                dataType: 'json', // Indique que vous attendez une réponse JSON
                success: function(response) {
                    if (response.success) {
                        // Afficher le message de succès
                        $('#message').html('<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                            '<center><strong>' + response.message + '</strong></center>' +
                            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                            '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '</div>');
                    } else {
                        // Afficher un message d'erreur si nécessaire
                        $('#message').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                            '<center><strong>' + response.message + '</strong></center>' +
                            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                            '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    // Gérer les erreurs de requête AJAX
                    $('#message').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        '<center><strong>Une erreur est survenue. Veuillez réessayer.</strong></strong></center>' +
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                        '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                        '</div>');
                }
            });
        });

    });
</script>

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class="container-xxl" data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class='my-3 text-center'><?php echo htmlspecialchars($register_training); ?></h1>

                <?php if (isset($success_msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <center><strong><?php echo htmlspecialchars($success_msg); ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>
                <?php if (isset($error_msg)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <center><strong><?php echo htmlspecialchars($error_msg); ?></strong></center>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php } ?>
                <div id="message"></div>

                <form method="POST"><br>
                    <!--begin::Input group-->
                    <div id='container' class="row fv-row mb-7">
                        <!--begin::Input group-->
                        <div class='d-flex flex-column mb-7 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder text-dark fs-6'>
                                <span class='required'><?php echo htmlspecialchars($prenomsNomsTechs); ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select id="user" name="user" class="form-select" <?php if ($selectedUser != 'all') echo 'disabled'; ?>>
                                <option value="" <?php if ($selectedUser === 'all') echo 'disabled selected'; ?>>-- Selectionnez un technicien --</option>
                                <?php if ($selectedUser != 'all') { ?>
                                    <option value="<?php echo htmlspecialchars($technician['_id']); ?>" selected>
                                        <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                    </option>
                                <?php } else { ?>
                                    <?php foreach ($technicians as $technician): ?>
                                    <option value="<?php echo htmlspecialchars($technician['_id']); ?>" >
                                        <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php } ?>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo htmlspecialchars($error); ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class='d-flex flex-column mb-7 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder text-dark fs-6'>
                                <span class='required'><?php echo htmlspecialchars($Trainings); ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select id="training" name="training" class="form-select" <?php if ($selectedTraining != 'all') echo 'disabled'; ?>>
                                <option value="" <?php if ($selectedTraining === 'all') echo 'disabled selected'; ?>>-- Selectionnez une formation --</option>
                                <?php if ($selectedTraining != 'all') { ?>
                                    <option value="<?php echo htmlspecialchars($trainingDt['_id']); ?>" 
                                    title="<?php echo htmlspecialchars($trainingDt['label']); ?>" selected>
                                        <?php echo htmlspecialchars($trainingDt['code']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo htmlspecialchars($error); ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="d-flex flex-column mb-7 fv-row" style="margin-top: 10px">
                            <!--begin::Label-->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?php echo htmlspecialchars($trainingDate); ?></span>
                            </label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select id="date" name="date" class="form-select">
                                <option value="" disabled selected>-- Selectionnez une periode de formation --</option>
                                <?php if ($selectedTraining != 'all') { ?>
                                    <?php foreach ($trainingDt['startDate'] as $i => $start): ?>
                                    <option value="<?php echo htmlspecialchars($start.' au '.$trainingDt['endDate'][$i]); ?>">
                                        <?php echo htmlspecialchars($start.' au '.$trainingDt['endDate'][$i]); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php } ?>
                            </select>
                            <!--end::Input-->
                            <?php if (isset($error)) { ?>
                            <span class='text-danger'>
                                <?php echo htmlspecialchars($error); ?>
                            </span>
                            <?php } ?>
                        </div>
                        <!--end::Input group-->
                    </div>
                    <!--end::Scroll-->
                    <!--end::Modal body-->
                    <!--begin::Modal footer -->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" class="btn btn-primary"
                            id="<?php if ($selectedUser != 'all' && $selectedTraining != 'all') echo 'submit' ?>"
                            name="<?php if ($selectedUser == 'all' && $selectedTraining == 'all') echo 'submit' ?>">
                            <span class="indicator-label">
                                <?php echo htmlspecialchars($valider); ?>
                            </span>
                            <span class="indicator-progress">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <!--end::Button-->
                    </div>
                    <!--end::Modal footer-->
                </form>
            </div>
            <!--end::Modal body-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<script>
    // Function to handle closing of the alert message
    document.addEventListener('DOMContentLoaded', function() {
        const closeButtons = document.querySelectorAll('.alert .close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = this.closest('.alert');
                alert.remove();
            });
        });
    });
</script>
<?php include_once "partials/footer.php"; ?>