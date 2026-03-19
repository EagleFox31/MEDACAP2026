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
    $results = $academy->results;
    $seuils = $academy->seuils;

    $user = $_GET["user"];
    $levelfilter = $_GET["level"];
    $numberTest = $_GET["numberTest"];

    $validate = $seuils->findOne([ "active" => true ]);

    if ($levelfilter == 'Junior') {
        $seuil = $validate['qcmJunior'];
    }
    if ($levelfilter == 'Senior') {
        $seuil = $validate['qcmSenior'];
    }
    if ($levelfilter == 'Expert') {
        $seuil = $validate['qcmExpert'];
    }

    $technician = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($user),
                "active" => true,
            ],
        ],
    ]);
    $resultFac = $results->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user)],
            ["type" => "Factuel"],
            ["typeR" => "Technicien"],
            ["level" => $levelfilter],
            ["numberTest" => +$numberTest],
            ["active" => true],
        ],
    ]);
    $resultDecla = $results->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user)],
            ["type" => "Declaratif"],
            ["typeR" => "Techniciens"],
            ["level" => $levelfilter],
            ["numberTest" => +$numberTest],
            ["active" => true],
        ],
    ]);
    $resultMa = $results->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user)],
            ["manager" => new MongoDB\BSON\ObjectId($technician->manager)],
            ["typeR" => "Managers"],
            ["level" => $levelfilter],
            ["numberTest" => +$numberTest],
            ["active" => true],
        ],
    ]);
    $resultTechMa = $results->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user)],
            ["manager" => new MongoDB\BSON\ObjectId($technician->manager)],
            ["typeR" => "Technicien - Manager"],
            ["level" => $levelfilter],
            ["numberTest" => +$numberTest],
            ["active" => true],
        ],
    ]);
    $percentageFac = ($resultFac['score'] * 100) / $resultFac['total'];
    $percentageTechMa = ($resultTechMa['score'] * 100) / $resultTechMa['total'];

    function getBootstrapClass($pourcentage) {
        if ($pourcentage <= 59) {
            return 'text-danger'; 
        } elseif ($pourcentage <= 79) {
            return 'text-warning';
        } else {
            return 'text-success'; 
        }
    }

    function getclasses($grp){
        $percent  = round(($grp->score * 100) / ($grp->total));
        $bootstrapclass = getBootstrapClass($percent);
        return $bootstrapclass;                                    
    }
?>

<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $result_tech ?> | CFAO Mobility Academy</title>
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
                <h1 class="text-dark fw-bold my-1" style="font-size: 30px;">
                    <?php echo $result.' du Niveau '.$_GET['level'].' de' ?>
                    <?php echo $technician->firstName; ?> <?php echo $technician->lastName; ?>
                </h1>
                <!--end::Title-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card header-->
                <div class="card-header border-0 pt-6">
                    <!--begin::Card title-->
                    <div class="card-title">
                    </div>
                    <!--begin::Card title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar">
                        <!--begin::Toolbar-->
                        <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                            <?php if($_SESSION['profile'] == "Super Admin") { ?>
                                <!--begin::Actions-->
                                <a class="btn btn-light"
                                    href="./detail.php?numberTest=<?php echo $numberTest; ?>&id=<?php echo $technician->_id; ?>&level=<?php echo $levelfilter; ?>"
                                    role="button">
                                   <button type="button" class="btn btn-light text-black me-3">
                                        <?php echo $result_detaillé ?>
                                    </button>
                                </a>
                                <a class="btn btn-light" style="margin-left: 10px"
                                    href="./brandResult.php?numberTest=<?php echo $numberTest; ?>&id=<?php echo $technician->_id; ?>&level=<?php echo $levelfilter; ?>"
                                    role="button">
                                    <button type="button" class="btn btn-light text-black me-3">
                                        <?php echo $result_brand ?>
                                    </button>
                                </a>
                            <!--end::Actions-->
                            <?php } ?>
                        </div>
                        <!--end::Toolbar-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--end::Card header-->
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered table-row-dashed fs-7 gy-3 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <th style=" position: sticky; left: 0; z-index: 2;" class="min-w-10px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $groupe_fonctionnel ?></th>
                                    <th class="min-w-120px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" 
                                        aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        <?php echo $qcm_connaissances_tech ?></th>
                                    <th class="min-w-120px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        <?php echo $qcm_tache_pro_tech ?></th>
                                    <th class="min-w-120px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        <?php echo $qcm_tache_pro_manager ?></th>
                                    <th class="sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $result ?></th>
                                    <th class="sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $fiabilite ?></th>
                                    <th class="min-w-100px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        <?php echo $result.' '.$groupe_fonctionnel ?></th>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                $transmissionFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Arbre de Transmission",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transmissionDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Arbre de Transmission",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transmissionMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Arbre de Transmission",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $transmissionFac &&
                                    $transmissionDecla &&
                                    $transmissionMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $transmissionFac->speciality; ?>&level=<?php echo $transmissionFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $arbre ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($transmissionFac); ?>">
                                            <?php 
                                                $percentTransmissionFac = round(($transmissionFac->score * 100) / $transmissionFac->total);
                                                echo $percentTransmissionFac?>%
                                        </td>

                                        <td class="text-center <?php echo getclasses($transmissionDecla); ?>">
                                            <?php echo round(
                                            ($transmissionDecla->score * 100) /
                                                $transmissionDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($transmissionMa); ?>">
                                            <?php echo round(
                                            ($transmissionMa->score * 100) /
                                                $transmissionMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($transmissionDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $transmissionDecla->answers[$i] ==
                                            "Oui" &&
                                        $transmissionMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransmission">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transmissionDecla->answers[$i] ==
                                            "Non" &&
                                        $transmissionMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransmission">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transmissionDecla->answers[$i] !=
                                        $transmissionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransmission">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transmissionDecla->answers[$i] ==
                                        $transmissionMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransmission">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transmissionDecla->answers[$i] !=
                                        $transmissionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransmission">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfTransmission">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableTransmission">

                                        </td>
                                        <td class="text-center" id="averageTransmission">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentTransmissionFac) ? $percentTransmissionFac : 0; // Pourcentage de transmissionFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $assistanceConduiteFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Assistance à la Conduite",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $assistanceConduiteDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Assistance à la Conduite",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $assistanceConduiteMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Assistance à la Conduite",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $assistanceConduiteFac &&
                                    $assistanceConduiteDecla &&
                                    $assistanceConduiteMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $assistanceConduiteFac->speciality; ?>&level=<?php echo $assistanceConduiteFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $assistanceConduite ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($assistanceConduiteFac); ?>">
                                            <?php 
                                            
                                            $percentassistanceConduiteFac = round(($assistanceConduiteFac->score * 100) / $assistanceConduiteFac->total);
                                            echo $percentassistanceConduiteFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($assistanceConduiteDecla); ?>">
                                            <?php echo round(
                                            ($assistanceConduiteDecla->score *
                                                100) /
                                                $assistanceConduiteDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($assistanceConduiteMa); ?>">
                                            <?php echo round(
                                            ($assistanceConduiteMa->score *
                                                100) /
                                                $assistanceConduiteMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count(
                                            $assistanceConduiteDecla->questions
                                        );
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $assistanceConduiteDecla->answers[$i] ==
                                            "Oui" &&
                                        $assistanceConduiteMa->answers[$i] ==
                                            "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfAssistance">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $assistanceConduiteDecla->answers[$i] ==
                                            "Non" &&
                                        $assistanceConduiteMa->answers[$i] ==
                                            "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfAssistance">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $assistanceConduiteDecla->answers[$i] !=
                                        $assistanceConduiteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfAssistance">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $assistanceConduiteDecla->answers[$i] ==
                                        $assistanceConduiteMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableAssistance">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $assistanceConduiteDecla->answers[$i] !=
                                        $assistanceConduiteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableAssistance">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfAssistance">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableAssistance">

                                        </td>
                                        <td class="text-center" id="averageAssistance">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentassistanceConduiteFac) ? $percentassistanceConduiteFac : 0; // Pourcentage de transmissionFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $transfertFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Boite de Transfert"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transfertDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Boite de Transfert"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transfertMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Boite de Transfert"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $transfertFac &&
                                    $transfertDecla &&
                                    $transfertMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $transfertFac->speciality; ?>&level=<?php echo $transfertFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $transfert ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($transfertFac); ?>">
                                            <?php $percentTransfertFac =round(
                                            ($transfertFac->score * 100) /
                                                $transfertFac->total); 
                                                echo $percentTransfertFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($transfertDecla); ?>">
                                            <?php echo round(
                                            ($transfertDecla->score * 100) /
                                                $transfertDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($transfertMa); ?>">
                                            <?php echo round(
                                            ($transfertMa->score * 100) /
                                                $transfertMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($transfertDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $transfertDecla->answers[$i] == "Oui" &&
                                        $transfertMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransfert">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transfertDecla->answers[$i] == "Non" &&
                                        $transfertMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransfert">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transfertDecla->answers[$i] !=
                                        $transfertMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransfert">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transfertDecla->answers[$i] ==
                                        $transfertMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransfert">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transfertDecla->answers[$i] !=
                                        $transfertMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransfert">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfTransfert">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableTransfert">

                                        </td>
                                        <td class="text-center" id="averageTransfert">
                                            <?php
                                            // Calculer la moyenne
                                            echo isset($percentTransfertFac) ? $percentTransfertFac : 0; // Pourcentage de transfertFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <?php
                                $boiteFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Boite de Vitesse"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Boite de Vitesse"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Boite de Vitesse"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $boiteFac &&
                                    $boiteDecla &&
                                    $boiteMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $boiteFac->speciality; ?>&level=<?php echo $boiteFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $boite_vitesse ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteFac); ?>">
                                            <?php $percentBoiteFac  = round(
                                            ($boiteFac->score * 100) /
                                                $boiteFac->total); 
                                                echo $percentBoiteFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteDecla); ?>">
                                            <?php echo round(
                                            ($boiteDecla->score * 100) /
                                                $boiteDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteMa); ?>">
                                            <?php echo round(
                                            ($boiteMa->score * 100) /
                                                $boiteMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($boiteDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $boiteDecla->answers[$i] == "Oui" &&
                                        $boiteMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoite">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteDecla->answers[$i] == "Non" &&
                                        $boiteMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoite">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteDecla->answers[$i] !=
                                        $boiteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoite">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteDecla->answers[$i] ==
                                        $boiteMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoite">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteDecla->answers[$i] !=
                                        $boiteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoite">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfBoite">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableBoite">

                                        </td>
                                        <td class="text-center" id="averageBoite">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentBoiteFac) ? $percentBoiteFac : 0; // Pourcentage de BoiteFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $boiteAutoFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Automatique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteAutoDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Automatique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteAutoMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Automatique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $boiteAutoFac &&
                                    $boiteAutoDecla &&
                                    $boiteAutoMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $boiteAutoFac->speciality; ?>&level=<?php echo $boiteAutoFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $boite_vitesse_auto ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteAutoFac); ?>">
                                            <?php $percentBoiteAutoFac = round(
                                            ($boiteAutoFac->score * 100) /
                                                $boiteAutoFac->total);
                                                echo $percentBoiteAutoFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteAutoDecla); ?>">
                                            <?php echo round(
                                            ($boiteAutoDecla->score * 100) /
                                                $boiteAutoDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteAutoMa); ?>">
                                            <?php echo round(
                                            ($boiteAutoMa->score * 100) /
                                                $boiteAutoMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($boiteAutoDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $boiteAutoDecla->answers[$i] == "Oui" &&
                                        $boiteAutoMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteAuto">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteAutoDecla->answers[$i] == "Non" &&
                                        $boiteAutoMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteAuto">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteAutoDecla->answers[$i] !=
                                        $boiteAutoMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteAuto">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteAutoDecla->answers[$i] ==
                                        $boiteAutoMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteAuto">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteAutoDecla->answers[$i] !=
                                        $boiteAutoMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteAuto">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfBoiteAuto">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableBoiteAuto">

                                        </td>
                                        <td class="text-center" id="averageBoiteAuto">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentBoiteAutoFac) ? $percentBoiteAutoFac : 0; // Pourcentage de BoiteAutoFac
                                            ?>
                                        </td>


                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $boiteManFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Mécanique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteManDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Mécanique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteManMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse Mécanique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $boiteManFac &&
                                    $boiteManDecla &&
                                    $boiteManMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $boiteManFac->speciality; ?>&level=<?php echo $boiteManFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $boite_vitesse_meca ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteManFac); ?>">
                                            <?php $percentBoiteManFac = round(
                                            ($boiteManFac->score * 100) /
                                                $boiteManFac->total); 
                                                echo $percentBoiteManFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteManDecla); ?>">
                                            <?php echo round(
                                            ($boiteManDecla->score * 100) /
                                                $boiteManDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteManMa); ?>">
                                            <?php echo round(
                                            ($boiteManMa->score * 100) /
                                                $boiteManMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($boiteManDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $boiteManDecla->answers[$i] == "Oui" &&
                                        $boiteManMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteMan">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteManDecla->answers[$i] == "Non" &&
                                        $boiteManMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteMan">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteManDecla->answers[$i] !=
                                        $boiteManMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteMan">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteManDecla->answers[$i] ==
                                        $boiteManMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteMan">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteManDecla->answers[$i] !=
                                        $boiteManMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteMan">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfBoiteMan">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableBoiteMan">

                                        </td>
                                        <td class="text-center" id="averageBoiteMan">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentBoiteManFac) ? $percentBoiteManFac : 0; // Pourcentage de BoiteManFac
                                            ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $boiteVaCoFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse à Variation Continue",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteVaCoDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse à Variation Continue",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $boiteVaCoMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Boite de Vitesse à Variation Continue",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $boiteVaCoFac &&
                                    $boiteVaCoDecla &&
                                    $boiteVaCoMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $boiteVaCoFac->speciality; ?>&level=<?php echo $boiteVaCoFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $boite_vitesse_VC ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteVaCoFac); ?>">
                                            <?php $percentBoiteVacoFac= round(
                                            ($boiteVaCoFac->score * 100) /
                                                $boiteVaCoFac->total); 
                                                echo $percentBoiteVacoFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteVaCoDecla); ?>">
                                            <?php echo round(
                                            ($boiteVaCoDecla->score * 100) /
                                                $boiteVaCoDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($boiteVaCoMa); ?>">
                                            <?php echo round(
                                            ($boiteVaCoMa->score * 100) /
                                                $boiteVaCoMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($boiteVaCoDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $boiteVaCoDecla->answers[$i] == "Oui" &&
                                        $boiteVaCoMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteVaCo">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteVaCoDecla->answers[$i] == "Non" &&
                                        $boiteVaCoMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteVaCo">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteVaCoDecla->answers[$i] !=
                                        $boiteVaCoMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfBoiteVaCo">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteVaCoDecla->answers[$i] ==
                                        $boiteVaCoMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteVaCo">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $boiteVaCoDecla->answers[$i] !=
                                        $boiteVaCoMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableBoiteVaCo">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfBoiteVaCo">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableBoiteVaCo">

                                        </td>
                                        <td class="text-center" id="averageBoiteVaco">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentBoiteVacoFac) ? $percentBoiteVacoFac : 0; // Pourcentage de BoiteVacoFac
                                            ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $climatisationFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Climatisation"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $climatisationDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Climatisation"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $climatisationMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Climatisation"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $climatisationFac &&
                                    $climatisationDecla &&
                                    $climatisationMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $climatisationFac->speciality; ?>&level=<?php echo $climatisationFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $clim ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($climatisationFac); ?>">
                                            <?php $percentClimFac= round(
                                            ($climatisationFac->score * 100) /
                                                $climatisationFac->total); 
                                                echo $percentClimFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($climatisationDecla); ?>">
                                            <?php echo round(
                                            ($climatisationDecla->score * 100) /
                                                $climatisationDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($climatisationMa); ?>">
                                            <?php echo round(
                                            ($climatisationMa->score * 100) /
                                                $climatisationMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($climatisationDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $climatisationDecla->answers[$i] ==
                                            "Oui" &&
                                        $climatisationMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfClimatisation">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $climatisationDecla->answers[$i] ==
                                            "Non" &&
                                        $climatisationMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfClimatisation">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $climatisationDecla->answers[$i] !=
                                        $climatisationMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfClimatisation">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $climatisationDecla->answers[$i] ==
                                        $climatisationMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableClimatisation">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $climatisationDecla->answers[$i] !=
                                        $climatisationMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableClimatisation">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfClimatisation">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableClimatisation">

                                        </td>
                                        <td class="text-center" id="averageClimatisation">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentClimFac) ? $percentClimFac : 0; // Pourcentage de ClimFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $demiFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Demi Arbre de Roue"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $demiDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Demi Arbre de Roue"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $demiMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Demi Arbre de Roue"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $demiFac &&
                                    $demiDecla &&
                                    $demiMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $demiFac->speciality; ?>&level=<?php echo $demiFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $demi ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($demiFac); ?>">
                                            <?php $percentDemiFac = round(
                                            ($demiFac->score * 100) /
                                                $demiFac->total);
                                                echo $percentDemiFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($demiDecla); ?>">
                                            <?php echo round(
                                            ($demiDecla->score * 100) /
                                                $demiDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($demiMa); ?>">
                                            <?php echo round(
                                            ($demiMa->score * 100) /
                                                $demiMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($demiDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $demiDecla->answers[$i] == "Oui" &&
                                        $demiMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDemi">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $demiDecla->answers[$i] == "Non" &&
                                        $demiMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDemi">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $demiDecla->answers[$i] !=
                                        $demiMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDemi">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $demiDecla->answers[$i] ==
                                        $demiMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableDemi">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $demiDecla->answers[$i] !=
                                        $demiMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableDemi">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfDemi">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableDemi">

                                        </td>
                                        <td class="text-center" id="averageDemi">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentDemiFac) ? $percentDemiFac : 0; // Pourcentage de DemiFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $directionFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Direction"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $directionDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Direction"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $directionMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Direction"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $directionFac &&
                                    $directionDecla &&
                                    $directionMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $directionFac->speciality; ?>&level=<?php echo $directionFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $direction ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($directionFac); ?>">
                                            <?php $percentDirectionFac = round(
                                            ($directionFac->score * 100) /
                                                $directionFac->total); 
                                                echo $percentDirectionFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($directionDecla); ?>">
                                            <?php echo round(
                                            ($directionDecla->score * 100) /
                                                $directionDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($directionMa); ?>">
                                            <?php echo round(
                                            ($directionMa->score * 100) /
                                                $directionMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($directionDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $directionDecla->answers[$i] == "Oui" &&
                                        $directionMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDirection">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $directionDecla->answers[$i] == "Non" &&
                                        $directionMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDirection">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $directionDecla->answers[$i] !=
                                        $directionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfDirection">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $directionDecla->answers[$i] ==
                                        $directionMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableDirection">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $directionDecla->answers[$i] !=
                                        $directionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableDirection">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfDirection">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableDirection">

                                        </td>
                                        <td class="text-center" id="averageDirection">
                                            <?php
                                                        // Calculer la moyenne
                                                        echo isset($percentDirectionFac) ? $percentDirectionFac : 0; // Pourcentage de DirectionFac
                                                        ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $electriciteFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Electricité et Electronique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $electriciteDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Electricité et Electronique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $electriciteMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Electricité et Electronique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $electriciteFac &&
                                    $electriciteDecla &&
                                    $electriciteMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $electriciteFac->speciality; ?>&level=<?php echo $electriciteFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $electricite ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($electriciteFac); ?>">
                                            <?php $percentElectriciteFac = round(
                                            ($electriciteFac->score * 100) /
                                                $electriciteFac->total); 
                                                echo $percentElectriciteFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($electriciteDecla); ?>">
                                            <?php echo round(
                                            ($electriciteDecla->score * 100) /
                                                $electriciteDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($electriciteMa); ?>">
                                            <?php echo round(
                                            ($electriciteMa->score * 100) /
                                                $electriciteMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($electriciteDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $electriciteDecla->answers[$i] ==
                                            "Oui" &&
                                        $electriciteMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfElectricite">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $electriciteDecla->answers[$i] ==
                                            "Non" &&
                                        $electriciteMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfElectricite">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $electriciteDecla->answers[$i] !=
                                        $electriciteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfElectricite">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $electriciteDecla->answers[$i] ==
                                        $electriciteMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableElectricite">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $electriciteDecla->answers[$i] !=
                                        $electriciteMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableElectricite">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfElectricite">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableElectricite">

                                        </td>
                                        <td class="text-center" id="averageElectricite">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentElectriciteFac) ? $percentElectriciteFac : 0; // Pourcentage de DirectionFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $freiFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Freinage"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freiDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Freinage"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freiMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Freinage"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $freiFac &&
                                    $freiDecla &&
                                    $freiMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $freiFac->speciality; ?>&level=<?php echo $freiFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $freinage ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($freiFac); ?>">
                                            <?php $percentFreiFac = round(
                                            ($freiFac->score * 100) /
                                                $freiFac->total);
                                                echo $percentFreiFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freiDecla); ?>">
                                            <?php echo round(
                                            ($freiDecla->score * 100) /
                                                $freiDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freiMa); ?>">
                                            <?php echo round(
                                            ($freiMa->score * 100) /
                                                $freiMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($freiDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $freiDecla->answers[$i] == "Oui" &&
                                        $freiMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrei">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freiDecla->answers[$i] == "Non" &&
                                        $freiMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrei">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freiDecla->answers[$i] !=
                                        $freiMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrei">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freiDecla->answers[$i] ==
                                        $freiMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFrei">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freiDecla->answers[$i] !=
                                        $freiMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFrei">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfFrei">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableFrei">

                                        </td>
                                        <td class="text-center" id="averageFrei">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentFreiFac) ? $percentFreiFac : 0; // Pourcentage de FreiFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $freinageElecFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Electromagnétique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinageElecDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Electromagnétique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinageElecMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Freinage Electromagnétique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $freinageElecFac &&
                                    $freinageElecDecla &&
                                    $freinageElecMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $freinageElecFac->speciality; ?>&level=<?php echo $freinageElecFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $freinageElec ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageElecFac); ?>">
                                            <?php $percentFreinageElecFac = round(
                                            ($freinageElecFac->score * 100) /
                                                $freinageElecFac->total); 
                                                echo $percentFreinageElecFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageElecDecla); ?>">
                                            <?php echo round(
                                            ($freinageElecDecla->score * 100) /
                                                $freinageElecDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageElecMa); ?>">
                                            <?php echo round(
                                            ($freinageElecMa->score * 100) /
                                                $freinageElecMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($freinageElecDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $freinageElecDecla->answers[$i] ==
                                            "Oui" &&
                                        $freinageElecMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sffreinageElec">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageElecDecla->answers[$i] ==
                                            "Non" &&
                                        $freinageElecMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sffreinageElec">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageElecDecla->answers[$i] !=
                                        $freinageElecMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sffreinageElec">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageElecDecla->answers[$i] ==
                                        $freinageElecMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFreinageElec">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageElecDecla->answers[$i] !=
                                        $freinageElecMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFreinageElec">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sffreinageElec">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableFreinageElec">

                                        </td>
                                        <td class="text-center" id="averageFreinageElec">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentFreinageElecFac) ? $percentFreinageElecFac : 0; // Pourcentage de FreinageElecFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $freinageFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Hydraulique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinageDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Hydraulique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinageMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Freinage Hydraulique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $freinageFac &&
                                    $freinageDecla &&
                                    $freinageMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $freinageFac->speciality; ?>&level=<?php echo $freinageFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $freinageHydro ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageFac); ?>">
                                            <?php $percentFreinageFac = round(
                                            ($freinageFac->score * 100) /
                                                $freinageFac->total); 
                                                echo $percentFreinageFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageDecla); ?>">
                                            <?php echo round(
                                            ($freinageDecla->score * 100) /
                                                $freinageDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinageMa); ?>">
                                            <?php echo round(
                                            ($freinageMa->score * 100) /
                                                $freinageMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($freinageDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $freinageDecla->answers[$i] == "Oui" &&
                                        $freinageMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFreinage">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageDecla->answers[$i] == "Non" &&
                                        $freinageMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFreinage">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageDecla->answers[$i] !=
                                        $freinageMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFreinage">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageDecla->answers[$i] ==
                                        $freinageMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFreinage">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinageDecla->answers[$i] !=
                                        $freinageMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFreinage">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfFreinage">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableFreinage">

                                        </td>
                                        <td class="text-center" id="averageFreinage">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentFreinageFac) ? $percentFreinageFac : 0; // Pourcentage de FreinageFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $freinFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Pneumatique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Freinage Pneumatique",
                                        ],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $freinMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        [
                                            "speciality" =>
                                                "Freinage Pneumatique",
                                        ],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $freinFac &&
                                    $freinDecla &&
                                    $freinMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $freinFac->speciality; ?>&level=<?php echo $freinFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $freinagePneu ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinFac); ?>">
                                            <?php $percentFreinFac = round(
                                            ($freinFac->score * 100) /
                                                $freinFac->total);
                                                echo $percentFreinFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinDecla); ?>">
                                            <?php echo round(
                                            ($freinDecla->score * 100) /
                                                $freinDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($freinMa); ?>">
                                            <?php echo round(
                                            ($freinMa->score * 100) /
                                                $freinMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($freinDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $freinDecla->answers[$i] == "Oui" &&
                                        $freinMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrein">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinDecla->answers[$i] == "Non" &&
                                        $freinMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrein">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinDecla->answers[$i] !=
                                        $freinMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfFrein">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinDecla->answers[$i] ==
                                        $freinMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFrein">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $freinDecla->answers[$i] !=
                                        $freinMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableFrein">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfFrein">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableFrein">

                                        </td>
                                        <td class="text-center" id="averageFrein">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentFreinFac) ? $percentFreinFac : 0; // Pourcentage de FreinFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $hydrauliqueFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Hydraulique"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $hydrauliqueDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Hydraulique"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $hydrauliqueMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Hydraulique"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $hydrauliqueFac &&
                                    $hydrauliqueDecla &&
                                    $hydrauliqueMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $hydrauliqueFac->speciality; ?>&level=<?php echo $hydrauliqueFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $hydraulique ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($hydrauliqueFac); ?>">
                                            <?php $percentHydrauliqueFac =  round(
                                            ($hydrauliqueFac->score * 100) /
                                                $hydrauliqueFac->total);
                                                echo $percentHydrauliqueFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($hydrauliqueDecla); ?>">
                                            <?php echo round(
                                            ($hydrauliqueDecla->score * 100) /
                                                $hydrauliqueDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($hydrauliqueMa); ?>">
                                            <?php echo round(
                                            ($hydrauliqueMa->score * 100) /
                                                $hydrauliqueMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($hydrauliqueDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $hydrauliqueDecla->answers[$i] ==
                                            "Oui" &&
                                        $hydrauliqueMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfHydraulique">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $hydrauliqueDecla->answers[$i] ==
                                            "Non" &&
                                        $hydrauliqueMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfHydraulique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $hydrauliqueDecla->answers[$i] !=
                                        $hydrauliqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfHydraulique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $hydrauliqueDecla->answers[$i] ==
                                        $hydrauliqueMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableHydraulique">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $hydrauliqueDecla->answers[$i] !=
                                        $hydrauliqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableHydraulique">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfHydraulique">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableHydraulique">

                                        </td>
                                        <td class="text-center" id="averageHydraulique">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentHydrauliqueFac) ? $percentHydrauliqueFac : 0; // Pourcentage de HydrauliqueFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $moteurDieselFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Diesel"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurDieselDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Diesel"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurDieselMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Moteur Diesel"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $moteurDieselFac &&
                                    $moteurDieselDecla &&
                                    $moteurDieselMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $moteurDieselFac->speciality; ?>&level=<?php echo $moteurDieselFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $moteurDiesel ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurDieselFac); ?>">
                                            <?php $percentMoteurDieselFac = round(
                                            ($moteurDieselFac->score * 100) /
                                                $moteurDieselFac->total); 
                                                echo $percentMoteurDieselFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurDieselDecla); ?>">
                                            <?php echo round(
                                            ($moteurDieselDecla->score * 100) /
                                                $moteurDieselDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurDieselMa); ?>">
                                            <?php echo round(
                                            ($moteurDieselMa->score * 100) /
                                                $moteurDieselMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($moteurDieselDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $moteurDieselDecla->answers[$i] ==
                                            "Oui" &&
                                        $moteurDieselMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurDiesel">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurDieselDecla->answers[$i] ==
                                            "Non" &&
                                        $moteurDieselMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurDiesel">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurDieselDecla->answers[$i] !=
                                        $moteurDieselMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurDiesel">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurDieselDecla->answers[$i] ==
                                        $moteurDieselMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurDiesel">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurDieselDecla->answers[$i] !=
                                        $moteurDieselMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurDiesel">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfMoteurDiesel">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableMoteurDiesel">

                                        </td>

                                        <td class="text-center" id="averageMoteurDiesel">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentMoteurDieselFac) ? $percentMoteurDieselFac : 0; // Pourcentage de MoteurDieselFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $moteurElecFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Electrique"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurElecDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Electrique"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurElecMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Moteur Electrique"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $moteurElecFac &&
                                    $moteurElecDecla &&
                                    $moteurElecMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $moteurElecFac->speciality; ?>&level=<?php echo $moteurElecFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $moteurElectrique ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurElecFac); ?>">
                                            <?php $percentMoteurElecFac = round(
                                            ($moteurElecFac->score * 100) /
                                                $moteurElecFac->total);
                                                echo $percentMoteurElecFac ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurElecDecla); ?>">
                                            <?php echo round(
                                            ($moteurElecDecla->score * 100) /
                                                $moteurElecDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurElecMa); ?>">
                                            <?php echo round(
                                            ($moteurElecMa->score * 100) /
                                                $moteurElecMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($moteurElecDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $moteurElecDecla->answers[$i] ==
                                            "Oui" &&
                                        $moteurElecMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurElec">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurElecDecla->answers[$i] ==
                                            "Non" &&
                                        $moteurElecMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurElec">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurElecDecla->answers[$i] !=
                                        $moteurElecMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurElec">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurElecDecla->answers[$i] ==
                                        $moteurElecMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurElec">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurElecDecla->answers[$i] !=
                                        $moteurElecMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurElec">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfMoteurElec">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableMoteurElec">

                                        </td>
                                        <td class="text-center" id="averageMoteurElec">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentMoteurElecFac) ? $percentMoteurElecFac : 0; // Pourcentage de MoteurElecFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $moteurEssenceFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Essence"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurEssenceDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Essence"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurEssenceMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Moteur Essence"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $moteurEssenceFac &&
                                    $moteurEssenceDecla &&
                                    $moteurEssenceMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $moteurEssenceFac->speciality; ?>&level=<?php echo $moteurEssenceFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $moteurEssence ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurEssenceFac); ?>">
                                            <?php $percentMoteurEssenceFac = round(
                                            ($moteurEssenceFac->score * 100) /
                                                $moteurEssenceFac->total); 
                                                echo $percentMoteurEssenceFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurEssenceDecla); ?>">
                                            <?php echo round(
                                            ($moteurEssenceDecla->score * 100) /
                                                $moteurEssenceDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurEssenceMa); ?>">
                                            <?php echo round(
                                            ($moteurEssenceMa->score * 100) /
                                                $moteurEssenceMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($moteurEssenceDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $moteurEssenceDecla->answers[$i] ==
                                            "Oui" &&
                                        $moteurEssenceMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurEssence">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurEssenceDecla->answers[$i] ==
                                            "Non" &&
                                        $moteurEssenceMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurEssence">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurEssenceDecla->answers[$i] !=
                                        $moteurEssenceMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurEssence">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurEssenceDecla->answers[$i] ==
                                        $moteurEssenceMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurEssence">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurEssenceDecla->answers[$i] !=
                                        $moteurEssenceMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurEssence">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfMoteurEssence">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableMoteurEssence">

                                        </td>
                                        <td class="text-center" id="averageMoteurEssence">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentMoteurEssenceFac) ? $percentMoteurEssenceFac : 0; // Pourcentage de MoteurEssenceFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $moteurThermiqueFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Thermique"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurThermiqueDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Moteur Thermique"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $moteurThermiqueMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Moteur Thermique"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $moteurThermiqueFac &&
                                    $moteurThermiqueDecla &&
                                    $moteurThermiqueMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $moteurThermiqueFac->speciality; ?>&level=<?php echo $moteurThermiqueFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $moteurThermique ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurThermiqueFac); ?>">
                                            <?php $percentMoteurThermiqueFac = round(
                                            ($moteurThermiqueFac->score * 100) /
                                                $moteurThermiqueFac->total); 
                                                echo $percentMoteurThermiqueFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurThermiqueDecla); ?>">
                                            <?php echo round(
                                            ($moteurThermiqueDecla->score *
                                                100) /
                                                $moteurThermiqueDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($moteurThermiqueMa); ?>">
                                            <?php echo round(
                                            ($moteurThermiqueMa->score * 100) /
                                                $moteurThermiqueMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($moteurThermiqueDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $moteurThermiqueDecla->answers[$i] ==
                                            "Oui" &&
                                        $moteurThermiqueMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurThermique">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurThermiqueDecla->answers[$i] ==
                                            "Non" &&
                                        $moteurThermiqueMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurThermique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurThermiqueDecla->answers[$i] !=
                                        $moteurThermiqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMoteurThermique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurThermiqueDecla->answers[$i] ==
                                        $moteurThermiqueMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurThermique">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $moteurThermiqueDecla->answers[$i] !=
                                        $moteurThermiqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMoteurThermique">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfMoteurThermique">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableMoteurThermique">

                                        </td>
                                        <td class="text-center" id="averageMoteurThermique">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentMoteurThermiqueFac) ? $percentMoteurThermiqueFac : 0; // Pourcentage de MoteurThermiqueFac
                                            ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $multiplexageFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Réseaux de Communication"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $multiplexageDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Réseaux de Communication"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $multiplexageMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Réseaux de Communication"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $multiplexageFac &&
                                    $multiplexageDecla &&
                                    $multiplexageMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $multiplexageFac->speciality; ?>&level=<?php echo $multiplexageFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $multiplexage ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($multiplexageFac); ?>">
                                            <?php $percentMultiplexageFac = round(
                                            ($multiplexageFac->score * 100) /
                                                $multiplexageFac->total); 
                                                echo $percentMultiplexageFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($multiplexageDecla); ?>">
                                            <?php echo round(
                                            ($multiplexageDecla->score * 100) /
                                                $multiplexageDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($multiplexageMa); ?>">
                                            <?php echo round(
                                            ($multiplexageMa->score * 100) /
                                                $multiplexageMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($multiplexageDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $multiplexageDecla->answers[$i] ==
                                            "Oui" &&
                                        $multiplexageMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMultiplexage">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $multiplexageDecla->answers[$i] ==
                                            "Non" &&
                                        $multiplexageMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMultiplexage">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $multiplexageDecla->answers[$i] !=
                                        $multiplexageMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfMultiplexage">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $multiplexageDecla->answers[$i] ==
                                        $multiplexageMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMultiplexage">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $multiplexageDecla->answers[$i] !=
                                        $multiplexageMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableMultiplexage">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfMultiplexage">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableMultiplexage">

                                        </td>
                                        <td class="text-center" id="averageMultiplexage">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentMultiplexageFac) ? $percentMultiplexageFac : 0; // Pourcentage de MultiplexageFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $pneuFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Pneumatique"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $pneuDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Pneumatique"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $pneuMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Pneumatique"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $pneuFac &&
                                    $pneuDecla &&
                                    $pneuMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $pneuFac->speciality; ?>&level=<?php echo $pneuFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $pneu ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($pneuFac); ?>">
                                            <?php $percentPneuFac = round(
                                            ($pneuFac->score * 100) /
                                                $pneuFac->total); 
                                                echo  $percentPneuFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($pneuDecla); ?>">
                                            <?php echo round(
                                            ($pneuDecla->score * 100) /
                                                $pneuDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($pneuMa); ?>">
                                            <?php echo round(
                                            ($pneuMa->score * 100) /
                                                $pneuMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($pneuDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $pneuDecla->answers[$i] == "Oui" &&
                                        $pneuMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPneu">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pneuDecla->answers[$i] == "Non" &&
                                        $pneuMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPneu">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pneuDecla->answers[$i] !=
                                        $pneuMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPneu">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pneuDecla->answers[$i] ==
                                        $pneuMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiablePneu">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pneuDecla->answers[$i] !=
                                        $pneuMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiablePneu">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfPneu">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiablePneu">

                                        </td>
                                        <td class="text-center" id="averagePneu">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentPneuFac) ? $percentPneuFac : 0; // Pourcentage de PneuFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $pontFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Pont"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $pontDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Pont"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $pontMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Pont"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $pontFac &&
                                    $pontDecla &&
                                    $pontMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $pontFac->speciality; ?>&level=<?php echo $pontFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $pont ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($pontFac); ?>">
                                            <?php $percentPontFac = round(
                                            ($pontFac->score * 100) /
                                                $pontFac->total); 
                                                echo $percentPontFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($pontDecla); ?>">
                                            <?php echo round(
                                            ($pontDecla->score * 100) /
                                                $pontDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($pontMa); ?>">
                                            <?php echo round(
                                            ($pontMa->score * 100) /
                                                $pontMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($pontDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $pontDecla->answers[$i] == "Oui" &&
                                        $pontMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPont">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pontDecla->answers[$i] == "Non" &&
                                        $pontMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPont">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pontDecla->answers[$i] !=
                                        $pontMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfPont">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pontDecla->answers[$i] ==
                                        $pontMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiablePont">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $pontDecla->answers[$i] !=
                                        $pontMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiablePont">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfPont">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiablePont">

                                        </td>
                                        <td class="text-center" id="averagePont">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentPontFac) ? $percentPontFac : 0; // Pourcentage de PontFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $reducteurFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Reducteur"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $reducteurDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Reducteur"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $reducteurMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Reducteur"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $reducteurFac &&
                                    $reducteurDecla &&
                                    $reducteurMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $reducteurFac->speciality; ?>&level=<?php echo $reducteurFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $reducteur ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($reducteurFac); ?>">
                                            <?php $percentReducteurFac = round(
                                            ($reducteurFac->score * 100) /
                                                $reducteurFac->total); 
                                                echo $percentReducteurFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($reducteurDecla); ?>">
                                            <?php echo round(
                                            ($reducteurDecla->score * 100) /
                                                $reducteurDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($reducteurMa); ?>">
                                            <?php echo round(
                                            ($reducteurMa->score * 100) /
                                                $reducteurMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($reducteurDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $reducteurDecla->answers[$i] == "Oui" &&
                                        $reducteurMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfReducteur">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $reducteurDecla->answers[$i] == "Non" &&
                                        $reducteurMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfReducteur">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $reducteurDecla->answers[$i] !=
                                        $reducteurMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfReducteur">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $reducteurDecla->answers[$i] ==
                                        $reducteurMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableReducteur">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $reducteurDecla->answers[$i] !=
                                        $reducteurMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableReducteur">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfReducteur">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableReducteur">

                                        </td>
                                        <td class="text-center" id="averageReducteur">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentReducteurFac) ? $percentReducteurFac : 0; // Pourcentage de ReducteurFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $suspensionFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Suspension"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $suspensionFac &&
                                    $suspensionDecla &&
                                    $suspensionMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $suspensionFac->speciality; ?>&level=<?php echo $suspensionFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $suspension ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionFac); ?>">
                                            <?php $percentSuspensionFac = round(
                                            ($suspensionFac->score * 100) /
                                                $suspensionFac->total); 
                                                echo $percentSuspensionFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionDecla); ?>">
                                            <?php echo round(
                                            ($suspensionDecla->score * 100) /
                                                $suspensionDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionMa); ?>">
                                            <?php echo round(
                                            ($suspensionMa->score * 100) /
                                                $suspensionMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i < count($suspensionDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $suspensionDecla->answers[$i] ==
                                            "Oui" &&
                                        $suspensionMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspension">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionDecla->answers[$i] ==
                                            "Non" &&
                                        $suspensionMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspension">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionDecla->answers[$i] !=
                                        $suspensionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspension">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionDecla->answers[$i] ==
                                        $suspensionMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspension">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionDecla->answers[$i] !=
                                        $suspensionMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspension">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfSuspension">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableSuspension">

                                        </td>
                                        <td class="text-center" id="averageSuspension">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentSuspensionFac) ? $percentSuspensionFac : 0; // Pourcentage de SuspensionFac
                                            ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $suspensionLameFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension à Lame"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionLameDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension à Lame"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionLameMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Suspension à Lame"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $suspensionLameFac &&
                                    $suspensionLameDecla &&
                                    $suspensionLameMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $suspensionLameFac->speciality; ?>&level=<?php echo $suspensionLameFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $suspensionLame ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionLameFac); ?>">
                                            <?php $percentSuspensionLameFac = round(
                                            ($suspensionLameFac->score * 100) /
                                                $suspensionLameFac->total); 
                                                echo $percentSuspensionLameFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionLameDecla); ?>">
                                            <?php echo round(
                                            ($suspensionLameDecla->score *
                                                100) /
                                                $suspensionLameDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionLameMa); ?>">
                                            <?php echo round(
                                            ($suspensionLameMa->score * 100) /
                                                $suspensionLameMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($suspensionLameDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $suspensionLameDecla->answers[$i] ==
                                            "Oui" &&
                                        $suspensionLameMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionLame">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionLameDecla->answers[$i] ==
                                            "Non" &&
                                        $suspensionLameMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionLame">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionLameDecla->answers[$i] !=
                                        $suspensionLameMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionLame">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionLameDecla->answers[$i] ==
                                        $suspensionLameMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionLame">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionLameDecla->answers[$i] !=
                                        $suspensionLameMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionLame">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfSuspensionLame">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableSuspensionLame">

                                        </td>
                                        <td class="text-center" id="averageSuspensionLame">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentSuspensionLameFac) ? $percentSuspensionLameFac : 0; // Pourcentage de SuspensionLameFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $suspensionRessortFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension Ressort"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionRessortDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Suspension Ressort"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionRessortMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Suspension Ressort"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $suspensionRessortFac &&
                                    $suspensionRessortDecla &&
                                    $suspensionRessortMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $suspensionRessortFac->speciality; ?>&level=<?php echo $suspensionRessortFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $suspensionRessort ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionRessortFac); ?>">
                                            <?php $percentSuspensionRessortFac = round(
                                            ($suspensionRessortFac->score *
                                                100) /
                                                $suspensionRessortFac->total); 
                                                echo $percentSuspensionRessortFac?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionRessortDecla); ?>">
                                            <?php echo round(
                                            ($suspensionRessortDecla->score *
                                                100) /
                                                $suspensionRessortDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionRessortMa); ?>">
                                            <?php echo round(
                                            ($suspensionRessortMa->score *
                                                100) /
                                                $suspensionRessortMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count(
                                            $suspensionRessortDecla->questions
                                        );
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $suspensionRessortDecla->answers[$i] ==
                                            "Oui" &&
                                        $suspensionRessortMa->answers[$i] ==
                                            "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionRessort">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionRessortDecla->answers[$i] ==
                                            "Non" &&
                                        $suspensionRessortMa->answers[$i] ==
                                            "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionRessort">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionRessortDecla->answers[$i] !=
                                        $suspensionRessortMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfSuspensionRessort">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionRessortDecla->answers[$i] ==
                                        $suspensionRessortMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionRessort">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionRessortDecla->answers[$i] !=
                                        $suspensionRessortMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionRessort">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfSuspensionRessort">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableSuspensionRessort">

                                        </td>
                                        <td class="text-center" id="averageSuspensionRessort">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentSuspensionRessortFac) ? $percentSuspensionRessortFac : 0; // Pourcentage de SuspensionRessortFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $suspensionPneumatiqueFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Suspension Pneumatique",
                                        ],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $suspensionPneumatiqueDecla = $results->findOne(
                                    [
                                        '$and' => [
                                            [
                                                "user" => new MongoDB\BSON\ObjectId(
                                                    $user
                                                ),
                                            ],
                                            ["level" => $levelfilter],
                                            [
                                                "speciality" =>
                                                    "Suspension Pneumatique",
                                            ],
                                            ["typeR" => "Technicien"],
                                            ["type" => "Declaratif"],
                                            ["numberTest" => +$numberTest],
                                            ["active" => true],
                                        ],
                                    ]
                                );
                                $suspensionPneumatiqueMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        [
                                            "speciality" =>
                                                "Suspension Pneumatique",
                                        ],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $suspensionPneumatiqueFac &&
                                    $suspensionPneumatiqueDecla &&
                                    $suspensionPneumatiqueMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $suspensionPneumatiqueFac->speciality; ?>&level=<?php echo $suspensionPneumatiqueFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $suspensionPneu ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionPneumatiqueFac); ?>">
                                            <?php echo $percentSuspensionPneumatiqueFac = round(
                                            ($suspensionPneumatiqueFac->score *
                                                100) /
                                                $suspensionPneumatiqueFac->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionPneumatiqueDecla); ?>">
                                            <?php echo round(
                                            ($suspensionPneumatiqueDecla->score *
                                                100) /
                                                $suspensionPneumatiqueDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($suspensionPneumatiqueMa); ?>">
                                            <?php echo round(
                                            ($suspensionPneumatiqueMa->score *
                                                100) /
                                                $suspensionPneumatiqueMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count(
                                            $suspensionPneumatiqueDecla->questions
                                        );
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $suspensionPneumatiqueDecla->answers[
                                            $i
                                        ] == "Oui" &&
                                        $suspensionPneumatiqueMa->answers[$i] ==
                                            "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire"
                                            id="sfSuspensionPneumatique">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionPneumatiqueDecla->answers[
                                            $i
                                        ] == "Non" &&
                                        $suspensionPneumatiqueMa->answers[$i] ==
                                            "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire"
                                            id="sfSuspensionPneumatique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionPneumatiqueDecla->answers[
                                            $i
                                        ] !=
                                        $suspensionPneumatiqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire"
                                            id="sfSuspensionPneumatique">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionPneumatiqueDecla->answers[$i] ==
                                        $suspensionPneumatiqueMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionPneumatique">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $suspensionPneumatiqueDecla->answers[$i] !=
                                        $suspensionPneumatiqueMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableSuspensionPneumatique">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfSuspensionPneumatique">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableSuspensionPneumatique">

                                        </td>
                                        <td class="text-center" id="averageSuspensionPneumatique">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentSuspensionPneumatiqueFac) ? $percentSuspensionPneumatiqueFac : 0; // Pourcentage de SuspensionPneumatiqueFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php
                                $transversaleFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Transversale"],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transversaleDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["speciality" => "Transversale"],
                                        ["typeR" => "Technicien"],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $transversaleMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["level" => $levelfilter],
                                        ["numberTest" => +$numberTest],
                                        ["speciality" => "Transversale"],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $transversaleFac &&
                                    $transversaleDecla &&
                                    $transversaleMa
                                ) { ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 100px; position: sticky; left: 0;">
                                            <a href="./system.php?numberTest=<?php echo $numberTest; ?>&speciality=<?php echo $transversaleFac->speciality; ?>&level=<?php echo $transversaleFac->level; ?>&user=<?php echo $technician->_id; ?>"
                                                class="btn btn-light btn-active-light-danger fw-bolder btn-sm"
                                                title="Cliquez ici pour voir le résultat du du groupe foctionnel"
                                                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                <?php echo $transversale ?>
                                            </a>
                                        </td>
                                        <td class="text-center <?php echo getclasses($transversaleFac); ?>">
                                            <?php $percentTransversaleFac =round(
                                            ($transversaleFac->score * 100) /
                                                $transversaleFac->total); 
                                                
                                                echo $percentTransversaleFac;?>%

                                         
                                        </td>
                                        <td class="text-center <?php echo getclasses($transversaleDecla); ?>">
                                            <?php echo round(
                                            ($transversaleDecla->score * 100) /
                                                $transversaleDecla->total); ?>%
                                        </td>
                                        <td class="text-center <?php echo getclasses($transversaleMa); ?>">
                                            <?php echo round(
                                            ($transversaleMa->score * 100) /
                                                $transversaleMa->total); ?>%
                                        </td>
                                        <?php for (
                                        $i = 0;
                                        $i <
                                        count($transversaleDecla->questions);
                                        $i++
                                    ) { ?>
                                        <?php if (
                                        $transversaleDecla->answers[$i] ==
                                            "Oui" &&
                                        $transversaleMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransversale">
                                            <?php echo $maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transversaleDecla->answers[$i] ==
                                            "Non" &&
                                        $transversaleMa->answers[$i] == "Non"
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransversale">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transversaleDecla->answers[$i] !=
                                        $transversaleMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" name="savoirs-faire" id="sfTransversale">
                                            <?php echo $non_maitrise ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transversaleDecla->answers[$i] ==
                                        $transversaleMa->answers[$i] 
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransversale">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $transversaleDecla->answers[$i] !=
                                        $transversaleMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center hidden" id="fiableTransversale">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                        <?php } ?>
                                        <td class="text-center" id="result-sfTransverse">

                                        </td>
                                        <td class="text-center" name="fiable" id="result-fiableTransversale">

                                        </td>
                                        <td class="text-center" id="averageTransversale">
                                            <?php 
                                            // Calculer la moyenne
                                            echo isset($percentTransversaleFac) ? $percentTransversaleFac : 0; // Pourcentage de transmissionFac
                                            ?>
                                        </td>

                                    </tr>
                                    <?php } ?>
                                    <!--end::Menu-->
                                    <?php 
                                    
                                    // function getclasses($grp){
                                    //     $percent  = round(($grp->score * 100) / ($grp->total));
                                    //     $bootstrapclass = getBootstrapClass($percent);
                                    //     return $bootstrapclass;                                    
                                    // }
                                    
                                    ?>
                                    <tr style=" position: sticky; bottom: 0; z-index: 2;">
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px; position: sticky; left:0;">
                                            <?php echo $result ?></th>
                                        <th id="result-savoir"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0 <?php echo getclasses($resultFac); ?>"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                            
                                            
                                            <?php echo round(($resultFac->score * 100) / $resultFac->total); ?>%

                                        </th>
                                        <!-- <th id="decision-savoir"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        </th> -->
                                        <th id="result-n1"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0 <?php echo getclasses($resultDecla); ?>"
                                            style="width: 100px;">
                                            <?php echo round(
                                            ($resultDecla->score * 100) /
                                                $resultDecla->total ??
                                                "0"); ?>%
                                        </th>
                                        <th id="result-n1"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0 <?php echo getclasses($resultMa); ?>"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                            <?php echo round(
                                            ($resultMa->score * 100) /
                                                $resultMa->total ??
                                                "0"); ?>%
                                        </th>
                                        <th id="result-savoir-faire"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0 <?php echo getclasses($resultTechMa); ?>"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                            <?php echo round(
                                            ($resultTechMa->score * 100) /
                                                $resultTechMa->total ??
                                                "0"); ?>%
                                        </th>
                                        <th name="fiable" id="result-fiable"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        </th>
                                        <th id="total-average" 
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0 <?php echo getBootstrapClass(round(($percentageFac + $percentageTechMa) / 2)); ?>" 
                                            tabindex="0" aria-controls="kt_customers_table" 
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                            <?php echo round(
                                            ($percentageFac + $percentageTechMa) / 2); ?>%
                                        </th>

                                        <!-- <th id="decision-savoir-faire"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        </th>
                                        <th id="synthese"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            colspan="1" tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        </th> -->
                                    </tr>
                                </tbody>
                            </table>
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
                name: `Table.xlsx`
            })
        });
    });

    function getBootstrapClass(pourcentage) {
        if (pourcentage <= 59) {
            return 'text-danger'; 
        } else if (pourcentage <= 79) {
            return 'text-warning';
        } else {
            return 'text-success'; 
        }
    }

    // const savoir = []
    // const savoirFaire = []
    // const n = []
    // const n1 = []
    const sfTransfert = []
    const sfTransversale = []
    const sfTransmission = []
    const sfBoite = []
    const sfBoiteAuto = []
    const sfBoiteMan = []
    const sfBoiteVaCo = []
    const sfAssistance = []
    const sfClimatisation = []
    const sfDemi = []
    const sfDirection = []
    const sfElectricite = []
    const sfFreinage = []
    const sfFrein = []
    const sfFrei = []
    const sffreinageElec = []
    const sfMoteurDiesel = []
    const sfMoteurElec = []
    const sfMoteurEssence = []
    const sfMoteurThermique = []
    const sfHydraulique = []
    const sfPneu = []
    const sfPont = []
    const sfReducteur = []
    const sfMultiplexage = []
    const sfSuspensionLame = []
    const sfSuspensionRessort = []
    const sfSuspension = []
    const sfSuspensionPneumatique = []
    const fiableTransfert = []
    const fiableTransversale = []
    const fiableTransmission = []
    const fiableBoite = []
    const fiableBoiteAuto = []
    const fiableBoiteMan = []
    const fiableBoiteVaCo = []
    const fiableAssistance = []
    const fiableClimatisation = []
    const fiableDemi = []
    const fiableDirection = []
    const fiableElectricite = []
    const fiableFreinage = []
    const fiableFrein = []
    const fiableFrei = []
    const fiableFreinageElec = []
    const fiableMoteurDiesel = []
    const fiableMoteurElec = []
    const fiableMoteurEssence = []
    const fiableMoteurThermique = []
    const fiableHydraulique = []
    const fiablePneu = []
    const fiablePont = []
    const fiableReducteur = []
    const fiablemultiplexage = []
    const fiableSuspensionLame = []
    const fiableSuspensionRessort = []
    const fiableSuspension = []
    const fiableSuspensionPneumatique = []
    const valueMaitrisé = "Maitrisé"
    const valueOui = "Oui"
    // const tdSavoir = document.querySelectorAll("td[name='savoir']")
    // const tdSavoirFaire = document.querySelectorAll("td[name='savoirs-faire']")
    // const tdN = document.querySelectorAll("td[name='n']")
    // const tdN1 = document.querySelectorAll("td[name='n1']")

    const tdFiable = document.querySelectorAll("td[name='fiable']")

    const tdsfTransversale = document.querySelectorAll("#sfTransversale")
    const tdsfTransfert = document.querySelectorAll("#sfTransfert")
    const tdsfTransmission = document.querySelectorAll("#sfTransmission")
    const tdsfBoite = document.querySelectorAll("#sfBoite")
    const tdsfBoiteAuto = document.querySelectorAll("#sfBoiteAuto")
    const tdsfBoiteMan = document.querySelectorAll("#sfBoiteMan")
    const tdsfBoiteVaCo = document.querySelectorAll("#sfBoiteVaCo")
    const tdsfAssistance = document.querySelectorAll("#sfAssistance")
    const tdsfClimatisation = document.querySelectorAll("#sfClimatisation")
    const tdsfDemi = document.querySelectorAll("#sfDemi")
    const tdsfDirection = document.querySelectorAll("#sfDirection")
    const tdsfElectricite = document.querySelectorAll("#sfElectricite")
    const tdsfFreinage = document.querySelectorAll("#sfFreinage")
    const tdsfFrein = document.querySelectorAll("#sfFrein")
    const tdsfFrei = document.querySelectorAll("#sfFrei")
    const tdsffreinageElec = document.querySelectorAll("#sffreinageElec")
    const tdsfHydraulique = document.querySelectorAll("#sfHydraulique")
    const tdsfMoteurDiesel = document.querySelectorAll("#sfMoteurDiesel")
    const tdsfMoteurElec = document.querySelectorAll("#sfMoteurElec")
    const tdsfMoteurEssence = document.querySelectorAll("#sfMoteurEssence")
    const tdsfMoteurThermique = document.querySelectorAll("#sfMoteurThermique")
    const tdsfPneu = document.querySelectorAll("#sfPneu")
    const tdsfPont = document.querySelectorAll("#sfPont")
    const tdsfReducteur = document.querySelectorAll("#sfReducteur")
    const tdsfMultiplexage = document.querySelectorAll("#sfMultiplexage")
    const tdsfSuspensionLame = document.querySelectorAll("#sfSuspensionLame")
    const tdsfSuspensionRessort = document.querySelectorAll("#sfSuspensionRessort")
    const tdsfSuspension = document.querySelectorAll("#sfSuspension")
    const tdsfSuspensionPneumatique = document.querySelectorAll("#sfSuspensionPneumatique")
    const tdfiableTransversale = document.querySelectorAll("#fiableTransversale")
    const tdfiableTransfert = document.querySelectorAll("#fiableTransfert")
    const tdfiableTransmission = document.querySelectorAll("#fiableTransmission")
    const tdfiableBoite = document.querySelectorAll("#fiableBoite")
    const tdfiableBoiteAuto = document.querySelectorAll("#fiableBoiteAuto")
    const tdfiableBoiteMan = document.querySelectorAll("#fiableBoiteMan")
    const tdfiableBoiteVaCo = document.querySelectorAll("#fiableBoiteVaCo")
    const tdfiableAssistance = document.querySelectorAll("#fiableAssistance")
    const tdfiableClimatisation = document.querySelectorAll("#fiableClimatisation")
    const tdfiableDemi = document.querySelectorAll("#fiableDemi")
    const tdfiableDirection = document.querySelectorAll("#fiableDirection")
    const tdfiableElectricite = document.querySelectorAll("#fiableElectricite")
    const tdfiableFreinage = document.querySelectorAll("#fiableFreinage")
    const tdfiableFrein = document.querySelectorAll("#fiableFrein")
    const tdfiableFrei = document.querySelectorAll("#fiableFrei")
    const tdfiableFreinageElec = document.querySelectorAll("#fiableFreinageElec")
    const tdfiableHydraulique = document.querySelectorAll("#fiableHydraulique")
    const tdfiableMoteurDiesel = document.querySelectorAll("#fiableMoteurDiesel")
    const tdfiableMoteurElec = document.querySelectorAll("#fiableMoteurElec")
    const tdfiableMoteurEssence = document.querySelectorAll("#fiableMoteurEssence")
    const tdfiableMoteurThermique = document.querySelectorAll("#fiableMoteurThermique")
    const tdfiablePneu = document.querySelectorAll("#fiablePneu")
    const tdfiablePont = document.querySelectorAll("#fiablePont")
    const tdfiableReducteur = document.querySelectorAll("#fiableReducteur")
    const tdfiablemultiplexage = document.querySelectorAll("#fiableMultiplexage")
    const tdfiableSuspensionLame = document.querySelectorAll("#fiableSuspensionLame")
    const tdfiableSuspensionRessort = document.querySelectorAll("#fiableSuspensionRessort")
    const tdfiableSuspension = document.querySelectorAll("#fiableSuspension")
    const tdfiableSuspensionPneumatique = document.querySelectorAll("#fiableSuspensionPneumatique")
    const resultSavoir = document.querySelector("#result-savoir")
    const resultFiable = document.querySelector("#result-fiable")
    const resultSavoirFaire = document.querySelector("#result-savoir-faire")
    const decisionSavoir = document.querySelector("#decision-savoir")
    const decisionSavoirFaire = document.querySelector("#decision-savoir-faire")
    const synthese = document.querySelector("#synthese")
    // const resultN = document.querySelector("#result-n")
    // const resultN1 = document.querySelector("#result-n1")
    const resultsfTransversale = document.querySelector("#result-sfTransverse")
    const resultfiableTransversale = document.querySelector("#result-fiableTransversale")
    const synthTransversale = document.querySelector("#synth-Transversale")
    const resultrTransversale = document.querySelector("#result-rTransversale")
    const facTransversale = document.querySelector("#facTransversale")
    const resultsfTransfert = document.querySelector("#result-sfTransfert")
    const synthTransfert = document.querySelector("#synth-Transfert")
    const resultrTransfert = document.querySelector("#result-rTransfert")
    const resultfiableTransfert = document.querySelector("#result-fiableTransfert")
    const facTransfert = document.querySelector("#facTransfert")
    const resultsfTransmission = document.querySelector("#result-sfTransmission")
    const synthTransmission = document.querySelector("#synth-Transmission")
    const resultrTransmission = document.querySelector("#result-rTransmission")
    const resultfiableTransmission = document.querySelector("#result-fiableTransmission")
    const facTransmission = document.querySelector("#facTransmission")
    const resultsfBoite = document.querySelector("#result-sfBoite")
    const synthBoite = document.querySelector("#synth-Boite")
    const resultrBoite = document.querySelector("#result-rBoite")
    const resultfiableBoite = document.querySelector("#result-fiableBoite")
    const facBoite = document.querySelector("#facBoite")
    const resultsfBoiteAuto = document.querySelector("#result-sfBoiteAuto")
    const synthBoiteAuto = document.querySelector("#synth-BoiteAuto")
    const resultrBoiteAuto = document.querySelector("#result-rBoiteAuto")
    const resultfiableBoiteAuto = document.querySelector("#result-fiableBoiteAuto")
    const facBoiteAuto = document.querySelector("#facBoiteAuto")
    const resultsfBoiteMan = document.querySelector("#result-sfBoiteMan")
    const synthBoiteMan = document.querySelector("#synth-BoiteMan")
    const resultrBoiteMan = document.querySelector("#result-rBoiteMan")
    const resultfiableBoiteMan = document.querySelector("#result-fiableBoiteMan")
    const facBoiteMan = document.querySelector("#facBoiteMan")
    const resultsfBoiteVaCo = document.querySelector("#result-sfBoiteVaCo")
    const resultfiableBoiteVaCo = document.querySelector("#result-fiableBoiteVaCo")
    const synthBoiteVaCo = document.querySelector("#synth-BoiteVaCo")
    const resultrBoiteVaCo = document.querySelector("#result-rBoiteVaCo")
    const facBoiteVaCo = document.querySelector("#facBoiteVaCo")
    const resultsfAssistance = document.querySelector("#result-sfAssistance")
    const resultfiableAssistance = document.querySelector("#result-fiableAssistance")
    const synthAssistance = document.querySelector("#synth-Assistance")
    const resultrAssistance = document.querySelector("#result-rAssistance")
    const facAssistance = document.querySelector("#facAssistance")
    const resultsfClimatisation = document.querySelector("#result-sfClimatisation")
    const resultfiableClimatisation = document.querySelector("#result-fiableClimatisation")
    const synthClimatisation = document.querySelector("#synth-Climatisation")
    const resultrClimatisation = document.querySelector("#result-rClimatisation")
    const facClimatisation = document.querySelector("#facClimatisation")
    const resultsfDemi = document.querySelector("#result-sfDemi")
    const resultfiableDemi = document.querySelector("#result-fiableDemi")
    const synthDemi = document.querySelector("#synth-Demi")
    const resultrDemi = document.querySelector("#result-rDemi")
    const facDemi = document.querySelector("#facDemi")
    const resultsfDirection = document.querySelector("#result-sfDirection")
    const synthDirection = document.querySelector("#synth-Direction")
    const resultfiableDirection = document.querySelector("#result-fiableDirection")
    const resultrDirection = document.querySelector("#result-rDirection")
    const facDirection = document.querySelector("#facDirection")
    const resultsfElectricite = document.querySelector("#result-sfElectricite")
    const synthElectricite = document.querySelector("#synth-Electricite")
    const resultfiableElectricite = document.querySelector("#result-fiableElectricite")
    const resultrElectricite = document.querySelector("#result-rElectricite")
    const facElectricite = document.querySelector("#facElectricite")
    const resultsfFreinage = document.querySelector("#result-sfFreinage")
    const synthFreinage = document.querySelector("#synth-Freinage")
    const resultfiableFreinage = document.querySelector("#result-fiableFreinage")
    const resultrFreinage = document.querySelector("#result-rFreinage")
    const facFreinage = document.querySelector("#facFreinage")
    const resultsfFrein = document.querySelector("#result-sfFrein")
    const synthFrein = document.querySelector("#synth-Frein")
    const resultfiableFrein = document.querySelector("#result-fiableFrein")
    const resultrFrein = document.querySelector("#result-rFrein")
    const facFrein = document.querySelector("#facFrein")
    const resultsfFrei = document.querySelector("#result-sfFrei")
    const resultfiableFrei = document.querySelector("#result-fiableFrei")
    const synthFrei = document.querySelector("#synth-Frei")
    const resultrFrei = document.querySelector("#result-rFrei")
    const facFrei = document.querySelector("#facFrei")
    const resultsffreinageElec = document.querySelector("#result-sffreinageElec")
    const synthfreinageElec = document.querySelector("#synth-freinageElec")
    const resultfiableFreinageElec = document.querySelector("#result-fiableFreinageElec")
    const resultrfreinageElec = document.querySelector("#result-rfreinageElec")
    const facfreinageElec = document.querySelector("#facfreinageElec")
    const resultsfHydraulique = document.querySelector("#result-sfHydraulique")
    const synthHydraulique = document.querySelector("#synth-Hydraulique")
    const resultfiableHydraulique = document.querySelector("#result-fiableHydraulique")
    const resultrHydraulique = document.querySelector("#result-rHydraulique")
    const facHydraulique = document.querySelector("#facHydraulique")
    const resultsfMoteurDiesel = document.querySelector("#result-sfMoteurDiesel")
    const synthMoteurDiesel = document.querySelector("#synth-MoteurDiesel")
    const resultfiableMoteurDiesel = document.querySelector("#result-fiableMoteurDiesel")
    const resultrMoteurDiesel = document.querySelector("#result-rMoteurDiesel")
    const facMoteurDiesel = document.querySelector("#facMoteurDiesel")
    const resultsfMoteurElec = document.querySelector("#result-sfMoteurElec")
    const synthMoteurElec = document.querySelector("#synth-MoteurElec")
    const resultfiableMoteurElec = document.querySelector("#result-fiableMoteurElec")
    const resultrMoteurElec = document.querySelector("#result-rMoteurElec")
    const facMoteurElec = document.querySelector("#facMoteurElec")
    const resultsfMoteurEssence = document.querySelector("#result-sfMoteurEssence")
    const synthMoteurEssence = document.querySelector("#synth-MoteurEssence")
    const resultfiableMoteurEssence = document.querySelector("#result-fiableMoteurEssence")
    const resultrMoteurEssence = document.querySelector("#result-rMoteurEssence")
    const facMoteurEssence = document.querySelector("#facMoteurEssence")
    const resultsfMoteurThermique = document.querySelector("#result-sfMoteurThermique")
    const synthMoteurThermique = document.querySelector("#synth-MoteurThermique")
    const resultfiableMoteurThermique = document.querySelector("#result-fiableMoteurThermique")
    const resultrMoteurThermique = document.querySelector("#result-rMoteurThermique")
    const facMoteurThermique = document.querySelector("#facMoteurThermique")
    const resultsfMultiplexage = document.querySelector("#result-sfMultiplexage")
    const synthMultiplexage = document.querySelector("#synth-Multiplexage")
    const resultfiableMultiplexage = document.querySelector("#result-fiableMultiplexage")
    const resultrMultiplexage = document.querySelector("#result-rMultiplexage")
    const facMultiplexage = document.querySelector("#facMultiplexage")
    const resultsfPneu = document.querySelector("#result-sfPneu")
    const synthPneu = document.querySelector("#synth-Pneu")
    const resultfiablePneu = document.querySelector("#result-fiablePneu")
    const resultrPneu = document.querySelector("#result-rPneu")
    const facPneu = document.querySelector("#facPneu")
    const resultsfPont = document.querySelector("#result-sfPont")
    const synthPont = document.querySelector("#synth-Pont")
    const resultfiablePont = document.querySelector("#result-fiablePont")
    const resultrPont = document.querySelector("#result-rPont")
    const facPont = document.querySelector("#facPont")
    const resultsfReducteur = document.querySelector("#result-sfReducteur")
    const synthReducteur = document.querySelector("#synth-Reducteur")
    const resultfiableReducteur = document.querySelector("#result-fiableReducteur")
    const resultrReducteur = document.querySelector("#result-rReducteur")
    const facReducteur = document.querySelector("#facReducteur")
    const resultsfSuspensionLame = document.querySelector("#result-sfSuspensionLame")
    const synthSuspensionLame = document.querySelector("#synth-SuspensionLame")
    const resultfiableSuspensionLame = document.querySelector("#result-fiableSuspensionLame")
    const resultrSuspensionLame = document.querySelector("#result-rSuspensionLame")
    const facSuspensionLame = document.querySelector("#facSuspensionLame")
    const resultsfSuspensionRessort = document.querySelector("#result-sfSuspensionRessort")
    const synthSuspensionRessort = document.querySelector("#synth-SuspensionRessort")
    const resultfiableSuspensionRessort = document.querySelector("#result-fiableSuspensionRessort")
    const resultrSuspensionRessort = document.querySelector("#result-rSuspensionRessort")
    const facSuspensionRessort = document.querySelector("#facSuspensionRessort")
    const resultsfSuspension = document.querySelector("#result-sfSuspension")
    const synthSuspension = document.querySelector("#synth-Suspension")
    const resultfiableSuspension = document.querySelector("#result-fiableSuspension")
    const resultrSuspension = document.querySelector("#result-rSuspension")
    const facSuspension = document.querySelector("#facSuspension")
    const resultsfSuspensionPneumatique = document.querySelector("#result-sfSuspensionPneumatique")
    const synthSuspensionPneumatique = document.querySelector("#synth-SuspensionPneumatique")
    const resultfiableSuspensionPneumatique = document.querySelector("#result-fiableSuspensionPneumatique")
    const resultrSuspensionPneumatique = document.querySelector("#result-rSuspensionPneumatique")
    const facSuspensionPneumatique = document.querySelector("#facSuspensionPneumatique")

    // for (let i = 0; i < tdSavoir.length; i++) {
    //     savoir.push(tdSavoir[i].innerHTML)
    // }
    // for (let i = 0; i < tdSavoirFaire.length; i++) {
    //     savoirFaire.push(tdSavoirFaire[i].innerHTML)
    // }
    // for (let i = 0; i < tdN.length; i++) {
    //     n.push(tdN[i].innerHTML)
    // }
    // for (let i = 0; i < tdN1.length; i++) {
    //     n1.push(tdN1[i].innerHTML)
    // }
    for (let i = 0; i < tdsfTransversale.length; i++) {
        sfTransversale.push(tdsfTransversale[i].innerHTML)
    }
    for (let i = 0; i < tdsfTransfert.length; i++) {
        sfTransfert.push(tdsfTransfert[i].innerHTML)
    }
    for (let i = 0; i < tdsfTransmission.length; i++) {
        sfTransmission.push(tdsfTransmission[i].innerHTML)
    }
    for (let i = 0; i < tdsfBoite.length; i++) {
        sfBoite.push(tdsfBoite[i].innerHTML)
    }
    for (let i = 0; i < tdsfBoiteAuto.length; i++) {
        sfBoiteAuto.push(tdsfBoiteAuto[i].innerHTML)
    }
    for (let i = 0; i < tdsfBoiteMan.length; i++) {
        sfBoiteMan.push(tdsfBoiteMan[i].innerHTML)
    }
    for (let i = 0; i < tdsfBoiteVaCo.length; i++) {
        sfBoiteVaCo.push(tdsfBoiteVaCo[i].innerHTML)
    }
    for (let i = 0; i < tdsfAssistance.length; i++) {
        sfAssistance.push(tdsfAssistance[i].innerHTML)
    }
    for (let i = 0; i < tdsfClimatisation.length; i++) {
        sfClimatisation.push(tdsfClimatisation[i].innerHTML)
    }
    for (let i = 0; i < tdsfDemi.length; i++) {
        sfDemi.push(tdsfDemi[i].innerHTML)
    }
    for (let i = 0; i < tdsfDirection.length; i++) {
        sfDirection.push(tdsfDirection[i].innerHTML)
    }
    for (let i = 0; i < tdsfElectricite.length; i++) {
        sfElectricite.push(tdsfElectricite[i].innerHTML)
    }
    for (let i = 0; i < tdsfFreinage.length; i++) {
        sfFreinage.push(tdsfFreinage[i].innerHTML)
    }
    for (let i = 0; i < tdsfFrein.length; i++) {
        sfFrein.push(tdsfFrein[i].innerHTML)
    }
    for (let i = 0; i < tdsfFrei.length; i++) {
        sfFrei.push(tdsfFrei[i].innerHTML)
    }
    for (let i = 0; i < tdsffreinageElec.length; i++) {
        sffreinageElec.push(tdsffreinageElec[i].innerHTML)
    }
    for (let i = 0; i < tdsfHydraulique.length; i++) {
        sfHydraulique.push(tdsfHydraulique[i].innerHTML)
    }
    for (let i = 0; i < tdsfMoteurDiesel.length; i++) {
        sfMoteurDiesel.push(tdsfMoteurDiesel[i].innerHTML)
    }
    for (let i = 0; i < tdsfMoteurElec.length; i++) {
        sfMoteurElec.push(tdsfMoteurElec[i].innerHTML)
    }
    for (let i = 0; i < tdsfMoteurEssence.length; i++) {
        sfMoteurEssence.push(tdsfMoteurEssence[i].innerHTML)
    }
    for (let i = 0; i < tdsfMoteurThermique.length; i++) {
        sfMoteurThermique.push(tdsfMoteurThermique[i].innerHTML)
    }
    for (let i = 0; i < tdsfMultiplexage.length; i++) {
        sfMultiplexage.push(tdsfMultiplexage[i].innerHTML)
    }
    for (let i = 0; i < tdsfPneu.length; i++) {
        sfPneu.push(tdsfPneu[i].innerHTML)
    }
    for (let i = 0; i < tdsfPont.length; i++) {
        sfPont.push(tdsfPont[i].innerHTML)
    }
    for (let i = 0; i < tdsfReducteur.length; i++) {
        sfReducteur.push(tdsfReducteur[i].innerHTML)
    }
    for (let i = 0; i < tdsfSuspensionLame.length; i++) {
        sfSuspensionLame.push(tdsfSuspensionLame[i].innerHTML)
    }
    for (let i = 0; i < tdsfSuspensionRessort.length; i++) {
        sfSuspensionRessort.push(tdsfSuspensionRessort[i].innerHTML)
    }
    for (let i = 0; i < tdsfSuspension.length; i++) {
        sfSuspension.push(tdsfSuspension[i].innerHTML)
    }
    for (let i = 0; i < tdsfSuspensionPneumatique.length; i++) {
        sfSuspensionPneumatique.push(tdsfSuspensionPneumatique[i].innerHTML)
    }

    for (let i = 0; i < tdfiableTransversale.length; i++) {
        fiableTransversale.push(tdfiableTransversale[i].innerHTML)
    }
    for (let i = 0; i < tdfiableTransfert.length; i++) {
        fiableTransfert.push(tdfiableTransfert[i].innerHTML)
    }
    for (let i = 0; i < tdfiableTransmission.length; i++) {
        fiableTransmission.push(tdfiableTransmission[i].innerHTML)
    }
    for (let i = 0; i < tdfiableBoite.length; i++) {
        fiableBoite.push(tdfiableBoite[i].innerHTML)
    }
    for (let i = 0; i < tdfiableBoiteAuto.length; i++) {
        fiableBoiteAuto.push(tdfiableBoiteAuto[i].innerHTML)
    }
    for (let i = 0; i < tdfiableBoiteMan.length; i++) {
        fiableBoiteMan.push(tdfiableBoiteMan[i].innerHTML)
    }
    for (let i = 0; i < tdfiableBoiteVaCo.length; i++) {
        fiableBoiteVaCo.push(tdfiableBoiteVaCo[i].innerHTML)
    }
    for (let i = 0; i < tdfiableAssistance.length; i++) {
        fiableAssistance.push(tdfiableAssistance[i].innerHTML)
    }
    for (let i = 0; i < tdfiableClimatisation.length; i++) {
        fiableClimatisation.push(tdfiableClimatisation[i].innerHTML)
    }
    for (let i = 0; i < tdfiableDemi.length; i++) {
        fiableDemi.push(tdfiableDemi[i].innerHTML)
    }
    for (let i = 0; i < tdfiableDirection.length; i++) {
        fiableDirection.push(tdfiableDirection[i].innerHTML)
    }
    for (let i = 0; i < tdfiableElectricite.length; i++) {
        fiableElectricite.push(tdfiableElectricite[i].innerHTML)
    }
    for (let i = 0; i < tdfiableFreinage.length; i++) {
        fiableFreinage.push(tdfiableFreinage[i].innerHTML)
    }
    for (let i = 0; i < tdfiableFrein.length; i++) {
        fiableFrein.push(tdfiableFrein[i].innerHTML)
    }
    for (let i = 0; i < tdfiableFrei.length; i++) {
        fiableFrei.push(tdfiableFrei[i].innerHTML)
    }
    for (let i = 0; i < tdfiableFreinageElec.length; i++) {
        fiableFreinageElec.push(tdfiableFreinageElec[i].innerHTML)
    }
    for (let i = 0; i < tdfiableHydraulique.length; i++) {
        fiableHydraulique.push(tdfiableHydraulique[i].innerHTML)
    }
    for (let i = 0; i < tdfiableMoteurDiesel.length; i++) {
        fiableMoteurDiesel.push(tdfiableMoteurDiesel[i].innerHTML)
    }
    for (let i = 0; i < tdfiableMoteurElec.length; i++) {
        fiableMoteurElec.push(tdfiableMoteurElec[i].innerHTML)
    }
    for (let i = 0; i < tdfiableMoteurEssence.length; i++) {
        fiableMoteurEssence.push(tdfiableMoteurEssence[i].innerHTML)
    }
    for (let i = 0; i < tdfiableMoteurThermique.length; i++) {
        fiableMoteurThermique.push(tdfiableMoteurThermique[i].innerHTML)
    }
    for (let i = 0; i < tdfiablemultiplexage.length; i++) {
        fiablemultiplexage.push(tdfiablemultiplexage[i].innerHTML)
    }
    for (let i = 0; i < tdfiablePneu.length; i++) {
        fiablePneu.push(tdfiablePneu[i].innerHTML)
    }
    for (let i = 0; i < tdfiablePont.length; i++) {
        fiablePont.push(tdfiablePont[i].innerHTML)
    }
    for (let i = 0; i < tdfiableReducteur.length; i++) {
        fiableReducteur.push(tdfiableReducteur[i].innerHTML)
    }
    for (let i = 0; i < tdfiableSuspensionLame.length; i++) {
        fiableSuspensionLame.push(tdfiableSuspensionLame[i].innerHTML)
    }
    for (let i = 0; i < tdfiableSuspensionRessort.length; i++) {
        fiableSuspensionRessort.push(tdfiableSuspensionRessort[i].innerHTML)
    }
    for (let i = 0; i < tdfiableSuspension.length; i++) {
        fiableSuspension.push(tdfiableSuspension[i].innerHTML)
    }
    for (let i = 0; i < tdfiableSuspensionPneumatique.length; i++) {
        fiableSuspensionPneumatique.push(tdfiableSuspensionPneumatique[i].innerHTML)
    }

    // const maitriseSavoir = savoir.filter(function(str) {
    //     return str.includes(valueMaitrisé)
    // })
    // const maitriseSavoirFaire = savoirFaire.filter(function(str) {
    //     return str.includes(valueMaitrisé)
    // })
    // const ouiN = n.filter(function(str) {
    //     return str.includes(valueOui)
    // })
    // const ouiN1 = n1.filter(function(str) {
    //     return str.includes(valueOui)
    // })
    const maitrisesfTransversale = sfTransversale.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfTransfert = sfTransfert.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfTransmission = sfTransmission.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfBoite = sfBoite.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfBoiteAuto = sfBoiteAuto.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfBoiteMan = sfBoiteMan.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfBoiteVaCo = sfBoiteVaCo.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfAssistance = sfAssistance.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfClimatisation = sfClimatisation.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfDemi = sfDemi.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfDirection = sfDirection.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfElectricite = sfElectricite.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfFreinage = sfFreinage.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfFrein = sfFrein.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfFrei = sfFrei.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesffreinageElec = sffreinageElec.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfHydraulique = sfHydraulique.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfMoteurDiesel = sfMoteurDiesel.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfMoteurElec = sfMoteurElec.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfMoteurEssence = sfMoteurEssence.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfMoteurThermique = sfMoteurThermique.filter(function(str) {
        return str.includes(value<?php echo $maitrise ?>)
    })
    const maitrisesfMultiplexage = sfMultiplexage.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfPneu = sfPneu.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfPont = sfPont.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfReducteur = sfReducteur.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfSuspensionLame = sfSuspensionLame.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfSuspensionRessort = sfSuspensionRessort.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfSuspension = sfSuspension.filter(function(str) {
        return str.includes(valueMaitrisé)
    })
    const maitrisesfSuspensionPneumatique = sfSuspensionPneumatique.filter(function(str) {
        return str.includes(valueMaitrisé)
    })

    const ouifiableTransversale = fiableTransversale.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableTransfert = fiableTransfert.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableTransmission = fiableTransmission.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableBoite = fiableBoite.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableBoiteAuto = fiableBoiteAuto.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableBoiteMan = fiableBoiteMan.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableBoiteVaCo = fiableBoiteVaCo.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableAssistance = fiableAssistance.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableClimatisation = fiableClimatisation.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableDemi = fiableDemi.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableDirection = fiableDirection.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableElectricite = fiableElectricite.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableFreinage = fiableFreinage.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableFrein = fiableFrein.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableFrei = fiableFrei.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableFreinageElec = fiableFreinageElec.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableHydraulique = fiableHydraulique.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableMoteurDiesel = fiableMoteurDiesel.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableMoteurElec = fiableMoteurElec.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableMoteurEssence = fiableMoteurEssence.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableMoteurThermique = fiableMoteurThermique.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiablemultiplexage = fiablemultiplexage.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiablePneu = fiablePneu.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiablePont = fiablePont.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableReducteur = fiableReducteur.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableSuspensionLame = fiableSuspensionLame.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableSuspensionRessort = fiableSuspensionRessort.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableSuspension = fiableSuspension.filter(function(str) {
        return str.includes(valueOui)
    })
    const ouifiableSuspensionPneumatique = fiableSuspensionPneumatique.filter(function(str) {
        return str.includes(valueOui)
    })

    // const percentSavoir = ((maitriseSavoir.length * 100) / tdSavoir.length)
    // const percentSavoirFaire = ((maitriseSavoirFaire.length * 100) / tdSavoirFaire.length)
    // const percentN = ((ouiN.length * 100) / tdN.length)
    // const percentN1 = ((ouiN1.length * 100) / tdN1.length)
    const percentsfTransversale = Math.round((maitrisesfTransversale.length * 100) / tdsfTransversale.length)
    const percentsfTransfert = Math.round((maitrisesfTransfert.length * 100) / tdsfTransfert.length)
    const percentsfTransmission = Math.round((maitrisesfTransmission.length * 100) / tdsfTransmission.length)
    const percentsfBoite = Math.round((maitrisesfBoite.length * 100) / tdsfBoite.length)
    const percentsfBoiteAuto = Math.round((maitrisesfBoiteAuto.length * 100) / tdsfBoiteAuto.length)
    const percentsfBoiteMan = Math.round((maitrisesfBoiteMan.length * 100) / tdsfBoiteMan.length)
    const percentsfBoiteVaCo = Math.round((maitrisesfBoiteVaCo.length * 100) / tdsfBoiteVaCo.length)
    const percentsfAssistance = Math.round((maitrisesfAssistance.length * 100) / tdsfAssistance.length)
    const percentsfClimatisation = Math.round((maitrisesfClimatisation.length * 100) / tdsfClimatisation.length)
    const percentsfDemi = Math.round((maitrisesfDemi.length * 100) / tdsfDemi.length)
    const percentsfDirection = Math.round((maitrisesfDirection.length * 100) / tdsfDirection.length)
    const percentsfElectricite = Math.round((maitrisesfElectricite.length * 100) / tdsfElectricite.length)
    const percentsfFreinage = Math.round((maitrisesfFreinage.length * 100) / tdsfFreinage.length)
    const percentsfFrein = Math.round((maitrisesfFrein.length * 100) / tdsfFrein.length)
    const percentsfFrei = Math.round((maitrisesfFrei.length * 100) / tdsfFrei.length)
    const percentsffreinageElec = Math.round((maitrisesffreinageElec.length * 100) / tdsffreinageElec.length)
    const percentsfHydraulique = Math.round((maitrisesfHydraulique.length * 100) / tdsfHydraulique.length)
    const percentsfMoteurDiesel = Math.round((maitrisesfMoteurDiesel.length * 100) / tdsfMoteurDiesel.length)
    const percentsfMoteurElec = Math.round((maitrisesfMoteurElec.length * 100) / tdsfMoteurElec.length)
    const percentsfMoteurEssence = Math.round((maitrisesfMoteurEssence.length * 100) / tdsfMoteurEssence.length)
    const percentsfMoteurThermique = Math.round((maitrisesfMoteurThermique.length * 100) / tdsfMoteurThermique.length)
    const percentsfPneu = Math.round((maitrisesfPneu.length * 100) / tdsfPneu.length)
    const percentsfPont = Math.round((maitrisesfPont.length * 100) / tdsfPont.length)
    const percentsfReducteur = Math.round((maitrisesfReducteur.length * 100) / tdsfReducteur.length)
    const percentsfMultiplexage = Math.round((maitrisesfMultiplexage.length * 100) / tdsfMultiplexage.length)
    const percentsfSuspensionLame = Math.round((maitrisesfSuspensionLame.length * 100) / tdsfSuspensionLame.length)
    const percentsfSuspensionRessort = Math.round((maitrisesfSuspensionRessort.length * 100) / tdsfSuspensionRessort.length)
    const percentsfSuspension = Math.round((maitrisesfSuspension.length * 100) / tdsfSuspension.length)
    const percentsfSuspensionPneumatique = Math.round((maitrisesfSuspensionPneumatique.length * 100) /
        tdsfSuspensionPneumatique.length)

    const percentfiableTransversale = Math.round((ouifiableTransversale.length * 100) / tdfiableTransversale.length)
    const percentfiableTransfert = Math.round((ouifiableTransfert.length * 100) / tdfiableTransfert.length)
    const percentfiableTransmission = Math.round((ouifiableTransmission.length * 100) / tdfiableTransmission.length)
    const percentfiableBoite = Math.round((ouifiableBoite.length * 100) / tdfiableBoite.length)
    const percentfiableBoiteAuto = Math.round((ouifiableBoiteAuto.length * 100) / tdfiableBoiteAuto.length)
    const percentfiableBoiteMan = Math.round((ouifiableBoiteMan.length * 100) / tdfiableBoiteMan.length)
    const percentfiableBoiteVaCo = Math.round((ouifiableBoiteVaCo.length * 100) / tdfiableBoiteVaCo.length)
    const percentfiableAssistance = Math.round((ouifiableAssistance.length * 100) / tdfiableAssistance.length)
    const percentfiableClimatisation = Math.round((ouifiableClimatisation.length * 100) / tdfiableClimatisation.length)
    const percentfiableDemi = Math.round((ouifiableDemi.length * 100) / tdfiableDemi.length)
    const percentfiableDirection = Math.round((ouifiableDirection.length * 100) / tdfiableDirection.length)
    const percentfiableElectricite = Math.round((ouifiableElectricite.length * 100) / tdfiableElectricite.length)
    const percentfiableFreinage = Math.round((ouifiableFreinage.length * 100) / tdfiableFreinage.length)
    const percentfiableFrein = Math.round((ouifiableFrein.length * 100) / tdfiableFrein.length)
    const percentfiableFrei = Math.round((ouifiableFrei.length * 100) / tdfiableFrei.length)
    const percentfiableFreinageElec = Math.round((ouifiableFreinageElec.length * 100) / tdfiableFreinageElec.length)
    const percentfiableHydraulique = Math.round((ouifiableHydraulique.length * 100) / tdfiableHydraulique.length)
    const percentfiableMoteurDiesel = Math.round((ouifiableMoteurDiesel.length * 100) / tdfiableMoteurDiesel.length)
    const percentfiableMoteurElec = Math.round((ouifiableMoteurElec.length * 100) / tdfiableMoteurElec.length)
    const percentfiableMoteurEssence = Math.round((ouifiableMoteurEssence.length * 100) / tdfiableMoteurEssence.length)
    const percentfiableMoteurThermique = Math.round((ouifiableMoteurThermique.length * 100) / tdfiableMoteurThermique.length)
    const percentfiablePneu = Math.round((ouifiablePneu.length * 100) / tdfiablePneu.length)
    const percentfiablePont = Math.round((ouifiablePont.length * 100) / tdfiablePont.length)
    const percentfiableReducteur = Math.round((ouifiableReducteur.length * 100) / tdfiableReducteur.length)
    const percentfiablemultiplexage = Math.round((ouifiablemultiplexage.length * 100) / tdfiablemultiplexage.length)
    const percentfiableSuspensionLame = Math.round((ouifiableSuspensionLame.length * 100) / tdfiableSuspensionLame.length)
    const percentfiableSuspensionRessort = Math.round((ouifiableSuspensionRessort.length * 100) / tdfiableSuspensionRessort
        .length)
    const percentfiableSuspension = Math.round((ouifiableSuspension.length * 100) / tdfiableSuspension.length)
    const percentfiableSuspensionPneumatique = Math.round((ouifiableSuspensionPneumatique.length * 100) /
        tdfiableSuspensionPneumatique.length)

    // resultSavoir.innerHTML = percentSavoir + "%";
    // resultSavoirFaire.innerHTML = percentSavoirFaire + "%";
    // resultN.innerHTML = percentN + "%";
    // resultN1.innerHTML = percentN1 + "%";
    // Variables globales pour accumuler la somme des moyennes et le compte
    var totalAverage = 0;
    var count = 0;
    function updateResultWithAverage(resultElement, percentValue, averageElementId, resultFiableElement, percentFiableValue) {
        if (resultElement) {
            // Mettre à jour le contenu et la classe CSS pour le résultat
            resultElement.innerHTML = percentValue + "%";
            resultElement.classList.add(getBootstrapClass(percentValue));

            // Calculer la moyenne pour le résultat
            var percentCurrent = parseInt(percentValue); // Convertir le pourcentage en entier
            var averageElement = document.getElementById(averageElementId);
            var percentAverage = 0;
            if (averageElement) {
                var averageInnerHTML = averageElement.innerHTML.replace('%', '');
                percentAverage = parseInt(averageInnerHTML) || 0;
            } else {
                console.log(`Average element not found for: ${averageElementId}`);
            }

            var average = Math.round((percentCurrent + percentAverage) / 2); // Arrondir la moyenne

            // Mettre à jour la moyenne et sa classe CSS
            if (averageElement) { // Vérifier si l'élément de moyenne existe
                averageElement.innerHTML = average + '%';
                averageElement.className = 'text-center'; // Centrer le texte
                averageElement.classList.add(getBootstrapClass(average)); // Ajouter la classe basée sur la moyenne

                // Accumuler la somme des moyennes et le compte
                if (average > 0) { // Compter seulement les moyennes non nulles
                    totalAverage += average;
                    count++;
                }
            }
        } else {
            console.log(`Result element not found for: ${averageElementId}`);
            return; // Sortir de la fonction si l'élément n'est pas trouvé
        }

        if (resultFiableElement) {
            console.log(`Element fiable found for: ${resultFiableElement}`);
            // Mettre à jour le contenu et la classe CSS pour le résultat fiable
            resultFiableElement.innerHTML = percentFiableValue + "%";
            resultFiableElement.classList.add(getBootstrapClass(percentFiableValue));
        } else {
            console.log(`Element fiable not found for: ${resultFiableElement}`);
        }
        // calculateTotalAverage(averageElement);
    }

    // // Fonction pour calculer la moyenne totale
    // function calculateTotalAverage() {
    //     // Calculer la moyenne totale
    //     var finalAverage = count > 0 ? Math.round(totalAverage / count) : 0; // Éviter la division par zéro

    //     // Mettre à jour la cellule de la somme des moyennes
    //     var totalAverageElement = document.getElementById('total-average');
    //     if (totalAverageElement) {
    //         totalAverageElement.innerHTML = finalAverage + '%';
    //         totalAverageElement.className = 'text-center'; // Centrer le texte
    //         totalAverageElement.classList.add(getBootstrapClass(finalAverage)); // Ajouter la classe basée sur la moyenne
    //     }
    // }

    updateResultWithAverage(resultsfTransversale, percentsfTransversale, 'averageTransversale', resultfiableTransversale, percentfiableTransversale);
    updateResultWithAverage(resultsfTransfert, percentsfTransfert, 'averageTransfert', resultfiableTransfert, percentfiableTransfert);
    updateResultWithAverage(resultsfTransmission, percentsfTransmission, 'averageTransmission', resultfiableTransmission, percentfiableTransmission);
    updateResultWithAverage(resultsfBoite, percentsfBoite, 'averageBoite', resultfiableBoite, percentfiableBoite);
    updateResultWithAverage(resultsfBoiteAuto, percentsfBoiteAuto, 'averageBoiteAuto', resultfiableBoiteAuto, percentfiableBoiteAuto);
    updateResultWithAverage(resultsfBoiteMan, percentsfBoiteMan, 'averageBoiteMan', resultfiableBoiteMan, percentfiableBoiteMan);
    updateResultWithAverage(resultsfBoiteVaCo, percentsfBoiteVaCo, 'averageBoiteVaco', resultfiableBoiteVaCo, percentfiableBoiteVaCo);
    updateResultWithAverage(resultsfAssistance, percentsfAssistance, 'averageAssistance', resultfiableAssistance, percentfiableAssistance);
    updateResultWithAverage(resultsfClimatisation, percentsfClimatisation, 'averageClimatisation', resultfiableClimatisation, percentfiableClimatisation);
    updateResultWithAverage(resultsfDemi, percentsfDemi, 'averageDemi', resultfiableDemi, percentfiableDemi);
    updateResultWithAverage(resultsfDirection, percentsfDirection, 'averageDirection', resultfiableDirection, percentfiableDirection);
    updateResultWithAverage(resultsfElectricite, percentsfElectricite, 'averageElectricite', resultfiableElectricite, percentfiableElectricite);
    updateResultWithAverage(resultsfFreinage, percentsfFreinage, 'averageFreinage', resultfiableFreinage, percentfiableFreinage);
    updateResultWithAverage(resultsfFrein, percentsfFrein, 'averageFrein', resultfiableFrein, percentfiableFrein);
    updateResultWithAverage(resultsfFrei, percentsfFrei, 'averageFrei', resultfiableFrei, percentfiableFrei);
    updateResultWithAverage(resultsffreinageElec, percentsffreinageElec, 'averageFreinageElec', resultfiableFreinageElec, percentfiableFreinageElec);
    updateResultWithAverage(resultsfHydraulique, percentsfHydraulique, 'averageHydraulique', resultfiableHydraulique, percentfiableHydraulique);
    updateResultWithAverage(resultsfMoteurDiesel, percentsfMoteurDiesel, 'averageMoteurDiesel', resultfiableMoteurDiesel, percentfiableMoteurDiesel);
    updateResultWithAverage(resultsfMoteurElec, percentsfMoteurElec, 'averageMoteurElec', resultfiableMoteurElec, percentfiableMoteurElec);
    updateResultWithAverage(resultsfMoteurEssence, percentsfMoteurEssence, 'averageMoteurEssence', resultfiableMoteurEssence, percentfiableMoteurEssence);
    updateResultWithAverage(resultsfMoteurThermique, percentsfMoteurThermique, 'averageMoteurThermique', resultfiableMoteurThermique, percentfiableMoteurThermique);
    updateResultWithAverage(resultsfMultiplexage, percentsfMultiplexage, 'averageMultiplexage', resultfiableMultiplexage, percentfiablemultiplexage);
    updateResultWithAverage(resultsfPneu, percentsfPneu, 'averagePneu', resultfiablePneu, percentfiablePneu);
    updateResultWithAverage(resultsfPont, percentsfPont, 'averagePont', resultfiablePont, percentfiablePont);
    updateResultWithAverage(resultsfReducteur, percentsfReducteur, 'averageReducteur', resultfiableReducteur, percentfiableReducteur);
    updateResultWithAverage(resultsfSuspensionLame, percentsfSuspensionLame, 'averageSuspensionLame', resultfiableSuspensionLame, percentfiableSuspensionLame);
    updateResultWithAverage(resultsfSuspensionRessort, percentsfSuspensionRessort, 'averageSuspensionRessort', resultfiableSuspensionRessort, percentfiableSuspensionRessort);
    updateResultWithAverage(resultsfSuspension, percentsfSuspension, 'averageSuspension', resultfiableSuspension, percentfiableSuspension);
    updateResultWithAverage(resultsfSuspensionPneumatique, percentsfSuspensionPneumatique, 'averageSuspensionPneumatique', resultfiableSuspensionPneumatique, percentfiableSuspensionPneumatique);

    if (resultsfMultiplexage) { 
        resultsfMultiplexage.innerHTML = percentsfMultiplexage + "%"; 
        resultsfMultiplexage.classList.add(getBootstrapClass(percentsfMultiplexage)); 
    }
    if (resultfiableMultiplexage) { 
        resultfiableMultiplexage.innerHTML = percentfiablemultiplexage + "%"; 
        resultfiableMultiplexage.classList.add(getBootstrapClass(percentfiablemultiplexage)); 
    }


    // Calculer la moyenne totale après avoir mis à jour tous les résultats
    // calculateTotalAverage();

    let total = 0;
    for (var i = 0; i < tdFiable.length; i++) {
        total += parseInt(tdFiable[i].innerText);
        var avg = Math.round(total / tdFiable.length);
    }

    // if (resultsfTransmission) {
    //     var percentSfTransmission = parseInt(resultsfTransmission.innerHTML.replace('%', '')); 
    //     var percentTransmissionFac = parseInt(document.getElementById('averageTransmission').innerHTML.replace('%', '')); 
    //     var average = (percentSfTransmission + percentTransmissionFac) / 2;
    //     document.getElementById('averageTransmission').innerHTML = average + '%'; 
    // }
    // if (resultsfAssistance) {
    //     var percentSfAssistance = parseInt(resultsfAssistance.innerHTML.replace('%', '')); 
    //     var percentAssistanceFac = parseInt(document.getElementById('averageAssistance').innerHTML.replace('%', '')); 
    //     var average = (percentSfAssistance + percentAssistanceFac) / 2;
    //     document.getElementById('averageAssistance').innerHTML = average + '%'; 
    // }

    resultFiable.innerHTML = avg + "%";
    resultFiable.classList.add(getBootstrapClass(avg)); 

    var level = '<?php echo $levelfilter ?>';
    if (level == 'Junior') {
        var validateTache = '<?php echo $validate['tacheJunior'] ?>%';
        var validateQCM = '<?php echo $validate['qcmJunior'] ?>%';
    }
    if (level == 'Senior') {
        var validateTache = '<?php echo $validate['tacheSenior'] ?>%';
        var validateQCM = '<?php echo $validate['qcmSenior'] ?>%';
    }
    if (level == 'Expert') {
        var validateTache = '<?php echo $validate['tacheExpert'] ?>%';
        var validateQCM = '<?php echo $validate['qcmExpert'] ?>%';
    }

</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>