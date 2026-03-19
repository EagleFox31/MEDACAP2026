<?php
session_start();
// Déterminer la langue en premier
$lang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['lang']) ? $_SESSION['lang'] : (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'FR'));
$_SESSION['lang'] = $lang;
$lang_lower = ($lang === 'EN') ? 'en' : 'fr';

// Inclure le fichier de langue approprié
if ($lang === 'EN') {
    include_once "language_EN.php";
} else {
    include_once "language.php";
}

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    $countries = [
        'Afghanistan',
        'Albania',
        'Algeria',
        'American Samoa',
        'Andorra',
        'Angola',
        'Anguilla',
        'Antarctica',
        'Antigua and Barbuda',
        'Argentina',
        'Armenia',
        'Aruba',
        'Australia',
        'Austria',
        'Azerbaijan',
        'Bahamas',
        'Bahrain',
        'Bangladesh',
        'Barbados',
        'Belarus',
        'Belgium',
        'Belize',
        'Benin',
        'Bermuda',
        'Bhutan',
        'Bolivia',
        'Bosnia and Herzegowina',
        'Botswana',
        'Bouvet Island',
        'Brazil',
        'British Indian Ocean Territory',
        'Brunei Darussalam',
        'Bulgaria',
        'Burkina Faso',
        'Burundi',
        'Cambodia',
        'Cameroon',
        'Canada',
        'Cape Verde',
        'Cayman Islands',
        'Central African Republic',
        'Chad',
        'Chile',
        'China',
        'Christmas Island',
        'Cocos (Keeling) Islands',
        'Colombia',
        'Comoros',
        'Congo',
        'Congo, the Democratic Republic of the',
        'Cook Islands',
        'Costa Rica',
        "Cote d'Ivoire",
        'Croatia (Hrvatska)',
        'Cuba',
        'Cyprus',
        'Czech Republic',
        'Denmark',
        'Djibouti',
        'Dominica',
        'Dominican Republic',
        'East Timor',
        'Ecuador',
        'Egypt',
        'El Salvador',
        'Equatorial Guinea',
        'Eritrea',
        'Estonia',
        'Ethiopia',
        'Falkland Islands (Malvinas)',
        'Faroe Islands',
        'Fiji',
        'Finland',
        'France',
        'France, Metropolitan',
        'French Guiana',
        'French Polynesia',
        'French Southern Territories',
        'Gabon',
        'Gambia',
        'Georgia',
        'Germany',
        'Ghana',
        'Gibraltar',
        'Greece',
        'Greenland',
        'Grenada',
        'Guadeloupe',
        'Guam',
        'Guatemala',
        'Guinea',
        'Guinea-Bissau',
        'Guyana',
        'Haiti',
        'Heard and Mc Donald Islands',
        'Holy See (Vatican City State)',
        'Honduras',
        'Hong Kong',
        'Hungary',
        'Iceland',
        'India',
        'Indonesia',
        'Iran (Islamic Republic of)',
        'Iraq',
        'Ireland',
        'Israel',
        'Italy',
        'Jamaica',
        'Japan',
        'Jordan',
        'Kazakhstan',
        'Kenya',
        'Kiribati',
        "Korea, Democratic People's Republic of",
        'Korea, Republic of',
        'Kuwait',
        'Kyrgyzstan',
        "Lao People's Democratic Republic",
        'Latvia',
        'Lebanon',
        'Lesotho',
        'Liberia',
        'Libyan Arab Jamahiriya',
        'Liechtenstein',
        'Lithuania',
        'Luxembourg',
        'Macau',
        'Macedonia, The Former Yugoslav Republic of',
        'Madagascar',
        'Malawi',
        'Malaysia',
        'Maldives',
        'Mali',
        'Malta',
        'Marshall Islands',
        'Martinique',
        'Mauritania',
        'Mauritius',
        'Mayotte',
        'Mexico',
        'Micronesia, Federated States of',
        'Moldova, Republic of',
        'Monaco',
        'Mongolia',
        'Montserrat',
        'Morocco',
        'Mozambique',
        'Myanmar',
        'Namibia',
        'Nauru',
        'Nepal',
        'Netherlands',
        'Netherlands Antilles',
        'New Caledonia',
        'New Zealand',
        'Nicaragua',
        'Niger',
        'Nigeria',
        'Niue',
        'Norfolk Island',
        'Northern Mariana Islands',
        'Norway',
        'Oman',
        'Pakistan',
        'Palau',
        'Panama',
        'Papua New Guinea',
        'Paraguay',
        'Peru',
        'Philippines',
        'Pitcairn',
        'Poland',
        'Portugal',
        'Puerto Rico',
        'Qatar',
        'Reunion',
        'Romania',
        'Russian Federation',
        'Rwanda',
        'Saint Kitts and Nevis',
        'Saint LUCIA',
        'Saint Vincent and the Grenadines',
        'Samoa',
        'San Marino',
        'Sao Tome and Principe',
        'Saudi Arabia',
        'Senegal',
        'Seychelles',
        'Sierra Leone',
        'Singapore',
        'Slovakia (Slovak Republic)',
        'Slovenia',
        'Solomon Islands',
        'Somalia',
        'South Africa',
        'South Georgia and the South Sandwich Islands',
        'Spain',
        'Sri Lanka',
        'St. Helena',
        'St. Pierre and Miquelon',
        'Sudan',
        'Suriname',
        'Svalbard and Jan Mayen Islands',
        'Swaziland',
        'Sweden',
        'Switzerland',
        'Syrian Arab Republic',
        'Taiwan, Province of China',
        'Tajikistan',
        'Tanzania, United Republic of',
        'Thailand',
        'Togo',
        'Tokelau',
        'Tonga',
        'Trinidad and Tobago',
        'Tunisia',
        'Turkey',
        'Turkmenistan',
        'Turks and Caicos Islands',
        'Tuvalu',
        'Uganda',
        'Ukraine',
        'United Arab Emirates',
        'United Kingdom',
        'United States',
        'United States Minor Outlying Islands',
        'Uruguay',
        'Uzbekistan',
        'Vanuatu',
        'Venezuela',
        'Viet Nam',
        'Virgin Islands (British)',
        'Virgin Islands (U.S.)',
        'Wallis and Futuna Islands',
        'Western Sahara',
        'Yemen',
        'Serbia',
        'Zambia',
        'Zimbabwe'
    ];

    require_once "../../vendor/autoload.php";

    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $users = $academy->users;
    $tests = $academy->tests;
    $vehicles = $academy->vehicles;
    $allocations = $academy->allocations;

    $userId = new MongoDB\BSON\ObjectId($_SESSION["id"]);
    $user = $users->findOne(['$and' => [["_id" => $userId], ["active" => true]]]);

    if (isset($_POST["update"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $person = [
            "username" => $_POST["username"],
            "matricule" => $_POST["matricule"],
            "firstName" => ucfirst($_POST["firstName"]),
            "lastName" => strtoupper($_POST["lastName"]),
            "email" => $_POST["email"],
            "phone" => $_POST["phone"],
            "gender" => $_POST["gender"],
            "level" => $level,
            "country" => $_POST["country"],
            "birthdate" => date("d-m-Y", strtotime($_POST["birthdate"])),
            "recrutmentDate" => date("d-m-Y", strtotime($_POST["recrutmentDate"])),
            "certificate" => ucfirst($_POST["certificate"]),
            "subsidiary" => ucfirst($_POST["subsidiary"] ?? null),
            "role" => ucfirst($_POST["role"]),
            "updated" => date("d-m-Y H:i:s")
        ];
        $users->updateOne(["_id" => $id], ['$set' => $person]);
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["specialitySenior"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $users->updateOne(["_id" => $id], ['$set' => ["specialitySenior" => $_POST["specialitySenior"]]]);
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["specialityExpert"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $users->updateOne(["_id" => $id], ['$set' => ["specialityExpert" => $_POST["specialityExpert"]]]);
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["department"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $users->updateOne(["_id" => $id], ['$set' => ["department" => $_POST["department"]]]);
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["manager"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $managerId = new MongoDB\BSON\ObjectId($_POST["manager"]);
        $users->updateOne(["_id" => $id], ['$set' => ["manager" => $managerId]]);
        $users->updateOne(["users" => $id, "active" => true], ['$pull' => ["users" => $id]]);
        $users->updateOne(["_id" => $managerId], ['$push' => ["users" => $id]]);
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["brandJu"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $brandJu = $_POST["brandJu"];

        $results->updateMany(['$and' => [["user" => $user['_id'], "level" => "Junior", "active" => true]]], ['$set' => ["active" => false]]);
        $users->updateOne(["_id" => $id], ['$set' => ["brandJunior" => $brandJu]]);

        $testsMap = [
            "FactuelJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Junior", "active" => true]]]),
            "DeclaratifJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Junior", "active" => true]]])
        ];

        foreach (["Factuel", "Declaratif"] as $type) {
            $testId = new MongoDB\BSON\ObjectId($testsMap["{$type}Junior"]["_id"]);
            $tests->updateOne(["_id" => $testId], ['$set' => ["quizzes" => [], "brand" => $brandJu]]);
            foreach ($brandJu as $brand) {
                $vehicle = $vehicles->findOne(['$and' => [["brand" => $brand, "type" => $type, "level" => "Junior", "active" => true]]]);
                if ($vehicle) {
                    foreach ($vehicle->quizzes as $quiz) {
                        $tests->updateOne(["_id" => $testId], ['$addToSet' => ["quizzes" => $quiz]]);
                    }
                }
            }
            $test = $tests->findOne(["_id" => $testId]);
            $tests->updateOne(["_id" => $testId], ['$set' => ["total" => count($test["quizzes"])]]);
        }
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["brandSe"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $brandSe = $_POST["brandSe"];

        foreach (["Junior", "Senior"] as $level) {
            $results->updateMany(['$and' => [["user" => $user['_id'], "level" => $level, "active" => true]]], ['$set' => ["active" => false]]);
        }

        $users->updateOne(["_id" => $id], ['$set' => ["brandSenior" => $brandSe]]);
        foreach ($brandSe as $bran) {
            $users->updateOne(["_id" => $id], ['$addToSet' => ["brandJunior" => $bran]]);
        }

        $user = $users->findOne(['$and' => [["_id" => $id, "active" => true]]]);
        $testsMap = [
            "FactuelJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Junior", "active" => true]]]),
            "DeclaratifJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Junior", "active" => true]]]),
            "FactuelSenior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Senior", "active" => true]]]),
            "DeclaratifSenior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Senior", "active" => true]]])
        ];

        foreach (["Factuel", "Declaratif"] as $type) {
            $testJuniorId = new MongoDB\BSON\ObjectId($testsMap["{$type}Junior"]["_id"]);
            $tests->updateOne(["_id" => $testJuniorId], ['$set' => ["quizzes" => [], "brand" => $user['brandJunior']]]);
            foreach ($user['brandJunior'] as $brand) {
                $vehicle = $vehicles->findOne(['$and' => [["brand" => $brand, "type" => $type, "level" => "Junior", "active" => true]]]);
                if ($vehicle) {
                    foreach ($vehicle->quizzes as $quiz) {
                        $tests->updateOne(["_id" => $testJuniorId], ['$addToSet' => ["quizzes" => $quiz]]);
                    }
                }
            }
            $test = $tests->findOne(["_id" => $testJuniorId]);
            $tests->updateOne(["_id" => $testJuniorId], ['$set' => ["total" => count($test["quizzes"])]]);
        }

        foreach (["Factuel", "Declaratif"] as $type) {
            $test = $testsMap["{$type}Senior"];
            $testId = $test ? new MongoDB\BSON\ObjectId($test["_id"]) : null;

            if ($test) {
                $tests->updateOne(["_id" => $testId], ['$set' => ["quizzes" => [], "brand" => $brandSe]]);
            } else {
                $newTest = [
                    "quizzes" => [],
                    "user" => $id,
                    "brand" => $brandSe,
                    "type" => $type,
                    "level" => "Senior",
                    "total" => 0,
                    "active" => true,
                    "created" => date("d-m-y h:i:s")
                ];
                $insert = $tests->insertOne($newTest);
                $testId = new MongoDB\BSON\ObjectId($insert->getInsertedId());
            }

            foreach ($brandSe as $brand) {
                $vehicle = $vehicles->findOne(['$and' => [["brand" => $brand, "type" => $type, "level" => "Senior", "active" => true]]]);
                if ($vehicle) {
                    foreach ($vehicle->quizzes as $quiz) {
                        $tests->updateOne(["_id" => $testId], ['$addToSet' => ["quizzes" => $quiz]]);
                    }
                }
            }

            $test = $tests->findOne(["_id" => $testId]);
            $tests->updateOne(["_id" => $testId], ['$set' => ["total" => count($test["quizzes"])]]);

            if (!$testsMap["{$type}Senior"]) {
                $allocations->insertOne([
                    'user' => $user['_id'],
                    'test' => $testId,
                    'type' => $type,
                    'level' => "Senior",
                    'activeTest' => false,
                    'active' => false,
                    'created' => date("d-m-y h:i:s")
                ]);
            }
        }
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["brandEx"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $brandEx = $_POST["brandEx"];

        foreach (["Junior", "Senior", "Expert"] as $level) {
            $results->updateMany(['$and' => [["user" => $user['_id'], "level" => $level, "active" => true]]], ['$set' => ["active" => false]]);
        }

        $users->updateOne(["_id" => $id], ['$set' => ["brandExpert" => $brandEx]]);
        foreach ($brandEx as $bran) {
            $users->updateOne(["_id" => $id], ['$addToSet' => ["brandJunior" => $bran, "brandSenior" => $bran]]);
        }

        $user = $users->findOne(['$and' => [["_id" => $id, "active" => true]]]);
        $testsMap = [
            "FactuelJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Junior", "active" => true]]]),
            "DeclaratifJunior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Junior", "active" => true]]]),
            "FactuelSenior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Senior", "active" => true]]]),
            "DeclaratifSenior" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Senior", "active" => true]]]),
            "FactuelExpert" => $tests->findOne(['$and' => [["user" => $id, "type" => "Factuel", "level" => "Expert", "active" => true]]]),
            "DeclaratifExpert" => $tests->findOne(['$and' => [["user" => $id, "type" => "Declaratif", "level" => "Expert", "active" => true]]])
        ];

        foreach (["Factuel", "Declaratif"] as $type) {
            $testJuniorId = new MongoDB\BSON\ObjectId($testsMap["{$type}Junior"]["_id"]);
            $tests->updateOne(["_id" => $testJuniorId], ['$set' => ["quizzes" => [], "brand" => $user['brandJunior']]]);
            foreach ($user['brandJunior'] as $brand) {
                $vehicle = $vehicles->findOne(['$and' => [["brand" => $brand, "type" => $type, "level" => "Junior", "active" => true]]]);
                if ($vehicle) {
                    foreach ($vehicle->quizzes as $quiz) {
                        $tests->updateOne(["_id" => $testJuniorId], ['$addToSet' => ["quizzes" => $quiz]]);
                    }
                }
            }
            $test = $tests->findOne(["_id" => $testJuniorId]);
            $tests->updateOne(["_id" => $testJuniorId], ['$set' => ["total" => count($test["quizzes"])]]);
        }

        foreach (["Senior" => $user['brandSenior'], "Expert" => $brandEx] as $level => $brands) {
            foreach (["Factuel", "Declaratif"] as $type) {
                $test = $testsMap["{$type}$level"];
                $testId = $test ? new MongoDB\BSON\ObjectId($test["_id"]) : null;

                if ($test) {
                    $tests->updateOne(["_id" => $testId], ['$set' => ["quizzes" => [], "brand" => $brands]]);
                } else {
                    $newTest = [
                        "quizzes" => [],
                        "user" => $id,
                        "brand" => $brands,
                        "type" => $type,
                        "level" => $level,
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-y h:i:s")
                    ];
                    $insert = $tests->insertOne($newTest);
                    $testId = new MongoDB\BSON\ObjectId($insert->getInsertedId());
                }

                foreach ($brands as $brand) {
                    $vehicle = $vehicles->findOne(['$and' => [["brand" => $brand, "type" => $type, "level" => $level, "active" => true]]]);
                    if ($vehicle) {
                        foreach ($vehicle->quizzes as $quiz) {
                            $tests->updateOne(["_id" => $testId], ['$addToSet' => ["quizzes" => $quiz]]);
                        }
                    }
                }

                $test = $tests->findOne(["_id" => $testId]);
                $tests->updateOne(["_id" => $testId], ['$set' => ["total" => count($test["quizzes"])]]);

                if (!$testsMap["{$type}$level"]) {
                    $allocations->insertOne([
                        'user' => $user['_id'],
                        'test' => $testId,
                        'type' => $type,
                        'level' => $level,
                        'activeTest' => false,
                        'active' => false,
                        'created' => date("d-m-y h:i:s")
                    ]);
                }
            }
        }
        $success_msg = $success_user_edit;
    }

    if (isset($_POST["excel"])) {
        $spreadsheet = new Spreadsheet();
        $activeSheet = $spreadsheet->setActiveSheetIndex(0);
        $activeSheet->setCellValue("A1", "Prénoms")->setCellValue("B1", "Noms");

        $usersData = $users->find();
        $i = 2;
        foreach ($usersData as $row) {
            $activeSheet->setCellValue("A$i", $row->firstName)->setCellValue("B$i", $row->lastName);
            $i++;
        }

        $excel_writer = new Xlsx($spreadsheet);
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;filename=collaborateurs.xlsx");
        header("Cache-Control: max-age=0");
        $excel_writer->save("php://output");
    }

    if (isset($_POST["password"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $password = $_POST["password"];

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{6,}$/', $password)) {
            $error = "Le mot de passe doit être au moins de six caractères contenir au moins un chiffre, une lettre majuscule";
        } else {
            $users->updateOne(
                ["_id" => $id],
                ['$set' => ["password" => sha1($password), "updated" => date("d-m-Y H:i:s"), "visiblePassword" => $password]]
            );
            $success_msg = $success_user_edit;
        }
    }

    if (isset($_POST["delete"])) {
        $id = new MongoDB\BSON\ObjectId($_POST["userID"]);
        $users->updateOne(["_id" => $id], ['$set' => ["active" => false]]);
        $success_msg = $success_user_delet;
    }

?>
    <?php include_once "./partials/header.php"; ?>
    <!--begin::Title-->
    <title><?php echo $my_info ?> | CFAO Mobility Academy</title>
    <!--end::Title-->

    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/training-plan.css">

    <style>
        #user-informations {
            margin-left: 300px;
            margin-right: 300px;
        }
    </style>


    <div class="content fs-6 d-flex flex-column flex-column-fluid p-10" id="kt_content"
        style="position: relative; border-radius: 25px; overflow: hidden;">
        <div class="background-overlay"></div>

        <!-- Messages d'alerte -->
        <?php if (isset($success_msg)) : ?>
            <div class="alert alert-success alert-dismissible fade show mb-5" role="alert">
                <center><strong><?= $success_msg ?></strong></center>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_msg)) : ?>
            <div class="alert alert-danger alert-dismissible fade show mb-5" role="alert">
                <center><strong><?= $error_msg ?></strong></center>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- En-tête -->
        <div>
            <h1 class="text-dark fw-bolder fs-2"><?= $my_info ?></h1>
        </div>

        <!-- Profil Utilisateur -->
        <div id="user-informations" class="glass-effect mb-5">
            <div class="card-body p-6">
                <div class="d-flex flex-wrap flex-sm-nowrap align-items-center">
                    <div class="me-7 mb-4">
                        <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                            <img src="../../public/assets/media/avatars/300-1.png" alt="image" />
                            <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px"></div>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center mb-2">
                                <h2 class="text-gray-900 fs-2 fw-bold me-3"><?= $user->firstName ?> <?= $user->lastName ?></h2>
                                <i class="ki-duotone ki-verify fs-1 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
                                <span class="badge badge-light-success fw-bold fs-8 px-2 py-1"><?= $user->profile ?></span>
                            </div>
                            <div class="d-flex align-items-center text-gray-900 mb-2">
                                <i class="ki-duotone ki-sms fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                <span class="fw-semibold text-dark"><?= $user->email ?></span>
                            </div>
                        </div>
                    </div>
                    <?php if ($_SESSION["profile"] != "Super Admin") : ?>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#kt_modal_update_details">
                            <i class="ki-duotone ki-pencil fs-2"><span class="path1"></span><span class="path2"></span></i>
                            <?= $edit ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conteneur scrollable pour le reste du contenu -->
        <div id="user-informations" class="flex-fill custom-main-panel scrollable-vertical"
            style="display: flex; flex-direction: column; min-height: 0; height: 0; overflow-y: auto; overflow-x: hidden;">

            <!-- Informations Utilisateur -->
            <div class="glass-effect mb-5">
                <div class="card-body p-6">
                    <h3 class="fw-bold mb-5">Informations Personnelles</h3>

                    <div class="row g-5">
                        <!-- Informations de base -->
                        <div class="col-md-6">
                            <div class="border-start border-success border-3 ps-4 mb-4">
                                <h5 class="text-success fw-bold mb-3">Identité</h5>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $username ?></label>
                                        <div class="fw-bold"><?= $user->username ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $matricule ?></label>
                                        <div class="fw-bold"><?= $user->matricule ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $gender ?></label>
                                        <div class="fw-bold"><?= $user->gender ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $birthdate ?></label>
                                        <div class="fw-bold"><?= $user->birthdate ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-start border-primary border-3 ps-4">
                                <h5 class="text-primary fw-bold mb-3">Contact</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="text-muted fw-semibold"><?= $phoneNumber ?></label>
                                        <div class="fw-bold"><?= $user->phone ?></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="text-muted fw-semibold"><?= $pays ?></label>
                                        <div class="fw-bold"><?= $user->country ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations professionnelles -->
                        <div class="col-md-6">
                            <div class="border-start border-warning border-3 ps-4 mb-4">
                                <h5 class="text-warning fw-bold mb-3">Professionnel</h5>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $role ?></label>
                                        <div class="fw-bold"><?= $user->role ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $department ?></label>
                                        <div class="fw-bold"><?= $user->department ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $certificat ?></label>
                                        <div class="fw-bold"><?= $user->certificate ?></div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-muted fw-semibold"><?= $filiale ?></label>
                                        <div class="fw-bold"><?= $user->subsidiary ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-start border-info border-3 ps-4">
                                <h5 class="text-info fw-bold mb-3">Recrutement</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="text-muted fw-semibold"><?= $recrutmentDate ?></label>
                                        <div class="fw-bold"><?= $user->recrutmentDate ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Spécialités et marques -->
                    <?php if (isset($user->specialitySenior) || isset($user->specialityExpert) || in_array($_SESSION["profile"], ["Technicien", "Manager"])) : ?>
                        <div class="separator my-5"></div>
                        <h3 class="fw-bold mb-5">Spécialités & Compétences</h3>

                        <div class="row g-5">
                            <?php if (isset($user->specialitySenior) || isset($user->specialityExpert)) : ?>
                                <div class="col-md-6">
                                    <div class="border-start border-danger border-3 ps-4">
                                        <h5 class="text-danger fw-bold mb-3">Spécialités</h5>
                                        <?php if (isset($user->specialitySenior)) : ?>
                                            <div class="mb-3">
                                                <label class="text-muted fw-semibold"><?= $speciality ?> <?= $senior ?></label>
                                                <div class="fw-bold">
                                                    <?php foreach ($user['specialitySenior'] as $specialitySenior) : ?>
                                                        <span class="badge badge-light-warning me-1"><?= $specialitySenior ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($user->specialityExpert)) : ?>
                                            <div>
                                                <label class="text-muted fw-semibold"><?= $speciality ?> <?= $expert ?></label>
                                                <div class="fw-bold">
                                                    <?php foreach ($user['specialityExpert'] as $specialityExpert) : ?>
                                                        <span class="badge badge-light-danger me-1"><?= $specialityExpert ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array($_SESSION["profile"], ["Technicien", "Manager"]) && ($_SESSION["profile"] != "Manager" || $_SESSION["test"] == true)) : ?>
                                <div class="col-md-6">
                                    <div class="border-start border-dark border-3 ps-4">
                                        <h5 class="text-dark fw-bold mb-3">Marques</h5>
                                        <?php foreach (["Junior" => $junior, "Senior" => $senior, "Expert" => $expert] as $level => $label) : ?>
                                            <?php if (isset($user["brand$level"])) : ?>
                                                <div class="mb-3">
                                                    <label class="text-muted fw-semibold"><?= $brand ?> <?= $label ?></label>
                                                    <div class="fw-bold">
                                                        <?php foreach ($user["brand$level"] as $userBrands) : ?>
                                                            <span class="badge badge-light-primary me-1"><?= $userBrands ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal de modification -->
            <div class="modal fade" id="kt_modal_update_details" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content glass-effect">
                        <form class="form" method="POST" id="kt_modal_update_user_form">
                            <input type="hidden" name="userID" value="<?= $user->_id ?>">

                            <div class="modal-header">
                                <h2 class="fs-2 fw-bolder"><?= $editer_data ?></h2>
                                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                            </div>

                            <div class="modal-body py-10 px-lg-10">
                                <div class="row g-5">
                                    <!-- Colonne gauche -->
                                    <div class="col-md-6">
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $username ?></label>
                                            <input type="text" class="form-control form-control-solid" name="username" value="<?= $user->username ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $matricule ?></label>
                                            <input type="text" class="form-control form-control-solid" name="matricule" value="<?= $user->matricule ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $prenoms ?></label>
                                            <input type="text" class="form-control form-control-solid" name="firstName" value="<?= $user->firstName ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $noms ?></label>
                                            <input type="text" class="form-control form-control-solid" name="lastName" value="<?= $user->lastName ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $Email ?></label>
                                            <input type="email" class="form-control form-control-solid" name="email" value="<?= $user->email ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $phoneNumber ?></label>
                                            <input type="text" class="form-control form-control-solid" name="phone" value="<?= $user->phone ?>" />
                                        </div>
                                    </div>

                                    <!-- Colonne droite -->
                                    <div class="col-md-6">
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $birthdate ?></label>
                                            <input type="text" class="form-control form-control-solid" name="birthdate" value="<?= $user->birthdate ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $certificat ?></label>
                                            <input type="text" class="form-control form-control-solid" name="certificate" value="<?= $user->certificate ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $role ?></label>
                                            <input type="text" class="form-control form-control-solid" name="role" value="<?= $user->role ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $recrutmentDate ?></label>
                                            <input type="text" class="form-control form-control-solid" name="recrutmentDate" value="<?= $user->recrutmentDate ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $Password ?></label>
                                            <input type="password" class="form-control form-control-solid" name="password" value="<?= $user->visiblePassword ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $gender ?></label>
                                            <select name="gender" class="form-select form-select-solid">
                                                <option value="<?= $user->gender ?>"><?= $user->gender ?></option>
                                                <option value="Feminin"><?= $female ?></option>
                                                <option value="Masculin"><?= $male ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="fv-row mb-5">
                                    <label class="fs-6 fw-bold mb-2"><?= $pays ?></label>
                                    <select name="country" class="form-select form-select-solid">
                                        <option value="<?= $user->country ?>"><?= $user->country ?></option>
                                        <?php foreach ($countries as $country) : ?>
                                            <option value="<?= $country ?>"><?= $country ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($error)) : ?>
                                        <span class="text-danger"><?= $error ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" name="update" class="btn btn-primary"><?= $valider ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include_once "partials/footer.php"; ?>
<?php } ?>
