<?php
session_start();
include_once "../language.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* 1. Sécurité session -------------------------------------------------- */
if (!isset($_SESSION['profile'])) {
    header('Location: ../../');
    exit();
}

/* 2. Connexion Mongo  -------------------------------------------------- */
require_once "../../vendor/autoload.php";
$mongo   = new Client("mongodb://localhost:27017");
$db      = $mongo->academy;
$colUser = $db->users;
$colConn = $db->connections;

/* 3. Helpers ----------------------------------------------------------- */
function canSee(array $profiles) {
    return in_array($_SESSION['profile'], $profiles, true);
}

/*– Qui peut voir la ligne ? –*/
function userVisible($u) {
    $p   = $_SESSION['profile'];
    $sub = $_SESSION['subsidiary'] ?? null;
    switch ($p) {
        case 'Admin':
            return !in_array($u->profile,
                ['Admin','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                && $u->subsidiary === $sub;
        case 'Ressource Humaine':
            return !in_array($u->profile,
                ['Ressource Humaine','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin','Admin'], true)
                && $u->subsidiary === $sub;
        case 'Super Admin':
            return $u->profile !== 'Super Admin';
        case 'Directeur Groupe':
            return !in_array($u->profile, ['Super Admin','Directeur Groupe'], true);
        case 'Directeur Pièce et Service':
        case 'Directeur des Opérations':
            return !in_array($u->profile,
                ['Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                && $u->subsidiary === $sub;
        default:
            return false;
    }
}

/* 4. Export Excel ------------------------------------------------------ */
if (isset($_POST['excel'])) {
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $fields = [
        ["username","Nom d'utilisateur"],
        ["matricule","Matricule"],
        ["firstName","Prénoms"],
        ["lastName","Noms"],
        ["email","Email"],
        ["phone","Numéro de téléphone"],
        ["gender","Sexe"],
        ["birthdate","Date de naissance"],
        ["level","Niveau technique"],
        ["country","Pays"],
        ["profile","Profil"],
        ["certificate","Diplôme"],
        ["subsidiary","Filiale"],
        ["department","Département"],
        ["role","Fonction"],
        ["recrutmentDate","Date de recrutement"],
        ["visiblePassword","Mot de Passe"],
    ];

    /* en-têtes */
    foreach ($fields as $i => $f) {
        $sheet->setCellValueByColumnAndRow($i+1, 1, $f[1]);
    }

    /* projection = champs utiles seulement */
    $cursor = $colUser->find([], ['projection'=>array_column($fields,0,0)]);
    $row=2;
    foreach ($cursor as $u) {
        foreach ($fields as $j=>$f) {
            $sheet->setCellValueByColumnAndRow($j+1,$row, $u[$f[0]] ?? '');
        }
        $row++;
    }
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Utilisateurs.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit();
}

/* 5. Préparation des données ------------------------------------------ */
$match = ['active'=>true];
if (!in_array($_SESSION['profile'], ['Super Admin','Directeur Groupe'], true) &&
    ($_SESSION['department'] ?? '') !== 'Equipment & Motors') {
    $match['department'] = $_SESSION['department'];
}
$projection = [
    'firstName'=>1,'lastName'=>1,'email'=>1,'country'=>1,'profile'=>1,
    'department'=>1,'level'=>1,'username'=>1,'visiblePassword'=>1,
    'subsidiary'=>1,'users'=>1,'active'=>1
];
$usersCursor = $colUser->find($match, ['projection'=>$projection]);

/* Récupérer d’un coup les statuts online */
function getUserIds($it) {
    foreach($it as $u) {
        yield $u->_id;
    }
}
$usersArray = iterator_to_array($usersCursor, false);
$userIds = array_map(function($u) { return $u->_id; }, $usersArray);
$online = [];
if ($userIds) {
    $on = $colConn->find(['user'=>['$in'=>$userIds],'status'=>'Online'], ['projection'=>['user'=>1]]);
    foreach ($on as $c) { $online[(string)$c->user]=true; }
}

/* 6. Affichage HTML ---------------------------------------------------- */
include_once "partials/header.php";
?>
<!--begin::Title-->
<title><?= $list_user ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!-- ================= TABLE ================= -->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="toolbar" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo $list_user ?>
                </h1>
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12" placeholder="<?php echo $recherche ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <div class="container-xxl">
            <div class="card">
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable" id="kt_customers_table">
    <thead>
        <tr class="text-start text-black fw-bold fs-7 text-uppercase">
            <th class="w-10px pe-2"></th>
            <?php
            $cols = [
                'name'   => [$prenomsNoms, ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
                'email'  => [$email     , ['Admin']],
                'pays'   => [$pays      , ['Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
                'profil' => [$profil    , ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
                'dept'   => [$department, ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
                'level'  => [$levelTech , ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
                'user'   => [$username  , ['Admin','Super Admin']],
                'pwd'    => [$Password  , ['Admin','Super Admin']],
                'stat'   => [$status    , ['Super Admin']],
                'collab' => [$title_collaborators, ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']]
            ];
            foreach ($cols as $c) if (canSee($c[1])) echo "<th>{$c[0]}</th>";
            ?>
        </tr>
    </thead>
    <tbody class="fw-semibold text-gray-600">
        <?php $modals=''; foreach ($usersArray as $u): if (!userVisible($u)) continue; ?>
        <tr>
            <td></td>
            <?php if (canSee($cols['name'][1]))  echo '<td>'.htmlspecialchars("{$u->firstName} {$u->lastName}").'</td>'; ?>
            <?php if (canSee($cols['email'][1])) echo '<td>'.htmlspecialchars($u->email ?? '').'</td>'; ?>
            <?php if (canSee($cols['pays'][1]))  echo '<td>'.htmlspecialchars($u->country ?? '').'</td>'; ?>

            <!-- Profil / Dept / Level toujours visibles pour ceux qui peuvent voir la ligne -->
            <td><?= htmlspecialchars(($u->profile==='Manager' && ($u->test??false)) ? 'Manager - Technicien' : $u->profile) ?></td>
            <td><?= htmlspecialchars($u->department ?? '') ?></td>
            <td><?= htmlspecialchars($u->level ?? '') ?></td>

            <?php if (canSee($cols['user'][1])) echo '<td>'.htmlspecialchars($u->username ?? '').'</td>'; ?>
            <?php if (canSee($cols['pwd'][1]))  echo '<td>'.htmlspecialchars($u->visiblePassword ?? '').'</td>'; ?>

            <?php if (canSee($cols['stat'][1])): ?>
                <?php $on = !empty($online[(string)$u->_id]); ?>
                <td><span class="badge <?= $on?'badge-light-success':'badge-light-danger' ?> fs-7 m-1"><?= $on?'Online':'Offline' ?></span></td>
            <?php endif; ?>

            <?php if ($u->profile==='Manager' && canSee($cols['collab'][1])): ?>
                <td><a href="#" class="btn btn-light btn-active-light-primary text-primary fw-bolder btn-sm"
                       data-bs-toggle="modal" data-bs-target="#collab_<?= $u->_id ?>"><?= $voir_collab ?></a></td>
            <?php endif; ?>
        </tr>

        <!-- ===== modale collaborateurs (collectée) ===== -->
        <?php if ($u->profile==='Manager' && canSee($cols['collab'][1])):
            ob_start(); ?>
            <div class="modal fade" id="collab_<?= $u->_id ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog mw-650px"><div class="modal-content">
                    <div class="modal-header pb-0 border-0 justify-content-end">
                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-1"></i>
                        </div>
                    </div>
                    <div class="modal-body scroll-y mx-5 mx-xl-18 pt-0 pb-15">
                        <div class="text-center mb-13"><h1 class="mb-3"><?= $title_collaborators ?></h1></div>
                        <div class="mh-300px scroll-y me-n7 pe-7">
                            <?php
                            $collabs = $colUser->find(
                                ['_id'=>['$in'=>$u->users ?? []],'active'=>true],
                                ['projection'=>['firstName'=>1,'lastName'=>1]]
                            );
                            foreach ($collabs as $c):
                                echo '<div class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                      <div class="d-flex align-items-center ms-5">
                                      <a href="#" class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2">'
                                      .htmlspecialchars($c->firstName.' '.$c->lastName).'</a></div></div>';
                            endforeach; ?>
                        </div>
                    </div>
                </div></div>
            </div>
        <?php $modals .= ob_get_clean(); endif; endforeach; ?>
    </tbody>
</table>

                    </div>
                      <!-- ====== ROW pagination & length ====== -->
    <div class="row">
        <div class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
            <div class="dataTables_length">
                <label>
                    <select id="kt_customers_table_length" name="kt_customers_table_length"
                            class="form-select form-select-sm form-select-solid">
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                        <option value="500">500</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
            <div class="dataTables_paginate paging_simple_numbers">
                <ul class="pagination" id="kt_customers_table_paginate"></ul>
            </div>
        </div>
    </div>
    <!-- ====== /ROW ====== -->
                </div>
            </div>
        </div>
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
<?= $modals /* on pousse toutes les modales ici */ ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.3.js"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>
<script src="../../public/js/main.js"></script>
<script>
    $(function(){
        $('#excel').on('click', ()=> TableToExcel.convert(document.querySelector('table'),{name:'Users.xlsx'}));
    });
</script>
    <?php include_once "partials/footer.php"; ?>
