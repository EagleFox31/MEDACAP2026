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

    $user = $_GET["user"];
    $level = $_GET["level"];
    $speciality = $_GET["speciality"];
    $numberTest = $_GET["numberTest"];

    $technician = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($user),
                "active" => true,
            ],
        ],
    ]);
    ?>
<title><?php echo $result_tech; ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<!-- Favicon -->
<link href="../../public/images/logo-cfao.png" rel="icon">

<meta charset="utf-8" />
<meta name="description"
    content="Craft admin dashboard live demo. Check out all the features of the admin panel. A large number of settings, additional services and widgets." />
<meta name="keywords"
    content="Craft, bootstrap, bootstrap 5, admin themes, dark mode, free admin themes, bootstrap admin, bootstrap dashboard" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta property="og:locale" content="en_US" />
<meta property="og:type" content="article" />
<meta property="og:title" content="Craft - Bootstrap 5 HTML Admin Dashboard Theme" />
<meta property="og:url" content="https://themes.getbootstrap.com/product/craft-bootstrap-5-admin-dashboard-theme" />
<meta property="og:site_name" content="Keenthemes | Craft" />
<link rel="canonical" href="https://preview.keenthemes.com/craft" />
<link rel="shortcut icon" href="/images/logo-cfao.png" />
<!--begin::Fonts(mandatory for all pages)-->
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
<!--end::Fonts-->
<!--begin::Vendor Stylesheets(used for this page only)-->
<link href="../../public/assets/plugins/custom/leaflet/leaflet.bundle.css" rel="stylesheet" type="text/css" />
<link href="../../public/assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css"
    integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<!--end::Vendor Stylesheets-->
<!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
<link href="../../public/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
<link href="../../public/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/habibmhamadi/multi-select-tag/dist/css/multi-select-tag.css">
<!--end::Global Stylesheets Bundle-->

<!--begin::Body-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content" data-select2-id="select2-data-kt_content">
    <!-- Spinner Start -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Chargement...</span>
        </div>
    </div>
    <!-- Spinner End -->
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1" style="font-size: 30px;">
                    <?php echo $result_to ?>
                    <?php echo $technician->firstName; ?> <?php echo $technician->lastName; ?>
                </h1>
                <!--end::Title-->
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1" style="font-size: 50px;">
                    <?php echo $groupe_fonctionnel ?>:
                    <?php echo $speciality; ?>
                </h1>
                <!--end::Title-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
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
                        <!--begin::Export-->
                        <button type="button" id="excel" class="btn btn-light-primary me-3" data-bs-toggle="modal"
                            data-bs-target="#kt_customers_export_modal">
                            <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span
                                    class="path2"></span></i> <?php echo $excel ?>
                        </button>
                        <!--end::Export-->
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
                    <div class="table-responsi">
                        <table aria-describedby=""
                            class="table align-middle table-bordered table-row-dashed fs-7 gy-3 dataTable no-footer"
                            id="kt_customers_table">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold text-uppercase gs-0">
                                    <th class="min-w-125px sorting bg-primary text-white text-center table-light"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="6"
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; font-size: 20px; ">
                                        <?php echo $result_mesure ?></th>
                                <tr></tr>
                                <th class="min-w-400px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $connaissances ?></th>
                                <th class="min-w-800px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="4"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $tache_pro ?></th>
                                <tr></tr>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $question ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $result ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $question ?></th>
                                <th class="min-w-130px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $result_tech ?></th>
                                <th class="min-w-120px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $result_manager ?></th>
                                <th class="min-w-120px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $result ?></th>
                                <tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600" id="table">
                                <?php
                                $groupeFac = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $level],
                                        ["speciality" => $speciality],
                                        ["type" => "Factuel"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => false],
                                    ],
                                ]);
                                $groupeDecla = $results->findOne([
                                    '$and' => [
                                        [
                                            "user" => new MongoDB\BSON\ObjectId(
                                                $user
                                            ),
                                        ],
                                        ["level" => $level],
                                        ["speciality" => $speciality],
                                        ["type" => "Declaratif"],
                                        ["numberTest" => +$numberTest],
                                        ["active" => false],
                                    ],
                                ]);
                                $groupeMa = $results->findOne([
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
                                        ["level" => $level],
                                        ["speciality" => $speciality],
                                        ["numberTest" => +$numberTest],
                                        ["active" => false],
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
                                    $i++
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
                                    <?php if (
                                        isset($groupeFac->answers[$i])
                                    ) { ?>
                                    <td class="text-center">
                                        <?php echo $questionFac->label; ?>
                                    </td>
                                    <td class="text-center" name="savoir">
                                        <?php echo $groupeFac->answers[$i]; ?>
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                    </td>
                                    <td class="text-center">
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        <?php echo $questionDecla->label; ?>
                                    </td>
                                    <td class="text-center" name="n">
                                        <?php echo $groupeDecla->answers[$i]; ?>
                                    </td>
                                    <td class="text-center" name="n1">
                                        <?php echo $groupeMa->answers[$i]; ?>
                                    </td>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Non" &&
                                        $groupeMa->answers[$i] == "Non"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] !=
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
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
                                    <td class="text-center">
                                        <?php echo $questionFac->label; ?>
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
                                    <td class="text-center" name="n">
                                        <?php echo $groupeDecla->answers[$i]; ?>
                                    </td>
                                    <td class="text-center" name="n1">
                                        <?php echo $groupeMa->answers[$i]; ?>
                                    </td>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Non" &&
                                        $groupeMa->answers[$i] == "Non"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] !=
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php } else { ?>
                                    <td class="text-center">
                                    </td>
                                    <td class="text-center">
                                    </td>
                                    <td class="text-center">
                                    </td>
                                    <td class="text-center">
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
                                    <td class="text-center">
                                        <?php echo $questionFac->label; ?>
                                    </td>
                                    <td class="text-center" name="savoir">
                                        <?php echo $groupeFac->answers[$i]; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $questionDecla->label; ?>
                                    </td>
                                    <td class="text-center" name="n">
                                        <?php echo $groupeDecla->answers[$i]; ?>
                                    </td>
                                    <td class="text-center" name="n1">
                                        <?php echo $groupeMa->answers[$i]; ?>
                                    </td>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Oui" &&
                                        $groupeMa->answers[$i] == "Oui"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] == "Non" &&
                                        $groupeMa->answers[$i] == "Non"
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        $groupeDecla->answers[$i] !=
                                        $groupeMa->answers[$i]
                                    ) { ?>
                                    <td class="text-center" name="savoirs-faire">
                                        <?php echo $non_maitrise ?>
                                    </td>
                                    <?php } ?>
                                </tr>
                                <?php
                                } ?>
                                <?php }} ?>
                                <!--end::Menu-->
                                <tr>
                                    <th id=""
                                        class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Résultats</th>
                                    <th id="result-savoir"
                                        class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo ceil(
                                            ($groupeFac->score * 100) /
                                                $groupeFac->total); ?>%
                                    </th>
                                    <th id=""
                                        class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        Résultats</th>
                                    <th id="result-n"
                                        class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo ceil(
                                            ($groupeDecla->score * 100) /
                                                $groupeDecla->total); ?>%
                                    </th>
                                    <th id="result-n1"
                                        class="min-w-120px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo ceil(
                                            ($groupeMa->score * 100) /
                                                $groupeMa->total); ?>%
                                    </th>
                                    <th id="result-savoir-faire"
                                        class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" colspan="1" aria-controls="kt_customers_table"
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

const valueMaitrisé = "Maitrisé"
const valueOui = "Oui"
const savoir = []
const savoirFaire = []
const n = []
const n1 = []
const tdSavoir = document.querySelectorAll("td[name='savoir']")
const tdSavoirFaire = document.querySelectorAll("td[name='savoirs-faire']")
const tdN = document.querySelectorAll("td[name='n']")
const tdN1 = document.querySelectorAll("td[name='n1']")
const resultN = document.querySelector("#result-n")
const resultN1 = document.querySelector("#result-n1")
const resultSavoir = document.querySelector("#result-savoir")
const resultSavoirFaire = document.querySelector("#result-savoir-faire")

for (let i = 0; i < tdSavoir.length; i++) {
    savoir.push(tdSavoir[i].innerHTML)
}
for (let i = 0; i < tdSavoirFaire.length; i++) {
    savoirFaire.push(tdSavoirFaire[i].innerHTML)
}
for (let i = 0; i < tdN.length; i++) {
    n.push(tdN[i].innerHTML)
}
for (let i = 0; i < tdN1.length; i++) {
    n1.push(tdN1[i].innerHTML)
}

const maitriseSavoir = savoir.filter(function(str) {
    return str.includes(valueMaitrisé)
})
const maitriseSavoirFaire = savoirFaire.filter(function(str) {
    return str.includes(valueMaitrisé)
})
const ouiN = n.filter(function(str) {
    return str.includes(valueOui)
})
const ouiN1 = n1.filter(function(str) {
    return str.includes(valueOui)
})

const percentSavoir = ((maitriseSavoir.length * 100) / tdSavoir.length).toFixed(0)
const percentSavoirFaire = ((maitriseSavoirFaire.length * 100) / tdSavoirFaire.length).toFixed(0)
const percentN = ((ouiN.length * 100) / tdN.length).toFixed(0)
const percentN1 = ((ouiN1.length * 100) / tdN1.length).toFixed(0)

// resultSavoir.innerHTML = percentSavoir + "%";
resultSavoirFaire.innerHTML = percentSavoirFaire + "%";
// resultN.innerHTML = percentN + "%";
// resultN1.innerHTML = percentN1 + "%";
</script>
<?php
} ?>
