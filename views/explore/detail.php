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
    $questions = $academy->questions;
    $results = $academy->results;
    $validations = $academy->validations;

    $id = $_GET["id"];
    $niveau = $_GET["level"];
    $numberTest = $_GET["numberTest"];

    $validate = $validations->findOne([ "active" => true ]);

    $technician = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($id),
                "active" => true,
            ],
        ],
    ]);
    function getBootstrapClass($pourcentage) {
        if ($pourcentage <= 59) {
            return 'text-danger'; 
        } elseif ($pourcentage <= 79) {
            return 'text-warning';
        } else {
            return 'text-success'; 
        }
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
                    <?php echo $result_detail.' '.$Level.' '.$niveau ?>
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
                                <a class="btn btn-light" style="margin-left: 10px"
                                    href="./brandResult.php?numberTest=<?php echo $numberTest; ?>&id=<?php echo $technician->_id; ?>&level=<?php echo $niveau; ?>"
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
                                    <th class="min-w-10px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan="3"
                                        aria-label="Email: activate to sort column ascending" style = "position : sticky; left : 0; z-index: 2;" >
                                        <?php echo $groupe_fonctionnel ?></th>
                                    <th class="min-w-100px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="4"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $connaissances ?> </th>
                                    <th class="min-w-300px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="5"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $tache_pro ?></th>
                                    <tr></tr>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $question ?></th>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $given_answer ?></th>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $correct_answer ?></th>
                                    <th class="min-w-100px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 100px;">
                                        <?php echo $result ?></th>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $question ?></th>
                                    <th class="min-w-100px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $result_tech ?></th>
                                    <th class="min-w-120px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $result_manager ?></th>
                                    <th class="min-w-120px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 125px;">
                                        <?php echo $result ?></th>
                                    <th class="min-w-100px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $fiabilite ?></th>
                                    <tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                $groupeFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $id
                                            ),
                                        ],
                                        ["type" => "Factuel"],
                                        ["typeR" => "Technicien"],
                                        ["level" => $niveau],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $groupeDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $id
                                            ),
                                        ],
                                        ["type" => "Declaratif"],
                                        ["typeR" => "Techniciens"],
                                        ["level" => $niveau],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $groupeMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $id
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["typeR" => "Managers"],
                                        ["level" => $niveau],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                $groupeTechMa = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $id
                                            ),
                                        ],
                                        [
                                            "manager" => new MongoDB\BSON\ObjectId(
                                                $technician->manager
                                            ),
                                        ],
                                        ["typeR" => "Technicien - Manager"],
                                        ["level" => $niveau],
                                        ["numberTest" => +$numberTest],
                                        ["active" => true],
                                    ],
                                ]);
                                ?>
                                    <?php if (
                                    $groupeFac &&
                                    $groupeDecla &&
                                    $groupeMa
                                ) { ?>
                                    <?php if (
                                    count($groupeFac->questions) <
                                    count($groupeDecla->questions)
                                ) { ?>
                                    <?php for (
                                    $i = 0;
                                    $i < count($groupeDecla->questions);
                                    ++$i
                                ) {

                                    if (isset($groupeFac->answers[$i])) {
                                        $questionFac = $questions->findOne([
                                            "_id" => new MongoDB\BSON\ObjectId(
                                                $groupeFac->questions[$i]
                                            ),
                                        ]);
                                    }
                                    $questionDecla = $questions->findOne([
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $groupeDecla->questions[$i]
                                        ),
                                    ]);
                                    ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; position : sticky; left : 0;">
                                            <?php echo $questionFac->speciality; ?>
                                        </td>
                                        <?php if (
                                        isset($groupeFac->answers[$i])
                                    ) { ?>
                                        <td class="text-center">
                                            <?php echo $questionFac->label; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $groupeFac->userAnswers[
                                            $i
                                        ]; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $questionFac->answer; ?>
                                        </td>
                                        <td class="text-center" name="savoir">
                                            <?php echo $groupeFac->answers[$i]; ?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center">
                                        </td>
                                        <td class="text-center">
                                        </td>
                                        <td class="text-center">
                                        </td>
                                        <td class="text-center" name="savoir">
                                        </td>
                                        <?php } ?>
                                        <td class="text-center">
                                            <?php echo $questionDecla->label; ?>
                                        </td>
                                        <?php if (
                                        $groupeDecla->userAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $jai_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeMa->managerAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_na_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $maitrise?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $non_maitrise?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeDecla->answers[$i] ==
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non?>
                                        </td>
                                        <?php } ?>
                                        
                                    </tr>
                                    <?php
                                } ?>
                                    <?php } elseif (
                                    count($groupeFac->questions) >
                                    count($groupeDecla->questions)
                                ) { ?>
                                    <?php for (
                                    $i = 0;
                                    $i < count($groupeFac->questions);
                                    $i++
                                ) {

                                    $questionFac = $questions->findOne([
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $groupeFac->questions[$i]
                                        ),
                                    ]);
                                    if (isset($groupeDecla->answers[$i])) {
                                        $questionDecla = $questions->findOne([
                                            "_id" => new MongoDB\BSON\ObjectId(
                                                $groupeDecla->questions[$i]
                                            ),
                                        ]);
                                    }
                                    ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; ">
                                            <?php echo $questionFac->speciality; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $questionFac->label; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $groupeFac->userAnswers[
                                            $i
                                        ]; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $questionFac->answer; ?>
                                        </td>
                                        <td class="text-center" name="savoir">
                                            <?php echo $groupeFac->answers[$i]; ?>
                                        </td>
                                        <?php if (
                                        isset($groupeDecla->answers[$i])
                                    ) { ?>
                                        <td class="text-center">
                                            <?php echo $questionDecla->label; ?>
                                        </td>
                                        <?php if (
                                        $groupeDecla->userAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $jai_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeMa->managerAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_na_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $maitrise?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $non_maitrise?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeDecla->answers[$i] ==
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non?>
                                        </td>
                                        <?php } ?>
                                        
                                        <?php } else { ?>
                                        <td class="text-center">
                                        </td>
                                        <td class="text-center" name="n">
                                        </td>
                                        <td class="text-center" name="n1">
                                        </td>
                                        <td class="text-center" name="savoirs-faire">
                                        </td>

                                        <?php } ?>
                                    </tr>
                                    <?php
                                } ?>
                                    <?php } else { ?>
                                    <?php for (
                                    $i = 0;
                                    $i < count($groupeDecla->questions);
                                    $i++
                                ) {

                                    $questionFac = $questions->findOne([
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $groupeFac->questions[$i]
                                        ),
                                    ]);
                                    $questionDecla = $questions->findOne([
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $groupeDecla->questions[$i]
                                        ),
                                    ]);
                                    ?>
                                    <tr class="odd" style="">
                                        <td class="min-w-125px sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; ">
                                            <?php echo $questionFac->speciality; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $questionFac->label; ?>
                                        </td>
                                        <td class="text-center" name="savoir">
                                            <?php echo $groupeFac->userAnswers[
                                            $i
                                        ]; ?>
                                        </td>
                                        <td class="text-center" name="savoir">
                                            <?php echo $questionFac->answer; ?>
                                        </td>
                                        <td class="text-center" name="savoir">
                                            <?php echo $groupeFac->answers[$i]; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $questionDecla->label; ?>
                                        </td>
                                        <?php if (
                                        $groupeDecla->userAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $je_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeDecla->userAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n">
                                            <?php echo $jai_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeMa->managerAnswers[$i] ==
                                        "1-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-1"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_maitrise?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "2-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-2"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_ne_maitrise_pas?>
                                        </td>
                                        <?php } elseif (
                                        $groupeMa->managerAnswers[$i] ==
                                        "3-" .
                                            $questionDecla->speciality .
                                            "-" .
                                            $questionDecla->level .
                                            "-" .
                                            $questionDecla->label .
                                            "-3"
                                    ) { ?>
                                        <td class="text-center" name="n1">
                                            <?php echo $il_na_jamais_fait?>
                                        </td>
                                        <?php } ?>
                                        </td>
                                        <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $maitrise?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="savoir-faire">
                                            <?php echo $non_maitrise?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                        $groupeDecla->answers[$i] ==
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui?>
                                        </td>
                                        <?php } else { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non?>
                                        </td>
                                        <?php } ?>

                                    </tr>
                                    <?php
                                } ?>
                                    <?php }} ?>
                                    <!--end::Menu-->
                                    <tr style="background-color: #EDF2F7; position: sticky; bottom: 0; z-index: 2;">
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; position : sticky; left : 0;">
                                            <?php echo $result?></th>
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                        </th>
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                        </th>
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                        </th>
                                        <?php 
                                        $percenttt = ceil(($groupeFac->score * 100) /  $groupeFac->total);
                                        $bootstrapClass = getBootstrapClass($percenttt);
                                        ?>
                                        <th id="result-savoir"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold <?php echo $bootstrapClass;?> text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                           <?php echo $percenttt; ?>%
                                        
                                        </th>
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $result ?></th>
                                        <?php 
                                            $percenttt1 = ceil(($groupeDecla->score * 100) /  $groupeDecla->total);
                                            $bootstrapClass = getBootstrapClass($percenttt1);
                                        ?>
                                        <th id="result-n"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold <?php echo $bootstrapClass;?> text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $percenttt1; ?>%
                                        </th>
                                        <?php 
                                            $percenttt2 = ceil(($groupeMa->score * 100) /  $groupeMa->total);
                                            $bootstrapClass = getBootstrapClass($percenttt2);
                                        ?>
                                        <th id="result-n1"
                                            class="min-w-120px sorting text-black text-center table-light fw-bold <?php echo $bootstrapClass;?> text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $percenttt2; ?>%
                                        </th>
                                        <?php 
                                            $percenttt3 = ceil(($groupeTechMa->score * 100) /  $groupeTechMa->total);
                                            $bootstrapClass = getBootstrapClass($percenttt3);
                                        ?>
                                        <th id="result-savoir-faire"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold <?php echo $bootstrapClass;?> text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $percenttt3; ?>%
                                        </th>
                                        <th id="result-fiable"
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                        </th>

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
<script src="../public/js/main.js"></script>
<script>
$(document).ready(function() {
    $("#excel").on("click", function() {
        let table = document.getElementsByTagName("table");
        debugger;
        TableToExcel.convert(table[0], {
            name: `Resultat Détaillés.xlsx`
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

const resultSavoir = document.querySelector("#result-savoir")
const resultSavoirFaire = document.querySelector("#result-savoir-faire")
const resultFiable = document.querySelector("#result-fiable")
const resultSynt = document.querySelector("#result-synt")

const fiable = []
const tdFiable = document.querySelectorAll("td[name='fiable']")

for (let i = 0; i < tdFiable.length; i++) {
    fiable.push(tdFiable[i].innerHTML)
}

const ouiFiable = fiable.filter(function(str) {
    return str.includes("Oui")
})

const percentFiable = Math.ceil((ouiFiable.length * 100) / tdFiable.length)


resultFiable.innerHTML = percentFiable + "%";
resultFiable.classList.add(getBootstrapClass(percentFiable)); 

var level = '<?php echo $niveau ?>';
if (level == 'Junior') {
    var tachePro = '<?php echo $validate['tacheJunior'] ?>%';
    var qcm = '<?php echo $validate['qcmJunior'] ?>%';
}
if (level == 'Senior') {
    var tachePro = '<?php echo $validate['tacheSenior'] ?>%';
    var qcm = '<?php echo $validate['qcmSenior'] ?>%';
}
if (level == 'Expert') {
    var tachePro = '<?php echo $validate['tacheExpert'] ?>%';
    var qcm = '<?php echo $validate['qcmExpert'] ?>%';
}

if (resultSavoirFaire.innerHTML >= a && resultSavoir.innerHTML >= b) {
    resultSynt.innerHTML = "Maitrisé";
} else {
    resultSynt.innerHTML = "Non maitrisé";
}
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>