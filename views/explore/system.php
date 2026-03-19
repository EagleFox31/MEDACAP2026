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
                <h1 class="text-dark fw-bold my-1" style="font-size: 25px;">
                    <?php echo $result.' du Niveau '.$_GET['level'].' de' ?>
                    <?php echo $technician->firstName; ?> <?php echo $technician->lastName; ?>
                </h1>
                <!--end::Title-->
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1" style="font-size: 20px;">
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
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-bordered table-row-dashed fs-7 gy-3 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bold text-uppercase gs-0">
                                        <th class="min-w-300px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $connaissances ?></th>
                                        <th class="min-w-700px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table" colspan="5"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $tache_pro ?></th>
                                    <tr></tr>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $question ?></th>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $result ?></th>
                                    <th class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $question ?></th>
                                    <th class="min-w-130px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                        <?php echo $result_tech ?></th>
                                    <th class="min-w-120px sorting  text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $result_manager ?></th>
                                    <th class="min-w-120px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $result ?></th>
                                    <th class="min-w-120px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $fiabilite ?></th>
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
                                            ["active" => true],
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
                                            ["typeR" => "Technicien"],
                                            ["type" => "Declaratif"],
                                            ["numberTest" => +$numberTest],
                                            ["active" => true],
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
                                        <?php if (
                                            $groupeDecla->answers[$i] ==
                                            $groupeMa->answers[$i] 
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $groupeDecla->answers[$i] !=
                                            $groupeMa->answers[$i]
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non ?>
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
                                        <?php if (
                                            $groupeDecla->answers[$i] ==
                                            $groupeMa->answers[$i] 
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $groupeDecla->answers[$i] !=
                                            $groupeMa->answers[$i]
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non ?>
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
                                        <td class="text-center">
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php
                                    } ?>
                                    <?php } elseif (
                                        count($groupeFac->questions) ==
                                        count($groupeDecla->questions)) { ?>
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
                                        <?php if (
                                            $groupeDecla->answers[$i] ==
                                            $groupeMa->answers[$i] 
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $oui ?>
                                        </td>
                                        <?php } ?>
                                        <?php if (
                                            $groupeDecla->answers[$i] !=
                                            $groupeMa->answers[$i]
                                        ) { ?>
                                        <td class="text-center" name="fiable">
                                            <?php echo $non ?>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php
                                    } ?>
                                    <?php }} ?>
                                    <!--end::Menu-->
                                    <tr style=" position: sticky; bottom: 0; z-index: 2;">
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $result ?></th>
                                        <th id="result-savoir"
                                            class="min-w-125px sorting text-black <?php echo getclasses($groupeFac) ?> table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo ceil(
                                                ($groupeFac->score * 100) /
                                                    $groupeFac->total
                                            ); ?>%
                                        </th>
                                        <th id=""
                                            class="min-w-125px sorting text-black text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo $result ?></th>
                                        <th id="result-n"
                                            class="min-w-125px sorting <?php echo getclasses($groupeDecla) ?> text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo ceil(
                                                ($groupeDecla->score * 100) /
                                                    $groupeDecla->total
                                            ); ?>%
                                        </th>
                                        <th id="result-n1"
                                            class="min-w-120px sorting <?php echo getclasses($groupeMa) ?> text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                            <?php echo ceil(
                                                ($groupeMa->score * 100) /
                                                    $groupeMa->total
                                            ); ?>%
                                        </th>
                                        <th id="result-savoir-faire"
                                            class="min-w-125px sorting text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;">
                                        </th>
                                        <th id="result-fiable"
                                            class="min-w-125px sorting text-center table-light fw-bold text-uppercase gs-0"
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
const fiable = []
const n = []
const n1 = []
const tdSavoir = document.querySelectorAll("td[name='savoir']")
const tdSavoirFaire = document.querySelectorAll("td[name='savoirs-faire']")
const tdFiable = document.querySelectorAll("td[name='fiable']")
const tdN = document.querySelectorAll("td[name='n']")
const tdN1 = document.querySelectorAll("td[name='n1']")
const resultN = document.querySelector("#result-n")
const resultN1 = document.querySelector("#result-n1")
const resultSavoir = document.querySelector("#result-savoir")
const resultSavoirFaire = document.querySelector("#result-savoir-faire")
const resultSFiable = document.querySelector("#result-fiable")

for (let i = 0; i < tdSavoir.length; i++) {
    savoir.push(tdSavoir[i].innerHTML)
}
for (let i = 0; i < tdSavoirFaire.length; i++) {
    savoirFaire.push(tdSavoirFaire[i].innerHTML)
}
for (let i = 0; i < tdFiable.length; i++) {
    fiable.push(tdFiable[i].innerHTML)
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
const ouiFiable = fiable.filter(function(str) {
    return str.includes(valueOui)
})

const percentSavoir = Math.ceil((maitriseSavoir.length * 100) / tdSavoir.length)
const percentSavoirFaire = Math.ceil((maitriseSavoirFaire.length * 100) / tdSavoirFaire.length)
const percentFiable = Math.ceil((ouiFiable.length * 100) / tdFiable.length)
const percentN = Math.ceil((ouiN.length * 100) / tdN.length)
const percentN1 = Math.ceil((ouiN1.length * 100) / tdN1.length)

// resultSavoir.innerHTML = percentSavoir + "%";
if (percentSavoirFaire >= 80) {
    resultSavoirFaire.innerHTML = percentSavoirFaire + "%";
    resultSavoirFaire.style.color = '#5cb85c'; // Green
} else if (percentSavoirFaire >= 60) {
    resultSavoirFaire.innerHTML = percentSavoirFaire + "%";
    resultSavoirFaire.style.color = '#f0ad4e'; // Yellow
} else {
    resultSavoirFaire.innerHTML = percentSavoirFaire + "%";
    resultSavoirFaire.style.color = '#d9534f'; // Orange
}

if (percentFiable >= 80) {
    resultSFiable.innerHTML = percentFiable + "%";
    resultSFiable.style.color = '#5cb85c'; // Green
} else if (percentFiable >= 60) {
    resultSFiable.innerHTML = percentFiable + "%";
    resultSFiable.style.color = '#f0ad4e'; // Yellow
} else {
    resultSFiable.innerHTML = percentFiable + "%";
    resultSFiable.style.color = '#d9534f'; // Orange
}
// resultN.innerHTML = percentN + "%";
// resultN1.innerHTML = percentN1 + "%";
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?>