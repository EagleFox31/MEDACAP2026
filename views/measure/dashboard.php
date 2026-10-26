<?php
require_once __DIR__ . '/../../core/medacap_config.php';
    session_start();
    include_once "../language.php";

    if (!isset($_SESSION["id"])) {
        header("Location: ../../");
        exit();
    } else {
        require_once "../../vendor/autoload.php";
        // Create connection
        $conn = new MongoDB\Client(medacapMongoUri());

        // Connecting in database
        $academy = $conn->academy;
        
        $users = $academy->users;
        $allocations = $academy->allocations;

        $objId = new MongoDB\BSON\ObjectId($_SESSION["id"]);

        if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == false) {
            $manager = $users->findOne([
                "_id" => $objId,
                "active" => true
            ]);

            $technicians = $manager['users'];
            
            // Fonction pour recupérer les affectations terminés
            function getAllocation($allocations, $user, $level, $type) {
                $query = [
                    'user' => $user,
                    'type' => $type,
                    'level' => $level
                ];
                return $allocations->find(['$and' => [$query]]);
            }

            function getAllocationByUser ($allocations, $users, $level) {
                $response = [];
                $declaratifDone = [];
                $declaratifPending = [];
                $declaratifTotal = [];

                foreach ($users as $user) {

                    $declaratifs = getAllocation($allocations, new MongoDB\BSON\ObjectId($user), $level, 'Declaratif');
        
                    foreach($declaratifs as $declaratif) {
                        $declaratifTotal[] = $declaratif;
                        if ($declaratif['activeManager'] == true) {
                            $declaratifDone[] = $declaratif;
                        } else {
                            $declaratifPending[] = $declaratif;
                        }
                    }
                }

                $response = [
                    "declaratifDone" => count($declaratifDone),
                    "declaratifPending" => count($declaratifPending),
                    "declaratifTotal" => count($declaratifTotal),
                ];

                return $response;
            }

            $resultJu = getAllocationByUser ($allocations, $technicians, 'Junior');
            $resultSe = getAllocationByUser ($allocations, $technicians, 'Senior');
            $resultEx = getAllocationByUser ($allocations, $technicians, 'Expert');
        } else if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == true){
            $manager = $users->findOne([
                "_id" => $objId,
                "active" => true
            ]);

            $technicians = $manager['users'];
            
            // Fonction pour recupérer les affectations terminés
            function getAllocationManager($allocations, $user, $level, $type) {
                $query = [
                    'user' => $user,
                    'type' => $type,
                    'level' => $level
                ];
                return $allocations->find(['$and' => [$query]]);
            }

            function getAllocationByUsers ($allocations, $users, $level) {
                $response = [];
                $declaratifDone = [];
                $declaratifPending = [];
                $declaratifTotal = [];

                foreach ($users as $user) {

                    $declaratifs = getAllocationManager($allocations, new MongoDB\BSON\ObjectId($user), $level, 'Declaratif');
        
                    foreach($declaratifs as $declaratif) {
                        $declaratifTotal[] = $declaratif;
                        if ($declaratif['activeManager'] == true) {
                            $declaratifDone[] = $declaratif;
                        } else {
                            $declaratifPending[] = $declaratif;
                        }
                    }
                }

                $response = [
                    "declaratifDone" => count($declaratifDone),
                    "declaratifPending" => count($declaratifPending),
                    "declaratifTotal" => count($declaratifTotal),
                ];

                return $response;
            }

            $resultJu = getAllocationByUsers ($allocations, $technicians, 'Junior');
            $resultSe = getAllocationByUsers ($allocations, $technicians, 'Senior');
            $resultEx = getAllocationByUsers ($allocations, $technicians, 'Expert');
            
            // Fonction pour recupérer les affectations terminés
            function getAllocationTech($allocations, $user, $type) {
                $query = [
                    'user' => $user,
                    'type' => $type,
                ];
                return $allocations->find(['$and' => [$query]]);
            }

            function getAllocationByUser ($allocations, $user) {
                $response = [];
                $factuelDone = [];
                $factuelPending = [];
                $factuelTotal = [];
                $declaratifDone = [];
                $declaratifPending = [];
                $declaratifTotal = [];

                $factuels = getAllocationTech($allocations, $user, 'Factuel');
                $declaratifs = getAllocationTech($allocations, $user, 'Declaratif');
    
                foreach($factuels as $factuel) {
                    $factuelTotal[] = $factuel;
                    if ($factuel['active'] == true) {
                        $factuelDone[] = $factuel;
                    } else {
                        $factuelPending[] = $factuel;
                    }
                }
                foreach($declaratifs as $declaratif) {
                    $declaratifTotal[] = $declaratif;
                    if ($declaratif['active'] == true) {
                        $declaratifDone[] = $declaratif;
                    } else {
                        $declaratifPending[] = $declaratif;
                    }
                }

                $response = [
                    "factuelDone" => count($factuelDone),
                    "factuelPending" => count($factuelPending),
                    "factuelTotal" => count($factuelTotal),
                    "declaratifDone" => count($declaratifDone),
                    "declaratifPending" => count($declaratifPending),
                    "declaratifTotal" => count($declaratifTotal),
                ];

                return $response;
            }

            $results = getAllocationByUser ($allocations, $objId);
        } else {
            // Fonction pour recupérer les affectations terminés
            function getAllocation($allocations, $user, $type) {
                $query = [
                    'user' => $user,
                    'type' => $type,
                ];
                return $allocations->find(['$and' => [$query]]);
            }

            function getAllocationByUser ($allocations, $user) {
                $response = [];
                $factuelDone = [];
                $factuelPending = [];
                $factuelTotal = [];
                $declaratifDone = [];
                $declaratifPending = [];
                $declaratifTotal = [];

                $factuels = getAllocation($allocations, $user, 'Factuel');
                $declaratifs = getAllocation($allocations, $user, 'Declaratif');
    
                foreach($factuels as $factuel) {
                    $factuelTotal[] = $factuel;
                    if ($factuel['active'] == true) {
                        $factuelDone[] = $factuel;
                    } else {
                        $factuelPending[] = $factuel;
                    }
                }
                foreach($declaratifs as $declaratif) {
                    $declaratifTotal[] = $declaratif;
                    if ($declaratif['active'] == true) {
                        $declaratifDone[] = $declaratif;
                    } else {
                        $declaratifPending[] = $declaratif;
                    }
                }

                $response = [
                    "factuelDone" => count($factuelDone),
                    "factuelPending" => count($factuelPending),
                    "factuelTotal" => count($factuelTotal),
                    "declaratifDone" => count($declaratifDone),
                    "declaratifPending" => count($declaratifPending),
                    "declaratifTotal" => count($declaratifTotal),
                ];

                return $response;
            }

            $results = getAllocationByUser ($allocations, $objId);
        }
?>
<?php include "./partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $participation_aux_tests ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php include_once "../partials/background-manager.php"; ?>
<?php setPageBackground("bg-dashboard"); ?>

<!--begin::Content-->
<?php openBackgroundContainer(); ?>
    <!-- Main Title Card -->
    <div class="container-xxl">
       <div class="card shadow-sm mb-5">
            <div class="card-body p-4">
                <h1 class="text-dark fw-bold text-center fs-1">
                    <?php echo strtoupper($participation_aux_tests) ?>
                </h1>
            </div>
        </div>
    </div>
    <?php if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == false) { ?>
        <!--begin::Content-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="margin-top: -10px; background-color: transparent;">
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class="container-xxl">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9" style="background: transparent; border: none; box-shadow: none;">
                        
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $resultJu['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_junior ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $resultSe['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_senior ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $resultEx['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_expert ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $resultJu['declaratifTotal']  + $resultSe['declaratifTotal']+ $resultEx['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_tache_pro_manager_total  ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>

                        <br>
                        <br>
                        <!-- Secondary Title Card with Glassmorphism -->
                        <div class="container-xxl mb-5">
                            <div class="card border-0" style="background-color: white; margin-top: 40px;">
                                <div class="card-body p-4">
                                    <h2 class="text-dark fw-bold text-center fs-2">
                                        <?php echo $etat_avancement_qcm_tache_pro_manager ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <br> 
                        <br>   
                        <!-- begin::Row -->
                        <div id="chartQCM" class="row" style="margin-top: 40px;">
                            <!-- Dynamic cards will be appended here -->
                        </div>
                        <!-- endr::Row -->
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } else if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == true) { ?>
        <!--begin::Content-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="margin-top: -10px; background-color: transparent;">
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class="container-xxl">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9" style="background: transparent; border: none; box-shadow: none;">
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $results['factuelTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_connaissance ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $results['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_tache_pro_tech ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $resultJu['declaratifTotal'] + $resultSe['declaratifTotal'] + $resultEx['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_tache_pro_manager ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <br>
                        <br>
                        <!--end::Col-->
                        <!-- Secondary Title Card with Glassmorphism -->
                        <div class="container-xxl mb-5">
                            <div class="card border-0" style="background-color: white; margin-top: 40px;">
                                <div class="card-body p-4">
                                    <h2 class="text-dark fw-bold text-center fs-2">
                                        <?php echo $etat_avancement_qcm ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <br>
                        <br>
                        <!-- begin::Row -->
                        <div id="chartQCM" class="row" style="margin-top: 40px;">
                            <!-- Dynamic cards will be appended here -->
                        </div>
                        <!-- endr::Row -->
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } else{ ?>
        <!--begin::Content-->
        <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content" style="margin-top: -10px; background-color: transparent;">
            <!--begin::Post-->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <!--begin::Container-->
                <div class="container-xxl">
                    <!--begin::Row-->
                    <div class="row g-6 g-xl-9 mb-6 mb-xl-9" style="background: transparent; border: none; box-shadow: none;">
                        <!--begin::Toolbar-->
                        <!-- <div class="toolbar" id="kt_toolbar" style="margin-top: 20px; margin-bottom: -30px"> -->
                            <!-- <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap"> -->
                                <!--begin::Info-->
                                <!-- <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2"> -->
                                    <!--begin::Title-->
                                    <!-- <h1 class="text-dark fw-bold my-1 fs-2">
                                        <?php echo 'Nombre de QCM' ?>
                                    </h1> -->
                                    <!--end::Title-->
                                <!-- </div> -->
                                <!--end::Info-->
                            <!-- </div> -->
                        <!-- </div> -->
                        <!--end::Toolbar-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $results['factuelTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_connaissance ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo $results['declaratifTotal'] ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_qcm_tache_pro ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-md-6 col-lg-4 col-xl-4">
                            <!--begin::Card-->
                            <div class="card h-100 ">
                                <!--begin::Card body-->
                                <div class="card-body d-flex justify-content-center text-center flex-column p-8">
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div
                                        class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-70px" data-kt-countup="true"
                                            data-kt-countup-value="<?php echo (($results['factuelTotal'] + $results['declaratifTotal']) ) ?>">
                                        </div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-5 fw-bold mb-2">
                                        <?php echo $nbre_test ?> </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                                <!--end::Card body-->
                            </div>
                            <!--end::Card-->
                        </div>
                        <!--end::Col-->
                        <br>
                        <br>
                        <!-- Secondary Title Card with Glassmorphism -->
                        <div class="container-xxl mb-5">
                            <div class="card border-0" style="background-color: white; margin-top: 40px;">
                                <div class="card-body p-4">
                                    <h2 class="text-dark fw-bold text-center fs-2">
                                        <?php echo $etat_avancement_qcm ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <br>
                        <br>
                        <!-- begin::Row -->
                        <div id="chartQCM" class="row" style="margin-top: 40px;">
                            <!-- Dynamic cards will be appended here -->
                        </div>
                        <!-- endr::Row -->
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Container-->
            </div>
            <!--end::Post-->
        </div>
        <!--end::Content-->
    <?php } ?>
<?php closeBackgroundContainer(); ?>
<!--end::Content-->
<?php include "./partials/footer.php"; ?>

<!-- <script>
    const results = <?php echo json_encode(isset($results) ? $results : null, JSON_HEX_APOS | JSON_HEX_QUOT); ?>

    console.log('results', results);
</script> -->

<?php if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == false) { ?>
    <script>
        // Graphiques pour les resultats du groupe
        document.addEventListener('DOMContentLoaded', function() {
            function addIfZero(done, total) {
                let percentage;
                if (total == 0) {
                    percentage = (done * 100) / 1;
                } else {
                    percentage = (done * 100) / total;
                }
                return Math.round(percentage);
            }
            
            // Calculate percentages
            let percentageJu = addIfZero(<?php echo $resultJu['declaratifDone'] ?>, <?php echo $resultJu['declaratifTotal'] ?>);
            let percentageSe = addIfZero(<?php echo $resultSe['declaratifDone'] ?>, <?php echo $resultSe['declaratifTotal'] ?>);
            let percentageEx = addIfZero(<?php echo $resultEx['declaratifDone'] ?>, <?php echo $resultEx['declaratifTotal'] ?>);

            // Data for each chart
           // --- bloc chartData internationalisé ---
            const chartData = [
                /* JUNIOR */
                {
                    title: `<?php
                        echo sprintf(
                            '%s %d / %d QCM',
                            $nbre_qcm_junior,
                            $resultJu['declaratifDone'],
                            $resultJu['declaratifTotal']
                        );
                    ?>`,
                    total: 100,
                    completed: percentageJu,
                    data: [percentageJu, 100 - percentageJu],
                    labels: [
                        percentageJu + ' <?php echo $tache_pro_junior_realise; ?>',
                        (100 - percentageJu) + ' <?php echo $tache_pro_junior_a_realiser; ?>'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },

                /* SENIOR */
                {
                    title: `<?php
                        echo sprintf(
                            '%s %d / %d QCM',
                            $nbre_qcm_senior,
                            $resultSe['declaratifDone'],
                            $resultSe['declaratifTotal']
                        );
                    ?>`,
                    total: 100,
                    completed: percentageSe,
                    data: [percentageSe, 100 - percentageSe],
                    labels: [
                        percentageSe + ' <?php echo $tache_pro_senior_realise; ?>',
                        (100 - percentageSe) + ' <?php echo $tache_pro_senior_a_realiser; ?>'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                },

                /* EXPERT */
                {
                    title: `<?php
                        echo sprintf(
                            '%s %d / %d QCM',
                            $nbre_qcm_expert,
                            $resultEx['declaratifDone'],
                            $resultEx['declaratifTotal']
                        );
                    ?>`,
                    total: 100,
                    completed: percentageEx,
                    data: [percentageEx, 100 - percentageEx],
                    labels: [
                        percentageEx + ' <?php echo $tache_pro_expert_realise; ?>',
                        (100 - percentageEx) + ' <?php echo $tache_pro_expert_a_realiser; ?>'
                    ],
                    backgroundColor: ['#4303ec', '#D3D3D3']
                }
            ];

            
            // Calculate the average for "Total : 03 Niveaux" based on non-zero values
            const validData = chartData.filter(chart => chart.completed > 0);
            const averageCompleted = validData.length > 0 ?
                Math.round(validData.reduce((acc, chart) => acc + chart.completed, 0) / validData.length) :
                0;
            const averageData = [averageCompleted, 100 - averageCompleted];
            
            chartData.push({
                /* ----- titre : “Nombre de QCM Tâches Professionnelles Managers …” ----- */
                title: `<?php
                    echo sprintf(
                        '%s %d / %d QCM',
                        $nbre_qcm_tache_pro_manager,                                   // libellé traduit
                        $resultJu['declaratifDone'] + $resultSe['declaratifDone'] + $resultEx['declaratifDone'],
                        $resultJu['declaratifTotal'] + $resultSe['declaratifTotal'] + $resultEx['declaratifTotal']
                    );
                ?>`,

                total: 100,
                completed: averageCompleted,
                data: averageData,

                /* ----- libellés des parts du doughnut ----- */
                labels: [
                    `${averageCompleted} <?php echo $tache_pro_tous_niveaux_realise; ?>`,
                    `${100 - averageCompleted} <?php echo $tache_pro_tous_niveaux_a_realiser; ?>`
                ],

                backgroundColor: ['#4303ec', '#D3D3D3']
            });

        
            const container = document.getElementById('chartQCM');
        
            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                const completedPercentage = Math.round((data.completed / data.total) * 100);
        
                // Create the card element
                const cardHtml = `
                        <div class="col-md-6 col-lg-3 col-xl-3 mb-4">
                            <div class="card h-100 glass-effect-chart depth-effect">
                                <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                    <h5 class="mb-4">${data.title}</h5>
                                    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                        <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                        <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

        
                // Append the card to the container
                container.insertAdjacentHTML('beforeend', cardHtml);
                // Initialize the Chart.js doughnut chart
                new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Data',
                            data: data.data,
                            backgroundColor: data.backgroundColor,
                            borderColor: data.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            datalabels: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (tooltipItem) {
                                        const value    = tooltipItem.raw || 0;
                                        const dataset  = tooltipItem.dataset.data;
                                        const sum      = dataset.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / sum) * 100);

                                        /* -------- libellé internationalisé -------- */
                                        return `<?php echo $pourcentages ; ?> : ${percentage}%`;
                                    }
                                }
                            }

                        }
                    }
                });
            });
        });
    </script>
    <?php } else if ($_SESSION["profile"] == "Manager" && $_SESSION["test"] == true) { ?>
    <script>
        // Graphiques pour les resultats du groupe
        document.addEventListener('DOMContentLoaded', function() {
            function addIfZero(done, total) {
                let percentage;
                if (total == 0) {
                    percentage = (done * 100) / 1;
                } else {
                    percentage = (done * 100) / total;
                }
                return Math.round(percentage);
            }
            
            // Calculate percentages
            let percentageJu = addIfZero(<?php echo $resultJu['declaratifDone'] ?>, <?php echo $resultJu['declaratifTotal'] ?>);
            let percentageSe = addIfZero(<?php echo $resultSe['declaratifDone'] ?>, <?php echo $resultSe['declaratifTotal'] ?>);
            let percentageEx = addIfZero(<?php echo $resultEx['declaratifDone'] ?>, <?php echo $resultEx['declaratifTotal'] ?>);

            const datas = [
                {
                    completed: percentageJu
                }, {
                    completed: percentageSe
                }, {
                    completed: percentageEx
                }
            ];

            // Data for each chart
        /* === Tableau des jeux de données === */
        const chartData = [
            /* 1️⃣  QCM CONNAISSANCES */
            {
                title: `<?php
                    echo sprintf(
                        '%s %d / %d QCM',
                        $nbre_qcm_connaissance,       // libellé traduit
                        $results['factuelDone'],
                        $results['factuelTotal']
                    );
                ?>`,
                total: 100,
                completed: <?php
                    echo $results['factuelTotal'] > 0
                        ? round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                        : 0;
                ?>,
                data: [
                    <?php
                        echo $results['factuelTotal'] > 0
                            ? round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                            : 0;
                    ?>,
                    <?php
                        echo $results['factuelTotal'] > 0
                            ? 100 - round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                            : 0;
                    ?>
                ],
                labels: [
                    `${<?php echo $results['factuelTotal'] > 0
                                ? round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                                : 0; ?>} <?php echo $qcm_connaissance_realise; ?>`,
                    `${<?php echo $results['factuelTotal'] > 0
                                ? 100 - round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                                : 0; ?>} <?php echo $qcm_connaissance_a_realiser; ?>`
                ],
                backgroundColor: ['#4303ec', '#D3D3D3']
            },

            /* 2️⃣  QCM TÂCHES PROFESSIONNELLES – TECHNICIENS */
            {
                title: `<?php
                    echo sprintf(
                        '%s %d / %d QCM',
                        $nbre_qcm_tache_pro_tech,     // libellé traduit
                        $results['declaratifDone'],
                        $results['declaratifTotal']
                    );
                ?>`,
                total: 100,
                completed: <?php
                    echo $results['declaratifTotal'] > 0
                        ? round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                        : 0;
                ?>,
                data: [
                    <?php
                        echo $results['declaratifTotal'] > 0
                            ? round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                            : 0;
                    ?>,
                    <?php
                        echo $results['declaratifTotal'] > 0
                            ? 100 - round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                            : 0;
                    ?>
                ],
                labels: [
                    `${<?php echo $results['declaratifTotal'] > 0
                                ? round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                                : 0; ?>} <?php echo $tache_pro_realise; ?>`,
                    `${<?php echo $results['declaratifTotal'] > 0
                                ? 100 - round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                                : 0; ?>} <?php echo $tache_pro_a_realiser; ?>`
                ],
                backgroundColor: ['#4303ec', '#D3D3D3']
            }
        ];

            
            // Calculate the average for "Total : 03 Niveaux" based on non-zero values
            const validData = datas.filter(chart => chart.completed > 0);
            const averageCompleted = validData.length > 0 ?
                Math.round(validData.reduce((acc, chart) => acc + chart.completed, 0) / validData.length) :
                0;
            const averageData = [averageCompleted, 100 - averageCompleted];
            
           chartData.push({
            /* ---- Titre : QCM TP managers (tous niveaux) ---- */
            title: `<?php
                echo sprintf(
                    '%s %d / %d QCM',
                    $nbre_qcm_tache_pro_manager,
                    $resultJu['declaratifDone'] + $resultSe['declaratifDone'] + $resultEx['declaratifDone'],
                    $resultJu['declaratifTotal'] + $resultSe['declaratifTotal'] + $resultEx['declaratifTotal']
                );
            ?>`,

            total: 100,
            completed: averageCompleted,
            data: averageData,

            /* ---- Libellés du doughnut ---- */
            labels: [
                `${averageCompleted} <?php echo $tache_pro_manager_realise; ?>`,
                `${100 - averageCompleted} <?php echo $tache_pro_manager_a_realiser; ?>`
            ],

            backgroundColor: ['#4303ec', '#D3D3D3']
        });

        
            const container = document.getElementById('chartQCM');
        
            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                const completedPercentage = Math.round((data.completed / data.total) * 100);
        
                // Create the card element
                const cardHtml = `
            <div class="col-md-6 col-lg-4 col-xl-4 mb-4">
                <div class="card h-100 glass-effect-chart depth-effect">
                    <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                        <h5 class="mb-4">${data.title}</h5>
                        <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                            <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                            <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        
                // Append the card to the container
                container.insertAdjacentHTML('beforeend', cardHtml);
                // Initialize the Chart.js doughnut chart
                new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Data',
                            data: data.data,
                            backgroundColor: data.backgroundColor,
                            borderColor: data.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            datalabels: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(tooltipItem) {
                                        const value = tooltipItem.raw || 0;
                                        const dataset = tooltipItem.dataset.data;
                                        let sum = dataset.reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return `<?php echo $pourcentages ; ?> : ${percentage}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });
    </script>
<?php } else { ?>
    <script>
         // Graphiques pour les resultats du groupe
        document.addEventListener('DOMContentLoaded', function() {
            function addIfZero(done, total) {
                let percentage;
                if (total == 0) {
                    percentage = (done * 100) / 1;
                } else {
                    percentage = (done * 100) / total;
                }
                return Math.round(percentage);
            }
            
            // Calculate percentages
            let factuel = addIfZero(<?php echo $results['factuelDone'] ?>, <?php echo $results['factuelTotal'] ?>);
            let declaratif = addIfZero(<?php echo $results['declaratifDone'] ?>, <?php echo $results['declaratifTotal'] ?>);

            // Data for each chart
           /* === Données pour le tableau de bord (profil Tech / RH / …) === */
const chartData = [

    /* 1️⃣ QCM CONNAISSANCES */
    {
        /* ---- Titre ---- */
        title: `<?php
            echo sprintf(
                '%s %d / %d QCM',
                $nbre_qcm_connaissance,            // libellé traduit
                $results['factuelDone'],
                $results['factuelTotal']
            );
        ?>`,

        total: 100,

        /* -- % réalisé -- */
        completed: <?php
            echo $results['factuelTotal'] > 0
                ? round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                : 0;
        ?>,

        /* -- données pour le doughnut -- */
        data: [
            <?php
                echo $results['factuelTotal'] > 0
                    ? round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                    : 0;
            ?>,
            <?php
                echo $results['factuelTotal'] > 0
                    ? 100 - round(($results['factuelDone'] * 100) / $results['factuelTotal'])
                    : 0;
            ?>
        ],

        /* -- libellés du doughnut -- */
        labels: [
            `<?php echo $qcm_connaissance_realise; ?>`,
            `<?php echo $qcm_connaissance_a_realiser; ?>`
        ],

        backgroundColor: ['#4303ec', '#D3D3D3']
    },

    /* 2️⃣ QCM TÂCHES PROFESSIONNELLES (Tech) */
    {
        title: `<?php
            echo sprintf(
                '%s %d / %d QCM',
                $nbre_qcm_tache_pro,               // libellé traduit
                $results['declaratifDone'],
                $results['declaratifTotal']
            );
        ?>`,

        total: 100,

        completed: <?php
            echo $results['declaratifTotal'] > 0
                ? round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                : 0;
        ?>,

        data: [
            <?php
                echo $results['declaratifTotal'] > 0
                    ? round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                    : 0;
            ?>,
            <?php
                echo $results['declaratifTotal'] > 0
                    ? 100 - round(($results['declaratifDone'] * 100) / $results['declaratifTotal'])
                    : 0;
            ?>
        ],

        labels: [
            `<?php echo $tache_pro_realise; ?>`,
            `<?php echo $tache_pro_a_realiser; ?>`
        ],

        backgroundColor: ['#4303ec', '#D3D3D3']
    },

    /* 3️⃣ TOTAL TESTS */
    {
        title: `<?php
            echo sprintf(
                '%s %d / %d QCM',
                $nbre_test,               // libellé traduit
                $results['factuelDone'] + $results['declaratifDone'],
                $results['factuelTotal'] + $results['declaratifTotal']
            );
        ?>`,

        total: 100,

        completed: <?php
            $totalDone = $results['factuelDone'] + $results['declaratifDone'];
            $totalTotal = $results['factuelTotal'] + $results['declaratifTotal'];
            echo $totalTotal > 0
                ? round(($totalDone * 100) / $totalTotal)
                : 0;
        ?>,

        data: [
            <?php
                $totalDone = $results['factuelDone'] + $results['declaratifDone'];
                $totalTotal = $results['factuelTotal'] + $results['declaratifTotal'];
                echo $totalTotal > 0
                    ? round(($totalDone * 100) / $totalTotal)
                    : 0;
            ?>,
            <?php
                $totalDone = $results['factuelDone'] + $results['declaratifDone'];
                $totalTotal = $results['factuelTotal'] + $results['declaratifTotal'];
                echo $totalTotal > 0
                    ? 100 - round(($totalDone * 100) / $totalTotal)
                    : 0;
            ?>
        ],

        labels: [
            `<?php echo $tests_completes; ?>`,
            `<?php echo $tests_restants_completer; ?>`
        ],

        backgroundColor: ['#4303ec', '#D3D3D3']
    }
];

        
            const container = document.getElementById('chartQCM');
        
            // Loop through the data to create and append cards
            chartData.forEach((data, index) => {
                // Calculate the completed percentage
                const completedPercentage = Math.round((data.completed / data.total) * 100);
        
                // Create the card element
                const cardHtml = `
                    <div class="col-md-6 col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 glass-effect-chart depth-effect">
                            <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                <h5 class="mb-4">${data.title}</h5>
                                <div style="position: relative; display: flex; justify-content: center; align-items: center;">
                                    <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                                    <div style="position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                        <span class="fs-2hx fw-bold text-dark">${completedPercentage}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
        
                // Append the card to the container
                container.insertAdjacentHTML('beforeend', cardHtml);
                // Initialize the Chart.js doughnut chart
                new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Data',
                            data: data.data,
                            backgroundColor: data.backgroundColor,
                            borderColor: data.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            datalabels: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(tooltipItem) {
                                        const value = tooltipItem.raw || 0;
                                        const dataset = tooltipItem.dataset.data;
                                        let sum = dataset.reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / sum) * 100);
                                        // Round up to the nearest whole number
                                        return `<?php echo $pourcentages ; ?>: ${percentage}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });
    </script>
<?php } ?>
<?php } ?>