<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

// ---------- Connexion MongoDB ----------
require_once "../../vendor/autoload.php";

use MongoDB\Model\BSONArray;

$conn        = new MongoDB\Client("mongodb://localhost:27017");
$academy     = $conn->academy;
$users       = $academy->users;
$tests       = $academy->tests;
$vehicles    = $academy->vehicles;
$allocations = $academy->allocations;
$results     = $academy->results;

/**
 * Crée ou met à jour un test et son allocation pour un user/brand/type/level.
 */
function allocateTests(string $type, string $level, $userId, array $brands)
{
    global $tests, $vehicles, $allocations, $users;

    // Recherche du test existant
    $testExists = $tests->findOne([
        'user'  => $userId,
        'type'  => $type,
        'level' => $level,
    ]);

    // Récupération des quizzes des marques sélectionnées
    $allQuizzes = [];
    foreach ($brands as $brand) {
        $veh = $vehicles->findOne([
            'brand'  => $brand,
            'type'   => $type,
            'level'  => $level,
            'active' => true,
        ]);
        if ($veh && isset($veh['quizzes'])) {
            $q = $veh['quizzes'];
            if ($q instanceof BSONArray) {
                $q = $q->getArrayCopy();
            }
            if (is_array($q)) {
                $allQuizzes = array_merge($allQuizzes, $q);
            }
        }
        // On ajoute la marque au profil utilisateur
        $users->updateOne(
            ['_id' => $userId],
            ['$addToSet' => ["brand{$level}" => $brand]]
        );
    }
    $allQuizzes = array_values(array_unique($allQuizzes));

    if ($testExists) {
        // Fusion avec les quizzes existants
        $existing = [];
        if (isset($testExists['quizzes'])) {
            $q = $testExists['quizzes'];
            if ($q instanceof BSONArray) {
                $q = $q->getArrayCopy();
            }
            if (is_array($q)) {
                $existing = $q;
            }
        }
        $merged = array_values(array_unique(array_merge($existing, $allQuizzes)));

        // Mise à jour : brand en $addToSet, quizzes et total en $set
        $tests->updateOne(
            ['_id' => $testExists['_id']],
            [
                '$addToSet' => [
                    'brand' => ['$each' => $brands],
                ],
                '$set' => [
                    'quizzes' => $merged,
                    'total'   => count($merged),
                ],
            ]
        );
        $testId = $testExists['_id'];
    } else {
        // Création
        $doc = [
            'user'    => $userId,
            'type'    => $type,
            'level'   => $level,
            'brand'   => $brands,
            'quizzes' => $allQuizzes,
            'total'   => count($allQuizzes),
            'active'  => true,
            'created' => date("Y-m-d H:i:s"),
        ];
        $res    = $tests->insertOne($doc);
        $testId = $res->getInsertedId();
    }

    $set = [
        'active'     => false,
        'activeTest' => true,
        'created'    => date("Y-m-d H:i:s"),
    ];

    // 2. Ajouter la ligne demandée quand c’est un test Déclaratif
    if ($type === 'Declaratif') {
        $set['activeManager'] = false;   // ✅ bien placé
    }

    // 3. Exécuter l’upsert
    $allocations->updateOne(
        [
            'user'  => $userId,
            'test'  => $testId,
            'type'  => $type,
            'level' => $level,
        ],
        ['$set' => $set],
        ['upsert' => true]
    );
}

// ---------- 1) AJAX : renvoi matrice Type×Niveau en JSON ----------
if (isset($_GET['techId'])) {
    $cursor = $tests->find(
        ['user' => new MongoDB\BSON\ObjectId($_GET['techId'])],
        ['projection' => ['type' => 1, 'level' => 1]]
    );
    $matrix = [
        'Factuel'    => ['Junior' => false, 'Senior' => false, 'Expert' => false],
        'Declaratif' => ['Junior' => false, 'Senior' => false, 'Expert' => false],
    ];
    foreach ($cursor as $t) {
        $matrix[$t->type][$t->level] = true;
    }
    header('Content-Type: application/json');
    echo json_encode($matrix);
    exit;
}

// ---------- 2) Traitement du formulaire ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectBrands = $_POST['brand']       ?? [];
    $level        = $_POST['level']       ?? '';
    $techId       = $_POST['technician']  ?? '';

    if (empty($selectBrands) || !$techId || !$level) {
        $error_msg = $champ_obligatoire;
    } else {
        $user = $users->findOne([
            '_id' => new MongoDB\BSON\ObjectId($techId),
            'active' => true
        ]);
        if (!$user) {
            $error_msg = $user_introuvable;
        } else {
            // Désactive les anciens résultats actifs pour ce niveau
            $results->updateMany(
                [
                    'user' => $user['_id'],
                    'level' => $level,
                    'active' => true
                ],
                ['$set' => ['active' => false]]
            );

            // Alloue/Met à jour pour Factuel et Déclaratif
            allocateTests('Factuel',    $level, $user['_id'], $selectBrands);
            allocateTests('Declaratif', $level, $user['_id'], $selectBrands);

            $success_msg = $success_allocation;
        }
    }
}

// ---------- Affichage ----------
include_once "partials/header.php";
?>
<title><?= $title_allocateUserTest ?> | CFAO Mobility Academy</title>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class="my-3 text-center"><?php echo $allocate_test ?></h1>

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

                <form method="POST">
                    <!-- Technicien -->
                    <!-- begin::Input group -->
                    <div class="fv-row mb-7">
                        <div class="d-flex flex-column mb-7 fv-row">
                            <!-- Label -->
                            <label class="form-label fw-bolder text-dark fs-6">
                                <span class="required"><?= $technicien ?></span>
                                <span class="ms-1" data-bs-toggle="tooltip" title="Veuillez choisir le technicien">
                                    <i class="ki-duotone ki-information fs-7">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                </span>
                            </label>
                            <!-- Select natif (plus de data-control) -->
                            <select name="technician"
                                class="form-select form-select-solid fw-bold"
                                aria-label="Select a Country">
                                <option value=""><?= $select_technicien ?></option>
                                <?php foreach ($users->find(['profile' => 'Technicien', 'active' => true]) as $u): ?>
                                    <option value="<?= $u->_id ?>"
                                        <?= (isset($_POST['technician']) && $_POST['technician'] == (string)$u->_id)
                                            ? 'selected' : '' ?>>
                                        <?= $u->firstName . ' ' . $u->lastName ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Matrice -->
                            <div id="matrix-placeholder" class="mt-3"></div>

                            <?php if (isset($error)): ?>
                                <span class="text-danger"><?= $error ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- end::Input group -->

                    <!--begin::Input group-->
                    <div class="row fv-row mb-7">
                        <!--begin::Col-->
                        <div class="col-xl-6">
                            <div class="d-flex flex-column mb-7 fv-row">
                                <!--begin::Label-->
                                <label class="form-label fw-bolder text-dark fs-6">
                                    <span class="required"><?php echo $brand ?></span>
                                    <span class="ms-1" data-bs-toggle="tooltip" title="Veuillez choisir le véhicule">
                                        <i class="ki-duotone ki-information fs-7">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                    </span>
                                </label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <select name="brand[]" multiple
                                    aria-label="Select a Country"
                                    data-control="select2"
                                    data-placeholder="<?php echo $select_brand ?>"
                                    class="form-select form-select-solid fw-bold">
                                    <option value=""><?php echo $select_brand ?></option>
                                    <option value="FUSO" <?php if (in_array('FUSO', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $fuso ?></option>
                                    <option value="HINO" <?php if (in_array('HINO', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $hino ?></option>
                                    <option value="JCB" <?php if (in_array('JCB', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $jcb ?></option>
                                    <option value="KING LONG" <?php if (in_array('KING LONG', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $kingLong ?></option>
                                    <option value="LOVOL" <?php if (in_array('LOVOL', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $lovol ?></option>
                                    <option value="MERCEDES TRUCK" <?php if (in_array('MERCEDES TRUCK', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $mercedesTruck ?></option>
                                    <option value="RENAULT TRUCK" <?php if (in_array('RENAULT TRUCK', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $renaultTruck ?></option>
                                    <option value="SINOTRUCK" <?php if (in_array('SINOTRUCK', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $sinotruk ?></option>
                                    <option value="TOYOTA BT" <?php if (in_array('TOYOTA BT', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $toyotaBt ?></option>
                                    <option value="TOYOTA FORKLIFT" <?php if (in_array('TOYOTA FORKLIFT', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $toyotaForklift ?></option>
                                    <option value="BYD" <?php if (in_array('BYD', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $byd ?></option>
                                    <option value="CITROEN" <?php if (in_array('CITROEN', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $citroen ?></option>
                                    <option value="MERCEDES" <?php if (in_array('MERCEDES', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $mercedes ?></option>
                                    <option value="MITSUBISHI" <?php if (in_array('MITSUBISHI', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $mitsubishi ?></option>
                                    <option value="PEUGEOT" <?php if (in_array('PEUGEOT', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $peugeot ?></option>
                                    <option value="SUZUKI" <?php if (in_array('SUZUKI', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $suzuki ?></option>
                                    <option value="TOYOTA" <?php if (in_array('TOYOTA', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $toyota ?></option>
                                    <option value="VOLKSWAGEN" <?php if (in_array('VOLKSWAGEN', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $volkswagen ?? 'VOLKSWAGEN'; ?></option>
                                    <option value="YAMAHA BATEAU" <?php if (in_array('YAMAHA BATEAU', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $yamahaBateau ?></option>
                                    <option value="YAMAHA MOTO" <?php if (in_array('YAMAHA MOTO', $selectBrands ?? [])) echo 'selected'; ?>><?php echo $yamahaMoto ?></option>
                                </select>
                                <?php if (isset($error)): ?>
                                    <span class="text-danger"><?php echo $error ?></span>
                                <?php endif; ?>
                                <!--end::Input-->
                            </div>
                        </div>
                        <!--end::Col-->

                        <!--end::Input group-->


                        <!--end::Input group-->
                        <!--begin::Input group-->
                        <div class="col-xl-6">
                            <div class="d-flex flex-column mb-7 fv-row">
                                <!--begin::Label-->
                                <label class="form-label fw-bolder text-dark fs-6">
                                    <span class="required"><?php echo $Level ?></span>
                                    <span class="ms-1" data-bs-toggle="tooltip" title="Veuillez choisir le niveau">
                                        <i class="ki-duotone ki-information fs-7">
                                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                        </i>
                                    </span>
                                </label>
                                <!--end::Label-->
                                <!--begin::Input-->
                                <select name="level"
                                    aria-label="Select a Country"
                                    data-control="select2"
                                    data-placeholder="<?php echo $select_level ?>"
                                    class="form-select form-select-solid fw-bold">
                                    <option value=""><?php echo $select_level ?></option>
                                    <option value="Junior" <?php if (isset($_POST['level']) && $_POST['level'] == 'Junior') echo 'selected'; ?>>
                                        <?php echo $junior ?> (<?php echo $maintenance ?>)
                                    </option>
                                    <option value="Senior" <?php if (isset($_POST['level']) && $_POST['level'] == 'Senior') echo 'selected'; ?>>
                                        <?php echo $senior ?> (<?php echo $reparation ?>)
                                    </option>
                                    <option value="Expert" <?php if (isset($_POST['level']) && $_POST['level'] == 'Expert') echo 'selected'; ?>>
                                        <?php echo $expert ?> (<?php echo $diagnostic ?>)
                                    </option>
                                </select>
                                <?php if (isset($error)) { ?>
                                    <span class='text-danger'>
                                        <?php echo $error; ?>
                                    </span>
                                <?php } ?>
                                <!--end::Input-->
                            </div>
                        </div>
                    </div>
                    <!--end::Input group-->
                    <div class="text-center" style="margin-bottom: 50px;"></div>

                    <button type="submit" class="btn btn-primary">
                        <?= $valider ?>
                    </button>
            </div>
            </form>
        </div>

    </div>
</div>




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

    document.querySelector('select[name="technician"]').addEventListener('change', async function() {
        const id = this.value,
            cont = document.getElementById('matrix-placeholder');
        if (!id) return cont.innerHTML = '';
        const res = await fetch(`?techId=${id}`),
            m = await res.json();
        const c = v => v ? '✅' : '❌';
        cont.innerHTML = `
    <table class="table table-sm text-center">
      <thead><tr><th>Type\\Niveau</th><th>Junior</th><th>Senior</th><th>Expert</th></tr></thead>
      <tbody>
        <tr><th>Factuel</th><td>${c(m.Factuel.Junior)}</td><td>${c(m.Factuel.Senior)}</td><td>${c(m.Factuel.Expert)}</td></tr>
        <tr><th>Déclaratif</th><td>${c(m.Declaratif.Junior)}</td><td>${c(m.Declaratif.Senior)}</td><td>${c(m.Declaratif.Expert)}</td></tr>
      </tbody>
    </table>`;
    });
</script>
<?php include_once "partials/footer.php"; ?>