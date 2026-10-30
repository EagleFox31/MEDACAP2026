<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client(medacapMongoUri());

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $results = $academy->results;
    $allocations = $academy->allocations;

    $subsidiar = $_GET["subsidiary"];
    $managerTechnician = isset($_GET["user"]) ? $_GET["user"] : null;
    $selectedLevel = $_GET["level"];

    $technicians = [];
    if (isset($managerTechnician)) {
        $techs = $users->find([
            '$and' => [
                [
                    "manager" => new MongoDB\BSON\ObjectId($managerTechnician),
                    "subsidiary" => $subsidiar,
                    "active" => true,
                ],
            ],
        ])->toArray();
        $managerTech = $users->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($managerTechnician),
                    "subsidiary" => $subsidiar,
                    "active" => true,
                ],
            ],
        ]);
        foreach ($techs as $techn) {
            if ($techn["profile"] == "Technicien") {
                array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
            } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
                array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
            }
        }
    } else {
        $techs = $users->find([
            '$and' => [
                [
                    "subsidiary" => $subsidiar,
                    "active" => true,
                ],
            ],
        ])->toArray();
        foreach ($techs as $techn) {
            if ($techn["profile"] == "Technicien") {
                array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
            } elseif ($techn["profile"] == "Manager" && $techn["test"] == true) {
                array_push($technicians, new MongoDB\BSON\ObjectId($techn['_id']));
            }
        }
    }
    $managers = $users->find([
        '$and' => [
            [
                "subsidiary" => $subsidiar,
                "profile" => "Manager",
                "active" => true,
            ],
        ],
    ])->toArray();

    include_once "../language.php";
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
                <h1 class="text-dark fw-bolder my-1 fs-2">
                    <?php echo $result_techs ?> <?php echo $Level ?> <?php echo $selectedLevel ?> <?php echo $by_brand ?></h1>
                <!--end::Title-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

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
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Filtres -->
                    <div class="container my-4">
                        <div class="row g-3 align-items-center">
                            <!-- Filtre Niveaux -->
                            <div class="col-md-6">
                                <label for="level-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-bar-chart-fill fs-2 me-2 text-warning"></i> Niveaux
                                </label>
                                <select id="level-filter" onchange="level()" name="level" class="form-select">
                                    <?php foreach (['Junior', 'Senior', 'Expert'] as $levelOption) { ?>
                                        <option value="<?php echo htmlspecialchars($levelOption); ?>" 
                                            <?php if ($selectedLevel === $levelOption) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($levelOption); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <!-- Filtre Manager -->
                            <div class="col-md-6">
                                <label for="manager-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-person-fill fs-2 me-2 text-info"></i> Manager
                                </label>
                                <select id="manager-filter" onchange="manager()" name="manager" class="form-select">
                                    <option value="all" selected>Tous les managers</option>
                                    <!-- Options des managers seront insérées ici dynamiquement -->
                                    <?php foreach  ($managers as $manager) { ?>
                                        <?php if  ($manager['_id'] == $managerTechnician) { ?>
                                            <option value="<?php echo $manager['_id'] ?>" selected><?php echo $manager['firstName'].' '.$manager['lastName'] ?></option>
                                        <?php } else { ?>
                                            <option value="<?php echo $manager['_id'] ?>"><?php echo $manager['firstName'].' '.$manager['lastName'] ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!--end::Filtres -->
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered table-row-dashed fs-7 gy-3 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder text-uppercase ">
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                        aria-label="Email: activate to sort column ascending" style=" position: sticky; left: 0; z-index: 2;">
                                        <?php echo $technicienss ?></th>
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending">
                                        Bâteaux</th> -->
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $bus ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="5"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $camions ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $chariots ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $engins ?></th>
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending">
                                        Moto</th> -->
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="7"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $vl ?></th>
                                <?php if ($selectedLevel != 'Junior') { ?>
                                    <th class="min-w-10px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="4"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $speciality." ".$selectedLevel ?></th>
                                <?php } ?>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan="3"
                                        aria-label="Email: activate to sort column ascending">
                                        <?php echo $total_marques ?></th>
                                        <tr></tr>
                                    <!-- <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        Yamaha</th> -->
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px; position: sticky; left: 0; z-index: 2;">
                                        <?php echo $prenomsNoms ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px; position: sticky; left: 85px; z-index: 2;">
                                        <?php echo $Level ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $kingLong ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $fuso ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $hino ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                        <?php echo $mercedesTruck ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $renaultTruck ?></th>
                                    <th class="min-w-135px sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $sinotruk ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $toyotaBt ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $toyotaForklift ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $jcb ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $lovol ?></th>
                                    <!-- <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        Yamaha</th> -->
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        BYD Vl</th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                        <?php echo $citroen ?></th>
                                    <th class="min-w-135px sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $mercedes ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        Mitsubishi Vl</th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $peugeot ?></th>
                                    <th class="min-w-135px sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                        <?php echo $suzuki ?></th>
                                    <th class="min-w-135px sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $toyota ?></th>
                                <?php if ($selectedLevel != 'Junior') { ?>
                                    <th class="min-w-135px sorting   text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $electricite ?></th>
                                    <th class="min-w-135px sorting   text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $hydraulique ?></th>
                                    <th class="min-w-135px sorting   text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $moteur ?></th>
                                    <th class="min-w-135px sorting   text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                        <?php echo $transmission?></th>
                                <?php } ?>
                                        <tr></tr>
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        Connaissances</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Technicien</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Manager</th> -->
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        Connaissances</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Technicien</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Manager</th> -->
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        Connaissances</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Technicien</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Manager</th> -->
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        Connaissances</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Technicien</th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Tâches Professionnelles du Manager</th> -->
                                    <!-- <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $global ?></th>
                                    <th class=" sorting  text-center table-light fw-bolder text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $global ?></th> -->
                                </thead>
                                    <tbody class="fw-semibolder text-gray-600" id="table">
                                        <?php if (isset($managerTechnician) && $managerTech['test'] == true) {
                                            $allocateFac = $allocations->findOne([
                                                '$and' => [
                                                    [
                                                        "user" => new MongoDB\BSON\ObjectId($managerTechnician),
                                                        "level" => $selectedLevel,
                                                        "type" => "Factuel",
                                                    ],
                                                ],
                                            ]);
                                            $allocateDecla = $allocations->findOne([
                                                '$and' => [
                                                    [
                                                        "user" => new MongoDB\BSON\ObjectId($managerTechnician),
                                                        "level" => $selectedLevel,
                                                        "type" => "Declaratif",
                                                    ],
                                                ],
                                            ]);
                                            if (isset($allocateFac) && isset($allocateDecla)) {
                                                $tech = $users->findOne([
                                                    '$and' => [
                                                        [
                                                            "_id" => $allocateFac['user'],
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                                $techManagerId = isset($tech->manager) ? $tech->manager : null;
                                                $transmissionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionPneumatiqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Suspension Pneumatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionPneumatiqueDecla = $results->findOne(
                                                    [
                                                        '$and' => [
                                                            [
                                                                "user" => new MongoDB\BSON\ObjectId(
                                                                    $managerTechnician
                                                                ),
                                                            ],
                                                            ["level" => $selectedLevel],
                                                            [
                                                                "speciality" =>
                                                                    "Suspension Pneumatique",
                                                            ],
                                                            ["type" => "Declaratif"],
                                                            ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                        ],
                                                    ]
                                                );
                                                $suspensionPneumatiqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Suspension Pneumatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transversaleFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transversaleDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                // var_dump($transversaleDecla);
                                                $transversaleMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $managerTechnician
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $managerTech->manager
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultFac = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($managerTechnician)],
                                                        ["type" => "Factuel"],
                                                        ["typeR" => "Technicien"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultDecla = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($managerTechnician)],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Techniciens"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultMa = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($managerTechnician)],
                                                        ["manager" => new MongoDB\BSON\ObjectId($managerTech->manager)],
                                                        ["typeR" => "Managers"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultTechMa = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($managerTechnician)],
                                                        ["manager" => new MongoDB\BSON\ObjectId($managerTech->manager)],
                                                        ["typeR" => "Technicien - Manager"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionTotalFac = $transmissionFac->total ?? 0;
                                                $assistanceConduiteTotalFac = $assistanceConduiteFac->total ?? 0;
                                                $transfertTotalFac = $transfertFac->total ?? 0;
                                                $boiteTotalFac = $boiteFac->total ?? 0;
                                                $boiteManTotalFac = $boiteManFac->total ?? 0;
                                                $boiteAutoTotalFac = $boiteAutoFac->total ?? 0;
                                                $boiteVaCoTotalFac = $boiteVaCoFac->total ?? 0;
                                                $climatisationTotalFac = $climatisationFac->total ?? 0;
                                                $demiTotalFac = $demiFac->total ?? 0;
                                                $directionTotalFac = $directionFac->total ?? 0;
                                                $electriciteTotalFac = $electriciteFac->total ?? 0;
                                                $freiTotalFac = $freiFac->total ?? 0;
                                                $freinageElecTotalFac = $freinageElecFac->total ?? 0;
                                                $freinageTotalFac = $freinageFac->total ?? 0;
                                                $freinTotalFac = $freinFac->total ?? 0;
                                                $hydrauliqueTotalFac = $hydrauliqueFac->total ?? 0;
                                                $moteurDieselTotalFac = $moteurDieselFac->total ?? 0;
                                                $moteurEssenceTotalFac = $moteurEssenceFac->total ?? 0;
                                                $moteurElecTotalFac = $moteurElecFac->total ?? 0;
                                                $moteurThermiqueTotalFac = $moteurThermiqueFac->total ?? 0;
                                                $multiplexageTotalFac = $multiplexageFac->total ?? 0;
                                                $pneuTotalFac = $pneuFac->total ?? 0;
                                                $pontTotalFac = $pontFac->total ?? 0;
                                                $reducteurTotalFac = $reducteurFac->total ?? 0;
                                                $suspensionTotalFac = $suspensionFac->total ?? 0;
                                                $suspensionLameTotalFac = $suspensionLameFac->total ?? 0;
                                                $suspensionRessortTotalFac = $suspensionRessortFac->total ?? 0;
                                                $suspensionPneumatiqueTotalFac = $suspensionPneumatiqueFac->total ?? 0;
                                                $transversaleTotalFac = $transversaleFac->total ?? 0;
                                                
                                                $transmissionTotalDecla = $transmissionDecla->total ?? 0;
                                                $assistanceConduiteTotalDecla = $assistanceConduiteDecla->total ?? 0;
                                                $transfertTotalDecla = $transfertDecla->total ?? 0;
                                                $boiteTotalDecla = $boiteDecla->total ?? 0;
                                                $boiteManTotalDecla = $boiteManDecla->total ?? 0;
                                                $boiteAutoTotalDecla = $boiteAutoDecla->total ?? 0;
                                                $boiteVaCoTotalDecla = $boiteVaCoDecla->total ?? 0;
                                                $climatisationTotalDecla = $climatisationDecla->total ?? 0;
                                                $demiTotalDecla = $demiDecla->total ?? 0;
                                                $directionTotalDecla = $directionDecla->total ?? 0;
                                                $electriciteTotalDecla = $electriciteDecla->total ?? 0;
                                                $freiTotalDecla = $freiDecla->total ?? 0;
                                                $freinageElecTotalDecla = $freinageElecDecla->total ?? 0;
                                                $freinageTotalDecla = $freinageDecla->total ?? 0;
                                                $freinTotalDecla = $freinDecla->total ?? 0;
                                                $hydrauliqueTotalDecla = $hydrauliqueDecla->total ?? 0;
                                                $moteurDieselTotalDecla = $moteurDieselDecla->total ?? 0;
                                                $moteurEssenceTotalDecla = $moteurEssenceDecla->total ?? 0;
                                                $moteurElecTotalDecla = $moteurElecDecla->total ?? 0;
                                                $moteurThermiqueTotalDecla = $moteurThermiqueDecla->total ?? 0;
                                                $multiplexageTotalDecla = $multiplexageDecla->total ?? 0;
                                                $pneuTotalDecla = $pneuDecla->total ?? 0;
                                                $pontTotalDecla = $pontDecla->total ?? 0;
                                                $reducteurTotalDecla = $reducteurDecla->total ?? 0;
                                                $suspensionTotalDecla = $suspensionDecla->total ?? 0;
                                                $suspensionLameTotalDecla = $suspensionLameDecla->total ?? 0;
                                                $suspensionRessortTotalDecla = $suspensionRessortDecla->total ?? 0;
                                                $suspensionPneumatiqueTotalDecla = $suspensionPneumatiqueDecla->total ?? 0;
                                                $transversaleTotalDecla = $transversaleDecla->total ?? 0;
                                            
                                                $transmissionScoreFac = $transmissionFac->score ?? 0;
                                                $assistanceConduiteScoreFac = $assistanceConduiteFac->score ?? 0;
                                                $transfertScoreFac = $transfertFac->score ?? 0;
                                                $boiteScoreFac = $boiteFac->score ?? 0;
                                                $boiteManScoreFac = $boiteManFac->score ?? 0;
                                                $boiteAutoScoreFac = $boiteAutoFac->score ?? 0;
                                                $boiteVaCoScoreFac = $boiteVaCoFac->score ?? 0;
                                                $climatisationScoreFac = $climatisationFac->score ?? 0;
                                                $demiScoreFac = $demiFac->score ?? 0;
                                                $directionScoreFac = $directionFac->score ?? 0;
                                                $electriciteScoreFac = $electriciteFac->score ?? 0;
                                                $freiScoreFac = $freiFac->score ?? 0;
                                                $freinageElecScoreFac = $freinageElecFac->score ?? 0;
                                                $freinageScoreFac = $freinageFac->score ?? 0;
                                                $freinScoreFac = $freinFac->score ?? 0;
                                                $hydrauliqueScoreFac = $hydrauliqueFac->score ?? 0;
                                                $moteurDieselScoreFac = $moteurDieselFac->score ?? 0;
                                                $moteurEssenceScoreFac = $moteurEssenceFac->score ?? 0;
                                                $moteurElecScoreFac = $moteurElecFac->score ?? 0;
                                                $moteurThermiqueScoreFac = $moteurThermiqueFac->score ?? 0;
                                                $multiplexageScoreFac = $multiplexageFac->score ?? 0;
                                                $pneuScoreFac = $pneuFac->score ?? 0;
                                                $pontScoreFac = $pontFac->score ?? 0;
                                                $reducteurScoreFac = $reducteurFac->score ?? 0;
                                                $suspensionScoreFac = $suspensionFac->score ?? 0;
                                                $suspensionLameScoreFac = $suspensionLameFac->score ?? 0;
                                                $suspensionRessortScoreFac = $suspensionRessortFac->score ?? 0;
                                                $suspensionPneumatiqueScoreFac = $suspensionPneumatiqueFac->score ?? 0;
                                                $transversaleScoreFac = $transversaleFac->score ?? 0;
                                                
                                                $transmissionScoreDecla = $transmissionDecla->score ?? 0;
                                                $assistanceConduiteScoreDecla = $assistanceConduiteDecla->score ?? 0;
                                                $transfertScoreDecla = $transfertDecla->score ?? 0;
                                                $boiteScoreDecla = $boiteDecla->score ?? 0;
                                                $boiteManScoreDecla = $boiteManDecla->score ?? 0;
                                                $boiteAutoScoreDecla = $boiteAutoDecla->score ?? 0;
                                                $boiteVaCoScoreDecla = $boiteVaCoDecla->score ?? 0;
                                                $climatisationScoreDecla = $climatisationDecla->score ?? 0;
                                                $demiScoreDecla = $demiDecla->score ?? 0;
                                                $directionScoreDecla = $directionDecla->score ?? 0;
                                                $electriciteScoreDecla = $electriciteDecla->score ?? 0;
                                                $freiScoreDecla = $freiDecla->score ?? 0;
                                                $freinageElecScoreDecla = $freinageElecDecla->score ?? 0;
                                                $freinageScoreDecla = $freinageDecla->score ?? 0;
                                                $freinScoreDecla = $freinDecla->score ?? 0;
                                                $hydrauliqueScoreDecla = $hydrauliqueDecla->score ?? 0;
                                                $moteurDieselScoreDecla = $moteurDieselDecla->score ?? 0;
                                                $moteurEssenceScoreDecla = $moteurEssenceDecla->score ?? 0;
                                                $moteurElecScoreDecla = $moteurElecDecla->score ?? 0;
                                                $moteurThermiqueScoreDecla = $moteurThermiqueDecla->score ?? 0;
                                                $multiplexageScoreDecla = $multiplexageDecla->score ?? 0;
                                                $pneuScoreDecla = $pneuDecla->score ?? 0;
                                                $pontScoreDecla = $pontDecla->score ?? 0;
                                                $reducteurScoreDecla = $reducteurDecla->score ?? 0;
                                                $suspensionScoreDecla = $suspensionDecla->score ?? 0;
                                                $suspensionLameScoreDecla = $suspensionLameDecla->score ?? 0;
                                                $suspensionRessortScoreDecla = $suspensionRessortDecla->score ?? 0;
                                                $suspensionPneumatiqueScoreDecla = $suspensionPneumatiqueDecla->score ?? 0;
                                                $transversaleScoreDecla = $transversaleDecla->score ?? 0;
                                                
                                                $transmissionScoreMa = $transmissionMa->score ?? 0;
                                                $assistanceConduiteScoreMa = $assistanceConduiteMa->score ?? 0;
                                                $transfertScoreMa = $transfertMa->score ?? 0;
                                                $boiteScoreMa = $boiteMa->score ?? 0;
                                                $boiteManScoreMa = $boiteManMa->score ?? 0;
                                                $boiteAutoScoreMa = $boiteAutoMa->score ?? 0;
                                                $boiteVaCoScoreMa = $boiteVaCoMa->score ?? 0;
                                                $climatisationScoreMa = $climatisationMa->score ?? 0;
                                                $demiScoreMa = $demiMa->score ?? 0;
                                                $directionScoreMa = $directionMa->score ?? 0;
                                                $electriciteScoreMa = $electriciteMa->score ?? 0;
                                                $freiScoreMa = $freiMa->score ?? 0;
                                                $freinageElecScoreMa = $freinageElecMa->score ?? 0;
                                                $freinageScoreMa = $freinageMa->score ?? 0;
                                                $freinScoreMa = $freinMa->score ?? 0;
                                                $hydrauliqueScoreMa = $hydrauliqueMa->score ?? 0;
                                                $moteurDieselScoreMa = $moteurDieselMa->score ?? 0;
                                                $moteurEssenceScoreMa = $moteurEssenceMa->score ?? 0;
                                                $moteurElecScoreMa = $moteurElecMa->score ?? 0;
                                                $moteurThermiqueScoreMa = $moteurThermiqueMa->score ?? 0;
                                                $multiplexageScoreMa = $multiplexageMa->score ?? 0;
                                                $pneuScoreMa = $pneuMa->score ?? 0;
                                                $pontScoreMa = $pontMa->score ?? 0;
                                                $reducteurScoreMa = $reducteurMa->score ?? 0;
                                                $suspensionScoreMa = $suspensionMa->score ?? 0;
                                                $suspensionLameScoreMa = $suspensionLameMa->score ?? 0;
                                                $suspensionRessortScoreMa = $suspensionRessortMa->score ?? 0;
                                                $suspensionPneumatiqueScoreMa = $suspensionPneumatiqueMa->score ?? 0;
                                                $transversaleScoreMa = $transversaleMa->score ?? 0;
                                            
                                                if (isset($resultFac)) {
                                                    $percentageFac = ($resultFac['score'] * 100) / max(1, (float) $resultFac['total']);
                                                }
                                                if (isset($resultTechMa)) {
                                                    $percentageTechMa = ($resultTechMa['score'] * 100) / max(1, (float) $resultTechMa['total']);
                                                }
                                            
                                                $scoreTransmission = 0;
                                                if (isset($transmissionDecla, $transmissionMa)) {
                                                    for ($i = 0; $i < count($transmissionDecla["answers"]); ++$i) {
                                                        if (
                                                            $transmissionDecla["answers"][$i] == "Oui" &&
                                                            $transmissionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransmission;
                                                        }
                                                    }
                                                }
                                                $scoreTransfert = 0;
                                                if (isset($transfertDecla, $transfertMa)) {
                                                    for ($i = 0; $i < count($transfertDecla["answers"]); ++$i) {
                                                        if (
                                                            $transfertDecla["answers"][$i] == "Oui" &&
                                                            $transfertMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransfert;
                                                        }
                                                    }
                                                }
                                                $scoreAssistance= 0;
                                                if (isset($assistanceConduiteDecla, $assistanceConduiteMa)) {
                                                    for ($i = 0; $i < count($assistanceConduiteDecla["answers"]); ++$i) {
                                                        if (
                                                            $assistanceConduiteDecla["answers"][$i] == "Oui" &&
                                                            $assistanceConduiteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreAssistance;
                                                        }
                                                    }
                                                }
                                                $scoreBoite = 0;
                                                if (isset($boiteDecla, $boiteMa)) {
                                                    for ($i = 0; $i < count($boiteDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteDecla["answers"][$i] == "Oui" &&
                                                            $boiteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoite;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteMan = 0;
                                                if (isset($boiteManDecla, $boiteManMa)) {
                                                    for ($i = 0; $i < count($boiteManDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteManDecla["answers"][$i] == "Oui" &&
                                                            $boiteManMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteMan;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteAuto = 0;
                                                if (isset($boiteAutoDecla, $boiteAutoMa)) {
                                                    for ($i = 0; $i < count($boiteAutoDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteAutoDecla["answers"][$i] == "Oui" &&
                                                            $boiteAutoMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteAuto;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteVaCo = 0;
                                                if (isset($boiteVaCoDecla, $boiteVaCoMa)) {
                                                    for ($i = 0; $i < count($boiteVaCoDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteVaCoDecla["answers"][$i] == "Oui" &&
                                                            $boiteVaCoMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteVaCo;
                                                        }
                                                    }
                                                }
                                                $scoreClim = 0;
                                                if (isset($climatisationDecla, $climatisationMa)) {
                                                    for ($i = 0; $i < count($climatisationDecla["answers"]); ++$i) {
                                                        if (
                                                            $climatisationDecla["answers"][$i] == "Oui" &&
                                                            $climatisationMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreClim;
                                                        }
                                                    }
                                                }
                                                $scoreDemi = 0;
                                                if (isset($demiDecla, $demiMa)) {
                                                    for ($i = 0; $i < count($demiDecla["answers"]); ++$i) {
                                                        if (
                                                            $demiDecla["answers"][$i] == "Oui" &&
                                                            $demiMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreDemi;
                                                        }
                                                    }
                                                }
                                                $scoreDirection = 0;
                                                if (isset($directionDecla, $directionMa)) {
                                                    for ($i = 0; $i < count($directionDecla["answers"]); ++$i) {
                                                        if (
                                                            $directionDecla["answers"][$i] == "Oui" &&
                                                            $directionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreDirection;
                                                        }
                                                    }
                                                }
                                                $scoreElectricite = 0;
                                                if (isset($electriciteDecla, $electriciteMa)) {
                                                    for ($i = 0; $i < count($electriciteDecla["answers"]); ++$i) {
                                                        if (
                                                            $electriciteDecla["answers"][$i] == "Oui" &&
                                                            $electriciteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreElectricite;
                                                        }
                                                    }
                                                }
                                                $scoreFrein = 0;
                                                if (isset($freiDecla, $freiMa)) {
                                                    for ($i = 0; $i < count($freiDecla["answers"]); ++$i) {
                                                        if (
                                                            $freiDecla["answers"][$i] == "Oui" &&
                                                            $freiMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFrein;
                                                        }
                                                    }
                                                }
                                                $scoreFreinElec = 0;
                                                if (isset($freinageElecDecla, $freinageElecMa)) {
                                                    for ($i = 0; $i < count($freinageElecDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinageElecDecla["answers"][$i] == "Oui" &&
                                                            $freinageElecMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinElec;
                                                        }
                                                    }
                                                }
                                                $scoreFreinHydro = 0;
                                                if (isset($freinageDecla, $freinageMa)) {
                                                    for ($i = 0; $i < count($freinageDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinageDecla["answers"][$i] == "Oui" &&
                                                            $freinageMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinHydro;
                                                        }
                                                    }
                                                }
                                                $scoreFreinPneu = 0;
                                                if (isset($freinDecla, $freinMa)) {
                                                    for ($i = 0; $i < count($freinDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinDecla["answers"][$i] == "Oui" &&
                                                            $freinMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinPneu;
                                                        }
                                                    }
                                                }
                                                $scoreHydro = 0;
                                                if (isset($hydrauliqueDecla, $hydrauliqueMa)) {
                                                    for ($i = 0; $i < count($hydrauliqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $hydrauliqueDecla["answers"][$i] == "Oui" &&
                                                            $hydrauliqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreHydro;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurDiesel = 0;
                                                if (isset($moteurDieselDecla, $moteurDieselMa)) {
                                                    for ($i = 0; $i < count($moteurDieselDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurDieselDecla["answers"][$i] == "Oui" &&
                                                            $moteurDieselMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurDiesel;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurElec = 0;
                                                if (isset($moteurElecDecla, $moteurElecMa)) {
                                                    for ($i = 0; $i < count($moteurElecDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurElecDecla["answers"][$i] == "Oui" &&
                                                            $moteurElecMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurElec;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurEssence = 0;
                                                if (isset($moteurEssenceDecla, $moteurEssenceMa)) {
                                                    for ($i = 0; $i < count($moteurEssenceDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurEssenceDecla["answers"][$i] == "Oui" &&
                                                            $moteurEssenceMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurEssence;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurThermique = 0;
                                                if (isset($moteurThermiqueDecla, $moteurThermiqueMa)) {
                                                    for ($i = 0; $i < count($moteurThermiqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurThermiqueDecla["answers"][$i] == "Oui" &&
                                                            $moteurThermiqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurThermique;
                                                        }
                                                    }
                                                }
                                                $scoreMultiplexage = 0;
                                                if (isset($multiplexageDecla, $multiplexageMa)) {
                                                    for ($i = 0; $i < count($multiplexageDecla["answers"]); ++$i) {
                                                        if (
                                                            $multiplexageDecla["answers"][$i] == "Oui" &&
                                                            $multiplexageMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMultiplexage;
                                                        }
                                                    }
                                                }
                                                $scorePneu = 0;
                                                if (isset($pneuDecla, $pneuMa)) {
                                                    for ($i = 0; $i < count($pneuDecla["answers"]); ++$i) {
                                                        if (
                                                            $pneuDecla["answers"][$i] == "Oui" &&
                                                            $pneuMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scorePneu;
                                                        }
                                                    }
                                                }
                                                $scorePont = 0;
                                                if (isset($pontDecla, $pontMa)) {
                                                    for ($i = 0; $i < count($pontDecla["answers"]); ++$i) {
                                                        if (
                                                            $pontDecla["answers"][$i] == "Oui" &&
                                                            $pontMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scorePont;
                                                        }
                                                    }
                                                }
                                                $scoreRed = 0;
                                                if (isset($reducteurDecla, $reducteurMa)) {
                                                    for ($i = 0; $i < count($reducteurDecla["answers"]); ++$i) {
                                                        if (
                                                            $reducteurDecla["answers"][$i] == "Oui" &&
                                                            $reducteurMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreRed;
                                                        }
                                                    }
                                                }
                                                $scoreSuspension = 0;
                                                if (isset($suspensionDecla, $suspensionMa)) {
                                                    for ($i = 0; $i < count($suspensionDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionDecla["answers"][$i] == "Oui" &&
                                                            $suspensionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspension;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionLame = 0;
                                                if (isset($suspensionLameDecla, $suspensionLameMa)) {
                                                    for ($i = 0; $i < count($suspensionLameDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionLameDecla["answers"][$i] == "Oui" &&
                                                            $suspensionLameMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionLame;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionRessort = 0;
                                                if (isset($suspensionRessortDecla, $suspensionRessortMa)) {
                                                    for ($i = 0; $i < count($suspensionRessortDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionRessortDecla["answers"][$i] == "Oui" &&
                                                            $suspensionRessortMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionRessort;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionPneu = 0;
                                                if (isset($suspensionPneumatiqueDecla, $suspensionPneumatiqueMa)) {
                                                    for ($i = 0; $i < count($suspensionPneumatiqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionPneumatiqueDecla["answers"][$i] == "Oui" &&
                                                            $suspensionPneumatiqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionPneu;
                                                        }
                                                    }
                                                }
                                                $scoreTransversale = 0;
                                                if (isset($transversaleDecla, $transversaleMa)) {
                                                    for ($i = 0; $i < count($transversaleDecla["answers"]); ++$i) {
                                                        if (
                                                            $transversaleDecla["answers"][$i] == "Oui" &&
                                                            $transversaleMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransversale;
                                                        }
                                                    }
                                                }
                                                // if (isset($Toyota)) {
                                                    $toyotaFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $toyotaDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                
                                                    $toyotaScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $toyotaScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $toyotaScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $toyotaScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Suzuki)) {
                                                    $suzukiFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $transversaleTotalFac;
                                                    $suzukiDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $suzukiScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
                                                    
                                                    $suzukiScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $transversaleScoreFac;
                                                    $suzukiScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $transversaleScoreDecla;
                                                    $suzukiScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurElecScoreMa + $moteurDieselScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Mercedes)) {
                                                    $mercedesFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $mercedesDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla+ $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $mercedesScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $mercedesScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $mercedesScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $mercedesScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Peugeot)) {
                                                    $peugeotFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $peugeotDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                            
                                                    $peugeotScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $peugeotScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $peugeotScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $peugeotScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Citroen)) {
                                                    $citroenFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $citroenDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                            
                                                    $citroenScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $citroenScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $citroenScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $citroenScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($KingLong)) {
                                                    $kingLongFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $kingLongDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $kingLongScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
                                                    
                                                    $kingLongScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $kingLongScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $kingLongScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Fuso)) {
                                                    $fusoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $fusoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $fusoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $fusoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $fusoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $fusoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Hino)) {
                                                    $hinoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $hinoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $hinoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $hinoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $hinoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $hinoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($RenalutTruck)) {
                                                    $renaultTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $renaultTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $renaultTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $renaultTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $renaultTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $renaultTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($MercedesTruck)) {
                                                    $mercedesTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $mercedesTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $mercedesTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $mercedesTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $mercedesTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $mercedesTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Sinotruk)) {
                                                    $sinotrukFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $sinotrukDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $sinotrukScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $sinotrukScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $sinotrukScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $sinotrukScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Jcb)) {
                                                    $jcbFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $jcbDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $jcbScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $jcbScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $jcbScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $jcbScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Lovol)) {
                                                    $lovolFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $lovolDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $lovolScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $lovolScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $lovolScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $lovolScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($ToyotaBt)) {
                                                    $toyotaBtFac = $assistanceConduiteTotalFac + $boiteTotalFac + $climatisationTotalFac + $directionTotalFac + $electriciteTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurElecTotalFac + $multiplexageTotalFac + $pneuTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $toyotaBtDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $climatisationTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurElecTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $toyotaBtScore = $scoreAssistance + $scoreBoite + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurElec + $scoreMultiplexage + $scorePneu + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $toyotaBtScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $climatisationScoreFac + $directionScoreFac + $electriciteScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurElecScoreFac + $multiplexageScoreFac + $pneuScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $toyotaBtScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $climatisationScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurElecScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $toyotaBtScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $climatisationScoreMa + $directionScoreMa + $electriciteScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurElecScoreMa + $multiplexageScoreMa + $pneuScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($ToyotaForflift)) {
                                                    $toyotaForfliftFac = $assistanceConduiteTotalFac + $boiteTotalFac + $boiteAutoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $toyotaForfliftDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteAutoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $toyotaForfliftScore = $scoreAssistance + $scoreBoite + $scoreBoiteAuto + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $toyotaForfliftScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $boiteAutoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $toyotaForfliftScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteAutoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $toyotaForfliftScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $boiteAutoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityTransmission) == 'Transmission') {
                                                    $specialityTransmissionFac = $transmissionTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $demiTotalFac + $pontTotalFac + $reducteurTotalFac + $transversaleTotalFac;
                                                    $specialityTransmissionDecla = $transmissionTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteAutoTotalDecla + $demiTotalDecla + $pneuTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityTransmissionScore = $scoreTransmission + $scoreTransfert + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreDemi + $scorePont + $scoreRed + $scoreTransversale;
                                                    
                                                    $specialityTransmissionScoreFac = $transmissionScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $demiScoreFac + $pontScoreFac + $reducteurScoreFac + $transversaleScoreFac;
                                                    $specialityTransmissionScoreDecla = $transmissionScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $demiScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
                                                    $specialityTransmissionScoreMa = $transmissionScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $demiScoreMa + $pontScoreMa + $reducteurScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityMoteur) == 'Moteur') {
                                                    $specialityMoteurFac = $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $transversaleTotalFac;
                                                    $specialityMoteurDecla = $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityMoteurScore = $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreTransversale;
                                                    
                                                    $specialityMoteurScoreFac = $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $transversaleScoreFac;
                                                    $specialityMoteurScoreDecla = $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $transversaleScoreDecla;
                                                    $specialityMoteurScoreMa = $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityElectricite) == 'Electricité et Electronique') {
                                                    $specialityElectriciteFac = $assistanceConduiteTotalFac + $climatisationTotalFac + $electriciteTotalFac + $multiplexageTotalFac + $transversaleTotalFac;
                                                    $specialityElectriciteDecla = $assistanceConduiteTotalDecla + $climatisationTotalDecla + $electriciteTotalDecla + $multiplexageTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityElectriciteScore = $scoreAssistance + $scoreClim + $scoreElectricite + $scoreMultiplexage + $scoreTransversale;
                                                    
                                                    $specialityElectriciteScoreFac = $assistanceConduiteScoreFac + $climatisationScoreFac + $electriciteScoreFac + $multiplexageScoreFac + $transversaleScoreFac;
                                                    $specialityElectriciteScoreDecla = $assistanceConduiteScoreDecla + $climatisationScoreDecla + $electriciteScoreDecla + $multiplexageScoreDecla + $transversaleScoreDecla;
                                                    $specialityElectriciteScoreMa = $assistanceConduiteScoreMa + $climatisationScoreMa + $electriciteScoreMa + $multiplexageScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityHydraulique) == 'Hydraulique') {
                                                    $specialityHydrauliqueFac =  $freiTotalFac + $freinTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $pneuTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $specialityHydrauliqueDecla = $freiTotalDecla + $freinTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla + $hydrauliqueTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityHydrauliqueScore = $scoreFrein + $scoreFreinPneu + $scoreFreinElec + $scoreHydro + $scoreFreinHydro + $scorePneu + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $specialityHydrauliqueScoreFac = $freiScoreFac + $freinScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $pneuScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $specialityHydrauliqueScoreDecla = $freiScoreDecla + $freinScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $pneuScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $specialityHydrauliqueScoreMa = $freiScoreMa + $freinScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $pneuScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                
                                            ?>
                                        <tr class="odd" style="">
                                            <td class=" sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; background-color: #e6f2fb; position: sticky; left: 0;">
                                            <?php echo $managerTech->firstName ?> <?php echo $managerTech->lastName ?>
                                            </td>
                                            <td class=" sorting text-black text-center table-light gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; background-color: #e6f2fb; position: sticky; left: 85px;">
                                            <?php echo $managerTech->level ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php $brandselectedLevel = [];
                                            foreach ($managerTech['brand'.$selectedLevel] as $brand) {
                                                array_push($brandselectedLevel, $brand);
                                            }
                                            if ($selectedLevel != 'Junior') {
                                                $specialityselectedLevel = [];
                                                foreach ($managerTech['speciality'.$selectedLevel] as $specialite) {
                                                    array_push($specialityselectedLevel, $specialite);
                                                }
                                            }
                                            if (
                                                in_array("KING LONG", $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $kingLongFac != 0 && $kingLongDecla != 0) { ?>
                                                <?php $percentageKingLong = round((($kingLongScoreFac * 100) / max(1, (float) $kingLongFac) + ($kingLongScore * 100) / max(1, (float) $kingLongDecla)) / 2); ?>
                                                <?php if($percentageKingLong < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageKingLong ?>
                                                </span>
                                                <?php } else if($percentageKingLong < 80 ) { ?>
                                                    <?php if($percentageKingLong > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageKingLong ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageKingLong >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageKingLong ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('FUSO', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $fusoFac != 0 && $fusoDecla != 0) { ?>
                                                <?php $percentageFuso = round((($fusoScoreFac * 100) / max(1, (float) $fusoFac) + ($fusoScore * 100) / max(1, (float) $fusoDecla)) / 2); ?>
                                                <?php if($percentageFuso < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageFuso ?>
                                                </span>
                                                <?php } else if($percentageFuso < 80 ) { ?>
                                                    <?php if($percentageFuso > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageFuso ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageFuso >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageFuso ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('HINO', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $hinoFac != 0 && $hinoDecla != 0) { ?>
                                                <?php $percentageHino = round((($hinoScoreFac * 100) / max(1, (float) $hinoFac) + ($hinoScore * 100) / max(1, (float) $hinoDecla)) / 2); ?>
                                                <?php if($percentageHino < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageHino ?>
                                                </span>
                                                <?php } else if($percentageHino < 80 ) { ?>
                                                    <?php if($percentageHino > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageHino ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageHino >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageHino ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('MERCEDES TRUCK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $mercedesTruckFac != 0 && $mercedesTruckDecla != 0) { ?>
                                                <?php $percentageMercedesTruck = round((($mercedesTruckScoreFac * 100) / max(1, (float) $mercedesTruckFac) + ($mercedesTruckScore * 100) / max(1, (float) $mercedesTruckDecla)) / 2); ?>
                                                <?php if($percentageMercedesTruck < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageMercedesTruck ?>
                                                </span>
                                                <?php } else if($percentageMercedesTruck < 80 ) { ?>
                                                    <?php if($percentageMercedesTruck > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageMercedesTruck ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageMercedesTruck >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageMercedesTruck ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('RENAULT TRUCK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $renaultTruckFac != 0 && $renaultTruckDecla != 0) { ?>
                                                <?php $percentageRenaultTruck = round((($renaultTruckScoreFac * 100) / max(1, (float) $renaultTruckFac) + ($renaultTruckScore * 100) / max(1, (float) $renaultTruckDecla)) / 2); ?>
                                                <?php if($percentageRenaultTruck < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageRenaultTruck ?>
                                                </span>
                                                <?php } else if($percentageRenaultTruck < 80 ) { ?>
                                                    <?php if($percentageRenaultTruck > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageRenaultTruck ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageRenaultTruck >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageRenaultTruck ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('SINOTRUK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $sinotrukFac != 0 && $sinotrukDecla != 0) { ?>
                                                <?php $percentageSinotruk = round((($sinotrukScoreFac * 100) / max(1, (float) $sinotrukFac) + ($sinotrukScore * 100) / max(1, (float) $sinotrukDecla)) / 2); ?>
                                                <?php if($percentageSinotruk < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSinotruk ?>
                                                </span>
                                                <?php } else if($percentageSinotruk < 80 ) { ?>
                                                    <?php if($percentageSinotruk > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSinotruk ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSinotruk >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSinotruk ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('TOYOTA BT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaBtFac != 0 && $toyotaBtDecla != 0) { ?>
                                                <?php $percentageToyotaBt = round((($toyotaBtScoreFac * 100) / max(1, (float) $toyotaBtFac) + ($toyotaBtScore * 100) / max(1, (float) $toyotaBtDecla)) / 2); ?>
                                                <?php if($percentageToyotaBt < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyotaBt ?>
                                                </span>
                                                <?php } else if($percentageToyotaBt < 80 ) { ?>
                                                    <?php if($percentageToyotaBt > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyotaBt ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyotaBt >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyotaBt ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('TOYOTA FORKLIFT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaForfliftFac != 0 && $toyotaForfliftDecla != 0) { ?>
                                                <?php $percentageToyotaForflift = round((($toyotaForfliftScoreFac * 100) / max(1, (float) $toyotaForfliftFac) + ($toyotaForfliftScore * 100) / max(1, (float) $toyotaForfliftDecla)) / 2); ?>
                                                <?php if($percentageToyotaForflift < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyotaForflift ?>
                                                </span>
                                                <?php } else if($percentageToyotaForflift < 80 ) { ?>
                                                    <?php if($percentageToyotaForflift > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyotaForflift ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyotaForflift >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyotaForflift ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php  if (
                                                in_array('JCB', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $jcbFac != 0 && $jcbDecla != 0) { ?>
                                                <?php $percentageJcb = round((($jcbScoreFac * 100) / max(1, (float) $jcbFac) + ($jcbScore * 100) / max(1, (float) $jcbDecla)) / 2); ?>
                                                <?php if($percentageJcb < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageJcb ?>
                                                </span>
                                                <?php } else if($percentageJcb < 80 ) { ?>
                                                    <?php if($percentageJcb > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageJcb ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageJcb >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageJcb ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('LOVOL', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $lovolFac != 0 && $lovolDecla != 0) { ?>
                                                <?php $percentageLovol = round((($lovolScoreFac * 100) / max(1, (float) $lovolFac) + ($lovolScore * 100) / max(1, (float) $lovolDecla)) / 2); ?>
                                                <?php if($percentageLovol < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageLovol ?>
                                                </span>
                                                <?php } else if($percentageLovol < 80 ) { ?>
                                                    <?php if($percentageLovol > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageLovol ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageLovol >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageLovol ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('CITROEN', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $citroenFac != 0 && $citroenDecla != 0) { ?>
                                                <?php $percentageCitroen = round((($citroenScoreFac * 100) / max(1, (float) $citroenFac) + ($citroenScore * 100) / max(1, (float) $citroenDecla)) / 2); ?>
                                                <?php if($percentageCitroen < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageCitroen ?>
                                                </span>
                                                <?php } else if($percentageCitroen < 80 ) { ?>
                                                    <?php if($percentageCitroen > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageCitroen ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageCitroen >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageCitroen ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('MERCEDES', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $mercedesFac != 0 && $mercedesDecla != 0) { ?>
                                                <?php $percentageMercedes = round((($mercedesScoreFac * 100) / max(1, (float) $mercedesFac) + ($mercedesScore * 100) / max(1, (float) $mercedesDecla)) / 2); ?>
                                                <?php if($percentageMercedes < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageMercedes ?>
                                                </span>
                                                <?php } else if($percentageMercedes < 80 ) { ?>
                                                    <?php if($percentageMercedes > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageMercedes ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageMercedes >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageMercedes ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('PEUGEOT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $peugeotFac != 0 && $peugeotDecla != 0) { ?>
                                                <?php $percentagePeugeot = round((($peugeotScoreFac * 100) / max(1, (float) $peugeotFac) + ($peugeotScore * 100) / max(1, (float) $peugeotDecla)) / 2); ?>
                                                <?php if($percentagePeugeot < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentagePeugeot ?>
                                                </span>
                                                <?php } else if($percentagePeugeot < 80 ) { ?>
                                                    <?php if($percentagePeugeot > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentagePeugeot ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentagePeugeot >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentagePeugeot ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('SUZUKI', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $suzukiFac != 0 && $suzukiDecla != 0) { ?>
                                                <?php $percentageSuzuki = round((($suzukiScoreFac * 100) / max(1, (float) $suzukiFac) + ($suzukiScore * 100) / max(1, (float) $suzukiDecla)) / 2); ?>
                                                <?php if($percentageSuzuki < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSuzuki ?>
                                                </span>
                                                <?php } else if($percentageSuzuki < 80 ) { ?>
                                                    <?php if($percentageSuzuki > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSuzuki ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSuzuki >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSuzuki ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('TOYOTA', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaFac != 0 && $toyotaDecla != 0) { ?>
                                                <?php $percentageToyota = round((($toyotaScoreFac * 100) / max(1, (float) $toyotaFac) + ($toyotaScore * 100) / max(1, (float) $toyotaDecla)) / 2); ?>
                                                <?php if($percentageToyota < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyota ?>
                                                </span>
                                                <?php } else if($percentageToyota < 80 ) { ?>
                                                    <?php if($percentageToyota > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyota ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyota >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyota ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                        <?php if ($selectedLevel != 'Junior') { ?>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('Electricité et Electronique', $specialityselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $specialityElectriciteFac != 0 && $specialityElectriciteDecla != 0) { ?>
                                                <?php $percentageSpecialityElectricite = round((($specialityElectriciteScoreFac * 100) / max(1, (float) $specialityElectriciteFac) + ($specialityElectriciteScore * 100) / max(1, (float) $specialityElectriciteDecla)) / 2); ?>
                                                <?php if($percentageSpecialityElectricite < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSpecialityElectricite ?>
                                                </span>
                                                <?php } else if($percentageSpecialityElectricite < 80 ) { ?>
                                                    <?php if($percentageSpecialityElectricite > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSpecialityElectricite ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSpecialityElectricite >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSpecialityElectricite ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('Hydraulique', $specialityselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $specialityHydrauliqueFac != 0 && $specialityHydrauliqueDecla != 0) { ?>
                                                <?php $percentageSpecialityHydraulique = round((($specialityHydrauliqueScoreFac * 100) / max(1, (float) $specialityHydrauliqueFac) + ($specialityHydrauliqueScore * 100) / max(1, (float) $specialityHydrauliqueDecla)) / 2); ?>
                                                <?php if($percentageSpecialityHydraulique < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSpecialityHydraulique ?>
                                                </span>
                                                <?php } else if($percentageSpecialityHydraulique < 80 ) { ?>
                                                    <?php if($percentageSpecialityHydraulique > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSpecialityHydraulique ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSpecialityHydraulique >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSpecialityHydraulique ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('Moteur', $specialityselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $specialityMoteurFac != 0 && $specialityMoteurDecla != 0) { ?>
                                                <?php $percentageSpecialityMoteur = round((($specialityMoteurScoreFac * 100) / max(1, (float) $specialityMoteurFac) + ($specialityMoteurScore * 100) / max(1, (float) $specialityMoteurDecla)) / 2); ?>
                                                <?php if($percentageSpecialityMoteur < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSpecialityMoteur ?>
                                                </span>
                                                <?php } else if($percentageSpecialityMoteur < 80 ) { ?>
                                                    <?php if($percentageSpecialityMoteur > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSpecialityMoteur ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSpecialityMoteur >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSpecialityMoteur ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                            <?php if (
                                                in_array('Transmission', $specialityselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true && $allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $specialityTransmissionFac != 0 && $specialityTransmissionDecla != 0) { ?>
                                                <?php $percentageSpecialityTransmission = round((($specialityTransmissionScoreFac * 100) / max(1, (float) $specialityTransmissionFac) + ($specialityTransmissionScore * 100) / max(1, (float) $specialityTransmissionDecla)) / 2); ?>
                                                <?php if($percentageSpecialityTransmission < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSpecialityTransmission ?>
                                                </span>
                                                <?php } else if($percentageSpecialityTransmission < 80 ) { ?>
                                                    <?php if($percentageSpecialityTransmission > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSpecialityTransmission ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSpecialityTransmission >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSpecialityTransmission ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                        <?php } ?>
                                            <td class="text-center" style="background-color: #e6f2fb;">
                                                <?php if (isset($resultFac) && isset($resultTechMa)) { ?>
                                                <?php $percentage = round((($resultFac->score * 100) / max(1, (float) $resultFac->total) + ($resultTechMa->score * 100) / max(1, (float) $resultTechMa->total) ) / 2); ?>
                                                <?php if($percentage < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentage ?>
                                                </span>
                                                <?php } else if($percentage < 80 ) { ?>
                                                    <?php if($percentage > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentage ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentage >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentage ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php } } ?>
                                        
                                        <?php foreach ($technicians as $technician) {
                                            $allocateFac = $allocations->findOne([
                                                '$and' => [
                                                    [
                                                        "user" => new MongoDB\BSON\ObjectId($technician),
                                                        "level" => $selectedLevel,
                                                        "type" => "Factuel",
                                                    ],
                                                ],
                                            ]);
                                            $allocateDecla = $allocations->findOne([
                                                '$and' => [
                                                    [
                                                        "user" => new MongoDB\BSON\ObjectId($technician),
                                                        "level" => $selectedLevel,
                                                        "type" => "Declaratif",
                                                    ],
                                                ],
                                            ]);
                                            if (isset($allocateFac) && isset($allocateDecla)) {
                                                $tech = $users->findOne([
                                                    '$and' => [
                                                        [
                                                            "_id" => $allocateFac['user'],
                                                            "active" => true,
                                                        ],
                                                    ],
                                                ]);
                                                $techManagerId = isset($tech->manager) ? $tech->manager : null;
                                                $transmissionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Arbre de Transmission",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $assistanceConduiteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Assistance à la Conduite",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transfertMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Transfert"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Boite de Vitesse"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteManMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Mécanique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteAutoMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse Automatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $boiteVaCoMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Boite de Vitesse à Variation Continue",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $climatisationMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Climatisation"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $demiMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Demi Arbre de Roue"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $directionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Direction"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $electriciteMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Electricité et Electronique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freiMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Freinage"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageElecMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Electromagnétique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinageMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Hydraulique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $freinMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Freinage Pneumatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $hydrauliqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Hydraulique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurDieselMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Diesel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurElecMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Electrique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurEssenceMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Essence"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $moteurThermiqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Moteur Thermique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $multiplexageMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Réseaux de Communication"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pneuMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pneumatique"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $pontMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Pont"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $reducteurMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Reducteur"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionLameMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension à Lame"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionRessortMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Suspension Ressort"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionPneumatiqueFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Suspension Pneumatique",
                                                        ],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $suspensionPneumatiqueDecla = $results->findOne(
                                                    [
                                                        '$and' => [
                                                            [
                                                                "user" => new MongoDB\BSON\ObjectId(
                                                                    $tech->_id
                                                                ),
                                                            ],
                                                            ["level" => $selectedLevel],
                                                            [
                                                                "speciality" =>
                                                                    "Suspension Pneumatique",
                                                            ],
                                                            ["type" => "Declaratif"],
                                                            ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                        ],
                                                    ]
                                                );
                                                $suspensionPneumatiqueMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        [
                                                            "speciality" =>
                                                                "Suspension Pneumatique",
                                                        ],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transversaleFac = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["type" => "Factuel"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transversaleDecla = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Technicien"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                // var_dump($transversaleDecla);
                                                $transversaleMa = $results->findOne([
                                                    '$and' => [
                                                        [
                                                            "user" => new MongoDB\BSON\ObjectId(
                                                                $tech->_id
                                                            ),
                                                        ],
                                                        [
                                                            "manager" => new MongoDB\BSON\ObjectId(
                                                                $techManagerId
                                                            ),
                                                        ],
                                                        ["level" => $selectedLevel],
                                                        ["speciality" => "Transversale"],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultFac = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($tech->_id)],
                                                        ["type" => "Factuel"],
                                                        ["typeR" => "Technicien"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultDecla = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($tech->_id)],
                                                        ["type" => "Declaratif"],
                                                        ["typeR" => "Techniciens"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultMa = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($tech->_id)],
                                                        ["manager" => new MongoDB\BSON\ObjectId($techManagerId)],
                                                        ["typeR" => "Managers"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $resultTechMa = $results->findOne([
                                                    '$and' => [
                                                        ["user" => new MongoDB\BSON\ObjectId($tech->_id)],
                                                        ["manager" => new MongoDB\BSON\ObjectId($techManagerId)],
                                                        ["typeR" => "Technicien - Manager"],
                                                        ["level" => $selectedLevel],
                                                        ["active" => true],
                                                    ],
                                                ]);
                                                $transmissionTotalFac = $transmissionFac->total ?? 0;
                                                $assistanceConduiteTotalFac = $assistanceConduiteFac->total ?? 0;
                                                $transfertTotalFac = $transfertFac->total ?? 0;
                                                $boiteTotalFac = $boiteFac->total ?? 0;
                                                $boiteManTotalFac = $boiteManFac->total ?? 0;
                                                $boiteAutoTotalFac = $boiteAutoFac->total ?? 0;
                                                $boiteVaCoTotalFac = $boiteVaCoFac->total ?? 0;
                                                $climatisationTotalFac = $climatisationFac->total ?? 0;
                                                $demiTotalFac = $demiFac->total ?? 0;
                                                $directionTotalFac = $directionFac->total ?? 0;
                                                $electriciteTotalFac = $electriciteFac->total ?? 0;
                                                $freiTotalFac = $freiFac->total ?? 0;
                                                $freinageElecTotalFac = $freinageElecFac->total ?? 0;
                                                $freinageTotalFac = $freinageFac->total ?? 0;
                                                $freinTotalFac = $freinFac->total ?? 0;
                                                $hydrauliqueTotalFac = $hydrauliqueFac->total ?? 0;
                                                $moteurDieselTotalFac = $moteurDieselFac->total ?? 0;
                                                $moteurEssenceTotalFac = $moteurEssenceFac->total ?? 0;
                                                $moteurElecTotalFac = $moteurElecFac->total ?? 0;
                                                $moteurThermiqueTotalFac = $moteurThermiqueFac->total ?? 0;
                                                $multiplexageTotalFac = $multiplexageFac->total ?? 0;
                                                $pneuTotalFac = $pneuFac->total ?? 0;
                                                $pontTotalFac = $pontFac->total ?? 0;
                                                $reducteurTotalFac = $reducteurFac->total ?? 0;
                                                $suspensionTotalFac = $suspensionFac->total ?? 0;
                                                $suspensionLameTotalFac = $suspensionLameFac->total ?? 0;
                                                $suspensionRessortTotalFac = $suspensionRessortFac->total ?? 0;
                                                $suspensionPneumatiqueTotalFac = $suspensionPneumatiqueFac->total ?? 0;
                                                $transversaleTotalFac = $transversaleFac->total ?? 0;
                                                
                                                $transmissionTotalDecla = $transmissionDecla->total ?? 0;
                                                $assistanceConduiteTotalDecla = $assistanceConduiteDecla->total ?? 0;
                                                $transfertTotalDecla = $transfertDecla->total ?? 0;
                                                $boiteTotalDecla = $boiteDecla->total ?? 0;
                                                $boiteManTotalDecla = $boiteManDecla->total ?? 0;
                                                $boiteAutoTotalDecla = $boiteAutoDecla->total ?? 0;
                                                $boiteVaCoTotalDecla = $boiteVaCoDecla->total ?? 0;
                                                $climatisationTotalDecla = $climatisationDecla->total ?? 0;
                                                $demiTotalDecla = $demiDecla->total ?? 0;
                                                $directionTotalDecla = $directionDecla->total ?? 0;
                                                $electriciteTotalDecla = $electriciteDecla->total ?? 0;
                                                $freiTotalDecla = $freiDecla->total ?? 0;
                                                $freinageElecTotalDecla = $freinageElecDecla->total ?? 0;
                                                $freinageTotalDecla = $freinageDecla->total ?? 0;
                                                $freinTotalDecla = $freinDecla->total ?? 0;
                                                $hydrauliqueTotalDecla = $hydrauliqueDecla->total ?? 0;
                                                $moteurDieselTotalDecla = $moteurDieselDecla->total ?? 0;
                                                $moteurEssenceTotalDecla = $moteurEssenceDecla->total ?? 0;
                                                $moteurElecTotalDecla = $moteurElecDecla->total ?? 0;
                                                $moteurThermiqueTotalDecla = $moteurThermiqueDecla->total ?? 0;
                                                $multiplexageTotalDecla = $multiplexageDecla->total ?? 0;
                                                $pneuTotalDecla = $pneuDecla->total ?? 0;
                                                $pontTotalDecla = $pontDecla->total ?? 0;
                                                $reducteurTotalDecla = $reducteurDecla->total ?? 0;
                                                $suspensionTotalDecla = $suspensionDecla->total ?? 0;
                                                $suspensionLameTotalDecla = $suspensionLameDecla->total ?? 0;
                                                $suspensionRessortTotalDecla = $suspensionRessortDecla->total ?? 0;
                                                $suspensionPneumatiqueTotalDecla = $suspensionPneumatiqueDecla->total ?? 0;
                                                $transversaleTotalDecla = $transversaleDecla->total ?? 0;
                                            
                                                $transmissionScoreFac = $transmissionFac->score ?? 0;
                                                $assistanceConduiteScoreFac = $assistanceConduiteFac->score ?? 0;
                                                $transfertScoreFac = $transfertFac->score ?? 0;
                                                $boiteScoreFac = $boiteFac->score ?? 0;
                                                $boiteManScoreFac = $boiteManFac->score ?? 0;
                                                $boiteAutoScoreFac = $boiteAutoFac->score ?? 0;
                                                $boiteVaCoScoreFac = $boiteVaCoFac->score ?? 0;
                                                $climatisationScoreFac = $climatisationFac->score ?? 0;
                                                $demiScoreFac = $demiFac->score ?? 0;
                                                $directionScoreFac = $directionFac->score ?? 0;
                                                $electriciteScoreFac = $electriciteFac->score ?? 0;
                                                $freiScoreFac = $freiFac->score ?? 0;
                                                $freinageElecScoreFac = $freinageElecFac->score ?? 0;
                                                $freinageScoreFac = $freinageFac->score ?? 0;
                                                $freinScoreFac = $freinFac->score ?? 0;
                                                $hydrauliqueScoreFac = $hydrauliqueFac->score ?? 0;
                                                $moteurDieselScoreFac = $moteurDieselFac->score ?? 0;
                                                $moteurEssenceScoreFac = $moteurEssenceFac->score ?? 0;
                                                $moteurElecScoreFac = $moteurElecFac->score ?? 0;
                                                $moteurThermiqueScoreFac = $moteurThermiqueFac->score ?? 0;
                                                $multiplexageScoreFac = $multiplexageFac->score ?? 0;
                                                $pneuScoreFac = $pneuFac->score ?? 0;
                                                $pontScoreFac = $pontFac->score ?? 0;
                                                $reducteurScoreFac = $reducteurFac->score ?? 0;
                                                $suspensionScoreFac = $suspensionFac->score ?? 0;
                                                $suspensionLameScoreFac = $suspensionLameFac->score ?? 0;
                                                $suspensionRessortScoreFac = $suspensionRessortFac->score ?? 0;
                                                $suspensionPneumatiqueScoreFac = $suspensionPneumatiqueFac->score ?? 0;
                                                $transversaleScoreFac = $transversaleFac->score ?? 0;
                                                
                                                $transmissionScoreDecla = $transmissionDecla->score ?? 0;
                                                $assistanceConduiteScoreDecla = $assistanceConduiteDecla->score ?? 0;
                                                $transfertScoreDecla = $transfertDecla->score ?? 0;
                                                $boiteScoreDecla = $boiteDecla->score ?? 0;
                                                $boiteManScoreDecla = $boiteManDecla->score ?? 0;
                                                $boiteAutoScoreDecla = $boiteAutoDecla->score ?? 0;
                                                $boiteVaCoScoreDecla = $boiteVaCoDecla->score ?? 0;
                                                $climatisationScoreDecla = $climatisationDecla->score ?? 0;
                                                $demiScoreDecla = $demiDecla->score ?? 0;
                                                $directionScoreDecla = $directionDecla->score ?? 0;
                                                $electriciteScoreDecla = $electriciteDecla->score ?? 0;
                                                $freiScoreDecla = $freiDecla->score ?? 0;
                                                $freinageElecScoreDecla = $freinageElecDecla->score ?? 0;
                                                $freinageScoreDecla = $freinageDecla->score ?? 0;
                                                $freinScoreDecla = $freinDecla->score ?? 0;
                                                $hydrauliqueScoreDecla = $hydrauliqueDecla->score ?? 0;
                                                $moteurDieselScoreDecla = $moteurDieselDecla->score ?? 0;
                                                $moteurEssenceScoreDecla = $moteurEssenceDecla->score ?? 0;
                                                $moteurElecScoreDecla = $moteurElecDecla->score ?? 0;
                                                $moteurThermiqueScoreDecla = $moteurThermiqueDecla->score ?? 0;
                                                $multiplexageScoreDecla = $multiplexageDecla->score ?? 0;
                                                $pneuScoreDecla = $pneuDecla->score ?? 0;
                                                $pontScoreDecla = $pontDecla->score ?? 0;
                                                $reducteurScoreDecla = $reducteurDecla->score ?? 0;
                                                $suspensionScoreDecla = $suspensionDecla->score ?? 0;
                                                $suspensionLameScoreDecla = $suspensionLameDecla->score ?? 0;
                                                $suspensionRessortScoreDecla = $suspensionRessortDecla->score ?? 0;
                                                $suspensionPneumatiqueScoreDecla = $suspensionPneumatiqueDecla->score ?? 0;
                                                $transversaleScoreDecla = $transversaleDecla->score ?? 0;
                                                
                                                $transmissionScoreMa = $transmissionMa->score ?? 0;
                                                $assistanceConduiteScoreMa = $assistanceConduiteMa->score ?? 0;
                                                $transfertScoreMa = $transfertMa->score ?? 0;
                                                $boiteScoreMa = $boiteMa->score ?? 0;
                                                $boiteManScoreMa = $boiteManMa->score ?? 0;
                                                $boiteAutoScoreMa = $boiteAutoMa->score ?? 0;
                                                $boiteVaCoScoreMa = $boiteVaCoMa->score ?? 0;
                                                $climatisationScoreMa = $climatisationMa->score ?? 0;
                                                $demiScoreMa = $demiMa->score ?? 0;
                                                $directionScoreMa = $directionMa->score ?? 0;
                                                $electriciteScoreMa = $electriciteMa->score ?? 0;
                                                $freiScoreMa = $freiMa->score ?? 0;
                                                $freinageElecScoreMa = $freinageElecMa->score ?? 0;
                                                $freinageScoreMa = $freinageMa->score ?? 0;
                                                $freinScoreMa = $freinMa->score ?? 0;
                                                $hydrauliqueScoreMa = $hydrauliqueMa->score ?? 0;
                                                $moteurDieselScoreMa = $moteurDieselMa->score ?? 0;
                                                $moteurEssenceScoreMa = $moteurEssenceMa->score ?? 0;
                                                $moteurElecScoreMa = $moteurElecMa->score ?? 0;
                                                $moteurThermiqueScoreMa = $moteurThermiqueMa->score ?? 0;
                                                $multiplexageScoreMa = $multiplexageMa->score ?? 0;
                                                $pneuScoreMa = $pneuMa->score ?? 0;
                                                $pontScoreMa = $pontMa->score ?? 0;
                                                $reducteurScoreMa = $reducteurMa->score ?? 0;
                                                $suspensionScoreMa = $suspensionMa->score ?? 0;
                                                $suspensionLameScoreMa = $suspensionLameMa->score ?? 0;
                                                $suspensionRessortScoreMa = $suspensionRessortMa->score ?? 0;
                                                $suspensionPneumatiqueScoreMa = $suspensionPneumatiqueMa->score ?? 0;
                                                $transversaleScoreMa = $transversaleMa->score ?? 0;
                                            
                                                if (isset($resultFac)) {
                                                    $percentageFac = ($resultFac['score'] * 100) / max(1, (float) $resultFac['total']);
                                                }
                                                if (isset($resultTechMa)) {
                                                    $percentageTechMa = ($resultTechMa['score'] * 100) / max(1, (float) $resultTechMa['total']);
                                                }
                                            
                                                $scoreTransmission = 0;
                                                if (isset($transmissionDecla, $transmissionMa)) {
                                                    for ($i = 0; $i < count($transmissionDecla["answers"]); ++$i) {
                                                        if (
                                                            $transmissionDecla["answers"][$i] == "Oui" &&
                                                            $transmissionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransmission;
                                                        }
                                                    }
                                                }
                                                $scoreTransfert = 0;
                                                if (isset($transfertDecla, $transfertMa)) {
                                                    for ($i = 0; $i < count($transfertDecla["answers"]); ++$i) {
                                                        if (
                                                            $transfertDecla["answers"][$i] == "Oui" &&
                                                            $transfertMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransfert;
                                                        }
                                                    }
                                                }
                                                $scoreAssistance= 0;
                                                if (isset($assistanceConduiteDecla, $assistanceConduiteMa)) {
                                                    for ($i = 0; $i < count($assistanceConduiteDecla["answers"]); ++$i) {
                                                        if (
                                                            $assistanceConduiteDecla["answers"][$i] == "Oui" &&
                                                            $assistanceConduiteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreAssistance;
                                                        }
                                                    }
                                                }
                                                $scoreBoite = 0;
                                                if (isset($boiteDecla, $boiteMa)) {
                                                    for ($i = 0; $i < count($boiteDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteDecla["answers"][$i] == "Oui" &&
                                                            $boiteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoite;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteMan = 0;
                                                if (isset($boiteManDecla, $boiteManMa)) {
                                                    for ($i = 0; $i < count($boiteManDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteManDecla["answers"][$i] == "Oui" &&
                                                            $boiteManMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteMan;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteAuto = 0;
                                                if (isset($boiteAutoDecla, $boiteAutoMa)) {
                                                    for ($i = 0; $i < count($boiteAutoDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteAutoDecla["answers"][$i] == "Oui" &&
                                                            $boiteAutoMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteAuto;
                                                        }
                                                    }
                                                }
                                                $scoreBoiteVaCo = 0;
                                                if (isset($boiteVaCoDecla, $boiteVaCoMa)) {
                                                    for ($i = 0; $i < count($boiteVaCoDecla["answers"]); ++$i) {
                                                        if (
                                                            $boiteVaCoDecla["answers"][$i] == "Oui" &&
                                                            $boiteVaCoMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreBoiteVaCo;
                                                        }
                                                    }
                                                }
                                                $scoreClim = 0;
                                                if (isset($climatisationDecla, $climatisationMa)) {
                                                    for ($i = 0; $i < count($climatisationDecla["answers"]); ++$i) {
                                                        if (
                                                            $climatisationDecla["answers"][$i] == "Oui" &&
                                                            $climatisationMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreClim;
                                                        }
                                                    }
                                                }
                                                $scoreDemi = 0;
                                                if (isset($demiDecla, $demiMa)) {
                                                    for ($i = 0; $i < count($demiDecla["answers"]); ++$i) {
                                                        if (
                                                            $demiDecla["answers"][$i] == "Oui" &&
                                                            $demiMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreDemi;
                                                        }
                                                    }
                                                }
                                                $scoreDirection = 0;
                                                if (isset($directionDecla, $directionMa)) {
                                                    for ($i = 0; $i < count($directionDecla["answers"]); ++$i) {
                                                        if (
                                                            $directionDecla["answers"][$i] == "Oui" &&
                                                            $directionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreDirection;
                                                        }
                                                    }
                                                }
                                                $scoreElectricite = 0;
                                                if (isset($electriciteDecla, $electriciteMa)) {
                                                    for ($i = 0; $i < count($electriciteDecla["answers"]); ++$i) {
                                                        if (
                                                            $electriciteDecla["answers"][$i] == "Oui" &&
                                                            $electriciteMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreElectricite;
                                                        }
                                                    }
                                                }
                                                $scoreFrein = 0;
                                                if (isset($freiDecla, $freiMa)) {
                                                    for ($i = 0; $i < count($freiDecla["answers"]); ++$i) {
                                                        if (
                                                            $freiDecla["answers"][$i] == "Oui" &&
                                                            $freiMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFrein;
                                                        }
                                                    }
                                                }
                                                $scoreFreinElec = 0;
                                                if (isset($freinageElecDecla, $freinageElecMa)) {
                                                    for ($i = 0; $i < count($freinageElecDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinageElecDecla["answers"][$i] == "Oui" &&
                                                            $freinageElecMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinElec;
                                                        }
                                                    }
                                                }
                                                $scoreFreinHydro = 0;
                                                if (isset($freinageDecla, $freinageMa)) {
                                                    for ($i = 0; $i < count($freinageDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinageDecla["answers"][$i] == "Oui" &&
                                                            $freinageMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinHydro;
                                                        }
                                                    }
                                                }
                                                $scoreFreinPneu = 0;
                                                if (isset($freinDecla, $freinMa)) {
                                                    for ($i = 0; $i < count($freinDecla["answers"]); ++$i) {
                                                        if (
                                                            $freinDecla["answers"][$i] == "Oui" &&
                                                            $freinMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreFreinPneu;
                                                        }
                                                    }
                                                }
                                                $scoreHydro = 0;
                                                if (isset($hydrauliqueDecla, $hydrauliqueMa)) {
                                                    for ($i = 0; $i < count($hydrauliqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $hydrauliqueDecla["answers"][$i] == "Oui" &&
                                                            $hydrauliqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreHydro;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurDiesel = 0;
                                                if (isset($moteurDieselDecla, $moteurDieselMa)) {
                                                    for ($i = 0; $i < count($moteurDieselDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurDieselDecla["answers"][$i] == "Oui" &&
                                                            $moteurDieselMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurDiesel;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurElec = 0;
                                                if (isset($moteurElecDecla, $moteurElecMa)) {
                                                    for ($i = 0; $i < count($moteurElecDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurElecDecla["answers"][$i] == "Oui" &&
                                                            $moteurElecMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurElec;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurEssence = 0;
                                                if (isset($moteurEssenceDecla, $moteurEssenceMa)) {
                                                    for ($i = 0; $i < count($moteurEssenceDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurEssenceDecla["answers"][$i] == "Oui" &&
                                                            $moteurEssenceMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurEssence;
                                                        }
                                                    }
                                                }
                                                $scoreMoteurThermique = 0;
                                                if (isset($moteurThermiqueDecla, $moteurThermiqueMa)) {
                                                    for ($i = 0; $i < count($moteurThermiqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $moteurThermiqueDecla["answers"][$i] == "Oui" &&
                                                            $moteurThermiqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMoteurThermique;
                                                        }
                                                    }
                                                }
                                                $scoreMultiplexage = 0;
                                                if (isset($multiplexageDecla, $multiplexageMa)) {
                                                    for ($i = 0; $i < count($multiplexageDecla["answers"]); ++$i) {
                                                        if (
                                                            $multiplexageDecla["answers"][$i] == "Oui" &&
                                                            $multiplexageMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreMultiplexage;
                                                        }
                                                    }
                                                }
                                                $scorePneu = 0;
                                                if (isset($pneuDecla, $pneuMa)) {
                                                    for ($i = 0; $i < count($pneuDecla["answers"]); ++$i) {
                                                        if (
                                                            $pneuDecla["answers"][$i] == "Oui" &&
                                                            $pneuMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scorePneu;
                                                        }
                                                    }
                                                }
                                                $scorePont = 0;
                                                if (isset($pontDecla, $pontMa)) {
                                                    for ($i = 0; $i < count($pontDecla["answers"]); ++$i) {
                                                        if (
                                                            $pontDecla["answers"][$i] == "Oui" &&
                                                            $pontMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scorePont;
                                                        }
                                                    }
                                                }
                                                $scoreRed = 0;
                                                if (isset($reducteurDecla, $reducteurMa)) {
                                                    for ($i = 0; $i < count($reducteurDecla["answers"]); ++$i) {
                                                        if (
                                                            $reducteurDecla["answers"][$i] == "Oui" &&
                                                            $reducteurMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreRed;
                                                        }
                                                    }
                                                }
                                                $scoreSuspension = 0;
                                                if (isset($suspensionDecla, $suspensionMa)) {
                                                    for ($i = 0; $i < count($suspensionDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionDecla["answers"][$i] == "Oui" &&
                                                            $suspensionMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspension;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionLame = 0;
                                                if (isset($suspensionLameDecla, $suspensionLameMa)) {
                                                    for ($i = 0; $i < count($suspensionLameDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionLameDecla["answers"][$i] == "Oui" &&
                                                            $suspensionLameMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionLame;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionRessort = 0;
                                                if (isset($suspensionRessortDecla, $suspensionRessortMa)) {
                                                    for ($i = 0; $i < count($suspensionRessortDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionRessortDecla["answers"][$i] == "Oui" &&
                                                            $suspensionRessortMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionRessort;
                                                        }
                                                    }
                                                }
                                                $scoreSuspensionPneu = 0;
                                                if (isset($suspensionPneumatiqueDecla, $suspensionPneumatiqueMa)) {
                                                    for ($i = 0; $i < count($suspensionPneumatiqueDecla["answers"]); ++$i) {
                                                        if (
                                                            $suspensionPneumatiqueDecla["answers"][$i] == "Oui" &&
                                                            $suspensionPneumatiqueMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreSuspensionPneu;
                                                        }
                                                    }
                                                }
                                                $scoreTransversale = 0;
                                                if (isset($transversaleDecla, $transversaleMa)) {
                                                    for ($i = 0; $i < count($transversaleDecla["answers"]); ++$i) {
                                                        if (
                                                            $transversaleDecla["answers"][$i] == "Oui" &&
                                                            $transversaleMa["answers"][$i] == "Oui"
                                                        ) {
                                                            ++$scoreTransversale;
                                                        }
                                                    }
                                                }
                                                // if (isset($Toyota)) {
                                                    $toyotaFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $toyotaDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                
                                                    $toyotaScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $toyotaScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $toyotaScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $toyotaScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Byd)) {
                                                    $bydFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $bydDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                
                                                    $bydScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $bydScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $bydScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $bydScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Mitsubishi)) {
                                                    $mitsubishiFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $mitsubishiDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                
                                                    $mitsubishiScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $mitsubishiScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $mitsubishiScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $mitsubishiScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Suzuki)) {
                                                    $suzukiFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $transversaleTotalFac;
                                                    $suzukiDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $suzukiScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
                                                    
                                                    $suzukiScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $transversaleScoreFac;
                                                    $suzukiScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $transversaleScoreDecla;
                                                    $suzukiScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurElecScoreMa + $moteurDieselScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Mercedes)) {
                                                    $mercedesFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $mercedesDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla+ $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $mercedesScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $mercedesScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $mercedesScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $mercedesScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Peugeot)) {
                                                    $peugeotFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $peugeotDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                            
                                                    $peugeotScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $peugeotScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $peugeotScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $peugeotScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Citroen)) {
                                                    $citroenFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $citroenDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                            
                                                    $citroenScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $citroenScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $citroenScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $citroenScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($KingLong)) {
                                                    $kingLongFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $kingLongDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $kingLongScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
                                                    
                                                    $kingLongScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $kingLongScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $kingLongScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Fuso)) {
                                                    $fusoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $fusoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $fusoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $fusoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $fusoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $fusoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Hino)) {
                                                    $hinoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $hinoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $hinoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $hinoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $hinoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $hinoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($RenalutTruck)) {
                                                    $renaultTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $renaultTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $renaultTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $renaultTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $renaultTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $renaultTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($MercedesTruck)) {
                                                    $mercedesTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
                                                    $mercedesTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $mercedesTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
                                                    
                                                    $mercedesTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
                                                    $mercedesTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
                                                    $mercedesTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Sinotruk)) {
                                                    $sinotrukFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $sinotrukDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $sinotrukScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $sinotrukScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $sinotrukScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $sinotrukScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Jcb)) {
                                                    $jcbFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $jcbDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $jcbScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $jcbScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $jcbScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $jcbScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($Lovol)) {
                                                    $lovolFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $lovolDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $lovolScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $lovolScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $lovolScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $lovolScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($ToyotaBt)) {
                                                    $toyotaBtFac = $assistanceConduiteTotalFac + $boiteTotalFac + $climatisationTotalFac + $directionTotalFac + $electriciteTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurElecTotalFac + $multiplexageTotalFac + $pneuTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $toyotaBtDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $climatisationTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurElecTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $toyotaBtScore = $scoreAssistance + $scoreBoite + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurElec + $scoreMultiplexage + $scorePneu + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $toyotaBtScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $climatisationScoreFac + $directionScoreFac + $electriciteScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurElecScoreFac + $multiplexageScoreFac + $pneuScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $toyotaBtScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $climatisationScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurElecScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $toyotaBtScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $climatisationScoreMa + $directionScoreMa + $electriciteScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurElecScoreMa + $multiplexageScoreMa + $pneuScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($ToyotaForflift)) {
                                                    $toyotaForfliftFac = $assistanceConduiteTotalFac + $boiteTotalFac + $boiteAutoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $transversaleTotalFac;
                                                    $toyotaForfliftDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteAutoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $toyotaForfliftScore = $scoreAssistance + $scoreBoite + $scoreBoiteAuto + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreTransversale;
                                                    
                                                    $toyotaForfliftScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $boiteAutoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $transversaleScoreFac;
                                                    $toyotaForfliftScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteAutoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $transversaleScoreDecla;
                                                    $toyotaForfliftScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $boiteAutoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityTransmission) == 'Transmission') {
                                                    $specialityTransmissionFac = $transmissionTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $demiTotalFac + $pontTotalFac + $reducteurTotalFac + $transversaleTotalFac;
                                                    $specialityTransmissionDecla = $transmissionTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteAutoTotalDecla + $demiTotalDecla + $pneuTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityTransmissionScore = $scoreTransmission + $scoreTransfert + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreDemi + $scorePont + $scoreRed + $scoreTransversale;
                                                    
                                                    $specialityTransmissionScoreFac = $transmissionScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $demiScoreFac + $pontScoreFac + $reducteurScoreFac + $transversaleScoreFac;
                                                    $specialityTransmissionScoreDecla = $transmissionScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $demiScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
                                                    $specialityTransmissionScoreMa = $transmissionScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $demiScoreMa + $pontScoreMa + $reducteurScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityMoteur) == 'Moteur') {
                                                    $specialityMoteurFac = $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $transversaleTotalFac;
                                                    $specialityMoteurDecla = $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityMoteurScore = $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreTransversale;
                                                    
                                                    $specialityMoteurScoreFac = $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $transversaleScoreFac;
                                                    $specialityMoteurScoreDecla = $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $transversaleScoreDecla;
                                                    $specialityMoteurScoreMa = $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityElectricite) == 'Electricité et Electronique') {
                                                    $specialityElectriciteFac = $assistanceConduiteTotalFac + $climatisationTotalFac + $electriciteTotalFac + $multiplexageTotalFac + $transversaleTotalFac;
                                                    $specialityElectriciteDecla = $assistanceConduiteTotalDecla + $climatisationTotalDecla + $electriciteTotalDecla + $multiplexageTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityElectriciteScore = $scoreAssistance + $scoreClim + $scoreElectricite + $scoreMultiplexage + $scoreTransversale;
                                                    
                                                    $specialityElectriciteScoreFac = $assistanceConduiteScoreFac + $climatisationScoreFac + $electriciteScoreFac + $multiplexageScoreFac + $transversaleScoreFac;
                                                    $specialityElectriciteScoreDecla = $assistanceConduiteScoreDecla + $climatisationScoreDecla + $electriciteScoreDecla + $multiplexageScoreDecla + $transversaleScoreDecla;
                                                    $specialityElectriciteScoreMa = $assistanceConduiteScoreMa + $climatisationScoreMa + $electriciteScoreMa + $multiplexageScoreMa + $transversaleScoreMa;
                                                // }
                                                // if (isset($specialityHydraulique) == 'Hydraulique') {
                                                    $specialityHydrauliqueFac =  $freiTotalFac + $freinTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $pneuTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
                                                    $specialityHydrauliqueDecla = $freiTotalDecla + $freinTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla + $hydrauliqueTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
                                                    
                                                    $specialityHydrauliqueScore = $scoreFrein + $scoreFreinPneu + $scoreFreinElec + $scoreHydro + $scoreFreinHydro + $scorePneu + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
                                                    
                                                    $specialityHydrauliqueScoreFac = $freiScoreFac + $freinScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $pneuScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
                                                    $specialityHydrauliqueScoreDecla = $freiScoreDecla + $freinScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $pneuScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
                                                    $specialityHydrauliqueScoreMa = $freiScoreMa + $freinScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $pneuScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
                                                // }
                                            
                                            ?>
                                        <tr class="odd" style="">
                                            <td class=" sorting text-black text-center table-light text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; position: sticky; left: 0;">
                                            <?php echo $tech->firstName ?> <?php echo $tech->lastName ?>
                                            </td>
                                            <td class=" sorting text-black text-center table-light gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px; position: sticky; left: 85px;">
                                            <?php echo $tech->level ?>
                                            </td>
                                            <td class="text-center" id="kingLong">
                                            <?php $brandselectedLevel = [];
                                            foreach ($tech['brand'.$selectedLevel] as $brand) {
                                                array_push($brandselectedLevel, $brand);
                                            }
                                            if ($selectedLevel != 'Junior') {
                                                $specialityselectedLevel = [];
                                                foreach ($tech['speciality'.$selectedLevel] as $specialite) {
                                                    array_push($specialityselectedLevel, $specialite);
                                                }
                                            }
                                            if (
                                                in_array("KING LONG", $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $kingLongFac != 0) { ?>
                                                        <?php $percentageKingLong = round(($kingLongScoreFac * 100) / max(1, (float) $kingLongFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $kingLongFac != 0 && $kingLongDecla != 0) { ?>
                                                        <?php $percentageKingLong = round((($kingLongScoreFac * 100) / max(1, (float) $kingLongFac) + ($kingLongScore * 100) / max(1, (float) $kingLongDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageKingLong = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageKingLong < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageKingLong ?>
                                                </span>
                                                <?php } else if($percentageKingLong < 80 ) { ?>
                                                    <?php if($percentageKingLong > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageKingLong ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageKingLong >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageKingLong ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            <td class="text-center" id="fuso">
                                            <?php if (
                                                in_array('FUSO', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $fusoFac != 0) { ?>
                                                        <?php $percentageFuso = round(($fusoScoreFac * 100) / max(1, (float) $fusoFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $fusoFac != 0 && $fusoDecla != 0) { ?>
                                                        <?php $percentageFuso = round((($fusoScoreFac * 100) / max(1, (float) $fusoFac) + ($fusoScore * 100) / max(1, (float) $fusoDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageFuso = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageFuso < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageFuso ?>
                                                </span>
                                                <?php } else if($percentageFuso < 80 ) { ?>
                                                    <?php if($percentageFuso > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageFuso ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageFuso >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageFuso ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="hino">
                                            <?php if (
                                                in_array('HINO', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $hinoFac != 0) { ?>
                                                        <?php $percentageHino = round(($hinoScoreFac * 100) / max(1, (float) $hinoFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $hinoFac != 0 && $hinoDecla != 0) { ?>
                                                        <?php $percentageHino = round((($hinoScoreFac * 100) / max(1, (float) $hinoFac) + ($hinoScore * 100) / max(1, (float) $hinoDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageHino = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageHino < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageHino ?>
                                                </span>
                                                <?php } else if($percentageHino < 80 ) { ?>
                                                    <?php if($percentageHino > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageHino ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageHino >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageHino ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="mercedesTruck">
                                            <?php if (
                                                in_array('MERCEDES TRUCK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $mercedesTruckFac != 0) { ?>
                                                        <?php $percentageMercedesTruck = round(($mercedesTruckScoreFac * 100) / max(1, (float) $mercedesTruckFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $mercedesTruckFac != 0 && $mercedesTruckDecla != 0) { ?>
                                                        <?php $percentageMercedesTruck = round((($mercedesTruckScoreFac * 100) / max(1, (float) $mercedesTruckFac) + ($mercedesTruckScore * 100) / max(1, (float) $mercedesTruckDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageMercedesTruck = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageMercedesTruck < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageMercedesTruck ?>
                                                </span>
                                                <?php } else if($percentageMercedesTruck < 80 ) { ?>
                                                    <?php if($percentageMercedesTruck > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageMercedesTruck ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageMercedesTruck >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageMercedesTruck ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="renaultTruck">
                                            <?php if (
                                                in_array('RENAULT TRUCK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $renaultTruckFac != 0) { ?>
                                                        <?php $percentageRenaultTruck = round(($renaultTruckScoreFac * 100) / max(1, (float) $renaultTruckFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $renaultTruckFac != 0 && $renaultTruckDecla != 0) { ?>
                                                        <?php $percentageRenaultTruck = round((($renaultTruckScoreFac * 100) / max(1, (float) $renaultTruckFac) + ($renaultTruckScore * 100) / max(1, (float) $renaultTruckDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageRenaultTruck = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageRenaultTruck < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageRenaultTruck ?>
                                                </span>
                                                <?php } else if($percentageRenaultTruck < 80 ) { ?>
                                                    <?php if($percentageRenaultTruck > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageRenaultTruck ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageRenaultTruck >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageRenaultTruck ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="sinotruk">
                                            <?php if (
                                                in_array('SINOTRUK', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $sinotrukFac != 0) { ?>
                                                        <?php $percentageSinotruk = round(($sinotrukScoreFac * 100) / max(1, (float) $sinotrukFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $sinotrukFac != 0 && $sinotrukDecla != 0) { ?>
                                                        <?php $percentageSinotruk = round((($sinotrukScoreFac * 100) / max(1, (float) $sinotrukFac) + ($sinotrukScore * 100) / max(1, (float) $sinotrukDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageSinotruk = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageSinotruk < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSinotruk ?>
                                                </span>
                                                <?php } else if($percentageSinotruk < 80 ) { ?>
                                                    <?php if($percentageSinotruk > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSinotruk ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSinotruk >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSinotruk ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="toyotaBt">
                                            <?php if (
                                                in_array('TOYOTA BT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $toyotaBtFac != 0) { ?>
                                                        <?php $percentageToyotaBt = round(($toyotaBtScoreFac * 100) / max(1, (float) $toyotaBtFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaBtFac != 0 && $toyotaBtDecla != 0) { ?>
                                                        <?php $percentageToyotaBt = round((($toyotaBtScoreFac * 100) / max(1, (float) $toyotaBtFac) + ($toyotaBtScore * 100) / max(1, (float) $toyotaBtDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageToyotaBt = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageToyotaBt < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyotaBt ?>
                                                </span>
                                                <?php } else if($percentageToyotaBt < 80 ) { ?>
                                                    <?php if($percentageToyotaBt > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyotaBt ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyotaBt >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyotaBt ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="toyotaForflift">
                                            <?php if (
                                                in_array('TOYOTA FORKLIFT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $toyotaForfliftFac != 0) { ?>
                                                        <?php $percentageToyotaForflift = round(($toyotaForfliftScoreFac * 100) / max(1, (float) $toyotaForfliftFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaForfliftFac != 0 && $toyotaForfliftDecla != 0) { ?>
                                                        <?php $percentageToyotaForflift = round((($toyotaForfliftScoreFac * 100) / max(1, (float) $toyotaForfliftFac) + ($toyotaForfliftScore * 100) / max(1, (float) $toyotaForfliftDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageToyotaForflift = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageToyotaForflift < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyotaForflift ?>
                                                </span>
                                                <?php } else if($percentageToyotaForflift < 80 ) { ?>
                                                    <?php if($percentageToyotaForflift > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyotaForflift ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyotaForflift >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyotaForflift ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="jcb">
                                            <?php  if (
                                                in_array('JCB', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $jcbFac != 0) { ?>
                                                        <?php $percentageJcb = round(($jcbScoreFac * 100) / max(1, (float) $jcbFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $jcbFac != 0 && $jcbDecla != 0) { ?>
                                                        <?php $percentageJcb = round((($jcbScoreFac * 100) / max(1, (float) $jcbFac) + ($jcbScore * 100) / max(1, (float) $jcbDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageJcb = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageJcb < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageJcb ?>
                                                </span>
                                                <?php } else if($percentageJcb < 80 ) { ?>
                                                    <?php if($percentageJcb > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageJcb ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageJcb >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageJcb ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="lovol">
                                            <?php if (in_array('LOVOL', $brandselectedLevel)) { ?>
                                                <?php if ($allocateFac['active'] === true) { ?>
                                                    <?php
                                                    /*-------------------------------------------------------
                                                    | Calcul du pourcentage LOVOL – même logique que KING LONG
                                                    |-------------------------------------------------------*/
                                                    if (isset($tech['candidat']) && $tech['candidat'] === true && $lovolFac != 0) {
                                                        // Cas Candidat : on ne considère que les scores de la FAC
                                                        $percentageLovol = round(($lovolScoreFac * 100) / max(1, (float) $lovolFac));
                                                    } elseif (
                                                        $allocateDecla['active'] === true &&
                                                        $allocateDecla['activeManager'] === true &&
                                                        $lovolFac != 0 &&
                                                        $lovolDecla != 0
                                                    ) {
                                                        // Cas Mixte : moyenne FAC + DECLARATION
                                                        $percentageLovol = round(
                                                            (
                                                                ($lovolScoreFac * 100) / max(1, (float) $lovolFac) +
                                                                ($lovolScore * 100)   / max(1, (float) $lovolDecla)
                                                            ) / 2
                                                        );
                                                    } else {
                                                        // Pas de données exploitables
                                                        $percentageLovol = null;
                                                    }
                                                    ?>

                                                    <?php if ($percentageLovol < 60) { ?>
                                                        <span class="badge text-danger fs-7 m-1">
                                                            <?php echo $percentageLovol; ?>
                                                        </span>
                                                    <?php } elseif ($percentageLovol < 80 && $percentageLovol >= 60) { ?>
                                                        <span class="badge text-warning fs-7 m-1">
                                                            <?php echo $percentageLovol; ?>
                                                        </span>
                                                    <?php } elseif ($percentageLovol >= 80) { ?>
                                                        <span class="badge text-success fs-7 m-1">
                                                            <?php echo $percentageLovol; ?>
                                                        </span>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>

                                            </td>
                                            <td class="text-center" id="byd">
                                            <?php if (
                                                in_array('BYD', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $bydFac != 0) { ?>
                                                        <?php $percentageByd = round(($bydScoreFac * 100) / max(1, (float) $bydFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $bydFac != 0 && $bydDecla != 0) { ?>
                                                        <?php $percentageByd = round((($bydScoreFac * 100) / max(1, (float) $bydFac) + ($bydScore * 100) / max(1, (float) $bydDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageByd = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageByd < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageByd ?>
                                                </span>
                                                <?php } else if($percentageByd < 80 ) { ?>
                                                    <?php if($percentageByd > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageByd ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageByd >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageByd ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="citroen">
                                            <?php if (
                                                in_array('CITROEN', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $citroenFac != 0) { ?>
                                                        <?php $percentageCitroen = round(($citroenScoreFac * 100) / max(1, (float) $citroenFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $citroenFac != 0 && $citroenDecla != 0) { ?>
                                                        <?php $percentageCitroen = round((($citroenScoreFac * 100) / max(1, (float) $citroenFac) + ($citroenScore * 100) / max(1, (float) $citroenDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageCitroen = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageCitroen < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageCitroen ?>
                                                </span>
                                                <?php } else if($percentageCitroen < 80 ) { ?>
                                                    <?php if($percentageCitroen > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageCitroen ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageCitroen >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageCitroen ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="mercedes">
                                            <?php if (
                                                in_array('MERCEDES', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $mercedesFac != 0) { ?>
                                                        <?php $percentageMercedes = round(($mercedesScoreFac * 100) / max(1, (float) $mercedesFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $mercedesFac != 0 && $mercedesDecla != 0) { ?>
                                                        <?php $percentageMercedes = round((($mercedesScoreFac * 100) / max(1, (float) $mercedesFac) + ($mercedesScore * 100) / max(1, (float) $mercedesDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageMercedes = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageMercedes < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageMercedes ?>
                                                </span>
                                                <?php } else if($percentageMercedes < 80 ) { ?>
                                                    <?php if($percentageMercedes > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageMercedes ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageMercedes >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageMercedes ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="mitsubishi">
                                            <?php if (
                                                in_array('MITSUBISHI', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $mitsubishiFac != 0) { ?>
                                                        <?php $percentageMitsubishi = round(($mitsubishiScoreFac * 100) / max(1, (float) $mitsubishiFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $mitsubishiFac != 0 && $mitsubishiDecla != 0) { ?>
                                                        <?php $percentageMitsubishi = round((($mitsubishiScoreFac * 100) / max(1, (float) $mitsubishiFac) + ($mitsubishiScore * 100) / max(1, (float) $mitsubishiDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageMitsubishi = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageMitsubishi < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageMitsubishi ?>
                                                </span>
                                                <?php } else if($percentageMitsubishi < 80 ) { ?>
                                                    <?php if($percentageMitsubishi > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageMitsubishi ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageMitsubishi >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageMitsubishi ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="peugeot">
                                            <?php if (
                                                in_array('PEUGEOT', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $peugeotFac != 0) { ?>
                                                        <?php $percentagePeugeot = round(($peugeotScoreFac * 100) / max(1, (float) $peugeotFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $peugeotFac != 0 && $peugeotDecla != 0) { ?>
                                                        <?php $percentagePeugeot = round((($peugeotScoreFac * 100) / max(1, (float) $peugeotFac) + ($peugeotScore * 100) / max(1, (float) $peugeotDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentagePeugeot = null; ?>
                                                    <?php } ?>
                                                <?php if($percentagePeugeot < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentagePeugeot ?>
                                                </span>
                                                <?php } else if($percentagePeugeot < 80 ) { ?>
                                                    <?php if($percentagePeugeot > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentagePeugeot ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentagePeugeot >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentagePeugeot ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="suzuki">
                                            <?php if (
                                                in_array('SUZUKI', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $suzukiFac != 0) { ?>
                                                        <?php $percentageSuzuki = round(($suzukiScoreFac * 100) / max(1, (float) $suzukiFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $suzukiFac != 0 && $suzukiDecla != 0) { ?>
                                                        <?php $percentageSuzuki = round((($suzukiScoreFac * 100) / max(1, (float) $suzukiFac) + ($suzukiScore * 100) / max(1, (float) $suzukiDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageSuzuki = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageSuzuki < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageSuzuki ?>
                                                </span>
                                                <?php } else if($percentageSuzuki < 80 ) { ?>
                                                    <?php if($percentageSuzuki > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageSuzuki ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageSuzuki >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageSuzuki ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <td class="text-center" id="toyota">
                                            <?php if (
                                                in_array('TOYOTA', $brandselectedLevel)
                                            ) { ?>
                                                <?php if ($allocateFac['active'] == true) { ?>
                                                    <?php if (isset($tech["candidat"]) && $tech["candidat"] == true && $toyotaFac != 0) { ?>
                                                        <?php $percentageToyota = round(($toyotaScoreFac * 100) / max(1, (float) $toyotaFac)); ?>
                                                    <?php } else if ($allocateDecla['active'] == true && $allocateDecla['activeManager'] == true && $toyotaFac != 0 && $toyotaDecla != 0) { ?>
                                                        <?php $percentageToyota = round((($toyotaScoreFac * 100) / max(1, (float) $toyotaFac) + ($toyotaScore * 100) / max(1, (float) $toyotaDecla)) / 2); ?>
                                                    <?php } else { ?>
                                                        <?php $percentageToyota = null; ?>
                                                    <?php } ?>
                                                <?php if($percentageToyota < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentageToyota ?>
                                                </span>
                                                <?php } else if($percentageToyota < 80 ) { ?>
                                                    <?php if($percentageToyota > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentageToyota ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentageToyota >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentageToyota ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            <?php } ?>
                                            </td>
                                            <?php if ($selectedLevel != 'Junior') { ?>
                                            <td class="text-center" id="specialityElectricite">
                                                <?php if (in_array('Electricité et Electronique', $specialityselectedLevel)) { ?>
                                                    <?php if ($allocateFac['active'] === true) { ?>
                                                        <?php
                                                        /*-------------------------------------------------------------
                                                        | Calcul du pourcentage pour la spécialité Électricité/Électronique
                                                        | même logique que KING LONG et LOVOL
                                                        |-------------------------------------------------------------*/
                                                        if (
                                                            isset($tech['candidat']) &&
                                                            $tech['candidat'] === true &&
                                                            $specialityElectriciteFac != 0
                                                        ) {
                                                            // Cas Candidat : uniquement les scores FAC
                                                            $percentageSpecialityElectricite = round(
                                                                ($specialityElectriciteScoreFac * 100) / max(1, (float) $specialityElectriciteFac)
                                                            );
                                                        } elseif (
                                                            $allocateDecla['active'] === true &&
                                                            $allocateDecla['activeManager'] === true &&
                                                            $specialityElectriciteFac  != 0 &&
                                                            $specialityElectriciteDecla != 0
                                                        ) {
                                                            // Cas Mixte : moyenne FAC + DECLARATION
                                                            $percentageSpecialityElectricite = round(
                                                                (
                                                                    ($specialityElectriciteScoreFac * 100) / max(1, (float) $specialityElectriciteFac) +
                                                                    ($specialityElectriciteScore     * 100) / max(1, (float) $specialityElectriciteDecla)
                                                                ) / 2
                                                            );
                                                        } else {
                                                            // Pas de données valides
                                                            $percentageSpecialityElectricite = null;
                                                        }
                                                        ?>

                                                        <?php if ($percentageSpecialityElectricite < 60) { ?>
                                                            <span class="badge text-danger fs-7 m-1">
                                                                <?php echo $percentageSpecialityElectricite; ?>
                                                            </span>
                                                        <?php } elseif ($percentageSpecialityElectricite >= 60 && $percentageSpecialityElectricite < 80) { ?>
                                                            <span class="badge text-warning fs-7 m-1">
                                                                <?php echo $percentageSpecialityElectricite; ?>
                                                            </span>
                                                        <?php } elseif ($percentageSpecialityElectricite >= 80) { ?>
                                                            <span class="badge text-success fs-7 m-1">
                                                                <?php echo $percentageSpecialityElectricite; ?>
                                                            </span>
                                                        <?php } ?>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                <?php } ?>

                                            </td>
                                            <!-- ================== SPÉCIALITÉ : HYDRAULIQUE ================== -->
                                            <td class="text-center" id="specialityHydraulique">
                                            <?php if (in_array('Hydraulique', $specialityselectedLevel)) { ?>
                                                <?php if ($allocateFac['active'] === true) { ?>
                                                    <?php
                                                    /*----------------------------------------------------
                                                    | Calcul du pourcentage ― Hydraulique
                                                    |----------------------------------------------------*/
                                                    if (
                                                        isset($tech['candidat']) &&
                                                        $tech['candidat'] === true &&
                                                        $specialityHydrauliqueFac != 0
                                                    ) {
                                                        // Cas Candidat : uniquement les scores FAC
                                                        $percentageSpecialityHydraulique = round(
                                                            ($specialityHydrauliqueScoreFac * 100) / max(1, (float) $specialityHydrauliqueFac)
                                                        );
                                                    } elseif (
                                                        $allocateDecla['active'] === true &&
                                                        $allocateDecla['activeManager'] === true &&
                                                        $specialityHydrauliqueFac  != 0 &&
                                                        $specialityHydrauliqueDecla != 0
                                                    ) {
                                                        // Cas Mixte : moyenne FAC + DECLARATION
                                                        $percentageSpecialityHydraulique = round(
                                                            (
                                                                ($specialityHydrauliqueScoreFac * 100) / max(1, (float) $specialityHydrauliqueFac) +
                                                                ($specialityHydrauliqueScore     * 100) / max(1, (float) $specialityHydrauliqueDecla)
                                                            ) / 2
                                                        );
                                                    } else {
                                                        $percentageSpecialityHydraulique = null;
                                                    }
                                                    ?>

                                                    <?php if ($percentageSpecialityHydraulique < 60) { ?>
                                                        <span class="badge text-danger fs-7 m-1"><?php echo $percentageSpecialityHydraulique; ?></span>
                                                    <?php } elseif ($percentageSpecialityHydraulique >= 60 && $percentageSpecialityHydraulique < 80) { ?>
                                                        <span class="badge text-warning fs-7 m-1"><?php echo $percentageSpecialityHydraulique; ?></span>
                                                    <?php } elseif ($percentageSpecialityHydraulique >= 80) { ?>
                                                        <span class="badge text-success fs-7 m-1"><?php echo $percentageSpecialityHydraulique; ?></span>
                                                    <?php } ?>
                                                <?php } else { echo '-'; } ?>
                                            <?php } ?>
                                            </td>

                                            <!-- ================== SPÉCIALITÉ : MOTEUR ================== -->
                                            <td class="text-center" id="specialityMoteur">
                                            <?php if (in_array('Moteur', $specialityselectedLevel)) { ?>
                                                <?php if ($allocateFac['active'] === true) { ?>
                                                    <?php
                                                    /*----------------------------------------------------
                                                    | Calcul du pourcentage ― Moteur
                                                    |----------------------------------------------------*/
                                                    if (
                                                        isset($tech['candidat']) &&
                                                        $tech['candidat'] === true &&
                                                        $specialityMoteurFac != 0
                                                    ) {
                                                        $percentageSpecialityMoteur = round(
                                                            ($specialityMoteurScoreFac * 100) / max(1, (float) $specialityMoteurFac)
                                                        );
                                                    } elseif (
                                                        $allocateDecla['active'] === true &&
                                                        $allocateDecla['activeManager'] === true &&
                                                        $specialityMoteurFac  != 0 &&
                                                        $specialityMoteurDecla != 0
                                                    ) {
                                                        $percentageSpecialityMoteur = round(
                                                            (
                                                                ($specialityMoteurScoreFac * 100) / max(1, (float) $specialityMoteurFac) +
                                                                ($specialityMoteurScore     * 100) / max(1, (float) $specialityMoteurDecla)
                                                            ) / 2
                                                        );
                                                    } else {
                                                        $percentageSpecialityMoteur = null;
                                                    }
                                                    ?>

                                                    <?php if ($percentageSpecialityMoteur < 60) { ?>
                                                        <span class="badge text-danger fs-7 m-1"><?php echo $percentageSpecialityMoteur; ?></span>
                                                    <?php } elseif ($percentageSpecialityMoteur >= 60 && $percentageSpecialityMoteur < 80) { ?>
                                                        <span class="badge text-warning fs-7 m-1"><?php echo $percentageSpecialityMoteur; ?></span>
                                                    <?php } elseif ($percentageSpecialityMoteur >= 80) { ?>
                                                        <span class="badge text-success fs-7 m-1"><?php echo $percentageSpecialityMoteur; ?></span>
                                                    <?php } ?>
                                                <?php } else { echo '-'; } ?>
                                            <?php } ?>
                                            </td>

                                            <!-- ================== SPÉCIALITÉ : TRANSMISSION ================== -->
                                            <td class="text-center" id="specialityTransmission">
                                            <?php if (in_array('Transmission', $specialityselectedLevel)) { ?>
                                                <?php if ($allocateFac['active'] === true) { ?>
                                                    <?php
                                                    /*----------------------------------------------------
                                                    | Calcul du pourcentage ― Transmission
                                                    |----------------------------------------------------*/
                                                    if (
                                                        isset($tech['candidat']) &&
                                                        $tech['candidat'] === true &&
                                                        $specialityTransmissionFac != 0
                                                    ) {
                                                        $percentageSpecialityTransmission = round(
                                                            ($specialityTransmissionScoreFac * 100) / max(1, (float) $specialityTransmissionFac)
                                                        );
                                                    } elseif (
                                                        $allocateDecla['active'] === true &&
                                                        $allocateDecla['activeManager'] === true &&
                                                        $specialityTransmissionFac  != 0 &&
                                                        $specialityTransmissionDecla != 0
                                                    ) {
                                                        $percentageSpecialityTransmission = round(
                                                            (
                                                                ($specialityTransmissionScoreFac * 100) / max(1, (float) $specialityTransmissionFac) +
                                                                ($specialityTransmissionScore     * 100) / max(1, (float) $specialityTransmissionDecla)
                                                            ) / 2
                                                        );
                                                    } else {
                                                        $percentageSpecialityTransmission = null;
                                                    }
                                                    ?>

                                                    <?php if ($percentageSpecialityTransmission < 60) { ?>
                                                        <span class="badge text-danger fs-7 m-1"><?php echo $percentageSpecialityTransmission; ?></span>
                                                    <?php } elseif ($percentageSpecialityTransmission >= 60 && $percentageSpecialityTransmission < 80) { ?>
                                                        <span class="badge text-warning fs-7 m-1"><?php echo $percentageSpecialityTransmission; ?></span>
                                                    <?php } elseif ($percentageSpecialityTransmission >= 80) { ?>
                                                        <span class="badge text-success fs-7 m-1"><?php echo $percentageSpecialityTransmission; ?></span>
                                                    <?php } ?>
                                                <?php } else { echo '-'; } ?>
                                            <?php } ?>
                                            </td>

                                        <?php } ?>
                                            <td class="text-center" id="result">
                                                <?php
                                                if (isset($resultFac)) {
                                                    if (isset($tech["candidat"]) && $tech["candidat"] == true && $resultFac->total != 0) {
                                                        // Cas candidat : FAC seul
                                                        $percentage = round(($resultFac->score * 100) / max(1, (float) $resultFac->total));
                                                    } elseif (isset($resultTechMa) && $resultFac->total != 0 && $resultTechMa->total != 0) {
                                                        // Cas normal : moyenne FAC + TechMa
                                                        $percentage = round((($resultFac->score * 100) / max(1, (float) $resultFac->total) + ($resultTechMa->score * 100) / max(1, (float) $resultTechMa->total)) / 2);
                                                    } else {
                                                        $percentage = null;
                                                    }
                                                }
                                                if (isset($percentage) && $percentage !== null) {
                                                ?>
                                                <?php if($percentage < 60 ) { ?>
                                                <span class="badge text-danger fs-7 m-1">
                                                    <?php echo $percentage ?>
                                                </span>
                                                <?php } else if($percentage < 80 ) { ?>
                                                    <?php if($percentage > 60 ) { ?>
                                                    <span class="badge text-warning fs-7 m-1">
                                                        <?php echo $percentage ?>
                                                    </span>
                                                    <?php } ?>
                                                <?php } else if($percentage >= 80) { ?>
                                                <span class="badge text-success fs-7 m-1">
                                                    <?php echo $percentage ?>
                                                </span>
                                                <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php } } ?>
                                        <!--end::Menu-->
                                        <tr style=" position: sticky; bottom: 0; z-index: 2;">
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px; position: sticky; left: 0;">
                                                <?php echo $result ?>
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultKingLong"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultFuso"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultHino"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultMercedesTruck"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultRenaultTruck"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSinotruk"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultToyotaBt"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultToyotaForklift"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultJcb"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultLovol"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultByd"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultCitroen"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultMercedes"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultMitsubishi"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultPeugeot"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSuzuki"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultToyota"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                        <?php if ($selectedLevel != 'Junior') { ?>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSpecialityElectricite"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSpecialityHydraulique"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSpecialityMoteur"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultSpecialityTransmission"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
                                            </th>
                                        <?php } ?>
                                            <th class=" sorting text-center table-light fw-bolder text-uppercase gs-0"
                                                tabindex="0" colspan="1" aria-controls="kt_customers_table" id="resultTotal"
                                                aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                                
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
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span
                            class="path2"></span></i> <?php echo $excel ?>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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

function manager() {
    var manager = document.querySelector("#manager-filter")
    var level = document.querySelector("#level-filter")
    if (manager.value == "all") {
        window.location.search = `?level=<?php echo $selectedLevel ?>&subsidiary=<?php echo $subsidiar ?>`;
    }  else {
        window.location.search = `?level=<?php echo $selectedLevel ?>&subsidiary=<?php echo $subsidiar ?>&user=${manager.value}`;
    }
}

function level() {
    var level = document.querySelector("#level-filter")
    var manager = document.querySelector("#manager-filter")
    if (level.value == '<?php echo $selectedLevel ?>' && manager.value != "all") {
        window.location.search = `?level=<?php echo $selectedLevel ?>&subsidiary=<?php echo $subsidiar ?>&user=${manager.value}`;
    } else if (level.value != '<?php echo $selectedLevel ?>' && manager.value != "all") {
        window.location.search = `?level=${level.value}&subsidiary=<?php echo $subsidiar ?>&user=${manager.value}`;
    } else {
        window.location.search = `?level=${level.value}&subsidiary=<?php echo $subsidiar ?>`;
    }
}

var kingLong = document.querySelectorAll("#kingLong")
var fuso = document.querySelectorAll("#fuso")
var hino = document.querySelectorAll("#hino")
var jcb = document.querySelectorAll("#jcb")
var lovol = document.querySelectorAll("#lovol")
var mercedesTruck = document.querySelectorAll("#mercedesTruck")
var renaultTruck = document.querySelectorAll("#renaultTruck")
var sinotruk = document.querySelectorAll("#sinotruk")
var toyotaBt = document.querySelectorAll("#toyotaBt")
var toyotaForflift = document.querySelectorAll("#toyotaForflift")
var byd = document.querySelectorAll("#byd")
var citroen = document.querySelectorAll("#citroen")
var mercedes = document.querySelectorAll("#mercedes")
var mitsubishi = document.querySelectorAll("#mitsubishi")
var peugeot = document.querySelectorAll("#peugeot")
var suzuki = document.querySelectorAll("#suzuki")
var toyota = document.querySelectorAll("#toyota")
var specialityElectricite = document.querySelectorAll("#specialityElectricite")
var specialityHydraulique = document.querySelectorAll("#specialityHydraulique")
var specialityMoteur = document.querySelectorAll("#specialityMoteur")
var specialityTransmission = document.querySelectorAll("#specialityTransmission")
var result = document.querySelectorAll("#result")

var resultKingLong = document.querySelector("#resultKingLong")
var resultFuso = document.querySelector("#resultFuso")
var resultHino = document.querySelector("#resultHino")
var resultJcb = document.querySelector("#resultJcb")
var resultLovol = document.querySelector("#resultLovol")
var resultMercedesTruck = document.querySelector("#resultMercedesTruck")
var resultRenaultTruck = document.querySelector("#resultRenaultTruck")
var resultSinotruk = document.querySelector("#resultSinotruk")
var resultToyotaBt = document.querySelector("#resultToyotaBt")
var resultToyotaForflift = document.querySelector("#resultToyotaForklift")
var resultByd = document.querySelector("#resultByd")
var resultCitroen = document.querySelector("#resultCitroen")
var resultMercedes = document.querySelector("#resultMercedes")
var resultMitsubishi = document.querySelector("#resultMitsubishi")
var resultPeugeot = document.querySelector("#resultPeugeot")
var resultSuzuki = document.querySelector("#resultSuzuki")
var resultToyota = document.querySelector("#resultToyota")
var resultSpecialityElectricite = document.querySelector("#resultSpecialityElectricite")
var resultSpecialityHydraulique = document.querySelector("#resultSpecialityHydraulique")
var resultSpecialityMoteur = document.querySelector("#resultSpecialityMoteur")
var resultSpecialityTransmission = document.querySelector("#resultSpecialityTransmission")
var resultTotal = document.querySelector("#resultTotal")

var totalKingLong = 0;
var totalFuso = 0;
var totalHino = 0;
var totalMercedesTruck = 0;
var totalRenaultTruck = 0;
var totalSinotruk = 0;
var totalToyotaBt = 0;
var totalToyotaForflift = 0;
var totalJcb = 0;
var totalLovol = 0;
var totalByd = 0;
var totalCitroen = 0;
var totalMercedes = 0;
var totalMitsubishi = 0;
var totalPeugeot= 0;
var totalSuzuki = 0;
var totalToyota = 0;
var totalSpecialityElectricite = 0;
var totalSpecialityHydraulique = 0;
var totalSpecialityMoteur = 0;
var totalSpecialityTransmission = 0;
var total = 0;

let arrayKingLong = [];
let arrayFuso = [];
let arrayHino = [];
let arrayMercedesTruck = [];
let arrayRenaultTruck = [];
let arraySinotruk = [];
let arrayToyotaBt = [];
let arrayToyotaForflift = [];
let arrayJcb = [];
let arrayLovol = [];
let arrayByd = [];
let arrayCitroen = [];
let arrayMercedes = [];
let arrayMitsubishi = [];
let arrayPeugeot = [];
let arraySuzuki = [];
let arrayToyota = [];
let arraySpecialityElectricite = [];
let arraySpecialityHydraulique = [];
let arraySpecialityMoteur = [];
let arraySpecialityTransmission = [];
let array = [];

for(var i = 0; i < kingLong.length; i++) {
    if(kingLong[i].innerText != "" && kingLong[i].innerText != "-") {
        arrayKingLong.push(kingLong[i].innerText)
    } else if(kingLong[i].innerText == "") {
        kingLong[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayKingLong.length; i++) {
    totalKingLong += parseInt(arrayKingLong[i]);
    var avgKing = Math.round(totalKingLong / arrayKingLong.length);
}
if (avgKing == undefined) {
    resultKingLong.innerHTML = "-"
} else {
    if (avgKing < 60) {
        resultKingLong.innerHTML = avgKing + "%"
        resultKingLong.style.color = "#d9534f"
    }
    if (avgKing > 60) {
        if (avgKing < 80) {
            resultKingLong.innerHTML = avgKing + "%"
            resultKingLong.style.color = "#f0ad4e"
        }
    } 
    if (avgKing >= 80) {
        resultKingLong.innerHTML = avgKing + "%"
        resultKingLong.style.color = "#5cb85c "
    }
}

for(var i = 0; i < fuso.length; i++) {
    if(fuso[i].innerText != "" && fuso[i].innerText != "-") {
        arrayFuso.push(fuso[i].innerText)
    } else if(fuso[i].innerText == "") {
        fuso[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayFuso.length; i++) {
    totalFuso += parseInt(arrayFuso[i]);
    var avgFu = Math.round(totalFuso / arrayFuso.length);
}
if (avgFu == undefined) {
    resultFuso.innerHTML = "-"
} else {
    if (avgFu < 60) {
        resultFuso.innerHTML = avgFu + "%"
        resultFuso.style.color = "#d9534f"
    }
    if (avgFu > 60) {
        if (avgFu < 80) {
            resultFuso.innerHTML = avgFu + "%"
            resultFuso.style.color = "#f0ad4e"
        }
    } 
    if (avgFu >= 80) {
        resultFuso.innerHTML = avgFu + "%"
        resultFuso.style.color = "#5cb85c "
    }
}

for(var i = 0; i < hino.length; i++) {
    if(hino[i].innerText != "" && hino[i].innerText != "-") {
        arrayHino.push(hino[i].innerText)
    } else if(hino[i].innerText == "") {
        hino[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayHino.length; i++) {
    totalHino += parseInt(arrayHino[i]);
    var avgHi = Math.round(totalHino / arrayHino.length);
}
if (avgHi == undefined) {
    resultHino.innerHTML = "-"
} else {
    if (avgHi < 60) {
        resultHino.innerHTML = avgHi + "%"
        resultHino.style.color = "#d9534f"
    }
    if (avgHi > 60) {
        if (avgHi < 80) {
            resultHino.innerHTML = avgHi + "%"
            resultHino.style.color = "#f0ad4e"
        }
    } 
    if (avgHi >= 80) {
        resultHino.innerHTML = avgHi + "%"
        resultHino.style.color = "#5cb85c "
    }
}

for(var i = 0; i < mercedesTruck.length; i++) {
    if(mercedesTruck[i].innerText != "" && mercedesTruck[i].innerText != "-") {
        arrayMercedesTruck.push(mercedesTruck[i].innerText)
    } else if(mercedesTruck[i].innerText == "") {
        mercedesTruck[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayMercedesTruck.length; i++) {
    totalMercedesTruck += parseInt(arrayMercedesTruck[i]);
    var avgMeTr = Math.round(totalMercedesTruck / arrayMercedesTruck.length);
}
if (avgMeTr == undefined) {
    resultMercedesTruck.innerHTML = "-"
} else {
    if (avgMeTr < 60) {
        resultMercedesTruck.innerHTML = avgMeTr + "%"
        resultMercedesTruck.style.color = "#d9534f"
    }
    if (avgMeTr > 60) {
        if (avgMeTr < 80) {
            resultMercedesTruck.innerHTML = avgMeTr + "%"
            resultMercedesTruck.style.color = "#f0ad4e"
        }
    } 
    if (avgMeTr >= 80) {
        resultMercedesTruck.innerHTML = avgMeTr + "%"
        resultMercedesTruck.style.color = "#5cb85c "
    }
}

for(var i = 0; i < renaultTruck.length; i++) {
    if(renaultTruck[i].innerText != "" && renaultTruck[i].innerText != "-") {
        arrayRenaultTruck.push(renaultTruck[i].innerText)
    } else if(renaultTruck[i].innerText == "") {
        renaultTruck[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayRenaultTruck.length; i++) {
    totalRenaultTruck += parseInt(arrayRenaultTruck[i]);
    var avgReTr = Math.round(totalRenaultTruck / arrayRenaultTruck.length);
}
if (avgReTr == undefined) {
    resultRenaultTruck.innerHTML = "-"
} else {
    if (avgReTr < 60) {
        resultRenaultTruck.innerHTML = avgReTr + "%"
        resultRenaultTruck.style.color = "#d9534f"
    }
    if (avgReTr > 60) {
        if (avgReTr < 80) {
            resultRenaultTruck.innerHTML = avgReTr + "%"
            resultRenaultTruck.style.color = "#f0ad4e"
        }
    } 
    if (avgReTr >= 80) {
        resultRenaultTruck.innerHTML = avgReTr + "%"
        resultRenaultTruck.style.color = "#5cb85c "
    }
}

for(var i = 0; i < sinotruk.length; i++) {
    if(sinotruk[i].innerText != "" && sinotruk[i].innerText != "-") {
        arraySinotruk.push(sinotruk[i].innerText)
    } else if(sinotruk[i].innerText == "") {
        sinotruk[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arraySinotruk.length; i++) {
    totalSinotruk += parseInt(arraySinotruk[i]);
    var avgSiTr = Math.round(totalSinotruk / arraySinotruk.length);
}
if (avgSiTr == undefined) {
    resultSinotruk.innerHTML = "-"
} else {
    if (avgSiTr < 60) {
        resultSinotruk.innerHTML = avgSiTr + "%"
        resultSinotruk.style.color = "#d9534f"
    }
    if (avgSiTr > 60) {
        if (avgSiTr < 80) {
            resultSinotruk.innerHTML = avgSiTr + "%"
            resultSinotruk.style.color = "#f0ad4e"
        }
    } 
    if (avgSiTr >= 80) {
        resultSinotruk.innerHTML = avgSiTr + "%"
        resultSinotruk.style.color = "#5cb85c "
    }
}

for(var i = 0; i < toyotaBt.length; i++) {
    if(toyotaBt[i].innerText != "" && toyotaBt[i].innerText != "-") {
        arrayToyotaBt.push(toyotaBt[i].innerText)
    } else if(toyotaBt[i].innerText == "") {
        toyotaBt[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayToyotaBt.length; i++) {
    totalToyotaBt += parseInt(arrayToyotaBt[i]);
    var avgToBt = Math.round(totalToyotaBt / arrayToyotaBt.length);
}
if (avgToBt == undefined) {
    resultToyotaBt.innerHTML = "-"
} else {
    if (avgToBt < 60) {
        resultToyotaBt.innerHTML = avgToBt + "%"
        resultToyotaBt.style.color = "#d9534f"
    }
    if (avgToBt > 60) {
        if (avgToBt < 80) {
            resultToyotaBt.innerHTML = avgToBt + "%"
            resultToyotaBt.style.color = "#f0ad4e"
        }
    } 
    if (avgToBt >= 80) {
        resultToyotaBt.innerHTML = avgToBt + "%"
        resultToyotaBt.style.color = "#5cb85c "
    }
}

for(var i = 0; i < toyotaForflift.length; i++) {
    if(toyotaForflift[i].innerText != "" && toyotaForflift[i].innerText != "-") {
        arrayToyotaForflift.push(toyotaForflift[i].innerText)
    } else if(toyotaForflift[i].innerText == "") {
        toyotaForflift[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayToyotaForflift.length; i++) {
    totalToyotaForflift += parseInt(arrayToyotaForflift[i]);
    var avgToFo = Math.round(totalToyotaForflift / arrayToyotaForflift.length);
}
if (avgToFo == undefined) {
    resultToyotaForflift.innerHTML = "-"
} else {
    if (avgToFo < 60) {
        resultToyotaForflift.innerHTML = avgToFo + "%"
        resultToyotaForflift.style.color = "#d9534f"
    }
    if (avgToFo > 60) {
        if (avgToFo < 80) {
            resultToyotaForflift.innerHTML = avgToFo + "%"
            resultToyotaForflift.style.color = "#f0ad4e"
        }
    } 
    if (avgToFo >= 80) {
        resultToyotaForflift.innerHTML = avgToFo + "%"
        resultToyotaForflift.style.color = "#5cb85c "
    }
}

for(var i = 0; i < jcb.length; i++) {
    if(jcb[i].innerText != "" && jcb[i].innerText != "-") {
        arrayJcb.push(jcb[i].innerText)
    } else if(jcb[i].innerText == "") {
        jcb[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayJcb.length; i++) {
    totalJcb += parseInt(arrayJcb[i]);
    var avgJc = Math.round(totalJcb / arrayJcb.length);
}
if (avgJc == undefined) {
    resultJcb.innerHTML = "-"
} else {
    if (avgJc < 60) {
        resultJcb.innerHTML = avgJc + "%"
        resultJcb.style.color = "#d9534f"
    }
    if (avgJc > 60) {
        if (avgJc < 80) {
            resultJcb.innerHTML = avgJc + "%"
            resultJcb.style.color = "#f0ad4e"
        }
    } 
    if (avgJc >= 80) {
        resultJcb.innerHTML = avgJc + "%"
        resultJcb.style.color = "#5cb85c "
    }
}

for(var i = 0; i < lovol.length; i++) {
    if(lovol[i].innerText != "" && lovol[i].innerText != "-") {
        arrayLovol.push(lovol[i].innerText)
    } else if(lovol[i].innerText == "") {
        lovol[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayLovol.length; i++) {
    totalLovol += parseInt(arrayLovol[i]);
    var avgLo = Math.round(totalLovol / arrayLovol.length);
}
if (avgLo == undefined) {
    resultLovol.innerHTML = "-"
} else {
    if (avgLo < 60) {
        resultLovol.innerHTML = avgLo + "%"
        resultLovol.style.color = "#d9534f"
    }
    if (avgLo > 60) {
        if (avgLo < 80) {
            resultLovol.innerHTML = avgLo + "%"
            resultLovol.style.color = "#f0ad4e"
        }
    } 
    if (avgLo >= 80) {
        resultLovol.innerHTML = avgLo + "%"
        resultLovol.style.color = "#5cb85c "
    }
}

for(var i = 0; i < byd.length; i++) {
    if(byd[i].innerText != "" && byd[i].innerText != "-") {
        arrayByd.push(byd[i].innerText)
    } else if(byd[i].innerText == "") {
        byd[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayByd.length; i++) {
    totalByd += parseInt(arrayByd[i]);
    var avgByd = Math.round(totalByd / arrayByd.length);
}
if (avgByd == undefined) {
    resultByd.innerHTML = "-"
} else {
    if (avgByd < 60) {
        resultByd.innerHTML = avgByd + "%"
        resultByd.style.color = "#d9534f"
    }
    if (avgByd > 60) {
        if (avgByd < 80) {
            resultByd.innerHTML = avgByd + "%"
            resultByd.style.color = "#f0ad4e"
        }
    } 
    if (avgByd >= 80) {
        resultByd.innerHTML = avgByd + "%"
        resultByd.style.color = "#5cb85c "
    }
}

for(var i = 0; i < citroen.length; i++) {
    if(citroen[i].innerText != "" && citroen[i].innerText != "-") {
        arrayCitroen.push(citroen[i].innerText)
    } else if(citroen[i].innerText == "") {
        citroen[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayCitroen.length; i++) {
    totalCitroen += parseInt(arrayCitroen[i]);
    var avgCi = Math.round(totalCitroen / arrayCitroen.length);
}
if (avgCi == undefined) {
    resultCitroen.innerHTML = "-"
} else {
    if (avgCi < 60) {
        resultCitroen.innerHTML = avgCi + "%"
        resultCitroen.style.color = "#d9534f"
    }
    if (avgCi > 60) {
        if (avgCi < 80) {
            resultCitroen.innerHTML = avgCi + "%"
            resultCitroen.style.color = "#f0ad4e"
        }
    } 
    if (avgCi >= 80) {
        resultCitroen.innerHTML = avgCi + "%"
        resultCitroen.style.color = "#5cb85c "
    }
}

for(var i = 0; i < mercedes.length; i++) {
    if(mercedes[i].innerText != "" && mercedes[i].innerText != "-") {
        arrayMercedes.push(mercedes[i].innerText)
    } else if(mercedes[i].innerText == "") {
        mercedes[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayMercedes.length; i++) {
    totalMercedes += parseInt(arrayMercedes[i]);
    var avgMe = Math.round(totalMercedes / arrayMercedes.length);
}
if (avgMe == undefined) {
    resultMercedes.innerHTML = "-"
} else {
    if (avgMe < 60) {
        resultMercedes.innerHTML = avgMe + "%"
        resultMercedes.style.color = "#d9534f"
    }
    if (avgMe > 60) {
        if (avgMe < 80) {
            resultMercedes.innerHTML = avgMe + "%"
            resultMercedes.style.color = "#f0ad4e"
        }
    } 
    if (avgMe >= 80) {
        resultMercedes.innerHTML = avgMe + "%"
        resultMercedes.style.color = "#5cb85c "
    }
}

for(var i = 0; i < mitsubishi.length; i++) {
    if(mitsubishi[i].innerText != "" && mitsubishi[i].innerText != "-") {
        arrayMitsubishi.push(mitsubishi[i].innerText)
    } else if(mitsubishi[i].innerText == "") {
        mitsubishi[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayMitsubishi.length; i++) {
    totalMitsubishi += parseInt(arrayMitsubishi[i]);
    var avgMi = Math.round(totalMitsubishi / arrayMitsubishi.length);
}
if (avgMi == undefined) {
    resultMitsubishi.innerHTML = "-"
} else {
    if (avgMi < 60) {
        resultMitsubishi.innerHTML = avgMi + "%"
        resultMitsubishi.style.color = "#d9534f"
    }
    if (avgMi > 60) {
        if (avgMi < 80) {
            resultMitsubishi.innerHTML = avgMi + "%"
            resultMitsubishi.style.color = "#f0ad4e"
        }
    } 
    if (avgMi >= 80) {
        resultMitsubishi.innerHTML = avgMi + "%"
        resultMitsubishi.style.color = "#5cb85c "
    }
}

for(var i = 0; i < peugeot.length; i++) {
    if(peugeot[i].innerText != "" && peugeot[i].innerText != "-") {
        arrayPeugeot.push(peugeot[i].innerText)
    } else if(peugeot[i].innerText == "") {
        peugeot[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayPeugeot.length; i++) {
    totalPeugeot += parseInt(arrayPeugeot[i]);
    var avgPe = Math.round(totalPeugeot / arrayPeugeot.length);
}
if (avgPe == undefined) {
    resultPeugeot.innerHTML = "-"
} else {
    if (avgPe < 60) {
        resultPeugeot.innerHTML = avgPe + "%"
        resultPeugeot.style.color = "#d9534f"
    }
    if (avgPe > 60) {
        if (avgPe < 80) {
            resultPeugeot.innerHTML = avgPe + "%"
            resultPeugeot.style.color = "#f0ad4e"
        }
    } 
    if (avgPe >= 80) {
        resultPeugeot.innerHTML = avgPe + "%"
        resultPeugeot.style.color = "#5cb85c "
    }
}

for(var i = 0; i < suzuki.length; i++) {
    if(suzuki[i].innerText != "" && suzuki[i].innerText != "-") {
        arraySuzuki.push(suzuki[i].innerText)
    } else if(suzuki[i].innerText == "") {
        suzuki[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arraySuzuki.length; i++) {
    totalSuzuki += parseInt(arraySuzuki[i]);
    var avgSu = Math.round(totalSuzuki / arraySuzuki.length);
}
if (avgSu == undefined) {
    resultSuzuki.innerHTML = "-"
} else {
    if (avgSu < 60) {
        resultSuzuki.innerHTML = avgSu + "%"
        resultSuzuki.style.color = "#d9534f"
    }
    if (avgSu > 60) {
        if (avgSu < 80) {
            resultSuzuki.innerHTML = avgSu + "%"
            resultSuzuki.style.color = "#f0ad4e"
        }
    } 
    if (avgSu >= 80) {
        resultSuzuki.innerHTML = avgSu + "%"
        resultSuzuki.style.color = "#5cb85c "
    }
}

for(var i = 0; i < toyota.length; i++) {
    if(toyota[i].innerText != "" && toyota[i].innerText != "-") {
        arrayToyota.push(toyota[i].innerText)
    } else if(toyota[i].innerText == "") {
        toyota[i].style.backgroundColor = "#f9f9f9"
    }
}
for(var i = 0; i < arrayToyota.length; i++) {
    totalToyota += parseInt(arrayToyota[i]);
    var avgToyota = Math.round(totalToyota / arrayToyota.length);
}
if (avgToyota == undefined) {
    resultToyota.innerHTML = "-"
} else {
    if (avgToyota < 60) {
        resultToyota.innerHTML = avgToyota + "%"
        resultToyota.style.color = "#d9534f"
    }
    if (avgToyota > 60) {
        if (avgToyota < 80) {
            resultToyota.innerHTML = avgToyota + "%"
            resultToyota.style.color = "#f0ad4e"
        }
    } 
    if (avgToyota >= 80) {
        resultToyota.innerHTML = avgToyota + "%"
        resultToyota.style.color = "#5cb85c "
    }
}
if ('<?php echo $selectedLevel ?>' != 'Junior') {
    for(var i = 0; i < specialityElectricite.length; i++) {
        if(specialityElectricite[i].innerText != "" && specialityElectricite[i].innerText != "-") {
            arraySpecialityElectricite.push(specialityElectricite[i].innerText)
        } else if(specialityElectricite[i].innerText == "") {
            specialityElectricite[i].style.backgroundColor = "#f9f9f9"
        }
    }
    
    for(var i = 0; i < arraySpecialityElectricite.length; i++) {
        totalSpecialityElectricite += parseInt(arraySpecialityElectricite[i]);
        var avgSpecialityElectricite = Math.round(totalSpecialityElectricite / arraySpecialityElectricite.length);
    }
    if (avgSpecialityElectricite == undefined) {
        resultSpecialityElectricite.innerHTML = "-"
    } else {
        if (avgSpecialityElectricite < 60) {
            resultSpecialityElectricite.innerHTML = avgSpecialityElectricite + "%"
            resultSpecialityElectricite.style.color = "#d9534f"
        }
        if (avgSpecialityElectricite > 60) {
            if (avgSpecialityElectricite < 80) {
                resultSpecialityElectricite.innerHTML = avgSpecialityElectricite + "%"
                resultSpecialityElectricite.style.color = "#f0ad4e"
            }
        } 
        if (avgSpecialityElectricite >= 80) {
            resultSpecialityElectricite.innerHTML = avgSpecialityElectricite + "%"
            resultSpecialityElectricite.style.color = "#5cb85c "
        }
    }
    
    for(var i = 0; i < specialityHydraulique.length; i++) {
        if(specialityHydraulique[i].innerText != "" && specialityHydraulique[i].innerText != "-") {
            arraySpecialityHydraulique.push(specialityHydraulique[i].innerText)
        } else if(specialityHydraulique[i].innerText == "") {
            specialityHydraulique[i].style.backgroundColor = "#f9f9f9"
        }
    }
    for(var i = 0; i < arraySpecialityHydraulique.length; i++) {
        totalSpecialityHydraulique += parseInt(arraySpecialityHydraulique[i]);
        var avgSpecialityHydraulique = Math.round(totalSpecialityHydraulique / arraySpecialityHydraulique.length);
    }
    if (avgSpecialityHydraulique == undefined) {
        resultSpecialityHydraulique.innerHTML = "-"
    } else {
        if (avgSpecialityHydraulique < 60) {
            resultSpecialityHydraulique.innerHTML = avgSpecialityHydraulique + "%"
            resultSpecialityHydraulique.style.color = "#d9534f"
        }
        if (avgSpecialityHydraulique > 60) {
            if (avgSpecialityHydraulique < 80) {
                resultSpecialityHydraulique.innerHTML = avgSpecialityHydraulique + "%"
                resultSpecialityHydraulique.style.color = "#f0ad4e"
            }
        } 
        if (avgSpecialityHydraulique >= 80) {
            resultSpecialityHydraulique.innerHTML = avgSpecialityHydraulique + "%"
            resultSpecialityHydraulique.style.color = "#5cb85c "
        }
    }
    
    for(var i = 0; i < specialityMoteur.length; i++) {
        if(specialityMoteur[i].innerText != "" && specialityMoteur[i].innerText != "-") {
            arraySpecialityMoteur.push(specialityMoteur[i].innerText)
        } else if(specialityMoteur[i].innerText == "") {
            specialityMoteur[i].style.backgroundColor = "#f9f9f9"
        }
    }
    for(var i = 0; i < arraySpecialityMoteur.length; i++) {
        totalSpecialityMoteur += parseInt(arraySpecialityMoteur[i]);
        var avgSpecialityMoteur = Math.round(totalSpecialityMoteur / arraySpecialityMoteur.length);
    }
    if (avgSpecialityMoteur == undefined) {
        resultSpecialityMoteur.innerHTML = "-"
    } else {
        if (avgSpecialityMoteur < 60) {
            resultSpecialityMoteur.innerHTML = avgSpecialityMoteur + "%"
            resultSpecialityMoteur.style.color = "#d9534f"
        }
        if (avgSpecialityMoteur > 60) {
            if (avgSpecialityMoteur < 80) {
                resultSpecialityMoteur.innerHTML = avgSpecialityMoteur + "%"
                resultSpecialityMoteur.style.color = "#f0ad4e"
            }
        } 
        if (avgSpecialityMoteur >= 80) {
            resultSpecialityMoteur.innerHTML = avgSpecialityMoteur + "%"
            resultSpecialityMoteur.style.color = "#5cb85c "
        }
    }
    
    for(var i = 0; i < specialityTransmission.length; i++) {
        if(specialityTransmission[i].innerText != "" && specialityTransmission[i].innerText != "-") {
            arraySpecialityTransmission.push(specialityTransmission[i].innerText)
        } else if(specialityTransmission[i].innerText == "") {
            specialityTransmission[i].style.backgroundColor = "#f9f9f9"
        }
    }
    for(var i = 0; i < arraySpecialityTransmission.length; i++) {
        totalSpecialityTransmission += parseInt(arraySpecialityTransmission[i]);
        var avgSpecialityTransmission = Math.round(totalSpecialityTransmission / arraySpecialityTransmission.length);
    }
    if (avgSpecialityTransmission == undefined) {
        resultSpecialityTransmission.innerHTML = "-"
    } else {
        if (avgSpecialityTransmission < 60) {
            resultSpecialityTransmission.innerHTML = avgSpecialityTransmission + "%"
            resultSpecialityTransmission.style.color = "#d9534f"
        }
        if (avgSpecialityTransmission > 60) {
            if (avgSpecialityTransmission < 80) {
                resultSpecialityTransmission.innerHTML = avgSpecialityTransmission + "%"
                resultSpecialityTransmission.style.color = "#f0ad4e"
            }
        } 
        if (avgSpecialityTransmission >= 80) {
            resultSpecialityTransmission.innerHTML = avgSpecialityTransmission + "%"
            resultSpecialityTransmission.style.color = "#5cb85c "
        }
    }
}

for(var i = 0; i < result.length; i++) {
    if(result[i].innerText != "" && result[i].innerText != "-") {
        array.push(result[i].innerText)
    }
}
for(var i = 0; i < array.length; i++) {
    total += parseInt(array[i]);
    var avg = Math.round(total / array.length);
}
if (avg == undefined) {
    resultTotal.innerHTML = "-"
} else {
    if (avg < 60) {
        resultTotal.innerHTML = avg + "%"
        resultTotal.style.color = "#d9534f"
    }
    if (avg > 60) {
        if (avg < 80) {
            resultTotal.innerHTML = avg + "%"
            resultTotal.style.color = "#f0ad4e"
        }
    } 
    if (avg >= 80) {
        resultTotal.innerHTML = avg + "%"
        resultTotal.style.color = "#5cb85c "
    }
}

// console.log(array)
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
