<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $trainings = $academy->trainings;
    $applications = $academy->applications;
    $allocations = $academy->allocations;
    
    $application = $applications->find([
        "active" => true,
    ])->toArray();

    if (isset($_POST['confirm'])) {
        $applicationId = $_POST['applicationID'];

        $applicate = $applications->findOne([
            "_id" => new MongoDB\BSON\ObjectId($applicationId),
            "active" => true
        ]);        

        $applications->updateOne(
            [
                "_id" => new MongoDB\BSON\ObjectId($applicationId), // ID de l'utilisateur
            ],
            [
                '$set' => [
                    'status' => 'Confirmed', // Mettre à jour le champ 'status'
                    'updated' => date("d-m-Y H:i:s") // Mettre à jour le champ 'updated'
                ]
            ]
        );

        $allocations->updateOne(
            [
                "user" => new MongoDB\BSON\ObjectId($applicate['user']), // ID de l'utilisateur
                "training" => new MongoDB\BSON\ObjectId($applicate['training']) // ID de la formation
            ],
            [
                '$set' => [
                    'active' => true, // Mettre à jour le champ 'active'
                    'updated' => date("d-m-Y H:i:s") // Mettre à jour le champ 'updated'
                ]
            ]
        );

        $technician = $users->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($applicate['user']), // ID de l'utilisateur
                    "active" => true
                ]
            ],
        ]);

        $manager = $users->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($applicate['manager']), // ID du manager
                    "active" => true
                ]
            ],
        ]);

        $training = $trainings->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($applicate['training']), // ID du training
                    "active" => true
                ]
            ],
        ]);

        $trainers = $users->find([
            '$expr' => [
                '$eq' => [
                    ['$concat' => ['$firstName', ' ', '$lastName']],
                    $training["trainer"]
                ]
            ]
        ]);
        
        $trainer = [];
        // Afficher les résultats
        foreach ($trainers as $document) {
            $trainer['_id'] = $document['_id'];
            $trainer['email'] = $document['email'];
        }
                
        // Assurez-vous que les variables sont définies et valides
        if (isset($manager, $technician, $training)) {
            // Échapper les données pour éviter les problèmes de sécurité
            $managerLastName = htmlspecialchars($manager['lastName']);
            $technicianFirstName = htmlspecialchars($technician['firstName']);
            $technicianLastName = htmlspecialchars($technician['lastName']);
            $trainingLabel = htmlspecialchars($training['label']);
            $trainingPlace = htmlspecialchars($training['place']);

            $message = '<p>Bonjour M. '.$managerLastName.',</p>
                    <p>Nous vous confirmons l\'inscription de votre collaborateur 
                    <strong>'.$technicianFirstName.' '.$technicianLastName.'</strong>
                        à la formation <strong>'.$trainingLabel.'</strong>, 
                        prévue du <strong>'.$date.'</strong> à <strong>'.$trainingPlace.'</strong>.<br><br>
                        Nous restons à votre disposition pour toute question ou information complémentaire.</p><br>
                    <p>Si vous souhaitez annuler son inscription à cette formation,
                        veuillez nous en informer en repondant à ce courriel.</p>                    
                    <p style="margin-top: 50px; font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いしま。</p>
                ';
            $subject = 'Confirmation d\'inscription à la formation '.$trainingLabel;         
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
        }
        
        $success_msg = 'Inscription confirmée';
    }

    if (isset($_POST['refuse'])) {
        $applicationId = $_POST['applicationID'];      

        $applications->updateOne(
            [
                "_id" => new MongoDB\BSON\ObjectId($applicationId), // ID de l'utilisateur
            ],
            [
                '$set' => [
                    'status' => 'Unconfirmed', // Mettre à jour le champ 'status'
                    'updated' => date("d-m-Y H:i:s") // Mettre à jour le champ 'updated'
                ]
            ]
        );
        
        $success_msg = 'Inscription refusée';
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_training ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo $list_techs_apply_training ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="<?php echo $recherche?>">
                    </div>
                    <!--end::Search-->
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <?php if (isset($success_msg)) { ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <center><strong><?php echo $success_msg; ?></strong></center>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php } ?>
        <?php if (isset($error_msg)) { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <center><strong><?php echo $error_msg; ?></strong></center>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php } ?>
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="w-10px pe-2 sorting_disabled" rowspan="1" colspan="1" aria-label=""
                                            style="width: 29.8906px;">
                                            <div
                                                class="form-check form-check-sm form-check-custom form-check-solid me-3">
                                                <input class="form-check-input" type="checkbox" data-kt-check="true"
                                                    data-kt-check-target="#kt_customers_table .form-check-input"
                                                    value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-150px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 150px;"><?php echo $prenomsNoms ?>
                                        </th>
                                        <th class="min-w-200px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 200px;"><?php echo $label_training ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Company: activate to sort column ascending"
                                            style="width: 125px;"><?php echo 'Manager' ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $Brand ?>
                                        </th>
                                        <th class="min-w-50px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 50px;"><?php echo $Level ?>
                                        </th>
                                        <th class="min-w-150px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 150px;"><?php echo $training_session ?>
                                        </th>
                                        <th class="min-w-100px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 100px;"><?php echo $status ?>
                                        </th>
                                        <?php if ($_SESSION['profile'] == 'Super Admin') { ?>
                                            <th class="min-w-50px sorting" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 50px;"><?php echo $confirm ?>
                                            </th>
                                            <th class="min-w-50px sorting" tabindex="0" aria-controls="kt_customers_table"
                                                rowspan="1" colspan="1"
                                                aria-label="Created Date: activate to sort column ascending"
                                                style="width: 50px;"><?php echo $refuse ?>
                                            </th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php foreach ($application as $index => $apply) { 
                                        $technician = $users->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId($apply['user']),
                                                    'active' => true
                                                ]
                                            ],
                                        ]);
                                        $manager = $users->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId($apply['manager']),
                                                    'active' => true
                                                ]
                                            ],
                                        ]);
                                        $training = $trainings->findOne([
                                            '$and' => [
                                                [
                                                    "_id" => new MongoDB\BSON\ObjectId($apply['training']),
                                                    'active' => true
                                                ]
                                            ],
                                        ]);
                                        ?>
                                    <tr class="odd" etat="<?php echo $apply->active; ?>">
                                        <td>
                                            <!-- <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                <input class="form-check-input" id="checkbox" type="checkbox"
                                                    onclick="enable()" value="<?php echo $apply->_id; ?>">
                                            </div> -->
                                        </td>
                                        <td data-filter=" search">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#kt_modal_add_customer"
                                                class="text-gray-800 text-hover-primary mb-1">
                                                <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                            </a>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $training->code.' : '.$training->label; ?>
                                        </td>
                                        <td data-filter="phone">
                                            <?php echo htmlspecialchars($manager['firstName'] . ' ' . $manager['lastName']); ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $training->brand; ?>
                                        </td>
                                        <td data-order="department">
                                            <?php if (
                                                $training->level == "Junior"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $training->level == "Senior"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $training->level == "Expert"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo $training->level; ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $apply->period.' - Lieu : '.$apply->place; ?>
                                        </td>
                                        <td data-order="department">
                                            <?php if (
                                                $apply->status == "Confirmed"
                                            ) { ?>
                                            <span class="badge badge-light-success fs-7 m-1">
                                                <?php echo "Confirmée" ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $apply->status == "Unconfirmed"
                                            ) { ?>
                                            <span class="badge badge-light-danger fs-7 m-1">
                                                <?php echo "Non confirmée" ?>
                                            </span>
                                            <?php } ?>
                                            <?php if (
                                                $apply->status == "Pending"
                                            ) { ?>
                                            <span class="badge badge-light-warning  fs-7 m-1">
                                                <?php echo "En attente" ?>
                                            </span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success w-30px h-30px me-3"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_confirm_technician<?php echo $index ?>" <?php if ($apply->status == "Confirmed") echo 'disabled' ?>>
                                                <i class="fas fa-thumbs-up fs-5"></i></button>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger w-30px h-30px"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_refuse_technician<?php echo $index ?>">
                                                <i class="fas fa-thumbs-down fs-5"></i></button>
                                        </td>
                                    </tr>
                                    <!-- begin:: Modal - Confirm application -->
                                    <div class="modal" id="kt_modal_confirm_technician<?php echo $index; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-550px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="applicationID"
                                                        value="<?php echo $apply['_id']; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $confirm_apply ?>
                                                        </h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16"
                                                                        height="2" rx="1"
                                                                        transform="rotate(-45 6 17.3137)"
                                                                        fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                        transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <h4>
                                                            <?php 
                                                                // Éléments à remplacer
                                                                $search = ['tech', 'training', 'period'];
                                                                $replace = [htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']), htmlspecialchars($training['label']), htmlspecialchars($apply['period'])];
                                                                echo str_replace($search, $replace, $confirm_apply_tech_training); ?>
                                                        </h4>
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            id="closeDesactivate" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $non ?>
                                                        </button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="confirm" class="btn btn-primary">
                                                            <?php echo $oui ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                        <!-- end Modal dialog -->
                                    </div>
                                    <!-- end:: Modal - Confirm application -->
                                    <!-- begin:: Modal - Refuse application -->
                                    <div class="modal" id="kt_modal_refuse_technician<?php echo $index; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-550px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="applicationID"
                                                        value="<?php echo $apply['_id']; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $refuse_apply ?>
                                                        </h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16"
                                                                        height="2" rx="1"
                                                                        transform="rotate(-45 6 17.3137)"
                                                                        fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                        transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <!--begin::Input group-->
                                                        <h4>
                                                            <?php 
                                                                // Éléments à remplacer
                                                                $search = ['tech', 'training', 'period'];
                                                                $replace = [htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']), htmlspecialchars($training['label']), htmlspecialchars($apply['period'])];
                                                                echo str_replace($search, $replace, $refuse_apply_tech_training); ?>
                                                        </h4>
                                                        <!--end::Input-->
                                                        <br>
                                                        <!--begin::Input group-->
                                                        <div class='d-flex flex-column mb-7 fv-row'>
                                                            <!--begin::Label-->
                                                            <label class='form-label fw-bolder text-dark fs-6'>
                                                                <span class='required'><?php echo 'Motif du refus' ?></span>
                                                                </span>
                                                            </label>
                                                            <!--end::Label-->
                                                            <!--begin::Input-->
                                                            <select id="reasonValue" name="reason" onchange="autreReason()" class="form-select form-select-solid fw-bold" required>
                                                                <option value="" disabled selected>-- Sélectionnez un motif --</option>
                                                                <option value="<?php echo htmlspecialchars($training_annuler); ?>">
                                                                    <?php echo htmlspecialchars($training_annuler); ?>
                                                                </option>
                                                                <option value="<?php echo htmlspecialchars($training_done); ?>">
                                                                    <?php echo htmlspecialchars($training_done); ?>
                                                                </option>
                                                                <option value="<?php echo htmlspecialchars($training_report); ?>">
                                                                    <?php echo htmlspecialchars($training_report); ?>
                                                                </option>
                                                                <option value="<?php echo htmlspecialchars($autres); ?>">
                                                                    <?php echo htmlspecialchars($autres); ?>
                                                                </option>
                                                            </select>
                                                            <!--end::Input-->
                                                        </div>  
                                                        <!--end::Input group-->
                                                        <!--begin::Input group-->
                                                        <div id="customInput" class="d-none flex-column mb-7 fv-row">
                                                            <!--begin::Label-->
                                                            <label class="form-label fw-bolder text-dark fs-6">
                                                                <span class="required"><?php echo 'Autres raisons' ?></span>
                                                            </label>
                                                            <!--end::Label-->
                                                            <!--begin::Input-->
                                                            <input type='text' class='form-control form-control-solid'style="background-color:#f5f5f5" placeholder='' name='reason'/>
                                                            <!--end::Input-->
                                                        </div>
                                                        <!--end::Input group-->
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            id="closeDesactivate" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $non ?>
                                                        </button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="refuse" class="btn btn-danger">
                                                            <?php echo $oui ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                        <!-- end Modal dialog -->
                                    </div>
                                    <!-- end:: Modal - Refuse application -->
                                    <?php }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label><select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-select form-select-sm form-select-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select></label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->
            <!--begin::Export dropdown-->
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <!--begin::Export-->
                <button type="button" id="excel" class="btn btn-light-danger me-3" data-bs-toggle="modal"
                    data-bs-target="#kt_customers_export_modal">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $excel ?>
                </button>
                <!--end::Export-->
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
</script>
<script src="../../public/js/main.js"></script>
<script>
    $(document).ready(function() {
        $("#excel").on("click", function() {
            let table = document.getElementsByTagName("table");
            debugger;
            TableToExcel.convert(table[0], {
                name: `Applications.xlsx`
            })
        });
    });

    function autreReason() {
        const reason = document.querySelector('#reasonValue').value;
        const customInput = document.getElementById("customInput");

        console.log(reason); // Affiche la valeur sélectionnée dans la console

        // Vérifiez si l'option sélectionnée est "Autres"
        if (reason === '<?php echo htmlspecialchars($autres); ?>') {
            customInput.classList.remove('d-none'); // Affiche le champ d'entrée personnalisé
            customInput.classList.add('d-flex'); // Ajoute la classe d-flex
        } else {
            customInput.classList.remove('d-flex'); // Masque le champ d'entrée personnalisé
            customInput.classList.add('d-none'); // Ajoute la classe d-none
        }
    }
</script>  
<?php include_once "partials/footer.php"; ?>
<?php } ?>
